<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Business. */
class Business {

	/**
	 * Id.
	 *
	 * @var int Id.
	 */
	public int $id;
	/**
	 * Post id.
	 *
	 * @var int Post id.
	 */
	public int $post_id;
	/**
	 * Owner id.
	 *
	 * @var int Owner id.
	 */
	public int $owner_id;
	/**
	 * Slug.
	 *
	 * @var string Slug.
	 */
	public string $slug;
	/**
	 * Name.
	 *
	 * @var string Name.
	 */
	public string $name;
	/**
	 * Status.
	 *
	 * @var string Status.
	 */
	public string $status;
	/**
	 * Phone.
	 *
	 * @var string Phone.
	 */
	public string $phone;
	/**
	 * Website.
	 *
	 * @var string Website.
	 */
	public string $website;
	/**
	 * Email.
	 *
	 * @var string Email.
	 */
	public string $email;
	/**
	 * Whatsapp.
	 *
	 * @var string Whatsapp.
	 */
	public string $whatsapp;
	/**
	 * Address.
	 *
	 * @var string Address.
	 */
	public string $address;
	/**
	 * City.
	 *
	 * @var string City.
	 */
	public string $city;
	/**
	 * State.
	 *
	 * @var string State.
	 */
	public string $state;
	/**
	 * Country.
	 *
	 * @var string Country.
	 */
	public string $country;
	/**
	 * Zip.
	 *
	 * @var string Zip.
	 */
	public string $zip;
	/**
	 * Lat.
	 *
	 * @var float Lat.
	 */
	public float $lat;
	/**
	 * Lng.
	 *
	 * @var float Lng.
	 */
	public float $lng;
	/**
	 * Follower count.
	 *
	 * @var int Follower count.
	 */
	public int $follower_count;
	/**
	 * Review count.
	 *
	 * @var int Review count.
	 */
	public int $review_count;
	/**
	 * Avg rating.
	 *
	 * @var float Avg rating.
	 */
	public float $avg_rating;
	/**
	 * View count.
	 *
	 * @var int View count.
	 */
	public int $view_count;
	/**
	 * Is featured.
	 *
	 * @var bool Is featured.
	 */
	public bool $is_featured;
	/**
	 * Is verified.
	 *
	 * @var bool Is verified.
	 */
	public bool $is_verified;
	/**
	 * Is claimed.
	 *
	 * @var bool Is claimed.
	 */
	public bool $is_claimed;
	/**
	 * Is sponsored.
	 *
	 * @var bool Is sponsored.
	 */
	public bool $is_sponsored;
	/**
	 * Claimed by.
	 *
	 * @var int Claimed by.
	 */
	public int $claimed_by;
	/**
	 * Claimed date.
	 *
	 * @var string Claimed date.
	 */
	public string $claimed_date;
	/**
	 * Featured expires.
	 *
	 * @var string Featured expires.
	 */
	public string $featured_expires;
	/**
	 * Sponsored expires.
	 *
	 * @var string Sponsored expires.
	 */
	public string $sponsored_expires;
	/**
	 * Plan.
	 *
	 * @var string Plan.
	 */
	public string $plan;
	/**
	 * Avatar id.
	 *
	 * @var int Avatar id.
	 */
	public int $avatar_id;
	/**
	 * Cover id.
	 *
	 * @var int Cover id.
	 */
	public int $cover_id;
	/**
	 * Business hours.
	 *
	 * @var array Business hours.
	 */
	public array $business_hours;
	/**
	 * Social links.
	 *
	 * @var array Social links.
	 */
	public array $social_links;
	/**
	 * Action buttons.
	 *
	 * @var array Action buttons.
	 */
	public array $action_buttons;
	/**
	 * Extra data.
	 *
	 * @var array Extra data.
	 */
	public array $extra_data;
	/**
	 * Timezone.
	 *
	 * @var string Timezone.
	 */
	public string $timezone;
	/**
	 * Hours mode.
	 *
	 * @var string Hours mode.
	 */
	public string $hours_mode;
	/**
	 * Group id.
	 *
	 * @var int Group id.
	 */
	public int $group_id;
	/**
	 * Date created.
	 *
	 * @var string Date created.
	 */
	public string $date_created;
	/**
	 * Date modified.
	 *
	 * @var string Date modified.
	 */
	public string $date_modified;

	/**
	 * Construct.
	 *
	 * @param array $data Data.
	 */
	public function __construct( array $data = array() ) {
		$this->id                = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->post_id           = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;
		$this->owner_id          = isset( $data['owner_id'] ) ? (int) $data['owner_id'] : 0;
		$this->slug              = isset( $data['slug'] ) ? (string) $data['slug'] : '';
		$this->name              = isset( $data['name'] ) ? (string) $data['name'] : '';
		$this->status            = isset( $data['status'] ) ? (string) $data['status'] : '';
		$this->phone             = isset( $data['phone'] ) ? (string) $data['phone'] : '';
		$this->website           = isset( $data['website'] ) ? (string) $data['website'] : '';
		$this->email             = isset( $data['email'] ) ? (string) $data['email'] : '';
		$this->whatsapp          = isset( $data['whatsapp'] ) ? (string) $data['whatsapp'] : '';
		$this->address           = isset( $data['address'] ) ? (string) $data['address'] : '';
		$this->city              = isset( $data['city'] ) ? (string) $data['city'] : '';
		$this->state             = isset( $data['state'] ) ? (string) $data['state'] : '';
		$this->country           = isset( $data['country'] ) ? (string) $data['country'] : '';
		$this->zip               = isset( $data['zip'] ) ? (string) $data['zip'] : '';
		$this->lat               = isset( $data['lat'] ) ? (float) $data['lat'] : 0.0;
		$this->lng               = isset( $data['lng'] ) ? (float) $data['lng'] : 0.0;
		$this->follower_count    = isset( $data['follower_count'] ) ? (int) $data['follower_count'] : 0;
		$this->review_count      = isset( $data['review_count'] ) ? (int) $data['review_count'] : 0;
		$this->avg_rating        = isset( $data['avg_rating'] ) ? (float) $data['avg_rating'] : 0.0;
		$this->view_count        = isset( $data['view_count'] ) ? (int) $data['view_count'] : 0;
		$this->is_featured       = ! empty( $data['is_featured'] );
		$this->is_verified       = ! empty( $data['is_verified'] );
		$this->is_claimed        = ! empty( $data['is_claimed'] );
		$this->is_sponsored      = ! empty( $data['is_sponsored'] );
		$this->claimed_by        = isset( $data['claimed_by'] ) ? (int) $data['claimed_by'] : 0;
		$this->claimed_date      = isset( $data['claimed_date'] ) ? (string) $data['claimed_date'] : '';
		$this->featured_expires  = isset( $data['featured_expires'] ) ? (string) $data['featured_expires'] : '';
		$this->sponsored_expires = isset( $data['sponsored_expires'] ) ? (string) $data['sponsored_expires'] : '';
		$this->plan              = isset( $data['plan'] ) ? (string) $data['plan'] : '';
		$this->avatar_id         = isset( $data['avatar_id'] ) ? (int) $data['avatar_id'] : 0;
		$this->cover_id          = isset( $data['cover_id'] ) ? (int) $data['cover_id'] : 0;
		$this->business_hours    = self::to_array_value( $data['business_hours'] ?? array() );
		$this->social_links      = self::to_array_value( $data['social_links'] ?? array() );
		$this->action_buttons    = self::to_array_value( $data['action_buttons'] ?? array() );
		$this->extra_data        = self::to_array_value( $data['extra_data'] ?? array() );
		$this->timezone          = isset( $data['timezone'] ) ? (string) $data['timezone'] : '';
		$this->hours_mode        = isset( $data['hours_mode'] ) ? (string) $data['hours_mode'] : '';
		$this->group_id          = isset( $data['group_id'] ) ? (int) $data['group_id'] : 0;
		$this->date_created      = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
		$this->date_modified     = isset( $data['date_modified'] ) ? (string) $data['date_modified'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'                => (int) $row->id,
				'post_id'           => (int) $row->post_id,
				'owner_id'          => (int) $row->owner_id,
				'slug'              => (string) $row->slug,
				'name'              => (string) $row->name,
				'status'            => (string) $row->status,
				'phone'             => (string) $row->phone,
				'website'           => (string) $row->website,
				'email'             => (string) $row->email,
				'whatsapp'          => (string) $row->whatsapp,
				'address'           => (string) $row->address,
				'city'              => (string) $row->city,
				'state'             => (string) $row->state,
				'country'           => (string) $row->country,
				'zip'               => (string) $row->zip,
				'lat'               => (float) $row->lat,
				'lng'               => (float) $row->lng,
				'follower_count'    => (int) $row->follower_count,
				'review_count'      => (int) $row->review_count,
				'avg_rating'        => (float) $row->avg_rating,
				'view_count'        => (int) $row->view_count,
				'is_featured'       => (bool) $row->is_featured,
				'is_verified'       => (bool) $row->is_verified,
				'is_claimed'        => (bool) $row->is_claimed,
				'is_sponsored'      => (bool) $row->is_sponsored,
				'claimed_by'        => (int) $row->claimed_by,
				'claimed_date'      => (string) $row->claimed_date,
				'featured_expires'  => (string) $row->featured_expires,
				'sponsored_expires' => (string) $row->sponsored_expires,
				'plan'              => (string) $row->plan,
				'avatar_id'         => (int) $row->avatar_id,
				'cover_id'          => (int) $row->cover_id,
				'business_hours'    => self::to_array_value( $row->business_hours ?? array() ),
				'social_links'      => self::to_array_value( $row->social_links ?? array() ),
				'action_buttons'    => self::to_array_value( $row->action_buttons ?? array() ),
				'extra_data'        => self::to_array_value( $row->extra_data ?? array() ),
				'timezone'          => (string) $row->timezone,
				'hours_mode'        => (string) $row->hours_mode,
				'group_id'          => (int) $row->group_id,
				'date_created'      => (string) $row->date_created,
				'date_modified'     => (string) $row->date_modified,
			)
		);
	}

	/**
	 * To array.
	 */
	public function to_array(): array {
		$data = array();

		foreach ( get_object_vars( $this ) as $key => $value ) {
			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}
			$data[ $key ] = $value;
		}

		return $data;
	}

	/**
	 * To array value.
	 *
	 * @param mixed $value Value.
	 */
	private static function to_array_value( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );

			return is_array( $decoded ) ? $decoded : array();
		}

		return array();
	}
}
