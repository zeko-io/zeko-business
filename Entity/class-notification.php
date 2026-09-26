<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Notification. */
class Notification {

	/**
	 * Id.
	 *
	 * @var int Id.
	 */
	public int $id;
	/**
	 * User id.
	 *
	 * @var int User id.
	 */
	public int $user_id;
	/**
	 * Business id.
	 *
	 * @var int Business id.
	 */
	public int $business_id;
	/**
	 * Type.
	 *
	 * @var string Type.
	 */
	public string $type;
	/**
	 * Title.
	 *
	 * @var string Title.
	 */
	public string $title;
	/**
	 * Message.
	 *
	 * @var string Message.
	 */
	public string $message;
	/**
	 * Link.
	 *
	 * @var string Link.
	 */
	public string $link;
	/**
	 * Is read.
	 *
	 * @var bool Is read.
	 */
	public bool $is_read;
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
		$this->id           = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->user_id      = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$this->business_id  = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->type         = isset( $data['type'] ) ? (string) $data['type'] : '';
		$this->title        = isset( $data['title'] ) ? (string) $data['title'] : '';
		$this->message      = isset( $data['message'] ) ? (string) $data['message'] : '';
		$this->link         = isset( $data['link'] ) ? (string) $data['link'] : '';
		$this->is_read      = ! empty( $data['is_read'] );
		$this->date_created = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'           => (int) $row->id,
				'user_id'      => (int) $row->user_id,
				'business_id'  => (int) $row->business_id,
				'type'         => (string) $row->type,
				'title'        => (string) $row->title,
				'message'      => (string) $row->message,
				'link'         => (string) $row->link,
				'is_read'      => (bool) $row->is_read,
				'date_created' => (string) $row->date_created,
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
