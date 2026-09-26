<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Integration;

defined( 'ABSPATH' ) || exit;

/** Class Hooks. */
class Hooks {
	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_ecosystem' ), 15 );
		add_action( 'zbe_business_approved', array( __CLASS__, 'on_business_approved' ), 10, 2 );
		add_action( 'zbe_business_suspended', array( __CLASS__, 'on_business_suspended' ), 10, 2 );
		add_action( 'zbe_review_created', array( __CLASS__, 'on_review_created' ), 10, 2 );
		add_action( 'zbe_business_featured', array( __CLASS__, 'on_business_featured' ), 10, 2 );
		add_action( 'zbe_business_sponsored', array( __CLASS__, 'on_business_sponsored' ), 10, 2 );
		add_action( 'zbe_business_unsponsored', array( __CLASS__, 'on_business_unsponsored' ), 10, 2 );
		add_action( 'zbe_plan_upgraded', array( __CLASS__, 'on_plan_upgraded' ), 10, 3 );
	}

	/**
	 * Load ecosystem.
	 */
	public static function load_ecosystem(): void {
		$ecosystem = new Ecosystem();
		$ecosystem->init();
	}

	/**
	 * On business approved.
	 *
	 * @param int    $business_id Business id.
	 * @param object $business Business.
	 */
	public static function on_business_approved( int $business_id, object $business ): void {
		unset( $business_id, $business );
	}

	/**
	 * On business suspended.
	 *
	 * @param int    $business_id Business id.
	 * @param object $business Business.
	 */
	public static function on_business_suspended( int $business_id, object $business ): void {
		if ( ! empty( $business->post_id ) ) {
			wp_update_post(
				array(
					'ID'          => (int) $business->post_id,
					'post_status' => 'draft',
				)
			);
		}
	}

	/**
	 * On review created.
	 *
	 * @param int    $review_id Review id.
	 * @param object $review Review.
	 */
	public static function on_review_created( int $review_id, object $review ): void {
	}

	/**
	 * On business featured.
	 *
	 * @param int    $business_id Business id.
	 * @param object $business Business.
	 */
	public static function on_business_featured( int $business_id, object $business ): void {
		if ( ! empty( $business->post_id ) ) {
			update_post_meta( (int) $business->post_id, '_zbp_featured', 1 );
		}
	}

	/**
	 * On business sponsored.
	 *
	 * @param int $business_id Business id.
	 * @param int $days Days.
	 */
	public static function on_business_sponsored( int $business_id, int $days ): void {
		unset( $days );
		$biz = \ZBE\Core\Services::businesses()->get( $business_id );
		if ( $biz && ! empty( $biz->post_id ) ) {
			update_post_meta( (int) $biz->post_id, '_zbp_sponsored', 1 );
		}
	}

	/**
	 * On business unsponsored.
	 *
	 * @param int $business_id Business id.
	 */
	public static function on_business_unsponsored( int $business_id ): void {
		$biz = \ZBE\Core\Services::businesses()->get( $business_id );
		if ( $biz && ! empty( $biz->post_id ) ) {
			delete_post_meta( (int) $biz->post_id, '_zbp_sponsored' );
		}
	}

	/**
	 * On plan upgraded.
	 *
	 * @param int    $business_id Business id.
	 * @param string $old_plan Old plan.
	 * @param string $new_plan New plan.
	 */
	public static function on_plan_upgraded( int $business_id, string $old_plan, string $new_plan ): void {
		$biz = \ZBE\Core\Services::businesses()->get( $business_id );
		if ( $biz && ! empty( $biz->owner_id ) ) {
			\ZBE\Core\Services::notification_service()->send(
				$biz->owner_id,
				'plan_upgraded',
				__( 'Plan upgraded', 'zeko-business' ),
				sprintf(
					/* translators: 1: business name, 2: old plan, 3: new plan */
					__( '"%1$s" has been upgraded from %2$s to %3$s.', 'zeko-business' ),
					$biz->name,
					ucfirst( $old_plan ),
					ucfirst( $new_plan )
				),
				'',
				$business_id
			);
		}
	}
}
