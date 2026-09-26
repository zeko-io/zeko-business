<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * HivePress (hivepress.io) — key "hivepress".
 *
 * Listings are posts of type `hp_listing`. Built-in fields alias to native
 * post columns (title/description/status/author/date), categories use the
 * `hp_listing_category` taxonomy, the cover image is `_thumbnail_id` and the
 * gallery is the set of image attachments whose `post_parent` points at the
 * listing. Featured/verified sit in `hp_*` post meta (or the HivePress
 * metastore table when present).
 */
final class HivePressSource implements SourceInterface {

	use SourceHelpers;

	private const CPT      = 'hp_listing';
	private const TAXONOMY = 'hp_listing_category';

	/**
	 * Key.
	 */
	public function key(): string {
		return 'hivepress';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'HivePress';
	}

	/**
	 * Version.
	 */
	public function version(): string {
		$plugins = (array) get_option( 'active_plugins', array() );

		foreach ( $plugins as $path ) {
			if ( ! is_string( $path ) || false === strpos( $path, 'hivepress' ) ) {
				continue;
			}

			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $path, false, false );

			if ( ! empty( $data['Version'] ) ) {
				return (string) $data['Version'];
			}
		}

		return 'unknown';
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
		return class_exists( 'HivePress' ) ? 'active' : ( $this->present() ? 'inactive' : 'unknown' );
	}

	/**
	 * Collect.
	 */
	public function collect(): array {
		global $wpdb;

		$warnings = array();

		if ( ! $this->post_type_exists( self::CPT ) ) {
			$warnings[] = 'The hp_listing post type was not found.';
			return $this->collection( $warnings );
		}

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','pending','future','draft','private') ORDER BY ID ASC", self::CPT ) );

		if ( empty( $posts ) ) {
			$warnings[] = 'No live HivePress listings were found.';
			return $this->collection( $warnings );
		}

		$rows = array();

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;

			$rows[] = array(
				'post_id'      => $post_id,
				'owner_id'     => (int) $post->post_author,
				'title'        => (string) $post->post_title,
				'slug'         => sanitize_title( $post->post_title ),
				'status'       => $this->map_status( (string) $post->post_status ),
				'email'        => sanitize_email( $this->meta_value( $post_id, array( 'hp_email', '_hp_email' ) ) ),
				'phone'        => sanitize_text_field( $this->meta_value( $post_id, array( 'hp_phone', '_hp_phone' ) ) ),
				'website'      => esc_url_raw( $this->meta_value( $post_id, array( 'hp_website', '_hp_website' ) ) ),
				'whatsapp'     => '',
				'address'      => sanitize_textarea_field( $this->meta_value( $post_id, array( 'hp_address', '_hp_address' ) ) ),
				'city'         => sanitize_text_field( $this->meta_value( $post_id, array( 'hp_city', '_hp_city' ) ) ),
				'state'        => '',
				'country'      => '',
				'zip'          => sanitize_text_field( $this->meta_value( $post_id, array( 'hp_zip', '_hp_zip' ) ) ),
				'lat'          => (float) $this->meta_value( $post_id, array( 'hp_latitude', '_hp_latitude' ), '0' ),
				'lng'          => (float) $this->meta_value( $post_id, array( 'hp_longitude', '_hp_longitude' ), '0' ),
				'description'  => (string) $post->post_content,
				'featured'     => $this->flag( $post_id, 'featured' ),
				'is_verified'  => $this->flag( $post_id, 'verified' ),
				'date_created' => (string) $post->post_date,
				'categories'   => $this->term_names( $post_id, array( self::TAXONOMY ) ),
				'photos'       => $this->attached_photos( $post_id ),
				'reviews'      => array(),
				'followers'    => array(),
			);
		}

		if ( empty( $rows ) ) {
			$warnings[] = 'No live HivePress listings were found.';
		}

		return $this->collection( $warnings, $rows );
	}

	/**
	 * Boolean flag from post meta or the HivePress metastore table.
	 *
	 * @param int    $post_id Post id.
	 * @param string $name Name.
	 */
	private function flag( int $post_id, string $name ): bool {
		$key = 'hp_' . $name;

		$value = $this->meta_value( $post_id, array( $key, '_' . $key ) );

		if ( '' !== $value ) {
			return in_array( strtolower( $value ), array( '1', 'yes', 'true', 'on' ), true );
		}

		global $wpdb;

		$metastores = array(
			$wpdb->prefix . 'hp_postmeta',
			$wpdb->prefix . 'hp_metastore',
		);

		foreach ( $metastores as $table ) {
			if ( ! $this->table_exists( $table ) ) {
				continue;
			}

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$found = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post_id, $key ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

			if ( null !== $found ) {
				return in_array( strtolower( (string) $found ), array( '1', 'yes', 'true', 'on' ), true );
			}
		}

		return false;
	}

	/**
	 * Capabilities.
	 */
	public function capabilities(): string {
		return 'Listings, categories, photos, featured/verified flags';
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
