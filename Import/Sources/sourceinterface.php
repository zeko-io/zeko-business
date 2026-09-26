<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for directory-plugin migration sources.
 *
 * Every source adapter only READs the foreign plugin's data and normalizes it
 * into Zeko Business "row" shapes. All persistence (zbp_* tables, terms,
 * media, import log) happens in the Migrator so that every source goes
 * through the exact same pipeline.
 *
 * @see \ZBE\Import\Migrator
 */
interface SourceInterface {

	/**
	 * Stable machine key used in the import log (source column).
	 */
	public function key(): string;

	/**
	 * Human-readable plugin name.
	 */
	public function label(): string;

	/**
	 * Detected plugin version (free text).
	 */
	public function version(): string;

	/**
	 * Whether the source data exists on this install.
	 */
	public function present(): bool;

	/**
	 * 'active' (plugin activated), 'inactive' (data present, plugin off) or 'unknown'.
	 */
	public function status(): string;

	/**
	 * Human-readable data scope (e.g. "Listings, categories, photos, reviews").
	 */
	public function capabilities(): string;

	/**
	 * Normalized data for migration.
	 * source: string,
	 * capabilities: string,
	 * warnings: string[],
	 * businesses: array<int, array{
	 * post_id: int, owner_id: int, title: string, slug: string, status: string,
	 * email: string, phone: string, website: string, whatsapp: string,
	 * address: string, city: string, state: string, country: string, zip: string,
	 * lat: float, lng: float, description: string, featured: bool, is_verified: bool,
	 * date_created: string, categories: string[],
	 * photos: array<int, array{src: int|string, caption: string, cover: bool}>,
	 * reviews: array<int, array{user_id: int, rating: int, title: string, content: string, status: string, date_created: string}>,
	 * followers: int[]
	 * }>
	 * }
	 *
	 * @return array{
	 */
	public function collect(): array;
}
