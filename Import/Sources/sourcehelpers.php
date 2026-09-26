<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Shared, defensive helpers for foreign-source adapters.
 *
 * Directory plugins differ wildly in how they store listings (custom tables,
 * CPT + meta, serialized blobs, CSV columns). Rather than trusting one schema,
 * every extraction is best-effort with column/attribute introspection and
 * candidate-name fallbacks so a migration never fatals on an unexpected shape.
 */
trait SourceHelpers {

	/**
	 * Query a full (prefixed) table name for an optional LIKE pattern.
	 *
	 * @return array<int, string>
	 * @param string $like Like.
	 */
	private function table_names( string $like = '' ): array {
		global $wpdb;

		$pattern = $like ? $wpdb->esc_like( $like ) : '%';
		$pattern = str_replace( '\\%', '%', $pattern );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( 'strval', $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) ) );
	}

	/**
	 * Check whether a (prefixed) table exists.
	 *
	 * @param string $table Table.
	 */
	private function table_exists( string $table ): bool {
		return in_array( $table, $this->table_names( $table ), true );
	}

	/**
	 * Column names of a table, or [] when it does not exist.
	 *
	 * @return array<int, string>
	 * @param string $table Table.
	 */
	private function columns_of( string $table ): array {
		global $wpdb;

		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( 'strval', $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * First non-empty property in a row (object or array).
	 *
	 * @param object|array $row * @param string[]     $candidates Ordered property/column names to check.
	 * @param array        $candidates Candidates.
	 * @param string       $default Default.
	 */
	private function pick( $row, array $candidates, string $default = '' ): string {
		foreach ( $candidates as $key ) {
			$value = '';
			if ( is_array( $row ) && isset( $row[ $key ] ) ) {
				$value = $row[ $key ];
			} elseif ( is_object( $row ) && isset( $row->{$key} ) ) {
				$value = $row->{$key};
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			if ( null !== $value && '' !== trim( (string) $value ) ) {
				return is_scalar( $value ) ? (string) $value : $default;
			}
		}

		return $default;
	}

	/**
	 * First non-empty post meta value from a list of candidate keys.
	 *
	 * @param int    $post_id Post id.
	 * @param array  $candidates Candidates.
	 * @param string $default Default.
	 */
	private function meta_value( int $post_id, array $candidates, string $default = '' ): string {
		foreach ( $candidates as $key ) {
			$value = get_post_meta( $post_id, (string) $key, true );
			if ( null !== $value && '' !== trim( (string) $value ) ) {
				return is_scalar( $value ) ? (string) $value : $default;
			}
		}

		return $default;
	}

	/**
	 * True when a post type is registered OR has rows in the posts table.
	 * Handles the "plugin deactivated but data still present" case.
	 *
	 * @param string $post_type Post type.
	 */
	private function post_type_exists( string $post_type ): bool {
		global $wpdb;

		if ( function_exists( 'get_post_type_object' ) && get_post_type_object( $post_type ) ) {
			return true;
		}

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft' LIMIT 1", $post_type ) );

		return null !== $id;
	}

	/**
	 * Whether any customers of the given post type exist.
	 *
	 * @param string $post_type Post type.
	 */
	private function post_type_has_rows( string $post_type ): bool {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft' LIMIT 1", $post_type ) );

		return null !== $id;
	}

	/**
	 * Publish/pending post statuses that map to an active listing.
	 *
	 * @param string $status Status.
	 */
	private function is_live_status( string $status ): bool {
		return in_array( $status, array( 'publish', 'pending', 'future', 'draft', 'private' ), true );
	}

	/**
	 * Map a foreign status string to a Zeko business status.
	 *
	 * @param string $status Status.
	 */
	private function map_status( string $status ): string {
		return 'publish' === $status ? 'active' : 'pending';
	}

	/**
	 * Term names (from any taxonomy) for a post, deduplicated.
	 *
	 * @return string[]
	 * @param int   $post_id Post id.
	 * @param array $taxonomies Taxonomies.
	 */
	private function term_names( int $post_id, array $taxonomies ): array {
		$names = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_the_terms( $post_id, $taxonomy );

			if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( $term instanceof \WP_Term && trim( (string) $term->name ) !== '' ) {
						$names[] = (string) $term->name;
					}
				}
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Term names by term IDs (comma separated), used by table-based sources.
	 *
	 * @return string[]
	 * @param mixed $csv "13,27,29"-style value or array of term IDs.
	 */
	private function term_names_from_ids( $csv ): array {
		if ( is_array( $csv ) ) {
			$ids = $csv;
		} else {
			$ids = array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) );
		}

		$names = array();

		foreach ( $ids as $id ) {
			$id = absint( (string) $id );

			if ( $id < 1 ) {
				continue;
			}

			$term = get_term( $id );

			if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
				$names[] = (string) $term->name;
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Normalize a likely photo blob (single id, URL, CSV, serialized array)
	 * into a flat list of attachment ids/URLs.
	 *
	 * @return array<int, int|string>
	 * @param mixed $value *.
	 */
	private function parse_photos( $value ): array {
		if ( is_scalar( $value ) ) {
			$value = (string) $value;
		}

		$items = array();

		if ( is_array( $value ) ) {
			$items = $value;
		} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
			$unserialized = @unserialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false !== $unserialized ) {
				$items = is_array( $unserialized ) ? $unserialized : array( $unserialized );
			} else {
				$items = array_map( 'trim', array_values( array_filter( explode( ',', $value ) ) ) );
			}
		}

		$photos = array();

		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				if ( isset( $item['id'] ) ) {
					$item = $item['id'];
				} elseif ( isset( $item['src'] ) ) {
					$item = $item['src'];
				} else {
					continue;
				}
			}

			if ( is_numeric( $item ) || ( is_string( $item ) && '' !== trim( $item ) ) ) {
				$item = (string) $item;

				if ( in_array( $item, $photos, true ) ) {
					continue;
				}

				$photos[] = is_numeric( $item ) ? (int) $item : $item;
			}
		}

		return $photos;
	}

	/**
	 * Find attached images/gallery for a post (HivePress/Listdom style).
	 *
	 * @return array<int, array{src: int|string, caption: string, cover: bool}>
	 * @param int   $post_id Post id.
	 * @param array $exclude Exclude.
	 */
	private function attached_photos( int $post_id, array $exclude = array() ): array {
		$thumb = get_post_thumbnail_id( $post_id );
		$media = get_attached_media( 'image', $post_id );

		$photos = array();
		$seen   = array();

		if ( $thumb ) {
			$photos[] = array(
				'src'     => (int) $thumb,
				'caption' => '',
				'cover'   => true,
			);
			$seen[]   = (int) $thumb;
		}

		if ( ! empty( $media ) ) {
			foreach ( $media as $attachment ) {
				$id = (int) $attachment->ID;

				if ( in_array( $id, $seen, true ) || in_array( $id, $exclude, true ) ) {
					continue;
				}

				$photos[] = array(
					'src'     => $id,
					'caption' => (string) $attachment->post_excerpt,
					'cover'   => false,
				);
				$seen[]   = $id;
			}
		}

		return $photos;
	}
}
