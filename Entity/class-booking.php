<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class Booking. */
class Booking {

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
	 * Location id.
	 *
	 * @var int Location id.
	 */
	public int $location_id;
	/**
	 * Service id.
	 *
	 * @var int Service id.
	 */
	public int $service_id;
	/**
	 * User id.
	 *
	 * @var int User id.
	 */
	public int $user_id;
	/**
	 * Slot id.
	 *
	 * @var int Slot id.
	 */
	public int $slot_id;
	/**
	 * Booking date.
	 *
	 * @var string Booking date.
	 */
	public string $booking_date;
	/**
	 * Start time.
	 *
	 * @var string Start time.
	 */
	public string $start_time;
	/**
	 * End time.
	 *
	 * @var string End time.
	 */
	public string $end_time;
	/**
	 * Status.
	 *
	 * @var string Status.
	 */
	public string $status;
	/**
	 * Price.
	 *
	 * @var float Price.
	 */
	public float $price;
	/**
	 * Currency.
	 *
	 * @var string Currency.
	 */
	public string $currency;
	/**
	 * Payment tx id.
	 *
	 * @var string Payment tx id.
	 */
	public string $payment_tx_id;
	/**
	 * Notes.
	 *
	 * @var string Notes.
	 */
	public string $notes;
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
		$this->location_id   = isset( $data['location_id'] ) ? (int) $data['location_id'] : 0;
		$this->service_id    = isset( $data['service_id'] ) ? (int) $data['service_id'] : 0;
		$this->user_id       = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$this->slot_id       = isset( $data['slot_id'] ) ? (int) $data['slot_id'] : 0;
		$this->booking_date  = isset( $data['booking_date'] ) ? (string) $data['booking_date'] : '';
		$this->start_time    = isset( $data['start_time'] ) ? (string) $data['start_time'] : '';
		$this->end_time      = isset( $data['end_time'] ) ? (string) $data['end_time'] : '';
		$this->status        = isset( $data['status'] ) ? (string) $data['status'] : 'pending';
		$this->price         = isset( $data['price'] ) ? (float) $data['price'] : 0.0;
		$this->currency      = isset( $data['currency'] ) ? (string) $data['currency'] : 'USD';
		$this->payment_tx_id = isset( $data['payment_tx_id'] ) ? (string) $data['payment_tx_id'] : '';
		$this->notes         = isset( $data['notes'] ) ? (string) $data['notes'] : '';
		$this->date_created  = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
		$this->date_modified = isset( $data['date_modified'] ) ? (string) $data['date_modified'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'            => (int) $row->id,
				'business_id'   => (int) $row->business_id,
				'location_id'   => (int) $row->location_id,
				'service_id'    => (int) $row->service_id,
				'user_id'       => (int) $row->user_id,
				'slot_id'       => (int) $row->slot_id,
				'booking_date'  => (string) $row->booking_date,
				'start_time'    => (string) $row->start_time,
				'end_time'      => (string) $row->end_time,
				'status'        => (string) $row->status,
				'price'         => (float) $row->price,
				'currency'      => (string) $row->currency,
				'payment_tx_id' => (string) $row->payment_tx_id,
				'notes'         => (string) $row->notes,
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
	 * Confirmed.
	 */
	public function is_confirmed(): bool {
		return 'confirmed' === $this->status || 'completed' === $this->status;
	}
}
