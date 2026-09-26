<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Core;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Demo data generator for the Zeko Business Directory.
 *
 * Seeds a realistic set of businesses (posts + zbp_businesses rows) together
 * with services (via Zeko Shop), operating hours, reviews, followers, media,
 * service categories and business_category terms. Every seeded id is tracked
 * in a single option (zbe_demo_registry) so it can be removed cleanly.
 *
 * Uses repositories/locators only (no raw $wpdb outside the DB layer).
 */
final class DemoSeeder {

	/**
	 * REGISTRY.
	 *
	 * @var string
	 */
	private const REGISTRY = 'zbe_demo_registry';

	/**
	 * Construct.
	 */
	private function __construct() {}

	/**
	 * Generate demo data.
	 *
	 * @return array{added:int, businesses:int, services:int, reviews:int, followers:int} Stats.
	 * @param int $count Number of businesses to seed (clamped 1..10).
	 */
	public static function generate( int $count = 10 ): array {
		$count = max( 1, min( 10, $count ) );
		$defs  = array_slice( self::dataset(), 0, $count );

		$registry = self::registry();
		$stats    = array(
			'added'      => 0,
			'businesses' => 0,
			'services'   => 0,
			'reviews'    => 0,
			'followers'  => 0,
			'media'      => 0,
		);

		$owner_id = self::owner_id();
		if ( ! $owner_id ) {
			return $stats;
		}

		// Ensure global service categories + business_category terms exist once.
		self::seed_service_categories( $registry );
		$term_ids             = self::ensure_terms();
		$registry['term_ids'] = array_unique( array_merge( $registry['term_ids'] ?? array(), $term_ids ) );

		$reviewer_ids = self::create_reviewers( $registry );

		foreach ( $defs as $def ) {
			$result = self::create_business( $def, $owner_id, $reviewer_ids, $registry );

			foreach ( $result as $key => $val ) {
				if ( isset( $stats[ $key ] ) ) {
					$stats[ $key ] += $val;
				}
			}
			++$stats['added'];
		}

		self::save_registry( $registry );
		\ZBE\Core\Cache::flush();

		return $stats;
	}

	/**
	 * Remove all tracked demo data. Returns a count of removed items.
	 */
	public static function remove(): array {
		$registry = self::registry();
		$stats    = array(
			'removed'     => 0,
			'businesses'  => 0,
			'services'    => 0,
			'reviews'     => 0,
			'followers'   => 0,
			'media'       => 0,
			'categories'  => 0,
			'terms'       => 0,
			'users'       => 0,
			'attachments' => 0,
		);

		// Followers (FK-less but referenced by id).
		foreach ( (array) ( $registry['follower_ids'] ?? array() ) as $id ) {
			if ( Services::followers()->delete( (int) $id ) ) {
				++$stats['removed'];
				++$stats['followers'];
			}
		}

		// Reviews.
		foreach ( (array) ( $registry['review_ids'] ?? array() ) as $id ) {
			if ( Services::reviews()->delete( (int) $id ) ) {
				++$stats['removed'];
				++$stats['reviews'];
			}
		}

		// Media.
		foreach ( (array) ( $registry['media_ids'] ?? array() ) as $id ) {
			if ( Services::media()->delete( (int) $id ) ) {
				++$stats['removed'];
				++$stats['media'];
			}
		}

		// Hour rows per seeded business (hours repo has no delete; clear + no-op replacement).
		foreach ( (array) ( $registry['business_ids'] ?? array() ) as $biz_id ) {
			\ZBE\Core\Services::hours()->delete_for_business( (int) $biz_id );
		}

		// Services (now in Zeko Shop via the adapter).
		foreach ( (array) ( $registry['service_ids'] ?? array() ) as $id ) {
			if ( Services::services_repo()->delete( (int) $id ) ) {
				++$stats['removed'];
				++$stats['services'];
			}
		}

		// Business rows + posts.
		foreach ( (array) ( $registry['business_ids'] ?? array() ) as $id ) {
			if ( Services::businesses()->delete( (int) $id ) ) {
				++$stats['removed'];
				++$stats['businesses'];
			}
		}
		foreach ( (array) ( $registry['post_ids'] ?? array() ) as $post_id ) {
			wp_delete_post( (int) $post_id, true );
			++$stats['removed'];
		}

		// Attachments (media-library placeholders) we created.
		foreach ( (array) ( $registry['attachment_ids'] ?? array() ) as $att_id ) {
			wp_delete_attachment( (int) $att_id, true );
			++$stats['removed'];
			++$stats['attachments'];
		}

		// Service categories (only ones that exist).
		foreach ( (array) ( $registry['category_ids'] ?? array() ) as $id ) {
			if ( Services::service_categories()->delete( (int) $id ) ) {
				++$stats['removed'];
				++$stats['categories'];
			}
		}

		// business_category terms we created (only if created by us).
		foreach ( (array) ( $registry['term_ids'] ?? array() ) as $term_id ) {
			$deleted = wp_delete_term( (int) $term_id, 'business_category' );

			if ( $deleted && ! is_wp_error( $deleted ) ) {
				++$stats['removed'];
				++$stats['terms'];
			}
		}

		// Demo review users (deleted with reassignment to 0).
		foreach ( (array) ( $registry['user_ids'] ?? array() ) as $user_id ) {
			if ( wp_delete_user( (int) $user_id, 0 ) ) {
				++$stats['removed'];
				++$stats['users'];
			}
		}

		delete_option( self::REGISTRY );
		delete_option( 'zbe_demo_business_id' );
		\ZBE\Core\Cache::flush();

		return $stats;
	}

	/**
	 * Create a single business and its associated demo rows.
	 *
	 * @param array $def Def.
	 * @param int   $owner_id Owner id.
	 * @param array $reviewer_ids Reviewer ids.
	 * @param array $registry Registry.
	 */
	private static function create_business( array $def, int $owner_id, array $reviewer_ids, array &$registry ): array {
		$stats = array(
			'businesses' => 0,
			'services'   => 0,
			'reviews'    => 0,
			'followers'  => 0,
			'media'      => 0,
		);

		// Skip businesses whose slug already exists (e.g. the default demo.
		// café) so we never create duplicate rows or collide on post_id.
		if ( Services::businesses()->get_by_slug( $def['slug'] ) ) {
			return $stats;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'     => $def['name'],
				'post_name'      => $def['slug'],
				'post_author'    => $owner_id,
				'post_status'    => 'publish',
				'post_type'      => 'zeko_business',
				'post_excerpt'   => $def['excerpt'],
				'post_content'   => self::build_content( $def ),
				'comment_status' => 'open',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return $stats;
		}

		$now  = current_time( 'mysql' );
		$data = array(
			'post_id'        => (int) $post_id,
			'owner_id'       => $owner_id,
			'slug'           => $def['slug'],
			'name'           => $def['name'],
			'status'         => $def['status'] ?? 'active',
			'phone'          => $def['phone'],
			'website'        => $def['website'],
			'email'          => $def['email'],
			'whatsapp'       => $def['whatsapp'] ?? '',
			'address'        => $def['address'],
			'city'           => $def['city'],
			'state'          => $def['state'],
			'country'        => $def['country'],
			'zip'            => $def['zip'],
			'lat'            => $def['lat'],
			'lng'            => $def['lng'],
			'follower_count' => $def['follower_count'],
			'review_count'   => count( $def['reviews'] ),
			'avg_rating'     => self::average( $def['reviews'] ),
			'view_count'     => $def['view_count'],
			'is_featured'    => ! empty( $def['featured'] ) ? 1 : 0,
			'is_verified'    => ! empty( $def['verified'] ) ? 1 : 0,
			'is_claimed'     => 1,
			'claimed_by'     => $owner_id,
			'claimed_date'   => $now,
			'is_sponsored'   => ! empty( $def['sponsored'] ) ? 1 : 0,
			'plan'           => self::valid_plan( $def['plan'] ),
			'business_hours' => is_array( $def['hours'] ) ? wp_json_encode( $def['hours'] ) : '',
			'social_links'   => wp_json_encode( $def['socials'] ),
			'extra_data'     => wp_json_encode( $def['extra'] ),
			'timezone'       => $def['timezone'],
			'group_id'       => 0,
		);

		$biz = Services::businesses()->create( $data );

		if ( ! $biz || ! $biz->id ) {
			wp_delete_post( (int) $post_id, true );
			return $stats;
		}

		$business_id = (int) $biz->id;

		update_post_meta( $post_id, '_zbe_business_id', $business_id );
		update_post_meta( $post_id, '_zbe_is_demo', 1 );

		if ( ! empty( $def['terms'] ) ) {
			wp_set_object_terms( (int) $post_id, $def['terms'], 'business_category' );
		}

		$registry['post_ids'][]     = (int) $post_id;
		$registry['business_ids'][] = $business_id;

		// Hours table.
		if ( is_array( $def['hours'] ) ) {
			\ZBE\Core\Services::hours()->replace_for_business( $business_id, self::hours_rows( $def['hours'] ) );
		}

		// Placeholder attachments (avatar + cover).
		list( $avatar_id, $cover_id ) = self::attachments( $def['slug'], $registry );

		// Media rows.
		$stats['media'] += self::seed_media( $business_id, $avatar_id, $cover_id, $registry );

		// Reviews.
		$stats['reviews'] += self::seed_reviews( $business_id, $def['reviews'], $reviewer_ids, $registry );

		// Followers (a few of the reviewer users follow the business).
		$stats['followers'] += self::seed_followers( $business_id, $reviewer_ids, $registry );

		// Services.
		$stats['services'] += self::seed_services( $business_id, $def['services'] ?? array(), $registry );

		if ( $avatar_id || $cover_id ) {
			Services::businesses()->update(
				$business_id,
				array(
					'avatar_id' => $avatar_id,
					'cover_id'  => $cover_id ? $cover_id : $avatar_id,
				)
			);
		}

		++$stats['businesses'];

		return $stats;
	}

	/*
	─────────────────────────────────────────────────────────────
	 * Small builders
	 * ─────────────────────────────────────────────────────────────
	 */

	/**
	 * Seed services.
	 *
	 * @param int   $business_id Business id.
	 * @param array $service_defs Service defs.
	 * @param array $registry Registry.
	 */
	private static function seed_services( int $business_id, array $service_defs, array &$registry ): int {
		$count = 0;

		foreach ( $service_defs as $svc ) {
			$created = Services::services_repo()->create(
				array(
					'business_id'       => $business_id,
					'name'              => $svc['name'],
					'short_description' => $svc['short'],
					'description'       => '<p>' . esc_html( $svc['desc'] ) . '</p>',
					'price'             => (float) $svc['price'],
					'price_type'        => $svc['price_type'] ?? 'starting',
					'price_note'        => $svc['price_note'] ?? '',
					'image_id'          => 0,
					'is_active'         => 1,
					'is_featured'       => ! empty( $svc['featured'] ) ? 1 : 0,
					'category'          => $svc['category'] ?? '',
					'duration'          => $svc['duration'] ?? '',
					'tags'              => is_array( $svc['tags'] ?? array() ) ? implode( ',', $svc['tags'] ) : '',
					'sort_order'        => (int) ( $svc['sort_order'] ?? 0 ),
				)
			);

			if ( $created && $created->id ) {
				$registry['service_ids'][] = (int) $created->id;
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Seed reviews.
	 *
	 * @param int   $business_id Business id.
	 * @param array $review_defs Review defs.
	 * @param array $reviewer_ids Reviewer ids.
	 * @param array $registry Registry.
	 */
	private static function seed_reviews( int $business_id, array $review_defs, array $reviewer_ids, array &$registry ): int {
		$count = 0;
		$users = array_values( $reviewer_ids );

		foreach ( $review_defs as $i => $def ) {
			$review = Services::reviews()->create(
				array(
					'business_id' => $business_id,
					'user_id'     => $users[ $i % count( $users ) ],
					'order_id'    => 0,
					'rating'      => (int) $def['rating'],
					'title'       => $def['title'],
					'content'     => $def['content'],
					'status'      => 'approved',
				)
			);

			if ( $review && $review->id ) {
				$registry['review_ids'][] = (int) $review->id;
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Seed followers.
	 *
	 * @param int   $business_id Business id.
	 * @param array $reviewer_ids Reviewer ids.
	 * @param array $registry Registry.
	 */
	private static function seed_followers( int $business_id, array $reviewer_ids, array &$registry ): int {
		$count = 0;
		$users = array_values( $reviewer_ids );
		$limit = min( 3, count( $users ) );

		for ( $i = 0; $i < $limit; $i++ ) {
			$follower = Services::followers()->create(
				array(
					'business_id' => $business_id,
					'user_id'     => (int) $users[ $i ],
				)
			);

			if ( $follower && $follower->id ) {
				$registry['follower_ids'][] = (int) $follower->id;
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Seed media.
	 *
	 * @param int   $business_id Business id.
	 * @param int   $avatar_id Avatar id.
	 * @param int   $cover_id Cover id.
	 * @param array $registry Registry.
	 */
	private static function seed_media( int $business_id, int $avatar_id, int $cover_id, array &$registry ): int {
		$count = 0;
		$refs  = array_filter( array( $avatar_id, $cover_id ) );

		foreach ( $refs as $i => $attachment_id ) {
			$media = Services::media()->create(
				array(
					'business_id'   => $business_id,
					'attachment_id' => (int) $attachment_id,
					'caption'       => 0 === $i ? __( 'Storefront', 'zeko-business' ) : __( 'Interior', 'zeko-business' ),
					'sort_order'    => $i,
				)
			);

			if ( $media && $media->id ) {
				$registry['media_ids'][] = (int) $media->id;
				++$count;
			}
		}

		return $count;
	}

	/*
	─────────────────────────────────────────────────────────────
	 * Placeholder attachments (SVG — no GD required)
	 * ─────────────────────────────────────────────────────────────
	 */

	/**
	 * Attachments.
	 *
	 * @param string $slug Slug.
	 * @param array  $registry Registry.
	 */
	private static function attachments( string $slug, array &$registry ): array {
		$avatar = self::placeholder_attachment( $slug . '-avatar', 'Avatar', '#7c5cff', $registry );
		$cover  = self::placeholder_attachment( $slug . '-cover', 'Cover', '#2d7ff9', $registry );

		return array( $avatar, $cover );
	}

	/**
	 * Placeholder attachment.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param string $color Color.
	 * @param array  $registry Registry.
	 */
	private static function placeholder_attachment( string $key, string $label, string $color, array &$registry ): int {
		$upload = wp_upload_dir();
		if ( empty( $upload['error'] ) && ! empty( $upload['path'] ) ) {
			$dir = $upload['path'];

			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			$file = trailingslashit( $dir ) . sanitize_file_name( $key . '.svg' );

			//phpcs:ignore WordPress.WP.AlternativeFunctions
			$saved = file_put_contents(
				$file,
				'<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">'
				. '<rect width="800" height="600" fill="' . esc_attr( $color ) . '"/>'
				. '<text x="400" y="300" font-family="Arial" font-size="48" fill="#fff" text-anchor="middle">'
				. esc_html( $label )
				. '</text></svg>'
			);

			if ( false !== $saved ) {
				$attachment_id = wp_insert_attachment(
					array(
						'post_mime_type' => 'image/svg+xml',
						'post_title'     => 'Zeko Demo ' . $label . ' ' . $key,
						'post_status'    => 'inherit',
					),
					$file,
					0
				);

				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );
					$registry['attachment_ids'][] = (int) $attachment_id;

					return (int) $attachment_id;
				}
			}
		}

		return 0;
	}

	/*
	─────────────────────────────────────────────────────────────
	 * Categories, terms, users
	 * ─────────────────────────────────────────────────────────────
	 */

	/**
	 * Seed service categories.
	 *
	 * @param array $registry Registry.
	 */
	private static function seed_service_categories( array &$registry ): void {
		$categories = array(
			array(
				'name'       => 'Consulting',
				'icon'       => 'dashicons-welcome-view-site',
				'sort_order' => 1,
			),
			array(
				'name'       => 'Design',
				'icon'       => 'dashicons-admin-appearance',
				'sort_order' => 2,
			),
			array(
				'name'       => 'Development',
				'icon'       => 'dashicons-admin-code',
				'sort_order' => 3,
			),
			array(
				'name'       => 'Maintenance',
				'icon'       => 'dashicons-update',
				'sort_order' => 4,
			),
			array(
				'name'       => 'Repair',
				'icon'       => 'dashicons-scheduler',
				'sort_order' => 5,
			),
			array(
				'name'       => 'Training',
				'icon'       => 'dashicons-welcome-learn-more',
				'sort_order' => 6,
			),
			array(
				'name'       => 'Installation',
				'icon'       => 'dashicons-admin-tools',
				'sort_order' => 7,
			),
			array(
				'name'       => 'Other',
				'icon'       => 'dashicons-ellipsis',
				'sort_order' => 99,
			),
		);

		foreach ( $categories as $cat ) {
			$name = $cat['name'];
			$slug = sanitize_title( $name );

			$found = null;
			foreach ( Services::service_categories()->get_all( false ) as $existing ) {
				if ( $existing->slug === $slug ) {
					$found = $existing->id;
					break;
				}
			}

			if ( $found ) {
				continue; // Already exists; do not track for cleanup.
			}

			$created = Services::service_categories()->create(
				array(
					'name'        => $name,
					'slug'        => $slug,
					'description' => $name . ' services',
					'icon'        => $cat['icon'],
					'sort_order'  => $cat['sort_order'],
				)
			);

			if ( $created && $created->id ) {
				$registry['category_ids'][] = (int) $created->id;
			}
		}
	}

	/**
	 * Ensure terms.
	 */
	private static function ensure_terms(): array {
		$names    = self::term_names();
		$term_ids = array();

		foreach ( $names as $name ) {
			$term = term_exists( $name, 'business_category' );

			if ( $term && (int) $term['term_id'] ) {
				continue; // Pre-existing; not tracked.
			}

			$inserted = wp_insert_term( $name, 'business_category' );

			if ( ! is_wp_error( $inserted ) ) {
				$term_ids[] = (int) $inserted['term_id'];
			}
		}

		return $term_ids;
	}

	/**
	 * Create reviewers.
	 *
	 * @param array $registry Registry.
	 */
	private static function create_reviewers( array &$registry ): array {
		$ids = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$slug     = 'zeko-demo-user-' . $i;
			$existing = get_user_by( 'login', $slug );

			if ( $existing ) {
				$ids[] = (int) $existing->ID;
				continue;
			}

			$user_id = wp_insert_user(
				array(
					'user_login'   => $slug,
					'user_pass'    => wp_generate_password( 24, true ),
					'user_email'   => $slug . '@example.com',
					'first_name'   => 'Demo',
					'last_name'    => 'Reviewer ' . $i,
					'role'         => 'subscriber',
					'display_name' => 'Demo Reviewer ' . $i,
				)
			);

			if ( ! is_wp_error( $user_id ) ) {
				$ids[]                  = (int) $user_id;
				$registry['user_ids'][] = (int) $user_id;
			} else {
				$ids[] = (int) $owner_id();
			}
		}

		return $ids;
	}

	/**
	 * Owner id.
	 */
	private static function owner_id(): int {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			return (int) $user_id;
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		return $admins ? (int) reset( $admins ) : 0;
	}

	/*
	─────────────────────────────────────────────────────────────
	 * Registry
	 * ─────────────────────────────────────────────────────────────
	 */

	/**
	 * Registry.
	 */
	private static function registry(): array {
		$registry = get_option( self::REGISTRY, array() );

		return is_array( $registry ) ? $registry : array();
	}

	/**
	 * Save registry.
	 *
	 * @param array $registry Registry.
	 */
	private static function save_registry( array $registry ): void {
		foreach ( $registry as $key => $values ) {
			$registry[ $key ] = array_values( array_unique( array_map( 'intval', (array) $values ) ) );
		}

		update_option( self::REGISTRY, $registry, false );
	}

	/*
	─────────────────────────────────────────────────────────────
	 * Helpers
	 * ─────────────────────────────────────────────────────────────
	 */

	/**
	 * Average.
	 *
	 * @param array $reviews Reviews.
	 */
	private static function average( array $reviews ): float {
		if ( empty( $reviews ) ) {
			return 0.0;
		}

		$sum = 0;
		foreach ( $reviews as $r ) {
			$sum += (float) $r['rating'];
		}

		return round( $sum / count( $reviews ), 2 );
	}

	/**
	 * Valid plan.
	 *
	 * @param string $plan Plan.
	 */
	private static function valid_plan( string $plan ): string {
		$plan = strtolower( trim( $plan ) );

		return in_array( $plan, array( 'free', 'basic', 'pro', 'enterprise' ), true ) ? $plan : 'enterprise';
	}

	/**
	 * Hours rows.
	 *
	 * @param array $hours Hours.
	 */
	private static function hours_rows( array $hours ): array {
		$rows = array();
		$days = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );

		foreach ( $days as $i => $day ) {
			$cfg = $hours[ $day ] ?? array();

			$rows[] = array(
				'business_id' => 0,
				'day_of_week' => $i,
				'open'        => empty( $cfg['closed'] ) ? ( $cfg['open'] ?? '' ) : '',
				'close'       => empty( $cfg['closed'] ) ? ( $cfg['close'] ?? '' ) : '',
				'closed'      => ! empty( $cfg['closed'] ),
			);
		}

		return $rows;
	}

	/**
	 * Build content.
	 *
	 * @param array $def Def.
	 */
	private static function build_content( array $def ): string {
		return '<h2>' . esc_html( $def['name'] ) . '</h2>'
			. '<p>' . esc_html( $def['content'] ) . '</p>';
	}

	/**
	 * Term names.
	 */
	private static function term_names(): array {
		return array(
			'Restaurants',
			'Coffee & Tea',
			'Fitness & Gym',
			'Beauty & Spa',
			'Automotive',
			'Home Services',
			'Retail',
			'Health & Wellness',
			'Education',
			'Technology',
		);
	}

	/**
	 * Minimal dataset. Each entry maps 1:1 to the auth/db/portal columns.
	 */
	private static function dataset(): array {
		return array(
			array(
				'name'           => 'Zeko Central Café & Bistro',
				'slug'           => 'zeko-central-cafe-bistro',
				'excerpt'        => 'Hand-roasted coffee, seasonal Californian plates and a sunny garden patio in the heart of San Francisco.',
				'content'        => 'Zeko Central Café & Bistro has been serving hand-roasted coffee and seasonal California cuisine since 2016. Our beans are sourced directly from small farms in Ethiopia and Colombia and roasted weekly in-house.',
				'city'           => 'San Francisco',
				'state'          => 'California',
				'country'        => 'United States',
				'zip'            => '94103',
				'lat'            => 37.7765123,
				'lng'            => -122.4172891,
				'address'        => '218 Market Street, Suite 4',
				'phone'          => '+1 (415) 555-0148',
				'email'          => 'hello@zekocentral.example.com',
				'website'        => 'https://zekocentral.example.com',
				'status'         => 'active',
				'plan'           => 'enterprise',
				'featured'       => true,
				'verified'       => true,
				'sponsored'      => true,
				'timezone'       => 'America/Los_Angeles',
				'follower_count' => 128,
				'view_count'     => 12470,
				'terms'          => array( 'Restaurants', 'Coffee & Tea' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '07:00',
						'close'  => '21:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '07:00',
						'close'  => '21:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '07:00',
						'close'  => '21:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '07:00',
						'close'  => '21:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '07:00',
						'close'  => '22:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => '08:00',
						'close'  => '22:00',
						'closed' => false,
					),
					'sunday'    => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
				),
				'socials'        => array(
					'facebook'  => 'https://www.facebook.com/zekocentral',
					'instagram' => 'https://www.instagram.com/zekocentral',
					'twitter'   => 'https://x.com/zekocentral',
					'linkedin'  => 'https://www.linkedin.com/company/zekocentral',
				),
				'extra'          => array(
					'price_range'          => '$$',
					'year_established'     => 2016,
					'seating_capacity'     => 64,
					'wifi'                 => true,
					'outdoor_seating'      => true,
					'accepts_reservations' => true,
					'delivery'             => true,
					'takeaway'             => true,
					'parking'              => 'street',
					'payment_methods'      => array( 'visa', 'mastercard', 'amex', 'cash', 'apple_pay' ),
					'languages'            => array( 'en', 'es' ),
				),
				'services'       => array(
					array(
						'name'       => 'Blending and Ironing',
						'short'      => 'Professional clothes care.',
						'desc'       => 'Full wash, press and fold service with same-week turnaround.',
						'price'      => 29,
						'price_type' => 'starting',
						'duration'   => '2 hours',
						'category'   => 'maintenance',
						'tags'       => array( 'laundry', 'pressing' ),
						'sort_order' => 1,
					),
					array(
						'name'       => 'Coffee Tasting Workshop',
						'short'      => 'An hour exploring single-origin roasts.',
						'desc'       => 'Guided tasting of four seasonal single-origin coffees with a barista.',
						'price'      => 45,
						'price_type' => 'fixed',
						'duration'   => '1 hour',
						'category'   => 'training',
						'tags'       => array( 'coffee', 'workshop' ),
						'sort_order' => 2,
					),
				),
				'reviews'        => array(
					array(
						'rating'  => 5,
						'title'   => 'Best flat white in the city',
						'content' => 'Bright, busy and welcoming. The single-origin menu is superb.',
					),
					array(
						'rating'  => 4,
						'title'   => 'Great patio',
						'content' => 'Lovely outdoor seating and fast service at lunch.',
					),
					array(
						'rating'  => 5,
						'title'   => 'Weekly regular',
						'content' => 'Consistent quality and friendly baristas. Highly recommended.',
					),
				),
			),
			array(
				'name'           => 'Northline Auto Repair',
				'slug'           => 'northline-auto-repair',
				'excerpt'        => 'Honest, certified auto diagnostics and repair for domestic and import vehicles in Seattle.',
				'content'        => 'Northline Auto Repair is an ASE-certified, family-run garage serving Seattle for over twenty years. We handle everything from routine oil changes to complex engine diagnostics with transparent pricing.',
				'city'           => 'Seattle',
				'state'          => 'Washington',
				'country'        => 'United States',
				'zip'            => '98101',
				'lat'            => 47.6062,
				'lng'            => -122.3321,
				'address'        => '412 Aurora Avenue N',
				'phone'          => '+1 (206) 555-0137',
				'email'          => 'service@northlineauto.example.com',
				'website'        => 'https://northlineauto.example.com',
				'status'         => 'active',
				'plan'           => 'pro',
				'featured'       => true,
				'verified'       => true,
				'sponsored'      => false,
				'timezone'       => 'America/Los_Angeles',
				'follower_count' => 64,
				'view_count'     => 8320,
				'terms'          => array( 'Automotive' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '08:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '08:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '08:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '08:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '08:00',
						'close'  => '17:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => '09:00',
						'close'  => '14:00',
						'closed' => false,
					),
					'sunday'    => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
				),
				'socials'        => array(
					'facebook'  => '#',
					'instagram' => '#',
				),
				'extra'          => array(
					'price_range'      => '$$$',
					'year_established' => 2003,
					'parking'          => 'lot',
					'payment_methods'  => array( 'visa', 'mastercard', 'cash' ),
				),
				'services'       => array(
					array(
						'name'       => 'Full Diagnostic',
						'short'      => 'Computerised engine diagnostics.',
						'desc'       => 'Complete OBD scan and technician report.',
						'price'      => 89,
						'price_type' => 'fixed',
						'duration'   => '1 hour',
						'category'   => 'repair',
						'tags'       => array( 'diagnostics' ),
						'sort_order' => 1,
					),
					array(
						'name'       => 'Brake Service',
						'short'      => 'Pad, rotor and fluid replacement.',
						'desc'       => 'Complete brake inspection and service.',
						'price'      => 149,
						'price_type' => 'starting',
						'duration'   => 'Half day',
						'category'   => 'repair',
						'tags'       => array( 'brakes' ),
						'sort_order' => 2,
					),
				),
				'reviews'        => array(
					array(
						'rating'  => 5,
						'title'   => 'Honest and fast',
						'content' => 'They fixed an issue two other shops missed.',
					),
					array(
						'rating'  => 4,
						'title'   => 'Fair pricing',
						'content' => 'Quoted up front, no surprises.',
					),
				),
			),
			array(
				'name'           => 'Bright Home Services',
				'slug'           => 'bright-home-services',
				'excerpt'        => 'Cleaning, handyman and property maintenance across Austin with fully vetted pros.',
				'content'        => 'Bright Home Services connects Austin homeowners with background-checked cleaners and handymen. Book recurring cleans, deep cleans or one-off repairs.',
				'city'           => 'Austin',
				'state'          => 'Texas',
				'country'        => 'United States',
				'zip'            => '78701',
				'lat'            => 30.2672,
				'lng'            => -97.7431,
				'address'        => '77 Rainey Street, Suite 210',
				'phone'          => '+1 (512) 555-0162',
				'email'          => 'hello@brighthome.example.com',
				'website'        => 'https://brighthome.example.com',
				'status'         => 'active',
				'plan'           => 'basic',
				'featured'       => false,
				'verified'       => true,
				'sponsored'      => false,
				'timezone'       => 'America/Chicago',
				'follower_count' => 41,
				'view_count'     => 4105,
				'terms'          => array( 'Home Services' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '08:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '08:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '08:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '08:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '08:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => '09:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'sunday'    => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
				),
				'socials'        => array(
					'instagram' => '#',
					'facebook'  => '#',
				),
				'extra'          => array(
					'price_range'      => '$$',
					'year_established' => 2019,
					'wifi'             => false,
					'payment_methods'  => array( 'visa', 'cash', 'zelle' ),
				),
				'services'       => array(
					array(
						'name'       => 'Deep Cleaning',
						'short'      => 'Two-person deep clean.',
						'desc'       => 'Extensive top-to-bottom home clean.',
						'price'      => 129,
						'price_type' => 'starting',
						'duration'   => '3-4 hours',
						'category'   => 'maintenance',
						'tags'       => array( 'cleaning' ),
						'sort_order' => 1,
					),
					array(
						'name'       => 'Handyman Hourly',
						'short'      => 'General repairs and assembly.',
						'desc'       => 'Hourly handyman for any small job.',
						'price'      => 55,
						'price_type' => 'starting',
						'duration'   => 'per hour',
						'category'   => 'repair',
						'tags'       => array( 'handyman' ),
						'sort_order' => 2,
					),
				),
				'reviews'        => array(
					array(
						'rating'  => 5,
						'title'   => 'Spotless apartment',
						'content' => 'The team was thorough and on time.',
					),
				),
			),
			array(
				'name'           => 'Zen Den Yoga Studio',
				'slug'           => 'zen-den-yoga-studio',
				'excerpt'        => 'A calm, inclusive studio offering daily yoga, pilates and meditation classes in Denver.',
				'content'        => 'Zen Den Yoga Studio offers small, friendly classes for every level. From gentle morning flow to high-energy hot yoga, our certified instructors keep the space welcoming.',
				'city'           => 'Denver',
				'state'          => 'Colorado',
				'country'        => 'United States',
				'zip'            => '80202',
				'lat'            => 39.7392,
				'lng'            => -104.9903,
				'address'        => '1200 16th Street, Unit 5',
				'phone'          => '+1 (303) 555-0119',
				'email'          => 'namaste@zenden.example.com',
				'website'        => 'https://zenden.example.com',
				'status'         => 'active',
				'plan'           => 'pro',
				'featured'       => false,
				'verified'       => false,
				'sponsored'      => false,
				'timezone'       => 'America/Denver',
				'follower_count' => 95,
				'view_count'     => 5120,
				'terms'          => array( 'Health & Wellness', 'Education' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '06:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '06:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '06:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '06:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '06:00',
						'close'  => '21:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => '08:00',
						'close'  => '16:00',
						'closed' => false,
					),
					'sunday'    => array(
						'open'   => '08:00',
						'close'  => '14:00',
						'closed' => false,
					),
				),
				'socials'        => array(
					'instagram' => '#',
					'facebook'  => '#',
					'youtube'   => '#',
				),
				'extra'          => array(
					'price_range'      => '$$',
					'year_established' => 2020,
					'wifi'             => true,
					'parking'          => 'street',
					'payment_methods'  => array( 'visa', 'mastercard' ),
				),
				'services'       => array(
					array(
						'name'       => 'Drop-in Class',
						'short'      => 'Single class pass.',
						'desc'       => 'Any single class, all levels welcome.',
						'price'      => 18,
						'price_type' => 'fixed',
						'duration'   => '1 hour',
						'category'   => 'training',
						'tags'       => array( 'yoga' ),
						'sort_order' => 1,
					),
					array(
						'name'       => 'Monthly Unlimited',
						'short'      => 'Unlimited classes for a month.',
						'desc'       => 'Unlimited access to all in-person classes.',
						'price'      => 99,
						'price_type' => 'fixed',
						'duration'   => 'monthly',
						'category'   => 'training',
						'tags'       => array( 'membership' ),
						'sort_order' => 2,
					),
				),
				'reviews'        => array(
					array(
						'rating'  => 5,
						'title'   => 'Wonderful community',
						'content' => 'Supportive teachers and a beautiful space.',
					),
					array(
						'rating'  => 4,
						'title'   => 'Great morning classes',
						'content' => 'Reliable 6am schedule, never crowded.',
					),
				),
			),
			array(
				'name'           => 'CodeCraft Web Studio',
				'slug'           => 'codecraft-web-studio',
				'excerpt'        => 'A full-service web design and development agency for startups and small businesses in Chicago.',
				'content'        => 'CodeCraft Web Studio builds fast, accessible websites and web apps. From brand and design to development and ongoing maintenance, one team covers it all.',
				'city'           => 'Chicago',
				'state'          => 'Illinois',
				'country'        => 'United States',
				'zip'            => '60601',
				'lat'            => 41.8781,
				'lng'            => -87.6298,
				'address'        => '333 Wacker Drive, Floor 22',
				'phone'          => '+1 (312) 555-0144',
				'email'          => 'hi@codecraft.example.com',
				'website'        => 'https://codecraft.example.com',
				'status'         => 'active',
				'plan'           => 'enterprise',
				'featured'       => true,
				'verified'       => true,
				'sponsored'      => false,
				'timezone'       => 'America/Chicago',
				'follower_count' => 312,
				'view_count'     => 21900,
				'terms'          => array( 'Technology' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '09:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '09:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '09:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '09:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '09:00',
						'close'  => '17:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
					'sunday'    => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
				),
				'socials'        => array(
					'linkedin' => '#',
					'twitter'  => '#',
					'github'   => '#',
				),
				'extra'          => array(
					'price_range'      => '$$$$',
					'year_established' => 2014,
					'wifi'             => true,
					'payment_methods'  => array( 'visa', 'mastercard', 'ach' ),
				),
				'services'       => array(
					array(
						'name'       => 'Brand Identity',
						'short'      => 'Logo, palette and style guide.',
						'desc'       => 'Complete brand identity package.',
						'price'      => 1500,
						'price_type' => 'starting',
						'duration'   => '2-3 weeks',
						'category'   => 'design',
						'tags'       => array( 'branding' ),
						'sort_order' => 1,
					),
					array(
						'name'       => 'Website Build',
						'short'      => 'Custom marketing site.',
						'desc'       => 'Responsive, SEO-ready website.',
						'price'      => 4500,
						'price_type' => 'starting',
						'duration'   => '4 weeks',
						'category'   => 'development',
						'tags'       => array( 'web' ),
						'sort_order' => 2,
					),
					array(
						'name'       => 'Care Plan',
						'short'      => 'Monthly maintenance & support.',
						'desc'       => 'Updates, backups, security and support.',
						'price'      => 199,
						'price_type' => 'fixed',
						'duration'   => 'monthly',
						'category'   => 'maintenance',
						'tags'       => array( 'support' ),
						'sort_order' => 3,
					),
				),
				'reviews'        => array(
					array(
						'rating'  => 5,
						'title'   => 'Stunning redesign',
						'content' => 'They transformed our site. Traffic is up 40%.',
					),
					array(
						'rating'  => 5,
						'title'   => 'Reliable partner',
						'content' => 'Fast, communicative and detail-oriented.',
					),
					array(
						'rating'  => 4,
						'title'   => 'Great value',
						'content' => 'Slightly over the estimate but worth it.',
					),
				),
			),
			array(
				'name'           => 'Glamour Nail & Spa',
				'slug'           => 'glamour-nail-spa',
				'excerpt'        => 'Premium manicure, pedicure and massage studio in Miami with an emphasis on hygiene and relaxation.',
				'content'        => 'Glamour Nail & Spa offers a serene escape with premium spa services, meticulous hygiene and friendly technicians. Walk-ins welcome most days.',
				'city'           => 'Miami',
				'state'          => 'Florida',
				'country'        => 'United States',
				'zip'            => '33101',
				'lat'            => 25.7617,
				'lng'            => -80.1918,
				'address'        => '900 Biscayne Blvd, Suite 3',
				'phone'          => '+1 (305) 555-0173',
				'email'          => 'bookings@glamournail.example.com',
				'website'        => 'https://glamournail.example.com',
				'status'         => 'active',
				'plan'           => 'basic',
				'featured'       => false,
				'verified'       => true,
				'sponsored'      => false,
				'timezone'       => 'America/New_York',
				'follower_count' => 78,
				'view_count'     => 6340,
				'terms'          => array( 'Beauty & Spa' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '10:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '10:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '10:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '10:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '10:00',
						'close'  => '21:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => '09:00',
						'close'  => '21:00',
						'closed' => false,
					),
					'sunday'    => array(
						'open'   => '10:00',
						'close'  => '18:00',
						'closed' => false,
					),
				),
				'socials'        => array(
					'instagram' => '#',
					'facebook'  => '#',
				),
				'extra'          => array(
					'price_range'      => '$$',
					'year_established' => 2018,
					'wifi'             => true,
					'payment_methods'  => array( 'visa', 'mastercard', 'cash', 'apple_pay' ),
				),
				'services'       => array(
					array(
						'name'       => 'Signature Manicure',
						'short'      => 'Spa manicure with massage.',
						'desc'       => 'Relaxing manicure with cuticle care and massage.',
						'price'      => 45,
						'price_type' => 'fixed',
						'duration'   => '45 min',
						'category'   => 'other',
						'tags'       => array( 'manicure' ),
						'sort_order' => 1,
					),
					array(
						'name'       => 'Gel Pedicure',
						'short'      => 'Long-lasting gel pedicure.',
						'desc'       => 'Soak, scrub, gel polish and foot massage.',
						'price'      => 70,
						'price_type' => 'fixed',
						'duration'   => '1 hour',
						'category'   => 'other',
						'tags'       => array( 'pedicure' ),
						'sort_order' => 2,
					),
				),
				'reviews'        => array(
					array(
						'rating'  => 4,
						'title'   => 'Clean and calm',
						'content' => 'Very well kept studio, lovely nails.',
					),
				),
			),
			array(
				'name'           => 'Fitness Forge Personal Training',
				'slug'           => 'fitness-forge-personal-training',
				'excerpt'        => '1-on-1 personal training and small-group coaching in Phoenix to hit your strength goals.',
				'content'        => 'Fitness Forge delivers results-driven personal training in a private studio. We build programs around your body, goals and schedule — no crowded gyms.',
				'city'           => 'Phoenix',
				'state'          => 'Arizona',
				'country'        => 'United States',
				'zip'            => '85001',
				'lat'            => 33.4484,
				'lng'            => -112.0740,
				'address'        => 'Dunlap & 7th Street, Bldg B',
				'phone'          => '+1 (602) 555-0192',
				'email'          => 'coach@fitnessforge.example.com',
				'website'        => 'https://fitnessforge.example.com',
				'status'         => 'active',
				'plan'           => 'pro',
				'featured'       => true,
				'verified'       => false,
				'sponsored'      => false,
				'timezone'       => 'America/Phoenix',
				'follower_count' => 150,
				'view_count'     => 7620,
				'terms'          => array( 'Fitness & Gym' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '05:30',
						'close'  => '21:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '05:30',
						'close'  => '21:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '05:30',
						'close'  => '21:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '05:30',
						'close'  => '21:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '05:30',
						'close'  => '20:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => '07:00',
						'close'  => '13:00',
						'closed' => false,
					),
					'sunday'    => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
				),
				'socials'        => array(
					'instagram' => '#',
					'tiktok'    => '#',
				),
				'extra'          => array(
					'price_range'      => '$$$',
					'year_established' => 2017,
					'parking'          => 'lot',
					'payment_methods'  => array( 'visa', 'cash' ),
				),
				'services'       => array(
					array(
						'name'       => '1-on-1 Session',
						'short'      => 'Private coaching session.',
						'desc'       => 'Fully personalised training session.',
						'price'      => 75,
						'price_type' => 'fixed',
						'duration'   => '1 hour',
						'category'   => 'training',
						'tags'       => array( 'personal training' ),
						'sort_order' => 1,
					),
					array(
						'name'       => '12-Week Transformation',
						'short'      => 'Program + check-ins.',
						'desc'       => 'Structured program with weekly check-ins and nutrition guidance.',
						'price'      => 699,
						'price_type' => 'fixed',
						'duration'   => '12 weeks',
						'category'   => 'training',
						'tags'       => array( 'program' ),
						'sort_order' => 2,
					),
				),
				'reviews'        => array(
					array(
						'rating'  => 5,
						'title'   => 'Transformed my strength',
						'content' => 'Lost 15kg and feel great. Highly recommend Sam.',
					),
					array(
						'rating'  => 4,
						'title'   => 'Motivated me',
						'content' => 'Accountability keeps me coming back.',
					),
				),
			),
			array(
				'name'           => 'Riverside Healthcare Clinic',
				'slug'           => 'riverside-healthcare-clinic',
				'excerpt'        => 'Community primary care, diagnostics and wellness services in Portland.',
				'content'        => 'Riverside Healthcare Clinic provides affordable, patient-first primary care. Same-day appointments, on-site labs and multilingual staff.',
				'city'           => 'Portland',
				'state'          => 'Oregon',
				'country'        => 'United States',
				'zip'            => '97205',
				'lat'            => 45.5231,
				'lng'            => -122.6765,
				'address'        => '221 SW Morrison St, Suite 900',
				'phone'          => '+1 (503) 555-0104',
				'email'          => 'care@riversidehc.example.com',
				'website'        => 'https://riversidehc.example.com',
				'status'         => 'active',
				'plan'           => 'enterprise',
				'featured'       => false,
				'verified'       => true,
				'sponsored'      => true,
				'timezone'       => 'America/Los_Angeles',
				'follower_count' => 205,
				'view_count'     => 11800,
				'terms'          => array( 'Health & Wellness' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '08:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '08:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '08:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '08:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '08:00',
						'close'  => '16:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
					'sunday'    => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
				),
				'socials'        => array(
					'facebook' => '#',
					'linkedin' => '#',
				),
				'extra'          => array(
					'price_range'      => '$$',
					'year_established' => 2011,
					'wifi'             => true,
					'parking'          => 'garage',
					'payment_methods'  => array( 'visa', 'mastercard', 'hsa' ),
				),
				'services'       => array(
					array(
						'name'       => 'Primary Care Visit',
						'short'      => 'Same-day appointment.',
						'desc'       => 'Comprehensive consultation with a physician.',
						'price'      => 120,
						'price_type' => 'fixed',
						'duration'   => '30 min',
						'category'   => 'consulting',
						'tags'       => array( 'primary care' ),
						'sort_order' => 1,
					),
					array(
						'name'       => 'Lab Panel',
						'short'      => 'Routine blood work.',
						'desc'       => 'On-site comprehensive lab panel.',
						'price'      => 89,
						'price_type' => 'fixed',
						'duration'   => '15 min',
						'category'   => 'other',
						'tags'       => array( 'labs' ),
						'sort_order' => 2,
					),
				),
				'reviews'        => array(
					array(
						'rating'  => 5,
						'title'   => 'Finally a clinic I trust',
						'content' => 'Caring staff and same-day care.',
					),
				),
			),
			array(
				'name'           => 'Little Sprouts Academy',
				'slug'           => 'little-sprouts-academy',
				'excerpt'        => 'Early childhood education and after-school care in Charlotte.',
				'content'        => 'Little Sprouts Academy offers a play-based curriculum for ages 2-6 plus after-school programs. Our focus is social-emotional learning in a safe, bright space.',
				'city'           => 'Charlotte',
				'state'          => 'North Carolina',
				'country'        => 'United States',
				'zip'            => '28202',
				'lat'            => 35.2271,
				'lng'            => -80.8431,
				'address'        => '500 S Tryon St, Suite 120',
				'phone'          => '+1 (704) 555-0123',
				'email'          => 'admin@littlesprouts.example.com',
				'website'        => 'https://littlesprouts.example.com',
				'status'         => 'active',
				'plan'           => 'basic',
				'featured'       => false,
				'verified'       => false,
				'sponsored'      => false,
				'timezone'       => 'America/New_York',
				'follower_count' => 33,
				'view_count'     => 2890,
				'terms'          => array( 'Education' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '07:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '07:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '07:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '07:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '07:00',
						'close'  => '18:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
					'sunday'    => array(
						'open'   => null,
						'close'  => null,
						'closed' => true,
					),
				),
				'socials'        => array( 'facebook' => '#' ),
				'extra'          => array(
					'price_range'      => '$$',
					'year_established' => 2021,
					'wifi'             => true,
					'parking'          => 'street',
					'payment_methods'  => array( 'visa', 'cash' ),
				),
				'services'       => array(),
				'reviews'        => array(
					array(
						'rating'  => 5,
						'title'   => 'Our daughter loves it',
						'content' => 'Caring teachers, wonderful program.',
					),
				),
			),
			array(
				'name'           => 'Urban Threads Clothing Co.',
				'slug'           => 'urban-threads-clothing-co',
				'excerpt'        => 'Curated sustainable fashion and streetwear boutique in Nashville.',
				'content'        => 'Urban Threads is a boutique retailer stocking sustainable, small-batch fashion and streetwear. From everyday basics to limited drops.',
				'city'           => 'Nashville',
				'state'          => 'Tennessee',
				'country'        => 'United States',
				'zip'            => '37201',
				'lat'            => 36.1627,
				'lng'            => -86.7816,
				'address'        => '411 Broadway, Suite 1',
				'phone'          => '+1 (615) 555-0181',
				'email'          => 'shop@urbanthreads.example.com',
				'website'        => 'https://urbanthreads.example.com',
				'status'         => 'active',
				'plan'           => 'free',
				'featured'       => false,
				'verified'       => false,
				'sponsored'      => false,
				'timezone'       => 'America/Chicago',
				'follower_count' => 52,
				'view_count'     => 3120,
				'terms'          => array( 'Retail' ),
				'hours'          => array(
					'monday'    => array(
						'open'   => '10:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'tuesday'   => array(
						'open'   => '10:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'wednesday' => array(
						'open'   => '10:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'thursday'  => array(
						'open'   => '10:00',
						'close'  => '20:00',
						'closed' => false,
					),
					'friday'    => array(
						'open'   => '10:00',
						'close'  => '22:00',
						'closed' => false,
					),
					'saturday'  => array(
						'open'   => '10:00',
						'close'  => '22:00',
						'closed' => false,
					),
					'sunday'    => array(
						'open'   => '11:00',
						'close'  => '18:00',
						'closed' => false,
					),
				),
				'socials'        => array(
					'instagram' => '#',
					'tiktok'    => '#',
				),
				'extra'          => array(
					'price_range'      => '$$',
					'year_established' => 2022,
					'wifi'             => true,
					'payment_methods'  => array( 'visa', 'mastercard', 'apple_pay' ),
				),
				'services'       => array(),
				'reviews'        => array(),
			),
		);
	}
}
