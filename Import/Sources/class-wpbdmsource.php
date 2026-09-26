<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Business Directory Plugin (businessdirectoryplugin.com) — key "wpbm".
 *
 * Listings are posts of type `wpbdp_listing`. Field values live in post meta
 * keys `_wpbdp[fields][{field_id}]` where the association (email/phone/
 * website/address/city/state/zip) is declared in the `wpbdp_form_fields`
 * table. Images are attachment IDs in `_wpbdp[images]` with a thumbnail in
 * `_thumbnail_id`; categories come from the `wpbdp_category` taxonomy.
 */
final class WpbdmSource implements SourceInterface {

	use SourceHelpers;

	private const CPT        = 'wpbdp_listing';
	private const TAXONOMY   = 'wpbdp_category';
	private const LIST_TABLE = 'wpbdp_listings';

	/**
	 * Key.
	 */
	public function key(): string {
		return 'wpbm';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Business Directory Plugin';
	}

	/**
	 * Version.
	 */
	public function version(): string {
		return (string) get_option( 'wpbdp_db_version', 'unknown' );
	}

	/**
	 * Present.
	 */
	public function present(): bool {
		global $wpdb;

		if ( class_exists( 'WPBDP' ) || class_exists( 'WPBDP_Listing' ) || get_option( 'wpbdp_settings' ) ) {
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
		if ( class_exists( 'WPBDP' ) || class_exists( 'WPBDP_Listing' ) ) {
			return 'active';
		}

		return $this->present() ? 'inactive' : 'unknown';
	}

	/**
	 * Collect.
	 */
	public function collect(): array {
		global $wpdb;

		$warnings = array();
		$rows     = array();

		$table = $wpdb->prefix . self::LIST_TABLE;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $this->table_exists( $table ) ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$listings = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY listing_id ASC" );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$post_ids = array();
			$seen     = array();

			foreach ( (array) $listings as $listing ) {
				$post_id = (int) $this->pick( $listing, array( 'listing_id', 'post_id', 'ID' ) );

				if ( $post_id < 1 || isset( $seen[ $post_id ] ) ) {
					continue;
				}

				$seen[ $post_id ] = 1;
				$post_ids[]       = $post_id;
			}
		} else {
			$post_ids = $this->cpt_post_ids( self::CPT );
		}

		if ( empty( $post_ids ) ) {
			$warnings[] = 'No live listings were found for this source.';
			return $this->collection( $warnings );
		}

		$assoc_fields = $this->association_map();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				$title       = (string) $post->post_title;
				$description = (string) ( $post->post_excerpt ? $post->post_excerpt . "\n\n" . $post->post_content : $post->post_content );
				$status      = $this->map_status( (string) $post->post_status );
				$owner_id    = (int) $post->post_author;
				$created     = (string) $post->post_date;
			} else {
				$title       = 'Untitled Listing';
				$description = '';
				$status      = 'pending';
				$owner_id    = 0;
				$created     = current_time( 'mysql' );
			}

			$field = function ( string $association ) use ( $post_id, $assoc_fields ): string {
				if ( isset( $assoc_fields[ $association ] ) ) {
					$value = get_post_meta( $post_id, '_wpbdp[fields][' . $assoc_fields[ $association ] . ']', true );
					if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
						return (string) $value;
					}
				}
				return '';
			};

			$email   = $field( 'email' ) ?: $this->meta_value( $post_id, array( '_email', '_wpbdp[fields][email]' ) );
			$phone   = $field( 'phone' ) ?: $this->meta_value( $post_id, array( '_phone' ) );
			$website = $field( 'website' ) ?: $this->meta_value( $post_id, array( '_website' ) );
			$address = $field( 'address' ) ?: $this->meta_value( $post_id, array( '_address' ) );
			$city    = $field( 'city' ) ?: $this->meta_value( $post_id, array( '_city' ) );
			$state   = $field( 'state' ) ?: $this->meta_value( $post_id, array( '_state' ) );
			$zip     = $field( 'zip' ) ?: $this->meta_value( $post_id, array( '_zip' ) );

			$photos = array();

			$image_ids = get_post_meta( $post_id, '_wpbdp[images]', true );
			$image_ids = is_array( $image_ids ) ? $image_ids : array( $image_ids );

			$image_ids = array_values( array_filter( array_map( 'absint', $image_ids ) ) );

			foreach ( $image_ids as $i => $image_id ) {
				if ( ! wp_attachment_is_image( $image_id ) ) {
					continue;
				}

				$photos[] = array(
					'src'     => (int) $image_id,
					'caption' => (string) get_post_meta( $image_id, '_wpbdp_image_caption', true ),
					'cover'   => 0 === $i,
				);
			}

			$thumb = get_post_meta( $post_id, '_thumbnail_id', true );

			if ( ! $thumb ) {
				$thumb = get_post_meta( $post_id, '_wpbdp[thumbnail_id]', true );
			}

			$thumb = absint( $thumb );

			if ( $thumb && wp_attachment_is_image( $thumb ) && ! in_array( $thumb, array_map( static fn( $p ) => (int) $p['src'], $photos ), true ) ) {
				array_unshift(
					$photos,
					array(
						'src'     => $thumb,
						'caption' => '',
						'cover'   => true,
					)
				);
			}

			$rows[] = array(
				'post_id'      => $post_id,
				'owner_id'     => $owner_id ?: 0,
				'title'        => $title,
				'slug'         => sanitize_title( $title ),
				'status'       => $status,
				'email'        => sanitize_email( $email ),
				'phone'        => sanitize_text_field( $phone ),
				'website'      => esc_url_raw( $website ),
				'whatsapp'     => '',
				'address'      => sanitize_textarea_field( $address ),
				'city'         => sanitize_text_field( $city ),
				'state'        => sanitize_text_field( $state ),
				'country'      => '',
				'zip'          => sanitize_text_field( $zip ),
				'lat'          => 0.0,
				'lng'          => 0.0,
				'description'  => $description,
				'featured'     => false,
				'date_created' => $created,
				'categories'   => $this->term_names( $post_id, array( self::TAXONOMY ) ),
				'photos'       => $photos,
				'reviews'      => array(),
				'followers'    => array(),
			);
		}

		if ( empty( $rows ) ) {
			$warnings[] = 'No live listings were found for this source.';
		}

		return $this->collection( $warnings, $rows );
	}

	/**
	 * Post IDs for the listing CPT, newest first.
	 *
	 * @return int[]
	 * @param string $post_type Post type.
	 */
	private function cpt_post_ids( string $post_type ): array {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','pending','future','draft','private') ORDER BY ID ASC", $post_type ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Map field association => wpbdp_form_fields.id.
	 *
	 * @return array<string, int>
	 */
	private function association_map(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpbdp_form_fields';

		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT id, association FROM {$table}", OBJECT_K );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$fields = array();

		foreach ( (array) $rows as $id => $row ) {
			$association = isset( $row->association ) ? (string) $row->association : '';

			if ( in_array( $association, array( 'email', 'phone', 'website', 'address', 'city', 'state', 'zip' ), true ) ) {
				$fields[ $association ] = (int) $id;
			}
		}

		return $fields;
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
