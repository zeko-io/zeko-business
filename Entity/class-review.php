<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Review. */
class Review {

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
	 * User id.
	 *
	 * @var int User id.
	 */
	public int $user_id;
	/**
	 * Order id.
	 *
	 * @var int Order id.
	 */
	public int $order_id;
	/**
	 * Rating.
	 *
	 * @var int Rating.
	 */
	public int $rating;
	/**
	 * Title.
	 *
	 * @var string Title.
	 */
	public string $title;
	/**
	 * Content.
	 *
	 * @var string Content.
	 */
	public string $content;
	/**
	 * Criteria.
	 *
	 * @var array Criteria.
	 */
	public array $criteria;
	/**
	 * Status.
	 *
	 * @var string Status.
	 */
	public string $status;
	/**
	 * Is verified purchase.
	 *
	 * @var bool Is verified purchase.
	 */
	public bool $is_verified_purchase;
	/**
	 * Admin reply.
	 *
	 * @var string Admin reply.
	 */
	public string $admin_reply;
	/**
	 * Photos.
	 *
	 * @var array Photos.
	 */
	public array $photos;
	/**
	 * Helpful count.
	 *
	 * @var int Helpful count.
	 */
	public int $helpful_count;
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
		$this->id                   = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id          = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->user_id              = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$this->order_id             = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
		$this->rating               = isset( $data['rating'] ) ? (int) $data['rating'] : 0;
		$this->title                = isset( $data['title'] ) ? (string) $data['title'] : '';
		$this->content              = isset( $data['content'] ) ? (string) $data['content'] : '';
		$this->criteria             = is_array( $data['criteria'] ?? null ) ? $data['criteria'] : array();
		$this->status               = isset( $data['status'] ) ? (string) $data['status'] : '';
		$this->is_verified_purchase = ! empty( $data['is_verified_purchase'] );
		$this->admin_reply          = isset( $data['admin_reply'] ) ? (string) $data['admin_reply'] : '';
		$this->photos               = (array) ( $data['photos'] ?? array() );
		$this->helpful_count        = isset( $data['helpful_count'] ) ? (int) $data['helpful_count'] : 0;
		$this->date_created         = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		$photos   = array();
		$criteria = array();

		if ( isset( $row->photos ) && ! empty( $row->photos ) ) {
			$decoded = json_decode( (string) $row->photos, true );
			if ( is_array( $decoded ) ) {
				$photos = array_map( 'absint', array_values( array_filter( $decoded ) ) );
			}
		}

		if ( isset( $row->criteria ) && ! empty( $row->criteria ) ) {
			$decoded = json_decode( (string) $row->criteria, true );
			if ( is_array( $decoded ) ) {
				$criteria = $decoded;
			}
		}

		return new self(
			array(
				'id'                   => (int) $row->id,
				'business_id'          => (int) $row->business_id,
				'user_id'              => (int) $row->user_id,
				'order_id'             => (int) $row->order_id,
				'rating'               => (int) $row->rating,
				'title'                => (string) $row->title,
				'content'              => (string) $row->content,
				'criteria'             => $criteria,
				'status'               => (string) $row->status,
				'is_verified_purchase' => (bool) $row->is_verified_purchase,
				'admin_reply'          => (string) $row->admin_reply,
				'photos'               => $photos,
				'helpful_count'        => isset( $row->helpful_count ) ? (int) $row->helpful_count : 0,
				'date_created'         => (string) $row->date_created,
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
