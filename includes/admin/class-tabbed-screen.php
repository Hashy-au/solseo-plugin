<?php
/**
 * A screen that is nothing but its tabs.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Draws whichever tab is open and gets out of the way.
 *
 * A child declares a PAGE constant and nothing else. Everything on the screen
 * arrives through the tab registry, which is what lets an add-on fill a screen
 * this plugin declared without either of them knowing much about the other.
 */
abstract class Tabbed_Screen extends Screen {

	/**
	 * Run the open tab's handler before anything is printed.
	 */
	public static function load() {
		$tabs = Tabs::for_page( static::PAGE );

		if ( ! $tabs ) {
			return;
		}

		Tabs::load( $tabs, Tabs::current( $tabs ) );
	}

	/**
	 * Draw the tab row and the open tab.
	 */
	public static function render() {
		self::notice();

		$tabs = Tabs::for_page( static::PAGE );

		if ( ! $tabs ) {
			return;
		}

		$current = Tabs::current( $tabs );

		self::tabs( static::PAGE, Tabs::labels( $tabs ), $current );

		Tabs::render( $tabs, $current );
	}
}
