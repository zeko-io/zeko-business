<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Admin\Pages;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class AnalyticsPage. */
class AnalyticsPage {
	/**
	 * Render.
	 */
	public static function render(): void {
		$biz_repo      = Services::businesses();
		$total_biz     = $biz_repo->count();
		$total_views   = $biz_repo->sum_views();
		$total_reviews = Services::reviews()->count_all();

		$by_plan    = $biz_repo->count_by_plan();
		$top_views  = $biz_repo->find(
			array(
				'orderby'  => 'view_count',
				'order'    => 'DESC',
				'per_page' => 10,
				'page'     => 1,
			)
		);
		$top_rating = $biz_repo->find(
			array(
				'min_reviews' => 1,
				'orderby'     => 'avg_rating',
				'order'       => 'DESC',
				'per_page'    => 10,
				'page'        => 1,
			)
		);

		$daily_totals = Services::analytics_repo()->daily_totals( 30 );

		$chart_labels = array_keys( $daily_totals );
		$chart_data   = array_values( $daily_totals );

		?>
		<div class="wrap zbp-admin-wrap">
			<h1><?php esc_html_e( 'Business Analytics', 'zeko-business' ); ?></h1>

			<div class="zbp-stat-cards">
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) $total_biz ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Total Businesses', 'zeko-business' ); ?></div>
				</div>
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( number_format( $total_views ) ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Total Views', 'zeko-business' ); ?></div>
				</div>
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) $total_reviews ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Total Reviews', 'zeko-business' ); ?></div>
				</div>
			</div>

			<div class="zbp-dashboard-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:1.5rem">
				<div class="zbp-dashboard-panel" style="background:#fff;padding:1.5rem;border-radius:0.5rem;box-shadow:0 1px 3px rgba(0,0,0,.1)">
					<h2><?php esc_html_e( 'Views (Last 30 Days)', 'zeko-business' ); ?></h2>
					<?php if ( empty( $chart_labels ) ) : ?>
						<p class="zbp-text-light"><?php esc_html_e( 'No views recorded yet.', 'zeko-business' ); ?></p>
					<?php endif; ?>
					<canvas id="zbp-views-chart" height="200"></canvas>
					<script>
					document.addEventListener('DOMContentLoaded', function() {
						if (typeof Chart === 'undefined') return;
						var ctx = document.getElementById('zbp-views-chart');
						if (!ctx) return;
						new Chart(ctx.getContext('2d'), {
							type: 'line',
							data: {
								labels: <?php echo wp_json_encode( $chart_labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>,
								datasets: [{
									label: <?php echo wp_json_encode( __( 'Views', 'zeko-business' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>,
									data: <?php echo wp_json_encode( $chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>,
									borderColor: '#2563eb',
									backgroundColor: 'rgba(37,99,235,0.1)',
									fill: true,
									tension: 0.3
								}]
							},
							options: { responsive: true, plugins: { legend: { display: false } } }
						});
					});
					</script>
				</div>

				<div class="zbp-dashboard-panel" style="background:#fff;padding:1.5rem;border-radius:0.5rem;box-shadow:0 1px 3px rgba(0,0,0,.1)">
					<h2><?php esc_html_e( 'By Plan', 'zeko-business' ); ?></h2>
					<table class="widefat">
						<thead><tr><th><?php esc_html_e( 'Plan', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Count', 'zeko-business' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $by_plan as $plan => $cnt ) : ?>
							<tr>
								<td><?php echo esc_html( ucfirst( (string) $plan ) ); ?></td>
								<td><?php echo esc_html( (int) $cnt ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="zbp-dashboard-panel" style="background:#fff;padding:1.5rem;border-radius:0.5rem;box-shadow:0 1px 3px rgba(0,0,0,.1)">
					<h2><?php esc_html_e( 'Top Businesses by Views', 'zeko-business' ); ?></h2>
					<table class="widefat">
						<thead><tr><th><?php esc_html_e( 'Business', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Views', 'zeko-business' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $top_views as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->name ); ?></td>
								<td><?php echo esc_html( number_format( (int) $row->view_count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="zbp-dashboard-panel" style="background:#fff;padding:1.5rem;border-radius:0.5rem;box-shadow:0 1px 3px rgba(0,0,0,.1)">
					<h2><?php esc_html_e( 'Top Businesses by Rating', 'zeko-business' ); ?></h2>
					<table class="widefat">
						<thead><tr><th><?php esc_html_e( 'Business', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Rating', 'zeko-business' ); ?></th><th><?php esc_html_e( 'Reviews', 'zeko-business' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $top_rating as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->name ); ?></td>
								<td><?php echo esc_html( number_format( (float) $row->avg_rating, 1 ) ); ?> ★</td>
								<td><?php echo esc_html( (int) $row->review_count ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}
}
