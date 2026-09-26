<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\FollowerRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\Follower;

defined( 'ABSPATH' ) || exit;

/** Class FollowerRepository. */
class FollowerRepository implements FollowerRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_followers';

	private const FORMAT = array(
		'business_id' => '%d',
		'user_id'     => '%d',
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
	public function get( int $id ): ?Follower {
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

		return null === $row ? null : Follower::from_row( $row );
	}

	/**
	 * Followers.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_followers( int $business_id, int $limit = 50 ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE business_id = %d ORDER BY date_created DESC, id DESC LIMIT %d",
				$business_id,
				max( 1, $limit )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( Follower::class, 'from_row' ), $rows );
	}

	/**
	 * Following.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_following( int $user_id, int $limit = 50 ): array {
		global $wpdb;

		if ( $user_id < 1 ) {
			return array();
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY date_created DESC, id DESC LIMIT %d",
				$user_id,
				max( 1, $limit )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( Follower::class, 'from_row' ), $rows );
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Follower {
		global $wpdb;

		[ $columns, $marks, $values ] = self::build_write_sets( $data );

		if ( empty( $columns ) ) {
			return new Follower();
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $inserted || (int) $wpdb->insert_id < 1 ) {
			return new Follower();
		}

		Cache::flush();

		return $this->get( (int) $wpdb->insert_id );
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
	 * Following.
	 *
	 * @param int $user_id User id.
	 * @param int $business_id Business id.
	 */
	public function is_following( int $user_id, int $business_id ): bool {
		global $wpdb;

		if ( $user_id < 1 || $business_id < 1 ) {
			return false;
		}

		$key    = 'fol/is/' . $user_id . '/' . $business_id;
		$cached = Cache::get( $key );

		if ( null !== $cached ) {
			return (bool) $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$following = null !== $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND business_id = %d",
				$user_id,
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		Cache::set( $key, $following );

		return $following;
	}

	/**
	 * Delete for user.
	 *
	 * @param int $business_id Business id.
	 * @param int $user_id User id.
	 */
	public function delete_for_user( int $business_id, int $user_id ): bool {
		global $wpdb;

		if ( $business_id < 1 || $user_id < 1 ) {
			return false;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = (bool) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE business_id = %d AND user_id = %d",
				$business_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $deleted ) {
			Cache::flush();
		}

		return $deleted;
	}

	/**
	 * Count.
	 *
	 * @param int $business_id Business id.
	 */
	public function count( int $business_id ): int {
		global $wpdb;

		if ( $business_id < 1 ) {
			return 0;
		}

		$key    = 'fol/cnt/' . $business_id;
		$cached = Cache::get( $key );

		if ( is_int( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE business_id = %d",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		Cache::set( $key, $count );

		return $count;
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

		return (int) $value;
	}
}
