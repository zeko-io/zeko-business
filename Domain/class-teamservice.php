<?php
/**
 * Team domain service.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Domain;

use ZBE\Contracts\Repository\BusinessRepositoryInterface;
use ZBE\Contracts\Repository\TeamRepositoryInterface;
use ZBE\Entity\Business;
use ZBE\Entity\TeamMember;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/email-html.php';

/** Class TeamService. */
class TeamService {

	private const ROLES = array( 'manager', 'editor', 'staff' );

	/**
	 * Team.
	 *
	 * @var TeamRepositoryInterface Team.
	 */
	private TeamRepositoryInterface $team;
	/**
	 * Businesses.
	 *
	 * @var BusinessRepositoryInterface Businesses.
	 */
	private BusinessRepositoryInterface $businesses;
	/**
	 * Notifications.
	 *
	 * @var NotificationService Notifications.
	 */
	private NotificationService $notifications;

	/**
	 * Construct.
	 *
	 * @param TeamRepositoryInterface     $team Team.
	 * @param BusinessRepositoryInterface $businesses Businesses.
	 * @param NotificationService         $notifications Notifications.
	 */
	public function __construct(
		TeamRepositoryInterface $team,
		BusinessRepositoryInterface $businesses,
		NotificationService $notifications
	) {
		$this->team          = $team;
		$this->businesses    = $businesses;
		$this->notifications = $notifications;
	}

	/**
	 * Invite.
	 *
	 * @param int    $business_id Business id.
	 * @param string $email Email.
	 * @param string $role Role.
	 * @param int    $invited_by Invited by.
	 */
	public function invite( int $business_id, string $email, string $role, int $invited_by ): ?TeamMember {
		$business = $this->businesses->get( $business_id );
		$user     = get_user_by( 'email', $email );

		if ( null === $business || ! $user || $invited_by < 1 ) {
			return null;
		}

		if ( $this->team->is_member( (int) $user->ID, $business_id ) ) {
			return null;
		}

		$member = $this->team->create(
			array(
				'business_id' => $business_id,
				'user_id'     => (int) $user->ID,
				'role'        => $this->sanitize_role( $role ),
				'invited_by'  => $invited_by,
				'status'      => 'active',
				'date_added'  => current_time( 'mysql' ),
			)
		);

		if ( $member->id < 1 ) {
			return null;
		}

		$this->notifications->send(
			(int) $user->ID,
			'staff_invited',
			__( 'Added to a business team', 'zeko-business' ),
			/* translators: 1: team role. 2: business name */
			sprintf( __( 'You have been added as %1$s on "%2$s".', 'zeko-business' ), $member->role, $business->name ),
			'',
			$business_id
		);

		do_action( 'zbe_team_member_invited', $member, $invited_by );

		return $member;
	}

	/**
	 * Send an external invitation by email.
	 *
	 * @param int    $business_id Business id.
	 * @param string $email Email.
	 * @param string $role Role.
	 * @param int    $invited_by Invited by.
	 */
	public function invite_external( int $business_id, string $email, string $role, int $invited_by ): ?object {
		$business = $this->businesses->get( $business_id );

		if ( null === $business || $invited_by < 1 || '' === trim( $email ) ) {
			return null;
		}

		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user && $this->team->is_member( (int) $existing_user->ID, $business_id ) ) {
			return null;
		}

		$invites = \ZBE\Core\Services::staff_invites();
		$token   = wp_generate_password( 32, false );

		$invite = $invites->create(
			array(
				'business_id' => $business_id,
				'email'       => $email,
				'role'        => $this->sanitize_role( $role ),
				'token'       => $token,
				'invited_by'  => $invited_by,
				'status'      => 'pending',
				'expires_at'  => gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) ),
			)
		);

		if ( $invite->id < 1 ) {
			return null;
		}

		$accept_url = add_query_arg(
			array( 'zbp_invite' => $token ),
			home_url( '/business-portal/' )
		);

		$inviter      = get_userdata( $invited_by );
		$inviter_name = $inviter ? $inviter->display_name : __( 'A team member', 'zeko-business' );

		$subject = sprintf(
			/* translators: 1: inviter name, 2: business name */
			__( '%1$s invited you to join %2$s', 'zeko-business' ),
			$inviter_name,
			$business->name
		);

		$message = sprintf(
			/* translators: 1: inviter name, 2: business name, 3: role, 4: accept URL, 5: expiry date */
			__( "Hello!\n\n%1\$s has invited you to join %2\$s as a %3\$s.\n\nClick here to accept the invitation:\n%4\$s\n\nThis invitation expires on %5\$s.\n\nIf you don't have an account yet, you'll be prompted to create one after clicking the link.", 'zeko-business' ),
			$inviter_name,
			$business->name,
			$this->sanitize_role( $role ),
			$accept_url,
			wp_date( get_option( 'date_format' ), strtotime( $invite->expires_at ) )
		);

		$email_parts = zbe_wrap_email( $message, $business->name, '', $subject );
		$headers     = $email_parts['html'] ? array( 'Content-Type: text/html; charset=UTF-8' ) : array();

		wp_mail( $email, $subject, $email_parts['body'], $headers );

		$this->notifications->send(
			$invited_by,
			'invite_sent',
			__( 'Invitation sent', 'zeko-business' ),
			/* translators: %s: email address */
			sprintf( __( 'Invitation sent to %s.', 'zeko-business' ), $email ),
			'',
			$business_id
		);

		do_action( 'zbe_external_invite_sent', $invite, $business, $invited_by );

		return $invite;
	}

	/**
	 * Accept a pending external invite by token.
	 *
	 * @param string $token Token.
	 * @param int    $user_id User id.
	 */
	public function accept_invite( string $token, int $user_id ): ?TeamMember {
		$invites = \ZBE\Core\Services::staff_invites();
		$invite  = $invites->get_by_token( $token );

		if ( null === $invite || $invite->is_expired() ) {
			return null;
		}

		$user  = get_userdata( $user_id );
		$match = $user && strcasecmp( $user->user_email, $invite->email ) === 0;

		if ( ! $match ) {
			return null;
		}

		if ( $this->team->is_member( $user_id, $invite->business_id ) ) {
			$invites->update( $invite->id, array( 'status' => 'already_member' ) );
			return null;
		}

		$member = $this->team->create(
			array(
				'business_id' => $invite->business_id,
				'user_id'     => $user_id,
				'role'        => $invite->role,
				'invited_by'  => $invite->invited_by,
				'status'      => 'active',
				'date_added'  => current_time( 'mysql' ),
			)
		);

		if ( $member->id < 1 ) {
			return null;
		}

		$invites->update(
			$invite->id,
			array(
				'status'      => 'accepted',
				'accepted_at' => current_time( 'mysql' ),
			)
		);

		$business = $this->businesses->get( $invite->business_id );

		if ( $business ) {
			$this->notifications->send(
				$user_id,
				'invite_accepted',
				__( 'Invitation accepted', 'zeko-business' ),
				/* translators: 1: team role. 2: business name */
				sprintf( __( 'You are now a %1$s on "%2$s".', 'zeko-business' ), $invite->role, $business->name ),
				'',
				$invite->business_id
			);

			$this->notifications->send(
				$invite->invited_by,
				'staff_joined',
				__( 'New team member joined', 'zeko-business' ),
				/* translators: 1: user name. 2: business name. 3: team role */
				sprintf( __( '%1$s accepted your invitation and joined "%2$s" as %3$s.', 'zeko-business' ), $user->display_name, $business->name, $invite->role ),
				'',
				$invite->business_id
			);
		}

		do_action( 'zbe_external_invite_accepted', $member, $invite );

		return $member;
	}

	/**
	 * Revoke a pending invite.
	 *
	 * @param int $invite_id Invite id.
	 * @param int $user_id User id.
	 */
	public function revoke_invite( int $invite_id, int $user_id ): bool {
		$invites = \ZBE\Core\Services::staff_invites();
		$invite  = $invites->get( $invite_id );

		if ( null === $invite ) {
			return false;
		}

		$business = $this->businesses->get( $invite->business_id );
		if ( null === $business || ! $this->can_manage( $business, $user_id ) ) {
			return false;
		}

		return $invites->update( $invite_id, array( 'status' => 'revoked' ) );
	}

	/**
	 * Resend a pending invite.
	 *
	 * @param int $invite_id Invite id.
	 * @param int $user_id User id.
	 */
	public function resend_invite( int $invite_id, int $user_id ): ?object {
		$invites = \ZBE\Core\Services::staff_invites();
		$invite  = $invites->get( $invite_id );

		if ( null === $invite || 'pending' !== $invite->status ) {
			return null;
		}

		$business = $this->businesses->get( $invite->business_id );
		if ( null === $business || ! $this->can_manage( $business, $user_id ) ) {
			return null;
		}

		$new_token = wp_generate_password( 32, false );
		$invites->update(
			$invite_id,
			array(
				'token'      => $new_token,
				'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) ),
			)
		);

		$invite->token      = $new_token;
		$invite->expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) );

		$accept_url = add_query_arg(
			array( 'zbp_invite' => $new_token ),
			home_url( '/business-portal/' )
		);

		$inviter      = get_userdata( $user_id );
		$inviter_name = $inviter ? $inviter->display_name : __( 'A team member', 'zeko-business' );

		$reminder_subject = sprintf(
			/* translators: 1: business name */
			__( 'Reminder: Join %1$s', 'zeko-business' ),
			$business->name
		);
		$reminder_body = sprintf(
			/* translators: 1: inviter name, 2: business name, 3: role, 4: accept URL, 5: expiry date */
			__( "%1\$s still wants you to join %2\$s as a %3\$s.\n\nClick here to accept:\n%4\$s\n\nThis invitation expires on %5\$s.", 'zeko-business' ),
			$inviter_name,
			$business->name,
			$invite->role,
			$accept_url,
			wp_date( get_option( 'date_format' ), strtotime( $invite->expires_at ) )
		);

		$email_parts = zbe_wrap_email( $reminder_body, $business->name, '', $reminder_subject );
		$headers     = $email_parts['html'] ? array( 'Content-Type: text/html; charset=UTF-8' ) : array();

		wp_mail( $invite->email, $reminder_subject, $email_parts['body'], $headers );

		return $invite;
	}

	/**
	 * Remove.
	 *
	 * @param int $member_id Member id.
	 * @param int $user_id User id.
	 */
	public function remove( int $member_id, int $user_id ): bool {
		$member   = $this->team->get( $member_id );
		$business = null !== $member ? $this->businesses->get( $member->business_id ) : null;

		if ( null === $member || null === $business || ! $this->can_manage( $business, $user_id ) ) {
			return false;
		}

		if ( ! $this->team->update( $member_id, array( 'status' => 'removed' ) ) ) {
			return false;
		}

		$this->notifications->send(
			$member->user_id,
			'staff_removed',
			__( 'Removed from business team', 'zeko-business' ),
			/* translators: %s: business name */
			sprintf( __( 'You are no longer a team member of "%s".', 'zeko-business' ), $business->name ),
			'',
			$business->id
		);

		do_action( 'zbe_team_member_removed', $member_id, $user_id );

		return true;
	}

	/**
	 * Update role.
	 *
	 * @param int    $member_id Member id.
	 * @param string $role Role.
	 * @param int    $user_id User id.
	 */
	public function update_role( int $member_id, string $role, int $user_id ): bool {
		$member   = $this->team->get( $member_id );
		$business = null !== $member ? $this->businesses->get( $member->business_id ) : null;

		if ( null === $member || null === $business || ! $this->can_manage( $business, $user_id ) ) {
			return false;
		}

		$new_role = $this->sanitize_role( $role );

		if ( ! $this->team->update( $member_id, array( 'role' => $new_role ) ) ) {
			return false;
		}

		do_action( 'zbe_team_member_role_updated', $member_id, $new_role, $user_id );

		return true;
	}

	/**
	 * Can manage.
	 *
	 * @param Business $business Business.
	 * @param int      $user_id User id.
	 */
	private function can_manage( Business $business, int $user_id ): bool {
		if ( $user_id > 0 && $user_id === $business->owner_id ) {
			return true;
		}

		return user_can( $user_id, 'edit_others_businesses' );
	}

	/**
	 * Sanitize role.
	 *
	 * @param string $role Role.
	 */
	private function sanitize_role( string $role ): string {
		$role = sanitize_key( $role );

		return in_array( $role, self::ROLES, true ) ? $role : 'staff';
	}
}
