<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * DB access for the structured business-mention index (zbe_question_mentions)
 * plus read-only, prepared access to the external zeko_questions table.
 *
 * QB questions live in the zeko-qa plugin. The zeko-qa table has no
 * business/mention relation of its own, so this plugin maintains a lightweight
 * index mapping a question_id to a business_id (matched by business name or
 * owner name). The index is populated from the `zeko_qa_question_created` hook
 * fired by zeko-qa's insert_question().
 */
class MentionRepository {

	private const TABLE_SUFFIX = 'zbe_question_mentions';
	private const FAQ_SUFFIX   = 'zeko_questions';

	/**
	 * Construct.
	 */
	public function __construct() {}

	/**
	 * Add a mention mapping, ignoring duplicates (UNIQUE key on
	 * question_id,business_id,matched_on).
	 *
	 * @param int    $question_id Question id.
	 * @param int    $business_id Business id.
	 * @param string $matched_on Matched on.
	 */
	public function add( int $question_id, int $business_id, string $matched_on ): bool {
		global $wpdb;

		if ( $question_id < 1 || $business_id < 1 ) {
			return false;
		}

		$matched_on = in_array( $matched_on, array( 'business', 'owner' ), true ) ? $matched_on : 'business';

		$table = $this->table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (question_id, business_id, matched_on) VALUES (%d, %d, %s)",
				$question_id,
				$business_id,
				$matched_on
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return false !== $inserted;
	}

	/**
	 * Remove all mention mappings for a given question.
	 *
	 * @param int $question_id Question id.
	 */
	public function delete_for_question( int $question_id ): bool {
		global $wpdb;

		if ( $question_id < 1 ) {
			return false;
		}

		$table = $this->table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE question_id = %d",
				$question_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * IDs of questions that mention a given business, optionally restricted to
	 * a match type ('business' = name match, 'owner' = owner name match).
	 *
	 * @return int[]
	 * @param int    $business_id Business id.
	 * @param string $matched_on Matched on.
	 * @param int    $limit Limit.
	 */
	public function get_question_ids_for_business( int $business_id, string $matched_on, int $limit = 50 ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$table = $this->table();
		$sql   = "SELECT question_id FROM {$table} WHERE business_id = %d AND matched_on = %s ORDER BY id DESC LIMIT %d";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare( $sql, $business_id, $matched_on, max( 1, $limit ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * IDs of questions that mention any business owned by the given user.
	 *
	 * @return int[]
	 * @param int $owner_id Owner id.
	 * @param int $limit Limit.
	 */
	public function get_question_ids_for_owner( int $owner_id, int $limit = 50 ): array {
		global $wpdb;

		if ( $owner_id < 1 ) {
			return array();
		}

		$table     = $this->table();
		$biz_table = $wpdb->prefix . 'zbp_businesses';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT m.question_id
				 FROM {$table} m
				 INNER JOIN {$biz_table} b ON b.id = m.business_id
				 WHERE b.owner_id = %d
				 ORDER BY m.id DESC
				 LIMIT %d",
					$owner_id,
					max( 1, $limit )
				)
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Fetch question rows from zeko-qa for the given IDs (safe columns only).
	 *
	 * @return object[]
	 * @param array $ids * @return object[].
	 */
	public function get_questions( array $ids ): array {
		global $wpdb;

		$ids = array_values(
			array_filter(
				array_map( 'absint', $ids ),
				static function ( int $id ): bool {
					return $id > 0;
				}
			)
		);

		if ( empty( $ids ) ) {
			return array();
		}

		$table = $this->faq_table();
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, title, slug, content, views, upvotes, downvotes, created_at
				 FROM {$table}
				 WHERE status = 'open' AND id IN ({$in})
				 ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				$ids
			)
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Fetch a single question row by id (status = open only).
	 *
	 * @param int $question_id Question id.
	 */
	public function get_question( int $question_id ): ?object {
		global $wpdb;

		if ( $question_id < 1 ) {
			return null;
		}

		$table = $this->faq_table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, user_id, title, slug, content, views, upvotes, downvotes, created_at
				 FROM {$table}
				 WHERE status = 'open' AND id = %d",
				$question_id
			)
		) ?: null;
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * New mention rows created after a timestamp, joined to the owning
	 * business (owner_id, name) and the matched question. Used by the daily
	 * owner-digest so each owner is emailed about questions that newly
	 * mention any business they own.
	 * this are returned.
	 * matched_on, question_id, q_title, q_slug,
	 * mention_created, q_created
	 *
	 * @return object[] rows with business_id, business_name, owner_id,
	 * @param int $since_ts Unix timestamp; only mentions created strictly after.
	 */
	public function get_new_questions_since( int $since_ts ): array {
		global $wpdb;

		if ( $since_ts < 1 ) {
			return array();
		}

		$mentions  = $this->table();
		$business  = $wpdb->prefix . 'zbp_businesses';
		$questions = $this->faq_table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.business_id,
				        b.name AS business_name,
				        b.owner_id,
				        m.question_id,
				        m.matched_on,
				        m.created_at AS mention_created,
				        q.title AS q_title,
				        q.slug   AS q_slug,
				        q.created_at AS q_created
				 FROM {$mentions} m
				 INNER JOIN {$business} b ON b.id = m.business_id
				 INNER JOIN {$questions} q ON q.id = m.question_id
				 WHERE m.created_at > %s
				   AND b.owner_id > 0
				   AND q.status = 'open'
				 ORDER BY m.created_at ASC, m.id ASC",
				gmdate( 'Y-m-d H:i:s', $since_ts )
			)
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Active businesses (id, name, owner_id) to scan for mentions. Names are
	 * lowercased here so the service can do a case-insensitive INSTR scan.
	 *
	 * @return object[] rows with id, name_l, owner_id
	 * @param int $limit Limit.
	 */
	public function get_active_businesses( int $limit = 1000 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'zbp_businesses';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, LOWER(name) AS name_l, owner_id
				 FROM {$table}
				 WHERE status = 'active' AND name <> ''
				 ORDER BY id ASC
				 LIMIT %d",
				max( 1, $limit )
			)
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Table.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Faq table.
	 */
	private function faq_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::FAQ_SUFFIX;
	}
}
