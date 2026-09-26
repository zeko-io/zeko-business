<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\ServiceRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Service repository adapter.
 *
 * Services now live in Zeko Shop (zeko_shop_services) under
 * external_type = 'business' / external_id = business_id — the same pattern
 * used by products. This repository maps between the Zeko Shop rows and the
 * local Service entity while keeping the same public interface, so consumer
 * code and templates are unchanged.
 *
 * When Zeko Shop is not active, reads fall back to the legacy zbp_services
 * table so existing data is never lost during the transition.
 */
class ServiceRepository implements ServiceRepositoryInterface {

	private const LEGACY_TABLE_SUFFIX = 'zbp_services';

	/**
	 * Fetch the Zeko Shop DB instance (or null when unavailable).
	 */
	private function shop_db() {
		if ( ! class_exists( 'Zeko_Shop' ) || ! method_exists( 'Zeko_Shop', 'instance' ) ) {
			return null;
		}
		try {
			$db = \Zeko_Shop::instance()->get_db();
			return method_exists( $db, 'get_services_by_external' ) ? $db : null;
		} catch ( \Throwable $e ) { // phpcs:ignore
			return null;
		}
	}

	/**
	 * Shop active.
	 */
	private function shop_active(): bool {
		return null !== $this->shop_db();
	}

	/**
	 * Map a Zeko Shop services row (ARRAY_A) to a Service entity.
	 *
	 * @param array $row Row.
	 */
	private function map_row( array $row ): Service {
		return new Service(
			array(
				'id'                => (int) ( $row['service_id'] ?? 0 ),
				'business_id'       => (int) ( $row['external_id'] ?? 0 ),
				'name'              => (string) ( $row['title'] ?? '' ),
				'short_description' => (string) ( $row['short_description'] ?? '' ),
				'description'       => (string) ( $row['description'] ?? '' ),
				'price'             => (float) ( $row['price'] ?? 0 ),
				'price_type'        => (string) ( $row['price_type'] ?? 'fixed' ),
				'price_note'        => (string) ( $row['price_note'] ?? '' ),
				'image_id'          => (int) ( $row['image_id'] ?? 0 ),
				'is_active'         => ! empty( $row['is_active'] ),
				'is_featured'       => ! empty( $row['is_featured'] ),
				'category'          => (string) ( $row['category'] ?? '' ),
				'duration'          => (string) ( $row['duration'] ?? '' ),
				'tags'              => (string) ( $row['tags'] ?? '' ),
				'sort_order'        => (int) ( $row['sort_order'] ?? 0 ),
				'date_created'      => (string) ( $row['created_at'] ?? '' ),
			)
		);
	}

	/**
	 * Translate a Service entity's write-data into Zeko Shop row keys.
	 *
	 * @param array $data Data.
	 * @param int   $business_id Business id.
	 */
	private function to_shop_data( array $data, int $business_id ): array {
		$shop = array(
			'external_type'     => 'business',
			'external_id'       => $business_id,
			'title'             => isset( $data['name'] ) ? $data['name'] : ( $data['title'] ?? '' ),
			'short_description' => $data['short_description'] ?? '',
			'description'       => $data['description'] ?? '',
			'price'             => isset( $data['price'] ) ? $data['price'] : 0,
			'price_type'        => $data['price_type'] ?? 'fixed',
			'price_note'        => $data['price_note'] ?? '',
			'image_id'          => $data['image_id'] ?? 0,
			'is_active'         => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			'is_featured'       => isset( $data['is_featured'] ) ? (int) $data['is_featured'] : 0,
			'category'          => $data['category'] ?? '',
			'duration'          => $data['duration'] ?? '',
			'tags'              => $data['tags'] ?? '',
			'sort_order'        => $data['sort_order'] ?? 0,
		);
		return $shop;
	}

	/**
	 * Construct.
	 */
	public function __construct() {}

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?Service {
		if ( $id < 1 ) {
			return null;
		}

		$shop = $this->shop_db();
		if ( $shop ) {
			$row = $shop->get_service( $id );
			return $row ? $this->map_row( $row ) : null;
		}

		// Legacy fallback.
		global $wpdb;
		$table = $this->legacy_table();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return null === $row ? null : Service::from_row( $row );
	}

	/**
	 * By business.
	 *
	 * @param int  $business_id Business id.
	 * @param bool $active_only Active only.
	 */
	public function get_by_business( int $business_id, bool $active_only = true ): array {
		if ( $business_id < 1 ) {
			return array();
		}

		$key    = 'svc/' . $business_id . '/' . ( $active_only ? '1' : '0' );
		$cached = Cache::get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$shop = $this->shop_db();
		if ( $shop ) {
			$rows     = $shop->get_services_by_external( 'business', $business_id, $active_only );
			$services = empty( $rows ) ? array() : array_map( array( $this, 'map_row' ), $rows );
			Cache::set( $key, $services );
			return $services;
		}

		// Legacy fallback.
		global $wpdb;
		$table     = $this->legacy_table();
		$condition = $active_only ? ' AND is_active = 1' : '';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE business_id = %d{$condition} ORDER BY sort_order ASC, id ASC", $business_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$services = empty( $rows ) ? array() : array_map( array( Service::class, 'from_row' ), $rows );

		Cache::set( $key, $services );

		return $services;
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Service {
		$shop = $this->shop_db();
		if ( ! $shop ) {
			return new Service();
		}

		$business_id = absint( $data['business_id'] ?? ( $data['external_id'] ?? 0 ) );
		$shop_data   = $this->to_shop_data( $data, $business_id );

		$id = $shop->create_service( $shop_data );
		if ( $id < 1 ) {
			return new Service();
		}

		Cache::flush();

		$entity = $this->get( $id );
		return $entity ? $entity : new Service();
	}

	/**
	 * Update.
	 *
	 * @param int   $id Id.
	 * @param array $data Data.
	 */
	public function update( int $id, array $data ): bool {
		if ( $id < 1 ) {
			return false;
		}

		$shop = $this->shop_db();
		if ( ! $shop ) {
			return false;
		}

		$shop_data = array();
		$map       = array(
			'name'              => 'title',
			'short_description' => 'short_description',
			'description'       => 'description',
			'price'             => 'price',
			'price_type'        => 'price_type',
			'price_note'        => 'price_note',
			'image_id'          => 'image_id',
			'is_active'         => 'is_active',
			'is_featured'       => 'is_featured',
			'category'          => 'category',
			'duration'          => 'duration',
			'tags'              => 'tags',
			'sort_order'        => 'sort_order',
		);
		foreach ( $map as $local => $shop_col ) {
			if ( array_key_exists( $local, $data ) ) {
				$shop_data[ $shop_col ] = $data[ $local ];
			}
		}

		if ( empty( $shop_data ) ) {
			return false;
		}

		$updated = $shop->update_service( $id, $shop_data );
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
		if ( $id < 1 ) {
			return false;
		}
		$shop = $this->shop_db();
		if ( ! $shop ) {
			return false;
		}
		$deleted = $shop->delete_service( $id );
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
		if ( $business_id < 1 ) {
			return 0;
		}
		$shop = $this->shop_db();
		if ( $shop ) {
			return $shop->count_services_by_external( 'business', $business_id );
		}

		global $wpdb;
		$table = $this->legacy_table();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE business_id = %d", $business_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Legacy table.
	 */
	private function legacy_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::LEGACY_TABLE_SUFFIX;
	}
}
