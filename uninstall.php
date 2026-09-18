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

foreach ( array( 'solseo_settings', 'solseo_version', 'solseo_schema', 'solseo_hub', 'solseo_robots_rules' ) as $solseo_option ) {
	delete_option( $solseo_option );
}

delete_transient( 'solseo_sitemap_index' );
delete_transient( 'solseo_hub_overview' );

wp_clear_scheduled_hook( 'solseo_daily' );
wp_clear_scheduled_hook( 'solseo_hub_sync' );
