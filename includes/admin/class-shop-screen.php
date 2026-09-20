<?php
/**
 * The shop, when there is one.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The Shop screen.
 *
 * A slot, not a feature. This plugin declares it so that an add-on has
 * somewhere to put a tab without declaring a menu item of its own, and it
 * stays out of the sidebar until one does.
 */
class Shop_Screen extends Tabbed_Screen {

	const PAGE = 'solseo-shop';
}
