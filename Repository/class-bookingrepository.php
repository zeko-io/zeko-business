<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\BookingRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\Booking;

defined( 'ABSPATH' ) || exit;

/** Class BookingRepository. */
class BookingRepository implements BookingRepositoryInterface {

	/**
	 * Add.
	 *
	 * @param array $data Data.
	 */
	public function add( array $data ): int {
		global $wpdb;

		$business_id = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		if ( $business_id < 1 ) {
			return 0;
		}

		$fields = $this->normalize( $data );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( self::table(), $fields, $this->formats( $fields ) );

		if ( false === $inserted ) {
			return 0;
		}

		$this->after_write( $business_id );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get.
	 *
	 * @param int $booking_id Booking id.
	 */
	public function get( int $booking_id ): ?Booking {
		global $wpdb;

		if ( $booking_id < 1 ) {
			return null;
		}

		$key    = 'bk/' . $booking_id;
		$cached = Cache::get( $key );

		if ( $cached instanceof Booking ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $booking_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $row instanceof \stdClass ) {
			return null;
		}

		$booking = Booking::from_row( $row );
		Cache::set( $key, $booking );

		return $booking;
	}

	/**
	 * For business.
	 *
	 * @param int    $business_id Business id.
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 */
	public function get_for_business( int $business_id, string $status = '', int $limit = 50 ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE business_id = %d";
		$args  = array( $business_id );

		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}

		$sql   .= ' ORDER BY booking_date DESC, start_time DESC, id DESC LIMIT %d';
		$args[] = max( 1, $limit );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return empty( $rows ) ? array() : array_map( array( Booking::class, 'from_row' ), $rows );
	}

	/**
	 * For user.
	 *
	 * @param int    $user_id User id.
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 */
	public function get_for_user( int $user_id, string $status = '', int $limit = 50 ): array {
		global $wpdb;

		if ( $user_id < 1 ) {
			return array();
		}

		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE user_id = %d";
		$args  = array( $user_id );

		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}

		$sql   .= ' ORDER BY booking_date DESC, start_time DESC, id DESC LIMIT %d';
		$args[] = max( 1, $limit );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return empty( $rows ) ? array() : array_map( array( Booking::class, 'from_row' ), $rows );
	}

	/**
	 * Count for slot date.
	 *
	 * @param int    $slot_id Slot id.
	 * @param string $booking_date Booking date.
	 * @param string $status Status.
	 */
	public function count_for_slot_date( int $slot_id, string $booking_date, string $status = 'confirmed' ): int {
		global $wpdb;

		if ( $slot_id < 1 || '' === $booking_date ) {
			return 0;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE slot_id = %d AND booking_date = %s AND status = %s",
				$slot_id,
				$booking_date,
				$status
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Update.
	 *
	 * @param int   $booking_id Booking id.
	 * @param array $data Data.
	 */
	public function update( int $booking_id, array $data ): bool {
		global $wpdb;

		if ( $booking_id < 1 ) {
			return false;
		}

		$current = $this->get( $booking_id );

		if ( ! $current ) {
			return false;
		}

		$picks = array(
			'location_id'   => 'location_id',
			'service_id'    => 'service_id',
			'slot_id'       => 'slot_id',
			'booking_date'  => 'booking_date',
			'start_time'    => 'start_time',
			'end_time'      => 'end_time',
			'status'        => 'status',
			'price'         => 'price',
			'currency'      => 'currency',
			'payment_tx_id' => 'payment_tx_id',
			'notes'         => 'notes',
		);

		$fields = array();
		foreach ( $picks as $in => $column ) {
			if ( array_key_exists( $in, $data ) && null !== $data[ $in ] ) {
				$fields[ $column ] = $data[ $in ];
			}
		}

		if ( empty( $fields ) ) {
			return true;
		}

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update( self::table(), $fields, array( 'id' => $booking_id ), $this->formats( $fields ), array( '%d' ) );

		$this->after_write( $current->business_id );

		return false !== $updated;
	}

	/**
	 * Delete.
	 *
	 * @param int $booking_id Booking id.
	 */
	public function delete( int $booking_id ): bool {
		global $wpdb;

		if ( $booking_id < 1 ) {
			return false;
		}

		$current  = $this->get( $booking_id );
		$business = $current ? $current->business_id : 0;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete( self::table(), array( 'id' => $booking_id ), array( '%d' ) );

		if ( $deleted ) {
			Cache::delete( 'bk/' . $booking_id );
			$this->after_write( $business );
		}

		return (bool) $deleted;
	}

	/**
	 * Normalize an insert payload for a new booking.
	 *
	 * @return array
	 * @param array $data Data.
	 */
	private function normalize( array $data ): array {
		$business_id = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;

		$fields = array(
			'business_id'  => $business_id,
			'location_id'  => isset( $data['location_id'] ) ? (int) $data['location_id'] : 0,
			'service_id'   => isset( $data['service_id'] ) ? (int) $data['service_id'] : 0,
			'user_id'      => isset( $data['user_id'] ) ? (int) $data['user_id'] : 0,
			'slot_id'      => isset( $data['slot_id'] ) ? (int) $data['slot_id'] : 0,
			'booking_date' => isset( $data['booking_date'] ) ? (string) $data['booking_date'] : '',
			'start_time'   => isset( $data['start_time'] ) ? (string) $data['start_time'] : '',
			'end_time'     => isset( $data['end_time'] ) ? (string) $data['end_time'] : '',
			'status'       => isset( $data['status'] ) ? (string) $data['status'] : 'pending',
			'price'        => isset( $data['price'] ) ? (float) $data['price'] : 0.0,
			'currency'     => isset( $data['currency'] ) ? (string) $data['currency'] : 'USD',
		);

		if ( array_key_exists( 'payment_tx_id', $data ) ) {
			$fields['payment_tx_id'] = (string) $data['payment_tx_id'];
		}

		if ( array_key_exists( 'notes', $data ) ) {
			$fields['notes'] = (string) $data['notes'];
		}

		return $fields;
	}

	/**
	 * Formats.
	 *
	 * @return string[]
	 * @param array $fields Fields.
	 */
	private function formats( array $fields ): array {
		$ints   = array( 'business_id', 'location_id', 'service_id', 'user_id', 'slot_id' );
		$floats = array( 'price' );

		$formats = array();
		foreach ( array_keys( $fields ) as $column ) {
			if ( in_array( $column, $ints, true ) ) {
				$formats[ $column ] = '%d';
			} elseif ( in_array( $column, $floats, true ) ) {
				$formats[ $column ] = '%f';
			} else {
				$formats[ $column ] = '%s';
			}
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

		return $wpdb->prefix . 'zbp_bookings';
	}
}
