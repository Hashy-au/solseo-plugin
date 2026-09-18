<?php
/**
 * Wires the plugin up.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Entry point. Decides which parts of the plugin load for this request.
 */
class Plugin {

	/**
	 * Boot on plugins_loaded.
	 */
	public static function boot() {
		Meta::register();
		Breadcrumbs::init();
		Redirects\Manager::init();
		Hub\Connection::init();
		Score_Keeper::init();

		if ( Options::get( 'sitemap_enabled' ) ) {
			Sitemaps\Controller::init();
		}

		/*
		 * This one is registered on admin requests too. Nothing serves
		 * robots.txt from wp-admin, so it costs an array entry and changes
		 * nothing a visitor sees. What it buys is a settings screen that can
		 * show what is actually being served by running the filter chain that
		 * serves it, rather than a second copy of the same logic drifting away
		 * from the first.
		 */
		Frontend\Robots_Txt::init();
		Jobs\Runner::init();
		Links\Indexer::init();

		/*
		 * Outside the admin branch on purpose. WordPress runs the direct Site
		 * Health tests from a weekly cron event to fill the count on its own
		 * dashboard widget, and that request is not an admin request.
		 */
		Health\Site_Health::init();

		if ( is_admin() ) {
			Admin\Admin::init();
		} else {
			Frontend\Head::init();
			Frontend\Schema::init();
		}

		add_action( 'rest_api_init', array( __NAMESPACE__ . '\\Rest', 'register_routes' ) );
		add_action( 'init', array( __CLASS__, 'upgrade' ), 1 );
	}

	/**
	 * Run the install steps again when the stored version is behind.
	 */
	public static function upgrade() {
		if ( SOLSEO_VERSION === get_option( 'solseo_version' ) ) {
			return;
		}

		Install::activate();
		update_option( 'solseo_version', SOLSEO_VERSION, false );

		/*
		 * The link table arrives empty on a site that already has pages in it,
		 * so the first version that has one goes and reads them. It books
		 * itself a hundred at a time and stops when it runs out.
		 */
		if ( ! get_option( 'solseo_links_indexed' ) ) {
			Links\Indexer::start_backfill( 0 );
		}
	}
}
