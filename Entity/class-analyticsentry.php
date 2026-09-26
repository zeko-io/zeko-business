<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Entity;

defined( 'ABSPATH' ) || exit;

/** Class AnalyticsEntry. */
class AnalyticsEntry {

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
	 * User id.
	 *
	 * @var int User id.
	 */
	public int $user_id;
	/**
	 * Action.
	 *
	 * @var string Action.
	 */
	public string $action;
	/**
	 * Ip address.
	 *
	 * @var string Ip address.
	 */
	public string $ip_address;
	/**
	 * User agent.
	 *
	 * @var string User agent.
	 */
	public string $user_agent;
	/**
	 * Referer.
	 *
	 * @var string Referer.
	 */
	public string $referer;
	/**
	 * Date created.
	 *
	 * @var string Date created.
	 */
	public string $date_created;

	/**
	 * Construct.
	 *
	 * @param array $data Data.
	 */
	public function __construct( array $data = array() ) {
		$this->id           = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->business_id  = isset( $data['business_id'] ) ? (int) $data['business_id'] : 0;
		$this->user_id      = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$this->action       = isset( $data['action'] ) ? (string) $data['action'] : '';
		$this->ip_address   = isset( $data['ip_address'] ) ? (string) $data['ip_address'] : '';
		$this->user_agent   = isset( $data['user_agent'] ) ? (string) $data['user_agent'] : '';
		$this->referer      = isset( $data['referer'] ) ? (string) $data['referer'] : '';
		$this->date_created = isset( $data['date_created'] ) ? (string) $data['date_created'] : '';
	}

	/**
	 * From row.
	 *
	 * @param object $row Row.
	 */
	public static function from_row( object $row ): self {
		return new self(
			array(
				'id'           => (int) $row->id,
				'business_id'  => (int) $row->business_id,
				'user_id'      => (int) $row->user_id,
				'action'       => (string) $row->action,
				'ip_address'   => (string) $row->ip_address,
				'user_agent'   => (string) $row->user_agent,
				'referer'      => (string) $row->referer,
				'date_created' => (string) $row->date_created,
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
