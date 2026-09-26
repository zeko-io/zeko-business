<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import;

defined( 'ABSPATH' ) || exit;

/** Class Importer. */
class Importer {
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
	 * Imported.
	 *
	 * @var int Imported.
	 */
	private int $imported = 0;
	/**
	 * Skipped.
	 *
	 * @var int Skipped.
	 */
	private int $skipped = 0;
	/**
	 * Failed.
	 *
	 * @var int Failed.
	 */
	private int $failed = 0;
	/**
	 * Errors.
	 *
	 * @var array Errors.
	 */
	private array $errors = array();

	/**
	 * Construct.
	 *
	 * @param string $table Table.
	 * @param array  $columns Columns.
	 */
	public function __construct( string $table, array $columns ) {
		$this->table   = $table;
		$this->columns = $columns;
	}

	/**
	 * Import csv.
	 *
	 * @param string $tmp_file Tmp file.
	 * @param array  $column_map Column map.
	 * @param bool   $update_existing Update existing.
	 */
	public function import_csv( string $tmp_file, array $column_map = array(), bool $update_existing = false ): array {
		if ( ! file_exists( $tmp_file ) ) {
			return array(
				'success' => false,
				'error'   => 'File not found',
			);
		}

		$handle = fopen( $tmp_file, 'r' );
		if ( ! $handle ) {
			return array(
				'success' => false,
				'error'   => 'Cannot open file',
			);
		}

		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			return array(
				'success' => false,
				'error'   => 'Empty CSV file',
			);
		}

		$mapped_header = array();
		foreach ( $header as $i => $col ) {
			$col = trim( str_replace( array( '"', "'" ), '', $col ) );
			if ( isset( $column_map[ $col ] ) ) {
				$mapped_header[ $i ] = $column_map[ $col ];
			} elseif ( in_array( $col, $this->columns, true ) ) {
				$mapped_header[ $i ] = $col;
			} else {
				$mapped_header[ $i ] = null;
			}
		}

		global $wpdb;
		$full_table = $wpdb->prefix . $this->table;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$data = array();
			foreach ( $mapped_header as $i => $col ) {
				if ( $col && isset( $row[ $i ] ) ) {
					$data[ $col ] = $row[ $i ];
				}
			}

			if ( empty( $data ) ) {
				++$this->skipped;
				continue;
			}

			try {
				$data = $this->sanitize_row( $data );

				if ( $update_existing && ! empty( $data['id'] ) ) {
					$existing_id = absint( $data['id'] );
					unset( $data['id'] );
                    //phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$result = $wpdb->update( $full_table, $data, array( 'id' => $existing_id ) );
					if ( false === $result ) {
						++$this->failed;
						$this->errors[] = "Failed to update ID {$existing_id}: " . $wpdb->last_error;
					} else {
						++$this->imported;
					}
				} else {
					unset( $data['id'] );
                    //phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$result = $wpdb->insert( $full_table, $data );
					if ( false === $result ) {
						++$this->failed;
						$this->errors[] = 'Failed to insert: ' . $wpdb->last_error;
					} else {
						++$this->imported;
					}
				}
			} catch ( \Exception $e ) {
				++$this->failed;
				$this->errors[] = $e->getMessage();
			}
		}

		fclose( $handle );

		// Imported rows bypass the repositories, so drop any cached queries.
		if ( 0 === strpos( $this->table, 'zbp_' ) ) {
			\ZBE\Core\Cache::flush();
		}

		return array(
			'success'  => true,
			'imported' => $this->imported,
			'skipped'  => $this->skipped,
			'failed'   => $this->failed,
			'errors'   => array_slice( $this->errors, 0, 20 ),
		);
	}

	/**
	 * Sanitize row.
	 *
	 * @param array $data Data.
	 */
	private function sanitize_row( array $data ): array {
		$sanitized = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'id', 'post_id', 'owner_id', 'business_id', 'user_id', 'follower_count', 'review_count', 'view_count', 'group_id', 'sort_order', 'day_of_week', 'is_active', 'is_featured', 'is_verified', 'is_claimed', 'is_sponsored', 'is_closed', 'is_read' ), true ) ) {
				$sanitized[ $key ] = absint( $value );
			} elseif ( in_array( $key, array( 'avg_rating', 'price', 'lat', 'lng' ), true ) ) {
				$sanitized[ $key ] = floatval( $value );
			} elseif ( in_array( $key, array( 'email' ), true ) ) {
				$sanitized[ $key ] = sanitize_email( $value );
			} elseif ( in_array( $key, array( 'website', 'whatsapp' ), true ) ) {
				$sanitized[ $key ] = esc_url_raw( $value );
			} elseif ( in_array( $key, array( 'name', 'slug', 'phone', 'city', 'state', 'country', 'zip', 'status', 'plan', 'timezone', 'method' ), true ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( in_array( $key, array( 'address', 'content', 'description', 'evidence', 'note', 'admin_reply', 'message' ), true ) ) {
				$sanitized[ $key ] = sanitize_textarea_field( $value );
			} elseif ( in_array( $key, array( 'title' ), true ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}
		return $sanitized;
	}

	/**
	 * Import businesses csv.
	 *
	 * @param string $tmp_file Tmp file.
	 */
	public function import_businesses_csv( string $tmp_file ): array {
		$map = array(
			'ID'        => 'id',
			'Slug'      => 'slug',
			'Name'      => 'name',
			'Status'    => 'status',
			'Email'     => 'email',
			'Phone'     => 'phone',
			'Website'   => 'website',
			'Address'   => 'address',
			'City'      => 'city',
			'State'     => 'state',
			'Country'   => 'country',
			'Zip'       => 'zip',
			'Lat'       => 'lat',
			'Lng'       => 'lng',
			'Rating'    => 'avg_rating',
			'Reviews'   => 'review_count',
			'Followers' => 'follower_count',
			'Views'     => 'view_count',
			'Featured'  => 'is_featured',
			'Verified'  => 'is_verified',
			'Plan'      => 'plan',
			'Created'   => 'date_created',
		);
		return $this->import_csv( $tmp_file, $map, true );
	}

	/**
	 * Import reviews csv.
	 *
	 * @param string $tmp_file Tmp file.
	 */
	public function import_reviews_csv( string $tmp_file ): array {
		$map = array(
			'ID'          => 'id',
			'Business ID' => 'business_id',
			'User ID'     => 'user_id',
			'Rating'      => 'rating',
			'Title'       => 'title',
			'Content'     => 'content',
			'Status'      => 'status',
			'Date'        => 'date_created',
		);
		return $this->import_csv( $tmp_file, $map, false );
	}

	/**
	 * Import services csv.
	 *
	 * @param string $tmp_file Tmp file.
	 */
	public function import_services_csv( string $tmp_file ): array {
		$map = array(
			'ID'          => 'id',
			'Business ID' => 'business_id',
			'Name'        => 'name',
			'Description' => 'description',
			'Price'       => 'price',
			'Price Type'  => 'price_type',
			'Active'      => 'is_active',
			'Featured'    => 'is_featured',
			'Category'    => 'category',
			'Date'        => 'date_created',
		);
		return $this->import_csv( $tmp_file, $map, false );
	}
}
