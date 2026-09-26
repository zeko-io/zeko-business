<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Security;

defined( 'ABSPATH' ) || exit;

/** Class RateLimiter. */
class RateLimiter {

	/**
	 * Check if an action has exceeded its rate limit.
	 *
	 * @return bool True if rate limit exceeded.
	 * @param string $action Unique action key (e.g. 'review', 'follow').
	 * @param int    $limit Max attempts allowed.
	 * @param int    $window Time window in seconds.
	 * @param string $context Optional context suffix (e.g. IP, user ID).
	 */
	public static function is_limited( string $action, int $limit = 5, int $window = 60, string $context = '' ): bool {
		$key   = 'zbp_ratelimit/' . $action . '/' . md5( $context ?: self::get_ip() );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return true;
		}

		set_transient( $key, $count + 1, $window );

		return false;
	}

	/**
	 * Get the visitor's IP address, respecting proxy headers.
	 */
	public static function get_ip(): string {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		}

		return $ip;
	}
}
