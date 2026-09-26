<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Directorist (directorist.com) — key "directorist".
 *
 * Modern Directorist stores listings as posts of type `at_biz_dir` with field
 * values in `_listing_*` post meta. Older releases kept a `at_biz_dir` table.
 * Categories come from the `at_biz_dir_category` taxonomy and photos from the
 * `_listing_prv_img` (cover) + `_listing_img` (gallery) meta keys.
 */
final class DirectoristSource implements SourceInterface {

	use SourceHelpers;

	private const CPT        = 'at_biz_dir';
	private const TAXONOMY   = 'at_biz_dir_category';
	private const LIST_TABLE = 'at_biz_dir';

	/**
	 * Key.
	 */
	public function key(): string {
		return 'directorist';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Directorist';
	}

	/**
	 * Version.
	 */
	public function version(): string {
		return defined( 'DIRECTORIST_VERSION' ) ? (string) DIRECTORIST_VERSION : 'unknown';
	}

	/**
	 * Present.
	 */
	public function present(): bool {
		global $wpdb;

		if ( class_exists( 'Directorist' ) ) {
			return true;
		}

		if ( $this->table_exists( $wpdb->prefix . self::LIST_TABLE ) ) {
			return true;
		}

		return $this->post_type_has_rows( self::CPT );
	}

	/**
	 * Status.
	 */
	public function status(): string {
		return class_exists( 'Directorist' ) ? 'active' : ( $this->present() ? 'inactive' : 'unknown' );
	}

	/**
	 * Collect.
	 */
	public function collect(): array {
		global $wpdb;

		$warnings = array();
		$rows     = array();

		$table = $wpdb->prefix . self::LIST_TABLE;

		$post_ids = array();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $this->table_exists( $table ) ) {
			// Legacy table path: extract dedicated post IDs.
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$listing_rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY ID ASC" );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

			foreach ( (array) $listing_rows as $listing ) {
				$post_id = (int) $this->pick( $listing, array( 'post_id', 'ID', 'id', 'listing_id' ) );

				if ( $post_id > 0 ) {
					$post_ids[] = $post_id;
				}
			}

			$post_ids = array_values( array_unique( $post_ids ) );
		}

		if ( empty( $post_ids ) ) {
			$post_ids = $this->cpt_post_ids();
		}

		if ( empty( $post_ids ) ) {
			$warnings[] = 'No live Directorist listings were found.';
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
				'slug'         => sanitize_title( $post->post_title ),
				'status'       => $this->map_status( (string) $post->post_status ),
				'email'        => sanitize_email( $this->meta_value( $post_id, array( '_listing_email' ) ) ),
				'phone'        => sanitize_text_field( $this->meta_value( $post_id, array( '_listing_phone' ) ) ),
				'website'      => esc_url_raw( $this->meta_value( $post_id, array( '_listing_website' ) ) ),
				'whatsapp'     => '',
				'address'      => sanitize_textarea_field( $this->meta_value( $post_id, array( '_listing_address' ) ) ),
				'city'         => sanitize_text_field( $this->meta_value( $post_id, array( '_listing_city' ) ) ),
				'state'        => sanitize_text_field( $this->meta_value( $post_id, array( '_listing_state' ) ) ),
				'country'      => sanitize_text_field( $this->meta_value( $post_id, array( '_listing_country' ) ) ),
				'zip'          => sanitize_text_field( $this->meta_value( $post_id, array( '_listing_zip' ) ) ),
				'lat'          => (float) $this->meta_value( $post_id, array( '_listing_latitude' ), '0' ),
				'lng'          => (float) $this->meta_value( $post_id, array( '_listing_longitude' ), '0' ),
				'description'  => (string) $post->post_content,
				'featured'     => '1' === $this->meta_value( $post_id, array( '_listing_featured' ), '' ),
				'date_created' => (string) $post->post_date,
				'categories'   => $this->term_names( $post_id, array( self::TAXONOMY ) ),
				'photos'       => $this->directorist_photos( $post_id ),
				'reviews'      => $this->load_reviews( $post_id ),
				'followers'    => array(),
			);
		}

		if ( empty( $rows ) ) {
			$warnings[] = 'No live Directorist listings were found.';
		}

		return $this->collection( $warnings, $rows );
	}

	/**
	 * Post IDs for the listing CPT.
	 *
	 * @return int[]
	 */
	private function cpt_post_ids(): array {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','pending','future','draft','private') ORDER BY ID ASC", self::CPT ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Cover image (_listing_prv_img URL) + gallery (_listing_img blob).
	 *
	 * @return array<int, array{src: int|string, caption: string, cover: bool}>
	 * @param int $post_id Post id.
	 */
	private function directorist_photos( int $post_id ): array {
		$photos = array();
		$seen   = array();

		$cover = $this->meta_value( $post_id, array( '_listing_prv_img', '_listing_cover' ) );

		if ( '' !== $cover ) {
			$photos[] = array(
				'src'     => $cover,
				'caption' => '',
				'cover'   => true,
			);
			$seen[]   = $cover;
		}

		$gallery_raw = $this->meta_value( $post_id, array( '_listing_img', '_listing_gallery' ) );

		if ( '' !== $gallery_raw ) {
			foreach ( $this->parse_photos( $gallery_raw ) as $src ) {
				if ( in_array( (string) $src, $seen, true ) ) {
					continue;
				}

				$photos[] = array(
					'src'     => $src,
					'caption' => '',
					'cover'   => false,
				);
				$seen[]   = (string) $src;
			}
		}

		if ( empty( $photos ) ) {
			$photos = $this->attached_photos( $post_id );
		}

		return $photos;
	}

	/**
	 * Directorist stores reviews as comments on the listing post.
	 *
	 * @return array<int, array{user_id: int, rating: int, title: string, content: string, status: string, date_created: string}>
	 * @param int $post_id Post id.
	 */
	private function load_reviews( int $post_id ): array {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = '1'", $post_id ) );

		$reviews = array();

		foreach ( (array) $comments as $comment ) {
			$rating = (int) get_comment_meta( (int) $comment->comment_ID, '_atbdp_rating', true );

			if ( ! $rating ) {
				$rating = (int) get_comment_meta( (int) $comment->comment_ID, 'atbdp_rating', true );
			}

			if ( $rating < 1 ) {
				continue;
			}

			$reviews[] = array(
				'user_id'      => (int) $comment->user_id,
				'rating'       => min( 5, max( 1, $rating ) ),
				'title'        => '',
				'content'      => sanitize_textarea_field( (string) $comment->comment_content ),
				'status'       => 'approved',
				'date_created' => (string) $comment->comment_date_gmt ? (string) $comment->comment_date : current_time( 'mysql' ),
			);
		}

		return $reviews;
	}

	/**
	 * Capabilities.
	 */
	public function capabilities(): string {
		return 'Listings, categories, photos, reviews';
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
