<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Contracts\Repository\BusinessRepositoryInterface;
use ZBE\Core\Cache;
use ZBE\Entity\Business;

defined( 'ABSPATH' ) || exit;

/** Class BusinessRepository. */
class BusinessRepository implements BusinessRepositoryInterface {

	private const TABLE_SUFFIX = 'zbp_businesses';

	private const FORMAT = array(
		'post_id'           => '%d',
		'owner_id'          => '%d',
		'slug'              => '%s',
		'name'              => '%s',
		'status'            => '%s',
		'phone'             => '%s',
		'website'           => '%s',
		'email'             => '%s',
		'whatsapp'          => '%s',
		'address'           => '%s',
		'city'              => '%s',
		'state'             => '%s',
		'country'           => '%s',
		'zip'               => '%s',
		'lat'               => '%f',
		'lng'               => '%f',
		'follower_count'    => '%d',
		'review_count'      => '%d',
		'avg_rating'        => '%f',
		'view_count'        => '%d',
		'is_featured'       => '%d',
		'is_verified'       => '%d',
		'is_claimed'        => '%d',
		'claimed_by'        => '%d',
		'claimed_date'      => '%s',
		'is_sponsored'      => '%d',
		'featured_expires'  => '%s',
		'sponsored_expires' => '%s',
		'plan'              => '%s',
		'avatar_id'         => '%d',
		'cover_id'          => '%d',
		'business_hours'    => '%s',
		'social_links'      => '%s',
		'action_buttons'    => '%s',
		'extra_data'        => '%s',
		'timezone'          => '%s',
		'hours_mode'        => '%s',
		'group_id'          => '%d',
	);

	private const JSON_COLUMNS = array( 'business_hours', 'social_links', 'action_buttons', 'extra_data' );

	private const SORTABLE = array(
		'id'             => 'id',
		'name'           => 'name',
		'slug'           => 'slug',
		'city'           => 'city',
		'country'        => 'country',
		'status'         => 'status',
		'plan'           => 'plan',
		'avg_rating'     => 'avg_rating',
		'review_count'   => 'review_count',
		'follower_count' => 'follower_count',
		'view_count'     => 'view_count',
		'date_created'   => 'date_created',
		'date_modified'  => 'date_modified',
		'is_featured'    => 'is_featured',
		'is_verified'    => 'is_verified',
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
	public function get( int $id ): ?Business {
		global $wpdb;

		if ( $id < 1 ) {
			return null;
		}

		$key = 'biz/' . $id;

		$cached = Cache::get( $key );
		if ( $cached instanceof Business || false === $cached ) {
			return $cached instanceof Business ? $cached : null;
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

		$business = null === $row ? null : Business::from_row( $row );

		Cache::set( $key, $business instanceof Business ? $business : false );

		return $business;
	}

	/**
	 * By slug.
	 *
	 * @param string $slug Slug.
	 */
	public function get_by_slug( string $slug ): ?Business {
		global $wpdb;

		if ( '' === $slug ) {
			return null;
		}

		$cached = Cache::get( 'biz/slug/' . md5( $slug ) );
		if ( $cached instanceof Business || false === $cached ) {
			return $cached instanceof Business ? $cached : null;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slug = %s",
				$slug
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$business = null === $row ? null : Business::from_row( $row );

		Cache::set( 'biz/slug/' . md5( $slug ), $business instanceof Business ? $business : false );
		Cache::set( 'biz/' . max( 0, (int) ( $row->id ?? 0 ) ), $business instanceof Business ? $business : false );

		return $business;
	}

	/**
	 * By post id.
	 *
	 * @param int $post_id Post id.
	 */
	public function get_by_post_id( int $post_id ): ?Business {
		global $wpdb;

		if ( $post_id < 1 ) {
			return null;
		}

		$cached = Cache::get( 'biz/post/' . $post_id );
		if ( $cached instanceof Business || false === $cached ) {
			return $cached instanceof Business ? $cached : null;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d",
				$post_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$business = null === $row ? null : Business::from_row( $row );

		Cache::set( 'biz/post/' . $post_id, $business instanceof Business ? $business : false );

		return $business;
	}

	/**
	 * By post ids.
	 *
	 * @param array $post_ids Post ids.
	 */
	public function get_by_post_ids( array $post_ids ): array {
		global $wpdb;

		$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		$table        = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				$post_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		$mapped = array();

		foreach ( $rows as $row ) {
			$business                      = Business::from_row( $row );
			$mapped[ (int) $row->post_id ] = $business;
			Cache::set( 'biz/' . $business->id, $business );
			Cache::set( 'biz/post/' . (int) $row->post_id, $business );
		}

		return $mapped;
	}

	/**
	 * By owner.
	 *
	 * @param int $owner_id Owner id.
	 * @param int $limit Limit.
	 */
	public function get_by_owner( int $owner_id, int $limit = 20 ): array {
		global $wpdb;

		if ( $owner_id < 1 ) {
			return array();
		}

		$limit = max( 1, $limit );
		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE owner_id = %d ORDER BY date_created DESC, id DESC LIMIT %d",
				$owner_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( Business::class, 'from_row' ), $rows );
	}

	/**
	 * By ids.
	 *
	 * @param array $ids Ids.
	 */
	public function get_by_ids( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$table        = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id IN ( {$placeholders} ) ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				$ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		$mapped = array();

		foreach ( $rows as $row ) {
			$mapped[ (int) $row->id ] = Business::from_row( $row );
		}

		$results = array();

		foreach ( $ids as $id ) {
			if ( isset( $mapped[ $id ] ) ) {
				$results[] = $mapped[ $id ];
			}
		}

		return $results;
	}

	/**
	 * Find.
	 *
	 * @param array $args Args.
	 */
	public function find( array $args = array() ): array {
		$key = 'biz/find/' . md5( wp_json_encode( $args ) );

		$cached = Cache::get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		[ $where, $values ] = self::build_where( $args );

		$requested = strtolower( (string) ( $args['orderby'] ?? 'date_created' ) );
		$direction = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';

		// Composite ordering used by the public directory (sponsored first,.
		// then featured, then rating, then recency).
		if ( 'proximity' === $requested ) {
			$earth = self::proximity_earth( $args );
			if ( null !== $earth ) {
				$lat       = (float) $args['latitude'];
				$lng       = (float) $args['longitude'];
				$order_sql = "( {$earth} * acos( least( 1.0, cos( radians( {$lat} ) ) * cos( radians( lat ) ) * cos( radians( lng ) - radians( {$lng} ) ) + sin( radians( {$lat} ) ) * sin( radians( lat ) ) ) ) ) ASC";
			} else {
				$order_sql = 'date_created DESC';
			}
		} elseif ( 'directory' === $requested ) {
			$order_sql = "is_sponsored {$direction}, is_featured {$direction}, avg_rating {$direction}, date_created {$direction}";
		} else {
			$orderby   = self::SORTABLE[ $requested ] ?? 'date_created';
			$order_sql = "{$orderby} {$direction}";
		}

		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$values[] = $per_page;
		$values[] = $offset;

		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order_sql} LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			Cache::set( $key, array() );

			return array();
		}

		$results = array_map( array( Business::class, 'from_row' ), $rows );

		Cache::set( $key, $results );

		return $results;
	}

	/**
	 * Count.
	 *
	 * @param array $args Args.
	 */
	public function count( array $args = array() ): int {
		global $wpdb;

		[ $where, $values ] = self::build_where( $args );

		$table = self::table();
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Count by status.
	 */
	public function count_by_status(): array {
		global $wpdb;

		$key    = 'biz/agg/status';
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$counts = array();

		foreach ( $rows ?? array() as $row ) {
			$counts[ (string) $row->status ] = (int) $row->cnt;
		}

		Cache::set( $key, $counts );

		return $counts;
	}

	/**
	 * Count by plan.
	 */
	public function count_by_plan(): array {
		global $wpdb;

		$key    = 'biz/agg/plan';
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT plan, COUNT(*) AS cnt FROM {$table} GROUP BY plan ORDER BY cnt DESC" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$counts = array();

		foreach ( $rows ?? array() as $row ) {
			$counts[ (string) $row->plan ] = (int) $row->cnt;
		}

		Cache::set( $key, $counts );

		return $counts;
	}

	/**
	 * Sum views.
	 */
	public function sum_views(): int {
		global $wpdb;

		$key    = 'biz/agg/views';
		$cached = Cache::get( $key );

		if ( is_int( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( "SELECT IFNULL(SUM(view_count), 0) FROM {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		Cache::set( $key, $total );

		return $total;
	}

	/**
	 * Distinct cities.
	 *
	 * @param int $limit Limit.
	 */
	public function distinct_cities( int $limit = 50 ): array {
		global $wpdb;

		$limit  = max( 1, min( 200, $limit ) );
		$key    = 'biz/cities/' . $limit;
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT city FROM {$table} WHERE status = 'active' AND city <> '' GROUP BY city ORDER BY city ASC LIMIT %d",
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$cities = empty( $rows ) ? array() : wp_list_pluck( $rows, 'city' );

		Cache::set( $key, $cities );

		return $cities;
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): ?Business {
		global $wpdb;

		[ $columns, $marks, $values ] = self::build_write_sets( $data );

		if ( empty( $columns ) ) {
			return null;
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $marks ) . ')';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query( self::prepared( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $inserted ) {
			return null;
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
	 * Increment views.
	 *
	 * @param int $id Id.
	 */
	public function increment_views( int $id ): void {
		global $wpdb;

		if ( $id < 1 ) {
			return;
		}

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET view_count = view_count + 1 WHERE id = %d",
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		Cache::delete( 'biz/' . $id );
	}

	/**
	 * Expire featured.
	 *
	 * @param string $before Before.
	 */
	public function expire_featured( string $before ): int {
		return self::expire_flag( 'is_featured', 'featured_expires', $before );
	}

	/**
	 * Expire sponsored.
	 *
	 * @param string $before Before.
	 */
	public function expire_sponsored( string $before ): int {
		return self::expire_flag( 'is_sponsored', 'sponsored_expires', $before );
	}

	/**
	 * Find businesses whose flag expires within $days from now.
	 *
	 * @return array<int, Business>
	 * @param string $flag Column name, e.g. 'is_featured'.
	 * @param string $expires Column name, e.g. 'featured_expires'.
	 * @param int    $days Warning window.
	 */
	public function find_expiring_flag( string $flag, string $expires, int $days = 7 ): array {
		global $wpdb;

		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS );
		$now    = gmdate( 'Y-m-d H:i:s', time() );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE {$flag} = 1
				   AND {$expires} IS NOT NULL
				   AND {$expires} > %s
				   AND {$expires} < %s",
				$now,
				$cutoff
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( Business::class, 'from_row' ), $rows );
	}

	/**
	 * Downgrade every row whose expiry timestamp has passed.
	 *
	 * @return int Affected row count.
	 * @param string $flag Boolean column, e.g. "is_featured".
	 * @param string $expires Datetime column, e.g. "featured_expires".
	 * @param string $before Local datetime cutoff, Y-m-d H:i:s.
	 */
	private static function expire_flag( string $flag, string $expires, string $before ): int {
		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET {$flag} = 0, {$expires} = NULL
				 WHERE {$flag} = 1 AND {$expires} IS NOT NULL AND {$expires} < %s",
				$before
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $updated ) {
			Cache::flush();
		}

		return (int) $updated;
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
	 * Build where.
	 *
	 * @param array $args Args.
	 */
	private static function build_where( array $args ): array {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';

			$where[] = '( name LIKE %s OR email LIKE %s OR city LIKE %s )';
			array_push( $values, $like, $like, $like );
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = (string) $args['status'];
		}

		if ( ! empty( $args['owner_id'] ) ) {
			$where[]  = 'owner_id = %d';
			$values[] = (int) $args['owner_id'];
		}

		if ( ! empty( $args['city'] ) ) {
			$where[]  = 'city = %s';
			$values[] = (string) $args['city'];
		}

		if ( ! empty( $args['country'] ) ) {
			$where[]  = 'country = %s';
			$values[] = (string) $args['country'];
		}

		if ( isset( $args['is_featured'] ) ) {
			$where[]  = 'is_featured = %d';
			$values[] = (int) (bool) $args['is_featured'];
		}

		if ( isset( $args['is_verified'] ) ) {
			$where[]  = 'is_verified = %d';
			$values[] = (int) (bool) $args['is_verified'];
		}

		if ( ! empty( $args['plan'] ) ) {
			$where[]  = 'plan = %s';
			$values[] = (string) $args['plan'];
		}

		if ( isset( $args['min_reviews'] ) && (int) $args['min_reviews'] > 0 ) {
			$where[]  = 'review_count >= %d';
			$values[] = (int) $args['min_reviews'];
		}

		if ( isset( $args['min_rating'] ) && (float) $args['min_rating'] > 0 ) {
			$where[]  = 'avg_rating >= %f';
			$values[] = round( (float) $args['min_rating'], 1 );
		}

		if ( ! empty( $args['category'] ) ) {
			$terms = $wpdb->terms;
			$tt    = $wpdb->term_taxonomy;
			$tr    = $wpdb->term_relationships;
			$table = self::table();

			$where[]  = "{$table}.post_id IN (
				SELECT tr2.object_id FROM {$tr} tr2
				INNER JOIN {$tt} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
				WHERE tt2.taxonomy = 'business_category' AND tt2.term_id = %d
			)";
			$values[] = (int) $args['category'];
		}

		if ( ! empty( $args['open_now'] ) ) {
			$table = self::table();
			$hours = $wpdb->prefix . 'zbp_business_hours';

			// Site-local day/time; schedules are stored as local times.
			$stamp = time();
			$dow   = (int) gmdate( 'w', $stamp );
			$prev  = ( $dow + 6 ) % 7;
			$time  = gmdate( 'H:i:s', $stamp );

			$where[] = "EXISTS (
				SELECT 1 FROM {$hours} bh WHERE bh.business_id = {$table}.id AND bh.is_closed = 0 AND (
					(
						bh.day_of_week = %d AND bh.open_time IS NOT NULL AND bh.close_time IS NOT NULL
						AND bh.open_time <= %s
						AND ( bh.close_time > %s OR bh.close_time < bh.open_time )
					) OR (
						bh.day_of_week = %d AND bh.close_time IS NOT NULL AND bh.close_time < bh.open_time
						AND bh.close_time > %s
					)
				)
			)";

			array_push( $values, $dow, $time, $time, $prev, $time );
		}

		// Radius / proximity filter (Haversine).
		$earth = self::proximity_earth( $args );
		if ( null !== $earth ) {
			$lat    = (float) $args['latitude'];
			$lng    = (float) $args['longitude'];
			$radius = max( 0.1, (float) $args['radius'] );

			$where[] = "( {$earth} * acos( least( 1.0, cos( radians( %f ) ) * cos( radians( lat ) ) * cos( radians( lng ) - radians( %f ) ) + sin( radians( %f ) ) * sin( radians( lat ) ) ) ) ) <= %f";

			array_push( $values, $lat, $lng, $lat, $radius );
		}

		return array( implode( ' AND ', $where ), $values );
	}

	/**
	 * Return the Earth radius (miles or km) when a valid proximity search
	 * (latitude + longitude + radius) is present, otherwise null.
	 *
	 * @return float|null
	 * @param array $args * @return float|null.
	 */
	private static function proximity_earth( array $args ): ?float {
		if (
			! isset( $args['latitude'], $args['longitude'], $args['radius'] )
			|| '' === (string) $args['latitude']
			|| '' === (string) $args['longitude']
			|| (float) $args['radius'] <= 0
		) {
			return null;
		}

		$metric = 'km' === strtolower( (string) ( $args['distance_units'] ?? '' ) );

		return $metric ? 6371.0 : 3959.0;
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

		if ( in_array( $column, self::JSON_COLUMNS, true ) && is_array( $value ) ) {
			return wp_json_encode( $value );
		}

		return (string) $value;
	}
}
