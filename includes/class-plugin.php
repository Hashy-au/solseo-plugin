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

		if ( is_admin() ) {
			Admin\Admin::init();
		} else {
			Frontend\Head::init();
			Frontend\Schema::init();
			Frontend\Robots_Txt::init();
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
	}
}
