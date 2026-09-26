<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class ServiceCategory. */
class ServiceCategory {

	/**
	 * Id.
	 *
	 * @var int Id.
	 */
	public int $id;
	/**
	 * Name.
	 *
	 * @var string Name.
	 */
	public string $name;
	/**
	 * Slug.
	 *
	 * @var string Slug.
	 */
	public string $slug;
	/**
	 * Description.
	 *
	 * @var string Description.
	 */
	public string $description;
	/**
	 * Icon.
	 *
	 * @var string Icon.
	 */
	public string $icon;
	/**
	 * Sort order.
	 *
	 * @var int Sort order.
	 */
	public int $sort_order;
	/**
	 * Is active.
	 *
	 * @var bool Is active.
	 */
	public bool $is_active;
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
		$this->id           = (int) ( $data['id'] ?? 0 );
		$this->name         = (string) ( $data['name'] ?? '' );
		$this->slug         = (string) ( $data['slug'] ?? '' );
		$this->description  = (string) ( $data['description'] ?? '' );
		$this->icon         = (string) ( $data['icon'] ?? '' );
		$this->sort_order   = (int) ( $data['sort_order'] ?? 0 );
		$this->is_active    = (bool) ( $data['is_active'] ?? true );
		$this->date_created = (string) ( $data['date_created'] ?? '' );
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'           => $row->id,
				'name'         => $row->name,
				'slug'         => $row->slug,
				'description'  => $row->description ?? '',
				'icon'         => $row->icon ?? '',
				'sort_order'   => $row->sort_order,
				'is_active'    => (bool) $row->is_active,
				'date_created' => $row->date_created ?? '',
			)
		);
	}

	/**
	 * To array.
	 */
	public function to_array(): array {
		return array(
			'id'           => $this->id,
			'name'         => $this->name,
			'slug'         => $this->slug,
			'description'  => $this->description,
			'icon'         => $this->icon,
			'sort_order'   => $this->sort_order,
			'is_active'    => $this->is_active,
			'date_created' => $this->date_created,
		);
	}
}
