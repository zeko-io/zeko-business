<?php
/**
 * Database schema manager for Zeko Business Directory.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Creates, upgrades and drops every custom table used by the plugin.
 */
final class Schema {

	public const TABLES = array(
		'zbp_businesses',
		'zbp_services',
		'zbp_service_categories',
		'zbp_staff',
		'zbp_staff_invites',
		'zbp_followers',
		'zbp_reviews',
		'zbp_review_votes',
		'zbp_media',
		'zbp_business_hours',
		'zbp_verification_requests',
		'zbp_claims',
		'zbp_notifications',
		'zbp_analytics',
		'zbp_analytics_daily',
		'zbp_import_log',
		'zbp_locations',
		'zbp_booking_slots',
		'zbp_bookings',
		'zbe_question_mentions',
	);

	/**
	 * Full (prefixed) name of a single plugin table.
	 *
	 * @param string $name Unprefixed table name, e.g. "zbp_businesses".
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . strtolower( preg_replace( '/[^a-zA-Z0-9_]/', '', $name ) );
	}

	/**
	 * All full (prefixed) table names managed by this plugin.
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		global $wpdb;
		return array_map(
			static function ( string $table ) use ( $wpdb ): string {
				return $wpdb->prefix . $table;
			},
			self::TABLES
		);
	}

	/**
	 * Create or update all tables via dbDelta().
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix;

		foreach ( self::definitions( $p, $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Drop every plugin table (used by uninstall routines).
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( self::tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
		}

		delete_option( 'zbe_db_version' );
	}

	/**
	 * DBDelta() cannot ALTER existing columns to change NULLability or
	 * defaults, so nullable date/id/json columns are corrected explicitly
	 * after each upgrade pass.
	 */
	public static function fix_nullable_columns(): void {
		global $wpdb;

		foreach ( self::nullable_columns() as $table_suffix => $columns ) {
			$table = $wpdb->prefix . $table_suffix;

			foreach ( $columns as $column => $definition ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) // phpcs:ignore WordPress.DB
				);

				if ( $exists !== $column ) {
					continue;
				}

				$wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN {$definition}" ); // phpcs:ignore WordPress.DB
			}
		}
	}

	/**
	 * Add missing columns to existing tables (dbDelta cannot add columns to
	 * already-created tables).
	 */
	public static function ensure_columns(): void {
		global $wpdb;

		$additions = array(
			'zbp_businesses' => array(
				'action_buttons' => "ALTER TABLE `{$wpdb->prefix}zbp_businesses` ADD COLUMN action_buttons longtext NULL DEFAULT NULL AFTER timezone",
				'hours_mode'     => "ALTER TABLE `{$wpdb->prefix}zbp_businesses` ADD COLUMN hours_mode varchar(20) NULL DEFAULT NULL AFTER action_buttons",
			),
			'zbp_services'   => array(
				'short_description' => "ALTER TABLE `{$wpdb->prefix}zbp_services` ADD COLUMN short_description text NULL DEFAULT NULL AFTER description",
				'tags'              => "ALTER TABLE `{$wpdb->prefix}zbp_services` ADD COLUMN tags varchar(500) DEFAULT '' AFTER duration",
			),
			'zbp_reviews'    => array(
				'photos'        => "ALTER TABLE `{$wpdb->prefix}zbp_reviews` ADD COLUMN photos text NULL DEFAULT NULL AFTER admin_reply",
				'helpful_count' => "ALTER TABLE `{$wpdb->prefix}zbp_reviews` ADD COLUMN helpful_count int(11) NOT NULL DEFAULT 0 AFTER photos",
				'criteria'      => "ALTER TABLE `{$wpdb->prefix}zbp_reviews` ADD COLUMN criteria text NULL DEFAULT NULL AFTER content",
			),
		);

		foreach ( $additions as $table_suffix => $columns ) {
			$table    = $wpdb->prefix . $table_suffix;
			$existing = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB
			if ( empty( $existing ) || ! is_array( $existing ) ) {
				continue;
			}
			foreach ( $columns as $col => $sql ) {
				if ( ! in_array( $col, $existing, true ) ) {
					$wpdb->query( $sql ); // phpcs:ignore WordPress.DB
				}
			}
		}
	}

	/**
	 * Columns that must stay NULLable, keyed by column name with the full
	 * MODIFY definition as the value.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function nullable_columns(): array {
		return array(
			'zbp_businesses'            => array(
				'post_id'           => '`post_id` bigint(20) unsigned NULL DEFAULT NULL',
				'owner_id'          => '`owner_id` bigint(20) unsigned NULL DEFAULT NULL',
				'phone'             => '`phone` varchar(50) NULL DEFAULT NULL',
				'website'           => '`website` varchar(255) NULL DEFAULT NULL',
				'email'             => '`email` varchar(255) NULL DEFAULT NULL',
				'whatsapp'          => '`whatsapp` varchar(50) NULL DEFAULT NULL',
				'address'           => '`address` text NULL',
				'city'              => '`city` varchar(100) NULL DEFAULT NULL',
				'state'             => '`state` varchar(100) NULL DEFAULT NULL',
				'country'           => '`country` varchar(100) NULL DEFAULT NULL',
				'zip'               => '`zip` varchar(20) NULL DEFAULT NULL',
				'lat'               => '`lat` decimal(10,7) NULL DEFAULT NULL',
				'lng'               => '`lng` decimal(10,7) NULL DEFAULT NULL',
				'claimed_by'        => '`claimed_by` bigint(20) unsigned NULL DEFAULT NULL',
				'claimed_date'      => '`claimed_date` datetime NULL DEFAULT NULL',
				'featured_expires'  => '`featured_expires` datetime NULL DEFAULT NULL',
				'sponsored_expires' => '`sponsored_expires` datetime NULL DEFAULT NULL',
				'avatar_id'         => '`avatar_id` bigint(20) unsigned NULL DEFAULT NULL',
				'cover_id'          => '`cover_id` bigint(20) unsigned NULL DEFAULT NULL',
				'business_hours'    => '`business_hours` longtext NULL',
				'social_links'      => '`social_links` longtext NULL',
				'extra_data'        => '`extra_data` longtext NULL',
				'timezone'          => '`timezone` varchar(50) NULL DEFAULT NULL',
				'action_buttons'    => '`action_buttons` longtext NULL',
				'hours_mode'        => '`hours_mode` varchar(20) NULL DEFAULT NULL',
			),
			'zbp_services'              => array(
				'description' => '`description` text NULL',
				'price_note'  => '`price_note` varchar(200) NULL DEFAULT NULL',
				'category'    => '`category` varchar(100) NULL DEFAULT NULL',
				'duration'    => '`duration` varchar(50) NULL DEFAULT NULL',
			),
			'zbp_staff'                 => array(
				'permissions' => '`permissions` longtext NULL',
			),
			'zbp_staff_invites'         => array(
				'expires_at'  => '`expires_at` datetime NULL DEFAULT NULL',
				'accepted_at' => '`accepted_at` datetime NULL DEFAULT NULL',
			),
			'zbp_reviews'               => array(
				'title'         => '`title` varchar(200) NULL DEFAULT NULL',
				'content'       => '`content` text NULL',
				'criteria'      => '`criteria` text NULL',
				'admin_reply'   => '`admin_reply` text NULL',
				'photos'        => '`photos` text NULL',
				'helpful_count' => '`helpful_count` int(11) NOT NULL DEFAULT 0',
			),
			'zbp_media'                 => array(
				'caption' => '`caption` varchar(255) NULL DEFAULT NULL',
			),
			'zbp_verification_requests' => array(
				'method'       => '`method` varchar(50) NULL DEFAULT NULL',
				'document_url' => '`document_url` varchar(255) NULL DEFAULT NULL',
				'note'         => '`note` text NULL',
				'reviewed_by'  => '`reviewed_by` bigint(20) unsigned NULL DEFAULT NULL',
				'reviewed_at'  => '`reviewed_at` datetime NULL DEFAULT NULL',
			),
			'zbp_claims'                => array(
				'method'      => '`method` varchar(50) NULL DEFAULT NULL',
				'evidence'    => '`evidence` text NULL',
				'reviewed_by' => '`reviewed_by` bigint(20) unsigned NULL DEFAULT NULL',
				'reviewed_at' => '`reviewed_at` datetime NULL DEFAULT NULL',
			),
			'zbp_notifications'         => array(
				'message' => '`message` text NULL',
				'link'    => '`link` varchar(255) NULL DEFAULT NULL',
			),
			'zbp_analytics'             => array(
				'user_agent' => '`user_agent` text NULL',
				'referer'    => '`referer` varchar(255) NULL DEFAULT NULL',
			),
			'zbp_import_log'            => array(
				'source_id'     => '`source_id` varchar(100) NULL DEFAULT NULL',
				'business_id'   => '`business_id` bigint(20) unsigned NULL DEFAULT NULL',
				'error_message' => '`error_message` text NULL',
			),
		);
	}

	/**
	 * Add missing hot-path indexes to existing tables.
	 * dbDelta() also adds new KEYs declared in the CREATE TABLE DDL, but this
	 * explicit existence-checked pass guarantees existing installs receive them
	 * even if a previous dbDelta pass was interrupted.
	 */
	public static function add_missing_indexes(): void {
		global $wpdb;

		$indexes = array(
			'zbp_reviews'       => array( 'business_status', '(`business_id`, `status`)' ),
			'zbp_notifications' => array( 'user_unread', '(`user_id`, `is_read`)' ),
		);

		foreach ( $indexes as $table_suffix => $key ) {
			$table  = $wpdb->prefix . $table_suffix;
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s', // phpcs:ignore WordPress.DB
					$table,
					$key[0]
				)
			);

			if ( ! $exists ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `{$key[0]}` {$key[1]}" ); // phpcs:ignore WordPress.DB
			}
		}
	}

	/**
	 * Raw CREATE TABLE statements following dbDelta formatting rules:
	 * one field per line, two spaces after PRIMARY KEY, named KEY indexes.
	 *
	 * @return string[]
	 * @param string $p Database table prefix.
	 * @param string $charset_collate Value of $wpdb->get_charset_collate().
	 */
	private static function definitions( string $p, string $charset_collate ): array {
		return array(

			<<<SQL
CREATE TABLE {$p}zbp_businesses (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	post_id bigint(20) unsigned NULL DEFAULT NULL,
	owner_id bigint(20) unsigned NULL DEFAULT NULL,
	slug varchar(255) NOT NULL,
	name varchar(255) NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'pending',
	phone varchar(50) NULL DEFAULT NULL,
	website varchar(255) NULL DEFAULT NULL,
	email varchar(255) NULL DEFAULT NULL,
	whatsapp varchar(50) NULL DEFAULT NULL,
	address text NULL,
	city varchar(100) NULL DEFAULT NULL,
	state varchar(100) NULL DEFAULT NULL,
	country varchar(100) NULL DEFAULT NULL,
	zip varchar(20) NULL DEFAULT NULL,
	lat decimal(10,7) NULL DEFAULT NULL,
	lng decimal(10,7) NULL DEFAULT NULL,
	follower_count int(11) NOT NULL DEFAULT 0,
	review_count int(11) NOT NULL DEFAULT 0,
	avg_rating decimal(3,2) NOT NULL DEFAULT 0.00,
	view_count int(11) NOT NULL DEFAULT 0,
	is_featured tinyint(1) NOT NULL DEFAULT 0,
	is_verified tinyint(1) NOT NULL DEFAULT 0,
	is_claimed tinyint(1) NOT NULL DEFAULT 0,
	claimed_by bigint(20) unsigned NULL DEFAULT NULL,
	claimed_date datetime NULL DEFAULT NULL,
	is_sponsored tinyint(1) NOT NULL DEFAULT 0,
	featured_expires datetime NULL DEFAULT NULL,
	sponsored_expires datetime NULL DEFAULT NULL,
	plan varchar(20) NOT NULL DEFAULT 'free',
	avatar_id bigint(20) unsigned NULL DEFAULT NULL,
	cover_id bigint(20) unsigned NULL DEFAULT NULL,
	business_hours longtext NULL,
	social_links longtext NULL,
	extra_data longtext NULL,
	timezone varchar(50) NULL DEFAULT NULL,
	action_buttons longtext NULL,
	hours_mode varchar(20) NULL DEFAULT NULL,
	group_id bigint(20) unsigned NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	date_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY slug (slug),
	KEY post_id (post_id),
	KEY owner_id (owner_id),
	KEY status (status),
	KEY is_featured (is_featured),
	KEY is_verified (is_verified),
	KEY city (city),
	KEY avg_rating (avg_rating),
	KEY date_created (date_created),
	KEY status_city (status,city),
	KEY status_featured (status,is_featured),
	KEY status_verified (status,is_verified),
	KEY owner_status (owner_id,status)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_services (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	name varchar(200) NOT NULL,
	description text NULL,
	price decimal(12,2) NOT NULL DEFAULT 0.00,
	price_type varchar(20) NOT NULL DEFAULT 'fixed',
	price_note varchar(200) NULL DEFAULT NULL,
	image_id bigint(20) unsigned NOT NULL DEFAULT 0,
	is_active tinyint(1) NOT NULL DEFAULT 1,
	is_featured tinyint(1) NOT NULL DEFAULT 0,
	category varchar(100) NULL DEFAULT NULL,
	duration varchar(50) NULL DEFAULT NULL,
	sort_order int(11) NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_id (business_id),
	KEY is_active (is_active),
	KEY is_featured (is_featured),
	KEY category (category),
	KEY sort_order (sort_order)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_service_categories (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(100) NOT NULL,
	slug varchar(100) NOT NULL,
	description text NULL,
	icon varchar(50) NULL DEFAULT NULL,
	sort_order int(11) NOT NULL DEFAULT 0,
	is_active tinyint(1) NOT NULL DEFAULT 1,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY slug (slug),
	KEY is_active (is_active),
	KEY sort_order (sort_order)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_staff (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	role varchar(50) NOT NULL DEFAULT 'staff',
	permissions longtext NULL,
	invited_by bigint(20) unsigned NOT NULL DEFAULT 0,
	status varchar(20) NOT NULL DEFAULT 'active',
	date_added datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY business_user (business_id,user_id),
	KEY user_id (user_id),
	KEY status (status)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_staff_invites (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	email varchar(255) NOT NULL,
	role varchar(50) NOT NULL DEFAULT 'staff',
	token varchar(64) NOT NULL,
	invited_by bigint(20) unsigned NOT NULL DEFAULT 0,
	status varchar(20) NOT NULL DEFAULT 'pending',
	expires_at datetime NULL DEFAULT NULL,
	accepted_at datetime NULL DEFAULT NULL,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY token (token),
	KEY business_id (business_id),
	KEY email (email),
	KEY status (status)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_followers (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY business_user (business_id,user_id),
	KEY business_id (business_id),
	KEY user_id (user_id)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_reviews (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	order_id bigint(20) unsigned NOT NULL DEFAULT 0,
	rating tinyint(1) NOT NULL,
	title varchar(200) NULL DEFAULT NULL,
	content text NULL,
	criteria text NULL,
	status varchar(20) NOT NULL DEFAULT 'approved',
	is_verified_purchase tinyint(1) NOT NULL DEFAULT 0,
	admin_reply text NULL,
	photos text NULL,
	helpful_count int(11) NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_id (business_id),
	KEY user_id (user_id),
	KEY status (status),
	KEY business_status (business_id, status),
	KEY rating (rating),
	KEY date_created (date_created)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_review_votes (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	review_id bigint(20) unsigned NOT NULL DEFAULT 0,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	value tinyint(1) NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY review_user (review_id,user_id),
	KEY review_id (review_id),
	KEY user_id (user_id)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_media (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
	caption varchar(255) NULL DEFAULT NULL,
	sort_order int(11) NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_id (business_id)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_business_hours (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	day_of_week tinyint(1) NOT NULL DEFAULT 0,
	open_time time NULL DEFAULT NULL,
	close_time time NULL DEFAULT NULL,
	is_closed tinyint(1) NOT NULL DEFAULT 0,
	PRIMARY KEY  (id),
	UNIQUE KEY business_day (business_id,day_of_week)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_verification_requests (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	method varchar(50) NULL DEFAULT NULL,
	document_url varchar(255) NULL DEFAULT NULL,
	note text NULL,
	status varchar(20) NOT NULL DEFAULT 'pending',
	reviewed_by bigint(20) unsigned NULL DEFAULT NULL,
	reviewed_at datetime NULL DEFAULT NULL,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_id (business_id),
	KEY status (status)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_claims (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	method varchar(50) NULL DEFAULT NULL,
	evidence text NULL,
	status varchar(20) NOT NULL DEFAULT 'pending',
	reviewed_by bigint(20) unsigned NULL DEFAULT NULL,
	reviewed_at datetime NULL DEFAULT NULL,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_id (business_id),
	KEY status (status)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_notifications (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	type varchar(50) NOT NULL,
	title varchar(200) NOT NULL,
	message text NULL,
	link varchar(255) NULL DEFAULT NULL,
	is_read tinyint(1) NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY user_id (user_id),
	KEY user_unread (user_id, is_read),
	KEY is_read (is_read),
	KEY date_created (date_created)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_analytics (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	action varchar(50) NOT NULL DEFAULT 'view',
	ip_address varchar(45) NULL DEFAULT NULL,
	user_agent text NULL,
	referer varchar(255) NULL DEFAULT NULL,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_date (business_id,date_created),
	KEY action (action)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_analytics_daily (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	day date NOT NULL,
	views int(11) NOT NULL DEFAULT 0,
	follows int(11) NOT NULL DEFAULT 0,
	reviews int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY  (id),
	UNIQUE KEY business_day (business_id,day),
	KEY day (day)
) {$charset_collate}
SQL
			,

			<<<SQL
	CREATE TABLE {$p}zbp_import_log (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	source varchar(50) NOT NULL,
	source_id varchar(100) NULL DEFAULT NULL,
	business_id bigint(20) unsigned NULL DEFAULT NULL,
	status varchar(20) NOT NULL DEFAULT 'imported',
	error_message text NULL,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY source_lookup (source,source_id),
	KEY business_id (business_id)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_locations (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	label varchar(200) NULL DEFAULT NULL,
	is_primary tinyint(1) NOT NULL DEFAULT 0,
	phone varchar(50) NULL DEFAULT NULL,
	email varchar(255) NULL DEFAULT NULL,
	address text NULL,
	city varchar(100) NULL DEFAULT NULL,
	state varchar(100) NULL DEFAULT NULL,
	country varchar(100) NULL DEFAULT NULL,
	zip varchar(20) NULL DEFAULT NULL,
	lat decimal(10,7) NULL DEFAULT NULL,
	lng decimal(10,7) NULL DEFAULT NULL,
	extra_data longtext NULL,
	sort_order int(11) NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	date_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_id (business_id),
	KEY business_primary (business_id,is_primary),
	KEY business_city (business_id,city),
	KEY sort_order (sort_order)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_booking_slots (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	location_id bigint(20) unsigned NOT NULL DEFAULT 0,
	service_id bigint(20) unsigned NOT NULL DEFAULT 0,
	day_of_week tinyint(1) NOT NULL DEFAULT 0,
	start_time time NOT NULL,
	end_time time NOT NULL,
	slot_duration_min int(11) NOT NULL DEFAULT 30,
	max_bookings int(11) NOT NULL DEFAULT 1,
	is_active tinyint(1) NOT NULL DEFAULT 1,
	sort_order int(11) NOT NULL DEFAULT 0,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	date_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_id (business_id),
	KEY location_id (location_id),
	KEY service_id (service_id),
	KEY business_day (business_id,day_of_week),
	KEY is_active (is_active)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbp_bookings (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	location_id bigint(20) unsigned NOT NULL DEFAULT 0,
	service_id bigint(20) unsigned NOT NULL DEFAULT 0,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	slot_id bigint(20) unsigned NOT NULL DEFAULT 0,
	booking_date date NOT NULL,
	start_time time NOT NULL,
	end_time time NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'pending',
	price decimal(12,2) NOT NULL DEFAULT 0.00,
	currency varchar(8) NOT NULL DEFAULT 'USD',
	payment_tx_id varchar(100) NULL DEFAULT NULL,
	notes text NULL,
	date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	date_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY business_id (business_id),
	KEY location_id (location_id),
	KEY service_id (service_id),
	KEY user_id (user_id),
	KEY slot_id (slot_id),
	KEY business_date (business_id,booking_date),
	KEY status (status),
	KEY user_date (user_id,booking_date)
) {$charset_collate}
SQL
			,

			<<<SQL
CREATE TABLE {$p}zbe_question_mentions (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	question_id bigint(20) unsigned NOT NULL DEFAULT 0,
	business_id bigint(20) unsigned NOT NULL DEFAULT 0,
	matched_on varchar(20) NOT NULL DEFAULT 'business',
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY mention_unique (question_id,business_id,matched_on),
	KEY question_id (question_id),
	KEY business_id (business_id)
) {$charset_collate}
SQL
			,
		);
	}
}
