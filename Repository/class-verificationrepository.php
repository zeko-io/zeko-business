<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\VerificationRepositoryInterface;
use ZBE\Entity\VerificationRequest;

defined( 'ABSPATH' ) || exit;

/** Class VerificationRepository. */
class VerificationRepository implements VerificationRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_verification_requests';

	private const FORMAT = array(
		'business_id'  => '%d',
		'user_id'      => '%d',
		'method'       => '%s',
		'document_url' => '%s',
		'note'         => '%s',
		'status'       => '%s',
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
	public function get( int $id ): ?VerificationRequest {
		return $this->fetch( $id );
	}

	/**
	 * Find.
	 *
	 * @param array $args Args.
	 */
	public function find( array $args = array() ): array {
		global $wpdb;

		$status  = isset( $args['status'] ) && '' !== $args['status']
			? sanitize_key( (string) $args['status'] )
			: '';
		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		$limit   = max( 1, min( 200, (int) ( $args['limit'] ?? 50 ) ) );

		$table = self::table();

		$where  = array();
		$values = array();

		if ( '' !== $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}
		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$values[] = $user_id;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$values[]  = $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where_sql} ORDER BY date_created DESC, id DESC LIMIT %d",
				$values
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( VerificationRequest::class, 'from_row' ), $rows );
	}

	/**
	 * By business.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_by_business( int $business_id ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE business_id = %d ORDER BY date_created DESC, id DESC",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( VerificationRequest::class, 'from_row' ), $rows );
	}

	/**
	 * Pending.
	 */
	public function get_pending(): array {
		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY date_created ASC, id ASC",
				'pending'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( VerificationRequest::class, 'from_row' ), $rows );
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): VerificationRequest {
		global $wpdb;

		[ $columns, $marks, $values ] = self::build_write_sets( $data );

		if ( empty( $columns ) ) {
			return new VerificationRequest();
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $inserted || (int) $wpdb->insert_id < 1 ) {
			return new VerificationRequest();
		}

		return $this->fetch( (int) $wpdb->insert_id );
	}

	/**
	 * Approve.
	 *
	 * @param int $id Id.
	 * @param int $reviewed_by Reviewed by.
	 */
	public function approve( int $id, int $reviewed_by ): bool {
		return $this->set_review_status( $id, $reviewed_by, 'approved' );
	}

	/**
	 * Reject.
	 *
	 * @param int $id Id.
	 * @param int $reviewed_by Reviewed by.
	 */
	public function reject( int $id, int $reviewed_by ): bool {
		return $this->set_review_status( $id, $reviewed_by, 'rejected' );
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
	 * Review status.
	 *
	 * @param int    $id Id.
	 * @param int    $reviewed_by Reviewed by.
	 * @param string $status Status.
	 */
	private function set_review_status( int $id, int $reviewed_by, string $status ): bool {
		global $wpdb;

		if ( $id < 1 ) {
			return false;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, reviewed_by = %d, reviewed_at = %s WHERE id = %d AND status = 'pending'",
				$status,
				$reviewed_by,
				current_time( 'mysql' ),
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
	private function fetch( int $id ): ?VerificationRequest {
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

		return null === $row ? null : VerificationRequest::from_row( $row );
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
