<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Integration\Adapters;

defined( 'ABSPATH' ) || exit;

/** Class ShopAdapter. */
class ShopAdapter {
	/**
	 * Active.
	 *
	 * @var bool Active.
	 */
	private bool $active;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->active = class_exists( 'Zeko_Shop' );
	}

	/**
	 * Active.
	 */
	public function is_active(): bool {
		return $this->active;
	}

	/**
	 * Business products.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_business_products( int $business_id, int $limit = 20 ): array {
		if ( ! $this->active ) {
			return array();
		}

		$db       = \Zeko_Shop::instance()->get_db();
		$products = $db->get_products_by_external( 'business', $business_id );

		return array_slice( $products, 0, $limit );
	}

	/**
	 * Count business products.
	 *
	 * @param int $business_id Business id.
	 */
	public function count_business_products( int $business_id ): int {
		if ( ! $this->active ) {
			return 0;
		}

		$db       = \Zeko_Shop::instance()->get_db();
		$products = $db->get_products_by_external( 'business', $business_id );

		return count( $products );
	}
}
