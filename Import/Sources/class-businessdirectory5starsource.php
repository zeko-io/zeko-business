<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Business Directory Plugin (by 5 Star Plugins) — key "wpbp".
 *
 * The free plugin predates modern directory plugins and its `wpbp_listing`
 * table layout varies by build (custom-field columns are defined by the
 * admin). Every read is therefore column-introspected and matched by
 * normalized column names, with post-meta fallbacks.
 */
final class BusinessDirectory5StarSource implements SourceInterface {

	use SourceHelpers;

	private const TABLE_CANDIDATES = array( 'wpbp_listing', 'wpbp_listings' );
	private const TAXONOMIES       = array( 'wpbp_category', 'wpbp_categories', 'category' );

	/**
	 * Key.
	 */
	public function key(): string {
		return 'wpbp';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Business Directory Plugin (5 Star)';
	}

	/**
	 * Version.
	 */
	public function version(): string {
		return (string) get_option( 'wpbp_db_version', 'unknown' );
	}

	/**
	 * Present.
	 */
	public function present(): bool {
		global $wpdb;

		if ( get_option( 'wpbp_settings' ) ) {
			return true;
		}

		foreach ( self::TABLE_CANDIDATES as $candidate ) {
			if ( $this->table_exists( $wpdb->prefix . $candidate ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Status.
	 */
	public function status(): string {
		return class_exists( 'WPBP' ) || get_option( 'wpbp_settings' ) ? 'active' : ( $this->present() ? 'inactive' : 'unknown' );
	}

	/**
	 * Collect.
	 */
	public function collect(): array {
		global $wpdb;

		$warnings = array();
		$table    = '';

		foreach ( self::TABLE_CANDIDATES as $candidate ) {
			if ( $this->table_exists( $wpdb->prefix . $candidate ) ) {
				$table = $wpdb->prefix . $candidate;
				break;
			}
		}

		if ( '' === $table ) {
			$warnings[] = 'The wpbp listing table was not found.';
			return $this->collection( $warnings );
		}

		$columns = $this->columns_of( $table );

		if ( empty( $columns ) ) {
			$warnings[] = 'The wpbp listing table has no readable columns.';
			return $this->collection( $warnings );
		}

		$id_column = $this->match_column( $columns, array( 'post_id', 'id', 'listing_id', 'business_id' ) );

		if ( null === $id_column ) {
			$warnings[] = 'No post_id/id column found in the wpbp listing table.';
			return $this->collection( $warnings );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$listings = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY {$id_column} ASC" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $listings ) ) {
			$warnings[] = 'The wpbp listing table contains no rows.';
			return $this->collection( $warnings );
		}

		if ( empty( $warnings ) ) {
			$warnings[] = 'wpbp schema was auto-mapped from column names; verify a sample business after importing.';
		}

		$rows = array();

		foreach ( $listings as $listing ) {
			$post_id = (int) $listing->{$id_column};

			if ( $post_id < 1 ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post && ! $this->is_live_status( (string) $post->post_status ) ) {
				continue;
			}

			$title   = (string) ( $post instanceof \WP_Post ? $post->post_title : $this->pick( $listing, array( 'title', 'name' ), '' ) );
			$content = (string) ( $post instanceof \WP_Post ? $post->post_content : $this->pick( $listing, array( 'description', 'content' ), '' ) );

			$email   = $this->meta_value( $post_id, array( 'wpbp_email', '_wpbp_email', 'email' ) );
			$phone   = $this->meta_value( $post_id, array( 'wpbp_phone', '_wpbp_phone', 'phone' ) );
			$website = $this->meta_value( $post_id, array( 'wpbp_website', '_wpbp_website', 'website', 'web' ) );

			if ( '' === $email ) {
				$email = $this->pick( $listing, $this->find_columns( $columns, 'email' ) );
			}

			if ( '' === $phone ) {
				$phone = $this->pick( $listing, $this->find_columns( $columns, 'phone' ) );
			}

			if ( '' === $website ) {
				$website = $this->pick( $listing, $this->find_columns( $columns, array( 'website', 'web', 'url' ) ) );
			}

			$lat = $this->pick( $listing, $this->find_columns( $columns, 'lat' ) );
			$lng = $this->pick( $listing, $this->find_columns( $columns, array( 'lng', 'lon', 'long' ) ) );

			$status = $post instanceof \WP_Post ? $this->map_status( (string) $post->post_status ) : 'pending';

			$rows[] = array(
				'post_id'      => $post_id,
				'owner_id'     => (int) ( $post instanceof \WP_Post ? $post->post_author : 0 ),
				'title'        => $title ?: 'Untitled Listing',
				'slug'         => sanitize_title( $title ?: 'untitled' ),
				'status'       => $status,
				'email'        => sanitize_email( $email ),
				'phone'        => sanitize_text_field( $phone ),
				'website'      => esc_url_raw( $website ),
				'whatsapp'     => '',
				'address'      => sanitize_textarea_field( $this->pick( $listing, $this->find_columns( $columns, 'address' ) ) ),
				'city'         => sanitize_text_field( $this->pick( $listing, $this->find_columns( $columns, 'city' ) ) ),
				'state'        => sanitize_text_field( $this->pick( $listing, $this->find_columns( $columns, 'state' ) ) ),
				'country'      => sanitize_text_field( $this->pick( $listing, $this->find_columns( $columns, 'country' ) ) ),
				'zip'          => sanitize_text_field( $this->pick( $listing, $this->find_columns( $columns, 'zip' ) ) ),
				'lat'          => (float) $lat,
				'lng'          => (float) $lng,
				'description'  => $content,
				'featured'     => false,
				'date_created' => (string) ( $post instanceof \WP_Post ? $post->post_date : current_time( 'mysql' ) ),
				'categories'   => $this->term_names( $post_id, self::TAXONOMIES ),
				'photos'       => $this->photos( $post_id ),
				'reviews'      => array(),
				'followers'    => array(),
			);
		}

		if ( empty( $rows ) ) {
			$warnings[] = 'No live wpbp listings were found.';
		}

		return $this->collection( $warnings, $rows );
	}

	/**
	 * Photos for a wpbp listing (meta + thumbnail, newest first).
	 *
	 * @return array<int, array{src: int|string, caption: string, cover: bool}>
	 * @param int $post_id Post id.
	 */
	private function photos( int $post_id ): array {
		$photos = array();

		foreach ( array( 'wpbp_photo', '_wpbp_photo', 'photo', '_photo' ) as $key ) {
			$value = $this->meta_value( $post_id, array( $key ) );

			if ( '' !== $value ) {
				foreach ( $this->parse_photos( $value ) as $i => $src ) {
					$photos[] = array(
						'src'     => $src,
						'caption' => '',
						'cover'   => 0 === $i,
					);
				}
				break;
			}
		}

		if ( empty( $photos ) ) {
			$photos = $this->attached_photos( $post_id );
		}

		return $photos;
	}

	/**
	 * Exact-match of one of the candidate column names.
	 *
	 * @param array $columns * @param string[] $candidates.
	 * @param array $candidates Candidates.
	 */
	private function match_column( array $columns, array $candidates ): ?string {
		foreach ( $candidates as $candidate ) {
			if ( in_array( $candidate, $columns, true ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Columns whose normalized name is inside (or equals) any needle.
	 *
	 * @return string[]
	 * @param array $columns * @param string|string[] $needles.
	 * @param mixed $needles Needles.
	 */
	private function find_columns( array $columns, $needles ): array {
		$needles = is_array( $needles ) ? $needles : array( $needles );
		$found   = array();

		foreach ( $columns as $column ) {
			$normalized = strtolower( str_replace( array( '_', '-' ), '', $column ) );

			foreach ( $needles as $needle ) {
				if ( false !== strpos( $normalized, strtolower( $needle ) ) ) {
					$found[] = $column;
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * Capabilities.
	 */
	public function capabilities(): string {
		return 'Listings, categories, photos';
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
