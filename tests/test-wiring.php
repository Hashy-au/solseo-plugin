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

/*
 * THE TWO SEAMS THE ADD-ON HOOKS. An add-on ships and updates separately, so a
 * seam quietly renamed here is a paid feature that stops appearing on
 * somebody's site with nothing in any log to say why.
 */
$solseo_dashboard_src = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-dashboard-screen.php' );

solseo_assert(
	false !== strpos( $solseo_dashboard_src, "do_action( 'solseo_dashboard_panels' )" ),
	'the dashboard offers a place to add a panel'
);

$solseo_connection_src = (string) file_get_contents( SOLSEO_PATH . 'includes/hub/class-connection.php' );

solseo_assert(
	false !== strpos( $solseo_connection_src, "do_action( 'solseo_hub_synced', self::summary() )" ),
	'a finished sync says so'
);

solseo_assert(
	false !== strpos( $solseo_connection_src, "do_action( 'solseo_hub_disconnected' )" ),
	'and so does losing the connection'
);

/*
 * AND IT HANDS OVER summary(), WHICH IS THE STORED CONNECTION WITHOUT THE KEY.
 * A hook is a public address: anything at all can subscribe to it and send what
 * it is given anywhere it likes. $stored holds the pairing key.
 */
solseo_assert(
	false === strpos( $solseo_connection_src, "do_action( 'solseo_hub_synced', \$stored" ),
	'and it never hands the key to whatever is listening'
);

/*
 * SITE HEALTH IS REGISTERED OUTSIDE THE ADMIN BRANCH. WordPress runs the direct
 * tests from a weekly cron event to fill the count on its own dashboard widget,
 * and that request is not an admin request. Registered inside the branch, the
 * tests exist on the screen and the widget counts nothing.
 */
$solseo_boot = (string) file_get_contents( SOLSEO_PATH . 'includes/class-plugin.php' );

solseo_assert(
	false !== strpos( $solseo_boot, 'Health\Site_Health::init()' ),
	'Site Health is booted'
);

solseo_assert(
	strpos( $solseo_boot, 'Health\Site_Health::init()' ) < strpos( $solseo_boot, 'if ( is_admin() ) {' ),
	'and it is booted before the admin branch, because a cron request is not an admin request'
);
