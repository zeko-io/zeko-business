<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/** Class SettingsPage. */
class SettingsPage {
	/**
	 * Defaults.
	 *
	 * @var array Defaults.
	 */
	private static array $defaults = array(
		'general'       => array(
			'default_status'     => 'pending',
			'require_approval'   => 1,
			'currency'           => '$',
			'per_page'           => 20,
			'demo_on_activation' => 0,
		),
		'display'       => array(
			'show_directory' => 1,
			'show_ratings'   => 1,
			'show_reviews'   => 1,
			'map_provider'   => 'openstreetmap',
		),
		'reviews'       => array(
			'enable_reviews'     => 1,
			'require_moderation' => 0,
			'min_rating'         => 1,
		),
		'notifications' => array(
			'email_new_business' => 1,
			'email_new_review'   => 1,
			'email_new_claim'    => 1,
		),
		'plans'         => array(
			'basic_price'      => 9.99,
			'pro_price'        => 29.99,
			'enterprise_price' => 79.99,
			'featured_price'   => 4.99,
			'sponsored_price'  => 14.99,
		),
	);

	/**
	 * Render.
	 */
	public static function render(): void {
		$settings = get_option( 'zbe_settings', self::$defaults );
		$settings = wp_parse_args( $settings, self::$defaults );
		foreach ( self::$defaults as $section => $defaults ) {
			$settings[ $section ] = wp_parse_args( $settings[ $section ] ?? array(), $defaults );
		}

		if ( ! empty( $_POST['zbe_save_settings'] ) && wp_verify_nonce( wp_unslash( $_POST['_zbe_settings_nonce'] ?? '' ), 'zbe_settings_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce field consumed by wp_verify_nonce().
			$new_settings = self::$defaults;

			$new_settings['general']['default_status']     = sanitize_text_field( wp_unslash( $_POST['general']['default_status'] ?? 'pending' ) );
			$new_settings['general']['require_approval']   = ! empty( $_POST['general']['require_approval'] ) ? 1 : 0;
			$new_settings['general']['currency']           = sanitize_text_field( wp_unslash( $_POST['general']['currency'] ?? '$' ) );
			$new_settings['general']['per_page']           = absint( $_POST['general']['per_page'] ?? 20 );
			$new_settings['general']['demo_on_activation'] = ! empty( $_POST['general']['demo_on_activation'] ) ? 1 : 0;

			$new_settings['display']['show_directory'] = ! empty( $_POST['display']['show_directory'] ) ? 1 : 0;
			$new_settings['display']['show_ratings']   = ! empty( $_POST['display']['show_ratings'] ) ? 1 : 0;
			$new_settings['display']['show_reviews']   = ! empty( $_POST['display']['show_reviews'] ) ? 1 : 0;
			$new_settings['display']['map_provider']   = sanitize_text_field( wp_unslash( $_POST['display']['map_provider'] ?? 'openstreetmap' ) );

			$new_settings['reviews']['enable_reviews']     = ! empty( $_POST['reviews']['enable_reviews'] ) ? 1 : 0;
			$new_settings['reviews']['require_moderation'] = ! empty( $_POST['reviews']['require_moderation'] ) ? 1 : 0;
			$new_settings['reviews']['min_rating']         = absint( $_POST['reviews']['min_rating'] ?? 1 );

			// Review criteria (label => enabled), stored as its own option so it.
			// can be shared with the public review form.
			$criteria      = array_filter( array_map( 'trim', (array) wp_unslash( $_POST['reviews']['criteria_label'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- labels sanitized per row below via sanitize_text_field().
			$enabled       = (array) wp_unslash( $_POST['reviews']['criteria_enabled'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- values coerced to int 1/0 per row below.
			$criteria_rows = array();
			$label_idx     = 0;
			foreach ( $criteria as $i => $label ) {
				$criteria_rows[] = array(
					'label'   => sanitize_text_field( $label ),
					'enabled' => ! empty( $enabled[ $i ] ) ? 1 : 0,
				);
				++$label_idx;
			}
			if ( $label_idx >= 1 ) {
				update_option( 'zbe_review_criteria', $criteria_rows );
			}

			$new_settings['notifications']['email_new_business'] = ! empty( $_POST['notifications']['email_new_business'] ) ? 1 : 0;
			$new_settings['notifications']['email_new_review']   = ! empty( $_POST['notifications']['email_new_review'] ) ? 1 : 0;
			$new_settings['notifications']['email_new_claim']    = ! empty( $_POST['notifications']['email_new_claim'] ) ? 1 : 0;

			$new_settings['plans']['basic_price']      = (float) sanitize_text_field( wp_unslash( $_POST['plans']['basic_price'] ?? 9.99 ) );
			$new_settings['plans']['pro_price']        = (float) sanitize_text_field( wp_unslash( $_POST['plans']['pro_price'] ?? 29.99 ) );
			$new_settings['plans']['enterprise_price'] = (float) sanitize_text_field( wp_unslash( $_POST['plans']['enterprise_price'] ?? 79.99 ) );
			$new_settings['plans']['featured_price']   = (float) sanitize_text_field( wp_unslash( $_POST['plans']['featured_price'] ?? 4.99 ) );
			$new_settings['plans']['sponsored_price']  = (float) sanitize_text_field( wp_unslash( $_POST['plans']['sponsored_price'] ?? 14.99 ) );

			update_option( 'zbe_settings', $new_settings );
			$settings = $new_settings;
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}

		$g = $settings['general'];
		$d = $settings['display'];
		$r = $settings['reviews'];
		$n = $settings['notifications'];
		$p = $settings['plans'];

		$rc = get_option( 'zbe_review_criteria', array() );
		if ( ! is_array( $rc ) || empty( $rc ) ) {
			$rc_defaults = array(
				array(
					'label'   => 'Quality',
					'enabled' => 1,
				),
				array(
					'label'   => 'Value',
					'enabled' => 1,
				),
				array(
					'label'   => 'Support',
					'enabled' => 1,
				),
				array(
					'label'   => 'Response Time',
					'enabled' => 0,
				),
			);
			$rc          = array_filter(
				$rc_defaults,
				function ( $item ) {
					return ! empty( $item['label'] );
				}
			);
		}
		?>
		<div class="wrap zbp-admin-wrap">
			<h1>Business Directory Settings</h1>

			<form method="post">
				<?php wp_nonce_field( 'zbe_settings_nonce', '_zbe_settings_nonce' ); ?>

				<div class="zbp-settings-section">
					<h2>General</h2>
					<table class="form-table">
						<tr>
							<th><label for="default_status">Default Business Status</label></th>
							<td>
								<select name="general[default_status]" id="default_status">
									<option value="active"<?php selected( $g['default_status'], 'active' ); ?>>Active</option>
									<option value="pending"<?php selected( $g['default_status'], 'pending' ); ?>>Pending</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>Require Approval</th>
							<td><label><input type="checkbox" name="general[require_approval]" value="1"<?php checked( $g['require_approval'], 1 ); ?>> Require admin approval before businesses go live</label></td>
						</tr>
						<tr>
							<th><label for="currency">Currency Symbol</label></th>
							<td><input type="text" name="general[currency]" id="currency" value="<?php echo esc_attr( $g['currency'] ); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th><label for="per_page">Businesses Per Page</label></th>
							<td><input type="number" name="general[per_page]" id="per_page" value="<?php echo (int) $g['per_page']; ?>" min="5" max="100" class="small-text" /></td>
						</tr>
						<tr>
							<th>Demo Data on Activation</th>
							<td><label><input type="checkbox" name="general[demo_on_activation]" value="1"<?php checked( $g['demo_on_activation'], 1 ); ?>> Seed a sample business listing on plugin activation (opt-in, off by default; when enabled, creates one demo business owned by the current admin)</label></td>
						</tr>
					</table>
				</div>

				<div class="zbp-settings-section">
					<h2>Display</h2>
					<table class="form-table">
						<tr>
							<th>Show Directory</th>
							<td><label><input type="checkbox" name="display[show_directory]" value="1"<?php checked( $d['show_directory'], 1 ); ?>> Show public business directory</label></td>
						</tr>
						<tr>
							<th>Show Ratings</th>
							<td><label><input type="checkbox" name="display[show_ratings]" value="1"<?php checked( $d['show_ratings'], 1 ); ?>> Show business ratings</label></td>
						</tr>
						<tr>
							<th>Show Reviews</th>
							<td><label><input type="checkbox" name="display[show_reviews]" value="1"<?php checked( $d['show_reviews'], 1 ); ?>> Show reviews section</label></td>
						</tr>
						<tr>
							<th><label for="map_provider">Map Provider</label></th>
							<td>
								<select name="display[map_provider]" id="map_provider">
									<option value="openstreetmap"<?php selected( $d['map_provider'], 'openstreetmap' ); ?>>OpenStreetMap</option>
									<option value="google"<?php selected( $d['map_provider'], 'google' ); ?>>Google Maps</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div class="zbp-settings-section">
					<h2>Reviews</h2>
					<table class="form-table">
						<tr>
							<th>Enable Reviews</th>
							<td><label><input type="checkbox" name="reviews[enable_reviews]" value="1"<?php checked( $r['enable_reviews'], 1 ); ?>> Allow users to leave reviews</label></td>
						</tr>
						<tr>
							<th>Require Moderation</th>
							<td><label><input type="checkbox" name="reviews[require_moderation]" value="1"<?php checked( $r['require_moderation'], 1 ); ?>> Hold reviews for admin approval</label></td>
						</tr>
						<tr>
							<th><label for="min_rating">Minimum Rating</label></th>
							<td>
								<select name="reviews[min_rating]" id="min_rating">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<option value="<?php echo esc_attr( $i ); ?>"<?php selected( $r['min_rating'], $i ); ?>><?php echo esc_html( $i ); ?> star<?php echo esc_html( $i > 1 ? 's' : '' ); ?></option>
									<?php endfor; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th>Review Criteria</th>
							<td>
								<p class="description">Enable per-criterion star ratings on reviews. Reviewers rate each enabled criterion on a 1–5 scale, and the overall rating is their average.</p>
								<div class="zbp-review-criteria" data-prerep>
									<?php $rc_idx = 0; ?>
									<?php foreach ( array_values( $rc ) as $rci => $item ) : ?>
										<div class="zbp-review-criteria__row" style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.4rem">
											<label>
												<input type="checkbox" name="reviews[criteria_enabled][<?php echo (int) $rci; ?>]" value="1"<?php checked( ! empty( $item['enabled'] ) ); ?>>
												<span class="screen-reader-text">Enabled</span>
											</label>
											<input type="text" name="reviews[criteria_label][]" value="<?php echo esc_attr( $item['label'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Criterion label', 'zeko-business' ); ?>" />
											<button type="button" class="button zbp-review-criteria__remove" onclick="this.closest('.zbp-review-criteria__row').remove()">&times;</button>
										</div>
									<?php endforeach; ?>
								</div>
								<button type="button" class="button zbp-review-criteria__add" onclick="zbpAddReviewCriterion(this)"><?php esc_attr_e( 'Add Criterion', 'zeko-business' ); ?></button>
								<script>
								function zbpAddReviewCriterion(btn) {
									var wrap = btn.previousElementSibling;
									var row = document.createElement('div');
									row.className = 'zbp-review-criteria__row';
									row.style.cssText = 'display:flex;gap:0.5rem;align-items:center;margin-bottom:0.4rem';
									var idx = wrap.querySelectorAll('.zbp-review-criteria__row').length;
									row.innerHTML = '<label><input type="checkbox" name="reviews[criteria_enabled][' + idx + ']" value="1" checked><span class="screen-reader-text">Enabled</span></label>' +
										'<input type="text" name="reviews[criteria_label][]" class="regular-text" placeholder="<?php esc_attr_e( 'Criterion label', 'zeko-business' ); ?>" />' +
										'<button type="button" class="button" onclick="this.closest(\'.zbp-review-criteria__row\').remove()">&times;</button>';
									wrap.appendChild(row);
								}
								</script>
								<script>
								window.zbpReviewCriteriaInit = window.zbpReviewCriteriaInit || [];
								</script>
							</td>
						</tr>
					</table>
				</div>

				<div class="zbp-settings-section">
					<h2>Notifications</h2>
					<table class="form-table">
						<tr>
							<th>New Business</th>
							<td><label><input type="checkbox" name="notifications[email_new_business]" value="1"<?php checked( $n['email_new_business'], 1 ); ?>> Email admin when a new business is registered</label></td>
						</tr>
						<tr>
							<th>New Review</th>
							<td><label><input type="checkbox" name="notifications[email_new_review]" value="1"<?php checked( $n['email_new_review'], 1 ); ?>> Email business owner when a review is submitted</label></td>
						</tr>
						<tr>
							<th>New Claim</th>
							<td><label><input type="checkbox" name="notifications[email_new_claim]" value="1"<?php checked( $n['email_new_claim'], 1 ); ?>> Email admin when a business claim is submitted</label></td>
						</tr>
					</table>
				</div>

				<div class="zbp-settings-section">
					<h2>Plan Pricing</h2>
					<table class="form-table">
						<tr>
							<th><label for="basic_price">Basic Plan (monthly)</label></th>
							<td><input type="number" step="0.01" min="0" name="plans[basic_price]" id="basic_price" value="<?php echo esc_attr( $p['basic_price'] ); ?>" class="small-text" /></td>
						</tr>
						<tr>
							<th><label for="pro_price">Pro Plan (monthly)</label></th>
							<td><input type="number" step="0.01" min="0" name="plans[pro_price]" id="pro_price" value="<?php echo esc_attr( $p['pro_price'] ); ?>" class="small-text" /></td>
						</tr>
						<tr>
							<th><label for="enterprise_price">Enterprise Plan (monthly)</label></th>
							<td><input type="number" step="0.01" min="0" name="plans[enterprise_price]" id="enterprise_price" value="<?php echo esc_attr( $p['enterprise_price'] ); ?>" class="small-text" /></td>
						</tr>
						<tr>
							<th><label for="featured_price">Featured Boost</label></th>
							<td><input type="number" step="0.01" min="0" name="plans[featured_price]" id="featured_price" value="<?php echo esc_attr( $p['featured_price'] ); ?>" class="small-text" /> <span class="description">One-time fee per 30 days</span></td>
						</tr>
						<tr>
							<th><label for="sponsored_price">Sponsored Boost</label></th>
							<td><input type="number" step="0.01" min="0" name="plans[sponsored_price]" id="sponsored_price" value="<?php echo esc_attr( $p['sponsored_price'] ); ?>" class="small-text" /> <span class="description">One-time fee per 30 days</span></td>
						</tr>
					</table>
				</div>

				<?php submit_button( 'Save Settings', 'primary', 'zbe_save_settings' ); ?>
			</form>
		</div>
		<?php
	}
}
