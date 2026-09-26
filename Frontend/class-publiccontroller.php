<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Frontend;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class PublicController. */
class PublicController {
	/**
	 * Initialized.
	 *
	 * @var bool Initialized.
	 */
	private static bool $initialized = false;
	/**
	 * Rewrite registered.
	 *
	 * @var bool Rewrite registered.
	 */
	private static bool $rewrite_registered = false;
	/**
	 * Template data.
	 *
	 * @var array Template data.
	 */
	public static array $template_data = array();

	/**
	 * Init.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'template_redirect', array( __CLASS__, 'keep_business_url' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'template_include', array( __CLASS__, 'filter_template' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_body_classes' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'meta_tags' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'json_ld' ), 5 );
		add_action( 'rest_api_init', array( new RestApi(), 'register_routes' ) );

		Shortcodes::register();

		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		// The custom business rewrite rules must survive any flush, including.
		// ones that happen in admin/CLI contexts (this init() is front-end.
		// only). Register them globally via ensure_rewrite_rules_registered().
		self::ensure_rewrite_rules_registered();
		// Self-heal: re-register + flush if a manual permalink save (or any.
		// other plugin's flush / an update / a cache clear) dropped our custom.
		// rewrite rules. Without this, /businesses/{slug}/ silently falls.
		// through to the blog page until the admin manually saves permalinks.
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_repair_rewrite_rules' ) );
	}

	/**
	 * Register the custom business rewrite rules in EVERY request context —
	 * admin, CLI and front-end — so no rewrite flush can ever regenerate the
	 * cached rules without them. PublicController::init() is front-end only,
	 * so an admin permalink save, a plugin update or any other admin-side
	 * flush_rewrite_rules() would otherwise drop /businesses/{slug}/ and 404
	 * every business URL until the next front-end self-heal ran.
	 * Idempotent: safe to call from both the shared plugin bootstrap and
	 * PublicController::init().
	 */
	public static function ensure_rewrite_rules_registered(): void {
		if ( self::$rewrite_registered ) {
			return;
		}
		self::$rewrite_registered = true;

		add_action( 'init', array( __CLASS__, 'rewrite_rules' ) );
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'register_rule_array' ), 9999 );
	}

	/**
	 * Return the custom rewrite rule patterns this plugin owns, as the
	 * (loose) substrings that appear in WP's stored rewrite_rules keys.
	 */
	private static function owned_rule_markers(): array {
		return array(
			'businesses/([^/]+)',
			'business-portal',
			'business-create',
			'services/([0-9]+)',
		);
	}

	/**
	 * Detect whether our custom rewrite rules are missing from the cached
	 * rewrite_rules option and, if so, flush them on shutdown so the very
	 * next front-end request matches cleanly (no manual permalink resave).
	 * Runs on wp_loaded, after every plugin has registered its rules on init,
	 * so the check reflects the full rule set. It only flushes when a rule is
	 * actually missing, so it is a no-op on a healthy install.
	 */
	public static function maybe_repair_rewrite_rules(): void {
		// Custom rules are meaningless under a plain permalink structure;.
		// don't churn the flush in that case.
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return;
		}

		$rules = get_option( 'rewrite_rules', array() );
		if ( ! is_array( $rules ) ) {
			return;
		}

		foreach ( self::owned_rule_markers() as $marker ) {
			$present = false;
			foreach ( $rules as $pattern => $_query ) {
				if ( false !== strpos( $pattern, $marker ) ) {
					$present = true;
					break;
				}
			}
			if ( ! $present ) {
				// Register a one-shot flush at shutdown (rules are already.
				// registered at this point via the init hook).
				add_action( 'shutdown', array( __CLASS__, 'flush_custom_rules' ) );
				return;
			}
		}
	}

	/**
	 * Re-register the custom rules and flush. Only ever runs at shutdown,
	 * once, when the self-heal detected a missing rule.
	 */
	public static function flush_custom_rules(): void {
		if ( did_action( 'shutdown' ) ) {
			return;
		}
		self::rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Rewrite rules.
	 */
	public static function rewrite_rules(): void {
		add_rewrite_tag( '%zbp_business%', '([^/]+)' );
		add_rewrite_tag( '%zbp_portal%', '([1])' );
		add_rewrite_tag( '%zbp_create%', '([1])' );
		add_rewrite_tag( '%zbp_service%', '([0-9]+)' );
		add_rewrite_rule( '^business(es)?/([^/]+)/services/([0-9]+)/?$', 'index.php?zbp_business=$matches[2]&zbp_service=$matches[3]', 'top' );
		add_rewrite_rule( '^businesses/([^/]+)/?$', 'index.php?zbp_business=$matches[1]', 'top' );
		add_rewrite_rule( '^business-portal/?$', 'index.php?zbp_portal=1', 'top' );
		add_rewrite_rule( '^business-create/?$', 'index.php?zbp_create=1', 'top' );
	}

	/**
	 * Always inject our custom rules into any generated rule set.
	 * Other plugins (zeko-core, zeko-* and WP itself) trigger rewrite flushes
	 * at various times; add_rewrite_rule() only survives a flush if our
	 * rewrite_rules() ran during that same request. This filter guarantees the
	 * /businesses/{slug}/, /business-portal/ and /business-create/ rules are
	 * present in whatever rule array gets generated and cached, so they can
	 * never be silently dropped. Rules are prepended (before generic attachment
	 * catch-alls) so the specific business route always wins.
	 *
	 * @param array $rules Rules.
	 */
	public static function register_rule_array( array $rules ): array {
		$custom = array(
			'^business(es)?/([^/]+)/services/([0-9]+)/?$' => 'index.php?zbp_business=$matches[2]&zbp_service=$matches[3]',
			'^businesses/([^/]+)/?$'                      => 'index.php?zbp_business=$matches[1]',
			'^business-portal/?$'                         => 'index.php?zbp_portal=1',
			'^business-create/?$'                         => 'index.php?zbp_create=1',
		);
		return $custom + $rules;
	}

	/**
	 * Register our custom rewrite query vars as public so WP carries their
	 * values from the matched rewrite rule into $wp_query. Without this the
	 * /businesses/{slug}/ rule matches but the var is dropped and the
	 * business template never resolves.
	 *
	 * @param array $vars Vars.
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = 'zbp_business';
		$vars[] = 'zbp_portal';
		$vars[] = 'zbp_create';
		$vars[] = 'zbp_service';
		return $vars;
	}

	/**
	 * Keep /businesses/{slug}/ as the canonical business URL. WordPress's
	 * redirect_canonical() sees the matched zeko_business post on the custom
	 * rewrite route and 301s it to the native CPT permalink /business/{slug}/.
	 * That breaks every /businesses/ link the plugin generates, so disable the
	 * canonical redirect on routes this plugin owns.
	 */
	public static function keep_business_url(): void {
		$owned = (string) get_query_var( 'zbp_business' ) !== ''
			|| (string) get_query_var( 'zbp_portal' ) !== ''
			|| (string) get_query_var( 'zbp_create' ) !== ''
			|| (int) get_query_var( 'zbp_service' ) > 0;
		if ( ! $owned ) {
			$uri   = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			$owned = (bool) preg_match( '#/(businesses|business-portal|business-create|business/[^/]+/services)/#', $uri )
				|| (bool) preg_match( '#^/businesses/#', $uri );
		}
		if ( $owned ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
			add_filter( 'redirect_canonical', array( __CLASS__, 'keep_canonical_business_url' ), 10, 2 );
		}
	}

	/**
	 * Keep /businesses/{slug}/ canonical: return the requested URL so WP's
	 * redirect_canonical() treats it as canonical and does not 301 to the
	 * native CPT permalink /business/{slug}/.
	 *
	 * @param mixed $redirect_url Redirect url.
	 * @param mixed $requested_url Requested url.
	 */
	public static function keep_canonical_business_url( $redirect_url, $requested_url ) {
		return $requested_url;
	}

	/**
	 * Enqueue assets.
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$dir_page    = (int) ( get_option( 'zbe_pages' )['directory'] ?? 0 );
		$submit_page = (int) ( get_option( 'zbe_pages' )['submit'] ?? 0 );
		$dashboard_p = (int) ( get_option( 'zbe_pages' )['dashboard'] ?? 0 );
		$portal_page = (int) ( get_option( 'zbe_pages' )['portal'] ?? 0 );

		$is_single    = is_singular( 'zeko_business' )
			|| (string) get_query_var( 'zbp_business' ) !== ''
			|| (int) get_query_var( 'zbp_service' ) > 0;
		$is_portal    = (bool) get_query_var( 'zbp_portal' ) || is_page( $portal_page );
		$is_create    = (bool) get_query_var( 'zbp_create' ) || is_page( $submit_page );
		$is_directory = is_post_type_archive( 'zeko_business' ) || is_page( $dir_page );

		// If a shortcode is embedded in the current page's content, load the.
		// matching assets. Use has_shortcode() (per post) — never the global.
		// shortcode_exists(), which is true on every request.
		$post       = get_post();
		$has_dir    = $post && has_shortcode( $post->post_content, 'zbp_directory' );
		$has_portal = $post && has_shortcode( $post->post_content, 'zbp_business_portal' );
		$has_mine   = $post && has_shortcode( $post->post_content, 'zbp_my_businesses' );

		$needs_common = $is_single || $is_portal || $is_create || $is_directory
			|| $has_dir || $has_portal || $has_mine || is_page( $dashboard_p );
		if ( ! $needs_common ) {
			return;
		}

		$ver     = ZBE_VERSION;
		$css_dir = ZBE_PLUGIN_DIR . '/css/';
		$js_dir  = ZBE_PLUGIN_DIR . '/js/';

		// Shared base: container + public (follow/review/forms/booking).
		wp_enqueue_style( 'zbp-container', ZBE_PLUGIN_URL . '/css/zbp-container.css', array(), $ver );
		wp_enqueue_style( 'zbp-public', ZBE_PLUGIN_URL . '/css/zbp-public.css', array( 'zbp-container' ), self::asset_version( $css_dir . 'zbp-public.css', $ver ) );
		wp_enqueue_script( 'zbp-container', ZBE_PLUGIN_URL . '/js/zbp-container.js', array(), $ver, true );
		wp_enqueue_script( 'zbp-public', ZBE_PLUGIN_URL . '/js/zbp-public.js', array( 'zbp-container' ), self::asset_version( $js_dir . 'zbp-public.js', $ver ), true );
		self::defer_script( 'zbp-container' );
		self::defer_script( 'zbp-public' );

		// Single-business page only.
		if ( $is_single ) {
			wp_enqueue_style( 'zbp-single', ZBE_PLUGIN_URL . '/css/zbp-single.css', array( 'zbp-public' ), self::asset_version( $css_dir . 'zbp-single.css', $ver ) );
		}

		// Directory / archive only.
		if ( $is_directory || $has_dir ) {
			wp_enqueue_style( 'zbp-directory', ZBE_PLUGIN_URL . '/css/zbp-directory.css', array( 'zbp-public' ), self::asset_version( $css_dir . 'zbp-directory.css', $ver ) );
			wp_enqueue_script( 'zbp-directory', ZBE_PLUGIN_URL . '/js/zbp-directory.js', array( 'zbp-container' ), self::asset_version( $js_dir . 'zbp-directory.js', $ver ), true );
			self::defer_script( 'zbp-directory' );
		}

		// Portal (and create wizard) only.
		if ( $is_portal || $is_create || $has_portal ) {
			wp_enqueue_style( 'zbp-portal', ZBE_PLUGIN_URL . '/css/zbp-portal.css', array( 'zbp-public' ), self::asset_version( $css_dir . 'zbp-portal.css', $ver ) );
			wp_enqueue_script( 'zbp-portal', ZBE_PLUGIN_URL . '/js/zbp-portal.js', array( 'zbp-container' ), self::asset_version( $js_dir . 'zbp-portal.js', $ver ), true );
			self::defer_script( 'zbp-portal' );
		}

		if ( $is_single || $is_portal || $is_create ) {
			// Quill editor is the single licensed copy shared from Zeko Core
			// (handle `zeko-quill`); no local bundle is shipped. When core is
			// absent the editor fields simply degrade to plain text inputs.
			if ( class_exists( 'Zeko_Core_Assets' ) ) {
				\Zeko_Core_Assets::enqueue_quill();
			}
			wp_enqueue_media();

			// Force-enqueue zeko-jobs and zeko-qa assets on portal pages so.
			// embedded shortcodes render correctly via AJAX tab loading.
			if ( class_exists( 'Zeko_Jobs_Public' ) ) {
				$plugins_url = str_replace( '/zeko-business/', '/', ZBE_PLUGIN_URL );
				$jobs_ver    = defined( 'ZEKO_JOBS_VERSION' ) ? ZEKO_JOBS_VERSION : '2.0.0';
				if ( ! wp_style_is( 'zeko-jobs-css' ) ) {
					wp_enqueue_style( 'zeko-jobs-css', $plugins_url . 'zeko-jobs/assets/css/zeko-jobs.css', array( 'dashicons' ), $jobs_ver );
				}
				if ( ! wp_script_is( 'zeko-jobs-js' ) ) {
					wp_enqueue_script( 'zeko-jobs-js', $plugins_url . 'zeko-jobs/assets/js/zeko-jobs.js', array( 'jquery-core' ), $jobs_ver, true );
				}
				wp_localize_script(
					'zeko-jobs-js',
					'zeko_jobs_ajax',
					array(
						'ajax_url'                   => admin_url( 'admin-ajax.php' ),
						'job_apply_nonce'            => wp_create_nonce( 'zeko_job_apply' ),
						'job_create_nonce'           => wp_create_nonce( 'zeko_job_create' ),
						'job_bookmark_nonce'         => wp_create_nonce( 'zeko_job_bookmark' ),
						'job_message_employer_nonce' => wp_create_nonce( 'zeko_job_message_employer' ),
						'dashboard_nonce'            => wp_create_nonce( 'zeko_job_dashboard' ),
					)
				);
				if ( get_query_var( 'zbp_portal' ) && shortcode_exists( 'zeko_jobs_post_form' ) ) {
					wp_enqueue_editor();
				}
			}
			if ( class_exists( 'Zeko_QA' ) ) {
				if ( ! wp_style_is( 'zeko-qa-public' ) ) {
					wp_enqueue_style( 'zeko-qa-public', ZEKO_QA_PLUGIN_URL . 'assets/css/zeko-qa-public.css', array(), ZEKO_QA_VERSION );
				}
				if ( ! wp_script_is( 'zeko-qa-public' ) ) {
					wp_enqueue_script( 'zeko-qa-public', ZEKO_QA_PLUGIN_URL . 'assets/js/zeko-qa-public.js', array( 'jquery' ), ZEKO_QA_VERSION, true );
				}
				// Quill for QA is the single licensed copy shared from Zeko Core,.
				// already enqueued above via Zeko_Core_Assets::enqueue_quill().
				// (handle `zeko-quill`) - zeko-qa no longer ships its own copy.
				if ( ! wp_style_is( 'zeko-quill' ) && class_exists( 'Zeko_Core_Assets' ) ) {
					\Zeko_Core_Assets::enqueue_quill();
				}
				wp_localize_script(
					'zeko-qa-public',
					'zekoQAPublic',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'zeko_qa_public_nonce' ),
						'userId'  => get_current_user_id(),
						'strings' => array(
							'upvote'       => __( 'Upvote', 'zeko-qa' ),
							'downvote'     => __( 'Downvote', 'zeko-qa' ),
							'submitAnswer' => __( 'Submit Answer', 'zeko-qa' ),
							'loginToVote'  => __( 'Please login to vote', 'zeko-qa' ),
						),
					)
				);
			}
		}

		wp_localize_script(
			'zbp-container',
			'zbpPublic',
			array(
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'zbp_public_nonce' ),
				'currentUserId' => get_current_user_id(),
				'isLoggedIn'    => is_user_logged_in(),
				'i18n'          => array(
					'follow'         => __( 'Follow', 'zeko-business' ),
					'unfollow'       => __( 'Unfollow', 'zeko-business' ),
					'error'          => __( 'An error occurred.', 'zeko-business' ),
					'confirm_delete' => __( 'Are you sure you want to delete this?', 'zeko-business' ),
					'active'         => __( 'Active', 'zeko-business' ),
					'inactive'       => __( 'Inactive', 'zeko-business' ),
					'select_images'  => __( 'Select images', 'zeko-business' ),
					'copied_to_all'  => __( 'Copied to all days.', 'zeko-business' ),
					'copied'         => __( 'Link copied to clipboard.', 'zeko-business' ),
				),
			)
		);
	}

	/**
	 * Cache-bust a registered asset with its filemtime, falling back to the
	 * plugin version when the file cannot be read.
	 *
	 * @param string $path Path.
	 * @param string $fallback Fallback.
	 */
	public static function asset_version( string $path, string $fallback ): string {
		$mtime = @filemtime( $path );
		return $mtime ? (string) $mtime : $fallback;
	}

	/**
	 * Mark a front-end script as deferred so it does not block parsing.
	 * Uses the WP 6.3+ strategy API when available; silently skipped on
	 * older core versions so the site still works everywhere.
	 *
	 * @param string $handle Handle.
	 */
	private static function defer_script( string $handle ): void {
		if ( \function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
	}

	/**
	 * Filter template.
	 *
	 * @param string $template Template.
	 */
	public static function filter_template( string $template ): string {
		// Single-service detail route: /businesses/{slug}/services/{id}/.
		$service_id = (int) get_query_var( 'zbp_service' );
		if ( $service_id > 0 ) {
			$slug    = get_query_var( 'zbp_business' );
			$service = Services::services_repo()->get( $service_id );
			if ( $service && $service->business_id > 0 ) {
				$biz = Services::businesses()->get( (int) $service->business_id );
				if ( $biz && ( empty( $slug ) || $biz->slug === $slug ) ) {
					self::$template_data['business'] = $biz;
					self::$template_data['service']  = $service;
					return ZBE_PLUGIN_DIR . '/Frontend/Templates/service-detail.php';
				}
			}
		}

		$slug = (string) get_query_var( 'zbp_business' );
		$repo = Services::businesses();

		if ( '' !== $slug ) {
			// Custom rewrite route: /businesses/{slug}/.
			$biz = $repo->get_by_slug( $slug );
		} elseif ( is_singular( 'zeko_business' ) ) {
			// Native CPT route: /business/{slug}/.
			$post = get_queried_object();
			$biz  = $repo->get_by_post_id( $post instanceof \WP_Post ? (int) $post->ID : 0 );
		} else {
			$biz = null;
		}

		if ( $biz ) {
			self::load_single_data( $biz );
			return ZBE_PLUGIN_DIR . '/Frontend/Templates/single.php';
		}

		$zbp_portal = get_query_var( 'zbp_portal' );
		if ( $zbp_portal ) {
			return ZBE_PLUGIN_DIR . '/Frontend/Templates/business-portal.php';
		}

		$zbp_create = get_query_var( 'zbp_create' );
		if ( $zbp_create && is_user_logged_in() ) {
			return ZBE_PLUGIN_DIR . '/Frontend/Templates/page-clean.php';
		}

		// Plugin app pages (directory / portal / dashboard / submit) are backed.
		// by shortcode-only pages. Render them clean and full-width so the.
		// theme's blog sidebar, comments and post navigation never wrap them.
		$page_id = get_queried_object_id();
		$owned   = array_filter( array_map( 'intval', (array) get_option( 'zbe_pages', array() ) ) );
		if ( $page_id > 0 && in_array( $page_id, $owned, true ) ) {
			return ZBE_PLUGIN_DIR . '/Frontend/Templates/page-clean.php';
		}
		if ( is_post_type_archive( 'zeko_business' ) ) {
			return ZBE_PLUGIN_DIR . '/Frontend/Templates/page-clean.php';
		}

		return $template;
	}

	/**
	 * Populate template data for a single business (rewrite route or
	 * shortcode) and record the view.
	 *
	 * @param object $biz Biz.
	 */
	public static function load_single_data( object $biz ): void {
		global $wpdb;

		self::$template_data['business'] = $biz;
		self::$template_data['services'] = Services::services_repo()->get_by_business( (int) $biz->id, true );
		self::$template_data['reviews']  = Services::reviews()->get_by_business( (int) $biz->id, 20 );
		self::$template_data['staff']    = Services::team_repo()->get_by_business( (int) $biz->id );
		self::$template_data['hours']    = Services::hours()->get_for_business( (int) $biz->id );

		$owner_id = (int) $biz->owner_id;

		// Jobs by this business owner.
		$biz_jobs    = array();
		$jobs_active = defined( 'ZEKO_JOBS_VERSION' ) || class_exists( 'Zeko_Jobs_DB' );
		if ( $jobs_active && $owner_id > 0 ) {
			$jobs_table = $wpdb->prefix . 'zeko_jobs';
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
            //phpcs:ignore WordPress.DB
			$biz_jobs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title, slug, location, type, salary_min, salary_max, is_featured, created_at
                     FROM {$jobs_table}
                     WHERE employer_id = %d AND status = 'publish'
                     ORDER BY is_featured DESC, created_at DESC LIMIT 10",
					$owner_id
				)
			) ?: array();
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		self::$template_data['biz_jobs'] = $biz_jobs;

		// Questions mentioning this business (Q&A + Requests combined).
		// Prefer the structured mention index (zbe_question_mentions), merged.
		// with a LIKE fallback so historical/hand-authored questions still.
		// surface even before a mention row exists.
		$biz_questions = array();
		$biz_mentions  = array();
		$qa_active     = defined( 'ZEKO_QA_VERSION' ) || class_exists( 'Zeko_QA' );
		$business_id   = (int) $biz->id;
		$q_table       = $wpdb->prefix . 'zeko_questions';
		if ( $qa_active && $business_id > 0 ) {
			$mention  = Services::mention_service();
			$biz_name = mb_strtolower( $biz->name );

			$by_index = self::key_by_id( $mention->get_business_questions( $business_id ) );

			$like_one = '%' . $wpdb->esc_like( $biz_name ) . '%';
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
            //phpcs:ignore WordPress.DB
			$by_like = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, user_id, title, slug, views, upvotes, downvotes, created_at
                     FROM {$q_table}
                     WHERE (LOWER(title) LIKE %s OR LOWER(content) LIKE %s) AND status = 'open'
                     ORDER BY created_at DESC LIMIT 10",
					$like_one,
					$like_one
				)
			) ?: array();
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

			foreach ( $by_like as $q ) {
				$by_index[ (int) $q->id ] = $q;
			}
			$biz_questions = array_values( $by_index );

			// Owner name mentions (Requests). MentionService exposes this as.
			// get_owner_mentions(); the underlying repo handles id lookup.
			$owner_map = self::key_by_id( $mention->get_owner_mentions( $business_id ) );

			$owner      = $owner_id > 0 ? get_userdata( $owner_id ) : false;
			$owner_name = $owner ? mb_strtolower( $owner->display_name ) : '';
			if ( mb_strlen( $owner_name ) > 2 ) {
				$like_owner = '%' . $wpdb->esc_like( $owner_name ) . '%';
                // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
                //phpcs:ignore WordPress.DB
				$by_like_owner = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, user_id, title, slug, views, upvotes, downvotes, created_at
                         FROM {$q_table}
                         WHERE (LOWER(title) LIKE %s OR LOWER(content) LIKE %s) AND status = 'open'
                         ORDER BY created_at DESC LIMIT 10",
						$like_owner,
						$like_owner
					)
				) ?: array();
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

				foreach ( $by_like_owner as $q ) {
					$owner_map[ (int) $q->id ] = $q;
				}
			}
			$biz_mentions = array_values( $owner_map );
		}
		self::$template_data['biz_questions'] = $biz_questions;
		self::$template_data['biz_mentions']  = $biz_mentions;

		// Products from zeko-shop.
		$shop_products = array();
		if ( class_exists( 'Zeko_Shop' ) ) {
			$shop_products = \Zeko_Shop::instance()->get_db()->get_products_by_external( 'business', (int) $biz->id );
		}
		self::$template_data['shop_products'] = $shop_products;

		// The current user's claim on this business, if any (pending/rejected),.
		// so the "claim this listing" panel can reflect real submission state.
		self::$template_data['my_claim'] = self::current_user_claim( (int) $biz->id );

		Services::business_service()->track_view( (int) $biz->id, get_current_user_id() );
	}

	/**
	 * The logged-in user's claim on the given business, or null.
	 *
	 * @return \ZBE\Entity\Claim|null
	 * @param int $business_id Business id.
	 */
	private static function current_user_claim( int $business_id ): ?\ZBE\Entity\Claim {
		if ( ! is_user_logged_in() || $business_id < 1 ) {
			return null;
		}

		$claims = Services::claims()->find(
			array(
				'business_id' => $business_id,
				'user_id'     => get_current_user_id(),
				'limit'       => 1,
			)
		);

		return $claims ? $claims[0] : null;
	}

	/**
	 * Key an array of row/entity objects by their numeric id, preserving the
	 * last occurrence when ids collide.
	 *
	 * @return array<int, object>
	 * @param array $rows * @return array<int, object>.
	 */
	private static function key_by_id( array $rows ): array {
		$map = array();
		foreach ( $rows as $row ) {
			if ( is_object( $row ) && isset( $row->id ) ) {
				$map[ (int) $row->id ] = $row;
			}
		}
		return $map;
	}

	/**
	 * Add body classes.
	 *
	 * @param array $classes Classes.
	 */
	public static function add_body_classes( array $classes ): array {
		$classes[] = 'zbp-public';
		$is_single = is_singular( 'zeko_business' ) || (string) get_query_var( 'zbp_business' ) !== '';
		if ( $is_single ) {
			$classes[] = 'zbp-single-business';
			// On the custom /businesses/{slug}/ route the main query is still a.
			// blog/home query, so WP tags the body with "home blog.
			// has-sidebar". Strip those so the theme renders the business page.
			// as a standalone content page rather than applying blog/home.
			// spacing and sidebar styling (which caused blank gaps and let.
			// stray nav/gear dropdowns overlap the tabbed content).
			$classes = array_values( array_diff( $classes, array( 'home', 'blog', 'has-sidebar' ) ) );
		} elseif ( is_post_type_archive( 'zeko_business' ) || is_page( (int) ( get_option( 'zbe_pages' )['directory'] ?? 0 ) ) ) {
			$classes[] = 'zbp-directory';
		}
		return $classes;
	}

	/**
	 * Meta tags.
	 */
	public static function meta_tags(): void {
		if ( ! is_singular( 'zeko_business' ) && (string) get_query_var( 'zbp_business' ) === '' ) {
			return;
		}

		$biz = self::$template_data['business'] ?? null;
		if ( ! $biz ) {
			return;
		}

		$description = '';
		if ( ! empty( $biz->post_id ) ) {
			$description = get_post_field( 'post_content', (int) $biz->post_id );
		}
		if ( '' === $description && ! empty( $biz->name ) ) {
			$services    = Services::services_repo()->get_by_business( (int) $biz->id, true );
			$names       = array_map(
				static function ( $service ) {
					return is_object( $service ) ? $service->name : '';
				},
				$services
			);
			$names       = array_filter( $names );
			$description = sprintf( '%s — %s', $biz->name, implode( ', ', array_slice( $names, 0, 5 ) ) );
		}

		echo '<meta property="og:title" content="' . esc_attr( $biz->name ) . '">' . "\n";
		echo '<meta property="og:type" content="business.business">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( home_url( '/businesses/' . $biz->slug . '/' ) ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( home_url( '/businesses/' . $biz->slug . '/' ) ) . '">' . "\n";
		if ( $description ) {
			echo '<meta property="og:description" content="' . esc_attr( wp_trim_words( wp_strip_all_tags( $description ), 30 ) ) . '">' . "\n";
		}
		if ( $biz->avatar_id ) {
			$url = wp_get_attachment_image_url( (int) $biz->avatar_id, 'large' );
			if ( $url ) {
				echo '<meta property="og:image" content="' . esc_url( $url ) . '">' . "\n";
			}
		}
	}

	/**
	 * Schema.org LocalBusiness JSON-LD for the single business view.
	 */
	public static function json_ld(): void {
		if ( ! is_singular( 'zeko_business' ) && (string) get_query_var( 'zbp_business' ) === '' ) {
			return;
		}

		$biz = self::$template_data['business'] ?? null;
		if ( ! $biz || 'active' !== $biz->status ) {
			return;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'name'     => $biz->name,
			'url'      => home_url( '/businesses/' . $biz->slug . '/' ),
		);

		if ( ! empty( $biz->post_id ) ) {
			$description = wp_strip_all_tags( get_post_field( 'post_content', (int) $biz->post_id ) );
			if ( $description ) {
				$data['description'] = wp_trim_words( $description, 55 );
			}
		}

		$image = $biz->avatar_id ? wp_get_attachment_image_url( (int) $biz->avatar_id, 'large' ) : '';
		if ( $image ) {
			$data['image'] = $image;
		}

		foreach ( array(
			'telephone' => $biz->phone,
			'email'     => $biz->email,
			'website'   => $biz->website,
		) as $key => $value ) {
			if ( ! empty( $value ) ) {
				$data[ $key ] = $value;
			}
		}

		$address_parts = array_filter(
			array(
				'streetAddress'   => $biz->address,
				'addressLocality' => $biz->city,
				'addressRegion'   => $biz->state,
				'postalCode'      => $biz->zip,
				'addressCountry'  => $biz->country,
			)
		);

		if ( ! empty( $address_parts ) ) {
			$data['address'] = array_merge( array( '@type' => 'PostalAddress' ), $address_parts );
		}

		if ( $biz->lat && $biz->lng ) {
			$data['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $biz->lat,
				'longitude' => (float) $biz->lng,
			);
		}

		if ( ! empty( $biz->social_links ) && is_array( $biz->social_links ) ) {
			$same_as = array_values( array_filter( array_map( 'esc_url_raw', $biz->social_links ) ) );
			if ( $same_as ) {
				$data['sameAs'] = $same_as;
			}
		}

		$hours = self::$template_data['hours'] ?? array();
		if ( ! empty( $hours ) ) {
			$day_keys = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
			$spec     = array();

			foreach ( $hours as $h ) {
				if ( $h->is_closed || empty( $h->open_time ) || empty( $h->close_time ) ) {
					continue;
				}

				$spec[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $day_keys[ (int) $h->day_of_week ] ?? 'Sunday',
					'opens'     => substr( $h->open_time, 0, 5 ),
					'closes'    => substr( $h->close_time, 0, 5 ),
				);
			}

			if ( $spec ) {
				$data['openingHoursSpecification'] = $spec;
			}
		}

		if ( (int) $biz->review_count > 0 && (float) $biz->avg_rating > 0 ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( (float) $biz->avg_rating, 1 ),
				'reviewCount' => (int) $biz->review_count,
			);
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
	}

	/**
	 * Whether the business is open right now.
	 * Honors the business-level hours mode tri-state:
	 * - "always"        => permanently open (24/7)
	 * - "closed"        => permanently closed
	 * - "none"          => no hours listed
	 * - "" (default)    => evaluate the weekly schedule
	 * Returns true / false when determinable, or null when there is no
	 * usable schedule.
	 *
	 * @return bool|null
	 * @param array  $hours * @param string $hours_mode.
	 * @param string $hours_mode Hours mode.
	 */
	public static function is_open_now( array $hours, string $hours_mode = '' ): ?bool {
		$hours_mode = strtolower( (string) $hours_mode );

		if ( 'always' === $hours_mode ) {
			return true;
		}
		if ( 'closed' === $hours_mode ) {
			return false;
		}
		if ( 'none' === $hours_mode ) {
			return null;
		}

		$by_day = array();

		foreach ( $hours as $h ) {
			if ( empty( $h->is_closed ) && ! empty( $h->open_time ) && ! empty( $h->close_time ) ) {
				$by_day[ (int) $h->day_of_week ] = array( $h->open_time, $h->close_time );
			}
		}

		if ( empty( $by_day ) ) {
			return null;
		}

		$stamp = time();
		$dow   = (int) gmdate( 'w', $stamp );
		$time  = gmdate( 'H:i:s', $stamp );

		// Today's window — also true right after opening for overnight shifts.
		if ( isset( $by_day[ $dow ] ) ) {
			[$open, $close] = $by_day[ $dow ];

			if ( $time >= $open && ( $time < $close || $close <= $open ) ) {
				return true;
			}
		}

		// Past midnight, still inside yesterday's overnight window.
		$prev = ( $dow + 6 ) % 7;

		if ( isset( $by_day[ $prev ] ) ) {
			[, $close] = $by_day[ $prev ];
			$prev_open = $by_day[ $prev ][0];

			if ( $close <= $prev_open && $time < $close ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the tri-state status of a business for display purposes.
	 *
	 * @return string One of "open", "closed", or "no_hours".
	 * @param array  $hours * @param string $hours_mode.
	 * @param string $hours_mode Hours mode.
	 */
	public static function hours_status( array $hours, string $hours_mode = '' ): string {
		$open = self::is_open_now( $hours, $hours_mode );

		if ( true === $open ) {
			return 'open';
		}
		if ( false === $open ) {
			return 'closed';
		}

		return 'no_hours';
	}
}
