<?php
/**
 * Analytics domain service.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Domain;

use ZBE\Contracts\Repository\AnalyticsRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/** Class AnalyticsService. */
class AnalyticsService {

	/**
	 * Repo.
	 *
	 * @var AnalyticsRepositoryInterface Repo.
	 */
	private AnalyticsRepositoryInterface $repo;

	/**
	 * Construct.
	 *
	 * @param AnalyticsRepositoryInterface $repo Repo.
	 */
	public function __construct( AnalyticsRepositoryInterface $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Track.
	 *
	 * @param int    $business_id Business id.
	 * @param string $action Action.
	 * @param int    $user_id User id.
	 */
	public function track( int $business_id, string $action, int $user_id = 0 ): void {
		if ( $business_id < 1 || '' === $action ) {
			return;
		}

		$this->repo->log(
			array(
				'business_id'  => $business_id,
				'user_id'      => max( 0, $user_id ),
				'action'       => sanitize_key( $action ),
				'referer'      => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'date_created' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Views.
	 *
	 * @param int $business_id Business id.
	 * @param int $days Days.
	 */
	public function get_views( int $business_id, int $days = 30 ): int {
		if ( $business_id < 1 ) {
			return 0;
		}

		$window = $this->window( max( 1, $days ) );

		return $this->repo->get_views( $business_id, $window['from'], $window['to'] );
	}

	/**
	 * Top actions.
	 *
	 * @param int $business_id Business id.
	 * @param int $limit Limit.
	 */
	public function get_top_actions( int $business_id, int $limit = 10 ): array {
		if ( $business_id < 1 ) {
			return array();
		}

		return $this->repo->get_top_actions( $business_id, max( 1, $limit ) );
	}

	/**
	 * Summary.
	 *
	 * @param int $business_id Business id.
	 */
	public function get_summary( int $business_id ): array {
		if ( $business_id < 1 ) {
			return array();
		}

		$d30 = $this->window( 30 );
		$d7  = $this->window( 7 );

		return array(
			'total_views'     => $this->repo->get_views( $business_id, '1970-01-01 00:00:00', $d30['to'] ),
			'views_30d'       => $this->repo->get_views( $business_id, $d30['from'], $d30['to'] ),
			'views_7d'        => $this->repo->get_views( $business_id, $d7['from'], $d7['to'] ),
			'unique_visitors' => $this->repo->count_unique_visitors( $business_id, $d30['from'], $d30['to'] ),
			'top_referrers'   => $this->repo->get_top_referrers( $business_id, 5 ),
		);
	}

	/**
	 * Local-time datetime boundaries for the last N days.
	 *
	 * @return array{from: string, to: string}
	 * @param int $days Days.
	 */
	private function window( int $days ): array {
		$now = time();

		return array(
			'from' => gmdate( 'Y-m-d H:i:s', $now - $days * DAY_IN_SECONDS ),
			'to'   => gmdate( 'Y-m-d H:i:s', $now ),
		);
	}
}
