<?php
/**
 * Activation, deactivation and the database tables.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Sets the site up and tidies up after itself.
 */
class Install {

	/** Bumped when a table changes shape. */
	const SCHEMA = 2;

	/**
	 * Create the tables, seed the settings and schedule the jobs.
	 */
	public static function activate() {
		self::tables();

		if ( ! get_option( Options::KEY ) ) {
			add_option( Options::KEY, Options::defaults() );
		}

		if ( ! wp_next_scheduled( 'solseo_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'solseo_daily' );
		}

		/*
		 * Record robots.txt as it was before we touched it. This runs again on
		 * every version change, and the call inside refuses to overwrite, so
		 * what is kept is always the site as it was on the day we arrived.
		 */
		Robots_Backup::snapshot();

		update_option( 'solseo_version', SOLSEO_VERSION, false );
		update_option( 'solseo_schema', self::SCHEMA, false );

		Sitemaps\Controller::rewrites();
		flush_rewrite_rules( false );
	}

	/**
	 * Stop the scheduled jobs and drop the rewrite rules.
	 *
	 * Nothing is deleted here. Uninstall does that, and only when asked.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'solseo_daily' );
		wp_clear_scheduled_hook( 'solseo_hub_sync' );
		delete_transient( 'solseo_sitemap_index' );
		flush_rewrite_rules( false );
	}

	/**
	 * Create or update the plugin's tables.
	 */
	public static function tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		dbDelta(
			"CREATE TABLE {$prefix}solseo_redirects (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source varchar(255) NOT NULL DEFAULT '',
				target text NOT NULL,
				status_code smallint(4) NOT NULL DEFAULT 301,
				match_type varchar(10) NOT NULL DEFAULT 'exact',
				enabled tinyint(1) NOT NULL DEFAULT 1,
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				last_used datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY source (source(191)),
				KEY enabled (enabled)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$prefix}solseo_not_found (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url varchar(255) NOT NULL DEFAULT '',
				referrer varchar(255) NOT NULL DEFAULT '',
				hits bigint(20) unsigned NOT NULL DEFAULT 1,
				first_seen datetime NOT NULL,
				last_seen datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY url (url(191)),
				KEY last_seen (last_seen)
			) {$charset};"
		);

		/*
		 * Which page links to which. The post ID is the join, so the index
		 * survives a page being renamed. The address is kept beside it so a
		 * link written before its target existed can be resolved later by the
		 * daily sweep, and so a link to something that has since gone is still
		 * a link rather than a missing row.
		 */
		dbDelta(
			"CREATE TABLE {$prefix}solseo_links (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_id bigint(20) unsigned NOT NULL DEFAULT 0,
				target_id bigint(20) unsigned NOT NULL DEFAULT 0,
				target_url varchar(255) NOT NULL DEFAULT '',
				link_type varchar(10) NOT NULL DEFAULT 'internal',
				PRIMARY KEY  (id),
				KEY source_id (source_id),
				KEY target_id (target_id),
				KEY target_url (target_url(191))
			) {$charset};"
		);
	}

	/**
	 * Table name for one of the plugin's tables.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'solseo_' . $name;
	}
}
