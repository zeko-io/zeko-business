<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\Media;

defined( 'ABSPATH' ) || exit;

/** Class MediaRepositoryInterface. */
interface MediaRepositoryInterface {

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?Media;

	/**
	 * By business.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_by_business( int $business_id ): array;

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Media;

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 */
	public function delete( int $id ): bool;
}
