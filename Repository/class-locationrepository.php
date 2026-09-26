<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\LocationRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\Location;

defined( 'ABSPATH' ) || exit;

/** Class LocationRepository. */
class LocationRepository implements LocationRepositoryInterface {

	/**
	 * For business.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_for_business( int $business_id ): array {
		if ( $business_id < 1 ) {
			return array();
		}

		$key    = 'loc/' . $business_id;
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE business_id = %d
				 ORDER BY is_primary DESC, sort_order ASC, id ASC",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$locations = empty( $rows ) ? array() : array_map( array( Location::class, 'from_row' ), $rows );

		Cache::set( $key, $locations );

		return $locations;
	}

	/**
	 * Get.
	 *
	 * @param int $location_id Location id.
	 * @param int $business_id Business id.
	 */
	public function get( int $location_id, int $business_id = 0 ): ?Location {
		if ( $location_id < 1 ) {
			return null;
		}

		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $business_id > 0 ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d AND business_id = %d",
					$location_id,
					$business_id
				)
			);
		} else {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d",
					$location_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		return $row instanceof \stdClass ? Location::from_row( $row ) : null;
	}

	/**
	 * Primary for business.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_primary_for_business( int $business_id ): ?Location {
		if ( $business_id < 1 ) {
			return null;
		}

		foreach ( $this->get_for_business( $business_id ) as $location ) {
			if ( $location->is_primary ) {
				return $location;
			}
		}

		return $this->get_for_business( $business_id )[0] ?? null;
	}

	/**
	 * Add.
	 *
	 * @param int   $business_id Business id.
	 * @param array $data Data.
	 */
	public function add( int $business_id, array $data ): int {
		global $wpdb;

		if ( $business_id < 1 ) {
			return 0;
		}

		$fields = $this->normalize( $data, $business_id );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( self::table(), $fields, $this->formats( $fields ) );

		if ( false === $inserted ) {
			return 0;
		}

		$this->after_write( $business_id );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update.
	 *
	 * @param int   $location_id Location id.
	 * @param array $data Data.
	 */
	public function update( int $location_id, array $data ): bool {
		global $wpdb;

		if ( $location_id < 1 ) {
			return false;
		}

		$current = $this->get( $location_id );

		if ( ! $current ) {
			return false;
		}

		$fields   = $this->normalize( $data, $current->business_id );
		$business = $current->business_id;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update( self::table(), $fields, array( 'id' => $location_id ), $this->formats( $fields ), array( '%d' ) );

		if ( false !== $updated ) {
			$this->after_write( $business );
			return true;
		}

		return false;
	}

	/**
	 * Delete.
	 *
	 * @param int $location_id Location id.
	 * @param int $business_id Business id.
	 */
	public function delete( int $location_id, int $business_id = 0 ): bool {
		global $wpdb;

		if ( $location_id < 1 ) {
			return false;
		}

		$where        = array( 'id' => $location_id );
		$where_format = array( '%d' );

		if ( $business_id > 0 ) {
			$where['business_id'] = $business_id;
			$where_format[]       = '%d';
		}

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete( self::table(), $where, $where_format );

		if ( $deleted ) {
			$this->after_write( $business_id ? $business_id : $this->business_for( $location_id ) );
		}

		return (bool) $deleted;
	}

	/**
	 * Delete for business.
	 *
	 * @param int $business_id Business id.
	 */
	public function delete_for_business( int $business_id ): bool {
		global $wpdb;

		if ( $business_id < 1 ) {
			return false;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$this->table()} WHERE business_id = %d", $business_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( false !== $deleted ) {
			$this->after_write( $business_id );
		}

		return false !== $deleted;
	}

	/**
	 * Whitelist a payload down to writable columns, defaulting sensible values.
	 *
	 * @return array
	 * @param array $data Data.
	 * @param int   $business_id Business id.
	 */
	private function normalize( array $data, int $business_id ): array {
		$picks = array(
			'label'      => 'label',
			'is_primary' => 'is_primary',
			'phone'      => 'phone',
			'email'      => 'email',
			'address'    => 'address',
			'city'       => 'city',
			'state'      => 'state',
			'country'    => 'country',
			'zip'        => 'zip',
			'lat'        => 'lat',
			'lng'        => 'lng',
			'extra_data' => 'extra_data',
			'sort_order' => 'sort_order',
		);

		$fields = array( 'business_id' => $business_id );

		foreach ( $picks as $in => $column ) {
			if ( array_key_exists( $in, $data ) && null !== $data[ $in ] ) {
				$fields[ $column ] = $data[ $in ];
			}
		}

		if ( isset( $fields['is_primary'] ) ) {
			$fields['is_primary'] = $fields['is_primary'] ? 1 : 0;
		}

		if ( isset( $fields['extra_data'] ) && is_array( $fields['extra_data'] ) ) {
			$fields['extra_data'] = wp_json_encode( $fields['extra_data'] );
		}

		return $fields;
	}

	/**
	 * Build $wpdb->insert/update format array matching the normalized fields.
	 *
	 * @return string[]
	 * @param array $fields Fields.
	 */
	private function formats( array $fields ): array {
		$formats = array();

		foreach ( array_keys( $fields ) as $column ) {
			switch ( $column ) {
				case 'business_id':
				case 'is_primary':
				case 'sort_order':
					$formats[ $column ] = '%d';
					break;
				case 'lat':
				case 'lng':
					$formats[ $column ] = '%f';
					break;
				default:
					$formats[ $column ] = '%s';
			}
		}

		return $formats;
	}

	/**
	 * Business for.
	 *
	 * @param int $location_id Location id.
	 */
	private function business_for( int $location_id ): int {
		global $wpdb;

		if ( $location_id < 1 ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT business_id FROM {$this->table()} WHERE id = %d",
				$location_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * After write.
	 *
	 * @param int $business_id Business id.
	 */
	private function after_write( int $business_id ): void {
		if ( $business_id > 0 ) {
			Cache::delete( 'loc/' . $business_id );
		}
	}

	/**
	 * Table.
	 */
	private static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'zbp_locations';
	}
}
