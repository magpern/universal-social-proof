<?php
/**
 * Uninstall: remove all USP-owned data (ADR-0017).
 *
 * @package UniversalSocialProof
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$options = array(
	'usp_settings',
	'usp_settings_version',
	'usp_retention_days',
	'usp_exclude_out_of_stock',
	'usp_db_version',
	'usp_db_migrate_lock',
);

foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}

$table = $wpdb->prefix . 'usp_events';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table suffix.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'usp_retention_daily', array(), 'universal-social-proof' );
	as_unschedule_all_actions( 'usp_retention_purge', array(), 'universal-social-proof' );
}
