<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\BookingSlotRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\BookingSlot;

defined( 'ABSPATH' ) || exit;

/** Class BookingSlotRepository. */
class BookingSlotRepository implements BookingSlotRepositoryInterface {

	/**
	 * For business.
	 *
	 * @param int  $business_id Business id.
	 * @param int  $day_of_week Day of week.
	 * @param bool $active_only Active only.
	 */
	public function get_for_business( int $business_id, int $day_of_week = -1, bool $active_only = true ): array {
		if ( $business_id < 1 ) {
			return array();
		}

		global $wpdb;

		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE business_id = %d";
		$args  = array( $business_id );

		if ( $day_of_week >= 0 && $day_of_week <= 6 ) {
			$sql   .= ' AND day_of_week = %d';
			$args[] = $day_of_week;
		}

		if ( $active_only ) {
			$sql .= ' AND is_active = 1';
		}

		$sql .= ' ORDER BY day_of_week ASC, start_time ASC, id ASC';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return empty( $rows ) ? array() : array_map( array( BookingSlot::class, 'from_row' ), $rows );
	}

	/**
	 * For service.
	 *
	 * @param int $business_id Business id.
	 * @param int $service_id Service id.
	 * @param int $day_of_week Day of week.
	 */
	public function get_for_service( int $business_id, int $service_id, int $day_of_week = -1 ): array {
		if ( $business_id < 1 || $service_id < 1 ) {
			return array();
		}

		global $wpdb;

		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE business_id = %d AND service_id = %d AND is_active = 1";
		$args  = array( $business_id, $service_id );

		if ( $day_of_week >= 0 && $day_of_week <= 6 ) {
			$sql   .= ' AND day_of_week = %d';
			$args[] = $day_of_week;
		}

		$sql .= ' ORDER BY day_of_week ASC, start_time ASC, id ASC';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return empty( $rows ) ? array() : array_map( array( BookingSlot::class, 'from_row' ), $rows );
	}

	/**
	 * Get.
	 *
	 * @param int $slot_id Slot id.
	 */
	public function get( int $slot_id ): ?BookingSlot {
		global $wpdb;

		if ( $slot_id < 1 ) {
			return null;
		}

		$key    = 'bslot/' . $slot_id;
		$cached = Cache::get( $key );

		if ( $cached instanceof BookingSlot ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $slot_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $row instanceof \stdClass ) {
			return null;
		}

		$slot = BookingSlot::from_row( $row );
		Cache::set( $key, $slot );

		return $slot;
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

		$fields   = $this->normalize( $data, $business_id );
		$inserted = $wpdb->insert( self::table(), $fields, $this->formats( $fields ) ); // phpcs:ignore WordPress.DB

		if ( false === $inserted ) {
			return 0;
		}

		$this->after_write( $business_id );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update.
	 *
	 * @param int   $slot_id Slot id.
	 * @param array $data Data.
	 */
	public function update( int $slot_id, array $data ): bool {
		global $wpdb;

		if ( $slot_id < 1 ) {
			return false;
		}

		$current = $this->get( $slot_id );

		if ( ! $current ) {
			return false;
		}

		$fields = $this->normalize( $data, $current->business_id );
		$this->after_write( $current->business_id );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update( self::table(), $fields, array( 'id' => $slot_id ), $this->formats( $fields ), array( '%d' ) );

		Cache::delete( 'bslot/' . $slot_id );

		return false !== $updated;
	}

	/**
	 * Active.
	 *
	 * @param int  $slot_id Slot id.
	 * @param bool $active Active.
	 */
	public function set_active( int $slot_id, bool $active ): bool {
		return $this->update( $slot_id, array( 'is_active' => $active ? 1 : 0 ) );
	}

	/**
	 * Delete.
	 *
	 * @param int $slot_id Slot id.
	 */
	public function delete( int $slot_id ): bool {
		global $wpdb;

		if ( $slot_id < 1 ) {
			return false;
		}

		$current  = $this->get( $slot_id );
		$business = $current ? $current->business_id : 0;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete( self::table(), array( 'id' => $slot_id ), array( '%d' ) );

		if ( $deleted ) {
			Cache::delete( 'bslot/' . $slot_id );
			$this->after_write( $business );
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
	 * Whitelist a payload down to writable columns with sensible defaults.
	 *
	 * @return array
	 * @param array $data Data.
	 * @param int   $business_id Business id.
	 */
	private function normalize( array $data, int $business_id ): array {
		$picks = array(
			'location_id'       => 'location_id',
			'service_id'        => 'service_id',
			'day_of_week'       => 'day_of_week',
			'start_time'        => 'start_time',
			'end_time'          => 'end_time',
			'slot_duration_min' => 'slot_duration_min',
			'max_bookings'      => 'max_bookings',
			'is_active'         => 'is_active',
			'sort_order'        => 'sort_order',
		);

		$fields = array( 'business_id' => $business_id );

		foreach ( $picks as $in => $column ) {
			if ( array_key_exists( $in, $data ) && null !== $data[ $in ] ) {
				$fields[ $column ] = $data[ $in ];
			}
		}

		if ( isset( $fields['day_of_week'] ) ) {
			$dow                   = (int) $fields['day_of_week'];
			$fields['day_of_week'] = ( $dow < 0 || $dow > 6 ) ? 0 : $dow;
		}

		if ( isset( $fields['is_active'] ) ) {
			$fields['is_active'] = $fields['is_active'] ? 1 : 0;
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
		$ints = array( 'business_id', 'location_id', 'service_id', 'day_of_week', 'slot_duration_min', 'max_bookings', 'is_active', 'sort_order' );

		$formats = array();
		foreach ( array_keys( $fields ) as $column ) {
			$formats[ $column ] = in_array( $column, $ints, true ) ? '%d' : '%s';
		}

		return $formats;
	}

	/**
	 * After write.
	 *
	 * @param int $business_id Business id.
	 */
	private function after_write( int $business_id ): void {
		Cache::flush();
	}

	/**
	 * Table.
	 */
	private static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'zbp_booking_slots';
	}
}
