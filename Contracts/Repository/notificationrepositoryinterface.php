<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\Notification;

defined( 'ABSPATH' ) || exit;

/** Class NotificationRepositoryInterface. */
interface NotificationRepositoryInterface {

	/**
	 * By user.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_by_user( int $user_id, int $limit = 20 ): array;

	/**
	 * Unread count.
	 *
	 * @param int $user_id User id.
	 */
	public function get_unread_count( int $user_id ): int;

	/**
	 * Mark read.
	 *
	 * @param int $id Id.
	 */
	public function mark_read( int $id ): bool;

	/**
	 * Mark all read.
	 *
	 * @param int $user_id User id.
	 */
	public function mark_all_read( int $user_id ): bool;

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): Notification;

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 */
	public function delete( int $id ): bool;
}
