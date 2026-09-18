<?php
/**
 * Maps SolSEO\Some\Thing to includes/some/class-thing.php.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( $name ) {
		if ( 0 !== strpos( $name, 'SolSEO\\' ) ) {
			return;
		}

		$parts = explode( '\\', substr( $name, 7 ) );
		$file  = strtolower( str_replace( '_', '-', array_pop( $parts ) ) );
		$dir   = SOLSEO_PATH . 'includes/';

		if ( $parts ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}

		foreach ( array( 'class-', 'interface-' ) as $prefix ) {
			$path = $dir . $prefix . $file . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);
