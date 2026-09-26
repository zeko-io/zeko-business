<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Frontend;

use ZBE\Core\Services;
use ZBE\Security\RateLimiter;

defined( 'ABSPATH' ) || exit;

/** Class RestApi. */
class RestApi {
	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'zbe/v1',
			'/businesses',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_businesses' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'search'   => array( 'type' => 'string' ),
						'status'   => array(
							'type'    => 'string',
							'default' => 'active',
						),
						'city'     => array( 'type' => 'string' ),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_business' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		register_rest_route(
			'zbe/v1',
			'/businesses/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_business' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_business' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_business' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		register_rest_route(
			'zbe/v1',
			'/businesses/(?P<id>\d+)/reviews',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_reviews' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_review' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		register_rest_route(
			'zbe/v1',
			'/businesses/(?P<id>\d+)/services',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_services' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'zbe/v1',
			'/businesses/(?P<id>\d+)/followers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_followers' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'follow' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'unfollow' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);
	}

	/**
	 * Check auth.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function check_auth( \WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$method = $request->get_method();
		if ( 'GET' === $method || 'HEAD' === $method || 'OPTIONS' === $method ) {
			return true;
		}

		$user = wp_get_current_user();
		return $user->exists() && ( $user->has_cap( 'manage_zbp' ) || $user->has_cap( 'edit_business' ) || $user->has_cap( 'publish_businesses' ) );
	}

	/**
	 * List args.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	private static function list_args( \WP_REST_Request $request ): array {
		// The list route is public (permission_callback => __return_true), so it.
		// must never expose non-published businesses. Only allow statuses that.
		// are safe to show to anonymous visitors.
		$status = sanitize_key( $request->get_param( 'status' ) ?: 'active' );
		if ( 'active' !== $status ) {
			$status = 'active';
		}

		$args = array(
			'status'   => $status,
			'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'city'     => sanitize_text_field( (string) $request->get_param( 'city' ) ),
			'orderby'  => 'directory',
			'order'    => 'DESC',
			'per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) ),
			'page'     => max( 1, (int) $request->get_param( 'page' ) ),
		);

		return array_filter( $args, static fn( $v ) => '' !== $v && null !== $v );
	}

	/**
	 * List businesses.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function list_businesses( \WP_REST_Request $request ): \WP_REST_Response {
		$args       = self::list_args( $request );
		$repo       = Services::businesses();
		$total      = $repo->count( $args );
		$businesses = array_map( static fn( $biz ) => self::serialize_business( $biz ), $repo->find( $args ) );

		$response = new \WP_REST_Response( $businesses );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $args['per_page'] ) );
		return $response;
	}

	/**
	 * Business.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function get_business( \WP_REST_Request $request ): \WP_REST_Response {
		$biz = Services::businesses()->get( (int) $request->get_param( 'id' ) );

		if ( ! $biz || 'active' !== $biz->status ) {
			return new \WP_REST_Response( array( 'message' => 'Business not found' ), 404 );
		}

		return new \WP_REST_Response( self::serialize_business( $biz ) );
	}

	/**
	 * Create business.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function create_business( \WP_REST_Request $request ): \WP_REST_Response {
		if ( self::is_limited( 'create_business', 5, 600 ) ) {
			return new \WP_REST_Response( array( 'message' => 'Too many requests' ), 429 );
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
		if ( empty( $name ) ) {
			return new \WP_REST_Response( array( 'message' => 'Name is required' ), 400 );
		}

		$user_id = get_current_user_id();

		$business = Services::business_service()->create(
			array(
				'name'    => $name,
				'email'   => sanitize_email( $request->get_param( 'email' ) ?? '' ),
				'phone'   => sanitize_text_field( $request->get_param( 'phone' ) ?? '' ),
				'website' => esc_url_raw( $request->get_param( 'website' ) ?? '' ),
				'address' => sanitize_textarea_field( $request->get_param( 'address' ) ?? '' ),
				'city'    => sanitize_text_field( $request->get_param( 'city' ) ?? '' ),
				'state'   => sanitize_text_field( $request->get_param( 'state' ) ?? '' ),
				'country' => sanitize_text_field( $request->get_param( 'country' ) ?? '' ),
			),
			$user_id
		);

		if ( ! $business ) {
			return new \WP_REST_Response( array( 'message' => 'Business could not be created' ), 500 );
		}

		return new \WP_REST_Response( self::serialize_business( $business ), 201 );
	}

	/**
	 * Update business.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function update_business( \WP_REST_Request $request ): \WP_REST_Response {
		if ( self::is_limited( 'update_business', 30, 300 ) ) {
			return new \WP_REST_Response( array( 'message' => 'Too many requests' ), 429 );
		}

		$id      = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$biz = Services::businesses()->get( $id );

		if ( ! $biz ) {
			return new \WP_REST_Response( array( 'message' => 'Not found' ), 404 );
		}

		if ( (int) $biz->owner_id !== $user_id && ! current_user_can( 'edit_others_businesses' ) ) {
			return new \WP_REST_Response( array( 'message' => 'Not authorized' ), 403 );
		}

		$sanitizers = array(
			'name'    => 'sanitize_text_field',
			'email'   => 'sanitize_email',
			'phone'   => 'sanitize_text_field',
			'website' => 'esc_url_raw',
			'address' => 'sanitize_textarea_field',
			'city'    => 'sanitize_text_field',
			'state'   => 'sanitize_text_field',
			'country' => 'sanitize_text_field',
		);

		$update = array();
		foreach ( $sanitizers as $field => $sanitizer ) {
			$val = $request->get_param( $field );
			if ( null !== $val && '' !== $val ) {
				$update[ $field ] = call_user_func( $sanitizer, $val );
			}
		}

		if ( ! empty( $update ) && ! Services::business_service()->update( $id, $update, $user_id ) ) {
			return new \WP_REST_Response( array( 'message' => 'Update failed' ), 500 );
		}

		return new \WP_REST_Response( array( 'message' => 'Updated' ) );
	}

	/**
	 * Delete business.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function delete_business( \WP_REST_Request $request ): \WP_REST_Response {
		if ( self::is_limited( 'delete_business', 10, 300 ) ) {
			return new \WP_REST_Response( array( 'message' => 'Too many requests' ), 429 );
		}

		$id = (int) $request->get_param( 'id' );

		if ( ! Services::business_service()->delete( $id, get_current_user_id() ) ) {
			return new \WP_REST_Response( array( 'message' => 'Not found or not authorized' ), 404 );
		}

		return new \WP_REST_Response( array( 'message' => 'Deleted' ) );
	}

	/**
	 * List reviews.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function list_reviews( \WP_REST_Request $request ): \WP_REST_Response {
		$reviews = Services::reviews()->get_by_business( (int) $request->get_param( 'id' ), 100 );

		$data = array_map(
			static function ( $review ) {
				$arr                = $review->to_array();
				$author             = get_userdata( (int) $review->user_id );
				$arr['author_name'] = $author ? $author->display_name : '';
				return $arr;
			},
			$reviews
		);

		return new \WP_REST_Response( $data );
	}

	/**
	 * Create review.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function create_review( \WP_REST_Request $request ): \WP_REST_Response {
		if ( self::is_limited( 'review', 3, 300 ) ) {
			return new \WP_REST_Response( array( 'message' => 'Too many requests' ), 429 );
		}

		$biz_id  = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();
		$rating  = (int) $request->get_param( 'rating' );
		$title   = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$content = sanitize_textarea_field( $request->get_param( 'content' ) ?? '' );

		if ( ! $biz_id || $rating < 1 || $rating > 5 || empty( $content ) ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid data' ), 400 );
		}

		foreach ( Services::reviews()->get_by_user( $user_id ) as $existing ) {
			if ( (int) $existing->business_id === $biz_id ) {
				return new \WP_REST_Response( array( 'message' => 'You have already reviewed this business' ), 409 );
			}
		}

		$review = Services::review_service()->create( $biz_id, $user_id, $rating, $title, $content );

		if ( ! $review ) {
			return new \WP_REST_Response( array( 'message' => 'Review could not be submitted' ), 500 );
		}

		return new \WP_REST_Response(
			array(
				'message' => 'Review submitted',
				'id'      => $review->id,
			),
			201
		);
	}

	/**
	 * List services.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function list_services( \WP_REST_Request $request ): \WP_REST_Response {
		$services = Services::services_repo()->get_by_business( (int) $request->get_param( 'id' ), true );

		return new \WP_REST_Response( array_map( static fn( $svc ) => $svc->to_array(), $services ) );
	}

	/**
	 * List followers.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function list_followers( \WP_REST_Request $request ): \WP_REST_Response {
		$followers = Services::followers()->get_followers( (int) $request->get_param( 'id' ), 100 );

		$data = array_map(
			static function ( $follower ) {
				$arr                 = $follower->to_array();
				$user                = get_userdata( (int) $follower->user_id );
				$arr['display_name'] = $user ? $user->display_name : '';
				return $arr;
			},
			$followers
		);

		return new \WP_REST_Response( $data );
	}

	/**
	 * Follow.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function follow( \WP_REST_Request $request ): \WP_REST_Response {
		if ( self::is_limited( 'follow', 10, 60 ) ) {
			return new \WP_REST_Response( array( 'message' => 'Too many requests' ), 429 );
		}

		$biz_id  = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		if ( ! Services::business_service()->follow( $biz_id, $user_id ) ) {
			return new \WP_REST_Response( array( 'message' => 'Already following or unavailable' ), 409 );
		}

		return new \WP_REST_Response(
			array(
				'message'        => 'Following',
				'follower_count' => Services::followers()->count( $biz_id ),
			)
		);
	}

	/**
	 * Unfollow.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function unfollow( \WP_REST_Request $request ): \WP_REST_Response {
		if ( self::is_limited( 'unfollow', 10, 60 ) ) {
			return new \WP_REST_Response( array( 'message' => 'Too many requests' ), 429 );
		}

		$biz_id  = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		if ( ! Services::business_service()->unfollow( $biz_id, $user_id ) ) {
			return new \WP_REST_Response( array( 'message' => 'You are not following this business' ), 409 );
		}

		return new \WP_REST_Response(
			array(
				'message'        => 'Unfollowed',
				'follower_count' => Services::followers()->count( $biz_id ),
			)
		);
	}

	/**
	 * Public JSON representation of a business entity.
	 *
	 * @param object $biz Biz.
	 */
	private static function serialize_business( object $biz ): array {
		$data = method_exists( $biz, 'to_array' ) ? $biz->to_array() : (array) $biz;
		unset( $data['post_id'] );

		return $data;
	}

	/**
	 * Rate-limit a REST write action, keyed by the current user id.
	 *
	 * @param string $action Action.
	 * @param int    $limit Limit.
	 * @param int    $window Window.
	 */
	private static function is_limited( string $action, int $limit, int $window ): bool {
		$user_id = get_current_user_id();
		return RateLimiter::is_limited( $action, $limit, $window, (string) $user_id );
	}
}
