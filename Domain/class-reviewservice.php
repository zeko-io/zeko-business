<?php
/**
 * Review domain service.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Domain;

use ZBE\Contracts\Repository\BusinessRepositoryInterface;
use ZBE\Contracts\Repository\ReviewRepositoryInterface;
use ZBE\Entity\Business;
use ZBE\Entity\Review;

defined( 'ABSPATH' ) || exit;

/** Class ReviewService. */
class ReviewService {

	/**
	 * Reviews.
	 *
	 * @var ReviewRepositoryInterface Reviews.
	 */
	private ReviewRepositoryInterface $reviews;
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
	 * Analytics.
	 *
	 * @var AnalyticsService Analytics.
	 */
	private AnalyticsService $analytics;

	/**
	 * Construct.
	 *
	 * @param ReviewRepositoryInterface   $reviews Reviews.
	 * @param BusinessRepositoryInterface $businesses Businesses.
	 * @param NotificationService         $notifications Notifications.
	 * @param AnalyticsService            $analytics Analytics.
	 */
	public function __construct(
		ReviewRepositoryInterface $reviews,
		BusinessRepositoryInterface $businesses,
		NotificationService $notifications,
		AnalyticsService $analytics
	) {
		$this->reviews       = $reviews;
		$this->businesses    = $businesses;
		$this->notifications = $notifications;
		$this->analytics     = $analytics;
	}

	/**
	 * Create.
	 *
	 * @param int    $business_id Business id.
	 * @param int    $user_id User id.
	 * @param int    $rating Rating.
	 * @param string $title Title.
	 * @param string $content Content.
	 * @param array  $criteria Criteria.
	 */
	public function create( int $business_id, int $user_id, int $rating, string $title, string $content, array $criteria = array() ): ?Review {
		if ( $business_id < 1 || $user_id < 1 || $rating < 1 || $rating > 5 ) {
			return null;
		}

		$business = $this->businesses->get( $business_id );

		if ( null === $business ) {
			return null;
		}

		foreach ( $this->reviews->get_by_user( $user_id ) as $existing ) {
			if ( $existing->business_id === $business_id ) {
				return null;
			}
		}

		// When multi-criteria stars are supplied, derive the overall rating as.
		// the average of the criteria (mirroring BP Business Profile behavior).
		// and persist the per-criterion breakdown.
		$stored_criteria = $this->sanitize_criteria( $criteria );
		if ( ! empty( $stored_criteria['values'] ) ) {
			$rating = (int) round( array_sum( $stored_criteria['values'] ) / count( $stored_criteria['values'] ) );
			$rating = min( 5, max( 1, $rating ) );
		}

		$needs_moderation = (bool) apply_filters( 'zbe_review_requires_moderation', false, $business_id, $user_id );
		$status           = $needs_moderation ? 'pending' : 'approved';

		$review = $this->reviews->create(
			array(
				'business_id'  => $business_id,
				'user_id'      => $user_id,
				'rating'       => $rating,
				'title'        => sanitize_text_field( $title ),
				'content'      => sanitize_textarea_field( $content ),
				'criteria'     => wp_json_encode( $stored_criteria['data'] ),
				'status'       => $status,
				'date_created' => current_time( 'mysql' ),
			)
		);

		if ( $review->id < 1 ) {
			return null;
		}

		if ( 'approved' === $status ) {
			$this->refresh_business_rating( $business_id );
		}

		$this->analytics->track( $business_id, 'review', $user_id );

		$this->notify_owner( $business, $user_id, $rating );

		do_action( 'zbe_review_created', $review, $business );

		return $review;
	}

	/**
	 * Approve.
	 *
	 * @param int $review_id Review id.
	 */
	public function approve( int $review_id ): bool {
		$review = $this->reviews->get( $review_id );

		if ( null === $review || ! $this->reviews->approve( $review_id ) ) {
			return false;
		}

		$this->refresh_business_rating( $review->business_id );

		do_action( 'zbe_review_approved', $review_id );

		return true;
	}

	/**
	 * Reject.
	 *
	 * @param int $review_id Review id.
	 */
	public function reject( int $review_id ): bool {
		$review = $this->reviews->get( $review_id );

		if ( null === $review || ! $this->reviews->reject( $review_id ) ) {
			return false;
		}

		$this->refresh_business_rating( $review->business_id );

		do_action( 'zbe_review_rejected', $review_id );

		return true;
	}

	/**
	 * Reply.
	 *
	 * @param int    $review_id Review id.
	 * @param string $reply Reply.
	 * @param int    $admin_id Admin id.
	 */
	public function reply( int $review_id, string $reply, int $admin_id ): bool {
		$review = $this->reviews->get( $review_id );

		if ( null === $review || $admin_id < 1 || '' === trim( $reply ) ) {
			return false;
		}

		if ( ! $this->reviews->update( $review_id, array( 'admin_reply' => sanitize_textarea_field( $reply ) ) ) ) {
			return false;
		}

		do_action( 'zbe_review_replied', $review_id, $reply, $admin_id );

		return true;
	}

	/**
	 * Sanitize the raw per-criterion stars (label => 1-5) into a persisted
	 * assoc map plus the list of numeric values used to derive the overall
	 * rating. Only labels allowed by the configured criteria are kept.
	 *
	 * @return array{data: array<string,int>, values: int[]}
	 * @param array $raw * @return array{data: array<string,int>, values: int[]}.
	 */
	private function sanitize_criteria( array $raw ): array {
		$all_labels = array_keys( self::criteria_options() );
		$data       = array();
		$values     = array();

		foreach ( $raw as $label => $stars ) {
			$label = sanitize_text_field( (string) $label );

			if ( '' === $label || ! in_array( $label, $all_labels, true ) ) {
				continue;
			}

			$stars = (int) $stars;

			if ( $stars < 1 || $stars > 5 ) {
				continue;
			}

			$data[ $label ] = $stars;
			$values[]       = $stars;
		}

		return array(
			'data'   => $data,
			'values' => $values,
		);
	}

	/**
	 * The admin-configurable review criteria (label => whether it is enabled).
	 * Defaults match the classic multi-criteria set.
	 *
	 * @return array<string, bool>
	 */
	public static function criteria_options(): array {
		$definitions = array(
			'Quality'       => true,
			'Value'         => true,
			'Support'       => true,
			'Response Time' => false,
		);

		$saved = get_option( 'zbe_review_criteria', array() );

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $definitions;
		}

		$normalized = array();
		foreach ( $saved as $item ) {
			if ( ! is_array( $item ) || '' === trim( (string) ( $item['label'] ?? '' ) ) ) {
				continue;
			}
			$normalized[ sanitize_text_field( (string) $item['label'] ) ] = ! empty( $item['enabled'] );
		}

		return empty( $normalized ) ? $definitions : $normalized;
	}

	/**
	 * Refresh business rating.
	 *
	 * @param int $business_id Business id.
	 * @throws \Throwable When an error occurs.
	 */
	private function refresh_business_rating( int $business_id ): void {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB

		try {
			$count = $this->reviews->count_by_business( $business_id, 'approved' );
			$avg   = $this->reviews->avg_rating( $business_id );

			$this->businesses->update(
				$business_id,
				array(
					'review_count' => $count,
					'avg_rating'   => round( $avg, 2 ),
				)
			);

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB
			throw $e;
		}
	}

	/**
	 * Notify owner.
	 *
	 * @param Business $business Business.
	 * @param int      $user_id User id.
	 * @param int      $rating Rating.
	 */
	private function notify_owner( Business $business, int $user_id, int $rating ): void {
		if ( $business->owner_id < 1 || $business->owner_id === $user_id ) {
			return;
		}

		$user   = get_userdata( $user_id );
		$author = $user ? $user->display_name : __( 'A customer', 'zeko-business' );

		$this->notifications->send(
			$business->owner_id,
			'new_review',
			__( 'New review received', 'zeko-business' ),
			/* translators: 1: reviewer name. 2: star rating. 3: business name */
			sprintf( __( '%1$s left a %2$d-star review on “%3$s”.', 'zeko-business' ), $author, $rating, $business->name ),
			'',
			$business->id
		);
	}
}
