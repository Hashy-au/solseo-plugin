<?php
/**
 * What this site is running.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * The Features tab: one row per module, with what it adds beside it.
 */
class Features_Tab extends Screen {

	const PAGE = 'solseo-settings';

	const TAB = 'features';

	/**
	 * Save the switches.
	 */
	public static function load() {
		if ( ! self::submitted( 'solseo_features' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- submitted() checked the nonce.
		$ticked = isset( $_POST['solseo_modules'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['solseo_modules'] ) ) : array();

		foreach ( array_keys( Modules::all() ) as $slug ) {
			Modules::set( $slug, in_array( $slug, $ticked, true ) );
		}

		self::remember( __( 'Saved.', 'solseo' ) );
		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		$modules = Modules::all();
		$rows    = array();

		foreach ( $modules as $slug => $module ) {
			$rows[ $slug ] = array(
				'label'   => $module['label'],
				'blurb'   => isset( $module['blurb'] ) ? $module['blurb'] : '',
				'enabled' => Modules::enabled( $slug ),
				'hooks'   => Modules::hooks_added( $slug ),
				'facts'   => Modules::facts( $slug ),
			);
		}

		echo '<form method="post" class="solseo-form">';
		self::nonce( 'solseo_features' );

		self::view(
			'settings-features',
			array(
				'rows'    => $rows,
				'upsell'  => Upgrade_Screen::available(),
				'upgrade' => add_query_arg( 'page', Upgrade_Screen::PAGE, admin_url( 'admin.php' ) ),
			)
		);

		submit_button();

		echo '</form>';
	}
}
