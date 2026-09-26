<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Privacy;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/**
 * GDPR tools integration.
 *
 * Registers a personal-data exporter and eraser so the user's owned
 * businesses, reviews, follows, claims and verification requests can be
 * exported or erased from WordPress Tools → Erase Personal Data.
 */
class Gdpr {

	/**
	 * Init.
	 */
	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_erasers' ) );
	}

	/**
	 * Exporters.
	 *
	 * @param array $exporters Exporters.
	 */
	public static function register_exporters( array $exporters ): array {
		$exporters['zeko-business'] = array(
			'exporter_friendly_name' => __( 'Zeko Business Directory', 'zeko-business' ),
			'callback'               => array( __CLASS__, 'export_data' ),
		);
		return $exporters;
	}

	/**
	 * Erasers.
	 *
	 * @param array $erasers Erasers.
	 */
	public static function register_erasers( array $erasers ): array {
		$erasers['zeko-business'] = array(
			'eraser_friendly_name' => __( 'Zeko Business Directory', 'zeko-business' ),
			'callback'             => array( __CLASS__, 'erase_data' ),
		);
		return $erasers;
	}

	/**
	 * Export data.
	 *
	 * @param string $email Email.
	 * @param int    $page Page.
	 */
	public static function export_data( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data = self::collect_export_data( (int) $user->ID );

		if ( empty( $data ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		// Chunk the collected items so large datasets paginate cleanly.
		$per_page = 100;
		$chunks   = array_chunk( $data, $per_page );
		$index    = $page - 1;

		if ( ! isset( $chunks[ $index ] ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		return array(
			'data' => $chunks[ $index ],
			'done' => $index >= count( $chunks ) - 1,
		);
	}

	/**
	 * Gather every GDPR-relevant record for a user as export items.
	 *
	 * @return array<int, array{group_id:string,group_label:string,item_id:string,data:array}>
	 * @param int $user_id * @return array<int, array{group_id:string,group_label:string,item_id:string,data:array}>.
	 */
	private static function collect_export_data( int $user_id ): array {
		$data = array();

		// Owned business listings.
		$businesses = Services::businesses()->get_by_owner( $user_id, 100 );
		foreach ( $businesses as $biz ) {
			$value = sprintf( '%s (%s)', $biz->name, $biz->status );
			if ( $biz->city ) {
				$value .= ', ' . $biz->city;
			}
			if ( $biz->phone ) {
				$value .= ', ' . $biz->phone;
			}
			if ( $biz->email ) {
				$value .= ', ' . $biz->email;
			}
			$data[] = self::item( __( 'Owned business listing', 'zeko-business' ), $value, count( $data ) + 1 );
		}

		// Reviews written by the user.
		$reviews = Services::reviews()->get_by_user( $user_id );
		foreach ( $reviews as $review ) {
			/* translators: 1: business ID. 2: rating out of 5. 3: review content */
			$value  = sprintf( __( 'Business #%1$d — rating %2$d/5: %3$s', 'zeko-business' ), (int) $review->business_id, (int) $review->rating, $review->content );
			$data[] = self::item( __( 'Review', 'zeko-business' ), $value, count( $data ) + 1 );
		}

		// Businesses the user follows.
		$follows = Services::followers()->get_following( $user_id, 100 );
		foreach ( $follows as $follow ) {
			$data[] = self::item( __( 'Followed business', 'zeko-business' ), (string) (int) $follow->business_id, count( $data ) + 1 );
		}

		// Claims submitted by the user.
		foreach ( Services::claims()->find( array( 'user_id' => $user_id ) ) as $claim ) {
			/* translators: 1: business ID. 2: request method. 3: request status */
			$value  = sprintf( __( 'Business #%1$d — method: %2$s — status: %3$s', 'zeko-business' ), (int) $claim->business_id, $claim->method, $claim->status );
			$data[] = self::item( __( 'Business claim', 'zeko-business' ), $value, count( $data ) + 1 );
		}

		// Verification requests submitted by the user.
		foreach ( Services::verification()->find( array( 'user_id' => $user_id ) ) as $request ) {
			/* translators: 1: business ID. 2: request method. 3: request status */
			$value  = sprintf( __( 'Business #%1$d — method: %2$s — status: %3$s', 'zeko-business' ), (int) $request->business_id, $request->method, $request->status );
			$data[] = self::item( __( 'Verification request', 'zeko-business' ), $value, count( $data ) + 1 );
		}

		return $data;
	}

	/**
	 * Erase data.
	 *
	 * @param string $email Email.
	 * @param int    $page Page.
	 */
	public static function erase_data( string $email, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$user_id = (int) $user->ID;

		// Number of erase operations performed so far across pages, so we can.
		// resume at the correct offset. Stored as state because erasure is.
		// destructive and cannot simply be re-enumerated.
		$state_key = 'zbe_gdpr_erase_' . $user_id;
		$progress  = (int) get_option( $state_key, 0 );

		// Build the full plan lazily on the first page, then cache the list of.
		// pending actions as an option so later pages resume deterministically.
		$plan = get_option( $state_key . '_plan', null );

		if ( null === $plan ) {
			$plan = self::build_erase_plan( $user_id );
			update_option( $state_key . '_plan', $plan, false );
		}

		$per_page   = 100;
		$done_count = 0;
		$removed    = 0;
		$retained   = 0;
		$messages   = array();
		$slice      = array_slice( $plan, $progress, $per_page );

		foreach ( $slice as $job ) {
			$ok = self::execute_erase( $job );
			if ( $ok ) {
				++$removed;
			} else {
				++$retained;
			}
			++$done_count;
		}

		$progress += $done_count;
		update_option( $state_key, $progress, false );

		$finished = $progress >= count( $plan );

		if ( $finished ) {
			delete_option( $state_key );
			delete_option( $state_key . '_plan' );
		}

		if ( $retained > 0 ) {
			$messages[] = __( 'Some business data could not be erased and may need manual review.', 'zeko-business' );
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained > 0,
			'messages'       => $messages,
			'done'           => $finished,
		);
	}

	/**
	 * Build the ordered list of erase jobs for a user. Each job is a small
	 * map describing the entity type and the id to erase.
	 *
	 * @return array<int, array{type:string,id:int}>
	 * @param int $user_id * @return array<int, array{type:string,id:int}>.
	 */
	private static function build_erase_plan( int $user_id ): array {
		$plan = array();

		foreach ( Services::reviews()->get_by_user( $user_id ) as $review ) {
			$plan[] = array(
				'type' => 'review',
				'id'   => (int) $review->id,
				'uid'  => $user_id,
			);
		}

		foreach ( Services::followers()->get_following( $user_id, 1000 ) as $follow ) {
			$plan[] = array(
				'type' => 'follow',
				'id'   => (int) $follow->business_id,
				'uid'  => $user_id,
			);
		}

		foreach ( Services::claims()->find( array( 'user_id' => $user_id ) ) as $claim ) {
			$plan[] = array(
				'type' => 'claim',
				'id'   => (int) $claim->id,
				'uid'  => $user_id,
			);
		}

		foreach ( Services::verification()->find( array( 'user_id' => $user_id ) ) as $request ) {
			$plan[] = array(
				'type' => 'verification',
				'id'   => (int) $request->id,
				'uid'  => $user_id,
			);
		}

		foreach ( Services::businesses()->get_by_owner( $user_id, 1000 ) as $biz ) {
			$plan[] = array(
				'type' => 'business',
				'id'   => (int) $biz->id,
				'uid'  => $user_id,
			);
		}

		return $plan;
	}

	/**
	 * Execute a single erase job.
	 *
	 * @return bool True if a record was removed.
	 * @param array $job * @return bool True if a record was removed.
	 */
	private static function execute_erase( array $job ): bool {
		switch ( $job['type'] ) {
			case 'review':
				return Services::reviews()->delete( (int) $job['id'] );
			case 'follow':
				return Services::followers()->delete_for_user( (int) $job['id'], (int) $job['uid'] );
			case 'claim':
				return Services::claims()->delete( (int) $job['id'] );
			case 'verification':
				return Services::verification()->delete( (int) $job['id'] );
			case 'business':
				return Services::business_service()->delete( (int) $job['id'], (int) $job['uid'] );
		}

		return false;
	}

	/**
	 * Item.
	 *
	 * @param string $group Group.
	 * @param string $data Data.
	 * @param int    $sid Sid.
	 */
	private static function item( string $group, string $data, int $sid ): array {
		return array(
			'group_id'    => 'zeko-business',
			'group_label' => __( 'Zeko Business Directory', 'zeko-business' ),
			'item_id'     => 'zeko-business-' . $sid,
			'data'        => array(
				array(
					'name'  => $group,
					'value' => $data,
				),
			),
		);
	}
}
