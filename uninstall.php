<?php
/**
 * Uninstall routine for Zeko Business Directory.
 *
 * Removes plugin options by default. Custom tables and business
 * content are only dropped when ZBE_UNINSTALL_DROP_DATA is defined
 * as true in wp-config.php, so accidental data loss is avoided.
 *
 * @package ZekoBusinessDirectory
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$options = array(
	'zbe_pages',
	'zbe_db_version',
	'zbe_demo_business_id',
	'zbe_demo_registry',
	'zbe_services_migrated',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

if ( ! defined( 'ZBE_UNINSTALL_DROP_DATA' ) || ! ZBE_UNINSTALL_DROP_DATA ) {
	return;
}

global $wpdb;

$tables = array(
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

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
}

$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_zbe_business_id','_zbe_is_demo')"
);
