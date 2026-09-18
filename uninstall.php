<?php
/**
 * Removes the plugin's data, but only when the site asked for that.
 *
 * @package SolSEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$solseo_settings = get_option( 'solseo_settings', array() );

if ( empty( $solseo_settings['remove_data'] ) ) {
	return;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}solseo_redirects" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}solseo_not_found" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_solseo\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '\_solseo\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$solseo_options = array(
	'solseo_settings',
	'solseo_version',
	'solseo_schema',
	'solseo_hub',
	'solseo_robots_rules',
	'solseo_robots_backup',
	'solseo_jobs',
	'solseo_setup',
	'solseo_switched_off',
	'solseo_links_indexed',
);

foreach ( $solseo_options as $solseo_option ) {
	delete_option( $solseo_option );
}

delete_metadata( 'user', 0, 'solseo_conflict_dismissed', '', true );

delete_transient( 'solseo_sitemap_index' );
delete_transient( 'solseo_hub_overview' );

/*
 * A robots.txt that was moved aside is left where it is on purpose. It is
 * somebody's file, we did not write it, and deleting it on the way out would
 * be strictly worse than leaving a file behind.
 */

wp_clear_scheduled_hook( 'solseo_daily' );
wp_clear_scheduled_hook( 'solseo_hub_sync' );
