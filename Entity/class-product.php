<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Product. */
class Product {

	/**
	 * Id.
	 *
	 * @var int Id.
	 */
	public int $id;
	/**
	 * Business id.
	 *
	 * @var int Business id.
	 */
	public int $business_id;
	/**
	 * Name.
	 *
	 * @var string Name.
	 */
	public string $name;
	/**
	 * Description.
	 *
	 * @var string Description.
	 */
	public string $description;
	/**
	 * Price.
	 *
	 * @var float Price.
	 */
	public float $price;
	/**
	 * Sale price.
	 *
	 * @var float Sale price.
	 */
	public float $sale_price;
	/**
	 * Category.
	 *
	 * @var string Category.
	 */
	public string $category;
	/**
	 * Image id.
	 *
	 * @var int Image id.
	 */
	public int $image_id;
	/**
	 * Gallery ids.
	 *
	 * @var int Gallery ids.
	 */
	public int $gallery_ids;
	/**
	 * Stock quantity.
	 *
	 * @var int Stock quantity.
	 */
	public int $stock_quantity;
	/**
	 * Stock status.
	 *
	 * @var string Stock status.
	 */
	public string $stock_status;
	/**
	 * Is active.
	 *
	 * @var bool Is active.
	 */
	public bool $is_active;
	/**
	 * Is featured.
	 *
	 * @var bool Is featured.
	 */
	public bool $is_featured;
	/**
	 * Sku.
	 *
	 * @var string Sku.
	 */
	public string $sku;
	/**
	 * Date created.
	 *
	 * @var string Date created.
	 */
	public string $date_created;
	/**
	 * Date modified.
	 *
	 * @var string Date modified.
	 */
	public string $date_modified;

	/**
	 * Construct.
	 *
	 * @param array $data Data.
	 */
	public function __construct( array $data = array() ) {
		$this->id             = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id    = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->name           = isset( $data['name'] ) ? (string) $data['name'] : '';
		$this->description    = isset( $data['description'] ) ? (string) $data['description'] : '';
		$this->price          = isset( $data['price'] ) ? (float) $data['price'] : 0.0;
		$this->sale_price     = isset( $data['sale_price'] ) ? (float) $data['sale_price'] : 0.0;
		$this->category       = isset( $data['category'] ) ? (string) $data['category'] : '';
		$this->image_id       = isset( $data['image_id'] ) ? (int) $data['image_id'] : 0;
		$this->gallery_ids    = isset( $data['gallery_ids'] ) ? (int) $data['gallery_ids'] : 0;
		$this->stock_quantity = isset( $data['stock_quantity'] ) ? (int) $data['stock_quantity'] : 0;
		$this->stock_status   = isset( $data['stock_status'] ) ? (string) $data['stock_status'] : 'in_stock';
		$this->is_active      = ! empty( $data['is_active'] );
		$this->is_featured    = ! empty( $data['is_featured'] );
		$this->sku            = isset( $data['sku'] ) ? (string) $data['sku'] : '';
		$this->date_created   = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
		$this->date_modified  = isset( $data['date_modified'] ) ? (string) $data['date_modified'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'             => (int) $row->id,
				'business_id'    => (int) $row->business_id,
				'name'           => (string) $row->name,
				'description'    => (string) ( $row->description ?? '' ),
				'price'          => (float) $row->price,
				'sale_price'     => (float) ( $row->sale_price ?? 0 ),
				'category'       => (string) ( $row->category ?? '' ),
				'image_id'       => (int) ( $row->image_id ?? 0 ),
				'gallery_ids'    => (int) ( $row->gallery_ids ?? 0 ),
				'stock_quantity' => (int) ( $row->stock_quantity ?? 0 ),
				'stock_status'   => (string) ( $row->stock_status ?? 'in_stock' ),
				'is_active'      => (bool) $row->is_active,
				'is_featured'    => (bool) ( $row->is_featured ?? 0 ),
				'sku'            => (string) ( $row->sku ?? '' ),
				'date_created'   => (string) $row->date_created,
				'date_modified'  => (string) ( $row->date_modified ?? '' ),
			)
		);
	}

	/**
	 * On sale.
	 */
	public function is_on_sale(): bool {
		return $this->sale_price > 0 && $this->sale_price < $this->price;
	}

	/**
	 * To array.
	 */
	public function to_array(): array {
		$data = array();
		foreach ( get_object_vars( $this ) as $key => $value ) {
			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}
			$data[ $key ] = $value;
		}
		return $data;
	}
}
