<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class BusinessHour. */
class BusinessHour {

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
	 * Day of week.
	 *
	 * @var int Day of week.
	 */
	public int $day_of_week;
	/**
	 * Open time.
	 *
	 * @var string Open time.
	 */
	public string $open_time;
	/**
	 * Close time.
	 *
	 * @var string Close time.
	 */
	public string $close_time;
	/**
	 * Is closed.
	 *
	 * @var bool Is closed.
	 */
	public bool $is_closed;

	/**
	 * Construct.
	 *
	 * @param array $data Data.
	 */
	public function __construct( array $data = array() ) {
		$this->id          = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->day_of_week = isset( $data['day_of_week'] ) ? (int) $data['day_of_week'] : 0;
		$this->open_time   = isset( $data['open_time'] ) ? (string) $data['open_time'] : '';
		$this->close_time  = isset( $data['close_time'] ) ? (string) $data['close_time'] : '';
		$this->is_closed   = ! empty( $data['is_closed'] );
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'          => (int) $row->id,
				'business_id' => (int) $row->business_id,
				'day_of_week' => (int) $row->day_of_week,
				'open_time'   => (string) $row->open_time,
				'close_time'  => (string) $row->close_time,
				'is_closed'   => (bool) $row->is_closed,
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
}
