<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\VerificationRequest;

defined( 'ABSPATH' ) || exit;

/** Class VerificationRepositoryInterface. */
interface VerificationRepositoryInterface {

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?VerificationRequest;

	/**
	 * By business.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_by_business( int $business_id ): array;

	/**
	 * Pending.
	 */
	public function get_pending(): array;

	/**
	 * Find.
	 *
	 * @param array $args {status?: string, user_id?: int, limit?: int}.
	 */
	public function find( array $args = array() ): array;

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): VerificationRequest;

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
	 * @param int $reviewed_by Reviewed by.
	 */
	public function approve( int $id, int $reviewed_by ): bool;

	/**
	 * Reject.
	 *
	 * @param int $id Id.
	 * @param int $reviewed_by Reviewed by.
	 */
	public function reject( int $id, int $reviewed_by ): bool;
}
