<?php
/**
 * No em dash and no en dash anywhere in the plugin.
 *
 * The one allowed form is the HTML entity, which the separator list uses
 * because the character it stands for is a setting rather than prose.
 *
 * @package SolSEO
 */

$paths = array_merge(
	glob( SOLSEO_PATH . '*.{php,txt,css,js}', GLOB_BRACE ),
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' ),
	glob( SOLSEO_PATH . 'assets/*/*.{css,js}', GLOB_BRACE ),
	glob( SOLSEO_PATH . 'tests/*.php' )
);

$offenders = array();

foreach ( $paths as $path ) {
	$contents = file_get_contents( $path );

	if ( preg_match( '/\x{2014}|\x{2013}/u', $contents ) ) {
		$offenders[] = str_replace( SOLSEO_PATH, '', $path );
	}
}

solseo_assert( count( $paths ) > 40, 'the guard is reading the whole plugin' );
solseo_assert_same( array(), $offenders, 'no file holds an em dash or an en dash' );
