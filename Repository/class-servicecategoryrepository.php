<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Core\Cache;
use ZBE\Entity\ServiceCategory;

defined( 'ABSPATH' ) || exit;

/** Class ServiceCategoryRepository. */
class ServiceCategoryRepository {

	private const TABLE_SUFFIX = 'zbp_service_categories';

	private const FORMAT = array(
		'name'        => '%s',
		'slug'        => '%s',
		'description' => '%s',
		'icon'        => '%s',
		'sort_order'  => '%d',
		'is_active'   => '%d',
	);

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?ServiceCategory {
		global $wpdb;

		if ( $id < 1 ) {
			return null;
		}

		$table = self::table();

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return null === $row ? null : ServiceCategory::from_row( $row );
	}

	/**
	 * All.
	 *
	 * @param bool $active_only Active only.
	 */
	public function get_all( bool $active_only = true ): array {
		global $wpdb;

		$key    = 'svc_cat/' . ( $active_only ? '1' : '0' );
		$cached = Cache::get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table     = self::table();
		$condition = $active_only ? ' WHERE is_active = 1' : '';

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table}{$condition} ORDER BY sort_order ASC, name ASC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$categories = empty( $rows ) ? array() : array_map( array( ServiceCategory::class, 'from_row' ), $rows );
		Cache::set( $key, $categories );

		return $categories;
	}

	/**
	 * By slug.
	 *
	 * @param string $slug Slug.
	 */
	public function get_by_slug( string $slug ): ?ServiceCategory {
		global $wpdb;

		$table = self::table();

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s AND is_active = 1", sanitize_title( $slug ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return null === $row ? null : ServiceCategory::from_row( $row );
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): ServiceCategory {
		global $wpdb;

		$name = sanitize_text_field( $data['name'] ?? '' );
		$slug = sanitize_title( $data['slug'] ?? $name );

		if ( empty( $name ) ) {
			return new ServiceCategory();
		}

		$table = self::table();

        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'icon'        => sanitize_text_field( $data['icon'] ?? '' ),
				'sort_order'  => (int) ( $data['sort_order'] ?? 0 ),
				'is_active'   => 1,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		Cache::flush();

		return $wpdb->insert_id ? $this->get( (int) $wpdb->insert_id ) : new ServiceCategory();
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

		foreach ( self::FORMAT as $col => $ph ) {
			if ( ! array_key_exists( $col, $data ) ) {
				continue;
			}
			$set[]    = "`{$col}` = {$ph}";
			$values[] = $data[ $col ];
		}

		if ( empty( $set ) ) {
			return false;
		}

		$values[] = $id;

		$table = self::table();
		$sql   = "UPDATE {$table} SET " . implode( ', ', $set ) . ' WHERE id = %d';

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = (bool) $wpdb->query( $wpdb->prepare( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $ok ) {
			Cache::flush();
		}

		return $ok;
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
			$wpdb->prepare( "DELETE FROM {$table} WHERE id = %d", $id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $deleted ) {
			Cache::flush();
		}

		return $deleted;
	}

	/**
	 * Seed defaults.
	 */
	public function seed_defaults(): void {
		$defaults = array(
			array(
				'name'       => 'Consulting',
				'slug'       => 'consulting',
				'icon'       => 'dashicons-welcome-view-site',
				'sort_order' => 1,
			),
			array(
				'name'       => 'Design',
				'slug'       => 'design',
				'icon'       => 'dashicons-admin-appearance',
				'sort_order' => 2,
			),
			array(
				'name'       => 'Development',
				'slug'       => 'development',
				'icon'       => 'dashicons-admin-code',
				'sort_order' => 3,
			),
			array(
				'name'       => 'Marketing',
				'slug'       => 'marketing',
				'icon'       => 'dashicons-megaphone',
				'sort_order' => 4,
			),
			array(
				'name'       => 'Installation',
				'slug'       => 'installation',
				'icon'       => 'dashicons-admin-tools',
				'sort_order' => 5,
			),
			array(
				'name'       => 'Maintenance',
				'slug'       => 'maintenance',
				'icon'       => 'dashicons-update',
				'sort_order' => 6,
			),
			array(
				'name'       => 'Repair',
				'slug'       => 'repair',
				'icon'       => 'dashicons-scheduler',
				'sort_order' => 7,
			),
			array(
				'name'       => 'Training',
				'slug'       => 'training',
				'icon'       => 'dashicons-welcome-learn-more',
				'sort_order' => 8,
			),
			array(
				'name'       => 'Other',
				'slug'       => 'other',
				'icon'       => 'dashicons-ellipsis',
				'sort_order' => 99,
			),
		);

		foreach ( $defaults as $cat ) {
			$exists = $this->get_by_slug( $cat['slug'] );
			if ( ! $exists ) {
				$this->create( $cat );
			}
		}
	}

	/**
	 * Table.
	 */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}
}
