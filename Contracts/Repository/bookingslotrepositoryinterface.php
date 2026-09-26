<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\BookingSlot;

defined( 'ABSPATH' ) || exit;

/** Class BookingSlotRepositoryInterface. */
interface BookingSlotRepositoryInterface {

	/**
	 * Fetch slots for a business, optionally filtered by day and activity.
	 *
	 * @return BookingSlot[]
	 * @param int  $business_id Business id.
	 * @param int  $day_of_week Day of week.
	 * @param bool $active_only Active only.
	 */
	public function get_for_business( int $business_id, int $day_of_week = -1, bool $active_only = true ): array;

	/**
	 * Fetch active slots for a business + service (day-aware).
	 *
	 * @return BookingSlot[]
	 * @param int $business_id Business id.
	 * @param int $service_id Service id.
	 * @param int $day_of_week Day of week.
	 */
	public function get_for_service( int $business_id, int $service_id, int $day_of_week = -1 ): array;

	/**
	 * Fetch a single slot by id.
	 *
	 * @param int $slot_id Slot id.
	 */
	public function get( int $slot_id ): ?BookingSlot;

	/**
	 * Insert a new slot.
	 *
	 * @return int New slot id, or 0 on failure.
	 * @param int   $business_id Business id.
	 * @param array $data Data.
	 */
	public function add( int $business_id, array $data ): int;

	/**
	 * Update an existing slot.
	 *
	 * @param int   $slot_id Slot id.
	 * @param array $data Data.
	 */
	public function update( int $slot_id, array $data ): bool;

	/**
	 * Toggle a slot active/inactive.
	 *
	 * @param int  $slot_id Slot id.
	 * @param bool $active Active.
	 */
	public function set_active( int $slot_id, bool $active ): bool;

	/**
	 * Delete a slot.
	 *
	 * @param int $slot_id Slot id.
	 */
	public function delete( int $slot_id ): bool;

	/**
	 * Delete all slots for a business.
	 *
	 * @param int $business_id Business id.
	 */
	public function delete_for_business( int $business_id ): bool;
}
