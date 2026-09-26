<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\BusinessHour;

defined( 'ABSPATH' ) || exit;

/** Class HoursRepositoryInterface. */
interface HoursRepositoryInterface {

	/**
	 * For business.
	 *
	 * @return BusinessHour[]
	 * @param int $business_id Business id.
	 */
	public function get_for_business( int $business_id ): array;

	/**
	 * Replace the whole weekly schedule for a business atomically.
	 *
	 * @param int   $business_id Business id.
	 * @param array $rows Array of ['day_of_week'=>int,'open'=>string,'close'=>string,'closed'=>bool].
	 */
	public function replace_for_business( int $business_id, array $rows ): bool;

	/**
	 * Delete all hours for a business (used for cleanup).
	 *
	 * @param int $business_id Business id.
	 */
	public function delete_for_business( int $business_id ): bool;
}
