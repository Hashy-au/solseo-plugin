<?php
/**
 * Sitemap settings.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Options;
use SolSEO\Sitemaps\Controller;

defined( 'ABSPATH' ) || exit;

/**
 * The Sitemap screen.
 */
class Sitemap_Screen extends Screen {
	/*
	 * This was a menu item of its own until 1.4.0 and is now a tab on the
	 * Technical screen, so PAGE is the screen it is drawn inside. The old
	 * address still works: Menu::MOVED sends it here with this tab open.
	 */
	const PAGE = 'solseo-technical';

	/** Which tab on that screen. */
	const TAB = 'sitemap';

	/**
	 * Save the form.
	 */
	public static function load() {
		if ( ! self::submitted( 'solseo_sitemap' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is cast below.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

		Options::update(
			array(
				'sitemap_enabled'  => ! empty( $input['sitemap_enabled'] ),
				'sitemap_images'   => ! empty( $input['sitemap_images'] ),
				'sitemap_authors'  => ! empty( $input['sitemap_authors'] ),
				'sitemap_per_page' => max( 50, min( 2000, (int) ( isset( $input['sitemap_per_page'] ) ? $input['sitemap_per_page'] : 500 ) ) ),
			)
		);

		Controller::clear_cache();
		flush_rewrite_rules( false );

		self::remember( __( 'Saved.', 'solseo' ) );
		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		$enabled = Options::get( 'sitemap_enabled' );

		echo '<form method="post" class="solseo-form">';
		self::nonce( 'solseo_sitemap' );

		self::view(
			'sitemap',
			array(
				'options'  => Options::all(),
				'sections' => $enabled ? Controller::sections() : array(),
			)
		);

		submit_button();

		echo '</form>';
	}
}
