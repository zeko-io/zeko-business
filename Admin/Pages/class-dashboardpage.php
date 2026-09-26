<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Admin\Pages;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class DashboardPage. */
class DashboardPage {
	/**
	 * Render.
	 */
	public static function render(): void {
		$biz_repo              = Services::businesses();
		$by_status             = $biz_repo->count_by_status();
		$total                 = array_sum( $by_status );
		$total_views           = $biz_repo->sum_views();
		$total_reviews         = Services::reviews()->count_all();
		$pending_claims        = count( Services::claims()->get_pending() );
		$pending_verifications = count( Services::verification()->get_pending() );
		$pending_reviews       = Services::reviews()->count_pending();
		$recent                = $biz_repo->find(
			array(
				'orderby'  => 'date_created',
				'order'    => 'DESC',
				'per_page' => 5,
				'page'     => 1,
			)
		);

		?>
		<div class="wrap zbp-admin-wrap">
			<h1><?php esc_html_e( 'Business Directory — Dashboard', 'zeko-business' ); ?></h1>

			<div class="zbp-stat-cards">
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) $total ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Total Businesses', 'zeko-business' ); ?></div>
				</div>
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) ( $by_status['active'] ?? 0 ) ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Active', 'zeko-business' ); ?></div>
				</div>
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) ( $by_status['pending'] ?? 0 ) ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Pending', 'zeko-business' ); ?></div>
				</div>
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) $total_reviews ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Total Reviews', 'zeko-business' ); ?></div>
				</div>
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( number_format( $total_views ) ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Total Views', 'zeko-business' ); ?></div>
				</div>
			</div>

			<?php if ( $pending_claims || $pending_verifications || $pending_reviews ) : ?>
				<div class="zbp-dashboard-panel" style="margin-top:1.25rem;padding:1rem;background:#fff;border-left:4px solid #f59e0b;box-shadow:0 1px 3px rgba(0,0,0,.1)">
					<strong><?php esc_html_e( 'Awaiting moderation:', 'zeko-business' ); ?></strong>
					<?php if ( $pending_claims ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=zbp-claims' ) ); ?>">
							<?php
							echo esc_html(
								sprintf(
								/* translators: %d: number of pending claims */
									_n( '%d claim', '%d claims', $pending_claims, 'zeko-business' ),
									$pending_claims
								)
							);
							?>
						</a>
					<?php endif; ?>
					<?php if ( $pending_verifications ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=zbp-verifications' ) ); ?>" style="<?php echo $pending_claims ? 'margin-left:1rem' : ''; ?>">
							<?php
							echo esc_html(
								sprintf(
								/* translators: %d: number of pending verification requests */
									_n( '%d verification request', '%d verification requests', $pending_verifications, 'zeko-business' ),
									$pending_verifications
								)
							);
							?>
						</a>
					<?php endif; ?>
					<?php if ( $pending_reviews ) : ?>
						<span style="<?php echo ( $pending_claims || $pending_verifications ) ? 'margin-left:1rem' : ''; ?>">
							<?php
							echo esc_html(
								sprintf(
								/* translators: %d: number of pending reviews */
									_n( '%d review', '%d reviews', $pending_reviews, 'zeko-business' ),
									$pending_reviews
								)
							);
							?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="zbp-dashboard-grid">
				<div class="zbp-dashboard-panel">
					<h2><?php esc_html_e( 'Recent Businesses', 'zeko-business' ); ?></h2>
						<?php if ( empty( $recent ) ) : ?>
						<p class="zbp-text-light"><?php esc_html_e( 'No businesses yet.', 'zeko-business' ); ?></p>
					<?php else : ?>
						<table class="widefat zbp-business-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Name', 'zeko-business' ); ?></th>
									<th><?php esc_html_e( 'Status', 'zeko-business' ); ?></th>
									<th><?php esc_html_e( 'Rating', 'zeko-business' ); ?></th>
									<th><?php esc_html_e( 'Date', 'zeko-business' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $recent as $biz ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( home_url( '/businesses/' . $biz->slug . '/' ) ); ?>">
											<?php echo esc_html( $biz->name ); ?>
										</a>
									</td>
									<td><span class="zbp-badge zbp-badge--<?php echo esc_attr( $biz->status ); ?>"><?php echo esc_html( ucfirst( $biz->status ) ); ?></span></td>
									<td><?php echo esc_html( number_format( (float) $biz->avg_rating, 1 ) ); ?> ★</td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $biz->date_created ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
			<?php
	}
}
