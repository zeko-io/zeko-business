<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\AnalyticsRepositoryInterface;
use ZBE\Core\Cache;

defined( 'ABSPATH' ) || exit;

/** Class AnalyticsRepository. */
class AnalyticsRepository implements AnalyticsRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_analytics';

	private const FORMAT = array(
		'business_id' => '%d',
		'user_id'     => '%d',
		'action'      => '%s',
		'ip_address'  => '%s',
		'user_agent'  => '%s',
		'referer'     => '%s',
	);

	/**
	 * Construct.
	 */
	public function __construct() {}

	/**
	 * Views.
	 *
	 * @param int    $business_id Business id.
	 * @param string $from From.
	 * @param string $to To.
	 */
	public function get_views( int $business_id, string $from, string $to ): int {
		global $wpdb;

		if ( $business_id < 1 ) {
			return 0;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND action = 'view' AND date_created BETWEEN %s AND %s",
				$business_id,
				$from,
				$to
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Count unique visitors.
	 *
	 * @param int    $business_id Business id.
	 * @param string $from From.
	 * @param string $to To.
	 */
	public function count_unique_visitors( int $business_id, string $from, string $to ): int {
		global $wpdb;

		if ( $business_id < 1 ) {
			return 0;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_address) FROM {$table} WHERE business_id = %d AND date_created BETWEEN %s AND %s",
				$business_id,
				$from,
				$to
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Top actions.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_top_actions( int $business_id, int $limit = 10 ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT action, COUNT(*) AS total FROM {$table} WHERE business_id = %d GROUP BY action ORDER BY total DESC, action ASC LIMIT %d",
				$business_id,
				max( 1, $limit )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		$results = array();

		foreach ( $rows as $row ) {
			$results[] = array(
				'action' => (string) $row['action'],
				'count'  => (int) $row['total'],
			);
		}

		return $results;
	}

	/**
	 * Top referrers.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_top_referrers( int $business_id, int $limit = 5 ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT referer, COUNT(*) AS total FROM {$table} WHERE business_id = %d AND referer IS NOT NULL AND referer != '' GROUP BY referer ORDER BY total DESC, referer ASC LIMIT %d",
				$business_id,
				max( 1, $limit )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		$results = array();

		foreach ( $rows as $row ) {
			$results[] = array(
				'referer' => (string) $row['referer'],
				'count'   => (int) $row['total'],
			);
		}

		return $results;
	}

	/**
	 * Log.
	 *
	 * @param array $data Data.
	 */
	public function log( array $data ): void {
		global $wpdb;

		[ $columns, $marks, $values ] = self::build_write_sets( $data );

		if ( empty( $columns ) ) {
			return;
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Rollup day.
	 *
	 * @param string $day Day.
	 */
	public function rollup_day( string $day ): int {
		global $wpdb;

		$day = gmdate( 'Y-m-d', strtotime( $day . ' 00:00:00' ) );

		if ( '1970-01-01' === $day ) {
			return 0;
		}

		$raw   = self::table();
		$daily = $wpdb->prefix . 'zbp_analytics_daily';
		$from  = $day . ' 00:00:00';
		$to    = $day . ' 23:59:59';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$daily} ( business_id, day, views, follows, reviews )
				 SELECT business_id,
						%s,
						SUM( action = 'view' ),
						SUM( action = 'follow' ),
						SUM( action = 'review' )
				 FROM {$raw}
				 WHERE date_created BETWEEN %s AND %s
				 GROUP BY business_id
				 ON DUPLICATE KEY UPDATE
					views   = VALUES( views ),
					follows = VALUES( follows ),
					reviews = VALUES( reviews )",
				$day,
				$from,
				$to
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->rows_affected;
	}

	/**
	 * Daily totals.
	 *
	 * @param int $days Days.
	 */
	public function daily_totals( int $days ): array {
		global $wpdb;

		$days = max( 1, min( 365, $days ) );
		$key  = 'ana/daily/' . $days;

		$cached = Cache::get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$daily = $wpdb->prefix . 'zbp_analytics_daily';
		$since = gmdate( 'Y-m-d', strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, SUM(views) AS total FROM {$daily} WHERE day >= %s GROUP BY day ORDER BY day ASC",
				$since
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			// Rollup table not populated yet — fall back to raw events so the.
			// chart works immediately after install.
			$raw = self::table();
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(date_created) AS day, COUNT(*) AS total
					 FROM {$raw}
					 WHERE action = 'view' AND date_created >= DATE_SUB(%s, INTERVAL %d DAY)
					 GROUP BY DATE(date_created) ORDER BY day ASC",
					current_time( 'mysql' ),
					$days
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		$totals = array();

		foreach ( $rows ?? array() as $row ) {
			$totals[ (string) $row->day ] = (int) $row->total;
		}

		Cache::set( $key, $totals );

		return $totals;
	}

	/**
	 * Daily totals for business.
	 *
	 * @param int    $business_id Business id.
	 * @param int    $days Days.
	 * @param string $action Action.
	 */
	public function daily_totals_for_business( int $business_id, int $days, string $action = 'view' ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$days   = max( 1, min( 365, $days ) );
		$action = sanitize_key( $action );
		$key    = 'ana/daily/' . $business_id . '/' . $days . '/' . $action;

		$cached = Cache::get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$daily = $wpdb->prefix . 'zbp_analytics_daily';
		$since = gmdate( 'Y-m-d', strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS );

		if ( in_array( $action, array( 'view', 'follow', 'review' ), true ) ) {
			$column = $action . 's';

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT day, SUM({$column}) AS total FROM {$daily} WHERE business_id = %d AND day >= %s GROUP BY day ORDER BY day ASC",
					$business_id,
					$since
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		} else {
			$rows = array();
		}

		if ( empty( $rows ) ) {
			// Rollup table not populated yet — fall back to raw events.
			$raw = self::table();

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(date_created) AS day, COUNT(*) AS total
					 FROM {$raw}
					 WHERE business_id = %d AND action = %s AND date_created >= DATE_SUB(%s, INTERVAL %d DAY)
					 GROUP BY DATE(date_created) ORDER BY day ASC",
					$business_id,
					$action,
					current_time( 'mysql' ),
					$days
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		$totals = array();

		foreach ( $rows ?? array() as $row ) {
			$totals[ (string) $row->day ] = (int) $row->total;
		}

		Cache::set( $key, $totals );

		return $totals;
	}

	/**
	 * Table.
	 */
	private static function table(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Prepared.
	 *
	 * @param string $sql Sql.
	 * @param array  $values Values.
	 */
	private static function prepared( string $sql, array $values ): string {
		global $wpdb;

		return empty( $values ) ? $sql : $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Build write sets.
	 *
	 * @param array $data Data.
	 */
	private static function build_write_sets( array $data ): array {
		$columns = array();
		$marks   = array();
		$values  = array();

		foreach ( self::FORMAT as $column => $placeholder ) {
			if ( ! array_key_exists( $column, $data ) ) {
				continue;
			}

			$value = self::normalize( $column, $data[ $column ] );

			$columns[] = "`{$column}`";

			if ( null === $value ) {
				$marks[] = 'NULL';
				continue;
			}

			$marks[]  = $placeholder;
			$values[] = $value;
		}

		return array( $columns, $marks, $values );
	}

	/**
	 * Normalize.
	 *
	 * @param string $column Column.
	 * @param mixed  $value Value.
	 */
	private static function normalize( string $column, $value ) {
		if ( null === $value ) {
			return null;
		}

		if ( '%d' === self::FORMAT[ $column ] ) {
			return (int) $value;
		}

		return (string) $value;
	}
}
