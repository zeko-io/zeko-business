<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Media. */
class Media {

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
	 * Attachment id.
	 *
	 * @var int Attachment id.
	 */
	public int $attachment_id;
	/**
	 * Caption.
	 *
	 * @var string Caption.
	 */
	public string $caption;
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
		$this->id            = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id   = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->attachment_id = isset( $data['attachment_id'] ) ? (int) $data['attachment_id'] : 0;
		$this->caption       = isset( $data['caption'] ) ? (string) $data['caption'] : '';
		$this->sort_order    = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
		$this->date_created  = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'            => (int) $row->id,
				'business_id'   => (int) $row->business_id,
				'attachment_id' => (int) $row->attachment_id,
				'caption'       => (string) $row->caption,
				'sort_order'    => (int) $row->sort_order,
				'date_created'  => (string) $row->date_created,
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
