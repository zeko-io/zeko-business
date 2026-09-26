<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Location. */
class Location {

	/**
	 * Id.
	 *
	 * @var int Id.
	 */
	public int $id;
	/**
	 * Business id.
	 *
	 * @var int Business id.
	 */
	public int $business_id;
	/**
	 * Label.
	 *
	 * @var string Label.
	 */
	public string $label;
	/**
	 * Is primary.
	 *
	 * @var bool Is primary.
	 */
	public bool $is_primary;
	/**
	 * Phone.
	 *
	 * @var string Phone.
	 */
	public string $phone;
	/**
	 * Email.
	 *
	 * @var string Email.
	 */
	public string $email;
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
	 * Extra data.
	 *
	 * @var array Extra data.
	 */
	public array $extra_data;
	/**
	 * Sort order.
	 *
	 * @var int Sort order.
	 */
	public int $sort_order;
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
		$this->id            = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id   = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->label         = isset( $data['label'] ) ? (string) $data['label'] : '';
		$this->is_primary    = ! empty( $data['is_primary'] );
		$this->phone         = isset( $data['phone'] ) ? (string) $data['phone'] : '';
		$this->email         = isset( $data['email'] ) ? (string) $data['email'] : '';
		$this->address       = isset( $data['address'] ) ? (string) $data['address'] : '';
		$this->city          = isset( $data['city'] ) ? (string) $data['city'] : '';
		$this->state         = isset( $data['state'] ) ? (string) $data['state'] : '';
		$this->country       = isset( $data['country'] ) ? (string) $data['country'] : '';
		$this->zip           = isset( $data['zip'] ) ? (string) $data['zip'] : '';
		$this->lat           = isset( $data['lat'] ) ? (float) $data['lat'] : 0.0;
		$this->lng           = isset( $data['lng'] ) ? (float) $data['lng'] : 0.0;
		$this->extra_data    = isset( $data['extra_data'] ) && is_array( $data['extra_data'] ) ? $data['extra_data'] : array();
		$this->sort_order    = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
		$this->date_created  = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
		$this->date_modified = isset( $data['date_modified'] ) ? (string) $data['date_modified'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		$extra = isset( $row->extra_data ) && ! empty( $row->extra_data ) ? json_decode( (string) $row->extra_data, true ) : array();

		return new self(
			array(
				'id'            => (int) $row->id,
				'business_id'   => (int) $row->business_id,
				'label'         => (string) $row->label,
				'is_primary'    => (bool) $row->is_primary,
				'phone'         => (string) $row->phone,
				'email'         => (string) $row->email,
				'address'       => (string) $row->address,
				'city'          => (string) $row->city,
				'state'         => (string) $row->state,
				'country'       => (string) $row->country,
				'zip'           => (string) $row->zip,
				'lat'           => (float) $row->lat,
				'lng'           => (float) $row->lng,
				'extra_data'    => is_array( $extra ) ? $extra : array(),
				'sort_order'    => (int) $row->sort_order,
				'date_created'  => (string) $row->date_created,
				'date_modified' => (string) $row->date_modified,
			)
		);
	}

	/**
	 * To array.
	 */
	public function to_array(): array {
		$data = array();

		foreach ( get_object_vars( $this ) as $key => $value ) {
			if ( null === $value || '' === $value || array() === $value || 0.0 === $value ) {
				continue;
			}
			$data[ $key ] = $value;
		}

		return $data;
	}

	/**
	 * Coordinates.
	 */
	public function coordinates(): array {
		$lat = $this->lat;
		$lng = $this->lng;

		if ( 0.0 === $lat || 0.0 === $lng ) {
			return array();
		}

		return array(
			'lat' => $lat,
			'lng' => $lng,
		);
	}
}
