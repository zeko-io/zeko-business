<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\Follower;

defined( 'ABSPATH' ) || exit;

/** Class FollowerRepositoryInterface. */
interface FollowerRepositoryInterface {

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?Follower;

	/**
	 * Followers.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_followers( int $business_id, int $limit = 50 ): array;

	/**
	 * Following.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_following( int $user_id, int $limit = 50 ): array;

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Follower;

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 */
	public function delete( int $id ): bool;

	/**
	 * Delete for user.
	 *
	 * @param int $business_id Business id.
	 * @param int $user_id User id.
	 */
	public function delete_for_user( int $business_id, int $user_id ): bool;

	/**
	 * Following.
	 *
	 * @param int $user_id User id.
	 * @param int $business_id Business id.
	 */
	public function is_following( int $user_id, int $business_id ): bool;

	/**
	 * Count.
	 *
	 * @param int $business_id Business id.
	 */
	public function count( int $business_id ): int;
}
