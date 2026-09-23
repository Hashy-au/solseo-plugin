<?php
/**
 * A source is switched off as a family, or not at all.
 *
 * A source is not one plugin file. It is a free plugin and the paid add-on
 * that loads on top of it, and the add-on cannot run with the plugin
 * underneath it switched off. Switching off the first file in the list and
 * leaving the rest running is not a cosmetic slip: the add-on fatals while
 * WordPress boots, so every request answers 500, front end and admin alike,
 * and the person who pressed the button has no admin screen left to undo it
 * from. A customer site spent 2026-09-20 in exactly that state.
 *
 * @package SolSEO
 */

use SolSEO\Tools\Import;

/*
 * WordPress's own plugin functions, which live in an admin file the test suite
 * has no copy of. `solseo_test_active_plugins` is what they read.
 */
if ( ! function_exists( 'get_plugins' ) ) {
	/**
	 * Stub. Only its existence is read, by need_plugin_functions().
	 *
	 * @return array
	 */
	function get_plugins() {
		return array();
	}
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $plugin Plugin file.
	 * @return bool
	 */
	function is_plugin_active( $plugin ) {
		return in_array( $plugin, $GLOBALS['solseo_test_active_plugins'], true );
	}
}

$GLOBALS['solseo_test_active_plugins'] = array(
	'seo-by-rank-math/rank-math.php',
	'seo-by-rank-math-pro/rank-math-pro.php',
);

$solseo_family = array(
	'seo-by-rank-math/rank-math.php',
	'seo-by-rank-math-pro/rank-math-pro.php',
);

solseo_assert_same(
	$solseo_family,
	Import::active_plugins( $solseo_family ),
	'a family with a paid add-on running hands back both of its files, not the first one'
);

/*
 * And in the order they are defined, which is the plugin first and the add-on
 * after it.
 */
$GLOBALS['solseo_test_active_plugins'] = array_reverse( $solseo_family );

solseo_assert_same(
	$solseo_family,
	Import::active_plugins( $solseo_family ),
	'the order is the order they load in, whatever order the site stored them in'
);

$GLOBALS['solseo_test_active_plugins'] = array( 'seo-by-rank-math/rank-math.php' );

solseo_assert_same(
	array( 'seo-by-rank-math/rank-math.php' ),
	Import::active_plugins( $solseo_family ),
	'a family with only the free plugin running hands back the one file'
);

$GLOBALS['solseo_test_active_plugins'] = array();

solseo_assert_same(
	array(),
	Import::active_plugins( $solseo_family ),
	'a family with nothing running hands back nothing'
);

/*
 * Every source that names more than one plugin file is a family, so nothing
 * may go back to asking which single one of them is on.
 */
$solseo_off_screen = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-tools-screen.php' );
$solseo_off_view   = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/views/tools-import.php' );

solseo_assert(
	(bool) preg_match( '~name="solseo_deactivate\[\]"~', $solseo_off_view ),
	'the switch off form posts every file in the family'
);

solseo_assert(
	! preg_match( '~name="solseo_deactivate"~', $solseo_off_view ),
	'and does not post a single one of them'
);

solseo_assert(
	(bool) preg_match( '~deactivate_plugins\(\s*\$files~', $solseo_off_screen ),
	'and the handler switches off everything that was posted, in one call'
);
