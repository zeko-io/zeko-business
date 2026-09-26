<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\Service;

defined( 'ABSPATH' ) || exit;

/** Class ServiceRepositoryInterface. */
interface ServiceRepositoryInterface {

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?Service;

	/**
	 * By business.
	 *
	 * @param int  $business_id Business id.
	 * @param bool $active_only Active only.
	 */
	public function get_by_business( int $business_id, bool $active_only = true ): array;

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Service;

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
	 * Count by business.
	 *
	 * @param int $business_id Business id.
	 */
	public function count_by_business( int $business_id ): int;
}
