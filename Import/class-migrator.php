<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import;

use ZBE\Core\Services;
use ZBE\Import\Sources\BpBusinessProfileSource;
use ZBE\Import\Sources\BusinessDirectory5StarSource;
use ZBE\Import\Sources\DirectoristSource;
use ZBE\Import\Sources\GeoDirectorySource;
use ZBE\Import\Sources\HivePressSource;
use ZBE\Import\Sources\ListdomSource;
use ZBE\Import\Sources\SourceInterface;
use ZBE\Import\Sources\WpbdmSource;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates listings from foreign directory plugins into Zeko Business.
 *
 * Every source is represented by an adapter under `Import/Sources/` that only
 * reads + normalizes the foreign data. This class owns all persistence so a
 * new source is a drop-in adapter, not a copy of the write pipeline.
 *
 * Large sources are imported in the background: `start_migration()` snapshots
 * the normalized rows and chains one `zbe_import_process_batch` cron run per
 * window. Small sources stay synchronous for instant feedback.
 *
 * Documented exception (as before): this file keeps raw `$wpdb` access by
 * design — foreign plugin schemas (custom detail tables, serialized meta)
 * can't be expressed through the plugin's repositories, and imports write
 * through `ImportLogRepository` + `Core\Cache::flush()`.
 */
class Migrator {

	const BATCH_HOOK = 'zbe_import_process_batch';

	/**
	 * BATCH SIZE.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 100;

	/**
	 * SYNC THRESHOLD.
	 *
	 * @var int
	 */
	private const SYNC_THRESHOLD = 50;

	/**
	 * ERROR CAP.
	 *
	 * @var int
	 */
	private const ERROR_CAP = 20;

	/**
	 * SOURCE CLASSES.
	 *
	 * @var array<string,
	 */
	private const SOURCE_CLASSES = array(
		'bpbp'         => BpBusinessProfileSource::class,
		'wpbm'         => WpbdmSource::class,
		'geodirectory' => GeoDirectorySource::class,
		'directorist'  => DirectoristSource::class,
		'wpbp'         => BusinessDirectory5StarSource::class,
		'hivepress'    => HivePressSource::class,
		'listdom'      => ListdomSource::class,
	);

	/**
	 * Available.
	 *
	 * @var mixed Available.
	 */
	private $available = array();

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->detect_sources();
	}

	/**
	 * Register the background batch hook and the (rate-capped) log-retention
	 * sweep. Called from Plugin::wire().
	 */
	public static function init_hooks(): void {
		add_action( self::BATCH_HOOK, array( __CLASS__, 'process_batch' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_retention_sweep' ), 20 );
	}

	/**
	 * One window of a queued background import (cron callback).
	 *
	 * @param string $source Source.
	 */
	public static function process_batch( string $source ): void {
		( new self() )->run_batch( $source );
	}

	/**
	 * Purge import-log rows older than a retention window.
	 *
	 * @return int Rows deleted.
	 * @param int $days Days.
	 */
	public function purge_logs( int $days ): int {
		global $wpdb;

		$days   = max( 1, min( 3650, $days ) );
		$table  = $wpdb->prefix . 'zbp_import_log';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE date_created < %s", $cutoff ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return (int) $wpdb->rows_affected;
	}

	/**
	 * Admin_init sweep behind the "keep import logs for N days" setting.
	 * Rate-capped to one run per day regardless of how often admin_init fires.
	 */
	public static function maybe_retention_sweep(): void {
		$days = (int) get_option( 'zbe_import_log_retention_days', 0 );

		if ( $days < 1 ) {
			return;
		}

		$last = (int) get_option( 'zbe_import_log_last_sweep', 0 );

		if ( time() - $last < DAY_IN_SECONDS ) {
			return;
		}

		$removed = ( new self() )->purge_logs( $days );

		update_option( 'zbe_import_log_last_sweep', time(), false );

		do_action( 'zbe_import_log_purged', $removed );
	}

	/**
	 * Adapter lookups. Kept instance-bound so the constructor cache is used.
	 */
	private function detect_sources(): void {
		foreach ( self::SOURCE_CLASSES as $key => $class ) {
			/** Resolved source adapter. @var SourceInterface $adapter */
			$adapter = new $class();

			if ( $adapter->present() ) {
				$this->available[ $key ] = array(
					'name'         => $adapter->label(),
					'version'      => $adapter->version(),
					'status'       => $adapter->status(),
					'capabilities' => $adapter->capabilities(),
				);
			}
		}
	}

	/**
	 * Available sources.
	 */
	public function get_available_sources(): array {
		return $this->available;
	}

	/**
	 * Non-destructive scan of a source: counts + a small sample. Writes nothing.
	 *
	 * @param string $source Source.
	 * @param int    $limit Limit.
	 */
	public function preview( string $source, int $limit = 10 ): array {
		$adapter = $this->adapter_for( $source );

		if ( ! $adapter ) {
			return array(
				'success' => false,
				/* translators: %s: migration source identifier */
				'error'   => sprintf( __( 'Unknown migration source: %s', 'zeko-business' ), $source ),
			);
		}

		if ( ! $adapter->present() ) {
			return array(
				'success' => false,
				/* translators: %s: migration source label */
				'error'   => sprintf( __( 'No %s data was detected on this site.', 'zeko-business' ), $adapter->label() ),
			);
		}

		$data = $adapter->collect();
		$rows = (array) ( $data['businesses'] ?? array() );

		$counts = array(
			'categories' => 0,
			'photos'     => 0,
			'reviews'    => 0,
			'followers'  => 0,
		);

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$counts['categories'] += count( (array) ( $row['categories'] ?? array() ) );
			$counts['photos']     += count( (array) ( $row['photos'] ?? array() ) );
			$counts['reviews']    += count( (array) ( $row['reviews'] ?? array() ) );
			$counts['followers']  += count( (array) ( $row['followers'] ?? array() ) );
		}

		$sample = array();

		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			$sample[] = array(
				'title'      => (string) ( $row['title'] ?? 'Untitled' ),
				'status'     => (string) ( $row['status'] ?? 'pending' ),
				'city'       => (string) ( $row['city'] ?? '' ),
				'email'      => (string) ( $row['email'] ?? '' ),
				'categories' => array_slice( array_values( (array) ( $row['categories'] ?? array() ) ), 0, 3 ),
				'photos'     => count( (array) ( $row['photos'] ?? array() ) ),
				'reviews'    => count( (array) ( $row['reviews'] ?? array() ) ),
			);
		}

		return array(
			'success'      => true,
			'source'       => $adapter->label(),
			'capabilities' => $adapter->capabilities(),
			'total'        => count( $rows ),
			'counts'       => $counts,
			'sample'       => $sample,
			'warnings'     => array_values( array_slice( (array) ( $data['warnings'] ?? array() ), 0, self::ERROR_CAP ) ),
			'background'   => count( $rows ) > self::SYNC_THRESHOLD,
		);
	}

	/**
	 * Start a migration. Small sources run in this request; large ones are
	 * queued as a background cron job.
	 * (replaces the previously imported business).
	 *
	 * @param string $source Source.
	 * @param bool   $force Re-import listings already present in the import log.
	 */
	public function start_migration( string $source, bool $force = false ): array {
		$adapter = $this->adapter_for( $source );

		if ( ! $adapter ) {
			return array(
				'success' => false,
				/* translators: %s: migration source identifier */
				'error'   => sprintf( __( 'Unknown migration source: %s', 'zeko-business' ), $source ),
			);
		}

		if ( ! $adapter->present() ) {
			return array(
				'success' => false,
				/* translators: %s: migration source label */
				'error'   => sprintf( __( 'No %s data was detected on this site.', 'zeko-business' ), $adapter->label() ),
			);
		}

		$data = $adapter->collect();
		$rows = (array) ( $data['businesses'] ?? array() );

		if ( count( $rows ) <= self::SYNC_THRESHOLD ) {
			return $this->import_rows( $source, $data, (bool) $force );
		}

		return $this->start_cron_job( $source, $data['source'] ?? $source, $rows, (bool) $force );
	}

	/**
	 * Fully synchronous import — kept for BC (CLI/tests). Routed through the
	 * same pipeline as start_migration() for small sources.
	 *
	 * @param string $source Source.
	 */
	public function migrate( string $source ): array {
		return $this->start_migration( $source, false );
	}

	/**
	 * Current job state for a source, or null when nothing is queued/running.
	 *
	 * @return array|null
	 * @param string $source Source.
	 */
	public function get_progress( string $source ): ?array {
		$state = get_option( $this->state_key( $source ), null );

		return is_array( $state ) ? $state : null;
	}

	/**
	 * Progress for every source that has an (active or finished) job.
	 *
	 * @return array<string, array>
	 */
	public function get_all_progress(): array {
		$jobs = array();

		foreach ( array_keys( self::SOURCE_CLASSES ) as $source ) {
			$state = $this->get_progress( $source );

			if ( null !== $state ) {
				$jobs[ $source ] = $state;
			}
		}

		return $jobs;
	}

	/**
	 * Drop a queued/running/completed job (state, snapshot, pending crons).
	 *
	 * @param string $source Source.
	 */
	public function cancel_job( string $source ): bool {
		delete_option( $this->state_key( $source ) );
		delete_option( $this->rows_key( $source ) );
		delete_transient( $this->lock_key( $source ) );

		$timestamp = wp_next_scheduled( self::BATCH_HOOK, array( $source ) );

		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::BATCH_HOOK, array( $source ) );
			$timestamp = wp_next_scheduled( self::BATCH_HOOK, array( $source ) );
		}

		\ZBE\Core\Cache::flush();

		return true;
	}

	/**
	 * Run the shared write pipeline over a source's normalized rows.
	 *
	 * @param string $source Adapter key.
	 * @param array  $data Output of SourceInterface::collect().
	 * @param bool   $force Re-import rows already present in the import log.
	 */
	private function import_rows( string $source, array $data, bool $force = false ): array {
		$counters = $this->fresh_counters();

		$label = $data['source'] ?? $source;

		foreach ( (array) ( $data['businesses'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['title'] ) ) {
				++$counters['failed'];
				continue;
			}

			$this->persist_row( $source, $row, $counters, $force );
		}

		if ( ! empty( $data['warnings'] ) && is_array( $data['warnings'] ) ) {
			foreach ( $data['warnings'] as $warning ) {
				$counters['errors'][] = (string) $warning;
			}
		}

		\ZBE\Core\Cache::flush();

		return $this->summary( $label, $counters );
	}

	/**
	 * Persist a single normalized row (business + categories + photos +
	 * reviews + followers) with dedupe against the import log.
	 *
	 * @param string $source Source.
	 * @param array  $row * @param array $counters Mutable counters.
	 * @param array  $counters Counters.
	 * @param bool   $force Re-import (replaces the prior business).
	 */
	private function persist_row( string $source, array $row, array &$counters, bool $force = false ): void {
		global $wpdb;

		$source_id = ! empty( $row['post_id'] )
			? (string) (int) $row['post_id']
			: 'id:' . md5( (string) ( $row['slug'] ?? $row['title'] ) );

		if ( $this->already_imported( $source, $source_id ) ) {
			if ( ! $force ) {
				++$counters['skipped'];
				return;
			}

			$previous_id = $this->imported_business_id( $source, $source_id );

			if ( $previous_id > 0 ) {
				$this->delete_business_relations( $previous_id, $source, $source_id );
			}

			++$counters['replaced'];
		}

		$biz_table = $wpdb->prefix . 'zbp_businesses';
		$name      = sanitize_text_field( (string) $row['title'] );
		$slug      = $this->unique_slug( sanitize_title( $name ?: (string) $row['slug'] ), $biz_table );

		$business_data = array(
			'post_id'     => ! empty( $row['post_id'] ) ? (int) $row['post_id'] : null,
			'owner_id'    => ! empty( $row['owner_id'] ) ? (int) $row['owner_id'] : null,
			'slug'        => $slug,
			'name'        => $name,
			'status'      => in_array( (string) $row['status'], array( 'active', 'pending' ), true ) ? (string) $row['status'] : 'pending',
			'email'       => sanitize_email( (string) ( $row['email'] ?? '' ) ),
			'phone'       => sanitize_text_field( (string) ( $row['phone'] ?? '' ) ),
			'website'     => esc_url_raw( (string) ( $row['website'] ?? '' ) ),
			'whatsapp'    => sanitize_text_field( (string) ( $row['whatsapp'] ?? '' ) ),
			'address'     => sanitize_textarea_field( (string) ( $row['address'] ?? '' ) ),
			'city'        => sanitize_text_field( (string) ( $row['city'] ?? '' ) ),
			'state'       => sanitize_text_field( (string) ( $row['state'] ?? '' ) ),
			'country'     => sanitize_text_field( (string) ( $row['country'] ?? '' ) ),
			'zip'         => sanitize_text_field( (string) ( $row['zip'] ?? '' ) ),
			'is_featured' => ! empty( $row['featured'] ) ? 1 : 0,
			'is_verified' => ! empty( $row['is_verified'] ) ? 1 : 0,
			'extra_data'  => wp_json_encode(
				array(
					'source'      => $source,
					'source_id'   => $source_id,
					'categories'  => array_values( array_filter( (array) ( $row['categories'] ?? array() ) ) ),
					'description' => (string) ( $row['description'] ?? '' ),
				)
			),
		);

		$lat = (float) ( $row['lat'] ?? 0 );
		$lng = (float) ( $row['lng'] ?? 0 );

		if ( $lat || $lng ) {
			$business_data['lat'] = $lat;
			$business_data['lng'] = $lng;
		}

		if ( ! empty( $row['date_created'] ) && is_string( $row['date_created'] ) ) {
			$business_data['date_created'] = $this->mysql_datetime( $row['date_created'] );
		}

		$business_data = array_filter( $business_data, static fn( $v ) => null !== $v );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( $biz_table, $business_data, $this->formats_for( $business_data ) );

		if ( false === $inserted ) {
			++$counters['failed'];
			$counters['errors'][] = sprintf( 'Failed to import "%s": %s', $name, (string) $wpdb->last_error );
			return;
		}

		$business_id = (int) $wpdb->insert_id;

		$categories_added = $this->import_categories( ! empty( $row['post_id'] ) ? (int) $row['post_id'] : 0, (array) ( $row['categories'] ?? array() ) );
		$photos_added     = $this->import_photos( $business_id, (array) ( $row['photos'] ?? array() ) );
		$reviews_added    = $this->import_reviews( $business_id, (array) ( $row['reviews'] ?? array() ) );
		$followers_added  = $this->import_followers( $business_id, (array) ( $row['followers'] ?? array() ) );

		$this->recompute_stats( $business_id );

		Services::import_logs()->log(
			array(
				'source'      => $source,
				'source_id'   => $source_id,
				'business_id' => $business_id,
				'status'      => 'imported',
			)
		);

		++$counters['imported'];
		$counters['categories_added']   += $categories_added;
		$counters['photos_imported']    += $photos_added;
		$counters['reviews_imported']   += $reviews_added;
		$counters['followers_imported'] += $followers_added;
	}

	/**
	 * Process one BATCH_SIZE window of a queued background job.
	 *
	 * @param string $source Source.
	 */
	private function run_batch( string $source ): void {
		$state = $this->get_progress( $source );

		if ( null === $state || empty( $state['total'] ) ) {
			return;
		}

		if ( get_transient( $this->lock_key( $source ) ) ) {
			return;
		}

		set_transient( $this->lock_key( $source ), 1, 10 * MINUTE_IN_SECONDS );

		$payload = get_option( $this->rows_key( $source ), '' );

		if ( ! is_string( $payload ) || '' === $payload ) {
			$this->finish_batch( $source, $state, array( 'status' => 'failed' ), true );
			return;
		}

		$decoded = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Import interchange payload, gzip+base64 by design.

		// Guard against corrupt or oversized queued payloads (which may be
		// zip-bomb-expanded by gzuncompress) before materializing anything.
		if ( false === $decoded || strlen( $decoded ) > 8 * MB_IN_BYTES ) {
			$this->finish_batch( $source, $state, array( 'status' => 'failed' ), true );
			return;
		}

		$rows = maybe_unserialize( gzuncompress( $decoded ) );

		if ( ! is_array( $rows ) ) {
			$this->finish_batch( $source, $state, array( 'status' => 'failed' ), true );
			return;
		}

		$offset   = (int) ( $state['offset'] ?? 0 );
		$counters = $this->fresh_counters();

		foreach ( array_slice( $rows, $offset, self::BATCH_SIZE ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['title'] ) ) {
				++$counters['failed'];
				continue;
			}

			$this->persist_row( $source, $row, $counters, ! empty( $state['force'] ) );
		}

		foreach ( array_keys( $counters ) as $key ) {
			if ( 'errors' === $key ) {
				$state['errors'] = array_slice( array_merge( (array) ( $state['errors'] ?? array() ), $counters['errors'] ), 0, self::ERROR_CAP );
				continue;
			}

			if ( is_int( $counters[ $key ] ) ) {
				$state[ $key ] = (int) ( $state[ $key ] ?? 0 ) + $counters[ $key ];
			}
		}

		$state['offset']    = $offset + count( array_slice( $rows, $offset, self::BATCH_SIZE ) );
		$state['status']    = 'processing';
		$state['processed'] = min( (int) $state['total'], (int) $state['offset'] );

		update_option( $this->state_key( $source ), $state, false );

		if ( (int) $state['offset'] >= (int) $state['total'] ) {
			$this->finish_batch( $source, $state, array( 'status' => 'completed' ), false );
			return;
		}

		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::BATCH_HOOK, array( $source ) );

		delete_transient( $this->lock_key( $source ) );
	}

	/**
	 * Finalize a background job (completed or failed): persist the terminal
	 * state, drop the snapshot + lock + pending cron, flush caches.
	 *
	 * @param string $source Source.
	 * @param array  $state State.
	 * @param array  $overrides Overrides.
	 * @param bool   $drop_rows Drop rows.
	 */
	private function finish_batch( string $source, array $state, array $overrides = array(), bool $drop_rows = true ): void {
		foreach ( $overrides as $key => $value ) {
			$state[ $key ] = $value;
		}

		$state['completed_at'] = time();

		update_option( $this->state_key( $source ), $state, false );

		if ( $drop_rows ) {
			delete_option( $this->rows_key( $source ) );
		}

		delete_transient( $this->lock_key( $source ) );

		$timestamp = wp_next_scheduled( self::BATCH_HOOK, array( $source ) );

		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::BATCH_HOOK, array( $source ) );
			$timestamp = wp_next_scheduled( self::BATCH_HOOK, array( $source ) );
		}

		\ZBE\Core\Cache::flush();

		if ( 'completed' === $state['status'] ) {
			do_action( 'zbe_import_job_completed', $source, $state );
		} else {
			do_action( 'zbe_import_job_failed', $source, $state );
		}
	}

	/**
	 * Queue a large source as a chained background job.
	 *
	 * @param string $source Source.
	 * @param string $label Label.
	 * @param array  $rows Rows.
	 * @param bool   $force Force.
	 */
	private function start_cron_job( string $source, string $label, array $rows, bool $force ): array {
		$payload = base64_encode( gzcompress( maybe_serialize( $rows ), 9 ) );

		if ( ! is_string( $payload ) || '' === $payload ) {
			return array(
				'success' => false,
				'error'   => __( 'This source is too large to snapshot for a background import. Increase your PHP memory limit or disable plugins that consume memory, then retry.', 'zeko-business' ),
			);
		}

		$state = array(
			'source'             => $source,
			'label'              => (string) $label,
			'status'             => 'queued',
			'total'              => count( $rows ),
			'offset'             => 0,
			'processed'          => 0,
			'batch_size'         => self::BATCH_SIZE,
			'force'              => (bool) $force,
			'imported'           => 0,
			'skipped'            => 0,
			'failed'             => 0,
			'replaced'           => 0,
			'categories_added'   => 0,
			'photos_imported'    => 0,
			'reviews_imported'   => 0,
			'followers_imported' => 0,
			'errors'             => array(),
			'warnings'           => array(),
			'started_at'         => time(),
			'completed_at'       => null,
		);

		delete_transient( $this->lock_key( $source ) );

		update_option( $this->rows_key( $source ), $payload, false );
		update_option( $this->state_key( $source ), $state, false );

		wp_schedule_single_event( time() + 5, self::BATCH_HOOK, array( $source ) );

		$summary               = $this->summary( $label, $this->fresh_counters() );
		$summary['background'] = true;
		$summary['queued']     = true;
		$summary['total']      = count( $rows );
		$summary['imported']   = 0;
		$summary['skipped']    = 0;
		$summary['failed']     = 0;

		return $summary;
	}

	/**
	 * Resolve an adapter by key or null when unknown.
	 *
	 * @param string $source Source.
	 */
	private function adapter_for( string $source ): ?SourceInterface {
		$class = self::SOURCE_CLASSES[ $source ] ?? '';

		if ( '' === $class || ! class_exists( $class ) ) {
			return null;
		}

		return new $class();
	}

	/**
	 * /** @return array<string, int|array> */

	/**
	 * /** @return array<string, int|array>
	 */
	private function fresh_counters(): array {
		return array(
			'imported'           => 0,
			'skipped'            => 0,
			'failed'             => 0,
			'replaced'           => 0,
			'categories_added'   => 0,
			'photos_imported'    => 0,
			'reviews_imported'   => 0,
			'followers_imported' => 0,
			'errors'             => array(),
		);
	}

	/**
	 * Summary.
	 *
	 * @param string $label Label.
	 * @param array  $counters Counters.
	 */
	private function summary( string $label, array $counters ): array {
		$errors = array_values( array_slice( (array) $counters['errors'], 0, self::ERROR_CAP ) );

		return array(
			'success'            => 0 === (int) $counters['failed'] || (int) $counters['imported'] > 0,
			'source'             => $label,
			'imported'           => (int) $counters['imported'],
			'skipped'            => (int) $counters['skipped'],
			'failed'             => (int) $counters['failed'],
			'replaced'           => (int) $counters['replaced'],
			'categories_added'   => (int) $counters['categories_added'],
			'photos_imported'    => (int) $counters['photos_imported'],
			'reviews_imported'   => (int) $counters['reviews_imported'],
			'followers_imported' => (int) $counters['followers_imported'],
			'warnings'           => $errors,
			'errors'             => $errors,
		);
	}

	/**
	 * State key.
	 *
	 * @param string $source Source.
	 */
	private function state_key( string $source ): string {
		return 'zbe_migration_' . $source;
	}

	/**
	 * Rows key.
	 *
	 * @param string $source Source.
	 */
	private function rows_key( string $source ): string {
		return 'zbe_migration_rows_' . $source;
	}

	/**
	 * Lock key.
	 *
	 * @param string $source Source.
	 */
	private function lock_key( string $source ): string {
		return 'zbe_import_lock_' . $source;
	}

	/**
	 * Whether (source, source_id) already exists in the import log.
	 *
	 * @param string $source Source.
	 * @param string $source_id Source id.
	 */
	private function already_imported( string $source, string $source_id ): bool {
		return $this->imported_business_id( $source, $source_id ) > 0;
	}

	/**
	 * Business created for (source, source_id), 0 when none.
	 *
	 * @param string $source Source.
	 * @param string $source_id Source id.
	 */
	private function imported_business_id( string $source, string $source_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'zbp_import_log';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT business_id FROM {$table} WHERE source = %s AND source_id = %s ORDER BY id DESC LIMIT 1", $source, $source_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return max( 0, (int) $id );
	}

	/**
	 * Remove a previously imported business and all its migrated relations
	 * (used by the force/re-import toggle).
	 *
	 * @param int    $business_id Business id.
	 * @param string $source Source.
	 * @param string $source_id Source id.
	 */
	private function delete_business_relations( int $business_id, string $source, string $source_id ): void {
		global $wpdb;

		$biz   = $wpdb->prefix . 'zbp_businesses';
		$rev   = $wpdb->prefix . 'zbp_reviews';
		$votes = $wpdb->prefix . 'zbp_review_votes';
		$fol   = $wpdb->prefix . 'zbp_followers';
		$media = $wpdb->prefix . 'zbp_media';
		$log   = $wpdb->prefix . 'zbp_import_log';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$votes} WHERE review_id IN (SELECT id FROM {$rev} WHERE business_id = %d)", $business_id ) );
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$rev} WHERE business_id = %d", $business_id ) );
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$fol} WHERE business_id = %d", $business_id ) );
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$media} WHERE business_id = %d", $business_id ) );
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$log} WHERE business_id = %d OR (source = %s AND source_id = %s)", $business_id, $source, $source_id ) );
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$biz} WHERE id = %d", $business_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		\ZBE\Core\Cache::flush();
	}

	/**
	 * Unique slug for a business (appends -{n} while taken).
	 *
	 * @param string $slug Slug.
	 * @param string $table Table.
	 */
	private function unique_slug( string $slug, string $table ): string {
		global $wpdb;

		if ( '' === $slug ) {
			$slug = 'business';
		}

		$base = substr( $slug, 0, 190 );
		$slug = $base;
		$i    = 1;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) ) ) {
			++$i;
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$slug = substr( $base, 0, 200 - strlen( (string) $i ) - 1 ) . '-' . $i;
		}

		return $slug;
	}

	/**
	 * Format types for $wpdb->insert matching the values we build.
	 *
	 * @return array
	 * @param array $data *.
	 */
	private function formats_for( array $data ): array {
		$formats = array();

		foreach ( $data as $value ) {
			if ( is_int( $value ) ) {
				$formats[] = '%d';
			} elseif ( is_float( $value ) ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		return $formats;
	}

	/**
	 * Parse a foreign date string into a MySQL datetime (fallback: now).
	 *
	 * @param string $value Value.
	 */
	private function mysql_datetime( string $value ): string {
		$value = trim( $value );

		if ( '' !== $value ) {
			$timestamp = strtotime( $value );

			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d H:i:s', $timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			}
		}

		return current_time( 'mysql' );
	}

	/**
	 * Ensure categories exist as `business_category` terms and attach them to
	 * the source post when possible.
	 *
	 * @return int Number of newly-created terms.
	 * @param int   $post_id Post id.
	 * @param array $names Names.
	 */
	private function import_categories( int $post_id, array $names ): int {
		global $wpdb;

		$names = array_values( array_unique( array_filter( array_map( 'strval', $names ), static fn( $n ) => '' !== trim( $n ) ) ) );

		if ( empty( $names ) ) {
			return 0;
		}

		if ( ! taxonomy_exists( 'business_category' ) ) {
			return 0;
		}

		$created  = 0;
		$term_ids = array();

		foreach ( array_slice( $names, 0, 3 ) as $name ) {
			$term = term_exists( $name, 'business_category' );

			if ( ! $term ) {
				$inserted = wp_insert_term( $name, 'business_category' );

				if ( is_wp_error( $inserted ) ) {
					continue;
				}

				++$created;
				$term = $inserted;
			}

			$term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
		}

		if ( $post_id > 0 && ! empty( $term_ids ) ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$existing = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type != 'attachment'", $post_id ) );

			if ( ! empty( $existing ) ) {
				wp_set_object_terms( $post_id, $term_ids, 'business_category' );
			}
		}

		return $created;
	}

	/**
	 * Import photos into zbp_media (max 20 per plan free tier, hard cap 20).
	 * Sets the first photo as the business avatar and a marked "cover" one as
	 * the business cover.
	 *
	 * @return int Number of media rows added.
	 * @param int   $business_id Business id.
	 * @param array $photos Photos.
	 */
	private function import_photos( int $business_id, array $photos ): int {
		$added  = 0;
		$avatar = 0;
		$cover  = 0;
		$order  = 0;

		foreach ( array_slice( $photos, 0, 20 ) as $photo ) {
			if ( ! is_array( $photo ) || empty( $photo['src'] ) ) {
				continue;
			}

			$attachment_id = $this->resolve_attachment( $photo['src'] );

			if ( $attachment_id < 1 ) {
				continue;
			}

			Services::media()->create(
				array(
					'business_id'   => $business_id,
					'attachment_id' => (int) $attachment_id,
					'caption'       => sanitize_text_field( (string) ( $photo['caption'] ?? '' ) ),
					'sort_order'    => $order,
				)
			);

			++$added;

			if ( 0 === $order ) {
				$avatar = (int) $attachment_id;
			}

			if ( ! empty( $photo['cover'] ) ) {
				$cover = (int) $attachment_id;
			}

			++$order;
		}

		if ( $added ) {
			global $wpdb;

			$biz_table = $wpdb->prefix . 'zbp_businesses';
			$set       = array( '`avatar_id` = %d' );
			$values    = array( $avatar );

			if ( $cover ) {
				$set[]    = '`cover_id` = %d';
				$values[] = $cover;
			}

			$values[] = $business_id;

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "UPDATE {$biz_table} SET " . implode( ', ', $set ) . ' WHERE id = %d', $values ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		return $added;
	}

	/**
	 * Return a valid attachment id for a numeric ID or image URL. Sideloads
	 * external URLs into the media library when they aren't already there.
	 *
	 * @param int|string $src Src.
	 */
	private function resolve_attachment( $src ): int {
		if ( is_numeric( $src ) ) {
			$attachment_id = (int) $src;

			return wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
		}

		$url = trim( (string) $src );

		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return 0;
		}

		$attachment_id = (int) attachment_url_to_postid( $url );

		if ( $attachment_id > 0 ) {
			return wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
		}

		return $this->sideload_attachment( $url );
	}

	/**
	 * Download an image URL into the media library.
	 *
	 * @param string $url Url.
	 */
	private function sideload_attachment( string $url ): int {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		$file_array = array(
			'name'     => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ?: 'photo.jpg' ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );

		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;
	}

	/**
	 * Insert source reviews (only for real, existing users).
	 *
	 * @return int
	 * @param int   $business_id Business id.
	 * @param array $reviews Reviews.
	 */
	private function import_reviews( int $business_id, array $reviews ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'zbp_reviews';
		$added = 0;

		foreach ( $reviews as $review ) {
			if ( ! is_array( $review ) ) {
				continue;
			}

			$user_id = (int) ( $review['user_id'] ?? 0 );
			$rating  = (int) ( $review['rating'] ?? 0 );

			if ( $user_id < 1 ) {
				continue;
			}

			if ( ! get_userdata( $user_id ) ) {
				continue;
			}

			$rating = min( 5, max( 1, $rating ) );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$dupe = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND user_id = %d AND rating = %d", $business_id, $user_id, $rating ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

			if ( $dupe > 0 ) {
				continue;
			}

			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->insert(
				$table,
				array(
					'business_id'  => $business_id,
					'user_id'      => $user_id,
					'rating'       => $rating,
					'title'        => sanitize_text_field( (string) ( $review['title'] ?? '' ) ),
					'content'      => sanitize_textarea_field( (string) ( $review['content'] ?? '' ) ),
					'status'       => 'approved' === ( $review['status'] ?? 'approved' ) ? 'approved' : 'pending',
					'date_created' => $this->mysql_datetime( (string) ( $review['date_created'] ?? '' ) ),
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( false !== $ok ) {
				++$added;
			}
		}

		return $added;
	}

	/**
	 * Insert source followers (only for real, existing users and no dups).
	 *
	 * @return int
	 * @param int   $business_id Business id.
	 * @param array $followers Followers.
	 */
	private function import_followers( int $business_id, array $followers ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'zbp_followers';
		$added = 0;

		foreach ( $followers as $user_id ) {
			$user_id = (int) $user_id;

			if ( $user_id < 1 || ! get_userdata( $user_id ) ) {
				continue;
			}

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$dupe = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND user_id = %d", $business_id, $user_id ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

			if ( $dupe > 0 ) {
				continue;
			}

			//phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->insert(
				$table,
				array(
					'business_id' => $business_id,
					'user_id'     => $user_id,
				),
				array( '%d', '%d' )
			);

			if ( false !== $ok ) {
				++$added;
			}
		}

		return $added;
	}

	/**
	 * Recompute aggregate counters for a business after a migration.
	 *
	 * @param int $business_id Business id.
	 */
	private function recompute_stats( int $business_id ): void {
		global $wpdb;

		$biz = $wpdb->prefix . 'zbp_businesses';
		$rev = $wpdb->prefix . 'zbp_reviews';
		$fol = $wpdb->prefix . 'zbp_followers';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$biz} b
				SET review_count   = (SELECT COUNT(*) FROM {$rev} r WHERE r.business_id = b.id AND r.status = 'approved'),
					avg_rating     = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM {$rev} r WHERE r.business_id = b.id AND r.status = 'approved'), 0.00),
					follower_count = (SELECT COUNT(*) FROM {$fol} f WHERE f.business_id = b.id)
				WHERE b.id = %d",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}
}
