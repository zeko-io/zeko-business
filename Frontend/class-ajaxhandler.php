<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Frontend;

use ZBE\Core\Services;
use ZBE\Security\RateLimiter;
use ZBE\Security\Akismet;

defined( 'ABSPATH' ) || exit;

/** Class AjaxHandler. */
class AjaxHandler {
	/**
	 * Sanitize rich-text (Quill) content with the narrow ecosystem
	 * allow-list; falls back to wp_kses_post() when zeko-core is absent.
	 *
	 * @param mixed $html Html.
	 */
	private static function sanitize_rich( $html ): string {
		if ( class_exists( 'Zeko_Core_Sanitize' ) ) {
			return \Zeko_Core_Sanitize::rich_text( (string) $html );
		}
		return wp_kses_post( $html );
	}

	/**
	 * Init.
	 */
	public static function init(): void {
		$actions = array(
			'zbp_follow',
			'zbp_unfollow',
			'zbp_submit_review',
			'zbp_review_vote',
			'zbp_portal_tab_load',
			'zbp_create_business',
			'zbp_update_business',
			'zbp_create_service',
			'zbp_update_service',
			'zbp_delete_service',
			'zbp_toggle_service',
			'zbp_add_service_category',
			'zbp_book_service',
			'zbp_save_product',
			'zbp_delete_product',
			'zbp_buy_product',
			'zbp_add_product_category',
			'zbp_invite_staff',
			'zbp_invite_external',
			'zbp_remove_staff',
			'zbp_revoke_invite',
			'zbp_resend_invite',
			'zbp_update_settings',
			'zbp_save_hours',
			'zbp_add_media',
			'zbp_delete_media',
			'zbp_mark_notifications_read',
			'zbp_submit_claim',
			'zbp_submit_verification',
			'zbp_directory_search',
			'zbp_upgrade_plan',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $action ) );
			if ( in_array( $action, array( 'zbp_follow', 'zbp_unfollow', 'zbp_submit_review', 'zbp_directory_search', 'zbp_book_service', 'zbp_buy_product' ), true ) ) {
				add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $action ) );
			}
		}
	}

	/**
	 * Require login.
	 */
	private static function require_login(): ?int {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'Login required', 'zeko-business' ) );
			return null;
		}
		return get_current_user_id();
	}

	/**
	 * User following.
	 *
	 * @param int $user_id User id.
	 * @param int $business_id Business id.
	 */
	public static function is_user_following( int $user_id, int $business_id ): bool {
		if ( ! $user_id || ! $business_id ) {
			return false;
		}

		return Services::followers()->is_following( $user_id, $business_id );
	}

	/**
	 * Verify.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	private static function verify( string $nonce_action = 'zbp_public_nonce' ): bool {
		if ( ! wp_verify_nonce( wp_unslash( $_POST['_ajax_nonce'] ?? '' ), $nonce_action ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce token is compared against the expected value, never stored or echoed.
			wp_send_json_error( __( 'Security check failed', 'zeko-business' ) );
			return false;
		}
		return true;
	}

	/**
	 * Keep only attachment IDs the user actually owns (admins excepted).
	 *
	 * @return int[] Owned, existing attachments.
	 * @param array $ids Raw attachment IDs from the request.
	 * @param int   $user_id Current user.
	 */
	private static function own_attachments( array $ids, int $user_id ): array {
		$clean = array();
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $attachment_id ) {
			if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}
			if ( ! current_user_can( 'manage_options' )
				&& (int) get_post_field( 'post_author', $attachment_id ) !== (int) $user_id ) {
				continue;
			}
			$clean[] = $attachment_id;
		}
		return $clean;
	}

	/**
	 * Fetch a business and make sure the current user may manage it.
	 *
	 * @param int $business_id Business id.
	 * @param int $user_id User id.
	 */
	private static function owned_business( int $business_id, int $user_id ): ?object {
		if ( $business_id < 1 ) {
			return null;
		}

		$biz = Services::businesses()->get( $business_id );

		if ( ! $biz ) {
			return null;
		}

		if ( (int) $biz->owner_id !== $user_id && ! current_user_can( 'edit_others_businesses' ) ) {
			return null;
		}

		return $biz;
	}

	/**
	 * Zbp follow.
	 */
	public static function zbp_follow(): void {
		if ( ! self::verify() || ! self::require_login() ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$user_id     = get_current_user_id();

		if ( RateLimiter::is_limited( 'follow', 10, 60, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many requests. Please wait a moment.', 'zeko-business' ) );
		}

		if ( ! $business_id ) {
			wp_send_json_error( __( 'Invalid business', 'zeko-business' ) );
		}

		$service = Services::business_service();

		if ( $service->follow( $business_id, $user_id ) ) {
			wp_send_json_success(
				array(
					'follower_count' => Services::followers()->count( $business_id ),
					'following'      => true,
				)
			);
		}

		wp_send_json_error( __( 'Already following or unavailable', 'zeko-business' ) );
	}

	/**
	 * Zbp unfollow.
	 */
	public static function zbp_unfollow(): void {
		if ( ! self::verify() || ! self::require_login() ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$user_id     = get_current_user_id();

		if ( RateLimiter::is_limited( 'unfollow', 10, 60, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many requests. Please wait a moment.', 'zeko-business' ) );
		}

		if ( ! $business_id ) {
			wp_send_json_error( __( 'Invalid business', 'zeko-business' ) );
		}

		$service = Services::business_service();

		if ( $service->unfollow( $business_id, $user_id ) ) {
			wp_send_json_success(
				array(
					'follower_count' => Services::followers()->count( $business_id ),
					'following'      => false,
				)
			);
		}

		wp_send_json_error( __( 'You are not following this business', 'zeko-business' ) );
	}

	/**
	 * Zbp submit review.
	 */
	public static function zbp_submit_review(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		if ( RateLimiter::is_limited( 'review', 3, 300, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many reviews. Please wait before submitting another.', 'zeko-business' ) );
		}

		// Honeypot check — field must be empty.
		if ( ! empty( $_POST['zbp_website_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			wp_send_json_error( __( 'Invalid submission', 'zeko-business' ) );
		}

		// Time-trap — reject submissions posted too fast for a human (bots).
		$form_ts = absint( wp_unslash( $_POST['zbp_form_ts'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( $form_ts <= 0 || ( time() - $form_ts ) < 2 ) {
			wp_send_json_error( __( 'Invalid submission', 'zeko-business' ) );
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$rating      = absint( $_POST['rating'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$content     = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		// Multi-criteria stars: criteria[label]=stars, e.g. criteria[Quality]=4.
		$criteria = array();
		if ( ! empty( $_POST['criteria'] ) && is_array( $_POST['criteria'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			foreach ( wp_unslash( $_POST['criteria'] ) as $label => $stars ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- array keys cast to string and values to int below.
				$criteria[ (string) $label ] = (int) $stars;
			}
		}

		$photo_ids = array();
		if ( ! empty( $_POST['photo_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$raw = wp_unslash( $_POST['photo_ids'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- attachment ids absint-cast below.
			if ( is_array( $raw ) ) {
				foreach ( $raw as $id ) {
					$pid = absint( $id );
					if ( $pid > 0 ) {
						$photo_ids[] = $pid;
					}
				}
			}
			// Only the reviewer's own uploads may be attached to a review.
			$photo_ids = array_slice( self::own_attachments( $photo_ids, $user_id ), 0, 8 );
		}

		if ( ! $business_id || $rating < 1 || $rating > 5 || empty( $content ) ) {
			wp_send_json_error( __( 'Invalid review data', 'zeko-business' ) );
		}

		$current_user = wp_get_current_user();
		if ( Akismet::is_spam(
			array(
				'author'       => $current_user->display_name,
				'email'        => $current_user->user_email,
				'url'          => $current_user->user_url,
				'content'      => $title . "\n" . $content,
				'comment_type' => 'review',
			)
		) ) {
			wp_send_json_error( __( 'Your review could not be submitted.', 'zeko-business' ) );
		}

		foreach ( Services::reviews()->get_by_user( $user_id ) as $existing ) {
			if ( (int) $existing->business_id === $business_id ) {
				wp_send_json_error( __( 'You have already reviewed this business', 'zeko-business' ) );
			}
		}

		$review = Services::review_service()->create( $business_id, $user_id, $rating, $title, $content, $criteria );

		if ( ! $review ) {
			wp_send_json_error( __( 'Review could not be submitted', 'zeko-business' ) );
		}

		if ( $photo_ids && (int) $review->id > 0 ) {
			Services::reviews()->update( (int) $review->id, array( 'photos' => $photo_ids ) );
		}

		$author  = get_userdata( $user_id );
		$biz_new = Services::businesses()->get( $business_id );

		wp_send_json_success(
			array(
				'message'      => __( 'Review submitted!', 'zeko-business' ),
				'review_html'  => self::render_single_review( $review, $author ),
				'avg_rating'   => $biz_new ? number_format( (float) $biz_new->avg_rating, 1 ) : '0.0',
				'review_count' => $biz_new ? (int) $biz_new->review_count : 0,
			)
		);
	}

	/**
	 * Zbp review vote.
	 */
	public static function zbp_review_vote(): void {
		if ( ! self::verify() || ! self::require_login() ) {
			return;
		}

		$user_id   = get_current_user_id();
		$review_id = absint( $_POST['review_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$value     = (int) sanitize_text_field( wp_unslash( $_POST['value'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( RateLimiter::is_limited( 'review_vote', 20, 60, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many requests. Please wait a moment.', 'zeko-business' ) );
		}

		if ( $review_id < 1 || ( 1 !== $value && -1 !== $value ) ) {
			wp_send_json_error( __( 'Invalid vote', 'zeko-business' ) );
		}

		$review = Services::reviews()->get( $review_id );

		if ( ! $review ) {
			wp_send_json_error( __( 'Review not found', 'zeko-business' ) );
		}

		$helper    = Services::reviews()->vote( $review_id, $user_id, $value );
		$user_vote = Services::reviews()->get_user_vote( $review_id, $user_id );

		wp_send_json_success(
			array(
				'helpful_count' => $helper,
				'user_vote'     => $user_vote,
			)
		);
	}

	/**
	 * Zbp portal tab load.
	 */
	public static function zbp_portal_tab_load(): void {
		if ( ! self::verify() || ! self::require_login() ) {
			return;
		}

		$tab         = sanitize_key( $_POST['tab'] ?? 'overview' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		$biz = self::owned_business( $business_id, get_current_user_id() );

		ob_start();

		switch ( $tab ) {
			case 'overview':
				self::render_portal_overview( $biz );
				break;
			case 'services':
				self::render_portal_services( $biz );
				break;
			case 'products':
				self::render_portal_products( $biz );
				break;
			case 'reviews':
				self::render_portal_reviews( $biz );
				break;
			case 'staff':
				self::render_portal_staff( $biz );
				break;
			case 'media':
				self::render_portal_media( $biz );
				break;
			case 'analytics':
				self::render_portal_analytics( $biz );
				break;
			case 'hours':
				self::render_portal_hours( $biz );
				break;
			case 'businesses':
				self::render_portal_businesses();
				break;
			case 'notifications':
				self::render_portal_notifications();
				break;
			case 'settings':
				self::render_portal_settings( $biz );
				break;
			case 'jobs':
				self::render_portal_jobs( $biz );
				break;
			case 'qa':
				self::render_portal_qa( $biz );
				break;
			case 'requests':
				self::render_portal_requests( $biz );
				break;
			case 'claims':
				self::render_portal_claims();
				break;
			case 'plan':
				self::render_portal_plan( $biz );
				break;
			default:
				echo '<div class="zbp-empty-state">' . esc_html__( 'Tab content not available.', 'zeko-business' ) . '</div>';
		}

		$html = ob_get_clean();
		wp_send_json_success( $html );
	}

	/**
	 * Render portal overview.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_overview( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business to view its overview.', 'zeko-business' ) . '</div>';
			return;
		}
		?>
		<h2><?php echo esc_html( $biz->name ); ?> — <?php esc_html_e( 'Overview', 'zeko-business' ); ?></h2>
		<div class="zbp-portal-stat-cards">
			<div class="zbp-portal-stat-card">
				<div class="zbp-portal-stat-card__number"><?php echo esc_html( number_format( (int) $biz->view_count ) ); ?></div>
				<div class="zbp-portal-stat-card__label"><?php esc_html_e( 'Views', 'zeko-business' ); ?></div>
			</div>
			<div class="zbp-portal-stat-card">
				<div class="zbp-portal-stat-card__number"><?php echo esc_html( number_format( (int) $biz->follower_count ) ); ?></div>
				<div class="zbp-portal-stat-card__label"><?php esc_html_e( 'Followers', 'zeko-business' ); ?></div>
			</div>
			<div class="zbp-portal-stat-card">
				<div class="zbp-portal-stat-card__number"><?php echo esc_html( number_format( (int) $biz->review_count ) ); ?></div>
				<div class="zbp-portal-stat-card__label"><?php esc_html_e( 'Reviews', 'zeko-business' ); ?></div>
			</div>
			<div class="zbp-portal-stat-card">
				<div class="zbp-portal-stat-card__number"><?php echo esc_html( number_format( (float) $biz->avg_rating, 1 ) ); ?></div>
				<div class="zbp-portal-stat-card__label"><?php esc_html_e( 'Avg Rating', 'zeko-business' ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render portal services.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_services( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$plan      = $biz->plan ?: 'free';
		$max_svc   = \ZBE\Core\Plans::get( $plan, 'max_services', 0 );
		$services  = Services::services_repo()->get_by_business( (int) $biz->id, false );
		$svc_count = count( $services );
		$settings  = get_option( 'zbe_settings', array() );
		$currency  = is_array( $settings ) ? ( $settings['general']['currency'] ?? '$' ) : '$';

		$can_add = 0 === $max_svc || $svc_count < $max_svc;

		$service_categories = Services::service_categories()->get_all( true );
		$cat_options        = '';
		foreach ( $service_categories as $cat ) {
			$cat_options .= '<option value="' . esc_attr( $cat->slug ) . '">' . esc_html( $cat->name ) . '</option>';
		}

		echo '<div class="zbp-portal-section-header">';
		echo '<h2 class="zbp-portal-section-title">' . esc_html__( 'Services', 'zeko-business' ) . '</h2>';
		echo '<p class="zbp-portal-section-desc">' . esc_html__( 'Manage services your business offers. Rich descriptions help customers understand what you provide.', 'zeko-business' ) . '</p>';
		echo '</div>';

		if ( ! empty( $services ) ) {
			foreach ( $services as $svc ) {
				$svc_id = (int) $svc->id;
				?>
				<div class="zbp-service-manage" data-service-id="<?php echo esc_attr( $svc_id ); ?>">
					<div class="zbp-service-manage__row">
						<?php if ( $svc->image_id ) : ?>
							<?php echo wp_get_attachment_image( (int) $svc->image_id, 'thumbnail', false, array( 'class' => 'zbp-service-manage__thumb' ) ); ?>
						<?php endif; ?>
						<div class="zbp-service-manage__info">
							<strong><?php echo esc_html( $svc->name ); ?></strong>
							<?php if ( ! empty( $svc->category ) ) : ?>
								<span class="zbp-badge zbp-badge--sm"><?php echo esc_html( ucfirst( $svc->category ) ); ?></span>
							<?php endif; ?>
							<?php if ( $svc->description ) : ?>
								<div class="zbp-service-manage__desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $svc->description ), 15 ) ); ?></div>
							<?php endif; ?>
							<?php if ( ! empty( $svc->tags ) ) : ?>
								<div class="zbp-service-manage__tags">
									<?php foreach ( array_filter( array_map( 'trim', explode( ',', $svc->tags ) ) ) as $tag ) : ?>
										<span class="zbp-tag"><?php echo esc_html( $tag ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<span class="zbp-service-manage__price">
							<?php echo esc_html( (float) $svc->price > 0 ? $currency . number_format( (float) $svc->price, 2 ) : __( 'Free', 'zeko-business' ) ); ?>
							<?php if ( $svc->price_type && 'fixed' !== $svc->price_type ) : ?>
								<small>/<?php echo esc_html( $svc->price_type ); ?></small>
							<?php endif; ?>
						</span>
						<div class="zbp-service-manage__controls">
							<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-service-toggle" aria-pressed="<?php echo $svc->is_active ? 'true' : 'false'; ?>">
								<?php echo $svc->is_active ? esc_html__( 'Active', 'zeko-business' ) : esc_html__( 'Inactive', 'zeko-business' ); ?>
							</button>
							<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-service-edit" aria-expanded="false"><?php esc_html_e( 'Edit', 'zeko-business' ); ?></button>
							<button type="button" class="zbp-btn zbp-btn--danger zbp-btn--sm zbp-service-delete"><?php esc_html_e( 'Delete', 'zeko-business' ); ?></button>
						</div>
					</div>
					<form class="zbp-portal-form zbp-service-manage__form" data-action="zbp_update_service" hidden>
						<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
						<input type="hidden" name="service_id" value="<?php echo esc_attr( $svc_id ); ?>" />
						<input type="hidden" name="image_id" value="<?php echo (int) $svc->image_id; ?>" />
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Service Name', 'zeko-business' ); ?></label>
							<input type="text" name="name" class="zbp-form-input" value="<?php echo esc_attr( $svc->name ); ?>" required />
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Short Description', 'zeko-business' ); ?></label>
							<textarea name="short_description" class="zbp-form-textarea" rows="2"><?php echo esc_textarea( $svc->short_description ); ?></textarea>
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Full Description', 'zeko-business' ); ?></label>
							<div class="zbp-quill-wrapper" data-field="description">
								<div class="zbp-quill-editor"></div>
								<input type="hidden" name="description" class="zbp-quill-value" value="<?php echo esc_attr( $svc->description ); ?>" />
							</div>
						</div>
						<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
							<div>
								<label class="zbp-form-label"><?php esc_html_e( 'Category', 'zeko-business' ); ?></label>
								<select name="category" class="zbp-form-select">
									<option value=""><?php esc_html_e( 'Select category', 'zeko-business' ); ?></option>
									<?php echo wp_kses( $cat_options, array( 'option' => array( 'value' => true ) ) ); ?>
								</select>
								<div class="zbp-cat-add">
									<input type="text" class="zbp-cat-add__input zbp-form-input zbp-form-input--sm" placeholder="<?php esc_attr_e( 'New category name', 'zeko-business' ); ?>" />
									<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-cat-add__btn"><?php esc_html_e( 'Add', 'zeko-business' ); ?></button>
								</div>
							</div>
							<div>
								<label class="zbp-form-label"><?php esc_html_e( 'Duration', 'zeko-business' ); ?></label>
								<select name="duration" class="zbp-form-select">
									<option value=""><?php esc_html_e( 'Not specified', 'zeko-business' ); ?></option>
									<option value="30min" <?php selected( $svc->duration ?? '', '30min' ); ?>><?php esc_html_e( '30 minutes', 'zeko-business' ); ?></option>
									<option value="1hour" <?php selected( $svc->duration ?? '', '1hour' ); ?>><?php esc_html_e( '1 hour', 'zeko-business' ); ?></option>
									<option value="2hours" <?php selected( $svc->duration ?? '', '2hours' ); ?>><?php esc_html_e( '2 hours', 'zeko-business' ); ?></option>
									<option value="half-day" <?php selected( $svc->duration ?? '', 'half-day' ); ?>><?php esc_html_e( 'Half day', 'zeko-business' ); ?></option>
									<option value="full-day" <?php selected( $svc->duration ?? '', 'full-day' ); ?>><?php esc_html_e( 'Full day', 'zeko-business' ); ?></option>
									<option value="custom" <?php selected( $svc->duration ?? '', 'custom' ); ?>><?php esc_html_e( 'Custom', 'zeko-business' ); ?></option>
								</select>
							</div>
						</div>
						<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
							<div>
								<label class="zbp-form-label"><?php esc_html_e( 'Price', 'zeko-business' ); ?></label>
								<input type="number" step="0.01" min="0" name="price" class="zbp-form-input" value="<?php echo esc_attr( $svc->price ); ?>" />
							</div>
							<div>
								<label class="zbp-form-label"><?php esc_html_e( 'Price Type', 'zeko-business' ); ?></label>
								<select name="price_type" class="zbp-form-select">
									<option value="fixed" <?php selected( $svc->price_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'zeko-business' ); ?></option>
									<option value="hourly" <?php selected( $svc->price_type, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'zeko-business' ); ?></option>
									<option value="starting" <?php selected( $svc->price_type, 'starting' ); ?>><?php esc_html_e( 'Starting at', 'zeko-business' ); ?></option>
								</select>
							</div>
							<div>
								<label class="zbp-form-label"><?php esc_html_e( 'Tags', 'zeko-business' ); ?></label>
								<input type="text" name="tags" class="zbp-form-input" placeholder="<?php esc_attr_e( 'tag1, tag2', 'zeko-business' ); ?>" value="<?php echo esc_attr( $svc->tags ); ?>" />
							</div>
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Service Image', 'zeko-business' ); ?></label>
							<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-upload-service-img"><?php esc_html_e( 'Select Image', 'zeko-business' ); ?></button>
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-checkbox-label">
								<input type="checkbox" name="is_featured" value="1" <?php checked( $svc->is_featured ); ?> />
								<?php esc_html_e( 'Featured service', 'zeko-business' ); ?>
							</label>
						</div>
						<button type="submit" class="zbp-btn zbp-btn--primary zbp-btn--sm"><?php esc_html_e( 'Save Service', 'zeko-business' ); ?></button>
					</form>
				</div>
				<?php
			}
		} else {
			echo '<p class="zbp-empty-state">' . esc_html__( 'No services yet. Add your first service below.', 'zeko-business' ) . '</p>';
		}

		if ( $can_add ) {
			?>
			<div class="zbp-service-create-wrapper" style="margin-top:2rem">
				<h3 class="zbp-portal-section-title"><?php esc_html_e( 'Add New Service', 'zeko-business' ); ?></h3>

				<form class="zbp-portal-form zbp-service-create-form" data-action="zbp_create_service">
					<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
					<input type="hidden" name="image_id" value="0" />

					<div class="zbp-service-create-form__grid">
						<div class="zbp-service-create-form__main">
							<div class="zbp-portal-form__row">
								<label class="zbp-form-label"><?php esc_html_e( 'Service Name', 'zeko-business' ); ?> *</label>
								<input type="text" name="name" class="zbp-form-input" required placeholder="<?php esc_attr_e( 'e.g. Website Design, Plumbing Repair', 'zeko-business' ); ?>" />
							</div>
							<div class="zbp-portal-form__row">
								<label class="zbp-form-label"><?php esc_html_e( 'Short Description', 'zeko-business' ); ?></label>
								<textarea name="short_description" class="zbp-form-textarea" rows="2" placeholder="<?php esc_attr_e( 'Brief summary for listings...', 'zeko-business' ); ?>"></textarea>
							</div>
							<div class="zbp-portal-form__row">
								<label class="zbp-form-label"><?php esc_html_e( 'Full Description', 'zeko-business' ); ?></label>
								<div class="zbp-quill-wrapper" data-field="description">
									<div class="zbp-quill-editor"></div>
									<input type="hidden" name="description" class="zbp-quill-value" />
								</div>
							</div>
							<div class="zbp-portal-form__row zbp-service-create-form__pricing">
								<label class="zbp-form-label"><?php esc_html_e( 'Pricing', 'zeko-business' ); ?></label>
								<div class="zbp-service-create-form__price-row">
									<div class="zbp-service-create-form__price-input">
										<span class="zbp-service-create-form__currency"><?php echo esc_html( $currency ); ?></span>
										<input type="number" step="0.01" min="0" name="price" class="zbp-form-input" placeholder="0.00" />
									</div>
									<select name="price_type" class="zbp-form-select zbp-form-select--sm">
										<option value="fixed"><?php esc_html_e( 'Fixed Price', 'zeko-business' ); ?></option>
										<option value="hourly"><?php esc_html_e( 'Per Hour', 'zeko-business' ); ?></option>
										<option value="starting"><?php esc_html_e( 'Starting At', 'zeko-business' ); ?></option>
									</select>
								</div>
							</div>
						</div>

						<div class="zbp-service-create-form__sidebar">
							<div class="zbp-service-create-form__image-upload">
								<label class="zbp-form-label"><?php esc_html_e( 'Service Image', 'zeko-business' ); ?></label>
								<div class="zbp-service-create-form__image-preview" id="zbp-svc-image-preview">
									<span class="zbp-service-create-form__image-placeholder">&#128247;</span>
								</div>
								<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-upload-service-img" style="width:100%"><?php esc_html_e( 'Select Image', 'zeko-business' ); ?></button>
							</div>
						</div>
					</div>

					<div class="zbp-portal-form__row">
						<label class="zbp-form-label"><?php esc_html_e( 'Category', 'zeko-business' ); ?></label>
						<select name="category" class="zbp-form-select">
							<option value=""><?php esc_html_e( 'Select category...', 'zeko-business' ); ?></option>
						<?php echo wp_kses( $cat_options, array( 'option' => array( 'value' => true ) ) ); ?>
						</select>
						<div class="zbp-cat-add">
							<input type="text" class="zbp-cat-add__input zbp-form-input zbp-form-input--sm" placeholder="<?php esc_attr_e( 'New category name', 'zeko-business' ); ?>" />
							<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-cat-add__btn"><?php esc_html_e( 'Add', 'zeko-business' ); ?></button>
						</div>
					</div>

					<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
						<div>
							<label class="zbp-form-label"><?php esc_html_e( 'Duration', 'zeko-business' ); ?></label>
							<select name="duration" class="zbp-form-select">
								<option value=""><?php esc_html_e( 'Not specified', 'zeko-business' ); ?></option>
								<option value="30min"><?php esc_html_e( '30 minutes', 'zeko-business' ); ?></option>
								<option value="1hour"><?php esc_html_e( '1 hour', 'zeko-business' ); ?></option>
								<option value="2hours"><?php esc_html_e( '2 hours', 'zeko-business' ); ?></option>
								<option value="half-day"><?php esc_html_e( 'Half day', 'zeko-business' ); ?></option>
								<option value="full-day"><?php esc_html_e( 'Full day', 'zeko-business' ); ?></option>
								<option value="custom"><?php esc_html_e( 'Custom', 'zeko-business' ); ?></option>
							</select>
						</div>
						<div>
							<label class="zbp-form-label"><?php esc_html_e( 'Tags', 'zeko-business' ); ?></label>
							<input type="text" name="tags" class="zbp-form-input" placeholder="<?php esc_attr_e( 'tag1, tag2, tag3', 'zeko-business' ); ?>" />
						</div>
						<div>
							<label class="zbp-form-label"><?php esc_html_e( 'Price Note', 'zeko-business' ); ?></label>
							<input type="text" name="price_note" class="zbp-form-input" placeholder="<?php esc_attr_e( 'e.g. per session, materials included', 'zeko-business' ); ?>" />
						</div>
					</div>

					<div class="zbp-portal-form__row">
						<label class="zbp-checkbox-label">
							<input type="checkbox" name="is_featured" value="1" />
						<?php esc_html_e( 'Featured service', 'zeko-business' ); ?>
						</label>
					</div>

					<div class="zbp-service-create-form__footer">
						<button type="submit" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Add Service', 'zeko-business' ); ?></button>
					</div>
				</form>
			</div>
		<?php } else { ?>
			<p class="zbp-form-hint"><?php /* translators: %d: maximum allowed services */ echo esc_html( sprintf( __( 'Service limit reached (%d). Upgrade your plan to add more.', 'zeko-business' ), $max_svc ) ); ?></p>
			<?php
		}
	}

	/**
	 * Render portal reviews.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_reviews( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$reviews = Services::reviews()->get_by_business( (int) $biz->id, 100 );

		echo '<h2>' . esc_html__( 'Reviews', 'zeko-business' ) . '</h2>';
		if ( empty( $reviews ) ) {
			echo '<p>' . esc_html__( 'No reviews yet.', 'zeko-business' ) . '</p>';
			return;
		}

		foreach ( $reviews as $rev ) {
			$author = get_userdata( (int) $rev->user_id );
			?>
			<div class="zbp-review-card">
				<div class="zbp-review-card__header">
					<span class="zbp-review-card__author"><?php echo esc_html( $author ? $author->display_name : __( 'Anonymous', 'zeko-business' ) ); ?></span>
					<span class="zbp-review-card__date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $rev->date_created ) ) ); ?></span>
				</div>
				<div class="zbp-rating"><?php echo esc_html( str_repeat( '★', (int) $rev->rating ) . str_repeat( '☆', 5 - (int) $rev->rating ) ); ?></div>
				<div class="zbp-review-card__content"><?php echo esc_html( $rev->content ); ?></div>
				<?php if ( ! empty( $rev->admin_reply ) ) : ?>
					<div class="zbp-review-card__reply">
						<div class="zbp-review-card__reply-label"><?php esc_html_e( 'Owner Reply', 'zeko-business' ); ?></div>
						<?php echo esc_html( $rev->admin_reply ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Render portal staff.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_staff( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$staff           = Services::team_repo()->get_by_business( (int) $biz->id );
		$pending_invites = Services::staff_invites()->get_pending_by_business( (int) $biz->id );

		echo '<h2>' . esc_html__( 'Staff Members', 'zeko-business' ) . '</h2>';
		if ( ! empty( $staff ) ) :
			?>
			<table class="widefat">
				<thead><tr><th><?php esc_html_e( 'Name', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Email', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Role', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Status', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Actions', 'zeko-business' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $staff as $member ) : ?>
					<?php $member_user = get_userdata( (int) $member->user_id ); ?>
					<tr>
						<td><?php echo esc_html( $member_user ? $member_user->display_name : __( 'Unknown', 'zeko-business' ) ); ?></td>
						<td><?php echo esc_html( $member_user ? $member_user->user_email : '' ); ?></td>
						<td><?php echo esc_html( ucfirst( $member->role ) ); ?></td>
						<td><span class="zbp-badge zbp-badge--active"><?php esc_html_e( 'Active', 'zeko-business' ); ?></span></td>
						<td>
							<?php if ( 'owner' !== $member->role ) : ?>
								<button class="zbp-btn zbp-btn--danger zbp-btn--sm zbp-remove-staff" data-member-id="<?php echo (int) $member->id; ?>"><?php esc_html_e( 'Remove', 'zeko-business' ); ?></button>
							<?php else : ?>
								<?php esc_html_e( 'Owner', 'zeko-business' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No staff members yet.', 'zeko-business' ); ?></p>
			<?php
			endif;

		if ( ! empty( $pending_invites ) ) :
			?>
			<h3 class="zbp-mt-lg"><?php esc_html_e( 'Pending Invitations', 'zeko-business' ); ?></h3>
			<table class="widefat">
				<thead><tr><th><?php esc_html_e( 'Email', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Role', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Expires', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Actions', 'zeko-business' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $pending_invites as $inv ) : ?>
					<tr>
						<td><?php echo esc_html( $inv->email ); ?></td>
						<td><?php echo esc_html( ucfirst( $inv->role ) ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $inv->expires_at ) ) ); ?></td>
						<td>
							<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-resend-invite" data-invite-id="<?php echo (int) $inv->id; ?>"><?php esc_html_e( 'Resend', 'zeko-business' ); ?></button>
							<button type="button" class="zbp-btn zbp-btn--danger zbp-btn--sm zbp-revoke-invite" data-invite-id="<?php echo (int) $inv->id; ?>"><?php esc_html_e( 'Revoke', 'zeko-business' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			endif;

		echo '<h3 class="zbp-mt-lg">' . esc_html__( 'Invite Staff', 'zeko-business' ) . '</h3>';
		?>
		<div class="zbp-invite-tabs">
			<div class="zbp-invite-tab zbp-invite-tab--active" data-invite-tab="existing"><?php esc_html_e( 'Existing User', 'zeko-business' ); ?></div>
			<div class="zbp-invite-tab" data-invite-tab="external"><?php esc_html_e( 'Invite by Email', 'zeko-business' ); ?></div>
		</div>

		<form class="zbp-portal-form zbp-invite-form" data-action="zbp_invite_staff" data-invite-panel="existing">
			<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Email Address', 'zeko-business' ); ?></label>
				<input type="email" name="email" class="zbp-form-input" required placeholder="<?php esc_attr_e( 'Must match an existing account', 'zeko-business' ); ?>" />
				<small class="zbp-form-hint"><?php esc_html_e( 'User must already have an account on this site.', 'zeko-business' ); ?></small>
			</div>
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Role', 'zeko-business' ); ?></label>
				<select name="role" class="zbp-form-select">
					<option value="staff"><?php esc_html_e( 'Staff', 'zeko-business' ); ?></option>
					<option value="editor"><?php esc_html_e( 'Editor', 'zeko-business' ); ?></option>
					<option value="manager"><?php esc_html_e( 'Manager', 'zeko-business' ); ?></option>
				</select>
			</div>
			<button type="submit" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Add to Team', 'zeko-business' ); ?></button>
		</form>

		<form class="zbp-portal-form zbp-invite-form" data-action="zbp_invite_external" data-invite-panel="external" hidden>
			<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Email Address', 'zeko-business' ); ?></label>
				<input type="email" name="email" class="zbp-form-input" required placeholder="<?php esc_attr_e( 'Person may or may not have an account yet', 'zeko-business' ); ?>" />
				<small class="zbp-form-hint"><?php esc_html_e( 'They will receive an email with a link to accept. If they don\'t have an account, they\'ll be prompted to register first.', 'zeko-business' ); ?></small>
			</div>
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Role', 'zeko-business' ); ?></label>
				<select name="role" class="zbp-form-select">
					<option value="staff"><?php esc_html_e( 'Staff', 'zeko-business' ); ?></option>
					<option value="editor"><?php esc_html_e( 'Editor', 'zeko-business' ); ?></option>
					<option value="manager"><?php esc_html_e( 'Manager', 'zeko-business' ); ?></option>
				</select>
			</div>
			<button type="submit" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Send Invitation', 'zeko-business' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render portal media.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_media( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$plan       = $biz->plan ?: 'free';
		$max_photos = \ZBE\Core\Plans::get( $plan, 'max_photos', 0 );

		if ( ! $max_photos ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Photo gallery is not available on your current plan.', 'zeko-business' ) . '</div>';
			return;
		}

		$media = Services::media()->get_by_business( (int) $biz->id );
		$count = count( $media );

		echo '<h2>' . esc_html__( 'Photo Gallery', 'zeko-business' ) . '</h2>';
		?>
		<p>
			<?php if ( $count < $max_photos ) : ?>
				<button type="button" class="zbp-btn zbp-btn--primary" id="zbp-media-picker"><?php esc_html_e( 'Add Photos', 'zeko-business' ); ?></button>
			<?php else : ?>
				<span class="zbp-badge"><?php /* translators: %d: maximum allowed photos */ echo esc_html( sprintf( __( 'Photo limit reached (%d)', 'zeko-business' ), $max_photos ) ); ?></span>
			<?php endif; ?>
			<input type="hidden" id="zbp-media-business-id" value="<?php echo (int) $biz->id; ?>" />
		</p>

		<?php if ( empty( $media ) ) : ?>
			<div class="zbp-empty-state"><?php esc_html_e( 'No photos yet.', 'zeko-business' ); ?></div>
		<?php else : ?>
			<div class="zbp-media-grid">
				<?php foreach ( $media as $m ) : ?>
					<figure class="zbp-media-grid__item">
						<?php
						echo wp_get_attachment_image(
							(int) $m->attachment_id,
							'medium',
							false,
							array(
								'class' => 'zbp-media-grid__img',
								'alt'   => $m->caption ?: get_the_title( (int) $m->attachment_id ),
							)
						);
						?>
						<button type="button" class="zbp-media-grid__delete zbp-media-delete" data-media-id="<?php echo (int) $m->id; ?>" aria-label="<?php esc_attr_e( 'Delete photo', 'zeko-business' ); ?>">×</button>
					</figure>
				<?php endforeach; ?>
			</div>
			<?php
			endif;
	}

	/**
	 * Render portal analytics.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_analytics( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$views   = Services::analytics_repo()->daily_totals_for_business( (int) $biz->id, 14, 'view' );
		$follows = array_sum( Services::analytics_repo()->daily_totals_for_business( (int) $biz->id, 30, 'follow' ) );
		$reviews = array_sum( Services::analytics_repo()->daily_totals_for_business( (int) $biz->id, 30, 'review' ) );

		echo '<h2>' . esc_html__( 'Analytics', 'zeko-business' ) . '</h2>';
		?>
		<div class="zbp-portal-stat-cards">
			<div class="zbp-portal-stat-card">
				<div class="zbp-portal-stat-card__number"><?php echo esc_html( number_format( (int) $biz->view_count ) ); ?></div>
				<div class="zbp-portal-stat-card__label"><?php esc_html_e( 'Total views', 'zeko-business' ); ?></div>
			</div>
			<div class="zbp-portal-stat-card">
				<div class="zbp-portal-stat-card__number"><?php echo esc_html( number_format( (int) $follows ) ); ?></div>
				<div class="zbp-portal-stat-card__label"><?php esc_html_e( 'New followers (30d)', 'zeko-business' ); ?></div>
			</div>
			<div class="zbp-portal-stat-card">
				<div class="zbp-portal-stat-card__number"><?php echo esc_html( number_format( (int) $reviews ) ); ?></div>
				<div class="zbp-portal-stat-card__label"><?php esc_html_e( 'New reviews (30d)', 'zeko-business' ); ?></div>
			</div>
		</div>

		<h3><?php esc_html_e( 'Views — last 14 days', 'zeko-business' ); ?></h3>
		<?php if ( empty( $views ) ) : ?>
			<div class="zbp-empty-state"><?php esc_html_e( 'No view data yet.', 'zeko-business' ); ?></div>
		<?php else : ?>
			<?php
			$max = max( 1, max( $views ) );
			ksort( $views );
			?>
			<div class="zbp-mini-chart" role="img" aria-label="<?php esc_attr_e( 'Daily views for the last 14 days', 'zeko-business' ); ?>">
				<?php foreach ( $views as $day => $count ) : ?>
					<div class="zbp-mini-chart__col" title="<?php echo esc_attr( $day . ': ' . $count ); ?>">
						<span class="zbp-mini-chart__bar" style="height:<?php echo esc_attr( (int) max( 4, round( $count * 100 / $max ) ) ); ?>%"></span>
						<span class="zbp-mini-chart__label"><?php echo esc_html( substr( $day, 8, 2 ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
			endif;
	}

	/**
	 * Render portal hours.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_hours( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$plan = $biz->plan ?: 'free';

		if ( ! \ZBE\Core\Plans::allows( $plan, 'hours' ) ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Business hours are not available on your current plan.', 'zeko-business' ) . '</div>';
			return;
		}

		$hours         = Services::hours()->get_for_business( (int) $biz->id );
		$by_day        = array();
		$day_names     = array( __( 'Sunday', 'zeko-business' ), __( 'Monday', 'zeko-business' ), __( 'Tuesday', 'zeko-business' ), __( 'Wednesday', 'zeko-business' ), __( 'Thursday', 'zeko-business' ), __( 'Friday', 'zeko-business' ), __( 'Saturday', 'zeko-business' ) );
		$hours_mode    = (string) ( $biz->hours_mode ?: '' );
		$show_schedule = in_array( $hours_mode, array( '', 'regular' ), true );

		foreach ( $hours as $h ) {
			$by_day[ (int) $h->day_of_week ] = $h;
		}

		echo '<h2>' . esc_html__( 'Business Hours', 'zeko-business' ) . '</h2>';
		?>
		<form class="zbp-portal-form" data-action="zbp_save_hours">
			<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Hours availability', 'zeko-business' ); ?></label>
				<select name="hours_mode" class="zbp-form-input" data-hours-mode>
					<option value="" <?php selected( $hours_mode, '' ); ?>><?php esc_html_e( 'Regular weekly schedule', 'zeko-business' ); ?></option>
					<option value="always" <?php selected( $hours_mode, 'always' ); ?>><?php esc_html_e( 'Open 24/7', 'zeko-business' ); ?></option>
					<option value="closed" <?php selected( $hours_mode, 'closed' ); ?>><?php esc_html_e( 'Permanently closed', 'zeko-business' ); ?></option>
					<option value="none" <?php selected( $hours_mode, 'none' ); ?>><?php esc_html_e( 'No hours listed', 'zeko-business' ); ?></option>
				</select>
			</div>
			<div class="zbp-hours-schedule" data-hours-schedule<?php echo $show_schedule ? '' : ' hidden'; ?>>
			<table class="zbp-hours-editor">
				<thead><tr><th><?php esc_html_e( 'Day', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Opens', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Closes', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Closed', 'zeko-business' ); ?></th></tr></thead>
				<tbody>
				<?php for ( $i = 0; $i < 7; $i++ ) : ?>
					<?php $row = $by_day[ $i ] ?? null; ?>
					<tr data-day="<?php echo esc_attr( $i ); ?>">
						<td><?php echo esc_html( $day_names[ $i ] ); ?></td>
						<td><input type="time" name="hours[<?php echo esc_attr( $i ); ?>][open]" value="<?php echo esc_attr( $row && ! empty( $row->open_time ) ? substr( $row->open_time, 0, 5 ) : '09:00' ); ?>" /></td>
						<td><input type="time" name="hours[<?php echo esc_attr( $i ); ?>][close]" value="<?php echo esc_attr( $row && ! empty( $row->close_time ) ? substr( $row->close_time, 0, 5 ) : '17:00' ); ?>" /></td>
						<td><input type="checkbox" name="hours[<?php echo esc_attr( $i ); ?>][closed]" value="1" <?php checked( $row ? ! empty( $row->is_closed ) : true ); ?> aria-label="<?php /* translators: %s: day of the week */ echo esc_attr( sprintf( __( 'Closed on %s', 'zeko-business' ), $day_names[ $i ] ) ); ?>" /></td>
					</tr>
				<?php endfor; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-hours-copy"><?php esc_html_e( 'Copy first row to all days', 'zeko-business' ); ?></button>
			</p>
			</div>
			<button type="submit" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Save Hours', 'zeko-business' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render portal businesses.
	 */
	private static function render_portal_businesses(): void {
		$businesses = Services::businesses()->get_by_owner( get_current_user_id(), 100 );
		$current_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in calling handler zbp_portal_tab_load via self::verify().

		echo '<h2>' . esc_html__( 'My Businesses', 'zeko-business' ) . '</h2>';

		if ( empty( $businesses ) ) {
			echo '<div class="zbp-empty-state">' . esc_html__( "You don't have any businesses yet.", 'zeko-business' ) . '</div>';
			return;
		}
		?>
		<div class="zbp-biz-switcher">
			<?php foreach ( $businesses as $b ) : ?>
				<div class="zbp-biz-switcher__item<?php echo (int) $b->id === $current_id ? ' zbp-biz-switcher__item--active' : ''; ?>">
					<a href="<?php echo esc_url( home_url( '/businesses/' . $b->slug . '/' ) ); ?>" class="zbp-biz-switcher__name"><?php echo esc_html( $b->name ); ?></a>
					<span class="zbp-badge zbp-badge--<?php echo esc_attr( $b->status ); ?>"><?php echo esc_html( ucfirst( $b->status ) ); ?></span>
					<?php
					if ( $b->is_verified ) :
						?>
						<span title="<?php esc_attr_e( 'Verified', 'zeko-business' ); ?>">✓</span><?php endif; ?>
					<?php if ( (int) $b->id !== $current_id ) : ?>
						<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-biz-switch" data-business-id="<?php echo (int) $b->id; ?>"><?php esc_html_e( 'Manage', 'zeko-business' ); ?></button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:1rem">
			<a href="<?php echo esc_url( \ZBE\Plugin::page_url( 'submit' ) ); ?>" class="zbp-btn zbp-btn--primary zbp-btn--sm"><?php esc_html_e( 'Create another business', 'zeko-business' ); ?></a>
		</p>
			<?php
	}

	/**
	 * Render portal notifications.
	 */
	private static function render_portal_notifications(): void {
		$notifications = Services::notification_service()->get_for_user( get_current_user_id(), 50 );

		echo '<h2>' . esc_html__( 'Notifications', 'zeko-business' ) . '</h2>';
		if ( empty( $notifications ) ) :
			?>
			<div class="zbp-empty-state"><?php esc_html_e( 'No notifications.', 'zeko-business' ); ?></div>
		<?php else : ?>
			<?php foreach ( $notifications as $n ) : ?>
				<div class="zbp-review-card" style="<?php echo ! $n->is_read ? 'border-left: 3px solid var(--color-primary);' : ''; ?>">
					<div class="zbp-review-card__header">
						<strong><?php echo esc_html( $n->title ); ?></strong>
						<span class="zbp-review-card__date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $n->date_created ) ) ); ?></span>
					</div>
					<div class="zbp-review-card__content"><?php echo esc_html( $n->message ); ?></div>
				</div>
			<?php endforeach; ?>
			<?php
			endif;
	}

	/**
	 * Render portal settings.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_settings( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business to edit settings.', 'zeko-business' ) . '</div>';
			return;
		}

		echo '<h2>' . esc_html__( 'Business Settings', 'zeko-business' ) . '</h2>';
		?>
		<form class="zbp-portal-form" data-action="zbp_update_settings">
			<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Business Name', 'zeko-business' ); ?></label>
				<input type="text" name="name" class="zbp-form-input" value="<?php echo esc_attr( $biz->name ); ?>" required />
			</div>
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Email', 'zeko-business' ); ?></label>
				<input type="email" name="email" class="zbp-form-input" value="<?php echo esc_attr( $biz->email ); ?>" />
			</div>
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Phone', 'zeko-business' ); ?></label>
				<input type="tel" name="phone" class="zbp-form-input" value="<?php echo esc_attr( $biz->phone ); ?>" />
			</div>
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Website', 'zeko-business' ); ?></label>
				<input type="url" name="website" class="zbp-form-input" value="<?php echo esc_attr( $biz->website ); ?>" />
			</div>
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Address', 'zeko-business' ); ?></label>
				<input type="text" name="address" class="zbp-form-input" value="<?php echo esc_attr( $biz->address ); ?>" />
			</div>
			<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
				<div>
					<label class="zbp-form-label"><?php esc_html_e( 'City', 'zeko-business' ); ?></label>
					<input type="text" name="city" class="zbp-form-input" value="<?php echo esc_attr( $biz->city ); ?>" />
				</div>
				<div>
					<label class="zbp-form-label"><?php esc_html_e( 'State', 'zeko-business' ); ?></label>
					<input type="text" name="state" class="zbp-form-input" value="<?php echo esc_attr( $biz->state ); ?>" />
				</div>
				<div>
					<label class="zbp-form-label"><?php esc_html_e( 'Country', 'zeko-business' ); ?></label>
					<input type="text" name="country" class="zbp-form-input" value="<?php echo esc_attr( $biz->country ); ?>" />
				</div>
			</div>

			<?php
				$button_types  = array(
					'call'        => __( 'Call', 'zeko-business' ),
					'whatsapp'    => __( 'WhatsApp', 'zeko-business' ),
					'email'       => __( 'Email', 'zeko-business' ),
					'contact'     => __( 'Contact', 'zeko-business' ),
					'website'     => __( 'Website', 'zeko-business' ),
					'video'       => __( 'Video', 'zeko-business' ),
					'signup'      => __( 'Sign Up', 'zeko-business' ),
					'start_order' => __( 'Start Order', 'zeko-business' ),
					'view_shop'   => __( 'View Shop', 'zeko-business' ),
					'get_tickets' => __( 'Get Tickets', 'zeko-business' ),
				);
				$saved_buttons = is_array( $biz->action_buttons ) ? $biz->action_buttons : array();
				?>
			<div class="zbp-portal-form__row">
				<label class="zbp-form-label"><?php esc_html_e( 'Action Buttons', 'zeko-business' ); ?></label>
				<p class="zbp-form-hint" style="color:var(--color-text-light);font-size:0.8125rem;margin-bottom:0.5rem">
					<?php esc_html_e( 'Shortcut buttons shown on your public listing. Leave a value blank to fall back to your stored contact details.', 'zeko-business' ); ?>
				</p>
				<div class="zbp-action-buttons-repeater" data-zbp-repeater>
					<?php if ( empty( $saved_buttons ) ) : ?>
						<div class="zbp-portal-form__row" style="grid-template-columns:9rem 1fr 1fr auto;display:grid;gap:0.5rem;margin-bottom:0.5rem">
							<select class="zbp-form-input" data-f-type>
								<?php foreach ( $button_types as $k => $l ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" class="zbp-form-input" data-f-label placeholder="<?php esc_attr_e( 'Label (optional)', 'zeko-business' ); ?>" />
							<input type="text" class="zbp-form-input" data-f-value placeholder="<?php esc_attr_e( 'Value / URL', 'zeko-business' ); ?>" />
							<button type="button" class="button" data-zbp-remove>&times;</button>
						</div>
					<?php else : ?>
						<?php foreach ( $saved_buttons as $btn ) : ?>
							<div class="zbp-portal-form__row" style="grid-template-columns:9rem 1fr 1fr auto;display:grid;gap:0.5rem;margin-bottom:0.5rem">
								<select class="zbp-form-input" data-f-type>
									<?php foreach ( $button_types as $k => $l ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>"<?php selected( (string) ( $btn['type'] ?? '' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="text" class="zbp-form-input" data-f-label value="<?php echo esc_attr( (string) ( $btn['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Label (optional)', 'zeko-business' ); ?>" />
								<input type="text" class="zbp-form-input" data-f-value value="<?php echo esc_attr( (string) ( $btn['value'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Value / URL', 'zeko-business' ); ?>" />
								<button type="button" class="button" data-zbp-remove>&times;</button>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<button type="button" class="zbp-btn zbp-btn--ghost zbp-btn--sm" data-zbp-add-action><?php esc_html_e( '+ Add Action Button', 'zeko-business' ); ?></button>
				<input type="hidden" name="action_buttons" value="" />
			</div>
			<button type="submit" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Save Changes', 'zeko-business' ); ?></button>
		</form>
			<?php
	}

	/**
	 * Zbp create business.
	 */
	public static function zbp_create_business(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		if ( RateLimiter::is_limited( 'create_business', 5, 600, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many businesses created. Please try again later.', 'zeko-business' ) );
		}

		// Time-trap — insanely fast submissions are almost always bots.
		$form_ts = absint( wp_unslash( $_POST['zbp_form_ts'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( $form_ts <= 0 || ( time() - $form_ts ) < 3 ) {
			wp_send_json_error( __( 'Invalid submission', 'zeko-business' ) );
		}

		$name = sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( empty( $name ) ) {
			wp_send_json_error( __( 'Business name is required', 'zeko-business' ) );
		}

		$current_user = wp_get_current_user();
		if ( Akismet::is_spam(
			array(
				'author'   => $current_user->display_name,
				'email'    => $current_user->user_email,
				'url'      => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'content'      => $name . "\n" . sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ) . "\n" . sanitize_text_field( wp_unslash( $_POST['short_description'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'comment_type' => 'business_submission',
			)
		) ) {
			wp_send_json_error( __( 'Your submission could not be created.', 'zeko-business' ) );
		}

		$social_links = array();
		$raw_socials  = json_decode( wp_unslash( $_POST['social_links'] ?? '{}' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every social URL is passed through esc_url_raw below.
		if ( is_array( $raw_socials ) ) {
			foreach ( array( 'facebook', 'twitter', 'linkedin', 'youtube', 'instagram' ) as $platform ) {
				if ( ! empty( $raw_socials[ $platform ] ) ) {
					$social_links[ $platform ] = esc_url_raw( $raw_socials[ $platform ] );
				}
			}
		}

		$extra_data = array(
			'tagline'        => sanitize_text_field( wp_unslash( $_POST['tagline'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'founding_year'  => absint( $_POST['founding_year'] ?? 0 ) ?: null, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'employee_count' => sanitize_text_field( wp_unslash( $_POST['employee_count'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'tax_id'         => sanitize_text_field( wp_unslash( $_POST['tax_id'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		);

		$business = Services::business_service()->create(
			array(
				'name'         => $name,
				'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'excerpt'      => sanitize_text_field( wp_unslash( $_POST['short_description'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'email'        => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'phone'        => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'website'      => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'whatsapp'     => sanitize_text_field( wp_unslash( $_POST['whatsapp'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'address'      => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'city'         => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'state'        => sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'country'      => sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'zip'          => sanitize_text_field( wp_unslash( $_POST['zip'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'lat'          => (float) sanitize_text_field( wp_unslash( $_POST['lat'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'lng'          => (float) sanitize_text_field( wp_unslash( $_POST['lng'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'avatar_id'    => absint( $_POST['avatar_id'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'cover_id'     => absint( $_POST['cover_id'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'social_links' => $social_links,
				'extra_data'   => $extra_data,
			),
			$user_id
		);

		if ( ! $business ) {
			wp_send_json_error( __( 'Business could not be created', 'zeko-business' ) );
		}

		$category_ids = array_map( 'absint', (array) ( $_POST['categories'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$category_ids = array_filter( array_unique( $category_ids ) );

		if ( ! empty( $category_ids ) ) {
			$valid_ids = array();
			foreach ( array_slice( $category_ids, 0, 3 ) as $term_id ) {
				if ( get_term( $term_id, 'business_category' ) instanceof \WP_Term ) {
					$valid_ids[] = $term_id;
				}
			}

			if ( ! empty( $valid_ids ) ) {
				wp_set_object_terms( (int) $business->post_id, $valid_ids, 'business_category' );
			}
		}

		$gallery_ids = array();
		if ( ! empty( $_POST['gallery_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$gallery_raw = wp_unslash( $_POST['gallery_ids'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- gallery ids absint-cast below.
			$gallery_ids = array_map( 'absint', is_array( $gallery_raw ) ? $gallery_raw : explode( ',', $gallery_raw ) );
			$gallery_ids = array_values( array_filter( array_unique( $gallery_ids ) ) );
			// Only the owner's own uploads may be added to a business gallery.
			$gallery_ids = self::own_attachments( $gallery_ids, $user_id );
		}

		if ( ! empty( $gallery_ids ) && (int) $business->id > 0 ) {
			$plan  = $business->plan ?: 'free';
			$max   = (int) \ZBE\Core\Plans::get( $plan, 'max_photos', 0 );
			$added = 0;

			$existing = Services::media()->get_by_business( (int) $business->id );
			$visible  = count(
				$max ? array_filter(
					$existing,
					static function ( $m ) {
						return (int) $m->attachment_id > 0;
					}
				) : array()
			);

			$permitted = $max > 0 ? $max : 0;
			$capacity  = max( 0, $permitted - $visible );

			foreach ( array_slice( $gallery_ids, 0, $capacity ) as $attachment_id ) {
				Services::media()->create(
					array(
						'business_id'   => (int) $business->id,
						'attachment_id' => (int) $attachment_id,
						'caption'       => '',
						'sort_order'    => $visible + $added,
					)
				);
				++$added;
			}
		}

		wp_send_json_success(
			array(
				'message' => __( 'Business created!', 'zeko-business' ),
				'url'     => home_url( '/businesses/' . $business->slug . '/' ),
			)
		);
	}

	/**
	 * Zbp update business.
	 */
	public static function zbp_update_business(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$fields      = self::collect_editable_fields();

		if ( ! $business_id ) {
			wp_send_json_error( __( 'Invalid business', 'zeko-business' ) );
		}

		if ( empty( $fields ) ) {
			wp_send_json_error( __( 'Nothing to update', 'zeko-business' ) );
		}

		if ( ! Services::business_service()->update( $business_id, $fields, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized or nothing changed', 'zeko-business' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Business updated!', 'zeko-business' ) ) );
	}

	/**
	 * Zbp create service.
	 */
	public static function zbp_create_service(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || empty( $name ) ) {
			wp_send_json_error( __( 'Business ID and name are required', 'zeko-business' ) );
		}

		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$biz  = Services::businesses()->get( $business_id );
		$plan = $biz ? ( $biz->plan ?: 'free' ) : 'free';
		$max  = \ZBE\Core\Plans::get( $plan, 'max_services', 0 );

		if ( $max > 0 ) {
			$current = Services::services_repo()->count_by_business( $business_id );
			if ( $current >= $max ) {
				wp_send_json_error( __( 'Service limit reached. Upgrade your plan to add more.', 'zeko-business' ) );
			}
		}

		Services::services_repo()->create(
			array(
				'business_id'       => $business_id,
				'name'              => $name,
				'short_description' => sanitize_textarea_field( wp_unslash( $_POST['short_description'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'description'       => self::sanitize_rich( wp_unslash( $_POST['description'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- allow-listed by self::sanitize_rich() (wp_kses/ecosystem sanitizer).
				'price'             => (float) sanitize_text_field( wp_unslash( $_POST['price'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'price_type'        => sanitize_key( $_POST['price_type'] ?? 'fixed' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'price_note'        => sanitize_text_field( wp_unslash( $_POST['price_note'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'category'          => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'image_id'          => absint( $_POST['image_id'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'duration'          => sanitize_text_field( wp_unslash( $_POST['duration'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'tags'              => sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'is_featured'       => ! empty( $_POST['is_featured'] ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			)
		);

		wp_send_json_success( array( 'message' => __( 'Service added!', 'zeko-business' ) ) );
	}

	/**
	 * Zbp add service category.
	 */
	public static function zbp_add_service_category(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || empty( $name ) ) {
			wp_send_json_error( __( 'Business ID and category name are required', 'zeko-business' ) );
		}
		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$category = Services::service_categories()->create( array( 'name' => $name ) );
		if ( ! $category || ! $category->id ) {
			wp_send_json_error( __( 'Could not create category', 'zeko-business' ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Category added!', 'zeko-business' ),
				'id'      => (int) $category->id,
				'slug'    => $category->slug,
				'name'    => $category->name,
			)
		);
	}

	/**
	 * Zbp add product category.
	 */
	public static function zbp_add_product_category(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || empty( $name ) ) {
			wp_send_json_error( __( 'Business ID and category name are required', 'zeko-business' ) );
		}
		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}
		if ( ! class_exists( 'Zeko_Shop' ) ) {
			wp_send_json_error( __( 'Shop plugin is not active.', 'zeko-business' ) );
		}

		$shop_db = \Zeko_Shop::instance()->get_db();
		if ( ! method_exists( $shop_db, 'create_category' ) ) {
			wp_send_json_error( __( 'Product categories are unavailable.', 'zeko-business' ) );
		}

		$category_id = $shop_db->create_category( array( 'name' => $name ) );
		if ( ! $category_id ) {
			wp_send_json_error( __( 'Could not create product category', 'zeko-business' ) );
		}

		$cat = $shop_db->get_category_by_key( sanitize_title( $name ) );

		wp_send_json_success(
			array(
				'message' => __( 'Product category added!', 'zeko-business' ),
				'id'      => (int) $category_id,
				'slug'    => $cat ? (string) $cat['key_slug'] : sanitize_title( $name ),
				'name'    => $cat ? (string) $cat['name'] : $name,
			)
		);
	}


	/**
	 * Zbp update service.
	 */
	public static function zbp_update_service(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$service_id  = absint( $_POST['service_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || ! $service_id ) {
			wp_send_json_error( __( 'Business and service IDs are required', 'zeko-business' ) );
		}

		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$svc = Services::services_repo()->get( $service_id );

		if ( ! $svc || (int) $svc->business_id !== $business_id ) {
			wp_send_json_error( __( 'Invalid service', 'zeko-business' ) );
		}

		$data = array();

		if ( isset( $_POST['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$name = sanitize_text_field( wp_unslash( $_POST['name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			if ( '' === $name ) {
				wp_send_json_error( __( 'Service name is required', 'zeko-business' ) );
			}
			$data['name'] = $name;
		}

		if ( isset( $_POST['description'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['description'] = self::sanitize_rich( wp_unslash( $_POST['description'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- allow-listed by self::sanitize_rich() (wp_kses/ecosystem sanitizer).
		}

		if ( isset( $_POST['short_description'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['short_description'] = sanitize_textarea_field( wp_unslash( $_POST['short_description'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		}

		if ( isset( $_POST['price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['price'] = max( 0, (float) sanitize_text_field( wp_unslash( $_POST['price'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		}

		if ( isset( $_POST['price_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$type               = sanitize_key( $_POST['price_type'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['price_type'] = in_array( $type, array( 'fixed', 'hourly', 'starting' ), true ) ? $type : 'fixed';
		}

		if ( isset( $_POST['category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['category'] = sanitize_text_field( wp_unslash( $_POST['category'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		}

		if ( isset( $_POST['duration'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['duration'] = sanitize_text_field( wp_unslash( $_POST['duration'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		}

		if ( isset( $_POST['tags'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['tags'] = sanitize_text_field( wp_unslash( $_POST['tags'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		}

		if ( isset( $_POST['image_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['image_id'] = absint( $_POST['image_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		}

		if ( isset( $_POST['is_active'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['is_active'] = ! empty( $_POST['is_active'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		}

		if ( isset( $_POST['is_featured'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			$data['is_featured'] = ! empty( $_POST['is_featured'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		}

		if ( empty( $data ) ) {
			wp_send_json_error( __( 'Nothing to update', 'zeko-business' ) );
		}

		Services::services_repo()->update( $service_id, $data );

		wp_send_json_success( array( 'message' => __( 'Service updated!', 'zeko-business' ) ) );
	}

	/**
	 * Zbp delete service.
	 */
	public static function zbp_delete_service(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$service_id  = absint( $_POST['service_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || ! $service_id ) {
			wp_send_json_error( __( 'Business and service IDs are required', 'zeko-business' ) );
		}

		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$svc = Services::services_repo()->get( $service_id );

		if ( ! $svc || (int) $svc->business_id !== $business_id ) {
			wp_send_json_error( __( 'Invalid service', 'zeko-business' ) );
		}

		Services::services_repo()->delete( $service_id );

		wp_send_json_success( array( 'message' => __( 'Service deleted.', 'zeko-business' ) ) );
	}

	/**
	 * Zbp toggle service.
	 */
	public static function zbp_toggle_service(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$service_id  = absint( $_POST['service_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || ! $service_id ) {
			wp_send_json_error( __( 'Business and service IDs are required', 'zeko-business' ) );
		}

		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$svc = Services::services_repo()->get( $service_id );

		if ( ! $svc || (int) $svc->business_id !== $business_id ) {
			wp_send_json_error( __( 'Invalid service', 'zeko-business' ) );
		}

		Services::services_repo()->update( $service_id, array( 'is_active' => empty( $svc->is_active ) ? 1 : 0 ) );

		wp_send_json_success(
			array(
				'message'   => __( 'Service updated!', 'zeko-business' ),
				'is_active' => empty( $svc->is_active ),
			)
		);
	}

	/**
	 * Zbp save hours.
	 */
	public static function zbp_save_hours(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id ) {
			wp_send_json_error( __( 'Invalid business', 'zeko-business' ) );
		}

		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$rows = wp_unslash( $_POST['hours'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified above; each row validated as array and time strings sanitized by the hours service.

		if ( ! is_array( $rows ) ) {
			wp_send_json_error( __( 'Invalid hours data', 'zeko-business' ) );
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				wp_send_json_error( __( 'Invalid hours data', 'zeko-business' ) );
			}
		}

		$mode_raw = sanitize_key( wp_unslash( $_POST['hours_mode'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$mode     = in_array( $mode_raw, array( 'always', 'closed', 'none', 'regular' ), true ) ? $mode_raw : '';

		if ( ! Services::hours()->replace_for_business( $business_id, $rows ) ) {
			wp_send_json_error( __( 'Hours could not be saved', 'zeko-business' ) );
		}

		Services::business_service()->update( $business_id, array( 'hours_mode' => ( 'regular' === $mode ? '' : $mode ) ), $user_id );

		wp_send_json_success( array( 'message' => __( 'Business hours saved!', 'zeko-business' ) ) );
	}

	/**
	 * Zbp add media.
	 */
	public static function zbp_add_media(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id   = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$attachment_id = absint( $_POST['attachment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || ! $attachment_id ) {
			wp_send_json_error( __( 'Business ID and image are required', 'zeko-business' ) );
		}

		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			wp_send_json_error( __( 'Invalid attachment', 'zeko-business' ) );
		}

		// The attachment must belong to the requesting user (admins excepted),.
		// so a user cannot graft someone else's media onto their gallery.
		if ( ! current_user_can( 'manage_options' )
			&& (int) get_post_field( 'post_author', $attachment_id ) !== (int) $user_id ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$existing = Services::media()->get_by_business( $business_id );
		$biz      = Services::businesses()->get( $business_id );
		$plan     = $biz ? ( $biz->plan ?: 'free' ) : 'free';
		$max      = \ZBE\Core\Plans::get( $plan, 'max_photos', 0 );

		if ( ! $max ) {
			wp_send_json_error( __( 'Photo gallery is not available on your current plan.', 'zeko-business' ) );
		}

		if ( count( $existing ) >= $max ) {
			/* translators: %d: photo limit */
			wp_send_json_error( sprintf( __( 'Photo limit reached (%d)', 'zeko-business' ), $max ) );
		}

		Services::media()->create(
			array(
				'business_id'   => $business_id,
				'attachment_id' => $attachment_id,
				'caption'       => sanitize_text_field( wp_unslash( $_POST['caption'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
				'sort_order'    => count( $existing ),
			)
		);

		wp_send_json_success( array( 'message' => __( 'Photo added!', 'zeko-business' ) ) );
	}

	/**
	 * Zbp delete media.
	 */
	public static function zbp_delete_media(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$media_id    = absint( $_POST['media_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $media_id ) {
			wp_send_json_error( __( 'Invalid photo', 'zeko-business' ) );
		}

		$media = Services::media()->get( $media_id );

		if ( ! $media ) {
			wp_send_json_error( __( 'Invalid photo', 'zeko-business' ) );
		}

		if ( ! self::owned_business( (int) $media->business_id, $user_id )
			|| ( $business_id && $business_id !== (int) $media->business_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		Services::media()->delete( $media_id );

		wp_send_json_success( array( 'message' => __( 'Photo removed.', 'zeko-business' ) ) );
	}

	/**
	 * Zbp invite staff.
	 */
	public static function zbp_invite_staff(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$role        = sanitize_key( $_POST['role'] ?? 'staff' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || empty( $email ) ) {
			wp_send_json_error( __( 'Business ID and email are required', 'zeko-business' ) );
		}

		$invited_user = get_user_by( 'email', $email );
		if ( ! $invited_user ) {
			wp_send_json_error( __( 'User not found with that email', 'zeko-business' ) );
		}

		$member = Services::team_service()->invite( $business_id, $email, $role, $user_id );

		if ( ! $member ) {
			wp_send_json_error( __( 'User is already a team member', 'zeko-business' ) );
		}

		/* translators: %s: invited user display name */
		wp_send_json_success( array( 'message' => sprintf( __( '%s has been added to the team!', 'zeko-business' ), $invited_user->display_name ) ) );
	}

	/**
	 * Zbp remove staff.
	 */
	public static function zbp_remove_staff(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$member_id = absint( $_POST['member_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( ! $member_id ) {
			wp_send_json_error( __( 'Invalid member', 'zeko-business' ) );
		}

		if ( ! Services::team_service()->remove( $member_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Staff member removed.', 'zeko-business' ) ) );
	}

	/**
	 * Zbp invite external.
	 */
	public static function zbp_invite_external(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$role        = sanitize_key( $_POST['role'] ?? 'staff' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || empty( $email ) ) {
			wp_send_json_error( __( 'Business ID and email are required', 'zeko-business' ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( __( 'Invalid email address', 'zeko-business' ) );
		}

		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$result = Services::team_service()->invite_external( $business_id, $email, $role, $user_id );

		if ( ! $result ) {
			wp_send_json_error( __( 'Could not send invitation. User may already be a member.', 'zeko-business' ) );
		}

		/* translators: %s: email address */
		wp_send_json_success( array( 'message' => sprintf( __( 'Invitation sent to %s!', 'zeko-business' ), $email ) ) );
	}

	/**
	 * Zbp revoke invite.
	 */
	public static function zbp_revoke_invite(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$invite_id = absint( $_POST['invite_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( ! $invite_id ) {
			wp_send_json_error( __( 'Invalid invite', 'zeko-business' ) );
		}

		if ( ! Services::team_service()->revoke_invite( $invite_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized or invite not found', 'zeko-business' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Invitation revoked.', 'zeko-business' ) ) );
	}

	/**
	 * Zbp resend invite.
	 */
	public static function zbp_resend_invite(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$invite_id = absint( $_POST['invite_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( ! $invite_id ) {
			wp_send_json_error( __( 'Invalid invite', 'zeko-business' ) );
		}

		$result = Services::team_service()->resend_invite( $invite_id, $user_id );

		if ( ! $result ) {
			wp_send_json_error( __( 'Could not resend invitation', 'zeko-business' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Invitation resent!', 'zeko-business' ) ) );
	}

	/**
	 * Zbp update settings.
	 */
	public static function zbp_update_settings(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$fields      = self::collect_editable_fields();

		if ( ! $business_id ) {
			wp_send_json_error( __( 'Invalid business', 'zeko-business' ) );
		}

		if ( empty( $fields ) ) {
			wp_send_json_error( __( 'Nothing to update', 'zeko-business' ) );
		}

		if ( ! Services::business_service()->update( $business_id, $fields, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized or nothing changed', 'zeko-business' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved!', 'zeko-business' ) ) );
	}

	/**
	 * Zbp mark notifications read.
	 */
	public static function zbp_mark_notifications_read(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		Services::notification_service()->mark_all_read( $user_id );

		wp_send_json_success( array( 'message' => __( 'All notifications marked as read.', 'zeko-business' ) ) );
	}

	/**
	 * Zbp submit claim.
	 */
	public static function zbp_submit_claim(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		if ( RateLimiter::is_limited( 'claim', 3, 300, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many claim requests. Please try again later.', 'zeko-business' ) );
		}

		// Honeypot + time-trap spam defenses.
		if ( ! empty( $_POST['zbp_website_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			wp_send_json_error( __( 'Invalid submission', 'zeko-business' ) );
		}
		$form_ts = absint( wp_unslash( $_POST['zbp_form_ts'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( $form_ts <= 0 || ( time() - $form_ts ) < 2 ) {
			wp_send_json_error( __( 'Invalid submission', 'zeko-business' ) );
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$method      = sanitize_key( $_POST['method'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$evidence    = sanitize_textarea_field( wp_unslash( $_POST['evidence'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || empty( $method ) ) {
			wp_send_json_error( __( 'Business ID and method are required', 'zeko-business' ) );
		}

		$current_user = wp_get_current_user();
		if ( Akismet::is_spam(
			array(
				'author'       => $current_user->display_name,
				'email'        => $current_user->user_email,
				'url'          => $current_user->user_url,
				'content'      => $evidence,
				'comment_type' => 'business_claim',
			)
		) ) {
			wp_send_json_error( __( 'Your request could not be submitted.', 'zeko-business' ) );
		}

		$claim = Services::business_service()->claim( $business_id, $user_id, $method, $evidence );

		if ( ! $claim ) {
			wp_send_json_error( __( 'Claim could not be submitted (already claimed or pending)', 'zeko-business' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Claim submitted for review.', 'zeko-business' ) ) );
	}

	/**
	 * Zbp submit verification.
	 */
	public static function zbp_submit_verification(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		if ( RateLimiter::is_limited( 'verify_request', 3, 300, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many verification requests. Please try again later.', 'zeko-business' ) );
		}

		// Honeypot + time-trap spam defenses.
		if ( ! empty( $_POST['zbp_website_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			wp_send_json_error( __( 'Invalid submission', 'zeko-business' ) );
		}
		$form_ts = absint( wp_unslash( $_POST['zbp_form_ts'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( $form_ts <= 0 || ( time() - $form_ts ) < 2 ) {
			wp_send_json_error( __( 'Invalid submission', 'zeko-business' ) );
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$method      = sanitize_key( $_POST['method'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$note        = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || empty( $method ) ) {
			wp_send_json_error( __( 'Business ID and method are required', 'zeko-business' ) );
		}

		$current_user = wp_get_current_user();
		if ( Akismet::is_spam(
			array(
				'author'       => $current_user->display_name,
				'email'        => $current_user->user_email,
				'url'          => $current_user->user_url,
				'content'      => $note,
				'comment_type' => 'business_verification',
			)
		) ) {
			wp_send_json_error( __( 'Your request could not be submitted.', 'zeko-business' ) );
		}

		$request = Services::business_service()->request_verification( $business_id, $user_id, $method, $note );

		if ( ! $request ) {
			wp_send_json_error( __( 'Request could not be submitted (already verified or pending)', 'zeko-business' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Verification request submitted.', 'zeko-business' ) ) );
	}

	/**
	 * Zbp directory search.
	 */
	public static function zbp_directory_search(): void {
		if ( ! self::verify() ) {
			return;
		}

		if ( RateLimiter::is_limited( 'dir_search', 20, 60 ) ) {
			wp_send_json_error( __( 'Too many requests. Please slow down.', 'zeko-business' ) );
		}

		$args = Shortcodes::directory_query_args( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via self::verify().

		$repo         = Services::businesses();
		$total        = $repo->count( $args );
		$args['page'] = max( 1, (int) ( $args['page'] ?? 1 ) );

		$businesses = $repo->find( $args );

		ob_start();

		if ( empty( $businesses ) ) {
			echo '<div class="zbp-directory__empty">' . esc_html__( 'No businesses found.', 'zeko-business' ) . '</div>';
		} else {
			foreach ( $businesses as $biz ) {
				Shortcodes::render_card( $biz );
			}

			$per_page    = (int) $args['per_page'];
			$total_pages = (int) ceil( $total / $per_page );
			$page        = (int) $args['page'];

			if ( $total_pages > 1 ) {
				echo '<div class="zbp-directory__pagination" aria-label="' . esc_attr__( 'Directory pagination', 'zeko-business' ) . '">';

				for ( $i = 1; $i <= $total_pages; $i++ ) {
					echo '<a href="#" data-page="' . esc_attr( $i ) . '" class="zbp-directory__page-link' . ( $i === $page ? ' zbp-directory__page-link--active' : '' ) . '" aria-current="' . ( $i === $page ? 'page' : 'false' ) . '">' . esc_html( $i ) . '</a>';
				}

				echo '</div>';
			}
		}

		wp_send_json_success(
			array(
				'html'       => ob_get_clean(),
				'found'      => (int) $total,
				'totalPages' => isset( $total_pages ) ? $total_pages : 1,
			)
		);
	}

	/**
	 * Whitelist + sanitize the shared profile fields used by both update
	 * endpoints.
	 */
	private static function collect_editable_fields(): array {
		$fields = array();

		$map = array(
			'name'    => 'sanitize_text_field',
			'email'   => 'sanitize_email',
			'phone'   => 'sanitize_text_field',
			'website' => 'esc_url_raw',
			'address' => 'sanitize_textarea_field',
			'city'    => 'sanitize_text_field',
			'state'   => 'sanitize_text_field',
			'country' => 'sanitize_text_field',
		);

		foreach ( $map as $key => $sanitizer ) {
			if ( ! array_key_exists( $key, $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in calling handlers via self::verify().
				continue;
			}

			$fields[ $key ] = call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- value sanitized by the whitelisted $sanitizer callback above.
		}

		// Action buttons: repeater JSON [{type,label,value}].
		if ( array_key_exists( 'action_buttons', $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in calling handlers via self::verify().
			$raw                      = json_decode( wp_unslash( $_POST['action_buttons'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON normalized by self::sanitize_action_buttons() below.
			$fields['action_buttons'] = self::sanitize_action_buttons( is_array( $raw ) ? $raw : array() );
		}

		return $fields;
	}

	/**
	 * Normalize the repeatable action-button list into a safe subset.
	 *
	 * @return array<int, array{type:string,label:string,value:string}>
	 * @param array $raw * @return array<int, array{type:string,label:string,value:string}>.
	 */
	private static function sanitize_action_buttons( array $raw ): array {
		$allowed = array(
			'call',
			'whatsapp',
			'email',
			'contact',
			'website',
			'video',
			'signup',
			'start_order',
			'view_shop',
			'get_tickets',
		);

		$buttons = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$type = sanitize_key( $item['type'] ?? '' );
			if ( ! in_array( $type, $allowed, true ) ) {
				continue;
			}

			$value = trim( (string) ( $item['value'] ?? '' ) );
			if ( '' === $value ) {
				continue;
			}

			$buttons[] = array(
				'type'  => $type,
				'label' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
				'value' => ( 'email' === $type ) ? sanitize_email( $value ) : esc_url_raw( $value ),
			);
		}

		return array_slice( $buttons, 0, 12 );
	}

	/**
	 * Zbp upgrade plan.
	 */
	public static function zbp_upgrade_plan(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$new_plan    = sanitize_text_field( wp_unslash( $_POST['new_plan'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || ! in_array( $new_plan, array( 'basic', 'pro', 'enterprise' ), true ) ) {
			wp_send_json_error( __( 'Invalid request', 'zeko-business' ) );
		}

		if ( ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		$biz = Services::businesses()->get( $business_id );
		if ( ! $biz ) {
			wp_send_json_error( __( 'Business not found', 'zeko-business' ) );
		}

		$settings  = get_option( 'zbe_settings', array() );
		$plans_cfg = is_array( $settings['plans'] ?? null ) ? $settings['plans'] : array();

		$price_key = $new_plan . '_price';
		$amount    = (float) ( $plans_cfg[ $price_key ] ?? \ZBE\Core\Plans::default_prices()[ $new_plan ] ?? 0 );

		if ( $amount <= 0 ) {
			wp_send_json_error( __( 'Invalid plan price', 'zeko-business' ) );
		}

		if ( ! class_exists( 'Zeko_Pay_SDK' ) ) {
			wp_send_json_error( __( 'Payment system unavailable', 'zeko-business' ) );
		}

		$sdk    = new \Zeko_Pay_SDK();
		$result = $sdk->charge(
			$user_id,
			$amount,
			/* translators: 1: business name. 2: new plan name */
			sprintf( __( 'Upgrade "%1$s" to %2$s', 'zeko-business' ), $biz->name, ucfirst( $new_plan ) ),
			array(
				'source'      => 'zeko_business',
				'business_id' => $business_id,
				'new_plan'    => $new_plan,
				'old_plan'    => $biz->plan ?: 'free',
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( $result['message'] ?? __( 'Payment failed', 'zeko-business' ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Payment successful. Plan upgraded.', 'zeko-business' ),
				'tx_id'   => $result['tx_id'] ?? 0,
			)
		);
	}

	/**
	 * Zbp book service.
	 */
	public static function zbp_book_service(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		if ( RateLimiter::is_limited( 'book_service', 10, 300, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many booking attempts. Please try again later.', 'zeko-business' ) );
		}

		$service_id  = absint( $_POST['service_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $service_id || ! $business_id ) {
			wp_send_json_error( __( 'Invalid service or business', 'zeko-business' ) );
		}

		$svc = Services::services_repo()->get( $service_id );
		if ( ! $svc || (int) $svc->business_id !== $business_id || ! $svc->is_active ) {
			wp_send_json_error( __( 'Service not found', 'zeko-business' ) );
		}

		if ( (float) $svc->price <= 0 ) {
			wp_send_json_error( __( 'This service is free — no payment required', 'zeko-business' ) );
		}

		if ( ! class_exists( 'Zeko_Pay_SDK' ) ) {
			wp_send_json_error( __( 'Payment system unavailable', 'zeko-business' ) );
		}

		$sdk    = new \Zeko_Pay_SDK();
		$result = $sdk->charge(
			$user_id,
			(float) $svc->price,
			/* translators: 1: service name. 2: site name */
			sprintf( __( 'Book "%1$s" at %2$s', 'zeko-business' ), $svc->name, get_bloginfo( 'name' ) ),
			array(
				'source'      => 'zeko_business',
				'type'        => 'service_booking',
				'service_id'  => $service_id,
				'business_id' => $business_id,
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( $result['message'] ?? __( 'Payment failed', 'zeko-business' ) );
		}

		wp_send_json_success(
			array(
				/* translators: %s: service name */
				'message' => sprintf( __( 'Booking confirmed for "%s"!', 'zeko-business' ), $svc->name ),
				'tx_id'   => $result['tx_id'] ?? 0,
			)
		);
	}

	/**
	 * Zbp buy product.
	 */
	public static function zbp_buy_product(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		if ( RateLimiter::is_limited( 'buy_product', 10, 300, (string) $user_id ) ) {
			wp_send_json_error( __( 'Too many purchase attempts. Please try again later.', 'zeko-business' ) );
		}

		$product_id  = absint( $_POST['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $product_id || ! $business_id ) {
			wp_send_json_error( __( 'Invalid product or business', 'zeko-business' ) );
		}

		if ( ! class_exists( 'Zeko_Shop' ) ) {
			wp_send_json_error( __( 'Shop plugin is not active.', 'zeko-business' ) );
		}

		$shop_db = \Zeko_Shop::instance()->get_db();
		$prod    = $shop_db->get_product( $product_id );
		if ( ! $prod || 'business' !== ( $prod['external_type'] ?? '' ) || (int) ( $prod['external_id'] ?? 0 ) !== $business_id ) {
			wp_send_json_error( __( 'Product not found', 'zeko-business' ) );
		}

		if ( 'active' !== ( $prod['status'] ?? '' ) ) {
			wp_send_json_error( __( 'Product is not available', 'zeko-business' ) );
		}

		$stock = (int) ( $prod['stock'] ?? -1 );
		if ( 0 === $stock ) {
			wp_send_json_error( __( 'Product is out of stock', 'zeko-business' ) );
		}

		$price = (float) ( $prod['price'] ?? 0 );
		if ( $price <= 0 ) {
			wp_send_json_error( __( 'Invalid product price', 'zeko-business' ) );
		}

		if ( ! class_exists( 'Zeko_Pay_SDK' ) ) {
			wp_send_json_error( __( 'Payment system unavailable', 'zeko-business' ) );
		}

		$sdk    = new \Zeko_Pay_SDK();
		$result = $sdk->charge(
			$user_id,
			$price,
			/* translators: 1: product title. 2: site name */
			sprintf( __( 'Buy "%1$s" from %2$s', 'zeko-business' ), $prod['title'] ?? '', get_bloginfo( 'name' ) ),
			array(
				'source'      => 'zeko_business',
				'type'        => 'product_purchase',
				'product_id'  => $product_id,
				'business_id' => $business_id,
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( $result['message'] ?? __( 'Payment failed', 'zeko-business' ) );
		}

		if ( $stock > 0 ) {
			$shop_db->decrement_stock( $product_id, 1 );
		}

		wp_send_json_success(
			array(
				/* translators: %s: product title */
				'message' => sprintf( __( 'Purchase confirmed for "%s"!', 'zeko-business' ), $prod['title'] ?? '' ),
				'tx_id'   => $result['tx_id'] ?? 0,
			)
		);
	}

	/**
	 * Zbp save product.
	 */
	public static function zbp_save_product(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		if ( ! $business_id || ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		if ( ! class_exists( 'Zeko_Shop' ) ) {
			wp_send_json_error( __( 'Shop plugin is not active', 'zeko-business' ) );
		}

		$shop_db    = \Zeko_Shop::instance()->get_db();
		$product_id = absint( $_POST['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( empty( $title ) ) {
			wp_send_json_error( __( 'Product name is required', 'zeko-business' ) );
		}

		$gallery_raw = sanitize_text_field( wp_unslash( $_POST['gallery_urls'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$gallery     = ! empty( $gallery_raw ) ? json_decode( $gallery_raw, true ) : array();

		$data = array(
			'title'             => $title,
			'short_description' => self::sanitize_rich( wp_unslash( $_POST['short_description'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- allow-listed by self::sanitize_rich() (wp_kses/ecosystem sanitizer).
			'description'       => self::sanitize_rich( wp_unslash( $_POST['description'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- allow-listed by self::sanitize_rich() (wp_kses/ecosystem sanitizer).
			'price'             => (float) sanitize_text_field( wp_unslash( $_POST['price'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'sale_price'        => sanitize_text_field( wp_unslash( $_POST['sale_price'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'category'          => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'tags'              => sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'sku'               => sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'product_type'      => sanitize_text_field( wp_unslash( $_POST['product_type'] ?? 'physical' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'stock'             => (int) sanitize_text_field( wp_unslash( $_POST['stock'] ?? -1 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'status'            => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'is_featured'       => ! empty( $_POST['is_featured'] ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'image_url'         => esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
			'gallery_urls'      => ! empty( $gallery ) ? wp_json_encode( $gallery ) : null,
			'file_url'          => esc_url_raw( wp_unslash( $_POST['file_url'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		);

		if ( $product_id > 0 ) {
			$existing = $shop_db->get_product( $product_id );
			if ( ! $existing || 'business' !== ( $existing['external_type'] ?? '' ) || (int) ( $existing['external_id'] ?? 0 ) !== $business_id ) {
				wp_send_json_error( __( 'Product not found', 'zeko-business' ) );
			}
			$shop_db->update_product( $product_id, $data );
			wp_send_json_success( array( 'message' => __( 'Product updated!', 'zeko-business' ) ) );
		} else {
			$data['external_type'] = 'business';
			$data['external_id']   = $business_id;
			$new_id                = $shop_db->create_product( $data );
			if ( $new_id < 1 ) {
				wp_send_json_error( __( 'Failed to create product', 'zeko-business' ) );
			}
			wp_send_json_success(
				array(
					'message'    => __( 'Product created!', 'zeko-business' ),
					'product_id' => $new_id,
				)
			);
		}
	}

	/**
	 * Zbp delete product.
	 */
	public static function zbp_delete_product(): void {
		if ( ! self::verify() ) {
			return;
		}
		$user_id = self::require_login();
		if ( ! $user_id ) {
			return;
		}

		$business_id = absint( $_POST['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.
		$product_id  = absint( $_POST['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via self::verify() at handler top.

		if ( ! $business_id || ! $product_id || ! self::owned_business( $business_id, $user_id ) ) {
			wp_send_json_error( __( 'Not authorized', 'zeko-business' ) );
		}

		if ( ! class_exists( 'Zeko_Shop' ) ) {
			wp_send_json_error( __( 'Shop plugin is not active', 'zeko-business' ) );
		}

		$shop_db  = \Zeko_Shop::instance()->get_db();
		$existing = $shop_db->get_product( $product_id );

		if ( ! $existing || 'business' !== ( $existing['external_type'] ?? '' ) || (int) ( $existing['external_id'] ?? 0 ) !== $business_id ) {
			wp_send_json_error( __( 'Product not found', 'zeko-business' ) );
		}

		$shop_db->delete_product( $product_id );
		wp_send_json_success( array( 'message' => __( 'Product deleted.', 'zeko-business' ) ) );
	}

	/**
	 * Init hooks.
	 */
	public static function init_hooks(): void {
		add_action( 'zeko_pay_transaction_created', array( __CLASS__, 'on_transaction_completed' ), 10, 6 );
	}

	/**
	 * Render portal jobs.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_jobs( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$jobs_active = defined( 'ZEKO_JOBS_VERSION' ) || class_exists( 'Zeko_Jobs_DB' );
		if ( ! $jobs_active ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Jobs plugin is not active.', 'zeko-business' ) . '</div>';
			return;
		}

		echo '<div class="zbp-portal-section-header">';
		echo '<h2 class="zbp-portal-section-title">' . esc_html__( 'Jobs', 'zeko-business' ) . '</h2>';
		echo '<p class="zbp-portal-section-desc">' . esc_html__( 'Post job openings for your business. Jobs will appear on your public profile and the jobs board.', 'zeko-business' ) . '</p>';
		echo '</div>';

		echo '<div class="zbp-embedded-form">';
		if ( shortcode_exists( 'zeko_jobs_post_form' ) ) {
			echo do_shortcode( '[zeko_jobs_post_form]' );
		} else {
			echo '<p>' . esc_html__( 'Job posting form not available.', 'zeko-business' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render portal qa.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_qa( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$qa_active = defined( 'ZEKO_QA_VERSION' ) || class_exists( 'Zeko_QA' );
		if ( ! $qa_active ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Q&A plugin is not active.', 'zeko-business' ) . '</div>';
			return;
		}

		echo '<div class="zbp-portal-section-header">';
		echo '<h2 class="zbp-portal-section-title">' . esc_html__( 'Questions & Answers', 'zeko-business' ) . '</h2>';
		echo '<p class="zbp-portal-section-desc">' . esc_html__( 'Ask questions related to your business or browse the community Q&A.', 'zeko-business' ) . '</p>';
		echo '</div>';

		echo '<div class="zbp-embedded-form">';
		if ( shortcode_exists( 'zeko_qa_ask_form' ) ) {
			echo do_shortcode( '[zeko_qa_ask_form]' );
		} else {
			echo '<p>' . esc_html__( 'Question form not available.', 'zeko-business' ) . '</p>';
		}
		echo '</div>';

		if ( shortcode_exists( 'zeko_qa_archive' ) ) {
			echo '<div class="zbp-portal-section-header" style="margin-top:2rem">';
			echo '<h3 class="zbp-portal-section-title">' . esc_html__( 'Recent Questions', 'zeko-business' ) . '</h3>';
			echo '</div>';
			echo '<div class="zbp-embedded-shortcode">';
			echo do_shortcode( '[zeko_qa_archive]' );
			echo '</div>';
		}
	}

	/**
	 * Key an array of row/entity objects by their numeric id, deduplicating on
	 * collisions (last wins).
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
	 * Render portal requests.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_requests( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$qa_active = defined( 'ZEKO_QA_VERSION' ) || class_exists( 'Zeko_QA' );
		if ( ! $qa_active ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Q&A plugin is not active.', 'zeko-business' ) . '</div>';
			return;
		}

		echo '<div class="zbp-portal-section-header">';
		echo '<h2 class="zbp-portal-section-title">' . esc_html__( 'Requests & Mentions', 'zeko-business' ) . '</h2>';
		echo '<p class="zbp-portal-section-desc">' . esc_html__( 'Questions where your business name or your name is mentioned will appear here.', 'zeko-business' ) . '</p>';
		echo '</div>';

		$biz_name   = mb_strtolower( $biz->name );
		$owner      = get_userdata( (int) $biz->owner_id );
		$owner_name = $owner ? mb_strtolower( $owner->display_name ) : '';

		global $wpdb;
		$table = $wpdb->prefix . 'zeko_questions';

		// Prefer the structured mention index, then merge with a LIKE fallback.
		// so historical questions (asked before the index existed) still show.
		$mention  = Services::mention_service();
		$by_index = $mention->get_owner_portal_mentions( (int) $biz->owner_id );
		$by_index = self::key_by_id( $by_index );

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB
		$by_like = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE (LOWER(title) LIKE %s OR LOWER(content) LIKE %s) AND status = 'open' ORDER BY created_at DESC LIMIT 20",
				'%' . $wpdb->esc_like( $biz_name ) . '%',
				'%' . $wpdb->esc_like( $biz_name ) . '%'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( $by_like as $q ) {
			$by_index[ (int) $q->id ] = $q;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $owner_name && mb_strlen( $owner_name ) > 2 ) {
            //phpcs:ignore WordPress.DB
			$by_like_owner = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE (LOWER(title) LIKE %s OR LOWER(content) LIKE %s) AND status = 'open' ORDER BY created_at DESC LIMIT 20",
					'%' . $wpdb->esc_like( $owner_name ) . '%',
					'%' . $wpdb->esc_like( $owner_name ) . '%'
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			foreach ( $by_like_owner as $q ) {
				$by_index[ (int) $q->id ] = $q;
			}
		}

		$questions = array_values( $by_index );

		if ( empty( $questions ) ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'No mentions found yet.', 'zeko-business' ) . '</div>';
			return;
		}

		foreach ( $questions as $q ) {
			$author = get_userdata( (int) $q->user_id );
			?>
			<div class="zbp-review-card">
				<div class="zbp-review-card__header">
					<strong><?php echo esc_html( $q->title ); ?></strong>
					<span class="zbp-review-card__date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $q->created_at ) ) ); ?></span>
				</div>
				<div class="zbp-review-card__content"><?php echo esc_html( wp_trim_words( $q->content, 30 ) ); ?></div>
				<div style="margin-top:0.5rem;font-size:0.8125rem;color:var(--color-text-light)">
					<?php echo esc_html( $author ? $author->display_name : __( 'Anonymous', 'zeko-business' ) ); ?>
					— <a href="<?php echo esc_url( home_url( '/questions/' . ( $q->slug ?? $q->id ) . '/' ) ); ?>"><?php esc_html_e( 'View Question', 'zeko-business' ); ?></a>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render the "My Claims" portal tab: every claim the current user has
	 * submitted across the directory, with its review status. This is the
	 * owner-facing surface for tracking claims on businesses they may not
	 * own yet.
	 */
	private static function render_portal_claims(): void {
		$user_id  = get_current_user_id();
		$claims   = Services::claims()->find(
			array(
				'user_id' => $user_id,
				'limit'   => 100,
			)
		);
		$biz_repo = Services::businesses();

		echo '<div class="zbp-portal-section-header">';
		echo '<h2 class="zbp-portal-section-title">' . esc_html__( 'My Claims', 'zeko-business' ) . '</h2>';
		echo '<p class="zbp-portal-section-desc">' . esc_html__( 'Track the status of business listings you have claimed.', 'zeko-business' ) . '</p>';
		echo '</div>';

		if ( empty( $claims ) ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'You have not submitted any claims yet.', 'zeko-business' ) . '</div>';
			return;
		}

		echo '<div class="zbp-claim-list">';
		foreach ( $claims as $claim ) {
			$business = $biz_repo->get( (int) $claim->business_id );
			$biz_name = $business ? $business->name : sprintf( '#%d', $claim->business_id );
			$status   = (string) $claim->status;
			$badge    = in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ? $status : 'pending';
			?>
			<div class="zbp-review-card">
				<div class="zbp-review-card__header">
					<strong><?php echo esc_html( $biz_name ); ?></strong>
					<span class="zbp-badge zbp-badge--<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
				</div>
				<div class="zbp-review-card__content">
					<?php if ( ! empty( $claim->method ) ) : ?>
						<span class="zbp-muted"><?php echo esc_html__( 'Method', 'zeko-business' ) . ': ' . esc_html( ucfirst( $claim->method ) ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $claim->evidence ) ) : ?>
						<div style="margin-top:0.25rem"><?php echo esc_html( wp_trim_words( $claim->evidence, 30 ) ); ?></div>
					<?php endif; ?>
				</div>
				<div class="zbp-review-card__date">
					<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $claim->date_created ) ) ); ?>
					<?php if ( ! empty( $claim->reviewed_at ) ) : ?>
						&middot; <?php echo esc_html__( 'Reviewed', 'zeko-business' ) . ' ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $claim->reviewed_at ) ) ); ?>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Render a single review card as HTML (used by AJAX review submission
	 * so the new review can be prepended to the list client-side).
	 *
	 * @param object    $review Review.
	 * @param ?\WP_User $author Author.
	 */
	public static function render_single_review( object $review, ?\WP_User $author ): string {
		ob_start();
		$author_name = $author ? $author->display_name : __( 'Anonymous', 'zeko-business' );
		$photos      = ! empty( $review->photos ) && is_array( $review->photos ) ? $review->photos : array();
		$user_vote   = get_current_user_id() > 0 ? Services::reviews()->get_user_vote( (int) $review->id, (int) get_current_user_id() ) : 0;
		$helper      = (int) ( $review->helpful_count ?? 0 );
		?>
		<div class="zbp-review-card zbp-review-card--new" data-review-id="<?php echo (int) $review->id; ?>">
			<div class="zbp-review-card__header">
				<span class="zbp-review-card__author"><?php echo esc_html( $author_name ); ?></span>
				<span class="zbp-review-card__date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $review->date_created ) ) ); ?></span>
			</div>
			<div class="zbp-rating"><?php echo esc_html( str_repeat( '★', (int) $review->rating ) . str_repeat( '☆', 5 - (int) $review->rating ) ); ?></div>
			<?php if ( ! empty( $review->criteria ) && is_array( $review->criteria ) ) : ?>
				<div class="zbp-review-card__criteria">
					<?php foreach ( $review->criteria as $criterion => $stars ) : ?>
						<div class="zbp-review-card__criterion">
							<span class="zbp-review-card__criterion-label"><?php echo esc_html( $criterion ); ?></span>
							<span class="zbp-review-card__criterion-stars"><?php echo esc_html( str_repeat( '★', (int) $stars ) . str_repeat( '☆', 5 - (int) $stars ) ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $review->title ) ) : ?>
				<div class="zbp-review-card__title"><strong><?php echo esc_html( $review->title ); ?></strong></div>
			<?php endif; ?>
			<div class="zbp-review-card__content"><?php echo esc_html( $review->content ); ?></div>
			<?php if ( ! empty( $photos ) ) : ?>
				<div class="zbp-review-card__photos">
					<?php
					foreach ( $photos as $pid ) :
						$pid = (int) $pid;
						if ( $pid < 1 ) {
							continue;
						} $img = wp_get_attachment_image_src( $pid, 'thumbnail' );
						if ( ! $img ) {
							continue; }
						?>
						<a href="<?php echo esc_url( wp_get_attachment_url( $pid ) ); ?>" target="_blank" rel="noopener">
							<img src="<?php echo esc_url( $img[0] ); ?>" alt="" loading="lazy" class="zbp-review-card__photo" />
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="zbp-review-card__actions">
				<button type="button" class="zbp-helpful-btn<?php echo 1 === $user_vote ? ' zbp-helpful-btn--active' : ''; ?>" data-review-id="<?php echo (int) $review->id; ?>" data-value="1" aria-pressed="<?php echo 1 === $user_vote ? 'true' : 'false'; ?>">
					<?php esc_html_e( 'Helpful', 'zeko-business' ); ?> (<span class="zbp-helpful-count"><?php echo (int) $helper; ?></span>)
				</button>
				<button type="button" class="zbp-helpful-btn zbp-helpful-btn--down<?php echo -1 === $user_vote ? ' zbp-helpful-btn--active' : ''; ?>" data-review-id="<?php echo (int) $review->id; ?>" data-value="-1" aria-pressed="<?php echo -1 === $user_vote ? 'true' : 'false'; ?>">
					<?php esc_html_e( 'Not helpful', 'zeko-business' ); ?>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render portal products.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_products( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$shop_active = class_exists( 'Zeko_Shop' );
		if ( ! $shop_active ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Shop plugin is not active.', 'zeko-business' ) . '</div>';
			return;
		}

		$shop_db  = \Zeko_Shop::instance()->get_db();
		$products = $shop_db->get_products_by_external( 'business', (int) $biz->id );
		$settings = get_option( 'zbe_settings', array() );
		$currency = is_array( $settings ) ? ( $settings['general']['currency'] ?? '$' ) : '$';

		$product_categories = apply_filters(
			'zbp_product_categories',
			array(
				'physical'     => __( 'Physical Product', 'zeko-business' ),
				'digital'      => __( 'Digital Product', 'zeko-business' ),
				'service'      => __( 'Service-based', 'zeko-business' ),
				'subscription' => __( 'Subscription', 'zeko-business' ),
				'downloadable' => __( 'Downloadable', 'zeko-business' ),
				'equipment'    => __( 'Equipment', 'zeko-business' ),
				'software'     => __( 'Software', 'zeko-business' ),
				'other'        => __( 'Other', 'zeko-business' ),
			)
		);

		// Prefer the Zeko Shop product categories store (persisted + editable),.
		// falling back to the hardcoded defaults when unavailable.
		if ( method_exists( $shop_db, 'get_categories' ) ) {
			$shop_cats = $shop_db->get_categories( true );
			if ( ! empty( $shop_cats ) ) {
				$product_categories = array();
				foreach ( $shop_cats as $c ) {
					$product_categories[ (string) $c['key_slug'] ] = (string) $c['name'];
				}
			}
		}

		$edit_product = null;
		$edit_id      = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET param used only to pre-fill the product form; no state change.
		if ( $edit_id ) {
			foreach ( $products as $p ) {
				if ( (int) ( $p['product_id'] ?? 0 ) === $edit_id ) {
					$edit_product = $p;
					break;
				}
			}
		}

		echo '<div class="zbp-portal-section-header">';
		echo '<h2 class="zbp-portal-section-title">' . esc_html__( 'Products', 'zeko-business' ) . '</h2>';
		echo '<p class="zbp-portal-section-desc">' . esc_html__( 'Create and manage products for your business. Products are powered by Zeko Shop.', 'zeko-business' ) . '</p>';
		echo '</div>';

		if ( ! empty( $products ) ) :
			?>
			<div class="zbp-product-list">
				<?php
				foreach ( $products as $prod ) :
					$pid   = (int) ( $prod['product_id'] ?? 0 );
					$sale  = ! empty( $prod['sale_price'] ) ? (float) $prod['sale_price'] : 0;
					$price = (float) ( $prod['price'] ?? 0 );
					?>
					<div class="zbp-product-card" data-product-id="<?php echo esc_attr( $pid ); ?>">
						<div class="zbp-product-card__image">
							<?php if ( ! empty( $prod['image_url'] ) ) : ?>
								<img src="<?php echo esc_url( $prod['image_url'] ); ?>" alt="<?php echo esc_attr( $prod['title'] ?? '' ); ?>" />
							<?php else : ?>
								<span class="zbp-product-card__no-image">&#128230;</span>
							<?php endif; ?>
						</div>
						<div class="zbp-product-card__body">
							<h4 class="zbp-product-card__title"><?php echo esc_html( $prod['title'] ?? '' ); ?></h4>
							<div class="zbp-product-card__meta">
								<?php if ( ! empty( $prod['category'] ) ) : ?>
									<span class="zbp-badge zbp-badge--sm"><?php echo esc_html( ucfirst( $prod['category'] ) ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $prod['sku'] ) ) : ?>
									<span class="zbp-product-card__sku">SKU: <?php echo esc_html( $prod['sku'] ); ?></span>
								<?php endif; ?>
							</div>
							<div class="zbp-product-card__price">
								<?php if ( $sale > 0 && $sale < $price ) : ?>
									<span class="zbp-price--sale"><?php echo esc_html( $currency . number_format( $sale, 2 ) ); ?></span>
									<span class="zbp-price--original"><?php echo esc_html( $currency . number_format( $price, 2 ) ); ?></span>
								<?php else : ?>
									<span><?php echo esc_html( $currency . number_format( $price, 2 ) ); ?></span>
								<?php endif; ?>
							</div>
							<div class="zbp-product-card__status">
								<?php if ( ( 'active' ) === ( $prod['status'] ?? '' ) ) : ?>
									<span class="zbp-badge zbp-badge--active"><?php esc_html_e( 'Active', 'zeko-business' ); ?></span>
								<?php else : ?>
									<span class="zbp-badge"><?php esc_html_e( 'Inactive', 'zeko-business' ); ?></span>
								<?php endif; ?>
								<?php
								$stock = (int) ( $prod['stock'] ?? -1 );
								if ( 0 === $stock ) :
									?>
									<span class="zbp-badge" style="background:var(--color-danger);color:#fff"><?php esc_html_e( 'Out of stock', 'zeko-business' ); ?></span>
								<?php elseif ( $stock > 0 ) : ?>
									<span class="zbp-badge"><?php /* translators: %d: number of units in stock */ echo esc_html( sprintf( __( 'Stock: %d', 'zeko-business' ), $stock ) ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="zbp-product-card__actions">
							<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-edit-shop-product" data-product-id="<?php echo esc_attr( $pid ); ?>"><?php esc_html_e( 'Edit', 'zeko-business' ); ?></button>
							<button type="button" class="zbp-btn zbp-btn--danger zbp-btn--sm zbp-delete-shop-product" data-product-id="<?php echo esc_attr( $pid ); ?>"><?php esc_html_e( 'Delete', 'zeko-business' ); ?></button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="zbp-empty-state"><?php esc_html_e( 'No products yet. Create your first product below.', 'zeko-business' ); ?></div>
		<?php endif; ?>

		<div class="zbp-product-form-wrapper" style="margin-top:2rem">
			<h3 class="zbp-portal-section-title"><?php echo $edit_product ? esc_html__( 'Edit Product', 'zeko-business' ) : esc_html__( 'Add New Product', 'zeko-business' ); ?></h3>

			<form class="zbp-portal-form zbp-product-form" data-action="zbp_save_product">
				<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
				<input type="hidden" name="product_id" value="<?php echo $edit_product ? esc_attr( (int) ( $edit_product['product_id'] ?? 0 ) ) : '0'; ?>" />
				<input type="hidden" name="image_url" value="<?php echo $edit_product ? esc_url( $edit_product['image_url'] ?? '' ) : ''; ?>" />
				<input type="hidden" name="gallery_urls" value="<?php echo $edit_product ? esc_attr( $edit_product['gallery_urls'] ?? '' ) : ''; ?>" />
				<input type="hidden" name="file_url" value="<?php echo $edit_product ? esc_url( $edit_product['file_url'] ?? '' ) : ''; ?>" />

				<div class="zbp-product-form__grid">
					<div class="zbp-product-form__main">
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Product Name', 'zeko-business' ); ?> *</label>
							<input type="text" name="title" class="zbp-form-input" required placeholder="<?php esc_attr_e( 'e.g. Premium Widget, Service Package', 'zeko-business' ); ?>" value="<?php echo $edit_product ? esc_attr( $edit_product['title'] ?? '' ) : ''; ?>" />
						</div>

						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Short Description', 'zeko-business' ); ?></label>
							<textarea name="short_description" class="zbp-form-textarea" rows="2" placeholder="<?php esc_attr_e( 'Brief summary shown in product listings...', 'zeko-business' ); ?>"><?php echo $edit_product ? esc_textarea( $edit_product['short_description'] ?? '' ) : ''; ?></textarea>
						</div>

						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Full Description', 'zeko-business' ); ?></label>
							<div class="zbp-quill-wrapper" data-field="description">
								<div class="zbp-quill-editor"></div>
								<input type="hidden" name="description" class="zbp-quill-value" value="<?php echo $edit_product ? esc_attr( $edit_product['description'] ?? '' ) : ''; ?>" />
							</div>
						</div>
					</div>

					<div class="zbp-product-form__sidebar">
						<div class="zbp-product-form__image-section">
							<label class="zbp-form-label"><?php esc_html_e( 'Product Image', 'zeko-business' ); ?></label>
							<div class="zbp-product-form__image-preview zbp-media-upload" data-target="image_url">
									<?php if ( $edit_product && ! empty( $edit_product['image_url'] ) ) : ?>
									<img src="<?php echo esc_url( $edit_product['image_url'] ); ?>" class="zbp-media-preview-img" />
								<?php else : ?>
									<span class="zbp-media-placeholder">&#128247;</span>
								<?php endif; ?>
							</div>
							<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-media-select"><?php esc_html_e( 'Select Image', 'zeko-business' ); ?></button>
							<button type="button" class="zbp-btn zbp-btn--ghost zbp-btn--sm zbp-media-remove" style="display:none"><?php esc_html_e( 'Remove', 'zeko-business' ); ?></button>
						</div>

						<div class="zbp-product-form__gallery-section">
							<label class="zbp-form-label"><?php esc_html_e( 'Gallery Images', 'zeko-business' ); ?></label>
							<div class="zbp-gallery-grid zbp-media-gallery-upload" data-target="gallery_urls">
									<?php
									$gallery = array();
									if ( $edit_product && ! empty( $edit_product['gallery_urls'] ) ) {
										$gallery = json_decode( $edit_product['gallery_urls'], true ) ?: array();
									}
									foreach ( $gallery as $gurl ) :
										?>
									<div class="zbp-gallery-item">
										<img src="<?php echo esc_url( $gurl ); ?>" />
										<button type="button" class="zbp-gallery-remove">&times;</button>
									</div>
									<?php endforeach; ?>
							</div>
							<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-gallery-add"><?php esc_html_e( 'Add Images', 'zeko-business' ); ?></button>
						</div>

						<div class="zbp-product-form__file-section">
							<label class="zbp-form-label"><?php esc_html_e( 'Digital File URL', 'zeko-business' ); ?></label>
							<input type="url" name="file_url_input" class="zbp-form-input" placeholder="https://..." value="<?php echo $edit_product ? esc_url( $edit_product['file_url'] ?? '' ) : ''; ?>" />
							<p class="zbp-form-hint"><?php esc_html_e( 'For downloadable products only.', 'zeko-business' ); ?></p>
						</div>
					</div>
				</div>

				<div class="zbp-product-form__pricing" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
					<div class="zbp-portal-form__row">
						<label class="zbp-form-label"><?php esc_html_e( 'Price', 'zeko-business' ); ?> *</label>
						<div style="display:flex;align-items:center;gap:0.25rem">
							<span style="color:var(--color-text-light)"><?php echo esc_html( $currency ); ?></span>
							<input type="number" step="0.01" min="0" name="price" class="zbp-form-input" required placeholder="0.00" value="<?php echo $edit_product ? esc_attr( $edit_product['price'] ?? '0' ) : ''; ?>" />
						</div>
					</div>
					<div class="zbp-portal-form__row">
						<label class="zbp-form-label"><?php esc_html_e( 'Sale Price', 'zeko-business' ); ?></label>
						<div style="display:flex;align-items:center;gap:0.25rem">
							<span style="color:var(--color-text-light)"><?php echo esc_html( $currency ); ?></span>
							<input type="number" step="0.01" min="0" name="sale_price" class="zbp-form-input" placeholder="0.00" value="<?php echo $edit_product ? esc_attr( $edit_product['sale_price'] ?? '' ) : ''; ?>" />
						</div>
					</div>
					<div class="zbp-portal-form__row">
						<label class="zbp-form-label"><?php esc_html_e( 'SKU', 'zeko-business' ); ?></label>
						<input type="text" name="sku" class="zbp-form-input" placeholder="<?php esc_attr_e( 'e.g. PROD-001', 'zeko-business' ); ?>" value="<?php echo $edit_product ? esc_attr( $edit_product['sku'] ?? '' ) : ''; ?>" />
					</div>
				</div>

				<div class="zbp-product-form__details" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
				<div class="zbp-portal-form__row">
					<label class="zbp-form-label"><?php esc_html_e( 'Product Category', 'zeko-business' ); ?></label>
					<select name="category" class="zbp-form-select">
						<option value=""><?php esc_html_e( 'Select category...', 'zeko-business' ); ?></option>
							<?php foreach ( $product_categories as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php $edit_product && selected( $edit_product['category'] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="zbp-cat-add">
						<input type="text" class="zbp-cat-add__input zbp-form-input zbp-form-input--sm" placeholder="<?php esc_attr_e( 'New category name', 'zeko-business' ); ?>" />
						<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-cat-add__btn--product"><?php esc_html_e( 'Add', 'zeko-business' ); ?></button>
					</div>
				</div>
					<div class="zbp-portal-form__row">
						<label class="zbp-form-label"><?php esc_html_e( 'Tags', 'zeko-business' ); ?></label>
						<input type="text" name="tags" class="zbp-form-input" placeholder="<?php esc_attr_e( 'Comma-separated: tag1, tag2, tag3', 'zeko-business' ); ?>" value="<?php echo $edit_product ? esc_attr( $edit_product['tags'] ?? '' ) : ''; ?>" />
					</div>
				</div>

				<div class="zbp-product-form__inventory" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
					<div class="zbp-portal-form__row">
						<label class="zbp-form-label"><?php esc_html_e( 'Stock Quantity', 'zeko-business' ); ?></label>
						<input type="number" min="-1" name="stock" class="zbp-form-input" placeholder="-1 = unlimited" value="<?php echo $edit_product ? esc_attr( $edit_product['stock'] ?? '-1' ) : '-1'; ?>" />
						<p class="zbp-form-hint"><?php esc_html_e( '-1 for unlimited stock.', 'zeko-business' ); ?></p>
					</div>
					<div class="zbp-portal-form__row">
						<label class="zbp-form-label"><?php esc_html_e( 'Product Type', 'zeko-business' ); ?></label>
						<select name="product_type" class="zbp-form-select">
							<option value="physical" <?php $edit_product && selected( $edit_product['product_type'] ?? 'physical', 'physical' ); ?>><?php esc_html_e( 'Physical', 'zeko-business' ); ?></option>
							<option value="digital" <?php $edit_product && selected( $edit_product['product_type'] ?? 'physical', 'digital' ); ?>><?php esc_html_e( 'Digital', 'zeko-business' ); ?></option>
							<option value="service" <?php $edit_product && selected( $edit_product['product_type'] ?? 'physical', 'service' ); ?>><?php esc_html_e( 'Service', 'zeko-business' ); ?></option>
							<option value="subscription" <?php $edit_product && selected( $edit_product['product_type'] ?? 'physical', 'subscription' ); ?>><?php esc_html_e( 'Subscription', 'zeko-business' ); ?></option>
						</select>
					</div>
					<div class="zbp-portal-form__row">
						<label class="zbp-form-label"><?php esc_html_e( 'Status', 'zeko-business' ); ?></label>
						<select name="status" class="zbp-form-select">
							<option value="active" <?php $edit_product && selected( $edit_product['status'] ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'zeko-business' ); ?></option>
							<option value="inactive" <?php $edit_product && selected( $edit_product['status'] ?? 'active', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'zeko-business' ); ?></option>
						</select>
					</div>
				</div>

				<div class="zbp-product-form__featured">
					<label class="zbp-checkbox-label">
						<input type="checkbox" name="is_featured" value="1" <?php $edit_product && checked( ! empty( $edit_product['is_featured'] ) ); ?> />
							<?php esc_html_e( 'Featured product', 'zeko-business' ); ?>
					</label>
				</div>

				<div class="zbp-product-form__footer">
					<button type="submit" class="zbp-btn zbp-btn--primary"><?php echo $edit_product ? esc_html__( 'Update Product', 'zeko-business' ) : esc_html__( 'Add Product', 'zeko-business' ); ?></button>
						<?php if ( $edit_product ) : ?>
						<button type="button" class="zbp-btn zbp-btn--ghost zbp-cancel-edit"><?php esc_html_e( 'Cancel Edit', 'zeko-business' ); ?></button>
					<?php endif; ?>
				</div>
			</form>
		</div>
			<?php
	}

	/**
	 * Render portal plan.
	 *
	 * @param ?object $biz Biz.
	 */
	private static function render_portal_plan( ?object $biz ): void {
		if ( ! $biz ) {
			echo '<div class="zbp-empty-state">' . esc_html__( 'Select a business first.', 'zeko-business' ) . '</div>';
			return;
		}

		$current = $biz->plan ?: 'free';
		$matrix  = \ZBE\Core\Plans::MATRIX;
		$prices  = \ZBE\Core\Plans::get_prices();
		$levels  = \ZBE\Core\Plans::level( $current );

		$settings = get_option( 'zbe_settings', array() );
		$currency = is_array( $settings ) ? ( $settings['general']['currency'] ?? '$' ) : '$';

		echo '<h2>' . esc_html__( 'Plan & Upgrade', 'zeko-business' ) . '</h2>';
		?>
		<div class="zbp-plan-grid">
			<?php foreach ( $matrix as $tier => $features ) : ?>
				<?php $tier_level = \ZBE\Core\Plans::level( $tier ); ?>
				<div class="zbp-plan-card<?php echo $tier === $current ? ' zbp-plan-card--current' : ''; ?>">
					<h3><?php echo esc_html( ucfirst( $tier ) ); ?></h3>
					<?php if ( $tier === $current ) : ?>
						<span class="zbp-badge"><?php esc_html_e( 'Current Plan', 'zeko-business' ); ?></span>
					<?php endif; ?>
					<div class="zbp-plan-card__price">
						<?php echo esc_html( ( $prices[ $tier ] ?? 0 ) > 0 ? $currency . number_format( $prices[ $tier ], 2 ) . '/mo' : 'Free' ); ?>
					</div>
					<ul class="zbp-plan-card__features">
						<li><?php echo esc_html( $features['max_photos'] ?: 'Unlimited' ); ?> photos</li>
						<li><?php echo esc_html( $features['max_services'] ?: 'Unlimited' ); ?> services</li>
						<li><?php echo $features['gallery'] ? '✓' : '✗'; ?> Gallery</li>
						<li><?php echo $features['hours'] ? '✓' : '✗'; ?> Business Hours</li>
						<li><?php echo $features['socials'] ? '✓' : '✗'; ?> Social Links</li>
						<li><?php echo $features['featured'] ? '✓' : '✗'; ?> Featured Boost</li>
						<li><?php echo $features['sponsored'] ? '✓' : '✗'; ?> Sponsored Boost</li>
					</ul>
					<?php if ( $tier !== $current && $tier_level > $levels ) : ?>
						<button type="button" class="zbp-btn zbp-btn--primary zbp-upgrade-btn" data-business-id="<?php echo (int) $biz->id; ?>" data-new-plan="<?php echo esc_attr( $tier ); ?>">
							<?php /* translators: %s: plan name */ echo esc_html( sprintf( __( 'Upgrade to %s', 'zeko-business' ), ucfirst( $tier ) ) ); ?>
						</button>
					<?php elseif ( $tier !== $current && $tier_level < $levels ) : ?>
						<span class="zbp-plan-card__downgrade-note"><?php esc_html_e( 'Downgrade — contact support', 'zeko-business' ); ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * On transaction completed.
	 *
	 * @param int    $tx_id Tx id.
	 * @param int    $wallet_id Wallet id.
	 * @param string $amount Amount.
	 * @param string $type Type.
	 * @param string $status Status.
	 * @param array  $metadata Metadata.
	 */
	public static function on_transaction_completed( int $tx_id, int $wallet_id, string $amount, string $type, string $status, array $metadata ): void {
		if ( 'completed' !== $status ) {
			return;
		}
		if ( empty( $metadata['source'] ) || 'zeko_business' !== $metadata['source'] ) {
			return;
		}

		$business_id = (int) ( $metadata['business_id'] ?? 0 );
		$new_plan    = $metadata['new_plan'] ?? '';

		if ( ! $business_id || ! $new_plan ) {
			return;
		}

		$biz = Services::businesses()->get( $business_id );
		if ( ! $biz ) {
			return;
		}

		Services::business_service()->upgrade_plan( $business_id, $new_plan );
	}
}
