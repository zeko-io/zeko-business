<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Service. */
class Service {

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
	 * Short description.
	 *
	 * @var string Short description.
	 */
	public string $short_description;
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
	 * Price type.
	 *
	 * @var string Price type.
	 */
	public string $price_type;
	/**
	 * Price note.
	 *
	 * @var string Price note.
	 */
	public string $price_note;
	/**
	 * Image id.
	 *
	 * @var int Image id.
	 */
	public int $image_id;
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
	 * Category.
	 *
	 * @var string Category.
	 */
	public string $category;
	/**
	 * Duration.
	 *
	 * @var string Duration.
	 */
	public string $duration;
	/**
	 * Tags.
	 *
	 * @var string Tags.
	 */
	public string $tags;
	/**
	 * Sort order.
	 *
	 * @var int Sort order.
	 */
	public int $sort_order;
	/**
	 * Date created.
	 *
	 * @var string Date created.
	 */
	public string $date_created;

	/**
	 * Construct.
	 *
	 * @param array $data Data.
	 */
	public function __construct( array $data = array() ) {
		$this->id                = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id       = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->name              = isset( $data['name'] ) ? (string) $data['name'] : '';
		$this->short_description = isset( $data['short_description'] ) ? (string) $data['short_description'] : '';
		$this->description       = isset( $data['description'] ) ? (string) $data['description'] : '';
		$this->price             = isset( $data['price'] ) ? (float) $data['price'] : 0.0;
		$this->price_type        = isset( $data['price_type'] ) ? (string) $data['price_type'] : '';
		$this->price_note        = isset( $data['price_note'] ) ? (string) $data['price_note'] : '';
		$this->image_id          = isset( $data['image_id'] ) ? (int) $data['image_id'] : 0;
		$this->is_active         = ! empty( $data['is_active'] );
		$this->is_featured       = ! empty( $data['is_featured'] );
		$this->category          = isset( $data['category'] ) ? (string) $data['category'] : '';
		$this->duration          = isset( $data['duration'] ) ? (string) $data['duration'] : '';
		$this->tags              = isset( $data['tags'] ) ? (string) $data['tags'] : '';
		$this->sort_order        = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
		$this->date_created      = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'                => (int) $row->id,
				'business_id'       => (int) $row->business_id,
				'name'              => (string) $row->name,
				'short_description' => (string) ( $row->short_description ?? '' ),
				'description'       => (string) $row->description,
				'price'             => (float) $row->price,
				'price_type'        => (string) $row->price_type,
				'price_note'        => (string) $row->price_note,
				'image_id'          => (int) $row->image_id,
				'is_active'         => (bool) $row->is_active,
				'is_featured'       => (bool) $row->is_featured,
				'category'          => (string) $row->category,
				'duration'          => (string) ( $row->duration ?? '' ),
				'tags'              => (string) ( $row->tags ?? '' ),
				'sort_order'        => (int) $row->sort_order,
				'date_created'      => (string) $row->date_created,
			)
		);
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
