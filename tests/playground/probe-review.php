<?php
/**
 * The WordPress.org review of solseo-2.0.0, answered inside a real WordPress.
 *
 * Every finding in that review is a claim about what happens on a running site:
 * a stylesheet that reaches the dashboard, a script tag a title can close, a
 * transform carrying its own CSS, an activation that is not clean. The unit
 * suite pins the code that decides each of those. This runs the decisions.
 *
 * TWO PHASES, BECAUSE is_admin() IS DECIDED BEFORE WordPress LOADS. The asset
 * gating only exists on an admin request, and the head output only exists on
 * one that is not, so the blueprint requires this file twice and each phase is
 * its own request. $solseo_phase says which.
 *
 * Run by tests/playground/run-review.sh. Not shipped.
 *
 * @package SolSEO
 */

// phpcs:disable

$out  = array();
$pass = 0;
$fail = 0;

function probe( $name, $ok, $detail = '' ) {
	global $out, $pass, $fail;

	if ( $ok ) {
		$pass++;
		$out[] = 'PASS ' . $name;
		return;
	}

	$fail++;
	$out[] = 'FAIL ' . $name . ( $detail ? ' :: ' . $detail : '' );
}

$phase = isset( $solseo_phase ) ? $solseo_phase : 'front';

require_once '/wordpress/wp-load.php';
require_once '/wordpress/wp-admin/includes/plugin.php';
require_once '/wordpress/wp-admin/includes/upgrade.php';

wp_set_current_user( 1 );

$plugin = '/wordpress/wp-content/plugins/solseo/';

/* ======================================================================== */
if ( 'front' === $phase ) :
/* ======================================================================== */

probe( 'the plugin is loaded', class_exists( '\SolSEO\Install' ) );
probe( 'and this is not an admin request', ! is_admin() );

/*
 * ---- THE ACTIVATION IS CLEAN -------------------------------------------
 *
 * "We installed your plugin in a clean WordPress and activated it, and the
 * activation did not go through cleanly." WP_DEBUG and WP_DEBUG_LOG are on in
 * the blueprint, and WP_DEBUG_DISPLAY is off, so a notice goes to the log
 * rather than into the response, which is exactly the shape of the problem:
 * output during activation makes WordPress warn about unexpected output and
 * can break plugins that have nothing to do with this one.
 */
$log = '/wordpress/wp-content/debug.log';

if ( is_file( $log ) ) {
	@unlink( $log );
}

ob_start();
\SolSEO\Install::activate();
$printed = (string) ob_get_clean();

probe( 'activation prints nothing at all', '' === $printed, strlen( $printed ) . ' bytes: ' . substr( $printed, 0, 200 ) );

$lines = is_file( $log ) ? array_values( array_filter( explode( "\n", (string) file_get_contents( $log ) ) ) ) : array();

probe( 'and writes no notice, warning or deprecation to debug.log',
	array() === $lines, implode( ' | ', array_slice( $lines, 0, 4 ) ) );

/*
 * ---- THE TABLES ARE MADE, WITH THIS SITE'S PREFIX ----------------------
 */
global $wpdb;

foreach ( array( 'redirects', 'not_found', 'links', 'crawl' ) as $short ) {
	$table = $wpdb->prefix . 'solseo_' . $short;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	probe( 'the ' . $short . ' table exists, at the site prefix', $table === $found, (string) $found );
}

probe( 'and nothing in the making of them errored', '' === (string) $wpdb->last_error, (string) $wpdb->last_error );

/*
 * ---- A TITLE CANNOT CLOSE THE STRUCTURED DATA --------------------------
 *
 * The review's finding: JSON_UNESCAPED_SLASHES permits a user-controlled
 * </script> sequence to terminate the JSON-LD script element and inject
 * markup. This is that sentence, run: a post whose title is the attack, its
 * own graph rendered by the plugin, and the element counted.
 */
$nasty = 'Closing tag: </script><img src=x onerror=alert(1)> and <!-- a comment -->';

$post_id = wp_insert_post(
	array(
		'post_title'   => $nasty,
		'post_content' => 'A page about ' . $nasty,
		'post_status'  => 'publish',
		'post_type'    => 'post',
	),
	true
);

probe( 'a post with a closing script tag in its title can be published', ! is_wp_error( $post_id ),
	is_wp_error( $post_id ) ? $post_id->get_error_message() : '' );

if ( ! is_wp_error( $post_id ) ) {
	$GLOBALS['wp_query']     = new WP_Query( array( 'p' => $post_id, 'post_type' => 'post' ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

	if ( $GLOBALS['wp_query']->have_posts() ) {
		$GLOBALS['wp_query']->the_post();
	}

	ob_start();
	\SolSEO\Frontend\Schema::render();
	$graph = (string) ob_get_clean();

	probe( 'the graph is rendered for that page', '' !== $graph, strlen( $graph ) . ' bytes' );

	probe( 'and it is one script element, opened once and closed once',
		1 === substr_count( $graph, '<script' ) && 1 === substr_count( $graph, '</script>' ),
		substr_count( $graph, '<script' ) . ' open, ' . substr_count( $graph, '</script>' ) . ' close' );

	/*
	 * The only angle brackets in the whole block are the four the script
	 * element itself is made of. Said that way round because "no <img" is a
	 * check against one payload and this is a check against all of them.
	 */
	$brackets = substr_count( $graph, '<' ) + substr_count( $graph, '>' );

	probe( 'the only angle brackets in the block are the script element\'s own',
		4 === $brackets, $brackets . ' found :: ' . $graph );

	$inner = '';

	if ( preg_match( '#<script[^>]*>(.*)</script>#s', $graph, $found ) ) {
		$inner = trim( $found[1] );
	}

	$parsed = json_decode( $inner, true );

	probe( 'what is inside the element is valid JSON', is_array( $parsed ), json_last_error_msg() );

	$found_title = '';

	if ( is_array( $parsed ) && ! empty( $parsed['@graph'] ) ) {
		foreach ( $parsed['@graph'] as $node ) {
			if ( isset( $node['name'] ) && false !== strpos( (string) $node['name'], 'Closing tag' ) ) {
				$found_title = (string) $node['name'];
			}

			if ( isset( $node['headline'] ) && false !== strpos( (string) $node['headline'], 'Closing tag' ) ) {
				$found_title = (string) $node['headline'];
			}
		}
	}

	probe( 'and the graph still describes that page', '' !== $found_title, $found_title );

	/*
	 * WHAT THE ESCAPING COSTS, WHICH IS NOTHING.
	 *
	 * This is the half that is easy to get wrong in the other direction. A
	 * plugin that answered the review by deleting angle brackets from every
	 * field would pass the count above and would be throwing away the
	 * customer's own words: a product called "12mm <-> 16mm adapter" would
	 * come out of the graph as something else, and nobody would notice until a
	 * rich result showed the wrong name.
	 *
	 * So: the payload typed into the body of that post is still in the
	 * document, whole, once a parser has read it. It is inert on the page
	 * because of how it is written, not because it is gone.
	 */
	$plain = '' === $inner ? '' : (string) json_encode( $parsed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	probe( 'and the words somebody typed survive the escaping, whole',
		'' !== $plain && false !== strpos( $plain, '</script>' ),
		substr( $plain, 0, 400 ) );

	wp_delete_post( $post_id, true );
}

/*
 * ---- THE SITEMAP TRANSFORM CARRIES NO STYLE BLOCK ----------------------
 */
$xsl = \SolSEO\Sitemaps\Writer::stylesheet();

probe( 'the sitemap transform holds no style block', false === stripos( $xsl, '<style' ), substr( $xsl, 0, 200 ) );

probe( 'and links a stylesheet file instead', false !== strpos( $xsl, 'assets/css/sitemap.css' ) );

probe( 'which is a real file in the plugin', is_file( $plugin . 'assets/css/sitemap.css' ) );

$link = '';

if ( preg_match( '#<link[^>]*href="([^"]+)"#', $xsl, $found ) ) {
	$link = html_entity_decode( $found[1], ENT_QUOTES );
}

probe( 'and the address it links is under this site\'s own plugin folder',
	'' !== $link && 0 === strpos( $link, plugins_url( '', $plugin . 'solseo.php' ) ), $link );

probe( 'and the transform is still well formed XML', false !== @simplexml_load_string( $xsl ) );

/*
 * ---- THE ADMIN PATHS IN robots.txt COME FROM WordPress -----------------
 */
$robots = \SolSEO\Frontend\Robots_Txt::preview( '' );
$ajax   = (string) wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH );

probe( 'robots.txt allows the ajax endpoint at the address this site actually has',
	false !== strpos( $robots, 'Allow: ' . $ajax ), $ajax . ' :: ' . str_replace( "\n", ' / ', $robots ) );

probe( 'and does not disallow the whole site', false === strpos( $robots, "Disallow: /\n" ),
	str_replace( "\n", ' / ', $robots ) );

/*
 * ---- AND NOTHING LEFT THE BUILDING ------------------------------------
 *
 * Networking is off in the blueprint, and the mu-plugin records every address
 * anything asked for. Guideline 7: activation must not contact a server.
 */
$asked = isset( $GLOBALS['solseo_review_asked'] ) ? (array) $GLOBALS['solseo_review_asked'] : array();

probe( 'nothing made an outbound request', array() === $asked, implode( ', ', $asked ) );

file_put_contents( '/wordpress/review-out/review-front.txt', implode( "\n", $out ) . "\n" . $pass . ' passed, ' . $fail . " failed\n" );

/* ======================================================================== */
else :
/* ======================================================================== */

require_once '/wordpress/wp-admin/includes/template.php';
require_once '/wordpress/wp-admin/includes/class-wp-screen.php';
require_once '/wordpress/wp-admin/includes/screen.php';

probe( 'this is an admin request', is_admin() );
probe( 'so the admin side booted', class_exists( '\SolSEO\Admin\Admin' ) && has_action( 'admin_enqueue_scripts' ) );

/*
 * ---- THE STYLESHEET GOES WHERE THERE IS SOMETHING TO STYLE -------------
 *
 * The review's finding: assets/css/admin.css is enqueued on every admin screen
 * and its :root rules leak SolSEO styling into the WordPress dashboard.
 *
 * Each case sets the screen WordPress would have set, fires the hook WordPress
 * would have fired, and asks the queue. `false` is the interesting column: a
 * screen this plugin draws nothing on must come back with nothing enqueued.
 */
$cases = array(
	array( 'options-general.php', 'options-general', array(), false, 'the general settings screen' ),
	array( 'plugins.php', 'plugins', array(), false, 'the plugins list' ),
	array( 'upload.php', 'upload', array(), false, 'the media library' ),
	array( 'users.php', 'users', array(), false, 'the users list' ),
	array( 'edit-comments.php', 'edit-comments', array(), false, 'the comments screen' ),
	array( 'themes.php', 'themes', array(), false, 'the themes screen' ),
	array( 'tools.php', 'tools', array(), false, 'the core tools screen' ),
	array( 'options-permalink.php', 'options-permalink', array(), false, 'the permalinks screen' ),
	array( 'edit.php', 'edit-post', array( 'post_type' => 'post' ), true, 'the posts list' ),
	array( 'post-new.php', 'post', array( 'post_type' => 'post' ), true, 'writing a post' ),
	array( 'term.php', 'edit-category', array( 'taxonomy' => 'category' ), true, 'editing a category' ),
	array( 'toplevel_page_solseo', 'toplevel_page_solseo', array(), true, 'our own screen' ),
);

foreach ( $cases as $case ) {
	list( $hook, $screen_id, $extra, $want, $says ) = $case;

	$styles        = wp_styles();
	$styles->queue = array();
	$styles->done  = array();

	set_current_screen( $screen_id );

	$screen = get_current_screen();

	foreach ( $extra as $key => $value ) {
		$screen->$key = $value;
	}

	do_action( 'admin_enqueue_scripts', $hook );

	$got = in_array( 'solseo-admin', (array) wp_styles()->queue, true );

	probe(
		'on ' . $says . ' the stylesheet is ' . ( $want ? 'loaded' : 'not loaded' ),
		$want === $got,
		'screen ' . $screen_id . ', post_type ' . (string) $screen->post_type . ', taxonomy ' . (string) $screen->taxonomy
			. ', queue: ' . implode( ', ', (array) wp_styles()->queue )
	);

	/*
	 * AND THE HANDLE EXISTS EVEN WHERE THE FILE IS NOT WANTED. The add-on and
	 * the packs name solseo-admin as a dependency of their own stylesheets, and
	 * WordPress drops an item whose dependency is not registered. A handle that
	 * only existed because this enqueued everywhere would have taken pro.css
	 * with it, silently, the day it stopped.
	 */
	probe( 'and on ' . $says . ' the handle is registered either way',
		wp_style_is( 'solseo-admin', 'registered' ) );
}

/*
 * ---- AND THE DASHBOARD ONLY GETS IT IF A PANEL IS ON IT ----------------
 *
 * Asked twice. Once with the widgets never registered, which is the state of a
 * user who cannot see the plugin, and once after wp_dashboard_setup() has run,
 * which is what wp-admin/index.php does before it loads the header that fires
 * the enqueue hook.
 */
$styles        = wp_styles();
$styles->queue = array();
$styles->done  = array();

set_current_screen( 'dashboard' );

probe( 'with no panel registered, the Dashboard is not asked to load it',
	false === \SolSEO\Admin\Dashboard_Widget::on_dashboard() );

do_action( 'admin_enqueue_scripts', 'index.php' );

probe( 'and nothing is enqueued on it',
	! in_array( 'solseo-admin', (array) wp_styles()->queue, true ),
	implode( ', ', (array) wp_styles()->queue ) );

require_once '/wordpress/wp-admin/includes/dashboard.php';

$styles        = wp_styles();
$styles->queue = array();
$styles->done  = array();

wp_dashboard_setup();

probe( 'once the Dashboard is set up, a panel of ours is on it',
	true === \SolSEO\Admin\Dashboard_Widget::on_dashboard() );

do_action( 'admin_enqueue_scripts', 'index.php' );

probe( 'and then the stylesheet loads',
	in_array( 'solseo-admin', (array) wp_styles()->queue, true ),
	implode( ', ', (array) wp_styles()->queue ) );

/*
 * ---- AND THE STYLESHEET ITSELF CANNOT REACH OUTSIDE OUR MARKUP ---------
 *
 * The file as it ships, read off the disk of a real install.
 */
$css = (string) file_get_contents( $plugin . 'assets/css/admin.css' );
$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

probe( 'the shipped stylesheet declares nothing on :root', false === strpos( $css, ':root' ) );

probe( 'and carries no unscoped table wrapper rule',
	0 === preg_match( '/(^|[},])\s*\.table-wrap/', $css ) );

$loose = array();

foreach ( preg_split( '/}\s*/', $css ) as $block ) {
	$open = strpos( $block, '{' );

	if ( false === $open ) {
		continue;
	}

	$selector = trim( substr( $block, 0, $open ) );

	if ( '' === $selector || '@' === $selector[0] ) {
		continue;
	}

	foreach ( explode( ',', $selector ) as $part ) {
		$part = trim( preg_replace( '/^@\w+[^{]*\{/', '', $part ) );

		if ( '' !== $part && false === strpos( $part, 'solseo' ) ) {
			$loose[ $part ] = true;
		}
	}
}

probe( 'and every rule in it names a SolSEO element or sits under one',
	array() === $loose, implode( ' | ', array_keys( $loose ) ) );

$log   = '/wordpress/wp-content/debug.log';
$lines = is_file( $log ) ? array_values( array_filter( explode( "\n", (string) file_get_contents( $log ) ) ) ) : array();

probe( 'and drawing all of that wrote nothing to debug.log', array() === $lines,
	implode( ' | ', array_slice( $lines, 0, 4 ) ) );

file_put_contents( '/wordpress/review-out/review-admin.txt', implode( "\n", $out ) . "\n" . $pass . ' passed, ' . $fail . " failed\n" );

endif;
