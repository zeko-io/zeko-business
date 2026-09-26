<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Admin\Pages;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class ClaimsPage. */
class ClaimsPage {
	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! empty( $_POST['zbp_claim_action'] ) && wp_verify_nonce( wp_unslash( $_POST['_zbp_claims_nonce'] ?? '' ), 'zbp_claims_admin' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce field consumed by wp_verify_nonce().
			$action   = sanitize_text_field( wp_unslash( $_POST['zbp_claim_action'] ) );
			$claim_id = absint( $_POST['claim_id'] ?? 0 );
			$service  = Services::business_service();

			if ( $claim_id && 'approve' === $action ) {
				$service->approve_claim( $claim_id );
			}

			if ( $claim_id && 'reject' === $action ) {
				$service->reject_claim( $claim_id );
			}
		}

		$filter   = isset( $_GET['claim_filter'] ) ? sanitize_key( wp_unslash( $_GET['claim_filter'] ) ) : '';
		$status   = in_array( $filter, array( 'pending', 'approved', 'rejected' ), true ) ? $filter : 'pending';
		$claims   = Services::claims()->find(
			array(
				'status' => $status,
				'limit'  => 50,
			)
		);
		$biz_repo = Services::businesses();
		?>
		<div class="wrap zbp-admin-wrap">
			<h1><?php esc_html_e( 'Business Claims', 'zeko-business' ); ?></h1>

			<div class="tablenav top">
				<div class="alignleft actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=zbp-claims' ) ); ?>" class="button<?php echo 'pending' === $status ? ' button-primary' : ''; ?>"><?php esc_html_e( 'Pending', 'zeko-business' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=zbp-claims&claim_filter=approved' ) ); ?>" class="button<?php echo 'approved' === $status ? ' button-primary' : ''; ?>"><?php esc_html_e( 'Approved', 'zeko-business' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=zbp-claims&claim_filter=rejected' ) ); ?>" class="button<?php echo 'rejected' === $status ? ' button-primary' : ''; ?>"><?php esc_html_e( 'Rejected', 'zeko-business' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=zbp-claims&claim_filter=all' ) ); ?>" class="button<?php echo 'all' === $filter ? ' button-primary' : ''; ?>"><?php esc_html_e( 'All', 'zeko-business' ); ?></a>
				</div>
			</div>

			<table class="widefat zbp-business-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Business', 'zeko-business' ); ?></th>
						<th><?php esc_html_e( 'Claimant', 'zeko-business' ); ?></th>
						<th><?php esc_html_e( 'Method', 'zeko-business' ); ?></th>
						<th><?php esc_html_e( 'Evidence', 'zeko-business' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zeko-business' ); ?></th>
						<th><?php esc_html_e( 'Date', 'zeko-business' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'zeko-business' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $claims ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No claims found.', 'zeko-business' ); ?></td></tr>
				<?php else : ?>
					<?php
					foreach ( $claims as $claim ) :
						$user     = get_userdata( (int) $claim->user_id );
						$business = $biz_repo->get( (int) $claim->business_id );
						?>
						<tr>
							<td><?php echo esc_html( $business ? $business->name : __( 'Unknown business', 'zeko-business' ) ); ?></td>
							<td><?php echo $user ? esc_html( $user->display_name ) : esc_html( sprintf( '#%d', $claim->user_id ) ); ?></td>
							<td><?php echo esc_html( $claim->method ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $claim->evidence, 15 ) ); ?></td>
							<td><span class="zbp-badge zbp-badge--<?php echo esc_attr( $claim->status ); ?>"><?php echo esc_html( ucfirst( $claim->status ) ); ?></span></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $claim->date_created ) ) ); ?></td>
							<td class="zbp-actions">
								<?php if ( 'pending' === $claim->status ) : ?>
									<form method="post" style="display:inline">
										<?php wp_nonce_field( 'zbp_claims_admin', '_zbp_claims_nonce' ); ?>
										<input type="hidden" name="claim_id" value="<?php echo (int) $claim->id; ?>">
										<button type="submit" name="zbp_claim_action" value="approve" class="button button-primary button-small"><?php esc_html_e( 'Approve', 'zeko-business' ); ?></button>
										<button type="submit" name="zbp_claim_action" value="reject" class="button button-small"><?php esc_html_e( 'Reject', 'zeko-business' ); ?></button>
									</form>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
			<?php
	}
}
