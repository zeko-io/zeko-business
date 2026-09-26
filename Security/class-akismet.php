<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal Akismet integration for user-generated text (reviews, claims,
 * verification notes, business submissions).
 *
 * Uses the Akismet plugin's public API when it is active and configured, and
 * gracefully no-ops (never blocks) when it is not available.
 */
class Akismet {

	/**
	 * Whether the Akismet plugin is installed, active and has a valid key.
	 */
	public static function is_available(): bool {
		if ( ! function_exists( 'akismet_http_post' ) ) {
			return false;
		}

		$key = self::api_key();

		if ( empty( $key ) ) {
			return false;
		}

		$verified = get_transient( 'zbp_akismet_key_check' );

		if ( null === $verified ) {
			$verified = function_exists( 'akismet_check_key' ) ? (bool) akismet_check_key() : true;
			set_transient( 'zbp_akismet_key_check', $verified ? 1 : 0, 12 * HOUR_IN_SECONDS );
		}

		return (bool) $verified;
	}

	/**
	 * Get the configured Akismet API key.
	 */
	public static function api_key(): string {
		$key = get_option( 'akismet_api_key' );

		if ( empty( $key ) ) {
			$key = get_option( 'wordpress_api_key' );
		}

		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Run an Akismet comment-check on user-generated content.
	 *
	 * @return bool True when the content is flagged as spam.
	 * @param array $args Permission/author/content details (all optional).
	 */
	public static function is_spam( array $args = array() ): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		$content = trim( (string) ( $args['content'] ?? '' ) );

		if ( '' === $content ) {
			return false;
		}

		$request = self::build_request( $args );

		// The Akismet plugin registers an autoloader that exposes this.
		// global wrapper around Akismet::http_post() when active.
		$response = akismet_http_post(
			$request,
			self::host(),
			'/1.1/comment-check',
			80,
			RateLimiter::get_ip()
		);

		if ( empty( $response ) || ! is_array( $response ) ) {
			return false;
		}

		$response_code = (int) ( $response[0] ?? 0 );
		$body          = (string) ( $response[1] ?? '' );

		return 200 === $response_code && 'true' === strtolower( trim( $body ) );
	}

	/**
	 * Build the Akismet comment-check request body array.
	 *
	 * @param array $args Args.
	 */
	private static function build_request( array $args ): array {
		$blog = get_option( 'home' );

		$request = array(
			'blog'                 => $blog,
			'blog_lang'            => get_locale(),
			'blog_charset'         => get_bloginfo( 'charset' ),
			'user_ip'              => RateLimiter::get_ip(),
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
			'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), 0, 255 ) : '',
			'permalink'            => (string) ( $args['permalink'] ?? $blog ),
			'comment_author'       => (string) ( $args['author'] ?? '' ),
			'comment_author_email' => (string) ( $args['email'] ?? '' ),
			'comment_author_url'   => (string) ( $args['url'] ?? '' ),
			'comment_type'         => (string) ( $args['comment_type'] ?? 'comment' ),
			'comment_content'      => (string) ( $args['content'] ?? '' ),
		);

		return array_filter(
			$request,
			static function ( $value ) {
				return '' !== $value && null !== $value && false !== $value;
			}
		);
	}

	/**
	 * Resolve the Akismet API host (the plugin exposes AKISMET_API_HOST; a key
	 * is not part of the host when the Akismet plugin already handles it.
	 */
	private static function host(): string {
		if ( defined( 'AKISMET_API_HOST' ) && AKISMET_API_HOST ) {
			return AKISMET_API_HOST;
		}

		return self::api_key() . '.rest.akismet.com';
	}
}
