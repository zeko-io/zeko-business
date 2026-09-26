<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\NotificationRepositoryInterface;
use ZBE\Entity\Notification;

defined( 'ABSPATH' ) || exit;

/** Class NotificationRepository. */
class NotificationRepository implements NotificationRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_notifications';

	private const FORMAT = array(
		'user_id'     => '%d',
		'business_id' => '%d',
		'type'        => '%s',
		'title'       => '%s',
		'message'     => '%s',
		'link'        => '%s',
		'is_read'     => '%d',
	);

	/**
	 * Construct.
	 */
	public function __construct() {}

	/**
	 * By user.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_by_user( int $user_id, int $limit = 20 ): array {
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

		return array_map( array( Notification::class, 'from_row' ), $rows );
	}

	/**
	 * Unread count.
	 *
	 * @param int $user_id User id.
	 */
	public function get_unread_count( int $user_id ): int {
		global $wpdb;

		if ( $user_id < 1 ) {
			return 0;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Mark read.
	 *
	 * @param int $id Id.
	 */
	public function mark_read( int $id ): bool {
		global $wpdb;

		if ( $id < 1 ) {
			return false;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_read = 1 WHERE id = %d AND is_read = 0",
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Mark all read.
	 *
	 * @param int $user_id User id.
	 */
	public function mark_all_read( int $user_id ): bool {
		global $wpdb;

		if ( $user_id < 1 ) {
			return false;
		}

		$table = self::table();
		$sql   = "UPDATE {$table} SET is_read = 1 WHERE user_id = %d AND is_read = 0";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->query( $wpdb->prepare( $sql, $user_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return false !== $updated;
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Notification {
		global $wpdb;

		[ $columns, $marks, $values ] = self::build_write_sets( $data );

		if ( empty( $columns ) ) {
			return new Notification();
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $inserted || (int) $wpdb->insert_id < 1 ) {
			return new Notification();
		}

		return $this->fetch( (int) $wpdb->insert_id );
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
		return (bool) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id = %d",
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Fetch.
	 *
	 * @param int $id Id.
	 */
	private function fetch( int $id ): ?Notification {
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

		return null === $row ? null : Notification::from_row( $row );
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
