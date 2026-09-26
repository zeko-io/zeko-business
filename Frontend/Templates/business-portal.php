<?php
/**
 * Business Portal Template
 *
 * When loaded via the rewrite rule (/business-portal/), this is the full
 * page template and needs get_header()/get_footer(). When loaded via the
 * [zbp_business_portal] shortcode, the caller already has header/footer,
 * so we skip them to avoid nested HTML documents.
 *
 * @package Zeko_ZEKO_BUSINESS
 **/

$is_shortcode = defined( 'ZBP_IN_SHORTCODE' ) && ZBP_IN_SHORTCODE;

if ( ! $is_shortcode ) {
	get_header();
}

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( wp_login_url( home_url() ) );
	exit;
}

$user_id    = get_current_user_id();
$businesses = \ZBE\Core\Services::businesses()->get_by_owner( $user_id, 100 );

$requested_id = absint( $_GET['business_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET tab selector for the current user's own businesses.
$selected_biz = null;
if ( $requested_id ) {
	foreach ( $businesses as $b ) {
		if ( (int) $b->id === $requested_id ) {
			$selected_biz = $b;
			break;
		}
	}
}
if ( ! $selected_biz ) {
	$selected_biz = $businesses[0] ?? null;
}

$unread_count = \ZBE\Core\Services::notification_service()->get_unread_count( $user_id );

$portal_tabs = array(
	'overview'   => array(
		'label' => __( 'Overview', 'zeko-business' ),
		'icon'  => '📊',
	),
	'businesses' => array(
		'label' => __( 'My Businesses', 'zeko-business' ),
		'icon'  => '🏢',
	),
	'services'   => array(
		'label' => __( 'Services', 'zeko-business' ),
		'icon'  => '🛎️',
	),
	'products'   => array(
		'label' => __( 'Products', 'zeko-business' ),
		'icon'  => '📦',
	),
	'staff'      => array(
		'label' => __( 'Staff', 'zeko-business' ),
		'icon'  => '👥',
	),
	'reviews'    => array(
		'label' => __( 'Reviews', 'zeko-business' ),
		'icon'  => '⭐',
	),
	'hours'      => array(
		'label' => __( 'Hours', 'zeko-business' ),
		'icon'  => '🕒',
	),
	'media'      => array(
		'label' => __( 'Media', 'zeko-business' ),
		'icon'  => '🖼️',
	),
	'analytics'  => array(
		'label' => __( 'Analytics', 'zeko-business' ),
		'icon'  => '📈',
	),
	'claims'     => array(
		'label' => __( 'My Claims', 'zeko-business' ),
		'icon'  => '🗹',
	),
);

if ( defined( 'ZEKO_JOBS_VERSION' ) || class_exists( 'Zeko_Jobs_DB' ) ) {
	$portal_tabs['jobs'] = array(
		'label' => __( 'Jobs', 'zeko-business' ),
		'icon'  => '💼',
	);
}

if ( defined( 'ZEKO_QA_VERSION' ) || class_exists( 'Zeko_QA' ) ) {
	$portal_tabs['qa']       = array(
		'label' => __( 'Q&A', 'zeko-business' ),
		'icon'  => '❓',
	);
	$portal_tabs['requests'] = array(
		'label' => __( 'Requests', 'zeko-business' ),
		'icon'  => '📩',
	);
}

$portal_tabs = array_merge(
	$portal_tabs,
	array(
		'notifications' => array(
			'label' => __( 'Notifications', 'zeko-business' ),
			'icon'  => '🔔',
			'badge' => $unread_count,
		),
		'settings'      => array(
			'label' => __( 'Settings', 'zeko-business' ),
			'icon'  => '⚙️',
		),
		'plan'          => array(
			'label' => __( 'Plan', 'zeko-business' ),
			'icon'  => '💳',
		),
	)
);
?>

<div class="zbp-container" style="margin-top:2rem;margin-bottom:2rem">
	<div class="zbp-portal-layout">
		<nav class="zbp-portal-nav" aria-label="<?php esc_attr_e( 'Portal sections', 'zeko-business' ); ?>">
			<div style="padding:0.75rem 1rem;font-weight:600;font-size:0.8125rem;text-transform:uppercase;color:var(--color-text-light);margin-bottom:0.5rem">
				<?php esc_html_e( 'Business Portal', 'zeko-business' ); ?>
			</div>
			<?php if ( $selected_biz ) : ?>
				<div style="padding:0.75rem 1rem;background:#f9fafb;border-radius:var(--radius-md);margin-bottom:0.75rem;font-size:0.875rem">
					<div style="font-weight:600"><?php echo esc_html( $selected_biz->name ); ?></div>
					<div style="color:var(--color-text-light);font-size:0.75rem"><?php echo esc_html( ucfirst( $selected_biz->status ) ); ?></div>
				</div>
			<?php endif; ?>
			<?php foreach ( $portal_tabs as $key => $active_tab ) : ?>
				<button class="zbp-portal-nav__item<?php echo 'overview' === $key ? ' zbp-portal-nav__item--active' : ''; ?>"
						data-tab="<?php echo esc_attr( $key ); ?>"
						data-business-id="<?php echo $selected_biz ? (int) $selected_biz->id : 0; ?>">
					<span><?php echo esc_html( $active_tab['icon'] ); ?></span>
					<span><?php echo esc_html( $active_tab['label'] ); ?></span>
					<?php if ( ! empty( $active_tab['badge'] ) ) : ?>
						<span class="zbp-portal-nav__badge"><?php echo (int) $active_tab['badge']; ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</nav>

		<main class="zbp-portal-content" aria-live="polite">
			<div class="zbp-text-center" style="padding:2rem">
				<p><?php esc_html_e( 'Loading...', 'zeko-business' ); ?></p>
			</div>
		</main>
	</div>
</div>

<?php if ( ! $is_shortcode ) {
	get_footer(); } ?>
