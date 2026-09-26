<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Admin;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class AdminController. */
class AdminController {
	/**
	 * Initialized.
	 *
	 * @var bool Initialized.
	 */
	private static bool $initialized = false;

	/**
	 * Init.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'migration_advice_notice' ) );
		add_filter( 'manage_edit-zeko_business_columns', array( __CLASS__, 'custom_columns' ) );
		add_action( 'manage_zeko_business_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-zeko_business_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_columns' ) );
		add_filter( 'bulk_actions-edit-zeko_business', array( __CLASS__, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-zeko_business', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
	}

	/**
	 * Menus.
	 */
	public static function register_menus(): void {
		add_menu_page(
			'Businesses',
			'Businesses',
			'manage_zbp',
			'zbp-businesses',
			function () {
				include ZBE_PLUGIN_DIR . '/Admin/Pages/class-dashboardpage.php';
				\ZBE\Admin\Pages\DashboardPage::render();
			},
			'dashicons-building',
			30
		);

		$parent = 'edit.php?post_type=zeko_business';

		add_submenu_page(
			$parent,
			'Dashboard',
			'Dashboard',
			'manage_zbp',
			'zbp-dashboard',
			function () {
				include ZBE_PLUGIN_DIR . '/Admin/Pages/class-dashboardpage.php';
				\ZBE\Admin\Pages\DashboardPage::render();
			}
		);

		add_submenu_page(
			$parent,
			'Claims',
			'Claims',
			'manage_zbp',
			'zbp-claims',
			function () {
				include ZBE_PLUGIN_DIR . '/Admin/Pages/class-claimspage.php';
				\ZBE\Admin\Pages\ClaimsPage::render();
			}
		);

		add_submenu_page(
			$parent,
			'Verifications',
			'Verifications',
			'manage_zbp',
			'zbp-verifications',
			function () {
				include ZBE_PLUGIN_DIR . '/Admin/Pages/class-verificationspage.php';
				\ZBE\Admin\Pages\VerificationsPage::render();
			}
		);

		add_submenu_page(
			$parent,
			'Analytics',
			'Analytics',
			'manage_zbp',
			'zbp-analytics',
			function () {
				include ZBE_PLUGIN_DIR . '/Admin/Pages/class-analyticspage.php';
				\ZBE\Admin\Pages\AnalyticsPage::render();
			}
		);

		add_submenu_page(
			$parent,
			'Settings',
			'Settings',
			'manage_zbp',
			'zbp-settings',
			function () {
				include ZBE_PLUGIN_DIR . '/Admin/Pages/class-settingspage.php';
				\ZBE\Admin\Pages\SettingsPage::render();
			}
		);

		add_submenu_page(
			$parent,
			'Import / Export',
			'Import / Export',
			'manage_zbp',
			'zbp-import-export',
			function () {
				include ZBE_PLUGIN_DIR . '/Admin/Pages/class-importexportpage.php';
				\ZBE\Admin\Pages\ImportExportPage::render();
			}
		);

		add_submenu_page(
			$parent,
			'Demo Data',
			'Demo Data',
			'manage_zbp',
			'zbp-demo-data',
			function () {
				include ZBE_PLUGIN_DIR . '/Admin/Pages/class-demodatapage.php';
				\ZBE\Admin\Pages\DemoDataPage::render();
			}
		);
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'zbp-' ) === false && strpos( $hook, 'zeko_business' ) === false ) {
			return;
		}

		wp_enqueue_style( 'zbp-admin', ZBE_PLUGIN_URL . '/Admin/css/admin.css', array(), ZBE_VERSION );
		wp_enqueue_script( 'zbp-admin', ZBE_PLUGIN_URL . '/Admin/js/admin.js', array( 'jquery' ), ZBE_VERSION, true );

		wp_localize_script(
			'zbp-admin',
			'zbpAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'zbp_admin_nonce' ),
				'i18n'    => array(
					'confirmDelete' => 'Are you sure you want to delete this?',
					'confirmBulk'   => 'Are you sure you want to perform this action?',
					'saved'         => 'Settings saved.',
					'error'         => 'An error occurred.',
				),
			)
		);
	}

	/**
	 * Custom columns.
	 *
	 * @param array $columns Columns.
	 */
	public static function custom_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $val ) {
			$new[ $key ] = $val;
			if ( 'title' === $key ) {
				$new['zbp_status']   = __( 'Status', 'zeko-business' );
				$new['zbp_email']    = __( 'Email', 'zeko-business' );
				$new['zbp_city']     = __( 'City', 'zeko-business' );
				$new['zbp_rating']   = __( 'Rating', 'zeko-business' );
				$new['zbp_plan']     = __( 'Plan', 'zeko-business' );
				$new['zbp_featured'] = __( 'Featured', 'zeko-business' );
				$new['zbp_verified'] = __( 'Verified', 'zeko-business' );
				$new['zbp_views']    = __( 'Views', 'zeko-business' );
			}
		}
		return $new;
	}

	/**
	 * Business rows.
	 *
	 * @var array Business rows.
	 */
	private static array $business_rows = array();

	/**
	 * Business row.
	 *
	 * @param int $post_id Post id.
	 */
	private static function get_business_row( int $post_id ): ?object {
		if ( ! array_key_exists( $post_id, self::$business_rows ) ) {
			self::$business_rows[ $post_id ] = Services::businesses()->get_by_post_id( $post_id );
		}

		return self::$business_rows[ $post_id ];
	}

	/**
	 * Render column.
	 *
	 * @param string $column Column.
	 * @param int    $post_id Post id.
	 */
	public static function render_column( string $column, int $post_id ): void {
		$biz = self::get_business_row( $post_id );

		if ( ! $biz ) {
			echo '—';
			return;
		}

		switch ( $column ) {
			case 'zbp_status':
				printf( '<span class="zbp-badge zbp-badge--%s">%s</span>', esc_attr( $biz->status ), esc_html( ucfirst( $biz->status ) ) );
				break;
			case 'zbp_email':
				echo esc_html( $biz->email ?: '—' );
				break;
			case 'zbp_city':
				echo esc_html( $biz->city ?: '—' );
				break;
			case 'zbp_rating':
				$rating = (float) $biz->avg_rating;
				$full   = (int) $rating;
				echo esc_html( str_repeat( '★', $full ) . str_repeat( '☆', 5 - $full ) . ' ' . number_format( $rating, 1 ) );
				break;
			case 'zbp_plan':
				printf( '<span class="zbp-badge zbp-badge--plan-%s">%s</span>', esc_attr( $biz->plan ), esc_html( ucfirst( $biz->plan ) ) );
				break;
			case 'zbp_featured':
				echo $biz->is_featured ? '★' : '—';
				break;
			case 'zbp_verified':
				echo $biz->is_verified ? '✓' : '—';
				break;
			case 'zbp_views':
				echo number_format( (int) $biz->view_count );
				break;
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $columns Columns.
	 */
	public static function sortable_columns( array $columns ): array {
		$columns['zbp_rating'] = 'zbp_rating';
		$columns['zbp_views']  = 'zbp_views';
		return $columns;
	}

	/**
	 * Sort columns.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function sort_columns( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = (string) $query->get( 'orderby' );

		if ( ! in_array( $orderby, array( 'zbp_rating', 'zbp_views' ), true ) ) {
			return;
		}

		global $wpdb;
		$table     = $wpdb->prefix . 'zbp_businesses';
		$alias     = 'zbe_biz';
		$column    = 'zbp_rating' === $orderby ? 'avg_rating' : 'view_count';
		$direction = 'asc' === strtolower( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';

		add_filter(
			'posts_join',
			static function ( $join ) use ( $table, $wpdb, $alias ) {
				if ( false === strpos( $join, "{$table} {$alias}" ) && false === strpos( $join, "{$table} AS {$alias}" ) ) {
					$join .= " LEFT JOIN {$table} {$alias} ON {$alias}.post_id = {$wpdb->posts}.ID";
				}
				return $join;
			}
		);

		add_filter(
			'posts_orderby',
			static function ( $orderby_sql ) use ( $alias, $column, $direction ) {
				$prefix = $orderby_sql ? trim( $orderby_sql ) . ', ' : '';
				return $prefix . "{$alias}.{$column} {$direction}";
			},
			10,
			2
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @param array $actions Actions.
	 */
	public static function register_bulk_actions( array $actions ): array {
		$actions['activate']  = __( 'Activate', 'zeko-business' );
		$actions['suspend']   = __( 'Suspend', 'zeko-business' );
		$actions['feature']   = __( 'Feature', 'zeko-business' );
		$actions['unfeature'] = __( 'Unfeature', 'zeko-business' );
		return $actions;
	}

	/**
	 * Handle bulk action.
	 *
	 * @param string $redirect_to Redirect to.
	 * @param string $action Action.
	 * @param array  $post_ids Post ids.
	 */
	public static function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( ! in_array( $action, array( 'activate', 'suspend', 'feature', 'unfeature' ), true ) ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'manage_zbp' ) ) {
			return $redirect_to;
		}

		$service   = Services::business_service();
		$by_post   = Services::businesses()->get_by_post_ids( array_map( 'absint', $post_ids ) );
		$processed = 0;

		foreach ( $by_post as $post_id => $biz ) {
			switch ( $action ) {
				case 'activate':
					$done = $service->approve( (int) $biz->id );
					break;
				case 'suspend':
					$done = $service->suspend( (int) $biz->id );
					break;
				case 'feature':
					$done = $service->feature( (int) $biz->id, 30 );
					break;
				default:
					$done = $service->unfeature( (int) $biz->id );
					break;
			}

			if ( $done ) {
				++$processed;
			}
		}

		return add_query_arg(
			array(
				'zbp_action'    => $action,
				'zbp_processed' => $processed,
			),
			$redirect_to
		);
	}

	/**
	 * Dismissible admin notice shown when a supported external directory
	 * plugin is installed but hasn't been migrated into Zeko Business yet.
	 */
	public static function migration_advice_notice(): void {
		if ( ! current_user_can( 'manage_zbp' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$hook   = $screen instanceof \WP_Screen ? (string) $screen->id : '';

		// Don't repeat the notice on the Import/Export page (it has its own guidance).
		if ( false !== strpos( $hook, 'zbp-import-export' ) ) {
			return;
		}

		require_once ZBE_PLUGIN_DIR . '/Import/class-migrationadvisor.php';
		$advisor = new \ZBE\Import\MigrationAdvisor();

		$pending = array();
		if ( class_exists( 'ZBE\Import\Migrator' ) ) {
			require_once ZBE_PLUGIN_DIR . '/Import/class-migrator.php';
			$migrator = new \ZBE\Import\Migrator();
			$sources  = $migrator->get_available_sources();

			foreach ( array_keys( $sources ) as $key ) {
				$key = (string) $key;
				if ( $advisor->plugin_file( $key ) && ! $advisor->is_fully_migrated( $key ) ) {
					$pending[] = $advisor->plugin_label( $key ) ?: $key;
				}
			}
		}

		if ( empty( $pending ) ) {
			return;
		}

		$names = implode( ', ', array_slice( $pending, 0, 5 ) );
		if ( count( $pending ) > 5 ) {
			$names .= '…';
		}

		$url = admin_url( 'edit.php?post_type=zeko_business&page=zbp-import-export' );

		echo '<div class="notice notice-info is-dismissible">';
		echo '<p><strong>Zeko Business:</strong> You have ';
		echo esc_html( $names );
		echo ' installed but its data has not been migrated yet. ';
		printf(
			'<a href="%s">Migrate and switch to Zeko Business</a> to import your directory, then deactivate the old plugin.',
			esc_url( $url )
		);
		echo '</p></div>';
	}
}
