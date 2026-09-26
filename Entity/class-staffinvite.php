<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class StaffInvite. */
class StaffInvite {
	/**
	 * Id.
	 *
	 * @var int Id.
	 */
	public int $id = 0;
	/**
	 * Business id.
	 *
	 * @var int Business id.
	 */
	public int $business_id = 0;
	/**
	 * Email.
	 *
	 * @var string Email.
	 */
	public string $email = '';
	/**
	 * Role.
	 *
	 * @var string Role.
	 */
	public string $role = 'staff';
	/**
	 * Token.
	 *
	 * @var string Token.
	 */
	public string $token = '';
	/**
	 * Invited by.
	 *
	 * @var int Invited by.
	 */
	public int $invited_by = 0;
	/**
	 * Status.
	 *
	 * @var string Status.
	 */
	public string $status = 'pending';
	/**
	 * Expires at.
	 *
	 * @var ?string Expires at.
	 */
	public ?string $expires_at = null;
	/**
	 * Accepted at.
	 *
	 * @var ?string Accepted at.
	 */
	public ?string $accepted_at = null;
	/**
	 * Date created.
	 *
	 * @var string Date created.
	 */
	public string $date_created = '';

	/**
	 * From row.
	 *
	 * @param mixed $row Row.
	 */
	public static function from_row( $row ): self {
		$invite               = new self();
		$invite->id           = (int) $row->id;
		$invite->business_id  = (int) $row->business_id;
		$invite->email        = (string) $row->email;
		$invite->role         = (string) $row->role;
		$invite->token        = (string) $row->token;
		$invite->invited_by   = (int) $row->invited_by;
		$invite->status       = (string) $row->status;
		$invite->expires_at   = $row->expires_at ? (string) $row->expires_at : null;
		$invite->accepted_at  = $row->accepted_at ? (string) $row->accepted_at : null;
		$invite->date_created = (string) $row->date_created;
		return $invite;
	}

	/**
	 * Expired.
	 */
	public function is_expired(): bool {
		return null !== $this->expires_at && strtotime( $this->expires_at ) < time();
	}
}
