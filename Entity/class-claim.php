<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Claim. */
class Claim {

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
	 * Method.
	 *
	 * @var string Method.
	 */
	public string $method;
	/**
	 * Evidence.
	 *
	 * @var string Evidence.
	 */
	public string $evidence;
	/**
	 * Status.
	 *
	 * @var string Status.
	 */
	public string $status;
	/**
	 * Reviewed by.
	 *
	 * @var int Reviewed by.
	 */
	public int $reviewed_by;
	/**
	 * Reviewed at.
	 *
	 * @var string Reviewed at.
	 */
	public string $reviewed_at;
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
		$this->business_id  = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->user_id      = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$this->method       = isset( $data['method'] ) ? (string) $data['method'] : '';
		$this->evidence     = isset( $data['evidence'] ) ? (string) $data['evidence'] : '';
		$this->status       = isset( $data['status'] ) ? (string) $data['status'] : '';
		$this->reviewed_by  = isset( $data['reviewed_by'] ) ? (int) $data['reviewed_by'] : 0;
		$this->reviewed_at  = isset( $data['reviewed_at'] ) ? (string) $data['reviewed_at'] : '';
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
				'business_id'  => (int) $row->business_id,
				'user_id'      => (int) $row->user_id,
				'method'       => (string) $row->method,
				'evidence'     => (string) $row->evidence,
				'status'       => (string) $row->status,
				'reviewed_by'  => (int) $row->reviewed_by,
				'reviewed_at'  => (string) $row->reviewed_at,
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
