<?php
/**
 * Lightweight geocoding + proximity helpers.
 *
 * Geocoding is performed against OpenStreetMap's public Nominatim service.
 * Responses are cached in transients for 30 days to respect the service's
 * usage policy and to avoid hammering the public endpoint.
 *
 * @package Zeko_ZEKO_BUSINESS
 **/

namespace ZBE\Core;

defined( 'ABSPATH' ) || exit;

/** Class Geocode. */
class Geocode {

	const TRANSIENT_PREFIX = 'zbe_geo_';
	const TTL              = 30 * DAY_IN_SECONDS;

	/**
	 * Resolve a free-text location to a [lat, lng] pair, or null on failure.
	 *
	 * @return array{0:float,1:float}|null
	 * @param string $location City / "City, Region" / "City, Country" string.
	 */
	public static function resolve( string $location ): ?array {
		$location = trim( $location );
		if ( '' === $location ) {
			return null;
		}

		$key = self::TRANSIENT_PREFIX . md5( strtolower( $location ) );

		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['lat'], $cached['lng'] ) ) {
			return array( (float) $cached['lat'], (float) $cached['lng'] );
		}

		if ( ! self::http_available() ) {
			return null;
		}

		$url = add_query_arg(
			array(
				'q'      => $location,
				'format' => 'json',
				'limit'  => 1,
			),
			'https://nominatim.openstreetmap.org/search'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'User-Agent' => 'ZekoBusiness/1.0' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data[0] ) ) {
			set_transient(
				$key,
				array(
					'lat' => 0,
					'lng' => 0,
				),
				self::TTL
			);

			return null;
		}

		$lat = (float) ( $data[0]['lat'] ?? 0 );
		$lng = (float) ( $data[0]['lon'] ?? 0 );

		set_transient(
			$key,
			array(
				'lat' => $lat,
				'lng' => $lng,
			),
			self::TTL
		);

		return array( $lat, $lng );
	}

	/**
	 * Haversine distance between two points.
	 *
	 * @return float
	 * @param float $lat1 * @param float $lng1.
	 * @param float $lng1 Lng1.
	 * @param float $lat2 * @param float $lng2.
	 * @param float $lng2 Lng2.
	 * @param bool  $metric True for kilometers, false for miles.
	 */
	public static function haversine( float $lat1, float $lng1, float $lat2, float $lng2, bool $metric = false ): float {
		$earth = $metric ? 6371.0 : 3959.0;

		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lng = deg2rad( $lng2 - $lng1 );

		$a = sin( $d_lat / 2 ) ** 2
			+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lng / 2 ) ** 2;

		return $earth * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
	}

	/**
	 * Http available.
	 */
	private static function http_available(): bool {
		return function_exists( 'wp_remote_get' ) && wp_http_validate_url( home_url() );
	}
}
