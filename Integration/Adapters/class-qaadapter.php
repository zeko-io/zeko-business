<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Integration\Adapters;

defined( 'ABSPATH' ) || exit;

/** Class QAAdapter. */
class QAAdapter {
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
		$cached = get_transient( 'zbp_adapter_qa_active' );
		if ( false !== $cached ) {
			$this->active = (bool) $cached;
			return;
		}
		$this->active = class_exists( 'Zeko_QA' ) || defined( 'ZEKO_QA_VERSION' );
		set_transient( 'zbp_adapter_qa_active', $this->active ? 1 : 0, 24 * HOUR_IN_SECONDS );
	}

	/**
	 * Active.
	 */
	public function is_active(): bool {
		return $this->active;
	}

	/**
	 * Business questions.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_business_questions( int $business_id, int $limit = 20 ): array {
		if ( ! $this->active ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'zeko_questions';
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return array();
		}

        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$biz = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT owner_id FROM {$wpdb->prefix}zbp_businesses WHERE id = %d",
				$business_id
			)
		);
		if ( ! $biz ) {
			return array();
		}

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
				$biz->owner_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Count business questions.
	 *
	 * @param int $business_id Business id.
	 */
	public function count_business_questions( int $business_id ): int {
		if ( ! $this->active ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'zeko_questions';
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return 0;
		}

        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$biz = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT owner_id FROM {$wpdb->prefix}zbp_businesses WHERE id = %d",
				$business_id
			)
		);
		if ( ! $biz ) {
			return 0;
		}

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
				$biz->owner_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}
}
