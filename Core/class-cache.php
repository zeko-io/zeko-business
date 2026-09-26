<?php
/**
 * Per-request + object-cache-backed store for repeated lookups.
 *
 * Values live in an in-memory mirror (zero-cost repeat reads) backed by
 * wp_cache_* so Redis/Memcached drop-ins make them persist across requests
 * automatically. All plugin caches share one group so writes can be
 * invalidated cheaply.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Core;

defined( 'ABSPATH' ) || exit;

/** Class Cache. */
final class Cache {

	const GROUP = 'zbp';

	/**
	 * Memory.
	 *
	 * @var array Memory.
	 */
	private static array $memory = array();

	/**
	 * Construct.
	 */
	private function __construct() {}

	/**
	 * Fetch a cached value.
	 *
	 * @return mixed|null Null on miss.
	 * @param string $key Cache key.
	 */
	public static function get( string $key ) {
		if ( array_key_exists( $key, self::$memory ) ) {
			return self::$memory[ $key ];
		}

		$found = false;
		$value = wp_cache_get( $key, self::GROUP, false, $found );

		if ( ! $found ) {
			return null;
		}

		self::$memory[ $key ] = $value;

		return $value;
	}

	/**
	 * Store a value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl Seconds (object cache only; memory lives for the request).
	 */
	public static function set( string $key, $value, int $ttl = 300 ): void {
		self::$memory[ $key ] = $value;
		wp_cache_set( $key, $value, self::GROUP, max( 1, $ttl ) );
	}

	/**
	 * Get-or-compute helper.
	 *
	 * @return mixed
	 * @param string   $key Cache key.
	 * @param int      $ttl TTL in seconds.
	 * @param callable $callback Produces the value on miss.
	 */
	public static function remember( string $key, int $ttl, callable $callback ) {
		$cached = self::get( $key );

		if ( null !== $cached ) {
			return $cached;
		}

		$value = $callback();
		self::set( $key, $value, $ttl );

		return $value;
	}

	/**
	 * Delete.
	 *
	 * @param string $key Key.
	 */
	public static function delete( string $key ): void {
		unset( self::$memory[ $key ] );
		wp_cache_delete( $key, self::GROUP );
	}

	/**
	 * Invalidate every key in the plugin group. Called after repository
	 * writes; cheap because reads recompute lazily.
	 */
	public static function flush(): void {
		self::$memory = array();

		if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_flush_group( self::GROUP ) ) {
			return;
		}

		// Non-persistent object cache (default): per-request data is already gone.
		if ( ! wp_using_ext_object_cache() ) {
			return;
		}

		wp_cache_flush();
	}
}
