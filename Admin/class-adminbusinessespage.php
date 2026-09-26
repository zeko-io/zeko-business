<?php
/**
 * Business directory admin page.
 *
 * @package Zeko_Business
 */

namespace ZBE\Admin;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class AdminBusinessesPage. */
class AdminBusinessesPage {
	/**
	 * Render.
	 */
	public static function render(): void {
		$by_status = Services::businesses()->count_by_status();

		?>
		<div class="wrap zbp-admin-wrap">
			<h1><?php esc_html_e( 'Business Directory', 'zeko-business' ); ?></h1>

			<?php if ( ! empty( $_REQUEST['zbp_action'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only success notice; the mutating action itself is nonce-verified. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Businesses updated.', 'zeko-business' ); ?></p></div>
			<?php endif; ?>

			<div class="zbp-stat-cards">
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) array_sum( $by_status ) ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Total', 'zeko-business' ); ?></div>
				</div>
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) ( $by_status['active'] ?? 0 ) ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Active', 'zeko-business' ); ?></div>
				</div>
				<div class="zbp-stat-card">
					<div class="zbp-stat-card__number"><?php echo esc_html( (int) ( $by_status['pending'] ?? 0 ) ); ?></div>
					<div class="zbp-stat-card__label"><?php esc_html_e( 'Pending', 'zeko-business' ); ?></div>
				</div>
			</div>

			<?php
			wp_nonce_field( 'zbe_businesses_admin' );

			$table = new AdminBusinessTable();
			$table->prepare_items();

			echo '<form method="post">';
			$table->search_box( __( 'Search businesses', 'zeko-business' ), 's' );
			$table->display();
			echo '</form>';
			?>
		</div>
			<?php
	}
}
