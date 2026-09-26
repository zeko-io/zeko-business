<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

use ZBE\Entity\Location;

defined( 'ABSPATH' ) || exit;

/** Class LocationRepositoryInterface. */
interface LocationRepositoryInterface {

	/**
	 * Fetch all locations for a business, primary first.
	 *
	 * @return Location[]
	 * @param int $business_id Business id.
	 */
	public function get_for_business( int $business_id ): array;

	/**
	 * Fetch a single location by id (optionally constrained to a business).
	 *
	 * @param int $location_id Location id.
	 * @param int $business_id Business id.
	 */
	public function get( int $location_id, int $business_id = 0 ): ?Location;

	/**
	 * Fetch the primary location for a business, or the first if none is flagged.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_primary_for_business( int $business_id ): ?Location;

	/**
	 * Insert a new location for a business.
	 *
	 * @return int New location id, or 0 on failure.
	 * @param int   $business_id Business id.
	 * @param array $data Location column values (label, address, city, etc.).
	 */
	public function add( int $business_id, array $data ): int;

	/**
	 * Update an existing location (by id).
	 *
	 * @return bool Whether the row existed and was updated.
	 * @param int   $location_id Location id.
	 * @param array $data Data.
	 */
	public function update( int $location_id, array $data ): bool;

	/**
	 * Delete a location (by id, optionally constrained to a business).
	 *
	 * @param int $location_id Location id.
	 * @param int $business_id Business id.
	 */
	public function delete( int $location_id, int $business_id = 0 ): bool;

	/**
	 * Delete all locations for a business (used for cleanup).
	 *
	 * @param int $business_id Business id.
	 */
	public function delete_for_business( int $business_id ): bool;
}
