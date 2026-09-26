<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\TeamMember;

defined( 'ABSPATH' ) || exit;

/** Class TeamRepositoryInterface. */
interface TeamRepositoryInterface {

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?TeamMember;

	/**
	 * By business.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_by_business( int $business_id ): array;

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
	public function create( array $data ): TeamMember;

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
	 * Member.
	 *
	 * @param int $user_id User id.
	 * @param int $business_id Business id.
	 */
	public function is_member( int $user_id, int $business_id ): bool;

	/**
	 * Role.
	 *
	 * @param int $user_id User id.
	 * @param int $business_id Business id.
	 */
	public function get_role( int $user_id, int $business_id ): ?string;

	/**
	 * Count by business.
	 *
	 * @param int $business_id Business id.
	 */
	public function count_by_business( int $business_id ): int;
}
