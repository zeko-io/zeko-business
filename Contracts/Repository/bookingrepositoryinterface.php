<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\Booking;

defined( 'ABSPATH' ) || exit;

/** Class BookingRepositoryInterface. */
interface BookingRepositoryInterface {

	/**
	 * Create a booking record.
	 *
	 * @return int New booking id, or 0 on failure.
	 * @param array $data Data.
	 */
	public function add( array $data ): int;

	/**
	 * Fetch a single booking by id.
	 *
	 * @param int $booking_id Booking id.
	 */
	public function get( int $booking_id ): ?Booking;

	/**
	 * List bookings for a business (newest first), optionally by status.
	 *
	 * @return Booking[]
	 * @param int    $business_id Business id.
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 */
	public function get_for_business( int $business_id, string $status = '', int $limit = 50 ): array;

	/**
	 * List bookings for a user (newest first), optionally by status.
	 *
	 * @return Booking[]
	 * @param int    $user_id User id.
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 */
	public function get_for_user( int $user_id, string $status = '', int $limit = 50 ): array;

	/**
	 * Count bookings for a slot on a given date (used for capacity checks).
	 *
	 * @param int    $slot_id Slot id.
	 * @param string $booking_date Booking date.
	 * @param string $status Status.
	 */
	public function count_for_slot_date( int $slot_id, string $booking_date, string $status = 'confirmed' ): int;

	/**
	 * Update a booking (e.g. change status, payment tx id).
	 *
	 * @param int   $booking_id Booking id.
	 * @param array $data Data.
	 */
	public function update( int $booking_id, array $data ): bool;

	/**
	 * Delete a booking.
	 *
	 * @param int $booking_id Booking id.
	 */
	public function delete( int $booking_id ): bool;
}
