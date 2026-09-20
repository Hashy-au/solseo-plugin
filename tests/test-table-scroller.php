<?php
/**
 * Every wrapper class a table sits in has a rule that actually scrolls.
 *
 * WHY THIS IS A GUARD AND NOT A CONVENTION. `.table-wrap` is the hub's class,
 * defined in `hub/public/assets/css/app.css`. The plugins copied the markup
 * across without the stylesheet, so twenty six wrappers in this plugin, the
 * add-on and the Local pack carried a class that matched no rule anywhere.
 * Every unit test passed, the hub's own "Every table sits in a scroller"
 * guard passed because it reads hub templates, and the tables still pushed
 * the page sideways. Found by looking at the Local pack's Business tab at
 * 1024, where it ran 23px past the viewport.
 *
 * WHAT IT CHECKS. Every class used as a table wrapper in this plugin's PHP is
 * matched against this plugin's stylesheet, and the rule has to carry
 * `overflow-x`. A class name is not a scroller.
 *
 * @package SolSEO
 */

$solseo_wrap_css = (string) file_get_contents( SOLSEO_PATH . 'assets/css/admin.css' );
$solseo_wrap_php = array();

$solseo_wrap_dir = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( SOLSEO_PATH . 'includes' ) );

foreach ( $solseo_wrap_dir as $solseo_wrap_file ) {
	if ( 'php' !== strtolower( $solseo_wrap_file->getExtension() ) ) {
		continue;
	}

	$solseo_wrap_text = (string) file_get_contents( $solseo_wrap_file->getPathname() );

	if ( preg_match_all( '/class="([^"]*\btable-wrap\b[^"]*)"/', $solseo_wrap_text, $solseo_wrap_hits ) ) {
		foreach ( $solseo_wrap_hits[1] as $solseo_wrap_attr ) {
			foreach ( preg_split( '/\s+/', trim( $solseo_wrap_attr ) ) as $solseo_wrap_class ) {
				if ( '' !== $solseo_wrap_class && false !== strpos( $solseo_wrap_class, 'table-wrap' ) ) {
					$solseo_wrap_php[ $solseo_wrap_class ] = true;
				}
			}
		}
	}
}

$solseo_wrap_names = array_keys( $solseo_wrap_php );

sort( $solseo_wrap_names );

solseo_assert(
	count( $solseo_wrap_names ) > 0,
	'the plugin wraps at least one table, so this guard has something to check'
);

$solseo_wrap_missing = array();

foreach ( $solseo_wrap_names as $solseo_wrap_class ) {
	$solseo_wrap_pattern = '/\.' . preg_quote( $solseo_wrap_class, '/' ) . '\s*(,[^{]*)?\{[^}]*overflow-x\s*:/';

	if ( ! preg_match( $solseo_wrap_pattern, $solseo_wrap_css ) ) {
		$solseo_wrap_missing[] = $solseo_wrap_class;
	}
}

solseo_assert_same(
	array(),
	$solseo_wrap_missing,
	'every table wrapper class in the markup has a rule in admin.css that scrolls it'
);
