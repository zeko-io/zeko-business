<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\Review;

defined( 'ABSPATH' ) || exit;

/** Class ReviewRepositoryInterface. */
interface ReviewRepositoryInterface {

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?Review;

	/**
	 * By business.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_by_business( int $business_id, int $limit = 20 ): array;

	/**
	 * Recent.
	 *
	 * @param int $limit Limit.
	 */
	public function get_recent( int $limit = 5 ): array;

	/**
	 * By user.
	 *
	 * @param int $user_id User id.
	 */
	public function get_by_user( int $user_id ): array;

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Review;

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
	 * Approve.
	 *
	 * @param int $id Id.
	 */
	public function approve( int $id ): bool;

	/**
	 * Reject.
	 *
	 * @param int $id Id.
	 */
	public function reject( int $id ): bool;

	/**
	 * Count by business.
	 *
	 * @param int    $business_id Business id.
	 * @param string $status Status.
	 */
	public function count_by_business( int $business_id, string $status = 'approved' ): int;

	/**
	 * Count pending.
	 */
	public function count_pending(): int;

	/**
	 * Count all.
	 */
	public function count_all(): int;

	/**
	 * Avg rating.
	 *
	 * @param int $business_id Business id.
	 */
	public function avg_rating( int $business_id ): float;

	/**
	 * Approved review counts per star: [1=>int,...5=>int].
	 *
	 * @param int $business_id Business id.
	 */
	public function rating_counts( int $business_id ): array;

	/**
	 * Get the vote (1 helpful, -1 not helpful, 0 none) the user cast on a review.
	 *
	 * @param int $review_id Review id.
	 * @param int $user_id User id.
	 */
	public function get_user_vote( int $review_id, int $user_id ): int;

	/**
	 * Record a review vote (value 1 or -1). Removing the same value returns 0.
	 * Returns the review's new helpful_count.
	 *
	 * @param int $review_id Review id.
	 * @param int $user_id User id.
	 * @param int $value Value.
	 */
	public function vote( int $review_id, int $user_id, int $value ): int;
}
