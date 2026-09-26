<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\TeamRepositoryInterface;
use ZBE\Entity\TeamMember;

defined( 'ABSPATH' ) || exit;

/** Class TeamRepository. */
class TeamRepository implements TeamRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_staff';

	private const FORMAT = array(
		'business_id' => '%d',
		'user_id'     => '%d',
		'role'        => '%s',
		'permissions' => '%s',
		'invited_by'  => '%d',
		'status'      => '%s',
	);

	private const JSON_COLUMNS = array( 'permissions' );

	/**
	 * Construct.
	 */
	public function __construct() {}

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?TeamMember {
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

		return null === $row ? null : TeamMember::from_row( $row );
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
				"SELECT * FROM {$table} WHERE business_id = %d ORDER BY date_added ASC, id ASC",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( TeamMember::class, 'from_row' ), $rows );
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
				"SELECT * FROM {$table} WHERE user_id = %d AND status = 'active' ORDER BY date_added ASC, id ASC",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( TeamMember::class, 'from_row' ), $rows );
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): TeamMember {
		global $wpdb;

		[ $columns, $marks, $values ] = self::build_write_sets( $data );

		if ( empty( $columns ) ) {
			return new TeamMember();
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $inserted || (int) $wpdb->insert_id < 1 ) {
			return new TeamMember();
		}

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
		return (bool) $wpdb->query( $wpdb->prepare( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
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
	 * Member.
	 *
	 * @param int $user_id User id.
	 * @param int $business_id Business id.
	 */
	public function is_member( int $user_id, int $business_id ): bool {
		global $wpdb;

		if ( $user_id < 1 || $business_id < 1 ) {
			return false;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null !== $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND business_id = %d AND status = 'active'",
				$user_id,
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Role.
	 *
	 * @param int $user_id User id.
	 * @param int $business_id Business id.
	 */
	public function get_role( int $user_id, int $business_id ): ?string {
		global $wpdb;

		if ( $user_id < 1 || $business_id < 1 ) {
			return null;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$role = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT role FROM {$table} WHERE user_id = %d AND business_id = %d AND status = 'active'",
				$user_id,
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return null === $role ? null : (string) $role;
	}

	/**
	 * Count by business.
	 *
	 * @param int $business_id Business id.
	 */
	public function count_by_business( int $business_id ): int {
		global $wpdb;

		if ( $business_id < 1 ) {
			return 0;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE business_id = %d",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
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

		$placeholder = self::FORMAT[ $column ];

		if ( '%d' === $placeholder ) {
			return (int) $value;
		}

		if ( in_array( $column, self::JSON_COLUMNS, true ) && is_array( $value ) ) {
			return wp_json_encode( $value );
		}

		return (string) $value;
	}
}
