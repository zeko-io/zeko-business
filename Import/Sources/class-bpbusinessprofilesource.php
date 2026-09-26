<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * BuddyPress Business Profile by WBCom Designs — key "bpbp".
 *
 * Business listings are the `business` CPT (title = name, content = "About"
 * description, author = owning member, thumbnail = logo, comments enabled).
 * Field values live in `_business_*` post meta: contact
 * (_business_email/_phone/_website/_whatsapp), location
 * (_business_address/_address_1/_address_2/_city/_state/_country/_zipcode) and
 * coordinates (_business_lat/_business_long). Categories use the
 * `business-category` taxonomy. Reviews are WordPress comments of type
 * `bp_business_review` with a per-review rating in `_bp_business_average_rated_stars`
 * (and per-criteria stars in `_bp_business_rated_stars`). Followers are
 * BuddyPress group members of the group linked via the `bp-business-group`
 * post meta (owner = group creator). The plugin creates no custom DB tables.
 *
 * @see \ZBE\Import\Migrator
 */
final class BpBusinessProfileSource implements SourceInterface {

	use SourceHelpers;

	private const CPT      = 'business';
	private const TAXONOMY = 'business-category';
	private const TAX_ALT  = 'business_category';
	private const REV_TYPE = 'bp_business_review';

	/**
	 * Key.
	 */
	public function key(): string {
		return 'bpbp';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'BuddyPress Business Profile';
	}

	/**
	 * Version.
	 */
	public function version(): string {
		if ( defined( 'BP_BUSINESS_PROFILE_VERSION' ) ) {
			return (string) BP_BUSINESS_PROFILE_VERSION;
		}

		$file = WP_PLUGIN_DIR . '/bp-business-profile/bp-business-profile.php';
		if ( file_exists( $file ) && function_exists( 'wp_get_plugin_data' ) ) {
			return (string) ( wp_get_plugin_data( $file )['Version'] ?? '' );
		}

		return 'unknown';
	}

	/**
	 * Present.
	 */
	public function present(): bool {
		return $this->post_type_exists( self::CPT ) || $this->post_type_has_rows( self::CPT );
	}

	/**
	 * Status.
	 */
	public function status(): string {
		return $this->plugin_active() ? 'active' : ( $this->present() ? 'inactive' : 'unknown' );
	}

	/**
	 * Capabilities.
	 */
	public function capabilities(): string {
		return 'Listings, categories, photos, reviews, followers, hours';
	}

	/**
	 * Collect.
	 */
	public function collect(): array {
		$warnings = array();
		$rows     = array();

		$post_ids = $this->cpt_post_ids();

		if ( empty( $post_ids ) ) {
			$warnings[] = 'No live BuddyPress Business Profile listings were found.';
			return $this->collection( $warnings );
		}

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post || ! $this->is_live_status( (string) $post->post_status ) ) {
				continue;
			}

			$rows[] = array(
				'post_id'      => $post_id,
				'owner_id'     => (int) $post->post_author,
				'title'        => (string) $post->post_title,
				'slug'         => (string) $post->post_name ?: sanitize_title( $post->post_title ),
				'status'       => $this->map_status( (string) $post->post_status ),
				'email'        => sanitize_email( $this->meta_value( $post_id, array( '_business_email' ) ) ),
				'phone'        => sanitize_text_field( $this->meta_value( $post_id, array( '_business_phone' ) ) ),
				'website'      => esc_url_raw( $this->meta_value( $post_id, array( '_business_website' ) ) ),
				'whatsapp'     => sanitize_text_field( $this->meta_value( $post_id, array( '_business_whatsapp' ) ) ),
				'address'      => $this->full_address( $post_id ),
				'city'         => sanitize_text_field( $this->meta_value( $post_id, array( '_business_city' ) ) ),
				'state'        => sanitize_text_field( $this->meta_value( $post_id, array( '_business_state' ) ) ),
				'country'      => sanitize_text_field( $this->meta_value( $post_id, array( '_business_country' ) ) ),
				'zip'          => sanitize_text_field( $this->meta_value( $post_id, array( '_business_zipcode', '_business_zip' ) ) ),
				'lat'          => (float) $this->meta_value( $post_id, array( '_business_lat', '_business_latitude' ), '0' ),
				'lng'          => (float) $this->meta_value( $post_id, array( '_business_long', '_business_longitude' ), '0' ),
				'description'  => (string) $post->post_content,
				'featured'     => false,
				'is_verified'  => false,
				'date_created' => (string) $post->post_date,
				'categories'   => $this->term_names( $post_id, array( self::TAXONOMY, self::TAX_ALT ) ),
				'photos'       => $this->bp_photos( $post_id ),
				'reviews'      => $this->load_reviews( $post_id ),
				'followers'    => $this->load_followers( $post_id ),
			);
		}

		if ( empty( $rows ) ) {
			$warnings[] = 'No live BuddyPress Business Profile listings were found.';
		}

		return $this->collection( $warnings, $rows );
	}

	/**
	 * Cpt post ids.
	 */
	private function cpt_post_ids(): array {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','pending','future','draft','private') ORDER BY ID ASC", self::CPT ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Combine the address fields the plugin stores (address_1, address_2 and
	 * the optional full `_business_address`) into a single block.
	 *
	 * @param int $post_id Post id.
	 */
	private function full_address( int $post_id ): string {
		$explicit = trim( $this->meta_value( $post_id, array( '_business_address' ) ) );
		$line1    = trim( $this->meta_value( $post_id, array( '_business_address_1' ) ) );
		$line2    = trim( $this->meta_value( $post_id, array( '_business_address_2' ) ) );

		return implode( "\n", array_values( array_filter( array( $explicit, $line1, $line2 ) ) ) ) ?: '';
	}

	/**
	 * Photos: avatar image (logo) + cover image (header). Both are attachment
	 * IDs. Falls back to the post thumbnail / attached media.
	 *
	 * @return array<int, array{src: int|string, caption: string, cover: bool}>
	 * @param int $post_id Post id.
	 */
	private function bp_photos( int $post_id ): array {
		$photos = array();
		$seen   = array();

		$cover_id  = (int) $this->meta_value( $post_id, array( '_business_cover_image' ), '0' );
		$avatar_id = (int) $this->meta_value( $post_id, array( '_business_avatar_image' ), '0' );

		$cover = $cover_id ?: $avatar_id;
		if ( $cover > 0 ) {
			$photos[] = array(
				'src'     => $cover,
				'caption' => '',
				'cover'   => true,
			);
			$seen[]   = $cover;
		}

		if ( $avatar_id > 0 && $avatar_id !== $cover && ! in_array( $avatar_id, $seen, true ) ) {
			$photos[] = array(
				'src'     => $avatar_id,
				'caption' => '',
				'cover'   => false,
			);
			$seen[]   = $avatar_id;
		}

		if ( empty( $photos ) ) {
			$photos = $this->attached_photos( $post_id );
		}

		return $photos;
	}

	/**
	 * Reviews are WordPress comments of type `bp_business_review`. The plugin
	 * stores a per-review average in `_bp_business_average_rated_stars` and the
	 * per-criteria stars in `_bp_business_rated_stars`; the overall rating is
	 * derived from those. Guest reviews are `comment_approved = 0`.
	 *
	 * @return array<int, array{user_id: int, rating: int, title: string, content: string, status: string, date_created: string}>
	 * @param int $post_id Post id.
	 */
	private function load_reviews( int $post_id ): array {
		global $wpdb;

		$comments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.comment_ID, c.user_id, c.comment_content, c.comment_date, c.comment_date_gmt, c.comment_approved
			 FROM {$wpdb->comments} c
			 WHERE c.comment_post_ID = %d AND c.comment_type = %s",
				$post_id,
				self::REV_TYPE
			)
		);

		$reviews = array();

		foreach ( (array) $comments as $comment ) {
			$rating = $this->comment_rating( (int) $comment->comment_ID );

			if ( $rating <= 0 ) {
				continue;
			}

			$approved = (int) $comment->comment_approved;

			$reviews[] = array(
				'user_id'      => (int) $comment->user_id,
				'rating'       => min( 5, max( 1, (int) round( $rating ) ) ),
				'title'        => '',
				'content'      => sanitize_textarea_field( (string) $comment->comment_content ),
				'status'       => 1 === $approved ? 'approved' : 'pending',
				'date_created' => (string) $comment->comment_date_gmt ? (string) $comment->comment_date : current_time( 'mysql' ),
			);
		}

		return $reviews;
	}

	/**
	 * Derive the 1-5 overall rating for a review comment.
	 *
	 * @param int $comment_id Comment id.
	 */
	private function comment_rating( int $comment_id ): float {
		$average = get_comment_meta( $comment_id, '_bp_business_average_rated_stars', true );

		if ( is_numeric( $average ) && (float) $average > 0 ) {
			return (float) $average;
		}

		$stars = maybe_unserialize( get_comment_meta( $comment_id, '_bp_business_rated_stars', true ) );
		if ( is_array( $stars ) ) {
			$nums = array_filter( array_values( $stars ), 'is_numeric' );
			if ( ! empty( $nums ) ) {
				return (float) ( array_sum( $nums ) / count( $nums ) );
			}
		}

		return 0.0;
	}

	/**
	 * Followers are BuddyPress group members of the group the business links
	 * to (the `bp-business-group` post meta). The business owner is the group
	 * creator and is excluded from the follower set, mirroring the plugin's
	 * own UI. Degrades to [] when BuddyPress isn't loaded.
	 *
	 * @return int[]
	 * @param int $post_id Post id.
	 */
	private function load_followers( int $post_id ): array {
		if ( ! function_exists( 'groups_get_group' ) && ! class_exists( 'BP_Group_Member_Query' ) ) {
			return array();
		}

		$group_id = (int) $this->meta_value( $post_id, array( 'bp-business-group' ), '0' );

		if ( $group_id < 1 ) {
			return array();
		}

		if ( function_exists( 'groups_get_group' ) ) {
			$group   = groups_get_group( $group_id );
			$creator = isset( $group->creator_id ) ? (int) $group->creator_id : 0;
		} else {
			$creator = 0;
		}

		$user_ids = array();

		if ( class_exists( 'BP_Group_Member_Query' ) ) {
			$query = new \BP_Group_Member_Query(
				array(
					'group_id'   => $group_id,
					'group_role' => array( 'member', 'admin', 'mod' ),
					'type'       => 'alphabetical',
					'per_page'   => 0,
				)
			);

			if ( isset( $query->user_ids ) && is_array( $query->user_ids ) ) {
				$user_ids = $query->user_ids;
			}
		}

		$followers = array();

		foreach ( $user_ids as $uid ) {
			$uid = (int) $uid;
			if ( $uid > 0 && $uid !== $creator ) {
				$followers[] = $uid;
			}
		}

		return array_values( array_unique( $followers ) );
	}

	/**
	 * Plugin active.
	 */
	private function plugin_active(): bool {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'bp-business-profile/bp-business-profile.php' ) ) {
			return true;
		}
		return class_exists( 'Bp_Business_Profile' ) || class_exists( 'Bp_Business_Profile_Activator' ) || defined( 'BP_BUSINESS_PROFILE_VERSION' );
	}

	/**
	 * Build the collect() result array.
	 *
	 * @param array $warnings * @param array[]  $rows.
	 * @param array $rows Rows.
	 */
	private function collection( array $warnings, array $rows = array() ): array {
		return array(
			'source'       => $this->label(),
			'capabilities' => $this->capabilities(),
			'warnings'     => $warnings,
			'businesses'   => $rows,
		);
	}
}
