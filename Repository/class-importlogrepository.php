<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\ImportLogRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/** Class ImportLogRepository. */
class ImportLogRepository implements ImportLogRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_import_log';

	private const FORMAT = array(
		'source'        => '%s',
		'source_id'     => '%s',
		'business_id'   => '%d',
		'status'        => '%s',
		'error_message' => '%s',
	);

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
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')'; // phpcs:ignore WordPress.DB.PreparedSQL

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Recent.
	 *
	 * @param int $limit Limit.
	 */
	public function recent( int $limit = 20 ): array {
		global $wpdb;

		$limit = max( 1, min( 200, $limit ) );
		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY date_created DESC, id DESC LIMIT %d",
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return is_array( $rows ) ? $rows : array();
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

			$value = null === $data[ $column ]
				? null
				: ( '%d' === $placeholder ? (int) $data[ $column ] : (string) $data[ $column ] );

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
}
