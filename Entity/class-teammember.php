<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class TeamMember. */
class TeamMember {

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
	 * Role.
	 *
	 * @var string Role.
	 */
	public string $role;
	/**
	 * Permissions.
	 *
	 * @var array Permissions.
	 */
	public array $permissions;
	/**
	 * Invited by.
	 *
	 * @var int Invited by.
	 */
	public int $invited_by;
	/**
	 * Status.
	 *
	 * @var string Status.
	 */
	public string $status;
	/**
	 * Date added.
	 *
	 * @var string Date added.
	 */
	public string $date_added;

	/**
	 * Construct.
	 *
	 * @param array $data Data.
	 */
	public function __construct( array $data = array() ) {
		$this->id          = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->user_id     = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$this->role        = isset( $data['role'] ) ? (string) $data['role'] : '';
		$this->permissions = self::to_array_value( $data['permissions'] ?? array() );
		$this->invited_by  = isset( $data['invited_by'] ) ? (int) $data['invited_by'] : 0;
		$this->status      = isset( $data['status'] ) ? (string) $data['status'] : '';
		$this->date_added  = isset( $data['date_added'] ) ? (string) $data['date_added'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'          => (int) $row->id,
				'business_id' => (int) $row->business_id,
				'user_id'     => (int) $row->user_id,
				'role'        => (string) $row->role,
				'permissions' => self::to_array_value( $row->permissions ?? array() ),
				'invited_by'  => (int) $row->invited_by,
				'status'      => (string) $row->status,
				'date_added'  => (string) $row->date_added,
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

	/**
	 * To array value.
	 *
	 * @param mixed $value Value.
	 */
	private static function to_array_value( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );

			return is_array( $decoded ) ? $decoded : array();
		}

		return array();
	}
}
