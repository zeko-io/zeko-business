<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Frontend;

use ZBE\Core\Geocode;
use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class Shortcodes. */
class Shortcodes {
	/**
	 * Register.
	 */
	public static function register(): void {
		add_shortcode( 'zbp_directory', array( __CLASS__, 'directory' ) );
		add_shortcode( 'zbp_single_business', array( __CLASS__, 'single_business' ) );
		add_shortcode( 'zbp_business_portal', array( __CLASS__, 'portal' ) );
		add_shortcode( 'zbp_create_business', array( __CLASS__, 'create_business' ) );
		add_shortcode( 'zbp_my_businesses', array( __CLASS__, 'my_businesses' ) );
	}

	/**
	 * Directory.
	 *
	 * @param array $atts Atts.
	 */
	public static function directory( array $atts = array() ): string {
		$settings = get_option( 'zbe_settings', array() );
		$per_page = $settings['general']['per_page'] ?? 20;

		$atts = shortcode_atts(
			array(
				'per_page' => $per_page,
				'status'   => 'active',
			),
			$atts,
			'zbp_directory'
		);

		$cities = Services::businesses()->distinct_cities( 100 );

		ob_start();
		?>
		<div class="zbp-directory" id="zbp-directory-container">
			<div class="zbp-directory__header">
				<h2 class="zbp-directory__title"><?php esc_html_e( 'Business Directory', 'zeko-business' ); ?></h2>
				<p class="zbp-directory__subtitle"><?php esc_html_e( 'Find businesses in your area', 'zeko-business' ); ?></p>
			</div>
			<div class="zbp-directory__search">
				<input type="text" class="zbp-directory__search-input" placeholder="<?php esc_attr_e( 'Search businesses...', 'zeko-business' ); ?>" aria-label="<?php esc_attr_e( 'Search businesses', 'zeko-business' ); ?>" />
				<button type="button" class="zbp-directory__search-btn"><?php esc_html_e( 'Search', 'zeko-business' ); ?></button>
			</div>
			<div class="zbp-directory__filters" role="group" aria-label="<?php esc_attr_e( 'Filter by status', 'zeko-business' ); ?>">
				<button type="button" class="zbp-directory__filter zbp-directory__filter--active" data-status="" aria-pressed="true"><?php esc_html_e( 'All', 'zeko-business' ); ?></button>
				<button type="button" class="zbp-directory__filter" data-status="active" aria-pressed="false"><?php esc_html_e( 'Active', 'zeko-business' ); ?></button>
				<button type="button" class="zbp-directory__filter" data-status="verified" aria-pressed="false"><?php esc_html_e( 'Verified', 'zeko-business' ); ?></button>
				<button type="button" class="zbp-directory__filter" data-status="featured" aria-pressed="false"><?php esc_html_e( 'Featured', 'zeko-business' ); ?></button>
			</div>
			<div class="zbp-directory__toolbar">
				<?php if ( ! empty( $cities ) ) : ?>
					<label class="zbp-directory__tool">
						<span><?php esc_html_e( 'City', 'zeko-business' ); ?></span>
						<select class="zbp-directory__control" data-param="city">
							<option value=""><?php esc_html_e( 'All cities', 'zeko-business' ); ?></option>
							<?php foreach ( $cities as $city ) : ?>
								<option value="<?php echo esc_attr( $city ); ?>"><?php echo esc_html( $city ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<label class="zbp-directory__tool">
					<span><?php esc_html_e( 'Near', 'zeko-business' ); ?></span>
					<input type="text" class="zbp-directory__control" data-param="near" placeholder="<?php esc_attr_e( 'City or postcode', 'zeko-business' ); ?>" />
				</label>
				<label class="zbp-directory__tool">
					<span><?php esc_html_e( 'Within', 'zeko-business' ); ?></span>
					<select class="zbp-directory__control" data-param="radius">
						<option value=""><?php esc_html_e( 'Any distance', 'zeko-business' ); ?></option>
						<option value="5">5 <?php esc_html_e( 'mi', 'zeko-business' ); ?></option>
						<option value="10">10 <?php esc_html_e( 'mi', 'zeko-business' ); ?></option>
						<option value="25">25 <?php esc_html_e( 'mi', 'zeko-business' ); ?></option>
						<option value="50">50 <?php esc_html_e( 'mi', 'zeko-business' ); ?></option>
						<option value="100">100 <?php esc_html_e( 'mi', 'zeko-business' ); ?></option>
					</select>
					<input type="hidden" class="zbp-directory__control" data-param="distance_units" value="mi" />
				</label>
				<label class="zbp-directory__tool">
					<span><?php esc_html_e( 'Rating', 'zeko-business' ); ?></span>
					<select class="zbp-directory__control" data-param="min_rating">
						<option value=""><?php esc_html_e( 'Any', 'zeko-business' ); ?></option>
						<option value="4"><?php esc_html_e( '4+ stars', 'zeko-business' ); ?></option>
						<option value="3"><?php esc_html_e( '3+ stars', 'zeko-business' ); ?></option>
					</select>
				</label>
				<label class="zbp-directory__tool">
					<span><?php esc_html_e( 'Sort by', 'zeko-business' ); ?></span>
					<select class="zbp-directory__control" data-param="sort">
						<option value="featured"><?php esc_html_e( 'Featured first', 'zeko-business' ); ?></option>
						<option value="rating"><?php esc_html_e( 'Highest rated', 'zeko-business' ); ?></option>
						<option value="views"><?php esc_html_e( 'Most viewed', 'zeko-business' ); ?></option>
						<option value="newest"><?php esc_html_e( 'Newest', 'zeko-business' ); ?></option>
						<option value="name"><?php esc_html_e( 'Name A–Z', 'zeko-business' ); ?></option>
					</select>
				</label>
				<label class="zbp-directory__tool zbp-directory__tool--check">
					<input type="checkbox" class="zbp-directory__control" data-param="open_now" value="1" />
					<span><?php esc_html_e( 'Open now', 'zeko-business' ); ?></span>
				</label>
				<button type="button" class="zbp-directory__view-toggle" aria-pressed="false" title="<?php esc_attr_e( 'Toggle list view', 'zeko-business' ); ?>">
					<?php esc_html_e( 'List view', 'zeko-business' ); ?>
				</button>
			</div>
			<div class="zbp-directory__grid" aria-live="polite">
				<?php self::render_business_list( $atts ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Single business.
	 *
	 * @param array $atts Atts.
	 */
	public static function single_business( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts,
			'zbp_single_business'
		);

		$repo = Services::businesses();
		$biz  = null;

		if ( $atts['slug'] ) {
			$biz = $repo->get_by_slug( sanitize_title( $atts['slug'] ) );
		} elseif ( $atts['id'] ) {
			$biz = $repo->get( absint( $atts['id'] ) );
		}

		if ( ! $biz || 'active' !== $biz->status ) {
			return '<div class="zbp-empty-state">' . esc_html__( 'Business not found.', 'zeko-business' ) . '</div>';
		}

		PublicController::load_single_data( $biz );

		ob_start();
		defined( 'ZBP_IN_SHORTCODE' ) || define( 'ZBP_IN_SHORTCODE', true );
		include ZBE_PLUGIN_DIR . '/Frontend/Templates/single.php';
		return ob_get_clean();
	}

	/**
	 * Portal.
	 *
	 * @param array $_atts atts.
	 */
	public static function portal( array $_atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="zbp-empty-state"><p>' .
				sprintf(
					/* translators: %s: login URL */
					esc_html__( 'Please %s to access the business portal.', 'zeko-business' ),
					'<a href="' . esc_url( wp_login_url( home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/business-portal/' ) ) ) ) ) . '">' . esc_html__( 'log in', 'zeko-business' ) . '</a>'
				) .
				'</p></div>';
		}

		$invite_token = sanitize_text_field( wp_unslash( $_GET['zbp_invite'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token validated server-side by team_service->accept_invite().
		$invite_msg   = '';

		if ( $invite_token ) {
			$team_service = \ZBE\Core\Services::team_service();
			$member       = $team_service->accept_invite( $invite_token, get_current_user_id() );

			if ( $member ) {
				$invite_msg = '<div class="zbp-notice zbp-notice--success">' . esc_html__( 'Invitation accepted! You are now a team member.', 'zeko-business' ) . '</div>';
			} else {
				$invite_msg = '<div class="zbp-notice zbp-notice--error">' . esc_html__( 'Invalid or expired invitation.', 'zeko-business' ) . '</div>';
			}
		}

		wp_enqueue_media();

		ob_start();
		defined( 'ZBP_IN_SHORTCODE' ) || define( 'ZBP_IN_SHORTCODE', true );
		include ZBE_PLUGIN_DIR . '/Frontend/Templates/business-portal.php';
		return ( $invite_msg ? $invite_msg : '' ) . ob_get_clean();
	}

	/**
	 * Create business.
	 *
	 * @param array $_atts atts.
	 */
	public static function create_business( array $_atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="zbp-empty-state"><p>' .
				sprintf(
					/* translators: %s: login URL */
					esc_html__( 'Please %s to create a business.', 'zeko-business' ),
					'<a href="' . esc_url( wp_login_url( home_url() ) ) . '">' . esc_html__( 'log in', 'zeko-business' ) . '</a>'
				) .
				'</p></div>';
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'business_category',
				'hide_empty' => false,
				'number'     => 50,
			)
		);

		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		ob_start();
		?>
		<div class="zbp-container">
			<div class="zbp-portal-content" style="max-width:720px;margin:0 auto">
				<h2><?php esc_html_e( 'Create Your Business', 'zeko-business' ); ?></h2>

				<ol class="zbp-wizard__steps" aria-label="<?php esc_attr_e( 'Form progress', 'zeko-business' ); ?>">
					<li class="zbp-wizard__step zbp-wizard__step--active" data-step-label="1"><?php esc_html_e( 'Basics', 'zeko-business' ); ?></li>
					<li class="zbp-wizard__step" data-step-label="2"><?php esc_html_e( 'Location', 'zeko-business' ); ?></li>
					<li class="zbp-wizard__step" data-step-label="3"><?php esc_html_e( 'Media', 'zeko-business' ); ?></li>
					<li class="zbp-wizard__step" data-step-label="4"><?php esc_html_e( 'Details', 'zeko-business' ); ?></li>
					<li class="zbp-wizard__step" data-step-label="5"><?php esc_html_e( 'Review', 'zeko-business' ); ?></li>
				</ol>

				<form class="zbp-portal-form zbp-wizard" data-action="zbp_create_business">
					<input type="hidden" name="zbp_form_ts" value="<?php echo (int) time(); ?>" />
					<input type="hidden" name="social_links" value="" />
					<input type="hidden" name="avatar_id" value="0" />
					<input type="hidden" name="cover_id" value="0" />
					<input type="hidden" name="gallery_ids" value="" />
					<input type="hidden" name="lat" value="" />
					<input type="hidden" name="lng" value="" />

					<fieldset class="zbp-wizard__panel" data-step="1">
						<legend class="screen-reader-text"><?php esc_html_e( 'Business basics', 'zeko-business' ); ?></legend>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label" for="zbp-cb-name"><?php esc_html_e( 'Business Name', 'zeko-business' ); ?> *</label>
							<input type="text" id="zbp-cb-name" name="business_name" class="zbp-form-input" required />
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label" for="zbp-cb-tagline"><?php esc_html_e( 'Tagline', 'zeko-business' ); ?></label>
							<input type="text" id="zbp-cb-tagline" name="tagline" class="zbp-form-input" maxlength="100" placeholder="<?php esc_attr_e( 'A short memorable phrase', 'zeko-business' ); ?>" />
						</div>
						<?php if ( ! empty( $categories ) ) : ?>
							<div class="zbp-portal-form__row">
								<span class="zbp-form-label"><?php esc_html_e( 'Categories', 'zeko-business' ); ?></span>
								<div class="zbp-category-picker">
									<?php foreach ( $categories as $term ) : ?>
										<label class="zbp-category-picker__option">
											<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
											<span><?php echo esc_html( $term->name ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<small class="zbp-form-hint"><?php esc_html_e( 'Pick up to 3 categories.', 'zeko-business' ); ?></small>
							</div>
						<?php endif; ?>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label" for="zbp-cb-desc-short"><?php esc_html_e( 'Short description', 'zeko-business' ); ?></label>
							<input type="text" id="zbp-cb-desc-short" name="short_description" class="zbp-form-input" maxlength="160" placeholder="<?php esc_attr_e( 'One line about what you do', 'zeko-business' ); ?>" />
							<small class="zbp-form-hint"><?php esc_html_e( 'Shown in directory cards and search results.', 'zeko-business' ); ?></small>
						</div>
					</fieldset>

					<fieldset class="zbp-wizard__panel" data-step="2" hidden>
						<legend class="screen-reader-text"><?php esc_html_e( 'Location and contact details', 'zeko-business' ); ?></legend>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label" for="zbp-cb-address"><?php esc_html_e( 'Street Address', 'zeko-business' ); ?></label>
							<input type="text" id="zbp-cb-address" name="address" class="zbp-form-input" placeholder="<?php esc_attr_e( 'Start typing for suggestions...', 'zeko-business' ); ?>" />
						</div>
						<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
							<div>
								<label class="zbp-form-label" for="zbp-cb-city"><?php esc_html_e( 'City', 'zeko-business' ); ?></label>
								<input type="text" id="zbp-cb-city" name="city" class="zbp-form-input" />
							</div>
							<div>
								<label class="zbp-form-label" for="zbp-cb-state"><?php esc_html_e( 'State / Province', 'zeko-business' ); ?></label>
								<input type="text" id="zbp-cb-state" name="state" class="zbp-form-input" />
							</div>
						</div>
						<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
							<div>
								<label class="zbp-form-label" for="zbp-cb-country"><?php esc_html_e( 'Country', 'zeko-business' ); ?></label>
								<input type="text" id="zbp-cb-country" name="country" class="zbp-form-input" />
							</div>
							<div>
								<label class="zbp-form-label" for="zbp-cb-zip"><?php esc_html_e( 'Postal Code', 'zeko-business' ); ?></label>
								<input type="text" id="zbp-cb-zip" name="zip" class="zbp-form-input" />
							</div>
						</div>
						<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
							<div>
								<label class="zbp-form-label" for="zbp-cb-email"><?php esc_html_e( 'Email', 'zeko-business' ); ?></label>
								<input type="email" id="zbp-cb-email" name="email" class="zbp-form-input" />
							</div>
							<div>
								<label class="zbp-form-label" for="zbp-cb-phone"><?php esc_html_e( 'Phone', 'zeko-business' ); ?></label>
								<input type="tel" id="zbp-cb-phone" name="phone" class="zbp-form-input" />
							</div>
						</div>
						<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
							<div>
								<label class="zbp-form-label" for="zbp-cb-website"><?php esc_html_e( 'Website', 'zeko-business' ); ?></label>
								<input type="url" id="zbp-cb-website" name="website" class="zbp-form-input" placeholder="https://" />
							</div>
							<div>
								<label class="zbp-form-label" for="zbp-cb-whatsapp"><?php esc_html_e( 'WhatsApp', 'zeko-business' ); ?></label>
								<input type="tel" id="zbp-cb-whatsapp" name="whatsapp" class="zbp-form-input" placeholder="+1234567890" />
							</div>
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Map Coordinates', 'zeko-business' ); ?></label>
							<div class="zbp-coord-picker">
								<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem">
									<div>
										<input type="number" step="any" id="zbp-cb-lat" name="lat_display" class="zbp-form-input" placeholder="Latitude" readonly />
									</div>
									<div>
										<input type="number" step="any" id="zbp-cb-lng" name="lng_display" class="zbp-form-input" placeholder="Longitude" readonly />
									</div>
									<div>
										<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-geolocate-btn"><?php esc_html_e( 'Use My Location', 'zeko-business' ); ?></button>
									</div>
								</div>
								<small class="zbp-form-hint"><?php esc_html_e( 'Click "Use My Location" or enter coordinates for map display.', 'zeko-business' ); ?></small>
							</div>
						</div>
					</fieldset>

					<fieldset class="zbp-wizard__panel" data-step="3" hidden>
						<legend class="screen-reader-text"><?php esc_html_e( 'Logo and cover photo', 'zeko-business' ); ?></legend>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Business Logo', 'zeko-business' ); ?></label>
							<div class="zbp-media-upload" id="zbp-avatar-upload">
								<div class="zbp-media-upload__preview" id="zbp-avatar-preview">
									<span class="zbp-media-upload__placeholder"><?php esc_html_e( 'Click to upload logo', 'zeko-business' ); ?></span>
								</div>
								<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-upload-avatar-btn"><?php esc_html_e( 'Select Logo', 'zeko-business' ); ?></button>
								<button type="button" class="zbp-btn zbp-btn--danger zbp-btn--sm zbp-remove-avatar-btn" hidden><?php esc_html_e( 'Remove', 'zeko-business' ); ?></button>
							</div>
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Cover Photo', 'zeko-business' ); ?></label>
							<div class="zbp-media-upload" id="zbp-cover-upload">
								<div class="zbp-media-upload__preview zbp-media-upload__preview--wide" id="zbp-cover-preview">
									<span class="zbp-media-upload__placeholder"><?php esc_html_e( 'Click to upload cover photo', 'zeko-business' ); ?></span>
								</div>
								<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-upload-cover-btn"><?php esc_html_e( 'Select Cover', 'zeko-business' ); ?></button>
								<button type="button" class="zbp-btn zbp-btn--danger zbp-btn--sm zbp-remove-cover-btn" hidden><?php esc_html_e( 'Remove', 'zeko-business' ); ?></button>
							</div>
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Gallery Photos', 'zeko-business' ); ?></label>
							<div class="zbp-gallery-upload">
								<div class="zbp-gallery-grid zbp-wizard-gallery" id="zbp-wizard-gallery"></div>
								<button type="button" class="zbp-btn zbp-btn--secondary zbp-btn--sm zbp-wizard-gallery-add"><?php esc_html_e( 'Add Photos', 'zeko-business' ); ?></button>
							</div>
							<p class="zbp-form-hint"><?php esc_html_e( 'Photos will be added to your business gallery. Availability depends on your plan.', 'zeko-business' ); ?></p>
						</div>
						<div class="zbp-portal-form__row">
							<span class="zbp-form-label"><?php esc_html_e( 'Social Media', 'zeko-business' ); ?></span>
							<div class="zbp-social-fields">
								<div class="zbp-social-field">
									<span class="zbp-social-field__icon">f</span>
									<input type="url" class="zbp-form-input" data-social="facebook" placeholder="https://facebook.com/..." />
								</div>
								<div class="zbp-social-field">
									<span class="zbp-social-field__icon">𝕏</span>
									<input type="url" class="zbp-form-input" data-social="twitter" placeholder="https://x.com/..." />
								</div>
								<div class="zbp-social-field">
									<span class="zbp-social-field__icon">in</span>
									<input type="url" class="zbp-form-input" data-social="linkedin" placeholder="https://linkedin.com/..." />
								</div>
								<div class="zbp-social-field">
									<span class="zbp-social-field__icon">▶</span>
									<input type="url" class="zbp-form-input" data-social="youtube" placeholder="https://youtube.com/..." />
								</div>
								<div class="zbp-social-field">
									<span class="zbp-social-field__icon">📷</span>
									<input type="url" class="zbp-form-input" data-social="instagram" placeholder="https://instagram.com/..." />
								</div>
							</div>
						</div>
					</fieldset>

					<fieldset class="zbp-wizard__panel" data-step="4" hidden>
						<legend class="screen-reader-text"><?php esc_html_e( 'Business description and details', 'zeko-business' ); ?></legend>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label"><?php esc_html_e( 'Description', 'zeko-business' ); ?></label>
							<div id="zbp-quill-desc"></div>
							<textarea name="description" class="zbp-form-textarea" rows="10" style="display:none" id="zbp-cb-desc"></textarea>
							<?php if ( class_exists( '\Zeko_AI_Writer_UI' ) ) : ?>
								<?php
								echo wp_kses_post(
									\Zeko_AI_Writer_UI::button(
										array(
											'preset' => 'business_description',
											'target' => '#zbp-cb-desc',
										)
									)
								);
								?>
							<?php endif; ?>
						</div>
						<div class="zbp-portal-form__row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
							<div>
								<label class="zbp-form-label" for="zbp-cb-founded"><?php esc_html_e( 'Year Founded', 'zeko-business' ); ?></label>
								<input type="number" id="zbp-cb-founded" name="founding_year" class="zbp-form-input" min="1800" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" placeholder="e.g. 2020" />
							</div>
							<div>
								<label class="zbp-form-label" for="zbp-cb-employees"><?php esc_html_e( 'Team Size', 'zeko-business' ); ?></label>
								<select id="zbp-cb-employees" name="employee_count" class="zbp-form-select">
									<option value=""><?php esc_html_e( 'Select...', 'zeko-business' ); ?></option>
									<option value="1-5">1-5</option>
									<option value="6-20">6-20</option>
									<option value="21-50">21-50</option>
									<option value="51-200">51-200</option>
									<option value="201-500">201-500</option>
									<option value="500+">500+</option>
								</select>
							</div>
							<div>
								<label class="zbp-form-label" for="zbp-cb-taxid"><?php esc_html_e( 'Tax ID / Registration No.', 'zeko-business' ); ?></label>
								<input type="text" id="zbp-cb-taxid" name="tax_id" class="zbp-form-input" />
							</div>
						</div>
					</fieldset>

					<fieldset class="zbp-wizard__panel" data-step="5" hidden>
						<legend class="screen-reader-text"><?php esc_html_e( 'Review and submit', 'zeko-business' ); ?></legend>
						<div class="zbp-review-summary">
							<p><?php esc_html_e( 'Please review your details before submitting.', 'zeko-business' ); ?></p>
							<div id="zbp-review-summary" class="zbp-review-summary__content"></div>
						</div>
						<button type="submit" class="zbp-btn zbp-btn--primary zbp-btn--lg"><?php esc_html_e( 'Create Business', 'zeko-business' ); ?></button>
					</fieldset>

					<div class="zbp-wizard__nav">
						<button type="button" class="zbp-btn zbp-btn--secondary zbp-wizard__prev" hidden><?php esc_html_e( 'Back', 'zeko-business' ); ?></button>
						<button type="button" class="zbp-btn zbp-btn--primary zbp-wizard__next"><?php esc_html_e( 'Next', 'zeko-business' ); ?></button>
					</div>
				</form>
			</div>
		</div>
			<?php
			return ob_get_clean();
	}

	/**
	 * My businesses.
	 *
	 * @param array $atts Atts.
	 */
	public static function my_businesses( array $atts = array() ): string {
		unset( $atts );
		if ( ! is_user_logged_in() ) {
			return '<div class="zbp-empty-state"><p>' . esc_html__( 'Please log in to view your businesses.', 'zeko-business' ) . '</p></div>';
		}

		$businesses = Services::businesses()->get_by_owner( get_current_user_id(), 100 );

		ob_start();
		?>
		<div class="zbp-container">
			<h2><?php esc_html_e( 'My Businesses', 'zeko-business' ); ?></h2>
			<?php if ( empty( $businesses ) ) : ?>
				<div class="zbp-empty-state">
					<p><?php esc_html_e( "You don't have any businesses yet.", 'zeko-business' ); ?></p>
					<a href="<?php echo esc_url( \ZBE\Plugin::page_url( 'submit' ) ); ?>" class="zbp-btn zbp-btn--primary"><?php esc_html_e( 'Create One', 'zeko-business' ); ?></a>
				</div>
			<?php else : ?>
				<div class="zbp-directory__grid">
					<?php foreach ( $businesses as $biz ) : ?>
						<div class="zbp-business-card">
							<div class="zbp-business-card__header">
								<div class="zbp-business-card__info">
									<a href="<?php echo esc_url( home_url( '/businesses/' . $biz->slug . '/' ) ); ?>" class="zbp-business-card__name"><?php echo esc_html( $biz->name ); ?></a>
									<div class="zbp-business-card__meta">
										<span class="zbp-badge zbp-badge--<?php echo esc_attr( $biz->status ); ?>"><?php echo esc_html( ucfirst( $biz->status ) ); ?></span>
										<span><?php echo esc_html( number_format( (int) $biz->view_count ) ); ?> <?php esc_html_e( 'views', 'zeko-business' ); ?></span>
									</div>
								</div>
							</div>
							<div style="padding:0 1rem 1rem;display:flex;gap:0.5rem">
								<a href="<?php echo esc_url( \ZBE\Plugin::page_url( 'portal', 'business_id=' . (int) $biz->id ) ); ?>" class="zbp-btn zbp-btn--primary zbp-btn--sm"><?php esc_html_e( 'Manage', 'zeko-business' ); ?></a>
								<a href="<?php echo esc_url( home_url( '/businesses/' . $biz->slug . '/' ) ); ?>" class="zbp-btn zbp-btn--secondary zbp-btn--sm"><?php esc_html_e( 'View', 'zeko-business' ); ?></a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shared filter args for the server-rendered list and the AJAX search so
	 * both paths apply identical whitelists.
	 *
	 * @param array $source Source.
	 */
	public static function directory_query_args( array $source ): array {
		$args = array(
			'status'   => 'active',
			'orderby'  => 'directory',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		);

		$status = sanitize_key( $source['status'] ?? '' );

		if ( 'verified' === $status ) {
			$args['is_verified'] = 1;
		} elseif ( 'featured' === $status ) {
			$args['is_featured'] = 1;
		}

		if ( ! empty( $source['search'] ) ) {
			$args['search'] = sanitize_text_field( (string) $source['search'] );
		}

		if ( ! empty( $source['city'] ) ) {
			$city = sanitize_text_field( (string) $source['city'] );
			if ( '' !== $city ) {
				$args['city'] = $city;
			}
		}

		if ( ! empty( $source['min_rating'] ) ) {
			$rating = (float) $source['min_rating'];
			if ( $rating > 0 && $rating <= 5 ) {
				$args['min_rating'] = $rating;
			}
		}

		if ( ! empty( $source['open_now'] ) ) {
			$args['open_now'] = 1;
		}

		// Radius / proximity search.
		$radius = (float) ( $source['radius'] ?? 0 );
		if ( $radius > 0 ) {
			$units     = ( 'km' === strtolower( (string) ( $source['distance_units'] ?? 'mi' ) ) ) ? 'km' : 'mi';
			$latitude  = is_numeric( $source['latitude'] ?? '' ) ? (float) $source['latitude'] : null;
			$longitude = is_numeric( $source['longitude'] ?? '' ) ? (float) $source['longitude'] : null;

			if ( ( null === $latitude || null === $longitude ) && ! empty( $source['near'] ) ) {
				$geo = Geocode::resolve( sanitize_text_field( (string) $source['near'] ) );
				if ( is_array( $geo ) ) {
					$latitude  = $geo[0];
					$longitude = $geo[1];
				}
			}

			if ( null !== $latitude && null !== $longitude ) {
				$args['latitude']       = $latitude;
				$args['longitude']      = $longitude;
				$args['radius']         = $radius;
				$args['distance_units'] = $units;

				if ( 'proximity' === sanitize_key( $source['sort'] ?? '' ) ) {
					$args['orderby'] = 'proximity';
					$args['order']   = 'ASC';
				}
			}
		}

		switch ( sanitize_key( $source['sort'] ?? '' ) ) {
			case 'rating':
				$args['orderby'] = 'avg_rating';
				$args['order']   = 'DESC';
				break;
			case 'views':
				$args['orderby'] = 'view_count';
				$args['order']   = 'DESC';
				break;
			case 'newest':
				$args['orderby'] = 'date_created';
				$args['order']   = 'DESC';
				break;
			case 'name':
				$args['orderby'] = 'name';
				$args['order']   = 'ASC';
				break;
		}

		if ( ! empty( $source['per_page'] ) ) {
			$args['per_page'] = max( 1, absint( $source['per_page'] ) );
		}

		if ( ! empty( $source['page'] ) ) {
			$args['page'] = max( 1, absint( $source['page'] ) );
		}

		return $args;
	}

	/**
	 * Shared card renderer used by the directory grid and the AJAX search.
	 *
	 * @param object $biz Biz.
	 */
	public static function render_card( object $biz ): void {
		$url = home_url( '/businesses/' . $biz->slug . '/' );
		?>
		<div class="zbp-business-card">
			<div class="zbp-business-card__header">
				<?php if ( $biz->avatar_id ) : ?>
					<?php echo wp_get_attachment_image( (int) $biz->avatar_id, 'thumbnail', false, array( 'class' => 'zbp-business-card__avatar' ) ); ?>
				<?php else : ?>
					<div class="zbp-business-card__avatar" style="background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;color:#6b7280"><?php echo esc_html( strtoupper( mb_substr( (string) $biz->name, 0, 1 ) ) ); ?></div>
				<?php endif; ?>
				<div class="zbp-business-card__info">
					<a href="<?php echo esc_url( $url ); ?>" class="zbp-business-card__name"><?php echo esc_html( $biz->name ); ?></a>
					<div class="zbp-business-card__meta">
						<?php
						if ( $biz->is_sponsored ) :
							?>
							<span class="zbp-badge" style="background:#fef3c7;color:#92400e"><?php esc_html_e( 'Sponsored', 'zeko-business' ); ?></span><?php endif; ?>
						<?php
						if ( $biz->is_featured ) :
							?>
							<span class="zbp-badge" style="background:#dbeafe;color:#1e40af">★ <?php esc_html_e( 'Featured', 'zeko-business' ); ?></span><?php endif; ?>
						<?php
						if ( $biz->is_verified ) :
							?>
							<span>✓ <?php esc_html_e( 'Verified', 'zeko-business' ); ?></span><?php endif; ?>
						<?php
						if ( $biz->city ) :
							?>
							<span><?php echo esc_html( $biz->city ); ?></span><?php endif; ?>
						<span class="zbp-business-card__rating">
							<span class="zbp-rating__star zbp-rating__star--filled">★</span>
							<?php echo esc_html( number_format( (float) $biz->avg_rating, 1 ) ); ?>
							<span>(<?php echo esc_html( (int) $biz->review_count ); ?>)</span>
						</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render business list.
	 *
	 * @param array $atts Atts.
	 */
	private static function render_business_list( array $atts ): void {
		$source = array_merge(
			self::request_source(),
			array(
				'status'   => sanitize_key( $atts['status'] ?? 'active' ),
				'per_page' => max( 1, absint( $atts['per_page'] ?? 20 ) ),
			)
		);

		$args = self::directory_query_args( $source );

		$service    = Services::business_service();
		$total      = $service->count( $args );
		$businesses = $service->search( $args );

		if ( empty( $businesses ) ) {
			echo '<div class="zbp-directory__empty">' . esc_html__( 'No businesses found.', 'zeko-business' ) . '</div>';
			return;
		}

		foreach ( $businesses as $biz ) {
			self::render_card( $biz );
		}

		$per_page    = (int) $args['per_page'];
		$page        = (int) $args['page'];
		$total_pages = (int) ceil( $total / $per_page );

		if ( $total_pages > 1 ) {
			$keep = array(
				'search'         => 'zbp_search',
				'city'           => 'zbp_city',
				'min_rating'     => 'zbp_rating',
				'sort'           => 'zbp_sort',
				'open_now'       => 'zbp_open',
				'near'           => 'zbp_near',
				'radius'         => 'zbp_radius',
				'distance_units' => 'zbp_units',
			);

			echo '<div class="zbp-directory__pagination" aria-label="' . esc_attr__( 'Directory pagination', 'zeko-business' ) . '">';

			for ( $i = 1; $i <= $total_pages; $i++ ) {
				$url = add_query_arg( 'zbp_page', $i );

				foreach ( $keep as $param => $name ) {
					$value = $source[ $param ] ?? '';
					if ( '' !== $value ) {
						$url = add_query_arg( $name, sanitize_text_field( (string) $value ), $url );
					}
				}

				echo '<a href="' . esc_url( $url ) . '" data-page="' . esc_attr( $i ) . '" class="zbp-directory__page-link' . ( $i === $page ? ' zbp-directory__page-link--active' : '' ) . '" aria-current="' . ( $i === $page ? 'page' : 'false' ) . '">' . esc_html( $i ) . '</a>';
			}

			echo '</div>';
		}
	}

	/**
	 * Normalize the directory querystring into canonical filter keys so both
	 * the bare (?city=…) and namespaced (?zbp_city=…) forms work, and the
	 * server-rendered pagination links round-trip their filters.
	 */
	private static function request_source(): array {
		$source = array();
		$keys   = array(
			'search'         => 'zbp_search',
			'city'           => 'zbp_city',
			'min_rating'     => 'zbp_rating',
			'sort'           => 'zbp_sort',
			'open_now'       => 'zbp_open',
			'near'           => 'zbp_near',
			'radius'         => 'zbp_radius',
			'distance_units' => 'zbp_units',
			'latitude'       => 'zbp_lat',
			'longitude'      => 'zbp_lng',
			'page'           => 'zbp_page',
		);

		foreach ( $keys as $canonical => $prefixed ) {
			if ( isset( $_GET[ $canonical ] ) && '' !== $_GET[ $canonical ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET URL filters for the public directory; no server state changes.
				$source[ $canonical ] = sanitize_text_field( wp_unslash( $_GET[ $canonical ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET URL filters for the public directory; no server state changes.
			} elseif ( isset( $_GET[ $prefixed ] ) && '' !== $_GET[ $prefixed ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET URL filters for the public directory; no server state changes.
				$source[ $canonical ] = sanitize_text_field( wp_unslash( $_GET[ $prefixed ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET URL filters for the public directory; no server state changes.
			}
		}

		return $source;
	}
}
