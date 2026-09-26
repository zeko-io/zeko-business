<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\HoursRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\BusinessHour;

defined( 'ABSPATH' ) || exit;

/** Class HoursRepository. */
class HoursRepository implements HoursRepositoryInterface {

	/**
	 * For business.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_for_business( int $business_id ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$key    = 'hrs/' . $business_id;
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE business_id = %d ORDER BY day_of_week ASC, id ASC",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$hours = empty( $rows ) ? array() : array_map( array( BusinessHour::class, 'from_row' ), $rows );

		Cache::set( $key, $hours );

		return $hours;
	}

	/**
	 * Replace for business.
	 *
	 * @param int   $business_id Business id.
	 * @param array $rows Rows.
	 */
	public function replace_for_business( int $business_id, array $rows ): bool {
		global $wpdb;

		if ( $business_id < 1 ) {
			return false;
		}

		$normalized = array();

		foreach ( $rows as $row ) {
			$row = (array) $row;

			$dow = (int) ( $row['day_of_week'] ?? -1 );
			if ( $dow < 0 || $dow > 6 ) {
				continue;
			}

			$open  = self::normalize_time( $row['open'] ?? ( $row['open_time'] ?? '' ) );
			$close = self::normalize_time( $row['close'] ?? ( $row['close_time'] ?? '' ) );

			if ( ! empty( $row['closed'] ) ) {
				$normalized[] = array(
					'business_id' => $business_id,
					'day_of_week' => $dow,
					'open_time'   => null,
					'close_time'  => null,
					'is_closed'   => 1,
				);
				continue;
			}

			if ( null === $open || null === $close ) {
				continue;
			}

			$normalized[] = array(
				'business_id' => $business_id,
				'day_of_week' => $dow,
				'open_time'   => $open,
				'close_time'  => $close,
				'is_closed'   => 0,
			);
		}

		if ( empty( $normalized ) ) {
			return false;
		}

		$table = self::table();

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE business_id = %d", $business_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		foreach ( $normalized as $row ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$inserted = $wpdb->insert(
				$table,
				array(
					'business_id' => $row['business_id'],
					'day_of_week' => $row['day_of_week'],
					'open_time'   => $row['open_time'],
					'close_time'  => $row['close_time'],
					'is_closed'   => $row['is_closed'],
				),
				array( '%d', '%d', '%s', '%s', '%d' )
			);

			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return false;
			}
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		Cache::delete( 'hrs/' . $business_id );

		return true;
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
		$deleted = (bool) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$this->table()} WHERE business_id = %d", $business_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $deleted ) {
			Cache::delete( 'hrs/' . $business_id );
		}

		return $deleted;
	}

	/**
	 * Normalize time.
	 *
	 * @param string $value Value.
	 */
	private static function normalize_time( string $value ): ?string {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		$time = date_parse( $value );

		if ( false === $time || ! empty( $time['errors'] ) ) {
			return null;
		}

		return sprintf( '%02d:%02d:00', min( 23, (int) $time['hour'] ), min( 59, (int) $time['minute'] ) );
	}

	/**
	 * Table.
	 */
	private static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'zbp_business_hours';
	}
}
