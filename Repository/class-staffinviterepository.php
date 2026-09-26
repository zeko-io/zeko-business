<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Repository;

use ZBE\Entity\StaffInvite;

defined( 'ABSPATH' ) || exit;

/** Class StaffInviteRepository. */
class StaffInviteRepository {

	private const TABLE_SUFFIX = 'zbp_staff_invites';

	private const FORMAT = array(
		'business_id'  => '%d',
		'email'        => '%s',
		'role'         => '%s',
		'token'        => '%s',
		'invited_by'   => '%d',
		'status'       => '%s',
		'expires_at'   => '%s',
		'accepted_at'  => '%s',
		'date_created' => '%s',
	);

	/**
	 * Table.
	 */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Format for db.
	 *
	 * @param array $data Data.
	 */
	private static function format_for_db( array $data ): array {
		$formatted = array();
		foreach ( $data as $key => $value ) {
			if ( isset( self::FORMAT[ $key ] ) ) {
				$formatted[ $key ] = $value;
			}
		}
		return $formatted;
	}

	/**
	 * Create.
	 *
	 * @param array $data Data.
	 */
	public function create( array $data ): StaffInvite {
		global $wpdb;

		$invite               = new StaffInvite();
		$invite->business_id  = $data['business_id'] ?? 0;
		$invite->email        = $data['email'] ?? '';
		$invite->role         = $data['role'] ?? 'staff';
		$invite->token        = $data['token'] ?? wp_generate_password( 32, false );
		$invite->invited_by   = $data['invited_by'] ?? 0;
		$invite->status       = $data['status'] ?? 'pending';
		$invite->expires_at   = $data['expires_at'] ?? gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) );
		$invite->date_created = current_time( 'mysql' );

		$db_data = self::format_for_db( get_object_vars( $invite ) );
		$wpdb->insert( self::table(), $db_data, array_values( self::FORMAT ) ); // phpcs:ignore WordPress.DB
		$invite->id = (int) $wpdb->insert_id;

		return $invite;
	}

	/**
	 * Get.
	 *
	 * @param int $id Id.
	 */
	public function get( int $id ): ?StaffInvite {
		global $wpdb;

		if ( $id < 1 ) {
			return null;
		}

		$table = self::table();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return $row ? StaffInvite::from_row( $row ) : null;
	}

	/**
	 * By token.
	 *
	 * @param string $token Token.
	 */
	public function get_by_token( string $token ): ?StaffInvite {
		global $wpdb;

		if ( '' === $token ) {
			return null;
		}

		$table = self::table();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE token = %s AND status = 'pending'", $token )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return $row ? StaffInvite::from_row( $row ) : null;
	}

	/**
	 * Pending by business.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_pending_by_business( int $business_id ): array {
		global $wpdb;

		$table = self::table();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE business_id = %d AND status = 'pending' ORDER BY date_created DESC",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return array_map( array( StaffInvite::class, 'from_row' ), $results );
	}

	/**
	 * Update.
	 *
	 * @param int   $id Id.
	 * @param array $data Data.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$db_data = self::format_for_db( $data );

		if ( empty( $db_data ) ) {
			return false;
		}

		$table = self::table();

		//phpcs:ignore WordPress.DB
		return (bool) $wpdb->update( $table, $db_data, array( 'id' => $id ), null, array( '%d' ) );
	}

	/**
	 * Count pending by email.
	 *
	 * @param string $email Email.
	 */
	public function count_pending_by_email( string $email ): int {
		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE email = %s AND status = 'pending'",
				$email
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}
}
