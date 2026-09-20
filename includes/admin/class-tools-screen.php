<?php
/**
 * Import, bulk image work and the robots.txt additions.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Jobs\Runner;
use SolSEO\Options;
use SolSEO\Tools\Alt_Text;
use SolSEO\Tools\Import;

defined( 'ABSPATH' ) || exit;

/**
 * The Tools screen.
 */
class Tools_Screen extends Screen {

	const PAGE = 'solseo-tools';

	/** Which plugin was switched off, until the next screen has said so. */
	const OFF_OPTION = 'solseo_switched_off';

	/**
	 * Handle the forms.
	 */
	public static function load() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- deactivate_source() checks its own nonce and its own capability, which are not this screen's.
		if ( ! empty( $_POST['solseo_deactivate'] ) ) {
			self::deactivate_source();
		}

		if ( self::submitted( 'solseo_data' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read as a flag.
			$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

			Options::update( array( 'remove_data' => ! empty( $input['remove_data'] ) ) );

			self::remember( __( 'Saved.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => 'data' ) );
		}

		$tabs = Tabs::for_page( self::PAGE );
		Tabs::load( $tabs, Tabs::current( $tabs ) );
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		$tabs    = Tabs::for_page( self::PAGE );
		$current = Tabs::current( $tabs );

		self::tabs( self::PAGE, Tabs::labels( $tabs ), $current );

		Tabs::render( $tabs, $current );
	}

	/**
	 * Copy the fields from another SEO plugin.
	 */
	public static function tab_import() {
		$prefix = self::chosen_source();

		self::view(
			'tools-import',
			array(
				'sources' => Import::sources(),
				'chosen'  => $prefix,
				'preview' => $prefix ? Import::preview( $prefix, 15 ) : array(),
				'state'   => Runner::state( 'import' ),
			)
		);
	}

	/**
	 * Alt text for the images that have none.
	 */
	public static function tab_images() {
		self::view(
			'tools-images',
			array(
				'missing' => Alt_Text::count_missing(),
				'rows'    => Alt_Text::missing( 50 ),
				'state'   => Runner::state( 'images' ),
			)
		);
	}

	/**
	 * What happens to what we stored if the plugin is removed.
	 */
	public static function tab_data() {
		self::view( 'tools-data', array( 'remove' => (bool) Options::get( 'remove_data' ) ) );
	}

	/**
	 * Which source the screen is previewing.
	 *
	 * @return string
	 */
	protected static function chosen_source() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing what to preview.
		$prefix  = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
		$sources = Import::sources();

		if ( $prefix && isset( $sources[ $prefix ] ) ) {
			return $prefix;
		}

		$keys = array_keys( $sources );

		return $keys ? $keys[0] : '';
	}

	/**
	 * Switch off the plugin the fields were copied from.
	 *
	 * Offered only after a run that checked itself, and only ever as the
	 * answer to somebody pressing a button and then confirming it. This is the
	 * one thing in the plugin that reaches outside itself, so it carries its
	 * own checks rather than the shared ones: the capability for switching a
	 * plugin off is not the capability for changing a setting, and the posted
	 * file has to be one we already know about.
	 *
	 * Nothing is deleted. Every field the other plugin stored stays where it
	 * is, so switching it back on puts the site as it was.
	 */
	protected static function deactivate_source() {
		$nonce = isset( $_POST['_solseo_nonce'] ) ? sanitize_key( wp_unslash( $_POST['_solseo_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'solseo_deactivate' ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			self::remember( __( 'You are not allowed to switch plugins off on this site.', 'solseo' ), 'error' );
			self::go_back( self::PAGE );
		}

		$plugin = isset( $_POST['solseo_deactivate'] ) ? sanitize_text_field( wp_unslash( $_POST['solseo_deactivate'] ) ) : '';

		if ( ! in_array( $plugin, Import::known_plugins(), true ) ) {
			self::remember( __( 'That is not a plugin this screen knows about.', 'solseo' ), 'error' );
			self::go_back( self::PAGE );
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( $plugin ) ) {
			self::remember( __( 'That plugin is already switched off.', 'solseo' ) );
			self::go_back( self::PAGE );
		}

		deactivate_plugins( $plugin, false, false );

		/*
		 * Written down rather than remembered in a cache or carried in the
		 * address. This is the one action in the plugin that changes what else
		 * is running on the site, so the sentence proving the button worked has
		 * to survive whatever happens between the press and the next screen.
		 *
		 * Both of the usual ways were tried and neither is sound here. A sixty
		 * second transient is a cache, and a cache is allowed to be empty. A
		 * query argument depends on the browser following our redirect with the
		 * address intact, which is exactly what a page doing something to the
		 * set of active plugins cannot count on. An option is neither.
		 */
		update_option(
			self::OFF_OPTION,
			array(
				'plugin' => $plugin,
				'at'     => time(),
			),
			false
		);

		self::go_back( self::PAGE, array( 'tab' => 'import' ) );
	}

	/**
	 * The line shown just after another plugin has been switched off.
	 *
	 * It ages out rather than clearing itself the first time it is read. A
	 * message that is destroyed by being looked at is a message that goes
	 * missing whenever anything renders the screen twice, and a browser that
	 * re-issues a request is a thing that happens. Showing it for a minute and
	 * then stopping cannot be got wrong.
	 *
	 * @return string Empty when nothing was switched off just now.
	 */
	public static function switched_off() {
		$stored = get_option( self::OFF_OPTION, array() );

		if ( ! is_array( $stored ) || empty( $stored['plugin'] ) ) {
			return '';
		}

		if ( time() - (int) $stored['at'] > MINUTE_IN_SECONDS ) {
			delete_option( self::OFF_OPTION );

			return '';
		}

		$plugin = (string) $stored['plugin'];

		if ( ! in_array( $plugin, Import::known_plugins(), true ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: the name of the plugin that was switched off. */
			__( '%s is switched off. Nothing of its was deleted, so you can switch it back on from the Plugins screen whenever you like.', 'solseo' ),
			Import::name( array( $plugin ), '' )
		);
	}
}
