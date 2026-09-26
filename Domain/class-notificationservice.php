<?php
/**
 * Notification domain service.
 *
 * Persists in-app notifications; email delivery is left to listeners
 * of the zbe_notification_sent action.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Domain;

use ZBE\Contracts\Repository\NotificationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/** Class NotificationService. */
class NotificationService {

	/**
	 * Repo.
	 *
	 * @var NotificationRepositoryInterface Repo.
	 */
	private NotificationRepositoryInterface $repo;

	/**
	 * Construct.
	 *
	 * @param NotificationRepositoryInterface $repo Repo.
	 */
	public function __construct( NotificationRepositoryInterface $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Send.
	 *
	 * @param int    $user_id User id.
	 * @param string $type Type.
	 * @param string $title Title.
	 * @param string $message Message.
	 * @param string $link Link.
	 * @param int    $business_id Business id.
	 */
	public function send( int $user_id, string $type, string $title, string $message, string $link = '', int $business_id = 0 ): void {
		if ( $user_id < 1 || '' === trim( $title ) ) {
			return;
		}

		$notification = $this->repo->create(
			array(
				'user_id'      => $user_id,
				'business_id'  => max( 0, $business_id ),
				'type'         => sanitize_key( $type ),
				'title'        => sanitize_text_field( $title ),
				'message'      => sanitize_textarea_field( $message ),
				'link'         => esc_url_raw( $link ),
				'is_read'      => 0,
				'date_created' => current_time( 'mysql' ),
			)
		);

		if ( $notification->id > 0 ) {
			do_action( 'zbe_notification_sent', $notification );
		}
	}

	/**
	 * For user.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_for_user( int $user_id, int $limit = 20 ): array {
		if ( $user_id < 1 ) {
			return array();
		}

		return $this->repo->get_by_user( $user_id, max( 1, $limit ) );
	}

	/**
	 * Unread count.
	 *
	 * @param int $user_id User id.
	 */
	public function get_unread_count( int $user_id ): int {
		if ( $user_id < 1 ) {
			return 0;
		}

		return $this->repo->get_unread_count( $user_id );
	}

	/**
	 * Mark read.
	 *
	 * @param int $notification_id Notification id.
	 */
	public function mark_read( int $notification_id ): bool {
		if ( $notification_id < 1 ) {
			return false;
		}

		return $this->repo->mark_read( $notification_id );
	}

	/**
	 * Mark all read.
	 *
	 * @param int $user_id User id.
	 */
	public function mark_all_read( int $user_id ): bool {
		if ( $user_id < 1 ) {
			return false;
		}

		return $this->repo->mark_all_read( $user_id );
	}
}
