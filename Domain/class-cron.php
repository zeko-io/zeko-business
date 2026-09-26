<?php
/**
 * Scheduled jobs: featured/sponsored expiry, pending-review digest,
 * nightly analytics rollup.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Domain;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/email-html.php';

/** Class Cron. */
final class Cron {

	const FEATURED_EXPIRY  = 'zbe_featured_expiry_check';
	const REVIEW_DIGEST    = 'zbe_review_digest';
	const ANALYTICS_ROLLUP = 'zbe_analytics_rollup';
	const OWNER_DIGEST     = 'zbe_owner_digest';

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( self::FEATURED_EXPIRY, array( __CLASS__, 'run_featured_expiry' ) );
		add_action( self::REVIEW_DIGEST, array( __CLASS__, 'run_review_digest' ) );
		add_action( self::ANALYTICS_ROLLUP, array( __CLASS__, 'run_analytics_rollup' ) );
		add_action( self::OWNER_DIGEST, array( __CLASS__, 'run_owner_digest' ) );

		// Self-healing: re-schedule if events went missing (e.g. cloned sites).
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ) );

		self::ensure_scheduled();
	}

	/**
	 * Wire all three events. Safe to call repeatedly.
	 */
	public static function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::FEATURED_EXPIRY ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::FEATURED_EXPIRY );
		}

		if ( ! wp_next_scheduled( self::REVIEW_DIGEST ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::REVIEW_DIGEST );
		}

		if ( ! wp_next_scheduled( self::ANALYTICS_ROLLUP ) ) {
			wp_schedule_single_event( strtotime( 'tomorrow', time() ), self::ANALYTICS_ROLLUP );
		}

		if ( ! wp_next_scheduled( self::OWNER_DIGEST ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::OWNER_DIGEST );
		}
	}

	/**
	 * Unschedule.
	 */
	public static function unschedule(): void {
		$hooks = array( self::FEATURED_EXPIRY, self::REVIEW_DIGEST, self::ANALYTICS_ROLLUP, self::OWNER_DIGEST );

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );

			while ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
	}

	/**
	 * Downgrade featured/sponsored listings whose expiry has passed.
	 */
	public static function run_featured_expiry(): void {
		$expired = Services::business_service()->expire_features();

		if ( $expired > 0 ) {
			do_action( 'zbe_cron_features_expired', $expired );
		}
	}

	/**
	 * Once a day, tell the site admin how many reviews await moderation.
	 */
	public static function run_review_digest(): void {
		$pending = Services::reviews()->count_pending();

		if ( $pending < 1 ) {
			return;
		}

		$subject = sprintf(
			/* translators: %d: number of pending reviews */
			__( '[%1$s] %2$d reviews awaiting moderation', 'zeko-business' ),
			get_bloginfo( 'name' ),
			$pending
		);

		$dashboard = admin_url( 'admin.php?page=zbp-dashboard' );

		$message = sprintf(
			/* translators: 1: number of pending reviews, 2: dashboard URL */
			__(
				"Howdy,\n\nThe business directory has %1\$d review(s) waiting in the moderation queue.\n\nManage them here: %2\$s",
				'zeko-business'
			),
			$pending,
			$dashboard
		);

		$email_parts = zbe_wrap_email( $message, get_bloginfo( 'name' ), '', $subject );
		$headers     = $email_parts['html'] ? array( 'Content-Type: text/html; charset=UTF-8' ) : array();

		wp_mail( get_option( 'admin_email' ), $subject, $email_parts['body'], $headers );

		do_action( 'zbe_cron_review_digest_sent', $pending );
	}

	/**
	 * Aggregate yesterday's raw analytics rows into the daily rollup table.
	 */
	public static function run_analytics_rollup(): void {
		$yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );

		$rows = Services::analytics_repo()->rollup_day( $yesterday );

		do_action( 'zbe_cron_analytics_rollup', $yesterday, $rows );
	}

	/**
	 * Once a day, email each business owner a digest of questions that newly
	 * mention any business they own.
	 */
	public static function run_owner_digest(): void {
		$sent = Services::digest_service()->run();

		do_action( 'zbe_cron_owner_digest_run', $sent );
	}
}
