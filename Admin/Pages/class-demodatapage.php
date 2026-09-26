<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Admin\Pages;

use ZBE\Core\DemoSeeder;
use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class DemoDataPage. */
class DemoDataPage {

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_zbp' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage demo data.', 'zeko-business' ) );
		}

		$message = null;
		$type    = 'success';

		if ( isset( $_POST['zbe_demo_action'] ) && check_admin_referer( 'zbe_demo_nonce', '_zbe_demo_nonce' ) ) {
			$action = sanitize_key( $_POST['zbe_demo_action'] );

			if ( 'generate' === $action ) {
				$count   = absint( $_POST['zbe_demo_count'] ?? 10 );
				$stats   = DemoSeeder::generate( $count );
				$message = sprintf(
					/* translators: %d = number of businesses added */
					esc_html__( 'Demo data generated: %1$d businesses, %2$d services, %3$d reviews, %4$d followers, %5$d media.', 'zeko-business' ),
					(int) $stats['businesses'],
					(int) $stats['services'],
					(int) $stats['reviews'],
					(int) $stats['followers'],
					(int) $stats['media']
				);
			} elseif ( 'remove' === $action ) {
				$stats   = DemoSeeder::remove();
				$message = sprintf(
					/* translators: %1$d = businesses removed; %2$d = total items removed */
					esc_html__( 'Demo data removed: %1$d businesses and %2$d total items cleaned up.', 'zeko-business' ),
					(int) $stats['businesses'],
					(int) $stats['removed']
				);
			} elseif ( 'flush' === $action ) {
				\ZBE\Core\Cache::flush();
				$message = esc_html__( 'Cache flushed.', 'zeko-business' );
			}
		}

		$registry = get_option( 'zbe_demo_registry', array() );
		$registry = is_array( $registry ) ? $registry : array();

		$counts   = array(
			'businesses' => count( $registry['business_ids'] ?? array() ),
			'services'   => count( $registry['service_ids'] ?? array() ),
			'reviews'    => count( $registry['review_ids'] ?? array() ),
			'followers'  => count( $registry['follower_ids'] ?? array() ),
			'media'      => count( $registry['media_ids'] ?? array() ),
		);
		$has_demo = array_sum( $counts ) > 0;

		?>
		<div class="wrap zbp-admin-wrap">
			<h1><?php esc_html_e( 'Demo Data', 'zeko-business' ); ?></h1>

			<?php if ( $message ) : ?>
				<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
					<p><?php echo wp_kses_post( $message ); ?></p>
				</div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'Populate the directory with realistic sample businesses and their associated services, hours, reviews, followers, media and categories. This is great for testing the public directory, single business pages, the business portal and admin analytics.', 'zeko-business' ); ?>
			</p>

			<h2><?php esc_html_e( 'Current demo data', 'zeko-business' ); ?></h2>
			<p>
				<?php if ( $has_demo ) : ?>
					<?php
					printf(
						/* translators: counts */
						esc_html__( '%1$d businesses · %2$d services · %3$d reviews · %4$d followers · %5$d media.', 'zeko-business' ),
						(int) $counts['businesses'],
						(int) $counts['services'],
						(int) $counts['reviews'],
						(int) $counts['followers'],
						(int) $counts['media']
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'No demo data has been generated yet.', 'zeko-business' ); ?>
				<?php endif; ?>
			</p>

			<h2><?php esc_html_e( 'Generate demo data', 'zeko-business' ); ?></h2>
			<form method="post">
					<?php wp_nonce_field( 'zbe_demo_nonce', '_zbe_demo_nonce' ); ?>
				<input type="hidden" name="zbe_demo_action" value="generate" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="zbe_demo_count"><?php esc_html_e( 'Number of businesses', 'zeko-business' ); ?></label></th>
						<td>
							<input type="number" id="zbe_demo_count" name="zbe_demo_count" value="10" min="1" max="10" class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Seeds up to 10 varied sample businesses (café, auto repair, home services, yoga, web studio, spa, fitness, clinic, education, retail).', 'zeko-business' ); ?>
							</p>
						</td>
					</tr>
				</table>
					<?php submit_button( __( 'Generate Demo Data', 'zeko-business' ), 'primary', 'zbe_demo_submit' ); ?>
			</form>

			<h2><?php esc_html_e( 'Remove demo data', 'zeko-business' ); ?></h2>
			<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Remove all generated demo data? This cannot be undone.', 'zeko-business' ) ); ?>');">
					<?php wp_nonce_field( 'zbe_demo_nonce', '_zbe_demo_nonce' ); ?>
				<input type="hidden" name="zbe_demo_action" value="remove" />
				<p>
					<?php esc_html_e( 'Deletes only the businesses and related rows created by the demo generator. Your real listings are left untouched.', 'zeko-business' ); ?>
				</p>
					<?php submit_button( __( 'Remove Demo Data', 'zeko-business' ), 'delete', 'zbe_demo_remove', false, array( 'class' => 'button button-secondary' ) ); ?>
			</form>
		</div>
			<?php
	}
}
