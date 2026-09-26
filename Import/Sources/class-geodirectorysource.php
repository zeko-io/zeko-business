<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * GeoDirectory (wpgeodirectory.com) — key "geodirectory".
 *
 * Listings are posts of type `gd_place` with structured data in a
 * `<prefix>geodir_<cpt>_detail` table (post_id + post_* columns). Image URLs
 * sit in the `post_images` column (comma separated), category IDs in
 * `post_category` and reviews in the `geodir_post_review` table. Column names
 * changed between major versions, so every read uses candidate+introspection
 * fallbacks.
 */
final class GeoDirectorySource implements SourceInterface {

	use SourceHelpers;

	private const DETAIL_LIKE = 'geodir_%_detail';

	/**
	 * Key.
	 */
	public function key(): string {
		return 'geodirectory';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'GeoDirectory';
	}

	/**
	 * Version.
	 */
	public function version(): string {
		return defined( 'GEODIRECTORY_VERSION' ) ? (string) GEODIRECTORY_VERSION : 'unknown';
	}

	/**
	 * Present.
	 */
	public function present(): bool {
		if ( class_exists( 'GeoDirectory' ) ) {
			return true;
		}

		foreach ( $this->detail_tables() as $table ) {
			if ( $this->table_exists( $table ) ) {
				return true;
			}
		}

		return $this->post_type_has_rows( 'gd_place' );
	}

	/**
	 * Status.
	 */
	public function status(): string {
		return class_exists( 'GeoDirectory' ) ? 'active' : ( $this->present() ? 'inactive' : 'unknown' );
	}

	/**
	 * Collect.
	 */
	public function collect(): array {
		global $wpdb;

		$warnings = array();
		$rows     = array();

		$tables = array_filter( $this->detail_tables(), array( $this, 'table_exists' ) );

		if ( empty( $tables ) ) {
			$warnings[] = 'GeoDirectory detail tables were not found.';
			return $this->collection( $warnings );
		}

		// Prefer the newest-style detail table.
		$table = ( array_values( $tables ) )[0];

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$listings = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY post_id ASC" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $listings ) ) {
			$warnings[] = 'No listings were found in the GeoDirectory detail table.';
			return $this->collection( $warnings );
		}

		$review_rows = $this->load_reviews();

		foreach ( $listings as $listing ) {
			$post_id = (int) $this->pick( $listing, array( 'post_id', 'ID', 'id' ) );

			if ( $post_id < 1 ) {
				continue;
			}

			$post = $post_id ? get_post( $post_id ) : null;

			$title = trim( (string) $this->pick( $listing, array( 'post_title', 'gd_title', 'title' ), '' ) );

			if ( '' === $title && $post instanceof \WP_Post ) {
				$title = (string) $post->post_title;
			}

			$status_raw = $this->pick( $listing, array( 'post_status', 'status', 'publish' ), '' );

			if ( '' === $status_raw && $post instanceof \WP_Post ) {
				$status_raw = (string) $post->post_status;
			}

			if ( ! $this->is_live_status( $status_raw ) ) {
				continue;
			}

			$owner_id = (int) $this->pick( $listing, array( 'post_author', 'author_id', 'user_id' ), '0' );

			if ( $owner_id < 1 && $post instanceof \WP_Post ) {
				$owner_id = (int) $post->post_author;
			}

			if ( $owner_id < 1 ) {
				$admin    = get_userdata( 1 );
				$owner_id = $admin ? 1 : 0;
			}

			$description = (string) $this->pick( $listing, array( 'post_content', 'content', 'post_excerpt' ), '' );

			if ( '' === $description && $post instanceof \WP_Post ) {
				$description = (string) $post->post_content;
			}

			$created = (string) $this->pick( $listing, array( 'post_date', 'date_created', 'submit_time' ), '' );

			if ( '' === $created && $post instanceof \WP_Post ) {
				$created = (string) $post->post_date;
			}

			$email   = $this->pick( $listing, array( 'contact_email', 'geodir_email', 'post_email', 'email' ) );
			$phone   = $this->pick( $listing, array( 'contact_phone', 'geodir_contact', 'post_phone', 'phone' ) );
			$website = $this->pick( $listing, array( 'contact_website', 'geodir_website', 'post_website', 'website' ) );

			if ( '' === $email ) {
				$email = $this->meta_value( $post_id, array( '_geodir_email', '_email' ) );
			}

			$address = $this->pick( $listing, array( 'street', 'post_address', 'address', 'geodir_address' ) );
			$city    = $this->pick( $listing, array( 'city', 'post_city', 'geodir_city' ) );
			$state   = $this->pick( $listing, array( 'region', 'state', 'post_region' ) );
			$country = $this->pick( $listing, array( 'country', 'post_country' ) );
			$zip     = $this->pick( $listing, array( 'zip', 'post_zip' ) );

			$lat = (float) $this->pick( $listing, array( 'latitude', 'post_latitude', 'geodir_latitude' ), '0' );
			$lng = (float) $this->pick( $listing, array( 'longitude', 'post_longitude', 'geodir_longitude' ), '0' );

			$featured = in_array(
				strtolower( $this->pick( $listing, array( 'featured', 'is_featured' ), '0' ) ),
				array( '1', 'yes', 'true', 'on' ),
				true
			);

			$photos = $this->collection_photos( $post_id, $listing );
			$cats   = $this->collection_categories( $post_id, $listing );

			$reviews = isset( $review_rows[ $post_id ] ) ? $review_rows[ $post_id ] : array();

			$rows[] = array(
				'post_id'      => $post_id,
				'owner_id'     => $owner_id,
				'title'        => $title ?: 'Untitled Listing',
				'slug'         => sanitize_title( $title ?: 'untitled' ),
				'status'       => $this->map_status( $status_raw ),
				'email'        => sanitize_email( $email ),
				'phone'        => sanitize_text_field( $phone ),
				'website'      => esc_url_raw( $website ),
				'whatsapp'     => '',
				'address'      => sanitize_textarea_field( $address ),
				'city'         => sanitize_text_field( $city ),
				'state'        => sanitize_text_field( $state ),
				'country'      => sanitize_text_field( $country ),
				'zip'          => sanitize_text_field( $zip ),
				'lat'          => $lat,
				'lng'          => $lng,
				'description'  => $description,
				'featured'     => $featured,
				'date_created' => $created,
				'categories'   => $cats,
				'photos'       => $photos,
				'reviews'      => $reviews,
				'followers'    => array(),
			);
		}

		if ( empty( $rows ) ) {
			$warnings[] = 'No live listings were found in the GeoDirectory detail table.';
		}

		return $this->collection( $warnings, $rows );
	}

	/**
	 * Candidate detail tables, newest naming scheme first.
	 *
	 * @return string[]
	 */
	private function detail_tables(): array {
		global $wpdb;

		$candidates = array(
			$wpdb->prefix . 'geodir_gd_place_detail',
			$wpdb->prefix . 'wp_geodirectory',
		);

		foreach ( $this->table_names( $wpdb->prefix . self::DETAIL_LIKE ) as $name ) {
			if ( strpos( $name, 'geodir_gd_place_detail' ) !== false || strpos( $name, 'geodir_post_review' ) !== false || strpos( $name, 'geodir_reviews' ) !== false ) {
				continue;
			}

			if ( ! in_array( $name, $candidates, true ) ) {
				$candidates[] = $name;
			}
		}

		return $candidates;
	}

	/**
	 * Photo entries from the detail row + post attachments.
	 *
	 * @return array<int, array{src: int|string, caption: string, cover: bool}>
	 * @param int   $post_id Post id.
	 * @param mixed $listing Listing.
	 */
	private function collection_photos( int $post_id, $listing ): array {
		$photos = array();

		$raw = $this->pick( $listing, array( 'post_images', 'images', 'image' ), '' );

		if ( '' !== $raw ) {
			foreach ( $this->parse_photos( $raw ) as $i => $src ) {
				$photos[] = array(
					'src'     => $src,
					'caption' => '',
					'cover'   => 0 === $i,
				);
			}
		}

		if ( empty( $photos ) ) {
			$photos = $this->attached_photos( $post_id );
		}

		// GD serializes full-size image URLs; drop size suffixes when known.
		foreach ( $photos as &$photo ) {
			if ( is_string( $photo['src'] ) && preg_match( '/-\d{2,4}x\d{2,4}(?=\.(?:jpg|jpeg|png|webp)(?:$|\?))/i', $photo['src'] ) ) {
				$photo['src'] = preg_replace( '/-\d{2,4}x\d{2,4}(?=\.(?:jpg|jpeg|png|webp)(?:$|\?))/i', '', $photo['src'] );
			}
		}
		unset( $photo );

		return $photos;
	}

	/**
	 * Category names from post_category CSV of term IDs.
	 *
	 * @return string[]
	 * @param int   $post_id Post id.
	 * @param mixed $listing Listing.
	 */
	private function collection_categories( int $post_id, $listing ): array {
		$raw = $this->pick( $listing, array( 'post_category', 'default_category' ), '' );

		$names = array();

		if ( '' !== $raw ) {
			$names = $this->term_names_from_ids( $raw );
		}

		if ( empty( $names ) ) {
			$names = $this->term_names( $post_id, array( 'gd_placecategory', 'gd_place_category' ) );
		}

		return $names;
	}

	/**
	 * Reviews keyed by listing post_id, one entry per review.
	 *
	 * @return array<int, array<int, array{user_id: int, rating: int, title: string, content: string, status: string, date_created: string}>>
	 */
	private function load_reviews(): array {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'geodir_post_review',
			$wpdb->prefix . 'geodir_reviews',
		);

		$table = '';

		foreach ( $tables as $candidate ) {
			if ( $this->table_exists( $candidate ) ) {
				$table = $candidate;
				break;
			}
		}

		if ( '' === $table ) {
			return array();
		}

		$columns = $this->columns_of( $table );

		if ( empty( $columns ) || ! in_array( 'post_id', $columns, true ) ) {
			return array();
		}

		$sql_cols = implode( ', ', array_intersect( $columns, array( 'post_id', 'review_id', 'post_title' ) ) );
		$sql_cols = $sql_cols ?: 'post_id';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT {$sql_cols} FROM {$table} ORDER BY review_id ASC" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		$title_col     = in_array( 'post_title', $columns, true ) ? 'post_title' : '';
		$reviews_by_id = array();

		foreach ( $rows as $row ) {
			$post_id = (int) $row->post_id;
			$review  = array();

			if ( in_array( 'review_id', $columns, true ) ) {
				$extra = $this->review_detail( $table, (int) $row->review_id );

				if ( empty( $extra ) ) {
					continue;
				}

				$review = $extra;
			} else {
				$review = $this->review_from_row( $row );
			}

			if ( empty( $review ) ) {
				continue;
			}

			if ( $title_col && isset( $row->{$title_col} ) && '' !== trim( (string) $row->{$title_col} ) ) {
				$review['title'] = (string) $row->{$title_col};
			}

			$reviews_by_id[ $post_id ][] = $review;
		}

		return $reviews_by_id;
	}

	/**
	 * Full review row for a single review_id.
	 *
	 * @return array{user_id: int, rating: int, title: string, content: string, status: string, date_created: string}
	 * @param string $table Table.
	 * @param int    $review_id Review id.
	 */
	private function review_detail( string $table, int $review_id ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE review_id = %d", $review_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return $row ? $this->review_from_row( $row ) : array();
	}

	/**
	 * Build a normalized review from an introspected review row.
	 *
	 * @return array{user_id: int, rating: int, title: string, content: string, status: string, date_created: string}
	 * @param mixed $row Row.
	 */
	private function review_from_row( $row ): array {
		$rating = (int) $this->pick( $row, array( 'rating', 'comment_rating', 'post_rating', 'star_rating' ), '0' );

		if ( $rating < 1 || $rating > 5 ) {
			return array();
		}

		$status_raw = strtolower( $this->pick( $row, array( 'post_status', 'status' ), 'approved' ) );

		return array(
			'user_id'      => (int) $this->pick( $row, array( 'user_id', 'post_author', 'author' ), '0' ),
			'rating'       => $rating,
			'title'        => '',
			'content'      => $this->pick( $row, array( 'comment_content', 'review', 'post_content', 'content' ) ),
			'status'       => in_array( $status_raw, array( 'approved', 'publish', '1', 'active' ), true ) ? 'approved' : 'pending',
			'date_created' => $this->pick( $row, array( 'comment_date', 'post_date', 'date_created', 'date' ), current_time( 'mysql' ) ),
		);
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
