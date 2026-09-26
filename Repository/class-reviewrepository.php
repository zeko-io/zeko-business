<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\ReviewRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\Review;

defined( 'ABSPATH' ) || exit;

/** Class ReviewRepository. */
class ReviewRepository implements ReviewRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_reviews';

	private const FORMAT = array(
		'business_id'          => '%d',
		'user_id'              => '%d',
		'order_id'             => '%d',
		'rating'               => '%d',
		'title'                => '%s',
		'content'              => '%s',
		'criteria'             => '%s',
		'status'               => '%s',
		'is_verified_purchase' => '%d',
		'admin_reply'          => '%s',
		'photos'               => '%s',
		'helpful_count'        => '%d',
	);

	/**
	 * Construct.
	 */
	public function __construct() {}

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?Review {
		global $wpdb;

		if ( $id < 1 ) {
			return null;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return null === $row ? null : Review::from_row( $row );
	}

	/**
	 * By business.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_by_business( int $business_id, int $limit = 20 ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$limit = max( 1, $limit );

		$key    = 'rev/biz/' . $business_id . '/' . $limit;
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE business_id = %d ORDER BY date_created DESC, id DESC LIMIT %d",
				$business_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$reviews = empty( $rows ) ? array() : array_map( array( Review::class, 'from_row' ), $rows );

		Cache::set( $key, $reviews );

		return $reviews;
	}

	/**
	 * Recent.
	 *
	 * @param int $limit Limit.
	 */
	public function get_recent( int $limit = 5 ): array {
		global $wpdb;

		$limit = max( 1, $limit );

		$key    = 'rev/recent/' . $limit;
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY date_created DESC, id DESC LIMIT %d",
				'approved',
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$reviews = empty( $rows ) ? array() : array_map( array( Review::class, 'from_row' ), $rows );

		Cache::set( $key, $reviews );

		return $reviews;
	}

	/**
	 * By user.
	 *
	 * @param int $user_id User id.
	 */
	public function get_by_user( int $user_id ): array {
		global $wpdb;

		if ( $user_id < 1 ) {
			return array();
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY date_created DESC, id DESC",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( Review::class, 'from_row' ), $rows );
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Review {
		global $wpdb;

		[ $columns, $marks, $values ] = self::build_write_sets( $data );

		if ( empty( $columns ) ) {
			return new Review();
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $inserted || (int) $wpdb->insert_id < 1 ) {
			return new Review();
		}

		Cache::flush();

		return $this->get( (int) $wpdb->insert_id );
	}

	/**
	 * Update.
	 *
	 * @param int   $id Id.
	 * @param array $data Data.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		if ( $id < 1 ) {
			return false;
		}

		$set    = array();
		$values = array();

		foreach ( self::FORMAT as $column => $placeholder ) {
			if ( ! array_key_exists( $column, $data ) ) {
				continue;
			}

			$value = self::normalize( $column, $data[ $column ] );

			if ( null === $value ) {
				$set[] = "`{$column}` = NULL";
				continue;
			}

			$set[]    = "`{$column}` = {$placeholder}";
			$values[] = $value;
		}

		if ( empty( $set ) ) {
			return false;
		}

		$values[] = $id;

		$table = self::table();
		$sql   = "UPDATE {$table} SET " . implode( ', ', $set ) . ' WHERE id = %d';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = (bool) $wpdb->query( $wpdb->prepare( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $updated ) {
			Cache::flush();
		}

		return $updated;
	}

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		if ( $id < 1 ) {
			return false;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = (bool) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id = %d",
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $deleted ) {
			Cache::flush();
		}

		return $deleted;
	}

	/**
	 * Approve.
	 *
	 * @param int $id Id.
	 */
	public function approve( int $id ): bool {
		return $this->set_status( $id, 'approved' );
	}

	/**
	 * Reject.
	 *
	 * @param int $id Id.
	 */
	public function reject( int $id ): bool {
		return $this->set_status( $id, 'rejected' );
	}

	/**
	 * Count by business.
	 *
	 * @param int    $business_id Business id.
	 * @param string $status Status.
	 */
	public function count_by_business( int $business_id, string $status = 'approved' ): int {
		global $wpdb;

		if ( $business_id < 1 ) {
			return 0;
		}

		$status = sanitize_key( $status );
		$key    = 'rev/cnt/' . $business_id . '/' . $status;
		$cached = Cache::get( $key );

		if ( is_int( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND status = %s",
				$business_id,
				$status
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		Cache::set( $key, $count );

		return $count;
	}

	/**
	 * Count pending.
	 */
	public function count_pending(): int {
		global $wpdb;

		$table = self::table();

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/**
	 * Count all.
	 */
	public function count_all(): int {
		global $wpdb;

		$key    = 'rev/agg/all';
		$cached = Cache::get( $key );

		if ( is_int( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL

		Cache::set( $key, $count );

		return $count;
	}

	/**
	 * Avg rating.
	 *
	 * @param int $business_id Business id.
	 */
	public function avg_rating( int $business_id ): float {
		global $wpdb;

		if ( $business_id < 1 ) {
			return 0.0;
		}

		$key    = 'rev/avg/' . $business_id;
		$cached = Cache::get( $key );

		if ( is_float( $cached ) || is_int( $cached ) ) {
			return (float) $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$average = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$table} WHERE business_id = %d AND status = 'approved'",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$rating = null === $average ? 0.0 : round( (float) $average, 2 );

		Cache::set( $key, $rating );

		return $rating;
	}

	/**
	 * Rating counts.
	 *
	 * @param int $business_id Business id.
	 */
	public function rating_counts( int $business_id ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array_fill( 1, 5, 0 );
		}

		$key    = 'rev/ratings/' . $business_id;
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rating, COUNT(*) AS cnt FROM {$table} WHERE business_id = %d AND status = 'approved' GROUP BY rating",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$counts = array_fill( 1, 5, 0 );

		foreach ( $rows ?? array() as $row ) {
			$stars = (int) $row->rating;

			if ( $stars >= 1 && $stars <= 5 ) {
				$counts[ $stars ] = (int) $row->cnt;
			}
		}

		Cache::set( $key, $counts );

		return $counts;
	}

	/**
	 * User vote.
	 *
	 * @param int $review_id Review id.
	 * @param int $user_id User id.
	 */
	public function get_user_vote( int $review_id, int $user_id ): int {
		global $wpdb;

		if ( $review_id < 1 || $user_id < 1 ) {
			return 0;
		}

		$table = $wpdb->prefix . 'zbp_review_votes';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$table} WHERE review_id = %d AND user_id = %d",
				$review_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return null === $value ? 0 : (int) $value;
	}

	/**
	 * Vote.
	 *
	 * @param int $review_id Review id.
	 * @param int $user_id User id.
	 * @param int $value Value.
	 */
	public function vote( int $review_id, int $user_id, int $value ): int {
		global $wpdb;

		if ( $review_id < 1 || $user_id < 1 ) {
			return 0;
		}

		$value = ( 1 === $value ) ? 1 : ( ( -1 === $value ) ? -1 : 0 );

		$votes   = $wpdb->prefix . 'zbp_review_votes';
		$reviews = $wpdb->prefix . 'zbp_reviews';

		$existing = $this->get_user_vote( $review_id, $user_id );

		if ( 0 === $value ) {
			// No-op; caller should send 1 or -1.
			return (int) self::count_helper( $reviews, $review_id );
		}

		if ( $existing === $value ) {
			// Toggle off.
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				$votes,
				array(
					'review_id' => $review_id,
					'user_id'   => $user_id,
				),
				array( '%d', '%d' )
			);
		} else {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->replace(
				$votes,
				array(
					'review_id' => $review_id,
					'user_id'   => $user_id,
					'value'     => $value,
				),
				array( '%d', '%d', '%d' )
			);
		}

		$helper = self::count_helper( $reviews, $review_id );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$reviews,
			array( 'helpful_count' => $helper ),
			array( 'id' => $review_id ),
			array( '%d' ),
			array( '%d' )
		);

		Cache::flush();

		return $helper;
	}

	/**
	 * Count helper.
	 *
	 * @param string $reviews Reviews.
	 * @param int    $review_id Review id.
	 */
	private static function count_helper( string $reviews, int $review_id ): int {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}zbp_review_votes WHERE review_id = %d AND value = 1",
				$review_id
			)
		);
	}

	/**
	 * Status.
	 *
	 * @param int    $id Id.
	 * @param string $status Status.
	 */
	private function set_status( int $id, string $status ): bool {
		global $wpdb;

		if ( $id < 1 ) {
			return false;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = (bool) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s WHERE id = %d AND status != %s",
				$status,
				$id,
				$status
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $updated ) {
			Cache::flush();
		}

		return $updated;
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

		if ( 'photos' === $column ) {
			$ids = is_array( $value ) ? array_map( 'absint', array_values( array_filter( $value ) ) ) : array( (int) $value );
			return wp_json_encode( $ids );
		}

		if ( 'criteria' === $column ) {
			if ( is_array( $value ) ) {
				return wp_json_encode( $value );
			}
			return (string) $value;
		}

		$placeholder = self::FORMAT[ $column ];

		if ( '%d' === $placeholder ) {
			return (int) $value;
		}

		return (string) $value;
	}
}
