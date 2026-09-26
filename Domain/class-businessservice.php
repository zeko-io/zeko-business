<?php
/**
 * Business domain service.
 *
 * Orchestrates business lifecycle: creation, moderation, featuring,
 * following, claiming and verification.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Domain;

use ZBE\Contracts\Repository\BusinessRepositoryInterface;
use ZBE\Contracts\Repository\ClaimRepositoryInterface;
use ZBE\Contracts\Repository\FollowerRepositoryInterface;
use ZBE\Contracts\Repository\ServiceRepositoryInterface;
use ZBE\Contracts\Repository\TeamRepositoryInterface;
use ZBE\Contracts\Repository\VerificationRepositoryInterface;
use ZBE\Core\Plans;
use ZBE\Entity\Business;
use ZBE\Entity\Claim;
use ZBE\Entity\VerificationRequest;

defined( 'ABSPATH' ) || exit;

/** Class BusinessService. */
class BusinessService {

	/**
	 * EDITABLE FIELDS.
	 *
	 * @var mixed
	 */
	private const EDITABLE_FIELDS = array(
		'name',
		'status',
		'phone',
		'website',
		'email',
		'whatsapp',
		'address',
		'city',
		'state',
		'country',
		'zip',
		'lat',
		'lng',
		'avatar_id',
		'cover_id',
		'business_hours',
		'social_links',
		'action_buttons',
		'extra_data',
		'timezone',
		'hours_mode',
		'group_id',
		'plan',
	);

	/**
	 * Repo.
	 *
	 * @var BusinessRepositoryInterface Repo.
	 */
	private BusinessRepositoryInterface $repo;
	/**
	 * Followers.
	 *
	 * @var FollowerRepositoryInterface Followers.
	 */
	private FollowerRepositoryInterface $followers;
	/**
	 * Claims.
	 *
	 * @var ClaimRepositoryInterface Claims.
	 */
	private ClaimRepositoryInterface $claims;
	/**
	 * Verification.
	 *
	 * @var VerificationRepositoryInterface Verification.
	 */
	private VerificationRepositoryInterface $verification;
	/**
	 * Analytics.
	 *
	 * @var AnalyticsService Analytics.
	 */
	private AnalyticsService $analytics;
	/**
	 * Services.
	 *
	 * @var ServiceRepositoryInterface Services.
	 */
	private ServiceRepositoryInterface $services;
	/**
	 * Team.
	 *
	 * @var TeamRepositoryInterface Team.
	 */
	private TeamRepositoryInterface $team;
	/**
	 * Notifications.
	 *
	 * @var NotificationService Notifications.
	 */
	private NotificationService $notifications;

	/**
	 * Construct.
	 *
	 * @param BusinessRepositoryInterface     $repo Repo.
	 * @param FollowerRepositoryInterface     $followers Followers.
	 * @param ClaimRepositoryInterface        $claims Claims.
	 * @param VerificationRepositoryInterface $verification Verification.
	 * @param ServiceRepositoryInterface      $services Services.
	 * @param TeamRepositoryInterface         $team Team.
	 * @param NotificationService             $notifications Notifications.
	 * @param AnalyticsService                $analytics Analytics.
	 */
	public function __construct(
		BusinessRepositoryInterface $repo,
		FollowerRepositoryInterface $followers,
		ClaimRepositoryInterface $claims,
		VerificationRepositoryInterface $verification,
		ServiceRepositoryInterface $services,
		TeamRepositoryInterface $team,
		NotificationService $notifications,
		AnalyticsService $analytics
	) {
		$this->repo          = $repo;
		$this->followers     = $followers;
		$this->claims        = $claims;
		$this->verification  = $verification;
		$this->services      = $services;
		$this->team          = $team;
		$this->notifications = $notifications;
		$this->analytics     = $analytics;
	}

	/**
	 * Create a business owned by $owner_id, starting in pending status.
	 *
	 * @return Business|null The created entity or null on validation failure.
	 * @param array $data Data.
	 * @param int   $owner_id Owner id.
	 */
	public function create( array $data, int $owner_id ): ?Business {
		$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';

		if ( '' === $name || $owner_id < 1 ) {
			return null;
		}

		$slug        = $this->unique_slug(
			isset( $data['slug'] ) && '' !== trim( (string) $data['slug'] ) ? (string) $data['slug'] : $name
		);
		$post_id     = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;
		$description = isset( $data['description'] ) ? (string) $data['description'] : '';
		$excerpt     = isset( $data['excerpt'] ) ? (string) $data['excerpt'] : '';
		$status      = $this->initial_status( isset( $data['status'] ) ? (string) $data['status'] : '', $owner_id );
		unset( $data['status'], $data['description'], $data['excerpt'] );

		if ( $post_id < 1 ) {
			$post_id = $this->insert_wp_post(
				$name,
				$slug,
				$description,
				$excerpt,
				$owner_id,
				'active' === $status ? 'publish' : 'pending'
			);
		}

		$row = array_merge(
			$data,
			array(
				'name'     => $name,
				'slug'     => $slug,
				'owner_id' => $owner_id,
				'status'   => $status,
				'post_id'  => $post_id,
			)
		);

		$business = $this->repo->create( $row );

		if ( null === $business ) {
			return null;
		}

		if ( $post_id > 0 ) {
			update_post_meta( $post_id, '_zbe_business_id', $business->id );
		}

		do_action( 'zbe_business_created', $business, $owner_id );

		if ( 'active' === $status ) {
			do_action( 'zbe_business_approved', $business->id );
		}

		self::flush_directory_cache();

		return $business;
	}

	/**
	 * Resolve the starting status for a submission. Privileged users may
	 * publish directly; everybody else lands in the site default
	 * (Settings → General → default status) so moderation holds.
	 *
	 * @param string $requested Requested.
	 * @param int    $owner_id Owner id.
	 */
	private function initial_status( string $requested, int $owner_id ): string {
		$allowed = array( 'pending', 'active' );
		$default = '';

		$settings = get_option( 'zbe_settings', array() );
		if ( is_array( $settings ) && isset( $settings['general']['default_status'] ) ) {
			$default = sanitize_key( (string) $settings['general']['default_status'] );
		}

		if ( ! in_array( $default, $allowed, true ) ) {
			$default = 'pending';
		}

		$requested = sanitize_key( $requested );

		if ( '' !== $requested && in_array( $requested, $allowed, true ) && user_can( $owner_id, 'publish_businesses' ) ) {
			return $requested;
		}

		return $default;
	}

	/**
	 * Update an existing business. Only the owner or an editor-level
	 * user may do so. Name/description changes are mirrored to the WP post.
	 *
	 * @param int   $id Id.
	 * @param array $data Data.
	 * @param int   $user_id User id.
	 */
	public function update( int $id, array $data, int $user_id ): bool {
		$business = $this->repo->get( $id );

		if ( null === $business || ! $this->can_manage( $business, $user_id ) ) {
			return false;
		}

		$fields = array_intersect_key( $data, array_flip( self::EDITABLE_FIELDS ) );

		if ( isset( $fields['name'] ) ) {
			$fields['name'] = trim( (string) $fields['name'] );

			if ( '' === $fields['name'] ) {
				unset( $fields['name'] );
			}
		}

		if ( isset( $fields['plan'] ) && ! Plans::is_valid( $fields['plan'] ) ) {
			unset( $fields['plan'] );
		}

		if ( empty( $fields ) ) {
			return false;
		}

		if ( ! $this->repo->update( $id, $fields ) ) {
			return false;
		}

		if ( $business->post_id > 0 ) {
			$this->sync_wp_post( $business, $fields, isset( $data['description'] ) ? (string) $data['description'] : null, isset( $data['excerpt'] ) ? (string) $data['excerpt'] : null );
		}

		do_action( 'zbe_business_updated', $id, $fields, $user_id );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 * @param int $user_id User id.
	 */
	public function delete( int $id, int $user_id ): bool {
		$business = $this->repo->get( $id );

		if ( null === $business || ! $this->can_manage( $business, $user_id ) ) {
			return false;
		}

		if ( ! $this->repo->delete( $id ) ) {
			return false;
		}

		if ( $business->post_id > 0 ) {
			wp_delete_post( $business->post_id, true );
		}

		do_action( 'zbe_business_deleted', $id, $user_id );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Approve.
	 *
	 * @param int $id Id.
	 */
	public function approve( int $id ): bool {
		$business = $this->repo->get( $id );

		if ( null === $business || ! $this->repo->update( $id, array( 'status' => 'active' ) ) ) {
			return false;
		}

		$this->set_wp_post_status( $business, 'publish' );

		$this->notify_owner(
			$business,
			'business_approved',
			__( 'Your business is live', 'zeko-business' ),
			/* translators: %s: business name */
			sprintf( __( '“%s” has been approved and is now listed in the directory.', 'zeko-business' ), $business->name )
		);

		do_action( 'zbe_business_approved', $id );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Suspend.
	 *
	 * @param int    $id Id.
	 * @param string $reason Reason.
	 */
	public function suspend( int $id, string $reason = '' ): bool {
		$business = $this->repo->get( $id );

		if ( null === $business || ! $this->repo->update( $id, array( 'status' => 'suspended' ) ) ) {
			return false;
		}

		$this->set_wp_post_status( $business, 'draft' );

		$message = __( 'Your listing has been suspended.', 'zeko-business' );

		if ( '' !== $reason ) {
			/* translators: %s: rejection reason */
			$message .= ' ' . sprintf( __( 'Reason: %s', 'zeko-business' ), $reason );
		}

		$this->notify_owner( $business, 'business_suspended', __( 'Your business was suspended', 'zeko-business' ), $message );

		do_action( 'zbe_business_suspended', $id, $reason );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Feature.
	 *
	 * @param int $id Id.
	 * @param int $days Days.
	 */
	public function feature( int $id, int $days = 30 ): bool {
		$days    = max( 1, $days );
		$expires = gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS );

		$updated = $this->repo->update(
			$id,
			array(
				'is_featured'      => 1,
				'featured_expires' => $expires,
			)
		);

		if ( ! $updated ) {
			return false;
		}

		do_action( 'zbe_business_featured', $id, $days );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Unfeature.
	 *
	 * @param int $id Id.
	 */
	public function unfeature( int $id ): bool {
		$updated = $this->repo->update(
			$id,
			array(
				'is_featured'      => 0,
				'featured_expires' => '',
			)
		);

		if ( ! $updated ) {
			return false;
		}

		do_action( 'zbe_business_unfeatured', $id );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Sponsor.
	 *
	 * @param int $id Id.
	 * @param int $days Days.
	 */
	public function sponsor( int $id, int $days = 30 ): bool {
		$days    = max( 1, $days );
		$expires = gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS );

		$updated = $this->repo->update(
			$id,
			array(
				'is_sponsored'      => 1,
				'sponsored_expires' => $expires,
			)
		);

		if ( ! $updated ) {
			return false;
		}

		do_action( 'zbe_business_sponsored', $id, $days );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Unsponsor.
	 *
	 * @param int $id Id.
	 */
	public function unsponsor( int $id ): bool {
		$updated = $this->repo->update(
			$id,
			array(
				'is_sponsored'      => 0,
				'sponsored_expires' => '',
			)
		);

		if ( ! $updated ) {
			return false;
		}

		do_action( 'zbe_business_unsponsored', $id );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Upgrade a business plan. Sets the new plan and returns true on success.
	 *
	 * @param int    $id Id.
	 * @param string $new_plan Plan tier slug (must be valid).
	 */
	public function upgrade_plan( int $id, string $new_plan ): bool {
		if ( ! Plans::is_valid( $new_plan ) ) {
			return false;
		}

		$business = $this->repo->get( $id );

		if ( null === $business ) {
			return false;
		}

		$old_plan = $business->plan;

		if ( Plans::level( $new_plan ) <= Plans::level( $old_plan ) ) {
			return false;
		}

		if ( ! $this->repo->update( $id, array( 'plan' => $new_plan ) ) ) {
			return false;
		}

		if ( $business->post_id > 0 ) {
			update_post_meta( $business->post_id, '_zbp_plan', $new_plan );
		}

		do_action( 'zbe_plan_upgraded', $id, $old_plan, $new_plan );

		self::flush_directory_cache();

		return true;
	}

	/**
	 * Follow.
	 *
	 * @param int $business_id Business id.
	 * @param int $user_id User id.
	 */
	public function follow( int $business_id, int $user_id ): bool {
		$business = $this->repo->get( $business_id );

		if ( $user_id < 1 || null === $business || $this->followers->is_following( $user_id, $business_id ) ) {
			return false;
		}

		$done = $this->transaction(
			function () use ( $business_id, $user_id ): bool {
				$follower = $this->followers->create(
					array(
						'business_id'  => $business_id,
						'user_id'      => $user_id,
						'date_created' => current_time( 'mysql' ),
					)
				);

				if ( $follower->id < 1 ) {
					return false;
				}

				$this->refresh_follower_count( $business_id );

				return true;
			}
		);

		if ( ! $done ) {
			return false;
		}

		$this->analytics->track( $business_id, 'follow', $user_id );

		if ( $user_id !== $business->owner_id ) {
			$follower = get_userdata( $user_id );
			$name     = $follower ? $follower->display_name : __( 'Someone', 'zeko-business' );

			$this->notifications->send(
				$business->owner_id,
				'new_follower',
				__( 'New follower', 'zeko-business' ),
				/* translators: 1: name of the follower. 2: business name */
				sprintf( __( '%1$s is now following "%2$s".', 'zeko-business' ), $name, $business->name ),
				\ZBE\Plugin::page_url( 'portal', 'business_id=' . $business_id ),
				$business_id
			);
		}

		do_action( 'zbe_business_followed', $business_id, $user_id );

		return true;
	}

	/**
	 * Unfollow.
	 *
	 * @param int $business_id Business id.
	 * @param int $user_id User id.
	 */
	public function unfollow( int $business_id, int $user_id ): bool {
		if ( ! $this->followers->is_following( $user_id, $business_id ) ) {
			return false;
		}

		$done = $this->transaction(
			function () use ( $business_id, $user_id ): bool {
				if ( ! $this->followers->delete_for_user( $business_id, $user_id ) ) {
					return false;
				}

				$this->refresh_follower_count( $business_id );

				return true;
			}
		);

		if ( ! $done ) {
			return false;
		}

		do_action( 'zbe_business_unfollowed', $business_id, $user_id );

		return true;
	}

	/**
	 * Claim.
	 *
	 * @param int    $business_id Business id.
	 * @param int    $user_id User id.
	 * @param string $method Method.
	 * @param string $evidence Evidence.
	 */
	public function claim( int $business_id, int $user_id, string $method, string $evidence ): ?Claim {
		$business = $this->repo->get( $business_id );

		if ( null === $business || $user_id < 1 || $business->is_claimed ) {
			return null;
		}

		foreach ( $this->claims->get_by_business( $business_id ) as $existing ) {
			if ( $existing->user_id === $user_id && 'pending' === $existing->status ) {
				return null;
			}
		}

		$claim = $this->claims->create(
			array(
				'business_id' => $business_id,
				'user_id'     => $user_id,
				'method'      => sanitize_key( $method ),
				'evidence'    => sanitize_textarea_field( $evidence ),
				'status'      => 'pending',
			)
		);

		if ( $claim->id < 1 ) {
			return null;
		}

		do_action( 'zbe_claim_submitted', $claim, $business );

		return $claim;
	}

	/**
	 * Request verification.
	 *
	 * @param int    $business_id Business id.
	 * @param int    $user_id User id.
	 * @param string $method Method.
	 * @param string $note Note.
	 */
	public function request_verification( int $business_id, int $user_id, string $method, string $note ): ?VerificationRequest {
		$business = $this->repo->get( $business_id );

		if ( null === $business || ! $this->can_manage( $business, $user_id ) || $business->is_verified ) {
			return null;
		}

		foreach ( $this->verification->get_by_business( $business_id ) as $existing ) {
			if ( 'pending' === $existing->status ) {
				return null;
			}
		}

		$request = $this->verification->create(
			array(
				'business_id' => $business_id,
				'user_id'     => $user_id,
				'method'      => sanitize_key( $method ),
				'note'        => sanitize_textarea_field( $note ),
				'status'      => 'pending',
			)
		);

		if ( $request->id < 1 ) {
			return null;
		}

		do_action( 'zbe_verification_requested', $request, $business );

		return $request;
	}

	/**
	 * Admin action: approve a pending claim, transfer ownership flags and
	 * notify the claimant.
	 *
	 * @param int $claim_id Claim id.
	 */
	public function approve_claim( int $claim_id ): bool {
		$claim = $this->claims->get( $claim_id );

		if ( null === $claim || 'pending' !== $claim->status ) {
			return false;
		}

		$business = $this->repo->get( $claim->business_id );

		if ( null === $business ) {
			return false;
		}

		$approved = $this->transaction(
			function () use ( $claim ): bool {
				if ( ! $this->claims->approve( $claim->id, get_current_user_id() ) ) {
					return false;
				}

				return $this->repo->update(
					$claim->business_id,
					array(
						'is_claimed'   => 1,
						'claimed_by'   => $claim->user_id,
						'claimed_date' => current_time( 'mysql' ),
					)
				);
			}
		);

		if ( ! $approved ) {
			return false;
		}

		$this->notifications->send(
			$claim->user_id,
			'claim_approved',
			__( 'Claim approved', 'zeko-business' ),
			/* translators: %s: business name */
			sprintf( __( 'Your claim on "%s" has been approved. You can now manage it from the business portal.', 'zeko-business' ), $business->name ),
			\ZBE\Plugin::page_url( 'portal', 'business_id=' . $business->id ),
			$business->id
		);

		do_action( 'zbe_claim_approved', $claim, $business );

		return true;
	}

	/**
	 * Admin action: reject a pending claim and notify the claimant.
	 *
	 * @param int $claim_id Claim id.
	 */
	public function reject_claim( int $claim_id ): bool {
		$claim = $this->claims->get( $claim_id );

		if ( null === $claim || 'pending' !== $claim->status ) {
			return false;
		}

		if ( ! $this->claims->reject( $claim_id, get_current_user_id() ) ) {
			return false;
		}

		$business = $this->repo->get( $claim->business_id );

		if ( null !== $business ) {
			$this->notifications->send(
				$claim->user_id,
				'claim_rejected',
				__( 'Claim rejected', 'zeko-business' ),
				/* translators: %s: business name */
				sprintf( __( 'Your claim on “%s” was not approved.', 'zeko-business' ), $business->name ),
				'',
				$business->id
			);
		}

		do_action( 'zbe_claim_rejected', $claim );

		return true;
	}

	/**
	 * Admin action: approve a verification request and badge the business.
	 *
	 * @param int $request_id Request id.
	 */
	public function approve_verification( int $request_id ): bool {
		$request = $this->verification->get( $request_id );

		if ( null === $request || 'pending' !== $request->status ) {
			return false;
		}

		$business = $this->repo->get( $request->business_id );

		if ( null === $business ) {
			return false;
		}

		$approved = $this->transaction(
			function () use ( $request ): bool {
				if ( ! $this->verification->approve( $request->id, get_current_user_id() ) ) {
					return false;
				}

				return $this->repo->update( $request->business_id, array( 'is_verified' => 1 ) );
			}
		);

		if ( ! $approved ) {
			return false;
		}

		$this->notifications->send(
			$request->user_id,
			'verification_approved',
			__( 'Business verified', 'zeko-business' ),
			/* translators: %s: business name */
			sprintf( __( '“%s” is now verified. The verified badge is visible on your listing.', 'zeko-business' ), $business->name ),
			home_url( '/businesses/' . $business->slug . '/' ),
			$business->id
		);

		do_action( 'zbe_verification_approved', $request, $business );

		return true;
	}

	/**
	 * Admin action: reject a verification request and notify the requester.
	 *
	 * @param int $request_id Request id.
	 */
	public function reject_verification( int $request_id ): bool {
		$request = $this->verification->get( $request_id );

		if ( null === $request || 'pending' !== $request->status ) {
			return false;
		}

		if ( ! $this->verification->reject( $request_id, get_current_user_id() ) ) {
			return false;
		}

		$business = $this->repo->get( $request->business_id );

		if ( null !== $business ) {
			$this->notifications->send(
				$request->user_id,
				'verification_rejected',
				__( 'Verification rejected', 'zeko-business' ),
				/* translators: %s: business name */
				sprintf( __( 'The verification request for “%s” was not approved. You may submit a new request with additional documents.', 'zeko-business' ), $business->name ),
				'',
				$business->id
			);
		}

		do_action( 'zbe_verification_rejected', $request );

		return true;
	}

	/**
	 * Track view.
	 *
	 * @param int $business_id Business id.
	 * @param int $user_id User id.
	 */
	public function track_view( int $business_id, int $user_id = 0 ): void {
		if ( $business_id < 1 || ! $this->should_count_view( $business_id ) ) {
			return;
		}

		$this->analytics->track( $business_id, 'view', $user_id );

		$this->repo->increment_views( $business_id );
	}

	/**
	 * Per-session dedupe: one view per visitor (IP + user agent hash) per
	 * business every six hours. The IP is only ever stored hashed inside a
	 * transient key.
	 *
	 * @param int $business_id Business id.
	 */
	private function should_count_view( int $business_id ): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$key = 'zbp_vw_' . substr( md5( $business_id . '|' . $ip . '|' . $ua ), 0, 40 );

		if ( get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, 1, 6 * HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Cron entry point: downgrade featured/sponsored flags whose expiry has
	 * passed. Also sends 7-day renewal warnings and expiry notifications.
	 *
	 * @return int Number of downgraded listings.
	 */
	public function expire_features(): int {
		$now                = gmdate( 'Y-m-d H:i:s', time() );
		$expiring_featured  = $this->repo->find_expiring_flag( 'is_featured', 'featured_expires', 7 );
		$expiring_sponsored = $this->repo->find_expiring_flag( 'is_sponsored', 'sponsored_expires', 7 );

		$warned = 0;
		foreach ( $expiring_featured as $biz ) {
			$key = 'zbp_renewal_warn_featured_' . $biz->id;
			if ( ! get_transient( $key ) ) {
				$this->notify_owner(
					$biz,
					'featured_expiring',
					__( 'Your featured status is expiring', 'zeko-business' ),
					sprintf(
						/* translators: 1: business name, 2: expiry date */
						__( '"%1$s" will lose its featured status on %2$s. Renew now to keep your premium placement.', 'zeko-business' ),
						$biz->name,
						wp_date( get_option( 'date_format' ), strtotime( $biz->featured_expires ) )
					)
				);
				set_transient( $key, 1, 7 * DAY_IN_SECONDS );
				++$warned;
			}
		}

		foreach ( $expiring_sponsored as $biz ) {
			$key = 'zbp_renewal_warn_sponsored_' . $biz->id;
			if ( ! get_transient( $key ) ) {
				$this->notify_owner(
					$biz,
					'sponsored_expiring',
					__( 'Your sponsored status is expiring', 'zeko-business' ),
					sprintf(
						/* translators: 1: business name, 2: expiry date */
						__( '"%1$s" will lose its sponsored status on %2$s. Renew now to keep your top placement.', 'zeko-business' ),
						$biz->name,
						wp_date( get_option( 'date_format' ), strtotime( $biz->sponsored_expires ) )
					)
				);
				set_transient( $key, 1, 7 * DAY_IN_SECONDS );
				++$warned;
			}
		}

		$featured  = $this->repo->expire_featured( $now );
		$sponsored = $this->repo->expire_sponsored( $now );

		if ( $featured || $sponsored ) {
			do_action( 'zbe_features_expired', $featured, $sponsored );
		}

		return $featured + $sponsored;
	}

	/**
	 * Run a set of writes atomically. Repositories share the same $wpdb
	 * connection so the transaction spans them all.
	 *
	 * @param callable $work Unit of work returning a truthy value on success.
	 * @throws \Throwable When an error occurs.
	 */
	private function transaction( callable $work ): bool {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB

		try {
			$ok = (bool) $work();
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB
			throw $e;
		}

		if ( $ok ) {
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB
		}

		return $ok;
	}

	/**
	 * Search.
	 *
	 * @param array $args Args.
	 */
	public function search( array $args = array() ): array {
		return $this->cached_query(
			$args,
			'rows',
			function () use ( $args ) {
				return $this->repo->find( $args );
			}
		);
	}

	/**
	 * Count.
	 *
	 * @param array $args Args.
	 */
	public function count( array $args = array() ): int {
		return $this->cached_query(
			$args,
			'count',
			function () use ( $args ) {
				return $this->repo->count( $args );
			}
		);
	}

	/**
	 * Run a read query with short-lived transient caching.
	 * Directory listings are read-heavy; a short TTL avoids hammering the DB
	 * on every request while keeping data fresh enough. Any mutation that
	 * alters visible listing data calls flush_directory_cache() to bump the
	 * cache generation and invalidate every entry at once.
	 *
	 * @param array    $args Args.
	 * @param string   $kind Kind.
	 * @param callable $loader Loader.
	 */
	private function cached_query( array $args, string $kind, callable $loader ) {
		ksort( $args );
		$key = 'zbp_dir/' . self::cache_generation() . '/' . $kind . '/' . md5( (string) wp_json_encode( $args ) );

		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$value = $loader();
		set_transient( $key, $value, 60 );

		return $value;
	}

	/**
	 * Cache generation.
	 */
	private static function cache_generation(): int {
		return (int) get_option( 'zbe_directory_cache_gen', 0 );
	}

	/**
	 * Invalidate every directory listing cache entry by bumping the
	 * generation. Must be called whenever listing-visible data changes
	 * (create/update/delete, status, feature, sponsor, plan, counts).
	 */
	public static function flush_directory_cache(): void {
		update_option( 'zbe_directory_cache_gen', self::cache_generation() + 1 );
	}

	/**
	 * Stats.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_stats( int $business_id ): array {
		$business = $this->repo->get( $business_id );

		if ( null === $business ) {
			return array();
		}

		return array(
			'views'          => $business->view_count,
			'followers'      => $this->followers->count( $business_id ),
			'reviews'        => $business->review_count,
			'avg_rating'     => $business->avg_rating,
			'services_count' => $this->services->count_by_business( $business_id ),
			'staff_count'    => $this->team->count_by_business( $business_id ),
		);
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
	 * Unique slug.
	 *
	 * @param string $source Source.
	 */
	private function unique_slug( string $source ): string {
		$base   = sanitize_title( $source );
		$base   = '' !== $base ? $base : 'business';
		$slug   = $base;
		$suffix = 2;

		while ( null !== $this->repo->get_by_slug( $slug ) ) {
			$slug = $base . '-' . $suffix;
			++$suffix;
		}

		return $slug;
	}

	/**
	 * Insert wp post.
	 *
	 * @param string $title Title.
	 * @param string $slug Slug.
	 * @param string $content Content.
	 * @param string $excerpt Excerpt.
	 * @param int    $author_id Author id.
	 * @param string $post_status Post status.
	 */
	private function insert_wp_post( string $title, string $slug, string $content, string $excerpt, int $author_id, string $post_status = 'pending' ): int {
		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_author'  => $author_id,
				'post_status'  => 'publish' === $post_status ? 'publish' : 'pending',
				'post_type'    => 'zeko_business',
				'ping_status'  => 'closed',
			),
			true
		);

		return is_wp_error( $post_id ) ? 0 : (int) $post_id;
	}

	/**
	 * Sync wp post.
	 *
	 * @param Business $business Business.
	 * @param array    $fields Fields.
	 * @param ?string  $description Description.
	 * @param ?string  $excerpt Excerpt.
	 */
	private function sync_wp_post( Business $business, array $fields, ?string $description, ?string $excerpt ): void {
		$post = array( 'ID' => $business->post_id );

		if ( isset( $fields['name'] ) ) {
			$post['post_title'] = $fields['name'];
		}

		if ( null !== $description ) {
			$post['post_content'] = $description;
		}

		if ( null !== $excerpt ) {
			$post['post_excerpt'] = $excerpt;
		}

		if ( count( $post ) > 1 ) {
			wp_update_post( $post );
		}
	}

	/**
	 * Wp post status.
	 *
	 * @param Business $business Business.
	 * @param string   $status Status.
	 */
	private function set_wp_post_status( Business $business, string $status ): void {
		if ( $business->post_id > 0 && get_post( $business->post_id ) ) {
			wp_update_post(
				array(
					'ID'          => $business->post_id,
					'post_status' => $status,
				)
			);
		}
	}

	/**
	 * Refresh follower count.
	 *
	 * @param int $business_id Business id.
	 */
	private function refresh_follower_count( int $business_id ): void {
		$this->repo->update( $business_id, array( 'follower_count' => $this->followers->count( $business_id ) ) );
	}

	/**
	 * Notify owner.
	 *
	 * @param Business $business Business.
	 * @param string   $type Type.
	 * @param string   $title Title.
	 * @param string   $message Message.
	 */
	private function notify_owner( Business $business, string $type, string $title, string $message ): void {
		if ( $business->owner_id > 0 ) {
			$this->notifications->send( $business->owner_id, $type, $title, $message, '', $business->id );
		}
	}
}
