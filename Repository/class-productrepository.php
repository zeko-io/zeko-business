<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\ProductRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\Product;

defined( 'ABSPATH' ) || exit;

/** Class ProductRepository. */
class ProductRepository implements ProductRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_products';

	private const FORMAT = array(
		'business_id'    => '%d',
		'name'           => '%s',
		'description'    => '%s',
		'price'          => '%f',
		'sale_price'     => '%f',
		'category'       => '%s',
		'image_id'       => '%d',
		'gallery_ids'    => '%d',
		'stock_quantity' => '%d',
		'stock_status'   => '%s',
		'is_active'      => '%d',
		'is_featured'    => '%d',
		'sku'            => '%s',
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
	public function get( int $id ): ?Product {
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

		return null === $row ? null : Product::from_row( $row );
	}

	/**
	 * By business.
	 *
	 * @param int  $business_id Business id.
	 * @param bool $active_only Active only.
	 */
	public function get_by_business( int $business_id, bool $active_only = true ): array {
		global $wpdb;

		if ( $business_id < 1 ) {
			return array();
		}

		$key    = 'prod/' . $business_id . '/' . ( $active_only ? '1' : '0' );
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table     = self::table();
		$condition = $active_only ? ' AND is_active = 1' : '';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE business_id = %d{$condition} ORDER BY is_featured DESC, name ASC",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$products = empty( $rows ) ? array() : array_map( array( Product::class, 'from_row' ), $rows );

		Cache::set( $key, $products );

		return $products;
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Product {
		global $wpdb;

		[$columns, $marks, $values] = self::build_write_sets( $data );

		if ( empty( $columns ) ) {
			return new Product();
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $inserted || (int) $wpdb->insert_id < 1 ) {
			return new Product();
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

		if ( '%f' === $placeholder ) {
			return (float) $value;
		}

		return (string) $value;
	}
}
