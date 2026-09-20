<?php
/**
 * The log of addresses that found nothing.
 *
 * @package SolSEO
 */

namespace SolSEO\Redirects;

use SolSEO\Install;

defined( 'ABSPATH' ) || exit;

// The tables below belong to this plugin. A table name cannot be passed to
// $wpdb->prepare() as a placeholder, so it is interpolated and the values
// around it are prepared. There is nothing to cache: a redirect has to be
// read on the request it answers.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

/**
 * Reads and clears the not found log.
 */
class Log {

	/**
	 * Entries, most recent first.
	 *
	 * @param int $per_page How many to return.
	 * @param int $offset   Where to start.
	 * @return array
	 */
	public static function recent( $per_page = 50, $offset = 0 ) {
		global $wpdb;

		$table = Install::table( 'not_found' );

		return (array) $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY last_seen DESC LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_A
		);
	}

	/**
	 * How many addresses are in the log.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		$table = Install::table( 'not_found' );

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
	}

	/**
	 * Remove one entry.
	 *
	 * @param int $id Entry ID.
	 */
	public static function delete( $id ) {
		global $wpdb;

		$wpdb->delete( Install::table( 'not_found' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Empty the log.
	 */
	public static function clear() {
		global $wpdb;

		$table = Install::table( 'not_found' );

		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
	}
}
