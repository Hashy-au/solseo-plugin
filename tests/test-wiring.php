<?php
/**
 * Every class file is where the autoloader expects it, and the classes the
 * bootstrap names all exist.
 *
 * @package SolSEO
 */

/**
 * The class name a file is expected to declare.
 *
 * @param string $file Absolute path.
 * @return string
 */
function solseo_expected_class( $file ) {
	$relative = str_replace( '\\', '/', substr( $file, strlen( SOLSEO_PATH . 'includes/' ) ) );
	$parts    = explode( '/', $relative );
	$name     = array_pop( $parts );
	$name     = preg_replace( '/^(class|interface)-/', '', basename( $name, '.php' ) );

	$namespace = array_map(
		function ( $part ) {
			return implode( '_', array_map( 'ucfirst', explode( '-', $part ) ) );
		},
		$parts
	);

	$class = implode( '_', array_map( 'ucfirst', explode( '-', $name ) ) );

	return 'SolSEO\\' . ( $namespace ? implode( '\\', $namespace ) . '\\' : '' ) . $class;
}

$files = array_merge(
	glob( SOLSEO_PATH . 'includes/class-*.php' ),
	glob( SOLSEO_PATH . 'includes/*/class-*.php' )
);

solseo_assert( count( $files ) > 20, 'the plugin has its class files' );

foreach ( $files as $file ) {
	$class = solseo_expected_class( $file );

	solseo_assert( class_exists( $class ), 'the autoloader finds ' . $class . ' for ' . basename( $file ) );
}

$named = array(
	'SolSEO\\Meta',
	'SolSEO\\Breadcrumbs',
	'SolSEO\\Install',
	'SolSEO\\Options',
	'SolSEO\\Rest',
	'SolSEO\\Score_Keeper',
	'SolSEO\\Score_Report',
	'SolSEO\\Redirects\\Manager',
	'SolSEO\\Redirects\\Log',
	'SolSEO\\Hub\\Connection',
	'SolSEO\\Hub\\Client',
	'SolSEO\\Sitemaps\\Controller',
	'SolSEO\\Sitemaps\\Writer',
	'SolSEO\\Frontend\\Head',
	'SolSEO\\Frontend\\Schema',
	'SolSEO\\Frontend\\Robots_Txt',
	'SolSEO\\Admin\\Admin',
	'SolSEO\\Admin\\Menu',
	'SolSEO\\Admin\\Metabox',
	'SolSEO\\Tools\\Import',
	'SolSEO\\Tools\\Alt_Text',
);

foreach ( $named as $class ) {
	solseo_assert( class_exists( $class ), $class . ' is loadable' );
}

foreach ( \SolSEO\Admin\Menu::screens() as $slug => $screen ) {
	solseo_assert( class_exists( $screen['screen'] ), $slug . ' has a screen class' );
	solseo_assert( is_callable( array( $screen['screen'], 'render' ) ), $slug . ' can be rendered' );
}

foreach ( array_keys( \SolSEO\Admin\Menu::screens() ) as $slug ) {
	$view = str_replace( 'solseo-', '', $slug );

	solseo_assert( '' !== $view, 'screen ' . $slug . ' has a slug' );
}
