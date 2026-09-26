<?php
/**
 * Plan tier definitions and feature gating matrix.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Core;

defined( 'ABSPATH' ) || exit;

/** Class Plans. */
final class Plans {

	const FREE       = 'free';
	const BASIC      = 'basic';
	const PRO        = 'pro';
	const ENTERPRISE = 'enterprise';

	/**
	 * TIERS.
	 *
	 * @var mixed
	 */
	const TIERS = array(
		self::FREE,
		self::BASIC,
		self::PRO,
		self::ENTERPRISE,
	);

	/**
	 * MATRIX.
	 *
	 * @var mixed
	 */
	const MATRIX = array(
		self::FREE       => array(
			'max_photos'       => 0,
			'max_services'     => 3,
			'gallery'          => false,
			'hours'            => false,
			'socials'          => false,
			'featured'         => false,
			'sponsored'        => false,
			'priority_support' => false,
		),
		self::BASIC      => array(
			'max_photos'       => 5,
			'max_services'     => 10,
			'gallery'          => true,
			'hours'            => true,
			'socials'          => true,
			'featured'         => true,
			'sponsored'        => false,
			'priority_support' => false,
		),
		self::PRO        => array(
			'max_photos'       => 20,
			'max_services'     => 0,
			'gallery'          => true,
			'hours'            => true,
			'socials'          => true,
			'featured'         => true,
			'sponsored'        => true,
			'priority_support' => false,
		),
		self::ENTERPRISE => array(
			'max_photos'       => 20,
			'max_services'     => 0,
			'gallery'          => true,
			'hours'            => true,
			'socials'          => true,
			'featured'         => true,
			'sponsored'        => true,
			'priority_support' => true,
		),
	);

	/**
	 * Pricing per plan tier (one-time upgrade price).
	 * Loaded from settings with these as defaults.
	 *
	 * @return array<string, float>
	 */
	public static function default_prices(): array {
		return array(
			self::FREE       => 0.0,
			self::BASIC      => 19.99,
			self::PRO        => 49.99,
			self::ENTERPRISE => 99.99,
		);
	}

	/**
	 * Get plan prices from settings, falling back to defaults.
	 *
	 * @return array<string, float>
	 */
	public static function get_prices(): array {
		$settings = get_option( 'zbe_settings', array() );
		$stored   = isset( $settings['plans']['prices'] ) && is_array( $settings['plans']['prices'] )
			? $settings['plans']['prices']
			: array();

		return wp_parse_args( $stored, self::default_prices() );
	}

	/**
	 * Get the feature value for a given plan.
	 *
	 * @return mixed
	 * @param string $plan Plan tier slug.
	 * @param string $feature Feature key.
	 * @param bool   $default Fallback value.
	 */
	public static function get( string $plan, string $feature, $default = false ) {
		if ( ! in_array( $plan, self::TIERS, true ) ) {
			$plan = self::FREE;
		}

		return isset( self::MATRIX[ $plan ][ $feature ] )
			? self::MATRIX[ $plan ][ $feature ]
			: $default;
	}

	/**
	 * Check if a plan has a boolean feature enabled.
	 *
	 * @param string $plan Plan.
	 * @param string $feature Feature.
	 */
	public static function allows( string $plan, string $feature ): bool {
		return (bool) self::get( $plan, $feature, false );
	}

	/**
	 * Validate that a plan string is a recognized tier.
	 *
	 * @param string $plan Plan.
	 */
	public static function is_valid( string $plan ): bool {
		return in_array( $plan, self::TIERS, true );
	}

	/**
	 * Get the plan tier order (higher = better). Used for comparison.
	 *
	 * @param string $plan Plan.
	 */
	public static function level( string $plan ): int {
		$levels = array(
			self::FREE       => 0,
			self::BASIC      => 1,
			self::PRO        => 2,
			self::ENTERPRISE => 3,
		);

		return isset( $levels[ $plan ] ) ? $levels[ $plan ] : 0;
	}
}
