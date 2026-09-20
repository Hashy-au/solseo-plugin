<?php
/**
 * Every class this plugin names can actually be found.
 *
 * PHP resolves an unimported name into the current namespace and says nothing
 * at all until the line runs. `Options::get()` inside `namespace
 * SolSEO\Indexing` is a call to `SolSEO\Indexing\Options`, which does not
 * exist, and the whole file loads, parses and passes review. The error arrives
 * on somebody's site, on the one branch nothing covered.
 *
 * CLAUDE.md names this as a hard rule and the hub has had a guard for it since
 * one missing import broke every site-list refresh. The free plugin did not,
 * and the first thing this test found was a missing `use SolSEO\Options;` in
 * the file that was being written when it was added.
 *
 * @package SolSEO
 */

$solseo_import_files = array_merge(
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' )
);

solseo_assert( count( $solseo_import_files ) > 40, 'the import guard is reading the whole plugin' );

/*
 * Names that belong to PHP or to WordPress and are written bare on purpose.
 * Everything else has to be imported, declared here, or live in this file's
 * own namespace.
 */
$solseo_global_names = array(
	'ABSPATH',
	'ArrayObject',
	'DateTime',
	'DateTimeZone',
	'DateTimeImmutable',
	'DOMDocument',
	'Exception',
	'InvalidArgumentException',
	'RuntimeException',
	'Throwable',
	'WP_Error',
	'WP_Post',
	'WP_Query',
	'WP_REST_Request',
	'WP_REST_Response',
	'WP_REST_Server',
	'WP_Term',
	'WP_User',
	'WP',
);

$solseo_unresolved = array();

foreach ( $solseo_import_files as $solseo_file ) {
	$solseo_raw = (string) file_get_contents( $solseo_file );

	// Comments and strings both hold class-shaped words that are not calls.
	$solseo_code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', ' ', $solseo_raw );
	$solseo_code = (string) preg_replace( '#\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"#s', "''", $solseo_code );

	$solseo_namespace = preg_match( '/^namespace\s+([^;]+);/m', $solseo_code, $solseo_found ) ? trim( $solseo_found[1] ) : '';

	$solseo_imported = array();

	if ( preg_match_all( '/^use\s+([^;]+);/m', $solseo_code, $solseo_uses ) ) {
		foreach ( $solseo_uses[1] as $solseo_use ) {
			$solseo_use = trim( $solseo_use );

			if ( preg_match( '/\s+as\s+(\w+)$/i', $solseo_use, $solseo_alias ) ) {
				$solseo_imported[] = $solseo_alias[1];
				continue;
			}

			$solseo_parts      = explode( '\\', $solseo_use );
			$solseo_imported[] = end( $solseo_parts );
		}
	}

	// What this file declares itself.
	if ( preg_match_all( '/^(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/m', $solseo_code, $solseo_own ) ) {
		$solseo_imported = array_merge( $solseo_imported, $solseo_own[1] );
	}

	// Every bare name used as a class: Name:: or new Name(.
	preg_match_all( '/(?<![\\\\$>\w])([A-Z]\w*)\s*::/', $solseo_code, $solseo_static );
	preg_match_all( '/\bnew\s+([A-Z]\w*)\s*\(/', $solseo_code, $solseo_made );

	$solseo_named = array_unique( array_merge( $solseo_static[1], $solseo_made[1] ) );

	foreach ( $solseo_named as $solseo_name ) {
		if ( in_array( $solseo_name, $solseo_global_names, true ) || in_array( $solseo_name, $solseo_imported, true ) ) {
			continue;
		}

		// A name with no import resolves into this file's own namespace, so
		// the class has to be there for the line to work.
		$solseo_target = '' === $solseo_namespace ? $solseo_name : $solseo_namespace . '\\' . $solseo_name;

		if ( class_exists( $solseo_target ) || interface_exists( $solseo_target ) ) {
			continue;
		}

		$solseo_unresolved[] = str_replace( '\\', '/', str_replace( SOLSEO_PATH, '', $solseo_file ) )
			. ' names ' . $solseo_name . ', which resolves to ' . $solseo_target . ' and is not there';
	}
}

sort( $solseo_unresolved );

solseo_assert_same( array(), $solseo_unresolved, 'every class reference resolves to a class that exists' );
