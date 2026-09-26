<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class BookingSlot. */
class BookingSlot {

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
	 * Day of week.
	 *
	 * @var int Day of week.
	 */
	public int $day_of_week;
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
	 * Slot duration min.
	 *
	 * @var int Slot duration min.
	 */
	public int $slot_duration_min;
	/**
	 * Max bookings.
	 *
	 * @var int Max bookings.
	 */
	public int $max_bookings;
	/**
	 * Is active.
	 *
	 * @var bool Is active.
	 */
	public bool $is_active;
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
		$this->id                = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id       = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->location_id       = isset( $data['location_id'] ) ? (int) $data['location_id'] : 0;
		$this->service_id        = isset( $data['service_id'] ) ? (int) $data['service_id'] : 0;
		$this->day_of_week       = isset( $data['day_of_week'] ) ? (int) $data['day_of_week'] : 0;
		$this->start_time        = isset( $data['start_time'] ) ? (string) $data['start_time'] : '';
		$this->end_time          = isset( $data['end_time'] ) ? (string) $data['end_time'] : '';
		$this->slot_duration_min = isset( $data['slot_duration_min'] ) ? (int) $data['slot_duration_min'] : 30;
		$this->max_bookings      = isset( $data['max_bookings'] ) ? (int) $data['max_bookings'] : 1;
		$this->is_active         = ! empty( $data['is_active'] );
		$this->sort_order        = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
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
				'business_id'       => (int) $row->business_id,
				'location_id'       => (int) $row->location_id,
				'service_id'        => (int) $row->service_id,
				'day_of_week'       => (int) $row->day_of_week,
				'start_time'        => (string) $row->start_time,
				'end_time'          => (string) $row->end_time,
				'slot_duration_min' => (int) $row->slot_duration_min,
				'max_bookings'      => (int) $row->max_bookings,
				'is_active'         => (bool) $row->is_active,
				'sort_order'        => (int) $row->sort_order,
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
	 * Number of discrete bookable sub-slots within this window at the
	 * configured duration (minimum 1 when a window is open).
	 */
	public function sub_slot_count(): int {
		$duration = max( 1, $this->slot_duration_min );

		if ( $duration <= 0 ) {
			return 0;
		}

		$start = strtotime( '1970-01-01 ' . $this->start_time );
		$end   = strtotime( '1970-01-01 ' . $this->end_time );

		if ( false === $start || false === $end || $end <= $start ) {
			return 0;
		}

		return max( 1, (int) floor( ( $end - $start ) / ( $duration * 60 ) ) );
	}
}
