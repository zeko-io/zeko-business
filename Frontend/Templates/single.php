<?php
/**
 * Single Business Template
 *
 * @var object $biz Business data
 * @var array  $services
 * @var array  $reviews
 * @var array  $staff
 * @var array  $hours
 * @package Zeko_ZEKO_BUSINESS
 **/

$is_shortcode = defined( 'ZBP_IN_SHORTCODE' ) && ZBP_IN_SHORTCODE;

if ( ! $is_shortcode ) {
	get_header();
}

$biz           = \ZBE\Frontend\PublicController::$template_data['business'] ?? null;
$services      = \ZBE\Frontend\PublicController::$template_data['services'] ?? array();
$reviews       = \ZBE\Frontend\PublicController::$template_data['reviews'] ?? array();
$staff         = \ZBE\Frontend\PublicController::$template_data['staff'] ?? array();
$hours         = \ZBE\Frontend\PublicController::$template_data['hours'] ?? array();
$settings      = get_option( 'zbe_settings', array() );
$biz_jobs      = \ZBE\Frontend\PublicController::$template_data['biz_jobs'] ?? array();
$biz_questions = \ZBE\Frontend\PublicController::$template_data['biz_questions'] ?? array();
$biz_mentions  = \ZBE\Frontend\PublicController::$template_data['biz_mentions'] ?? array();
$shop_products = \ZBE\Frontend\PublicController::$template_data['shop_products'] ?? array();
$my_claim      = \ZBE\Frontend\PublicController::$template_data['my_claim'] ?? null;

$jobs_active = defined( 'ZEKO_JOBS_VERSION' ) || class_exists( 'Zeko_Jobs_DB' );
$qa_active   = defined( 'ZEKO_QA_VERSION' ) || class_exists( 'Zeko_QA' );

if ( ! $biz ) {
	echo '<div class="zbp-container"><div class="zbp-empty-state">' . esc_html__( 'Business not found.', 'zeko-business' ) . '</div></div>';
	if ( ! $is_shortcode ) {
		get_footer(); }
	return;
}

$avatar_url = '';
if ( $biz->avatar_id ) {
	$avatar_url = wp_get_attachment_image_url( (int) $biz->avatar_id, 'large' );
}
$cover_url = '';
if ( $biz->cover_id ) {
	$cover_url = wp_get_attachment_image_url( (int) $biz->cover_id, 'full' );
}
$currency     = $settings['general']['currency'] ?? '$';
$day_names    = array(
	__( 'Sunday', 'zeko-business' ),
	__( 'Monday', 'zeko-business' ),
	__( 'Tuesday', 'zeko-business' ),
	__( 'Wednesday', 'zeko-business' ),
	__( 'Thursday', 'zeko-business' ),
	__( 'Friday', 'zeko-business' ),
	__( 'Saturday', 'zeko-business' ),
);
$hours_by_day = array();
foreach ( $hours as $h ) {
	$hours_by_day[ (int) $h->day_of_week ] = $h;
}
$social         = is_array( $biz->social_links ) ? $biz->social_links : array();
$open_state     = \ZBE\Frontend\PublicController::is_open_now( $hours, $biz->hours_mode );
$hours_status   = \ZBE\Frontend\PublicController::hours_status( $hours, $biz->hours_mode );
$share_url      = home_url( '/businesses/' . $biz->slug . '/' );
$share_title    = rawurlencode( $biz->name . ' — ' . get_bloginfo( 'name' ) );
$is_owner       = is_user_logged_in() && get_current_user_id() === (int) $biz->owner_id;
$plan           = $biz->plan ?: 'free';
$action_buttons = is_array( $biz->action_buttons ) ? $biz->action_buttons : array();

/**
 * Return the href( ) for an action button, deriving the target from the
 * business's own contact fields when the stored value is empty.
 */
$action_button_url = static function ( array $button, object $biz ): ?string {
	$type  = (string) ( $button['type'] ?? '' );
	$value = trim( (string) ( $button['value'] ?? '' ) );

	switch ( $type ) {
		case 'call':
			$raw = $value ?: (string) $biz->phone;
			return '' !== $raw ? 'tel:' . preg_replace( '/[^0-9+]/', '', $raw ) : null;

		case 'whatsapp':
			$raw    = $value ?: (string) $biz->whatsapp;
			$digits = preg_replace( '/[^0-9]/', '', $raw );
			return '' !== $digits ? 'https://wa.me/' . $digits : null;

		case 'email':
			$raw = $value ?: (string) $biz->email;
			return '' !== $raw ? 'mailto:' . sanitize_email( $raw ) : null;

		case 'website':
			$raw = $value ?: (string) $biz->website;
			return '' !== $raw ? $raw : null;

		case 'contact':
			$raw = (string) $biz->email;
			return '' !== $raw ? 'mailto:' . sanitize_email( $raw ) : null;

		case 'video':
		case 'signup':
		case 'start_order':
		case 'view_shop':
		case 'get_tickets':
			return '' !== $value ? $value : null;
	}

	return null;
};

/**
 * Resolve an author display name for review/team rows that come from the
 * repositories without a users JOIN.
 */
$display_name = static function ( $user_id ) {
	$user_id = (int) $user_id;
	$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

	return $user ? $user->display_name : __( 'Anonymous', 'zeko-business' );
};
?>

<div class="zbp-container">

	<div class="zbp-business-hero">
		<?php if ( $cover_url ) : ?>
			<img src="<?php echo esc_url( $cover_url ); ?>" alt="" class="zbp-business-hero__cover" />
		<?php endif; ?>
		<div class="zbp-business-hero__content">
			<?php if ( $avatar_url ) : ?>
				<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $biz->name ); ?>" class="zbp-business-hero__avatar" />
			<?php else : ?>
				<div class="zbp-business-hero__avatar" style="background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700">
					<?php echo esc_html( strtoupper( mb_substr( (string) $biz->name, 0, 1 ) ) ); ?>
				</div>
			<?php endif; ?>
			<div>
				<h1 class="zbp-business-hero__title"><?php echo esc_html( $biz->name ); ?></h1>
				<div class="zbp-business-hero__meta">
					<span class="zbp-badge zbp-badge--<?php echo esc_attr( $biz->status ); ?>"><?php echo esc_html( ucfirst( $biz->status ) ); ?></span>
					<?php if ( $biz->is_verified ) : ?>
						<span style="background:rgba(255,255,255,0.2);padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem">✓ Verified</span>
					<?php endif; ?>
					<?php if ( $biz->is_featured ) : ?>
						<span style="background:rgba(255,255,255,0.2);padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem">★ Featured</span>
					<?php endif; ?>
					<?php if ( $biz->is_sponsored ) : ?>
						<span style="background:rgba(255,200,0,0.3);padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem"><?php esc_html_e( 'Sponsored', 'zeko-business' ); ?></span>
					<?php endif; ?>
					<?php if ( 'free' !== $plan ) : ?>
						<span style="background:rgba(255,255,255,0.2);padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem"><?php echo esc_html( ucfirst( $plan ) ); ?></span>
					<?php endif; ?>
					<?php if ( null !== $open_state ) : ?>
						<span class="zbp-open-badge zbp-open-badge--<?php echo $open_state ? 'open' : 'closed'; ?>">
							<?php echo $open_state ? esc_html__( 'Open now', 'zeko-business' ) : esc_html__( 'Closed now', 'zeko-business' ); ?>
						</span>
					<?php endif; ?>
					<span>
						<span class="zbp-rating__star zbp-rating__star--filled">★</span>
						<span data-avg-rating><?php echo number_format( (float) $biz->avg_rating, 1 ); ?></span>
						(<span data-review-count><?php echo (int) $biz->review_count; ?></span> reviews)
					</span>
					<span><?php echo number_format( (int) $biz->follower_count ); ?> followers</span>
				</div>
			</div>
			<div style="margin-left:auto;text-align:right">
				<?php
					$is_following = is_user_logged_in() && \ZBE\Frontend\AjaxHandler::is_user_following( get_current_user_id(), (int) $biz->id );
				?>
				<button class="zbp-follow-btn<?php echo $is_following ? ' zbp-follow-btn--following' : ''; ?>" data-business-id="<?php echo (int) $biz->id; ?>">
					<?php echo esc_html( $is_following ? 'Unfollow' : 'Follow' ); ?>
				</button>
				<?php if ( $is_owner ) : ?>
					<a href="<?php echo esc_url( \ZBE\Plugin::page_url( 'portal', 'business_id=' . (int) $biz->id ) ); ?>" class="zbp-btn zbp-btn--secondary zbp-btn--sm" style="margin-top:0.5rem"><?php esc_html_e( 'Manage in Portal', 'zeko-business' ); ?></a>
				<?php endif; ?>
				<div class="zbp-share-row" style="margin-top:0.5rem">
					<a href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $share_url ) ); ?>" target="_blank" rel="noopener nofollow" class="zbp-share-btn" aria-label="<?php esc_attr_e( 'Share on Facebook', 'zeko-business' ); ?>">f</a>
					<a href="<?php echo esc_url( 'https://twitter.com/intent/tweet?url=' . rawurlencode( $share_url ) . '&text=' . $share_title ); ?>" target="_blank" rel="noopener nofollow" class="zbp-share-btn" aria-label="<?php esc_attr_e( 'Share on X', 'zeko-business' ); ?>">X</a>
					<a href="<?php echo esc_url( 'https://wa.me/?text=' . $share_title . '%20' . rawurlencode( $share_url ) ); ?>" target="_blank" rel="noopener nofollow" class="zbp-share-btn" aria-label="<?php esc_attr_e( 'Share on WhatsApp', 'zeko-business' ); ?>">w</a>
					<a href="<?php echo esc_url( 'mailto:?subject=' . $share_title . '&body=' . rawurlencode( $share_url ) ); ?>" class="zbp-share-btn" aria-label="<?php esc_attr_e( 'Share by email', 'zeko-business' ); ?>">@</a>
					<button type="button" class="zbp-share-btn zbp-copy-link" data-url="<?php echo esc_url( $share_url ); ?>" aria-label="<?php esc_attr_e( 'Copy link', 'zeko-business' ); ?>">#</button>
				</div>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $action_buttons ) ) : ?>
		<div class="zbp-action-buttons">
			<?php
			foreach ( $action_buttons as $btn ) :
				$href = $action_button_url( $btn, $biz );
				?>
				<?php
				if ( null === $href ) {
					continue; }
				?>
				<a href="<?php echo esc_url( $href ); ?>" class="zbp-action-button zbp-action-button--<?php echo esc_attr( (string) ( $btn['type'] ?? '' ) ); ?>" rel="noopener nofollow" target="_blank">
					<?php echo esc_html( $btn['label'] ?: ucwords( str_replace( '_', ' ', (string) ( $btn['type'] ?? '' ) ) ) ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="zbp-single-content">
		<div class="zbp-single-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Business sections', 'zeko-business' ); ?>">
			<button class="zbp-single-tab zbp-single-tab--active" role="tab" aria-selected="true" data-tab="zbp-tab-overview"><?php esc_html_e( 'Overview', 'zeko-business' ); ?></button>
			<button class="zbp-single-tab" role="tab" aria-selected="false" data-tab="zbp-tab-services"><?php esc_html_e( 'Services', 'zeko-business' ); ?></button>
			<button class="zbp-single-tab" role="tab" aria-selected="false" data-tab="zbp-tab-products"><?php esc_html_e( 'Products', 'zeko-business' ); ?></button>
			<button class="zbp-single-tab" role="tab" aria-selected="false" data-tab="zbp-tab-reviews"><?php esc_html_e( 'Reviews', 'zeko-business' ); ?> (<?php echo (int) $biz->review_count; ?>)</button>
			<?php if ( ! empty( $staff ) ) : ?>
				<button class="zbp-single-tab" role="tab" aria-selected="false" data-tab="zbp-tab-staff"><?php esc_html_e( 'Staff', 'zeko-business' ); ?></button>
			<?php endif; ?>
			<?php if ( $jobs_active ) : ?>
				<button class="zbp-single-tab" role="tab" aria-selected="false" data-tab="zbp-tab-jobs"><?php esc_html_e( 'Jobs', 'zeko-business' ); ?></button>
			<?php endif; ?>
			<?php if ( $qa_active ) : ?>
				<button class="zbp-single-tab" role="tab" aria-selected="false" data-tab="zbp-tab-qa"><?php esc_html_e( 'Q&A', 'zeko-business' ); ?></button>
				<button class="zbp-single-tab" role="tab" aria-selected="false" data-tab="zbp-tab-requests"><?php esc_html_e( 'Requests', 'zeko-business' ); ?></button>
			<?php endif; ?>
			<button class="zbp-single-tab" role="tab" aria-selected="false" data-tab="zbp-tab-location"><?php esc_html_e( 'Location', 'zeko-business' ); ?></button>
		</div>

		<div id="zbp-tab-overview" role="tabpanel" class="zbp-single-tab-content zbp-single-tab-content--active">
			<?php if ( get_the_content( null, false, $biz->post_id ) ) : ?>
				<div style="margin-bottom:2rem;line-height:1.7">
					<?php echo wp_kses_post( wpautop( get_the_content( null, false, $biz->post_id ) ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $biz->email || $biz->phone || $biz->website || $biz->address ) : ?>
				<div class="zbp-business-info" style="margin-bottom:2rem">
					<?php if ( $biz->phone ) : ?>
						<div class="zbp-business-info__item">
							<div class="zbp-business-info__icon">📞</div>
							<div>
								<div class="zbp-business-info__label"><?php esc_html_e( 'Phone', 'zeko-business' ); ?></div>
								<div class="zbp-business-info__value"><a href="tel:<?php echo esc_attr( $biz->phone ); ?>"><?php echo esc_html( $biz->phone ); ?></a></div>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( $biz->email ) : ?>
						<div class="zbp-business-info__item">
							<div class="zbp-business-info__icon">✉</div>
							<div>
								<div class="zbp-business-info__label"><?php esc_html_e( 'Email', 'zeko-business' ); ?></div>
								<div class="zbp-business-info__value"><a href="mailto:<?php echo esc_attr( $biz->email ); ?>"><?php echo esc_html( $biz->email ); ?></a></div>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( $biz->website ) : ?>
						<div class="zbp-business-info__item">
							<div class="zbp-business-info__icon">🌐</div>
							<div>
								<div class="zbp-business-info__label"><?php esc_html_e( 'Website', 'zeko-business' ); ?></div>
								<div class="zbp-business-info__value"><a href="<?php echo esc_url( $biz->website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( parse_url( $biz->website, PHP_URL_HOST ) ); ?></a></div>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( $biz->address ) : ?>
						<div class="zbp-business-info__item">
							<div class="zbp-business-info__icon">📍</div>
							<div>
								<div class="zbp-business-info__label"><?php esc_html_e( 'Address', 'zeko-business' ); ?></div>
								<div class="zbp-business-info__value"><?php echo esc_html( implode( ', ', array_filter( array( $biz->address, $biz->city, $biz->state, $biz->country ) ) ) ); ?></div>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( 0.0 !== (float) $biz->lat && 0.0 !== (float) $biz->lng ) : ?>
				<div class="zbp-map">
					<h3><?php esc_html_e( 'Location', 'zeko-business' ); ?></h3>
					<iframe
						class="zbp-map__frame"
						title="<?php esc_attr_e( 'Map showing business location', 'zeko-business' ); ?>"
						loading="lazy"
						src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo esc_attr( (float) $biz->lng - 0.01 . '%2C' . ( (float) $biz->lat - 0.008 ) . '%2C' . ( (float) $biz->lng + 0.01 ) . '%2C' . ( (float) $biz->lat + 0.008 ) ); ?>&amp;layer=mapnik&amp;marker=<?php echo esc_attr( (float) $biz->lat . '%2C' . (float) $biz->lng ); ?>"
					></iframe>
					<a
						class="zbp-map__link"
						href="https://www.openstreetmap.org/?mlat=<?php echo esc_attr( (float) $biz->lat ); ?>&amp;mlon=<?php echo esc_attr( (float) $biz->lng ); ?>#map=16/<?php echo esc_attr( (float) $biz->lat ); ?>/<?php echo esc_attr( (float) $biz->lng ); ?>"
						target="_blank" rel="noopener"
					><?php esc_html_e( 'Open in OpenStreetMap', 'zeko-business' ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( 'always' === $biz->hours_mode ) : ?>
				<h3>
					<?php esc_html_e( 'Business Hours', 'zeko-business' ); ?>
					<span class="zbp-open-badge zbp-open-badge--open"><?php esc_html_e( 'Open 24/7', 'zeko-business' ); ?></span>
				</h3>
				<p><?php esc_html_e( 'This business is open around the clock.', 'zeko-business' ); ?></p>
			<?php elseif ( 'closed' === $biz->hours_mode ) : ?>
				<h3>
					<?php esc_html_e( 'Business Hours', 'zeko-business' ); ?>
					<span class="zbp-open-badge zbp-open-badge--closed"><?php esc_html_e( 'Permanently closed', 'zeko-business' ); ?></span>
				</h3>
			<?php elseif ( 'none' === $biz->hours_mode ) : ?>
				<h3><?php esc_html_e( 'Business Hours', 'zeko-business' ); ?></h3>
				<p><?php esc_html_e( 'Hours have not been published yet.', 'zeko-business' ); ?></p>
			<?php elseif ( \ZBE\Core\Plans::allows( $plan, 'hours' ) && ! empty( $hours_by_day ) ) : ?>
				<h3>
					<?php esc_html_e( 'Business Hours', 'zeko-business' ); ?>
					<?php if ( null !== $open_state ) : ?>
						<span class="zbp-open-badge zbp-open-badge--<?php echo $open_state ? 'open' : 'closed'; ?>">
							<?php echo $open_state ? esc_html__( 'Open now', 'zeko-business' ) : esc_html__( 'Closed now', 'zeko-business' ); ?>
						</span>
					<?php endif; ?>
				</h3>
				<table class="zbp-hours-table" style="margin-bottom:2rem">
					<?php foreach ( $day_names as $i => $day ) : ?>
						<?php $h = $hours_by_day[ $i ] ?? null; ?>
						<tr>
							<td class="zbp-hours-table__day"><?php echo esc_html( $day ); ?></td>
							<td class="zbp-hours-table__time">
								<?php if ( $h && $h->is_closed ) : ?>
									<span class="zbp-hours-table__closed">Closed</span>
								<?php elseif ( $h && $h->open_time && $h->close_time ) : ?>
									<?php echo esc_html( date_i18n( 'g:i A', strtotime( $h->open_time ) ) . ' — ' . date_i18n( 'g:i A', strtotime( $h->close_time ) ) ); ?>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<?php if ( \ZBE\Core\Plans::allows( $plan, 'socials' ) && ! empty( $social ) ) : ?>
				<h3><?php esc_html_e( 'Social Links', 'zeko-business' ); ?></h3>
				<div style="display:flex;gap:1rem;margin-bottom:2rem">
					<?php foreach ( $social as $platform => $url ) : ?>
						<?php if ( $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="zbp-btn zbp-btn--secondary zbp-btn--sm"><?php echo esc_html( ucfirst( $platform ) ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $biz->is_claimed ) : ?>
				<?php if ( is_user_logged_in() && $my_claim && 'pending' === $my_claim->status ) : ?>
					<div class="zbp-claim-panel">
						<h3><?php esc_html_e( 'Claim under review', 'zeko-business' ); ?></h3>
						<p><?php esc_html_e( 'You already submitted a claim for this listing. It is awaiting review by our team.', 'zeko-business' ); ?></p>
						<p class="zbp-muted"><?php esc_html_e( 'Submitted', 'zeko-business' ); ?>: <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $my_claim->date_created ) ) ); ?></p>
					</div>
				<?php else : ?>
				<div class="zbp-claim-panel">
					<h3><?php esc_html_e( 'Own this business?', 'zeko-business' ); ?></h3>
					<?php if ( is_user_logged_in() && $my_claim && 'rejected' === $my_claim->status ) : ?>
						<p class="zbp-warning"><?php esc_html_e( 'Your previous claim was not approved. You can submit a new one with more evidence.', 'zeko-business' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Claim this listing to manage its details, respond to reviews and more.', 'zeko-business' ); ?></p>
					<?php endif; ?>
					<?php if ( is_user_logged_in() ) : ?>
						<form class="zbp-ajax-form" data-action="zbp_submit_claim" aria-live="polite">
							<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
							<input type="hidden" name="zbp_form_ts" value="<?php echo (int) time(); ?>" />
							<div style="position:absolute;left:-9999px" aria-hidden="true">
								<input type="text" name="zbp_website_confirm" tabindex="-1" autocomplete="off" />
							</div>
							<div class="zbp-portal-form__row">
								<label class="zbp-form-label" for="zbp-claim-method"><?php esc_html_e( 'Verification method', 'zeko-business' ); ?></label>
								<select id="zbp-claim-method" name="method" class="zbp-form-select" required>
									<option value="email"><?php esc_html_e( 'Business email', 'zeko-business' ); ?></option>
									<option value="phone"><?php esc_html_e( 'Business phone', 'zeko-business' ); ?></option>
									<option value="document"><?php esc_html_e( 'Registration document', 'zeko-business' ); ?></option>
								</select>
							</div>
							<div class="zbp-portal-form__row">
								<label class="zbp-form-label" for="zbp-claim-evidence"><?php esc_html_e( 'Evidence details', 'zeko-business' ); ?></label>
								<textarea id="zbp-claim-evidence" name="evidence" class="zbp-form-textarea" rows="3" placeholder="<?php esc_attr_e( 'Tell us how you are connected to this business...', 'zeko-business' ); ?>"></textarea>
							</div>
							<button type="submit" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Submit Claim', 'zeko-business' ); ?></button>
						</form>
					<?php else : ?>
						<p><a href="<?php echo esc_url( wp_login_url( get_permalink( (int) $biz->post_id ) ) ); ?>" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Log in to claim', 'zeko-business' ); ?></a></p>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			<?php elseif ( $is_owner && ! $biz->is_verified ) : ?>
				<div class="zbp-claim-panel">
					<h3><?php esc_html_e( 'Get verified', 'zeko-business' ); ?></h3>
					<p><?php esc_html_e( 'Verified businesses earn a trust badge and rank higher in search results.', 'zeko-business' ); ?></p>
					<form class="zbp-ajax-form" data-action="zbp_submit_verification" aria-live="polite">
						<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
						<input type="hidden" name="zbp_form_ts" value="<?php echo (int) time(); ?>" />
						<div style="position:absolute;left:-9999px" aria-hidden="true">
							<input type="text" name="zbp_website_confirm" tabindex="-1" autocomplete="off" />
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label" for="zbp-verify-method"><?php esc_html_e( 'Verification method', 'zeko-business' ); ?></label>
							<select id="zbp-verify-method" name="method" class="zbp-form-select" required>
								<option value="documents"><?php esc_html_e( 'Official documents', 'zeko-business' ); ?></option>
								<option value="phone"><?php esc_html_e( 'Phone call', 'zeko-business' ); ?></option>
								<option value="site_visit"><?php esc_html_e( 'Site visit', 'zeko-business' ); ?></option>
							</select>
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label" for="zbp-verify-note"><?php esc_html_e( 'Notes', 'zeko-business' ); ?></label>
							<textarea id="zbp-verify-note" name="note" class="zbp-form-textarea" rows="3"></textarea>
						</div>
						<button type="submit" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Request Verification', 'zeko-business' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
		</div>

		<div id="zbp-tab-services" role="tabpanel" class="zbp-single-tab-content">
			<?php if ( empty( $services ) ) : ?>
				<div class="zbp-empty-state"><?php esc_html_e( 'No services listed.', 'zeko-business' ); ?></div>
			<?php else : ?>
				<?php
				foreach ( $services as $svc ) :
					$svc_tags = $svc->tags ? array_filter( array_map( 'trim', explode( ',', (string) $svc->tags ) ) ) : array();
					$svc_url  = home_url( '/businesses/' . rawurlencode( $biz->slug ) . '/services/' . (int) $svc->id . '/' );
					?>
					<div class="zbp-service-card" style="margin-bottom:1.5rem">
						<?php if ( $svc->image_id ) : ?>
							<div class="zbp-service-card__image">
								<a href="<?php echo esc_url( $svc_url ); ?>">
									<?php
									echo wp_get_attachment_image(
										(int) $svc->image_id,
										'medium',
										false,
										array(
											'class' => 'zbp-service-card__img',
											'alt'   => esc_attr( $svc->name ),
										)
									);
									?>
								</a>
							</div>
						<?php endif; ?>
						<div class="zbp-service-card__body">
							<div class="zbp-service-card__header">
								<div>
									<a href="<?php echo esc_url( $svc_url ); ?>" class="zbp-service-card__name-link"><div class="zbp-service-card__name"><?php echo esc_html( $svc->name ); ?></div></a>
									<?php if ( $svc->category ) : ?>
										<span class="zbp-badge zbp-badge--category"><?php echo esc_html( ucfirst( $svc->category ) ); ?></span>
									<?php endif; ?>
								</div>
								<div class="zbp-service-card__price">
									<?php if ( $svc->price > 0 ) : ?>
										<?php echo esc_html( $currency . number_format( (float) $svc->price, 2 ) ); ?>
										<?php if ( 'hourly' === $svc->price_type ) : ?>
											<small>/hr</small>
										<?php elseif ( 'starting' === $svc->price_type ) : ?>
											<small>+ <?php esc_html_e( 'starting', 'zeko-business' ); ?></small>
										<?php endif; ?>
									<?php else : ?>
										<?php esc_html_e( 'Free', 'zeko-business' ); ?>
									<?php endif; ?>
								</div>
							</div>
							<?php if ( $svc->short_description ) : ?>
								<div class="zbp-service-card__description"><?php echo esc_html( $svc->short_description ); ?></div>
							<?php elseif ( $svc->description ) : ?>
								<div class="zbp-service-card__description"><?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $svc->description ), 20 ) ); ?></div>
							<?php endif; ?>
							<?php if ( $svc->duration ) : ?>
								<div class="zbp-service-card__meta">
									<span class="zbp-service-card__duration">⏱ <?php echo esc_html( $svc->duration ); ?></span>
								</div>
							<?php endif; ?>
							<?php if ( $svc_tags ) : ?>
								<div class="zbp-service-card__tags">
									<?php foreach ( $svc_tags as $term_tag ) : ?>
										<span class="zbp-tag"><?php echo esc_html( $term_tag ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<div class="zbp-service-card__actions">
								<a href="<?php echo esc_url( $svc_url ); ?>" class="zbp-btn zbp-btn--secondary zbp-btn--sm"><?php esc_html_e( 'View Details', 'zeko-business' ); ?></a>
								<?php if ( $svc->price > 0 ) : ?>
									<button type="button"
											class="zbp-btn zbp-btn--primary zbp-btn--sm zbp-book-service"
											data-service-id="<?php echo (int) $svc->id; ?>"
											data-service-name="<?php echo esc_attr( $svc->name ); ?>"
											data-service-price="<?php echo esc_attr( $svc->price ); ?>"
											data-business-id="<?php echo (int) $biz->id; ?>"
											data-business-name="<?php echo esc_attr( $biz->name ); ?>"
											data-currency="<?php echo esc_attr( $currency ); ?>">
										<?php esc_html_e( 'Book Now', 'zeko-business' ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div id="zbp-tab-products" role="tabpanel" class="zbp-single-tab-content">
			<?php if ( empty( $shop_products ) ) : ?>
				<div class="zbp-empty-state"><?php esc_html_e( 'No products listed.', 'zeko-business' ); ?></div>
			<?php else : ?>
				<div class="zbp-products-grid">
					<?php foreach ( $shop_products as $prod ) : ?>
						<?php
							$prod_id     = (int) ( $prod['product_id'] ?? 0 );
							$prod_title  = $prod['title'] ?? '';
							$prod_desc   = $prod['description'] ?? '';
							$prod_price  = (float) ( $prod['price'] ?? 0 );
							$prod_image  = $prod['image_url'] ?? '';
							$prod_stock  = (int) ( $prod['stock'] ?? -1 );
							$prod_status = $prod['status'] ?? 'active';
						?>
						<div class="zbp-product-card">
							<?php if ( $prod_image ) : ?>
								<div class="zbp-product-card__image">
									<img src="<?php echo esc_url( $prod_image ); ?>" alt="<?php echo esc_attr( $prod_title ); ?>" class="zbp-product-card__img" />
								</div>
							<?php endif; ?>
							<div class="zbp-product-card__body">
								<div class="zbp-product-card__name"><?php echo esc_html( $prod_title ); ?></div>
								<?php if ( $prod_desc ) : ?>
									<div class="zbp-product-card__description"><?php echo esc_html( wp_trim_words( $prod_desc, 20 ) ); ?></div>
								<?php endif; ?>
								<div class="zbp-product-card__price">
									<?php echo esc_html( $currency . number_format( $prod_price, 2 ) ); ?>
								</div>
								<div class="zbp-product-card__stock">
									<?php if ( 0 === $prod_stock ) : ?>
										<span class="zbp-product-card__out-of-stock"><?php esc_html_e( 'Out of Stock', 'zeko-business' ); ?></span>
									<?php elseif ( -1 === $prod_stock ) : ?>
										<span class="zbp-product-card__in-stock"><?php esc_html_e( 'In Stock', 'zeko-business' ); ?></span>
									<?php else : ?>
										<span class="zbp-product-card__in-stock"><?php /* translators: %d: number of units in stock */ echo esc_html( sprintf( __( 'In Stock (%d)', 'zeko-business' ), $prod_stock ) ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( 'active' === $prod_status && 0 !== $prod_stock ) : ?>
									<div class="zbp-product-card__actions">
										<button type="button"
												class="zbp-btn zbp-btn--primary zbp-btn--sm zbp-buy-product"
												data-product-id="<?php echo esc_attr( $prod_id ); ?>"
												data-product-name="<?php echo esc_attr( $prod_title ); ?>"
												data-product-price="<?php echo esc_attr( $prod_price ); ?>"
												data-business-id="<?php echo (int) $biz->id; ?>"
												data-business-name="<?php echo esc_attr( $biz->name ); ?>"
												data-currency="<?php echo esc_attr( $currency ); ?>">
											<?php esc_html_e( 'Buy Now', 'zeko-business' ); ?>
										</button>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div id="zbp-tab-reviews" role="tabpanel" class="zbp-single-tab-content">
			<?php if ( $settings['reviews']['enable_reviews'] ?? 1 ) : ?>
				<?php $rating_counts = \ZBE\Core\Services::reviews()->rating_counts( (int) $biz->id ); ?>
				<div class="zbp-rating-breakdown" style="margin-bottom:1.5rem">
					<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
						<?php
						$count = (int) ( $rating_counts[ $i ] ?? 0 );
						$pct   = (int) $biz->review_count > 0 ? round( $count * 100 / (int) $biz->review_count ) : 0;
						?>
						<div class="zbp-rating-breakdown__row">
							<span class="zbp-rating-breakdown__stars"><?php echo esc_html( str_repeat( '★', $i ) ); ?></span>
							<span class="zbp-rating-breakdown__bar" role="img" aria-label="<?php /* translators: %d: percentage of reviews */ echo esc_attr( sprintf( __( '%d%% of reviews', 'zeko-business' ), $pct ) ); ?>">
								<span class="zbp-rating-breakdown__fill" style="width:<?php echo (int) $pct; ?>%"></span>
							</span>
							<span class="zbp-rating-breakdown__count"><?php echo esc_html( number_format( $count ) ); ?></span>
						</div>
					<?php endfor; ?>
				</div>

				<?php if ( is_user_logged_in() ) : ?>
					<div class="zbp-review-form">
						<h3><?php esc_html_e( 'Leave a Review', 'zeko-business' ); ?></h3>
						<form>
							<input type="hidden" name="business_id" value="<?php echo (int) $biz->id; ?>" />
							<input type="hidden" name="rating" value="0" />
							<input type="hidden" name="zbp_form_ts" value="<?php echo (int) time(); ?>" />
							<div style="position:absolute;left:-9999px" aria-hidden="true">
								<input type="text" name="zbp_website_confirm" tabindex="-1" autocomplete="off" />
							</div>
							<div class="zbp-review-form__stars">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<span class="zbp-review-form__star" data-value="<?php echo esc_attr( $i ); ?>">★</span>
								<?php endfor; ?>
							</div>
							<?php
							$active_criteria = array_filter(
								\ZBE\Domain\ReviewService::criteria_options(),
								function ( $on ) {
									return (bool) $on;
								}
							);
							?>
							<?php if ( ! empty( $active_criteria ) ) : ?>
								<div class="zbp-review-form__criteria">
									<?php foreach ( $active_criteria as $criterion => $enabled ) : ?>
										<div class="zbp-review-form__criterion">
											<span class="zbp-review-form__criterion-label"><?php echo esc_html( $criterion ); ?></span>
											<div class="zbp-review-form__stars zbp-review-form__stars--criteria" data-criterion="<?php echo esc_attr( $criterion ); ?>">
												<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
													<span class="zbp-review-form__star" data-value="<?php echo esc_attr( $i ); ?>">★</span>
												<?php endfor; ?>
											</div>
											<input type="hidden" name="criteria[<?php echo esc_attr( $criterion ); ?>]" value="0" />
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<div class="zbp-form-group">
								<input type="text" name="title" class="zbp-form-input" placeholder="<?php esc_attr_e( 'Review title (optional)', 'zeko-business' ); ?>" />
							</div>
							<div class="zbp-form-group">
								<textarea name="content" class="zbp-form-textarea" placeholder="<?php esc_attr_e( 'Share your experience...', 'zeko-business' ); ?>" required rows="4"></textarea>
							</div>
							<div class="zbp-form-group">
								<label class="zbp-review-form__label"><?php esc_html_e( 'Photos (optional)', 'zeko-business' ); ?></label>
								<button type="button" class="zbp-btn zbp-btn--ghost zbp-review-photo-btn"><?php esc_html_e( 'Add Photos', 'zeko-business' ); ?></button>
								<div class="zbp-review-photos" style="display:none;gap:0.5rem;margin-top:0.5rem"></div>
								<input type="hidden" name="photo_ids" value="" />
							</div>
							<button type="submit" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Submit Review', 'zeko-business' ); ?></button>
						</form>
					</div>
				<?php endif; ?>

				<div class="zbp-reviews-list">
				<?php foreach ( $reviews as $rev ) : ?>
					<?php
					$rev_photos       = ! empty( $rev->photos ) && is_array( $rev->photos ) ? $rev->photos : array();
							$rev_vote = get_current_user_id() > 0 ? \ZBE\Core\Services::reviews()->get_user_vote( (int) $rev->id, get_current_user_id() ) : 0;
					?>
					<div class="zbp-review-card" data-review-id="<?php echo (int) $rev->id; ?>">
						<div class="zbp-review-card__header">
							<span class="zbp-review-card__author"><?php echo esc_html( $display_name( $rev->user_id ) ); ?></span>
							<span class="zbp-review-card__date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $rev->date_created ) ) ); ?></span>
						</div>
						<div class="zbp-rating">
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
								<span class="zbp-rating__star<?php echo $i <= (int) $rev->rating ? ' zbp-rating__star--filled' : ''; ?>">★</span>
							<?php endfor; ?>
						</div>
						<?php if ( ! empty( $rev->criteria ) && is_array( $rev->criteria ) ) : ?>
							<div class="zbp-review-card__criteria">
								<?php foreach ( $rev->criteria as $criterion => $stars ) : ?>
									<div class="zbp-review-card__criterion">
										<span class="zbp-review-card__criterion-label"><?php echo esc_html( $criterion ); ?></span>
										<span class="zbp-review-card__criterion-stars"><?php echo esc_html( str_repeat( '★', (int) $stars ) . str_repeat( '☆', 5 - (int) $stars ) ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( $rev->title ) : ?>
							<strong><?php echo esc_html( $rev->title ); ?></strong>
						<?php endif; ?>
						<div class="zbp-review-card__content"><?php echo esc_html( $rev->content ); ?></div>
						<?php if ( ! empty( $rev_photos ) ) : ?>
							<div class="zbp-review-card__photos">
								<?php
								foreach ( $rev_photos as $pid ) :
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
							<button type="button" class="zbp-helpful-btn<?php echo 1 === $rev_vote ? ' zbp-helpful-btn--active' : ''; ?>" data-review-id="<?php echo (int) $rev->id; ?>" data-value="1" aria-pressed="<?php echo 1 === $rev_vote ? 'true' : 'false'; ?>">
								<?php esc_html_e( 'Helpful', 'zeko-business' ); ?> (<span class="zbp-helpful-count"><?php echo (int) $rev->helpful_count; ?></span>)
							</button>
							<button type="button" class="zbp-helpful-btn zbp-helpful-btn--down<?php echo -1 === $rev_vote ? ' zbp-helpful-btn--active' : ''; ?>" data-review-id="<?php echo (int) $rev->id; ?>" data-value="-1" aria-pressed="<?php echo -1 === $rev_vote ? 'true' : 'false'; ?>">
								<?php esc_html_e( 'Not helpful', 'zeko-business' ); ?>
							</button>
						</div>
						<?php if ( $rev->admin_reply ) : ?>
							<div class="zbp-review-card__reply">
								<div class="zbp-review-card__reply-label"><?php esc_html_e( 'Owner Reply', 'zeko-business' ); ?></div>
								<?php echo esc_html( $rev->admin_reply ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="zbp-empty-state"><?php esc_html_e( 'Reviews are not enabled.', 'zeko-business' ); ?></div>
			<?php endif; ?>
		</div>

		<div id="zbp-tab-staff" role="tabpanel" class="zbp-single-tab-content">
			<?php if ( empty( $staff ) ) : ?>
				<div class="zbp-empty-state"><?php esc_html_e( 'No staff members listed.', 'zeko-business' ); ?></div>
			<?php else : ?>
				<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1.5rem">
					<?php foreach ( $staff as $member ) : ?>
						<div class="zbp-staff-card">
							<div class="zbp-staff-card__avatar" style="background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-weight:700;color:#6b7280;margin:0 auto 0.5rem;width:64px;height:64px;border-radius:50%">
								<?php echo esc_html( strtoupper( mb_substr( $display_name( $member->user_id ), 0, 1 ) ) ); ?>
							</div>
							<div class="zbp-staff-card__name"><?php echo esc_html( $display_name( $member->user_id ) ); ?></div>
							<div class="zbp-staff-card__role"><?php echo esc_html( ucfirst( $member->role ) ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div id="zbp-tab-jobs" role="tabpanel" class="zbp-single-tab-content">
			<?php if ( empty( $biz_jobs ) ) : ?>
				<div class="zbp-empty-state"><?php esc_html_e( 'No job openings posted yet.', 'zeko-business' ); ?></div>
			<?php else : ?>
				<?php foreach ( $biz_jobs as $job ) : ?>
					<div class="zbp-review-card">
						<div class="zbp-review-card__header">
							<strong><?php echo esc_html( $job->title ); ?></strong>
							<?php if ( $job->is_featured ) : ?>
								<span class="zbp-badge zbp-badge--active"><?php esc_html_e( 'Featured', 'zeko-business' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="zbp-review-card__content">
							<?php if ( $job->location ) : ?>
								<span style="margin-right:1rem">📍 <?php echo esc_html( $job->location ); ?></span>
							<?php endif; ?>
							<?php if ( $job->type ) : ?>
								<span style="margin-right:1rem"><?php echo esc_html( ucfirst( $job->type ) ); ?></span>
							<?php endif; ?>
							<?php if ( (float) $job->salary_max > 0 ) : ?>
								<span>💰 <?php echo esc_html( $currency . number_format( (float) $job->salary_min, 0 ) . ' — ' . $currency . number_format( (float) $job->salary_max, 0 ) ); ?></span>
							<?php endif; ?>
						</div>
						<div style="margin-top:0.5rem;font-size:0.8125rem;color:var(--color-text-light)">
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $job->created_at ) ) ); ?>
							— <a href="<?php echo esc_url( home_url( '/jobs/' . $job->slug . '/' ) ); ?>"><?php esc_html_e( 'View Job', 'zeko-business' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
				<div style="margin-top:1rem;text-align:center">
					<a href="<?php echo esc_url( home_url( '/jobs/?company=' . rawurlencode( $biz->name ) ) ); ?>" class="zbp-btn zbp-btn--secondary zbp-btn--sm"><?php esc_html_e( 'View All Jobs', 'zeko-business' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<div id="zbp-tab-qa" role="tabpanel" class="zbp-single-tab-content">
			<div style="margin-bottom:1rem;text-align:right">
				<a href="<?php echo esc_url( \ZBE\Core\Services::mention_service()->ask_url( (int) $biz->id ) ); ?>" class="zbp-btn zbp-btn--primary zbp-btn--sm">
					<?php esc_html_e( 'Ask this business', 'zeko-business' ); ?>
				</a>
			</div>
			<?php if ( empty( $biz_questions ) ) : ?>
				<div class="zbp-empty-state"><?php esc_html_e( 'No questions about this business yet.', 'zeko-business' ); ?></div>
			<?php else : ?>
				<?php foreach ( $biz_questions as $q ) : ?>
					<?php
						$q_author = $q->user_id > 0 ? get_userdata( (int) $q->user_id ) : false;
					?>
					<div class="zbp-review-card">
						<div class="zbp-review-card__header">
							<strong><?php echo esc_html( $q->title ); ?></strong>
							<span class="zbp-review-card__date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $q->created_at ) ) ); ?></span>
						</div>
						<div style="margin-top:0.5rem;font-size:0.875rem;color:var(--color-text-light)">
							<?php echo esc_html( $q_author ? $q_author->display_name : __( 'Anonymous', 'zeko-business' ) ); ?>
							· 👍 <?php echo (int) $q->upvotes; ?> · 👎 <?php echo (int) $q->downvotes; ?> · 👁 <?php echo (int) $q->views; ?>
							— <a href="<?php echo esc_url( home_url( '/questions/' . ( $q->slug ?: $q->id ) . '/' ) ); ?>"><?php esc_html_e( 'View Question', 'zeko-business' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div id="zbp-tab-requests" role="tabpanel" class="zbp-single-tab-content">
			<?php if ( empty( $biz_mentions ) ) : ?>
				<div class="zbp-empty-state"><?php esc_html_e( 'No mentions or requests found yet.', 'zeko-business' ); ?></div>
			<?php else : ?>
				<?php foreach ( $biz_mentions as $q ) : ?>
					<?php
						$q_author = $q->user_id > 0 ? get_userdata( (int) $q->user_id ) : false;
					?>
					<div class="zbp-review-card">
						<div class="zbp-review-card__header">
							<strong><?php echo esc_html( $q->title ); ?></strong>
							<span class="zbp-review-card__date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $q->created_at ) ) ); ?></span>
						</div>
						<div style="margin-top:0.5rem;font-size:0.875rem;color:var(--color-text-light)">
							<?php echo esc_html( $q_author ? $q_author->display_name : __( 'Anonymous', 'zeko-business' ) ); ?>
							— <a href="<?php echo esc_url( home_url( '/questions/' . ( $q->slug ?: $q->id ) . '/' ) ); ?>"><?php esc_html_e( 'View Request', 'zeko-business' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div id="zbp-tab-location" role="tabpanel" class="zbp-single-tab-content">
			<?php if ( $biz->lat && $biz->lng ) : ?>
				<div class="zbp-map-container">
					<iframe
						src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo esc_attr( $biz->lng - 0.01 ); ?>,<?php echo esc_attr( $biz->lat - 0.01 ); ?>,<?php echo esc_attr( $biz->lng + 0.01 ); ?>,<?php echo esc_attr( $biz->lat + 0.01 ); ?>&layer=mapnik&marker=<?php echo esc_attr( $biz->lat ); ?>,<?php echo esc_attr( $biz->lng ); ?>"
						loading="lazy"
					></iframe>
				</div>
			<?php elseif ( $biz->address || $biz->city ) : ?>
				<p><?php echo esc_html( implode( ', ', array_filter( array( $biz->address, $biz->city, $biz->state, $biz->country, $biz->zip ) ) ) ); ?></p>
			<?php else : ?>
				<div class="zbp-empty-state"><?php esc_html_e( 'No location data available.', 'zeko-business' ); ?></div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Payment Modal -->
	<div id="zbp-payment-modal" class="zbp-modal" hidden>
		<div class="zbp-modal__overlay" tabindex="-1"></div>
		<div class="zbp-modal__content" role="dialog" aria-modal="true">
			<button type="button" class="zbp-modal__close" aria-label="<?php esc_attr_e( 'Close', 'zeko-business' ); ?>">×</button>
			<h3 class="zbp-modal__title"><?php esc_html_e( 'Confirm Booking', 'zeko-business' ); ?></h3>
			<div class="zbp-modal__body">
				<div class="zbp-payment-details">
					<div class="zbp-payment-details__row">
						<span class="zbp-payment-details__label"><?php esc_html_e( 'Item', 'zeko-business' ); ?></span>
						<span class="zbp-payment-details__value" id="zbp-payment-item"></span>
					</div>
					<div class="zbp-payment-details__row">
						<span class="zbp-payment-details__label"><?php esc_html_e( 'Business', 'zeko-business' ); ?></span>
						<span class="zbp-payment-details__value" id="zbp-payment-business"></span>
					</div>
					<div class="zbp-payment-details__row zbp-payment-details__row--total">
						<span class="zbp-payment-details__label"><?php esc_html_e( 'Total', 'zeko-business' ); ?></span>
						<span class="zbp-payment-details__value" id="zbp-payment-total"></span>
					</div>
				</div>
			</div>
			<div class="zbp-modal__footer">
				<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm" id="zbp-payment-cancel"><?php esc_html_e( 'Cancel', 'zeko-business' ); ?></button>
				<button type="button" class="zbp-btn zbp-btn--primary zbp-btn--sm" id="zbp-payment-confirm"><?php esc_html_e( 'Confirm & Pay', 'zeko-business' ); ?></button>
			</div>
			<div id="zbp-payment-processing" class="zbp-modal__processing" hidden>
				<div class="zbp-spinner"></div>
				<p><?php esc_html_e( 'Processing payment...', 'zeko-business' ); ?></p>
			</div>
		</div>
	</div>
</div>

<?php if ( ! $is_shortcode ) {
	get_footer(); } ?>
