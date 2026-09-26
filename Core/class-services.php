<?php
/**
 * Lazy service locator bridging controllers to the Domain layer.
 *
 * Controllers never instantiate repositories/services directly; they ask this
 * registry so dependency wiring lives in exactly one place.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Core;

use ZBE\Contracts\Repository\AnalyticsRepositoryInterface;
use ZBE\Contracts\Repository\BookingRepositoryInterface;
use ZBE\Contracts\Repository\BookingSlotRepositoryInterface;
use ZBE\Contracts\Repository\BusinessRepositoryInterface;
use ZBE\Contracts\Repository\ClaimRepositoryInterface;
use ZBE\Contracts\Repository\FollowerRepositoryInterface;
use ZBE\Contracts\Repository\HoursRepositoryInterface;
use ZBE\Contracts\Repository\ImportLogRepositoryInterface;
use ZBE\Contracts\Repository\LocationRepositoryInterface;
use ZBE\Contracts\Repository\MediaRepositoryInterface;
use ZBE\Contracts\Repository\NotificationRepositoryInterface;
use ZBE\Contracts\Repository\ReviewRepositoryInterface;
use ZBE\Contracts\Repository\ServiceRepositoryInterface;
use ZBE\Contracts\Repository\TeamRepositoryInterface;
use ZBE\Contracts\Repository\VerificationRepositoryInterface;
use ZBE\Domain\AnalyticsService;
use ZBE\Domain\BusinessService;
use ZBE\Domain\DigestService;
use ZBE\Domain\MentionService;
use ZBE\Domain\NotificationService;
use ZBE\Domain\ReviewService;
use ZBE\Domain\TeamService;
use ZBE\Repository\AnalyticsRepository;
use ZBE\Repository\BookingRepository;
use ZBE\Repository\BookingSlotRepository;
use ZBE\Repository\BusinessRepository;
use ZBE\Repository\ClaimRepository;
use ZBE\Repository\FollowerRepository;
use ZBE\Repository\HoursRepository;
use ZBE\Repository\ImportLogRepository;
use ZBE\Repository\LocationRepository;
use ZBE\Repository\MediaRepository;
use ZBE\Repository\MentionRepository;
use ZBE\Repository\NotificationRepository;
use ZBE\Repository\ReviewRepository;
use ZBE\Repository\ServiceCategoryRepository;
use ZBE\Repository\ServiceRepository;
use ZBE\Repository\StaffInviteRepository;
use ZBE\Repository\TeamRepository;
use ZBE\Repository\VerificationRepository;

defined( 'ABSPATH' ) || exit;

/** Class Services. */
final class Services {

	/**
	 * Instances.
	 *
	 * @var array Instances.
	 */
	private static array $instances = array();

	/**
	 * Construct.
	 */
	private function __construct() {}

	/**
	 * Business service.
	 */
	public static function business_service(): BusinessService {
		if ( ! isset( self::$instances['business'] ) ) {
			self::$instances['business'] = new BusinessService(
				self::businesses(),
				self::followers(),
				self::claims(),
				self::verification(),
				self::services_repo(),
				self::team_repo(),
				self::notification_service(),
				self::analytics()
			);
		}

		return self::$instances['business'];
	}

	/**
	 * Review service.
	 */
	public static function review_service(): ReviewService {
		if ( ! isset( self::$instances['review'] ) ) {
			self::$instances['review'] = new ReviewService(
				self::reviews(),
				self::businesses(),
				self::notification_service(),
				self::analytics()
			);
		}

		return self::$instances['review'];
	}

	/**
	 * Team service.
	 */
	public static function team_service(): TeamService {
		if ( ! isset( self::$instances['team'] ) ) {
			self::$instances['team'] = new TeamService(
				self::team_repo(),
				self::businesses(),
				self::notification_service()
			);
		}

		return self::$instances['team'];
	}

	/**
	 * Notification service.
	 */
	public static function notification_service(): NotificationService {
		if ( ! isset( self::$instances['notification'] ) ) {
			self::$instances['notification'] = new NotificationService( self::notification_repo() );
		}

		return self::$instances['notification'];
	}

	/**
	 * Mention service.
	 */
	public static function mention_service(): MentionService {
		if ( ! isset( self::$instances['mention'] ) ) {
			self::$instances['mention'] = new MentionService( new MentionRepository() );
		}

		return self::$instances['mention'];
	}

	/**
	 * Digest service.
	 */
	public static function digest_service(): DigestService {
		if ( ! isset( self::$instances['digest'] ) ) {
			self::$instances['digest'] = new DigestService( new MentionRepository() );
		}

		return self::$instances['digest'];
	}

	/**
	 * Analytics.
	 */
	public static function analytics(): AnalyticsService {
		if ( ! isset( self::$instances['analytics'] ) ) {
			self::$instances['analytics'] = new AnalyticsService( self::analytics_repo() );
		}

		return self::$instances['analytics'];
	}

	/**
	 * Businesses.
	 */
	public static function businesses(): BusinessRepositoryInterface {
		if ( ! isset( self::$instances['repo_business'] ) ) {
			self::$instances['repo_business'] = new BusinessRepository();
		}

		return self::$instances['repo_business'];
	}

	/**
	 * Reviews.
	 */
	public static function reviews(): ReviewRepositoryInterface {
		if ( ! isset( self::$instances['repo_review'] ) ) {
			self::$instances['repo_review'] = new ReviewRepository();
		}

		return self::$instances['repo_review'];
	}

	/**
	 * Followers.
	 */
	public static function followers(): FollowerRepositoryInterface {
		if ( ! isset( self::$instances['repo_follower'] ) ) {
			self::$instances['repo_follower'] = new FollowerRepository();
		}

		return self::$instances['repo_follower'];
	}

	/**
	 * Claims.
	 */
	public static function claims(): ClaimRepositoryInterface {
		if ( ! isset( self::$instances['repo_claim'] ) ) {
			self::$instances['repo_claim'] = new ClaimRepository();
		}

		return self::$instances['repo_claim'];
	}

	/**
	 * Verification.
	 */
	public static function verification(): VerificationRepositoryInterface {
		if ( ! isset( self::$instances['repo_verification'] ) ) {
			self::$instances['repo_verification'] = new VerificationRepository();
		}

		return self::$instances['repo_verification'];
	}

	/**
	 * Services repo.
	 */
	public static function services_repo(): ServiceRepositoryInterface {
		if ( ! isset( self::$instances['repo_service'] ) ) {
			self::$instances['repo_service'] = new ServiceRepository();
		}

		return self::$instances['repo_service'];
	}

	/**
	 * Service categories.
	 */
	public static function service_categories(): ServiceCategoryRepository {
		if ( ! isset( self::$instances['repo_service_cat'] ) ) {
			self::$instances['repo_service_cat'] = new ServiceCategoryRepository();
		}

		return self::$instances['repo_service_cat'];
	}

	/**
	 * Team repo.
	 */
	public static function team_repo(): TeamRepositoryInterface {
		if ( ! isset( self::$instances['repo_team'] ) ) {
			self::$instances['repo_team'] = new TeamRepository();
		}

		return self::$instances['repo_team'];
	}

	/**
	 * Notification repo.
	 */
	public static function notification_repo(): NotificationRepositoryInterface {
		if ( ! isset( self::$instances['repo_notification'] ) ) {
			self::$instances['repo_notification'] = new NotificationRepository();
		}

		return self::$instances['repo_notification'];
	}

	/**
	 * Analytics repo.
	 */
	public static function analytics_repo(): AnalyticsRepositoryInterface {
		if ( ! isset( self::$instances['repo_analytics'] ) ) {
			self::$instances['repo_analytics'] = new AnalyticsRepository();
		}

		return self::$instances['repo_analytics'];
	}

	/**
	 * Hours.
	 */
	public static function hours(): HoursRepositoryInterface {
		if ( ! isset( self::$instances['repo_hours'] ) ) {
			self::$instances['repo_hours'] = new HoursRepository();
		}

		return self::$instances['repo_hours'];
	}

	/**
	 * Locations.
	 */
	public static function locations(): LocationRepositoryInterface {
		if ( ! isset( self::$instances['repo_locations'] ) ) {
			self::$instances['repo_locations'] = new LocationRepository();
		}

		return self::$instances['repo_locations'];
	}

	/**
	 * Booking slots.
	 */
	public static function booking_slots(): BookingSlotRepositoryInterface {
		if ( ! isset( self::$instances['repo_booking_slots'] ) ) {
			self::$instances['repo_booking_slots'] = new BookingSlotRepository();
		}

		return self::$instances['repo_booking_slots'];
	}

	/**
	 * Bookings.
	 */
	public static function bookings(): BookingRepositoryInterface {
		if ( ! isset( self::$instances['repo_bookings'] ) ) {
			self::$instances['repo_bookings'] = new BookingRepository();
		}

		return self::$instances['repo_bookings'];
	}

	/**
	 * Import logs.
	 */
	public static function import_logs(): ImportLogRepositoryInterface {
		if ( ! isset( self::$instances['repo_import_log'] ) ) {
			self::$instances['repo_import_log'] = new ImportLogRepository();
		}

		return self::$instances['repo_import_log'];
	}

	/**
	 * Media.
	 */
	public static function media(): MediaRepositoryInterface {
		if ( ! isset( self::$instances['repo_media'] ) ) {
			self::$instances['repo_media'] = new MediaRepository();
		}

		return self::$instances['repo_media'];
	}

	/**
	 * Staff invites.
	 */
	public static function staff_invites(): StaffInviteRepository {
		if ( ! isset( self::$instances['repo_staff_invites'] ) ) {
			self::$instances['repo_staff_invites'] = new StaffInviteRepository();
		}

		return self::$instances['repo_staff_invites'];
	}
}
