<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\Business;

defined( 'ABSPATH' ) || exit;

/** Class BusinessRepositoryInterface. */
interface BusinessRepositoryInterface {

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?Business;

	/**
	 * By slug.
	 *
	 * @param string $slug Slug.
	 */
	public function get_by_slug( string $slug ): ?Business;

	/**
	 * By post id.
	 *
	 * @param int $post_id Post id.
	 */
	public function get_by_post_id( int $post_id ): ?Business;

	/**
	 * By post ids.
	 *
	 * @param array $post_ids Post ids.
	 */
	public function get_by_post_ids( array $post_ids ): array;

	/**
	 * By owner.
	 *
	 * @param int $owner_id Owner id.
	 * @param int $limit Limit.
	 */
	public function get_by_owner( int $owner_id, int $limit = 20 ): array;

	/**
	 * By ids.
	 *
	 * @param array $ids Ids.
	 */
	public function get_by_ids( array $ids ): array;

	/**
	 * Expire featured.
	 *
	 * @param string $before Before.
	 */
	public function expire_featured( string $before ): int;

	/**
	 * Expire sponsored.
	 *
	 * @param string $before Before.
	 */
	public function expire_sponsored( string $before ): int;

	/**
	 * Find businesses whose flag expires within $days from now.
	 *
	 * @return array<int, Business>
	 * @param string $flag Column name, e.g. 'is_featured'.
	 * @param string $expires Column name, e.g. 'featured_expires'.
	 * @param int    $days Warning window.
	 */
	public function find_expiring_flag( string $flag, string $expires, int $days = 7 ): array;

	/**
	 * Find.
	 *
	 * @param array $args Args.
	 */
	public function find( array $args = array() ): array;

	/**
	 * Count.
	 *
	 * @param array $args Args.
	 */
	public function count( array $args = array() ): int;

	/**
	 * Count by status.
	 *
	 * @return array<string, int> status slug => row count.
	 */
	public function count_by_status(): array;

	/**
	 * Count by plan.
	 *
	 * @return array<string, int> plan slug => row count.
	 */
	public function count_by_plan(): array;

	/**
	 * Sum views.
	 */
	public function sum_views(): int;

	/**
	 * Distinct non-empty city names across active businesses.
	 *
	 * @return string[]
	 * @param int $limit Limit.
	 */
	public function distinct_cities( int $limit = 50 ): array;

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): ?Business;

	/**
	 * Update.
	 *
	 * @param int   $id Id.
	 * @param array $data Data.
	 */
	public function update( int $id, array $data ): bool;

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 */
	public function delete( int $id ): bool;

	/**
	 * Increment views.
	 *
	 * @param int $id Id.
	 */
	public function increment_views( int $id ): void;
}
