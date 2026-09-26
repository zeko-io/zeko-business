<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

defined( 'ABSPATH' ) || exit;

/** Class AnalyticsRepositoryInterface. */
interface AnalyticsRepositoryInterface {

	/**
	 * Views.
	 *
	 * @param int    $business_id Business id.
	 * @param string $from From.
	 * @param string $to To.
	 */
	public function get_views( int $business_id, string $from, string $to ): int;

	/**
	 * Count unique visitors.
	 *
	 * @param int    $business_id Business id.
	 * @param string $from From.
	 * @param string $to To.
	 */
	public function count_unique_visitors( int $business_id, string $from, string $to ): int;

	/**
	 * Top actions.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_top_actions( int $business_id, int $limit = 10 ): array;

	/**
	 * Top referrers.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_top_referrers( int $business_id, int $limit = 5 ): array;

	/**
	 * Log.
	 *
	 * @param array $data Data.
	 */
	public function log( array $data ): void;

	/**
	 * Aggregate one local day of raw events into the daily rollup table.
	 *
	 * @return int Number of business-day rows written.
	 * @param string $day Local date, Y-m-d.
	 */
	public function rollup_day( string $day ): int;

	/**
	 * Daily view totals across all businesses for charting, preferring the
	 * rollup table and falling back to raw events when it is still empty.
	 *
	 * @return array<string, int> Map of Y-m-d => view count, ascending by day.
	 * @param int $days Lookback window in days.
	 */
	public function daily_totals( int $days ): array;

	/**
	 * Daily event counts for a single business, Y-m-d => count ascending.
	 *
	 * @param int    $business_id Business id.
	 * @param int    $days Days.
	 * @param string $action Action.
	 */
	public function daily_totals_for_business( int $business_id, int $days, string $action = 'view' ): array;
}
