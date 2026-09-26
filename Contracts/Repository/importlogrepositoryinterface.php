<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Contracts\Repository;

defined( 'ABSPATH' ) || exit;

/** Class ImportLogRepositoryInterface. */
interface ImportLogRepositoryInterface {

	/**
	 * Record one imported (or failed) row.
	 *
	 * @param array $data {source, source_id?, business_id?, status, error_message?}.
	 */
	public function log( array $data ): void;

	/**
	 * Recent.
	 *
	 * @return array<int, object> Most recent import log rows.
	 * @param int $limit Limit.
	 */
	public function recent( int $limit = 20 ): array;
}
