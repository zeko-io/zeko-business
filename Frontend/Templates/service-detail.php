<?php
/**
 * Single Service Template
 *
 * Rendered by /businesses/{slug}/services/{id}/ via the zbp_service rewrite
 * var. Loads on a full page (get_header/get_footer) or shortcode context.
 *
 * @var object $biz  Business object
 * @var object $svc  Service object
 * @package Zeko_ZEKO_BUSINESS
 **/

$is_shortcode = defined( 'ZBP_IN_SHORTCODE' ) && ZBP_IN_SHORTCODE;

if ( ! $is_shortcode ) {
	get_header();
}

$biz = \ZBE\Frontend\PublicController::$template_data['business'] ?? null;
$svc = \ZBE\Frontend\PublicController::$template_data['service'] ?? null;

if ( ! $biz || ! $svc ) {
	echo '<div class="zbp-container"><div class="zbp-empty-state">' . esc_html__( 'Service not found.', 'zeko-business' ) . '</div></div>';
	if ( ! $is_shortcode ) {
		get_footer(); }
	return;
}

$settings   = get_option( 'zbe_settings', array() );
$currency   = is_array( $settings ) ? ( $settings['general']['currency'] ?? '$' ) : '$';
$tags       = array_filter( array_map( 'trim', explode( ',', (string) $svc->tags ) ) );
$open_state = \ZBE\Frontend\PublicController::is_open_now( \ZBE\Core\Services::hours()->get_for_business( (int) $biz->id ), $biz->hours_mode );
$back_url   = home_url( '/businesses/' . rawurlencode( $biz->slug ) . '/' );
?>

<div class="zbp-container">
	<div class="zbp-service-detail">
		<a class="zbp-service-detail__back" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php echo esc_html( $biz->name ); ?></a>

		<div class="zbp-service-detail__hero">
			<?php if ( $svc->image_id ) : ?>
				<div class="zbp-service-detail__image">
					<?php
					echo wp_get_attachment_image(
						(int) $svc->image_id,
						'large',
						false,
						array(
							'class' => 'zbp-service-detail__img',
							'alt'   => esc_attr( $svc->name ),
						)
					);
					?>
				</div>
			<?php endif; ?>
			<div class="zbp-service-detail__info">
				<div class="zbp-service-detail__meta">
					<?php if ( $svc->category ) : ?>
						<span class="zbp-badge zbp-badge--category"><?php echo esc_html( ucfirst( $svc->category ) ); ?></span>
					<?php endif; ?>
					<?php if ( $svc->is_featured ) : ?>
						<span class="zbp-badge zbp-badge--active"><?php esc_html_e( 'Featured', 'zeko-business' ); ?></span>
					<?php endif; ?>
				</div>
				<h1 class="zbp-service-detail__title"><?php echo esc_html( $svc->name ); ?></h1>
				<div class="zbp-service-detail__business">
					<?php esc_html_e( 'Provided by', 'zeko-business' ); ?> <a href="<?php echo esc_url( $back_url ); ?>"><?php echo esc_html( $biz->name ); ?></a>
					<?php if ( null !== $open_state ) : ?>
						<span class="zbp-open-badge zbp-open-badge--<?php echo $open_state ? 'open' : 'closed'; ?>">
							<?php echo $open_state ? esc_html__( 'Open now', 'zeko-business' ) : esc_html__( 'Closed now', 'zeko-business' ); ?>
						</span>
					<?php endif; ?>
				</div>

				<div class="zbp-service-detail__price">
					<?php if ( (float) $svc->price > 0 ) : ?>
						<span class="zbp-service-detail__price-value"><?php echo esc_html( $currency . number_format( (float) $svc->price, 2 ) ); ?></span>
						<?php if ( 'hourly' === $svc->price_type ) : ?>
							<span class="zbp-service-detail__price-suffix"><?php esc_html_e( '/hr', 'zeko-business' ); ?></span>
						<?php elseif ( 'starting' === $svc->price_type ) : ?>
							<span class="zbp-service-detail__price-suffix"><?php esc_html_e( 'starting', 'zeko-business' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="zbp-service-detail__price-value"><?php esc_html_e( 'Free', 'zeko-business' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $svc->price_note ) : ?>
					<div class="zbp-service-detail__price-note"><?php echo esc_html( $svc->price_note ); ?></div>
				<?php endif; ?>

				<div class="zbp-service-detail__actions">
					<button type="button"
							class="zbp-btn zbp-btn--primary zbp-book-service"
							data-service-id="<?php echo (int) $svc->id; ?>"
							data-service-name="<?php echo esc_attr( $svc->name ); ?>"
							data-service-price="<?php echo esc_attr( $svc->price ); ?>"
							data-business-id="<?php echo (int) $biz->id; ?>"
							data-business-name="<?php echo esc_attr( $biz->name ); ?>"
							data-currency="<?php echo esc_attr( $currency ); ?>">
						<?php esc_html_e( 'Book Now', 'zeko-business' ); ?>
					</button>
				</div>
			</div>
		</div>

		<?php if ( $svc->short_description ) : ?>
			<div class="zbp-service-detail__section">
				<h2 class="zbp-service-detail__section-title"><?php esc_html_e( 'Overview', 'zeko-business' ); ?></h2>
				<p class="zbp-service-detail__short"><?php echo esc_html( $svc->short_description ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $svc->description ) : ?>
			<div class="zbp-service-detail__section">
				<h2 class="zbp-service-detail__section-title"><?php esc_html_e( 'Details', 'zeko-business' ); ?></h2>
				<div class="zbp-service-detail__description"><?php echo wp_kses_post( $svc->description ); ?></div>
			</div>
		<?php endif; ?>

		<?php if ( $svc->duration || $svc->category || $biz->phone ) : ?>
			<div class="zbp-service-detail__section">
				<h2 class="zbp-service-detail__section-title"><?php esc_html_e( 'Info', 'zeko-business' ); ?></h2>
				<div class="zbp-service-detail__facts">
					<?php if ( $svc->duration ) : ?>
						<div class="zbp-service-detail__fact">
							<span class="zbp-service-detail__fact-label"><?php esc_html_e( 'Duration', 'zeko-business' ); ?></span>
							<span class="zbp-service-detail__fact-value"><?php echo esc_html( $svc->duration ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( $svc->category ) : ?>
						<div class="zbp-service-detail__fact">
							<span class="zbp-service-detail__fact-label"><?php esc_html_e( 'Category', 'zeko-business' ); ?></span>
							<span class="zbp-service-detail__fact-value"><?php echo esc_html( ucfirst( $svc->category ) ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( $biz->phone ) : ?>
						<div class="zbp-service-detail__fact">
							<span class="zbp-service-detail__fact-label"><?php esc_html_e( 'Contact', 'zeko-business' ); ?></span>
							<span class="zbp-service-detail__fact-value"><a href="tel:<?php echo esc_attr( $biz->phone ); ?>"><?php echo esc_html( $biz->phone ); ?></a></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $tags ) : ?>
			<div class="zbp-service-detail__section">
				<h2 class="zbp-service-detail__section-title"><?php esc_html_e( 'Tags', 'zeko-business' ); ?></h2>
				<div class="zbp-tag-list">
					<?php foreach ( $tags as $term_tag ) : ?>
						<span class="zbp-tag"><?php echo esc_html( $term_tag ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="zbp-service-detail__note">
			<?php esc_html_e( 'Interested in this service? Contact the business to book or ask questions.', 'zeko-business' ); ?>
		</div>
	</div>

	<!-- Payment Modal (required by the Book Now button handler in zbp-public.js) -->
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
