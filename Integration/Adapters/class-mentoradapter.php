<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Integration\Adapters;

defined( 'ABSPATH' ) || exit;

/** Class MentorAdapter. */
class MentorAdapter {
	/**
	 * Active.
	 *
	 * @var bool Active.
	 */
	private bool $active;

	/**
	 * Construct.
	 */
	public function __construct() {
		$cached = get_transient( 'zbp_adapter_mentor_active' );
		if ( false !== $cached ) {
			$this->active = (bool) $cached;
			return;
		}
		$this->active = class_exists( 'Zeko_Mentor_DB' ) || defined( 'ZEKO_MENTOR_VERSION' );
		set_transient( 'zbp_adapter_mentor_active', $this->active ? 1 : 0, 24 * HOUR_IN_SECONDS );
	}

	/**
	 * Active.
	 */
	public function is_active(): bool {
		return $this->active;
	}

	/**
	 * Business sessions.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_business_sessions( int $business_id, int $limit = 20 ): array {
		if ( ! $this->active ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'zeko_mentor_sessions';
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return array();
		}

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE business_id = %d AND status = 'active' ORDER BY date_created DESC LIMIT %d",
				$business_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}
}
