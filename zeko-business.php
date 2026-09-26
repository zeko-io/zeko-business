<?php
/**
 * Plugin Name:       Zeko Business Directory
 * Plugin URI:        https://ozconsultz.com/zeko-business
 * Description:       Advanced business profiles and directory for the Zeko ecosystem.
 * Version:           1.0.0
 * Requires PHP:      7.4
 * Requires at least: 5.8
 * Tested up to:      7.1.2
 * Author:            Zeko Team
 * Author URI:        https://ozconsultz.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zeko-business
 * Domain Path:       /languages
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE;

use ZBE\DB\Schema;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ZBE_VERSION' ) ) {
	define( 'ZBE_VERSION', '1.0.0' );
}
if ( ! defined( 'ZBE_DB_VERSION' ) ) {
	define( 'ZBE_DB_VERSION', '1.7.2' );
}
define( 'ZBE_PLUGIN_FILE', __FILE__ );
define( 'ZBE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZBE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| PSR-4 autoloader:  ZBE\Foo\Bar => {plugin dir}/Foo/Bar.php
|--------------------------------------------------------------------------
*/
spl_autoload_register(
	static function ( string $class ): void {
		if ( 0 !== strpos( $class, 'ZBE\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( 'ZBE\\' ) );
		$file     = ZBE_PLUGIN_DIR . str_replace( '\\', '/', $relative ) . '.php';

		if ( ! is_readable( $file ) ) {
			// WPCS-style file names: class-zeko-X.php / lowercase kebab names.
			$file = ZBE_PLUGIN_DIR . str_replace( '\\', '/', dirname( $relative ) ) . '/class-' . strtolower( str_replace( '_', '-', basename( $relative ) ) ) . '.php';
		}
		if ( ! is_readable( $file ) ) {
			$file = ZBE_PLUGIN_DIR . str_replace( '\\', '/', dirname( $relative ) ) . '/' . strtolower( str_replace( '_', '-', basename( $relative ) ) ) . '.php';
		}

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Main plugin controller.
 */
final class Plugin {

	const CAPABILITIES = array(
		'manage_zbp'                  => true,
		'edit_business'               => true,
		'read_business'               => true,
		'delete_business'             => true,
		'edit_businesses'             => true,
		'edit_others_businesses'      => true,
		'publish_businesses'          => true,
		'read_private_businesses'     => true,
		'delete_businesses'           => true,
		'delete_private_businesses'   => true,
		'delete_published_businesses' => true,
		'delete_others_businesses'    => true,
		'edit_private_businesses'     => true,
		'edit_published_businesses'   => true,
	);

	/**
	 * Wire.
	 */
	public static function wire(): void {
		register_activation_hook( ZBE_PLUGIN_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( ZBE_PLUGIN_FILE, array( __CLASS__, 'deactivate' ) );

		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_filter( 'user_has_cap', array( __CLASS__, 'filter_user_has_cap' ), 10, 3 );

		if ( class_exists( '\ZBE\Integration\Hooks' ) ) {
			Integration\Hooks::init();
		}

		if ( class_exists( '\ZBE\Domain\Cron' ) ) {
			Domain\Cron::init();
		}

		// Background migration batches + import-log retention sweep.
		if ( class_exists( '\ZBE\Import\Migrator' ) ) {
			Import\Migrator::init_hooks();
		}
	}

	/**
	 * Activation: install schema, register rewrite-backed objects, create
	 * pages, grant capabilities and seed a demo business.
	 */
	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		ob_start();

		self::register_post_types();
		self::register_taxonomies();

		Schema::create_tables();
		Schema::fix_nullable_columns();
		Schema::ensure_columns();
		update_option( 'zbe_db_version', ZBE_DB_VERSION, false );

		// Seed default service categories.
		if ( class_exists( '\ZBE\Repository\ServiceCategoryRepository' ) ) {
			( new \ZBE\Repository\ServiceCategoryRepository() )->seed_defaults();
		}

		self::add_admin_capabilities();
		self::create_pages();
		self::maybe_seed_demo_business();

		if ( class_exists( '\ZBE\Domain\Cron' ) ) {
			Domain\Cron::ensure_scheduled();
		}

		// Register custom rewrite rules before flushing so clean URLs.
		// (/businesses/slug/, /business-portal/, /business-create/).
		// are available immediately after activation.
		if ( class_exists( '\ZBE\Frontend\PublicController' ) ) {
			Frontend\PublicController::rewrite_rules();
		}

		flush_rewrite_rules();

		// Guarantee: if flush_rewrite_rules() didn't persist during the.
		// activation context (admin-only, incomplete lifecycle), the next.
		// admin_init will detect the flag and flush again.
		set_transient( 'zbe_flush_rewrite', 1, 60 );

		ob_end_clean();
	}

	/**
	 * Deactivate.
	 */
	public static function deactivate(): void {
		if ( class_exists( '\ZBE\Domain\Cron' ) ) {
			Domain\Cron::unschedule();
		}

		// Abort any in-flight/queued migration batches.
		if ( class_exists( '\ZBE\Import\Migrator' ) ) {
			$migrator = new \ZBE\Import\Migrator();

			foreach ( $migrator->get_all_progress() as $source => $ignored ) {
				$migrator->cancel_job( $source );
			}
		}

		// Register custom rewrite rules before flushing so they are.
		// removed cleanly. Without this, stale rules can persist.
		if ( class_exists( '\ZBE\Frontend\PublicController' ) ) {
			Frontend\PublicController::rewrite_rules();
		}

		flush_rewrite_rules();
	}

	/**
	 * Booted on plugins_loaded @ priority 5.
	 */
	public static function boot(): void {
		load_plugin_textdomain(
			'zeko-business',
			false,
			dirname( plugin_basename( ZBE_PLUGIN_FILE ) ) . '/languages'
		);

		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );

		// One-time data migration: services now live in Zeko Shop.
		add_action( 'admin_init', array( __CLASS__, 'migrate_services_to_shop' ), 10 );

		// Always self-heal missing/mismatched columns (idempotent) so schema.
		// additions shipped without a version bump still get applied on admin.
		add_action( 'admin_init', array( '\ZBE\DB\Schema', 'ensure_columns' ), 9 );

		// Safety net: ensure all plugin pages exist even if create_pages().
		// was interrupted during activation or if a new version added pages.
		add_action( 'admin_init', array( __CLASS__, 'ensure_pages' ), 5 );

		// AJAX handlers must be registered on ALL requests (including.
		// admin-ajax.php which is an admin context). Register them here.
		// before the is_admin() early return.
		if ( class_exists( '\ZBE\Frontend\AjaxHandler' ) ) {
			\ZBE\Frontend\AjaxHandler::init();
			\ZBE\Frontend\AjaxHandler::init_hooks();
		}

		// GDPR privacy exporters/erasers (Tools → Erase Personal Data).
		if ( class_exists( '\ZBE\Privacy\Gdpr' ) ) {
			\ZBE\Privacy\Gdpr::init();
		}

		// Register the front-end widgets (working hours, etc.).
		add_action( 'widgets_init', array( '\ZBE\Widgets\HoursWidget', 'register' ) );

		// The custom business rewrite rules (/businesses/{slug}/,.
		// /business-portal/) must be registered in EVERY context — admin, CLI.
		// and front-end. PublicController::init() below is front-end only, so.
		// an admin rewrite flush (permalink save, another plugin's update).
		// would otherwise regenerate the cached rules without them and 404.
		// every business URL. ensure_rewrite_rules_registered() is idempotent.
		if ( class_exists( '\ZBE\Frontend\PublicController' ) ) {
			\ZBE\Frontend\PublicController::ensure_rewrite_rules_registered();
		}

		if ( is_admin() ) {
			if ( class_exists( '\ZBE\Admin\AdminController' ) ) {
				\ZBE\Admin\AdminController::init();
			}
			return;
		}

		if ( class_exists( '\ZBE\Frontend\PublicController' ) ) {
			\ZBE\Frontend\PublicController::init();
		}
	}

	/**
	 * Runs schema upgrades when ZBE_DB_VERSION changes.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'zbe_db_version' ) !== ZBE_DB_VERSION ) {
			Schema::create_tables();
			Schema::fix_nullable_columns();
			Schema::ensure_columns();
			Schema::add_missing_indexes();
			update_option( 'zbe_db_version', ZBE_DB_VERSION, false );

			// Flush rewrite rules on version bump so any new/changed.
			// rewrite rules take effect without a manual permalink save.
			if ( class_exists( '\ZBE\Frontend\PublicController' ) ) {
				Frontend\PublicController::rewrite_rules();
			}
			flush_rewrite_rules();
		}

		// Idempotent; no-ops once manage_zbp is present.
		self::add_admin_capabilities();
	}

	/**
	 * One-time migration of services from the legacy zbp_services table into
	 * Zeko Shop's zeko_shop_services table (external_type = 'business').
	 * Runs on every admin_init but only acts a single time.
	 */
	public static function migrate_services_to_shop(): void {
		if ( get_option( 'zbe_services_migrated' ) ) {
			return;
		}

		if ( ! class_exists( 'Zeko_Shop' ) || ! method_exists( 'Zeko_Shop', 'instance' ) ) {
			return;
		}

		try {
			$shop = \Zeko_Shop::instance()->get_db();
		} catch ( \Throwable $e ) { // phpcs:ignore
			return;
		}

		if ( ! $shop || ! method_exists( $shop, 'import_service' ) ) {
			return;
		}

		global $wpdb;
		$legacy = $wpdb->prefix . 'zbp_services';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT * FROM {$legacy}", ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		foreach ( (array) $rows as $row ) {
			$business_id = absint( $row['business_id'] ?? 0 );
			if ( $business_id < 1 ) {
				continue;
			}

			$shop->import_service(
				array(
					'service_id'        => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'external_type'     => 'business',
					'external_id'       => $business_id,
					'title'             => isset( $row['name'] ) ? $row['name'] : '',
					'short_description' => isset( $row['short_description'] ) ? $row['short_description'] : '',
					'description'       => isset( $row['description'] ) ? $row['description'] : '',
					'price'             => isset( $row['price'] ) ? (float) $row['price'] : 0,
					'price_type'        => isset( $row['price_type'] ) ? $row['price_type'] : 'fixed',
					'price_note'        => isset( $row['price_note'] ) ? $row['price_note'] : '',
					'image_id'          => isset( $row['image_id'] ) ? (int) $row['image_id'] : 0,
					'is_active'         => isset( $row['is_active'] ) ? (int) $row['is_active'] : 1,
					'is_featured'       => isset( $row['is_featured'] ) ? (int) $row['is_featured'] : 0,
					'category'          => isset( $row['category'] ) ? $row['category'] : '',
					'duration'          => isset( $row['duration'] ) ? $row['duration'] : '',
					'tags'              => isset( $row['tags'] ) ? $row['tags'] : '',
					'sort_order'        => isset( $row['sort_order'] ) ? (int) $row['sort_order'] : 0,
					'created_at'        => isset( $row['date_created'] ) ? $row['date_created'] : '',
				)
			);
		}

		update_option( 'zbe_services_migrated', 1, false );
	}

	/**
	 * Ensure all plugin pages exist. Runs on every admin_init so pages
	 * that were missing (e.g. interrupted activation, new version) are
	 * auto-created without manual intervention.
	 */
	public static function ensure_pages(): void {
		$stored       = get_option( 'zbe_pages', array() );
		$known        = array( 'directory', 'submit', 'dashboard', 'portal' );
		$needs_create = false;

		foreach ( $known as $key ) {
			$page_id = isset( $stored[ $key ] ) ? (int) $stored[ $key ] : 0;
			if ( $page_id <= 0 || ! get_post( $page_id ) instanceof \WP_Post ) {
				$needs_create = true;
				break;
			}
		}

		if ( $needs_create ) {
			self::create_pages();
		}

		// Flush rewrite rules if flagged (e.g. after activation where.
		// flush_rewrite_rules() may not have persisted).
		if ( get_transient( 'zbe_flush_rewrite' ) ) {
			delete_transient( 'zbe_flush_rewrite' );
			if ( class_exists( '\ZBE\Frontend\PublicController' ) ) {
				Frontend\PublicController::rewrite_rules();
			}
			flush_rewrite_rules();
		}
	}

	/**
	 * Post types.
	 */
	public static function register_post_types(): void {
		if ( post_type_exists( 'zeko_business' ) ) {
			return;
		}

		register_post_type(
			'zeko_business',
			array(
				'labels'              => array(
					'name'                  => __( 'Businesses', 'zeko-business' ),
					'singular_name'         => __( 'Business', 'zeko-business' ),
					'menu_name'             => __( 'Business Directory', 'zeko-business' ),
					'all_items'             => __( 'All Businesses', 'zeko-business' ),
					'add_new'               => __( 'Add New', 'zeko-business' ),
					'add_new_item'          => __( 'Add New Business', 'zeko-business' ),
					'edit_item'             => __( 'Edit Business', 'zeko-business' ),
					'new_item'              => __( 'New Business', 'zeko-business' ),
					'view_item'             => __( 'View Business', 'zeko-business' ),
					'view_items'            => __( 'View Businesses', 'zeko-business' ),
					'search_items'          => __( 'Search Businesses', 'zeko-business' ),
					'not_found'             => __( 'No businesses found.', 'zeko-business' ),
					'not_found_in_trash'    => __( 'No businesses found in Trash.', 'zeko-business' ),
					'archives'              => __( 'Business Archives', 'zeko-business' ),
					'featured_image'        => __( 'Cover Image', 'zeko-business' ),
					'set_featured_image'    => __( 'Set cover image', 'zeko-business' ),
					'remove_featured_image' => __( 'Remove cover image', 'zeko-business' ),
					'use_featured_image'    => __( 'Use as cover image', 'zeko-business' ),
				),
				'description'         => __( 'Business profiles in the Zeko directory.', 'zeko-business' ),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-building',
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => true,
				'has_archive'         => true,
				'show_in_rest'        => true,
				'rest_base'           => 'businesses',
				'hierarchical'        => false,
				'exclude_from_search' => false,
				'capability_type'     => array( 'business', 'businesses' ),
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ),
				'rewrite'             => array(
					'slug'       => 'business',
					'with_front' => false,
					'feeds'      => true,
				),
			)
		);
	}

	/**
	 * Taxonomies.
	 */
	public static function register_taxonomies(): void {
		if ( taxonomy_exists( 'business_category' ) ) {
			return;
		}

		register_taxonomy(
			'business_category',
			array( 'zeko_business' ),
			array(
				'labels'             => array(
					'name'              => __( 'Business Categories', 'zeko-business' ),
					'singular_name'     => __( 'Business Category', 'zeko-business' ),
					'menu_name'         => __( 'Categories', 'zeko-business' ),
					'all_items'         => __( 'All Categories', 'zeko-business' ),
					'edit_item'         => __( 'Edit Category', 'zeko-business' ),
					'view_item'         => __( 'View Category', 'zeko-business' ),
					'update_item'       => __( 'Update Category', 'zeko-business' ),
					'add_new_item'      => __( 'Add New Category', 'zeko-business' ),
					'new_item_name'     => __( 'New Category Name', 'zeko-business' ),
					'parent_item'       => __( 'Parent Category', 'zeko-business' ),
					'parent_item_colon' => __( 'Parent Category:', 'zeko-business' ),
					'search_items'      => __( 'Search Categories', 'zeko-business' ),
					'not_found'         => __( 'No categories found.', 'zeko-business' ),
				),
				'hierarchical'       => true,
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_tagcloud'      => false,
				'show_in_quick_edit' => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rest_base'          => 'business-categories',
				'capabilities'       => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_businesses',
				),
				'rewrite'            => array(
					'slug'       => 'business-category',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Administrators always pass business capability checks via
	 * manage_options, even if role caps get out of sync.
	 *
	 * @param array $allcaps All capabilities of the user.
	 * @param array $caps Requested primitive/meta capabilities.
	 * @param array $args Context of the cap check.
	 */
	public static function filter_user_has_cap( array $allcaps, array $caps, array $args ): array {
		unset( $args );

		if ( empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}

		foreach ( $caps as $cap ) {
			if ( 'manage_zbp' === $cap || self::is_business_capability( (string) $cap ) ) {
				$allcaps[ $cap ] = true;
			}
		}

		return $allcaps;
	}

	/**
	 * Business capability.
	 *
	 * @param string $cap Cap.
	 */
	private static function is_business_capability( string $cap ): bool {
		return 1 === preg_match( '/^(edit|read|delete|publish)(_others|_private|_published)?_business(es)?$/', $cap );
	}

	/**
	 * Add admin capabilities.
	 */
	public static function add_admin_capabilities(): void {
		$role = get_role( 'administrator' );

		if ( ! $role instanceof \WP_Role || $role->has_cap( 'manage_zbp' ) ) {
			return;
		}

		foreach ( self::CAPABILITIES as $cap => $grant ) {
			$role->add_cap( $cap, $grant );
		}
	}

	/**
	 * Create front-end pages for the directory, submission form and user
	 * dashboard. IDs are cached in the zbe_pages option.
	 */
	public static function create_pages(): void {
		$pages = array(
			'directory' => array(
				'title'   => __( 'Business Directory', 'zeko-business' ),
				'slug'    => 'business-directory',
				'content' => "[zbp_directory]\n",
			),
			'submit'    => array(
				'title'   => __( 'Add Your Business', 'zeko-business' ),
				'slug'    => 'add-business',
				'content' => "[zbp_create_business]\n",
			),
			'dashboard' => array(
				'title'   => __( 'My Businesses', 'zeko-business' ),
				'slug'    => 'my-businesses',
				'content' => "[zbp_my_businesses]\n",
			),
			'portal'    => array(
				'title'   => __( 'Business Portal', 'zeko-business' ),
				'slug'    => 'business-portal',
				'content' => "[zbp_business_portal]\n",
			),
		);

		$stored = get_option( 'zbe_pages', array() );

		foreach ( $pages as $key => $page ) {
			$page_id = isset( $stored[ $key ] ) ? (int) $stored[ $key ] : 0;

			if ( $page_id > 0 && get_post( $page_id ) instanceof \WP_Post ) {
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'     => $page['title'],
					'post_name'      => $page['slug'],
					'post_content'   => $page['content'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				),
				true
			);

			if ( ! is_wp_error( $page_id ) && $page_id > 0 ) {
				if ( function_exists( 'zeko_mark_plugin_page' ) ) {
					zeko_mark_plugin_page( $page_id, 'business' );
				}
				$stored[ $key ] = (int) $page_id;
			}
		}

		update_option( 'zbe_pages', $stored, false );
	}

	/**
	 * Return the permalink for a plugin-created page.
	 * Falls back to the slug-based URL if the page hasn't been created yet.
	 *
	 * @return string Full URL.
	 * @param string $key Page key (directory, submit, dashboard, portal).
	 * @param string $args Optional query string, e.g. 'business_id=5'.
	 */
	public static function page_url( string $key, string $args = '' ): string {
		$pages   = get_option( 'zbe_pages', array() );
		$page_id = isset( $pages[ $key ] ) ? (int) $pages[ $key ] : 0;

		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $args ? $url . '?' . $args : $url;
			}
		}

		// Fallback: use the slug directly (works if rewrite rules are flushed).
		$slugs = array(
			'directory' => 'business-directory',
			'submit'    => 'add-business',
			'dashboard' => 'my-businesses',
			'portal'    => 'business-portal',
		);

		$slug = isset( $slugs[ $key ] ) ? $slugs[ $key ] : $key;
		$url  = home_url( '/' . $slug . '/' );

		return $args ? $url . '?' . $args : $url;
	}

	/**
	 * Seed the demo business on activation only when the setting is enabled.
	 * Off by default (opt-in): nothing is seeded on activation unless an admin
	 * enables "Demo Data on Activation" under Settings → General, in which
	 * case one sample business owned by the current admin is created.
	 */
	public static function maybe_seed_demo_business(): void {
		$settings = get_option( 'zbe_settings', array() );
		$general  = isset( $settings['general'] ) && is_array( $settings['general'] ) ? $settings['general'] : array();
		$enabled  = isset( $general['demo_on_activation'] ) ? (int) $general['demo_on_activation'] : 0;

		if ( $enabled ) {
			self::create_demo_business();
		}
	}

	/**
	 * Seed one realistic demo business owned by the acting administrator.
	 */
	public static function create_demo_business(): void {
		global $wpdb;

		if ( get_option( 'zbe_demo_business_id' ) ) {
			return;
		}
		$owner_id = get_current_user_id();

		if ( ! $owner_id ) {
			$admins   = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			);
			$owner_id = $admins ? (int) reset( $admins ) : 0;
		}

		if ( ! $owner_id ) {
			return;
		}

		$name = 'Zeko Central Café & Bistro';
		$slug = 'zeko-central-cafe-bistro';

		$post_id = wp_insert_post(
			array(
				'post_title'     => $name,
				'post_name'      => $slug,
				'post_author'    => $owner_id,
				'post_status'    => 'publish',
				'post_type'      => 'zeko_business',
				'post_excerpt'   => __(
					'Hand-roasted coffee, seasonal Californian plates and a sunny garden patio in the heart of San Francisco.',
					'zeko-business'
				),
				'post_content'   => '<h2>' . esc_html__( 'Welcome to Zeko Central', 'zeko-business' ) . '</h2>'
					. '<p>' . esc_html__(
						'Zeko Central Café & Bistro has been serving hand-roasted coffee and seasonal California cuisine in the heart of San Francisco since 2016. Our beans are sourced directly from small farms in Ethiopia and Colombia and roasted weekly in-house.',
						'zeko-business'
					) . '</p>'
					. '<p>' . esc_html__(
						'Stop by for a morning flat white, stay for lunch on our garden patio, and join us on Friday evenings for live acoustic sets and local craft beer.',
						'zeko-business'
					) . '</p>',
				'comment_status' => 'open',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		$hours = array(
			'monday'    => array(
				'open'   => '07:00',
				'close'  => '21:00',
				'closed' => false,
			),
			'tuesday'   => array(
				'open'   => '07:00',
				'close'  => '21:00',
				'closed' => false,
			),
			'wednesday' => array(
				'open'   => '07:00',
				'close'  => '21:00',
				'closed' => false,
			),
			'thursday'  => array(
				'open'   => '07:00',
				'close'  => '21:00',
				'closed' => false,
			),
			'friday'    => array(
				'open'   => '07:00',
				'close'  => '22:00',
				'closed' => false,
			),
			'saturday'  => array(
				'open'   => '08:00',
				'close'  => '22:00',
				'closed' => false,
			),
			'sunday'    => array(
				'open'   => null,
				'close'  => null,
				'closed' => true,
			),
		);

		$social = array(
			'facebook'  => 'https://www.facebook.com/zekocentral',
			'instagram' => 'https://www.instagram.com/zekocentral',
			'twitter'   => 'https://x.com/zekocentral',
			'linkedin'  => 'https://www.linkedin.com/company/zekocentral',
		);

		$extra = array(
			'price_range'          => '$$',
			'year_established'     => 2016,
			'seating_capacity'     => 64,
			'wifi'                 => true,
			'outdoor_seating'      => true,
			'accepts_reservations' => true,
			'delivery'             => true,
			'takeaway'             => true,
			'parking'              => 'street',
			'payment_methods'      => array( 'visa', 'mastercard', 'amex', 'cash', 'apple_pay' ),
			'languages'            => array( 'en', 'es' ),
		);

		$data = array(
			'post_id'        => (int) $post_id,
			'owner_id'       => $owner_id,
			'slug'           => $slug,
			'name'           => $name,
			'status'         => 'active',
			'phone'          => '+1 (415) 555-0148',
			'website'        => 'https://zekocentral.example.com',
			'email'          => 'hello@zekocentral.example.com',
			'whatsapp'       => '+14155550148',
			'address'        => '218 Market Street, Suite 4',
			'city'           => 'San Francisco',
			'state'          => 'California',
			'country'        => 'United States',
			'zip'            => '94103',
			'lat'            => 37.7765123,
			'lng'            => -122.4172891,
			'follower_count' => 128,
			'review_count'   => 42,
			'avg_rating'     => 4.68,
			'view_count'     => 1247,
			'is_featured'    => 1,
			'is_verified'    => 1,
			'is_claimed'     => 1,
			'claimed_by'     => $owner_id,
			'claimed_date'   => current_time( 'mysql' ),
			'is_sponsored'   => 0,
			'plan'           => 'enterprise',
			'business_hours' => wp_json_encode( $hours ),
			'social_links'   => wp_json_encode( $social ),
			'extra_data'     => wp_json_encode( $extra ),
			'timezone'       => 'America/Los_Angeles',
			'group_id'       => 0,
		);

		$formats = array_map(
			static function ( $value ): string {
				if ( is_int( $value ) ) {
					return '%d';
				}
				return is_float( $value ) ? '%f' : '%s';
			},
			$data
		);

		$inserted = $wpdb->insert( $wpdb->prefix . 'zbp_businesses', $data, $formats ); // phpcs:ignore WordPress.DB

		if ( ! $inserted ) {
			return;
		}

		$business_id = (int) $wpdb->insert_id;

		update_post_meta( $post_id, '_zbe_business_id', $business_id );
		update_post_meta( $post_id, '_zbe_is_demo', 1 );

		wp_set_object_terms( $post_id, array( 'Restaurants', 'Coffee & Tea' ), 'business_category' );

		update_option( 'zbe_demo_business_id', $business_id, false );
	}
}

Plugin::wire();
