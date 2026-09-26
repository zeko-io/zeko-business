<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Listdom (webilia.com) — key "listdom".
 *
 * Listings are posts of type `listdom_listing`. Structured data ships as
 * `listdom_*` / `_listdom_*` post meta, categories use the `litem_category`
 * taxonomy, the cover image is `_thumbnail_id` and the gallery is stored in
 * a `listdom_gallery`-style meta key. Reads fall back to column-independent
 * meta candidates so schema name changes across addon versions degrade
 * gracefully.
 */
final class ListdomSource implements SourceInterface {

	use SourceHelpers;

	private const CPT        = 'listdom_listing';
	private const TAXONOMIES = array( 'litem_category', 'listdom_category', 'litem_location' );

	/**
	 * Key.
	 */
	public function key(): string {
		return 'listdom';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Listdom';
	}

	/**
	 * Version.
	 */
	public function version(): string {
		return get_option( 'listdom_version', 'unknown' );
	}

	/**
	 * Present.
	 */
	public function present(): bool {
		return $this->post_type_exists( self::CPT );
	}

	/**
	 * Status.
	 */
	public function status(): string {
		return class_exists( 'Listdom' ) ? 'active' : ( $this->present() ? 'inactive' : 'unknown' );
	}

	/**
	 * Collect.
	 */
	public function collect(): array {
		global $wpdb;

		$warnings = array();

		if ( ! $this->post_type_exists( self::CPT ) ) {
			$warnings[] = 'The listdom_listing post type was not found.';
			return $this->collection( $warnings );
		}

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','pending','future','draft','private') ORDER BY ID ASC", self::CPT ) );

		if ( empty( $posts ) ) {
			$warnings[] = 'No live Listdom listings were found.';
			return $this->collection( $warnings );
		}

		$rows = array();

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;

			$lat = (float) $this->meta_value( $post_id, array( 'listdom_latitude', '_listdom_latitude', 'latitude', '_latitude' ), '0' );
			$lng = (float) $this->meta_value( $post_id, array( 'listdom_longitude', '_listdom_longitude', 'longitude', '_longitude' ), '0' );

			$rows[] = array(
				'post_id'      => $post_id,
				'owner_id'     => (int) $post->post_author,
				'title'        => (string) $post->post_title,
				'slug'         => sanitize_title( $post->post_title ),
				'status'       => $this->map_status( (string) $post->post_status ),
				'email'        => sanitize_email( $this->meta_value( $post_id, array( 'listdom_email', '_listdom_email', 'email', '_email' ) ) ),
				'phone'        => sanitize_text_field( $this->meta_value( $post_id, array( 'listdom_phone', '_listdom_phone', 'phone', '_phone' ) ) ),
				'website'      => esc_url_raw( $this->meta_value( $post_id, array( 'listdom_website', '_listdom_website', 'website', '_website' ) ) ),
				'whatsapp'     => '',
				'address'      => sanitize_textarea_field( $this->meta_value( $post_id, array( 'listdom_address', '_listdom_address', 'address', '_address' ) ) ),
				'city'         => sanitize_text_field( $this->meta_value( $post_id, array( 'listdom_city', '_listdom_city', 'city', '_city' ) ) ),
				'state'        => sanitize_text_field( $this->meta_value( $post_id, array( 'listdom_state', '_listdom_state', 'state', '_state' ) ) ),
				'country'      => sanitize_text_field( $this->meta_value( $post_id, array( 'listdom_country', '_listdom_country', 'country', '_country' ) ) ),
				'zip'          => sanitize_text_field( $this->meta_value( $post_id, array( 'listdom_zip', '_listdom_zip', 'zip', '_zip' ) ) ),
				'lat'          => $lat,
				'lng'          => $lng,
				'description'  => (string) $post->post_content,
				'featured'     => '1' === $this->meta_value( $post_id, array( 'listdom_featured', '_listdom_featured', '_featured' ), '' ),
				'date_created' => (string) $post->post_date,
				'categories'   => $this->term_names( $post_id, self::TAXONOMIES ),
				'photos'       => $this->listdom_photos( $post_id ),
				'reviews'      => array(),
				'followers'    => array(),
			);
		}

		if ( empty( $rows ) ) {
			$warnings[] = 'No live Listdom listings were found.';
		}

		return $this->collection( $warnings, $rows );
	}

	/**
	 * Gallery + cover image.
	 *
	 * @return array<int, array{src: int|string, caption: string, cover: bool}>
	 * @param int $post_id Post id.
	 */
	private function listdom_photos( int $post_id ): array {
		$photos = array();
		$seen   = array();

		$thumb = get_post_thumbnail_id( $post_id );

		if ( $thumb ) {
			$photos[] = array(
				'src'     => (int) $thumb,
				'caption' => '',
				'cover'   => true,
			);
			$seen[]   = (int) $thumb;
		}

		foreach ( array( 'listdom_gallery', '_listdom_gallery', 'gallery', '_gallery', '_listdom_images', 'listdom_images' ) as $key ) {
			$value = $this->meta_value( $post_id, array( $key ) );

			if ( '' === $value ) {
				continue;
			}

			foreach ( $this->parse_photos( $value ) as $src ) {
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

			break;
		}

		if ( empty( $photos ) ) {
			$photos = $this->attached_photos( $post_id );
		}

		return $photos;
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
