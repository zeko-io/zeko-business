<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import;

defined( 'ABSPATH' ) || exit;

/** Class Exporter. */
class Exporter {
	/**
	 * Table.
	 *
	 * @var string Table.
	 */
	private string $table;
	/**
	 * Columns.
	 *
	 * @var array Columns.
	 */
	private array $columns;
	/**
	 * Filename.
	 *
	 * @var string Filename.
	 */
	private string $filename;

	/**
	 * Construct.
	 *
	 * @param string $table Table.
	 * @param array  $columns Columns.
	 * @param string $filename Filename.
	 */
	public function __construct( string $table, array $columns, string $filename ) {
		$this->table    = $table;
		$this->columns  = $columns;
		$this->filename = $filename;
	}

	/**
	 * Export.
	 *
	 * @param array  $where Where.
	 * @param string $where_extra Where extra.
	 */
	public function export( array $where = array(), string $where_extra = '' ): void {
		global $wpdb;

		$table     = $wpdb->prefix . $this->table;
		$where_sql = '';
		$params    = array();

		if ( ! empty( $where ) ) {
			$clauses = array();
			foreach ( $where as $col => $val ) {
				$clauses[] = "{$col} = %s";
				$params[]  = $val;
			}
			$where_sql = 'WHERE ' . implode( ' AND ', $clauses );
		}

		if ( $where_extra ) {
			$where_sql = ( $where_sql ? $where_sql . ' AND ' : 'WHERE ' ) . $where_extra;
		}

		$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY id ASC";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $params ) ) {
            //phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		} else {
            //phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results( $sql );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		if ( empty( $results ) ) {
			wp_die( 'No data to export.' );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $this->filename . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, $this->columns );

		foreach ( $results as $row ) {
			$line = array();
			foreach ( $this->columns as $col ) {
				$line[] = $row->$col ?? '';
			}
			fputcsv( $output, $line );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Businesses.
	 */
	public static function businesses(): void {
		$exporter = new self(
			'zbp_businesses',
			array(
				'id',
				'slug',
				'name',
				'status',
				'email',
				'phone',
				'website',
				'whatsapp',
				'address',
				'city',
				'state',
				'country',
				'zip',
				'lat',
				'lng',
				'avg_rating',
				'review_count',
				'follower_count',
				'view_count',
				'is_featured',
				'is_verified',
				'plan',
				'date_created',
				'date_modified',
			),
			'zeko-businesses'
		);
		$exporter->export();
	}

	/**
	 * Reviews.
	 */
	public static function reviews(): void {
		$exporter = new self(
			'zbp_reviews',
			array(
				'id',
				'business_id',
				'user_id',
				'rating',
				'title',
				'content',
				'status',
				'date_created',
			),
			'zeko-reviews'
		);
		$exporter->export();
	}

	/**
	 * Services.
	 */
	public static function services(): void {
		$exporter = new self(
			'zbp_services',
			array(
				'id',
				'business_id',
				'name',
				'description',
				'price',
				'price_type',
				'is_active',
				'is_featured',
				'category',
				'date_created',
			),
			'zeko-services'
		);
		$exporter->export();
	}
}
