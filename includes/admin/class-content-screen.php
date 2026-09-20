<?php
/**
 * What the pages say.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The Content screen.
 *
 * Declared here and drawn nowhere until something puts a tab on it: internal
 * links, the schema library, the writing profile and the phrase work all land
 * here. A screen with no tabs is not in the menu, so this costs one array
 * entry until the day it holds something.
 */
class Content_Screen extends Tabbed_Screen {

	const PAGE = 'solseo-content';
}
