<?php
/**
 * What a real WordPress says about the Google Search Console connection.
 *
 * The unit suite proves the arithmetic against a fake WordPress. This proves
 * the wiring, and the wiring is where the risk is: a REST route that has to be
 * reachable with nobody signed in, a handshake that crosses two requests, a
 * token that has to come back out of the sealed store, and a screen rendered
 * by code that only exists inside wp-admin.
 *
 * Run by tests/playground/run.sh. Not shipped.
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

require_once '/wordpress/wp-load.php';

/*
 * A SCREEN ONLY EVER RENDERS INSIDE wp-admin, SO THE PROBE HAS TO BE THERE TOO.
 *
 * AUA1's probe took a fatal on its first run because it rendered a tab from a
 * front end context and submit_button() lives in wp-admin/includes/template.php
 * (D-125.8). The Connections screen calls it, so the same three includes are
 * loaded here before anything is drawn.
 */
require_once '/wordpress/wp-admin/includes/template.php';
require_once '/wordpress/wp-admin/includes/class-wp-screen.php';
require_once '/wordpress/wp-admin/includes/screen.php';
require_once '/wordpress/wp-admin/includes/plugin.php';

wp_set_current_user( 1 );

$home = home_url( '/' );
$host = (string) wp_parse_url( $home, PHP_URL_HOST );

/* ---- the plugin is on and the two new classes resolve ---- */

probe( 'the free plugin is loaded', class_exists( '\SolSEO\Admin\Tabs' ) );
probe( 'the Google connection is loaded', class_exists( '\SolSEO\Connect\Google' ) );
probe( 'the Search Console reader is loaded', class_exists( '\SolSEO\Connect\Search_Console' ) );

/* ---- nothing is connected to start with ---- */

$status = \SolSEO\Connect\Google::status();

probe( 'a fresh site is not connected to Google', false === $status['connected'] );
probe( 'and it is on the hosted path', 'hosted' === $status['mode'] );
probe( 'and the redirect URI it would register is its own REST route',
	false !== strpos( $status['redirect_uri'], '/solseo/v1/google/callback' ), $status['redirect_uri'] );

/* ---- the REST route really is registered, on a real REST server ---- */

$routes = rest_get_server()->get_routes();

probe( 'the callback route is registered', isset( $routes['/solseo/v1/google/callback'] ) );
probe( 'and so is the editor route', isset( $routes['/solseo/v1/search-console'] ) );

/*
 * ---- THE WHOLE HANDSHAKE, ACROSS TWO REQUESTS ----------------------------
 *
 * Mint the handshake the way the Connect button does, then hand the nonce and
 * a code to the REST route the way the relay's redirect does. Nothing here
 * calls finish() directly: the point is that the route is reachable, that its
 * permission callback lets a signed-out browser through, and that the
 * handshake stored in the first request is found in the second.
 */
$shake = \SolSEO\Connect\Google::begin_handshake( 'hosted' );
\SolSEO\Connect\Google::remember_handshake( $shake );

$consent = \SolSEO\Connect\Google::consent_url( $shake );

probe( 'the Connect button points at the relay',
	0 === strpos( $consent, 'https://solseo.com.au/api/v1/plugin/google/start?' ), $consent );
probe( 'and the verifier is not in the address', false === strpos( $consent, $shake['verifier'] ) );

/* Nobody is signed in when Google sends the browser back. */
wp_set_current_user( 0 );

$request = new WP_REST_Request( 'GET', '/solseo/v1/google/callback' );
$request->set_param( 'nonce', $shake['nonce'] );
$request->set_param( 'code', '4/0AProbeAuthorisationCode' );

$response = rest_do_request( $request );

probe( 'the callback answers a signed-out browser', 401 !== $response->get_status() && 403 !== $response->get_status(),
	'status ' . $response->get_status() );
probe( 'and sends it back into wp-admin', 302 === $response->get_status()
	&& false !== strpos( (string) ( $response->get_headers()['Location'] ?? '' ), 'page=solseo-settings' ),
	(string) ( $response->get_headers()['Location'] ?? '' ) );

wp_set_current_user( 1 );

/* ---- and the connection is now real ---- */

$status = \SolSEO\Connect\Google::status();

probe( 'the site is connected', true === $status['connected'] );
probe( 'and the property was matched without anybody choosing one',
	'sc-domain:' . $host === $status['property'], $status['property'] );
probe( 'and the screen is given a fingerprint rather than a token',
	8 === strlen( $status['fingerprint'] ) && false === strpos( wp_json_encode( $status ), '1//0g-freec1-probe' ) );

/*
 * AND THE TOKEN IS SEALED IN THE DATABASE, not merely absent from a screen.
 * Read straight out of wp_options, because this is the one thing a mocked
 * option store in the unit suite cannot honestly prove.
 */
global $wpdb;

$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options}", ARRAY_A );
$leaked = array();

foreach ( $rows as $row ) {
	foreach ( array( 'ya29.freec1-probe', '1//0g-freec1-probe' ) as $secret ) {
		if ( false !== strpos( (string) $row['option_value'], $secret ) ) {
			$leaked[] = $row['option_name'];
		}
	}
}

probe( 'NO OPTION IN THE DATABASE HOLDS A READABLE TOKEN', array() === $leaked, implode( ', ', array_unique( $leaked ) ) );

probe( 'and the sealed store can still open it',
	'ya29.freec1-probe' === \SolSEO\Connect\Google::access_token() );

probe( 'and the sealing is real rather than a fallback to nothing',
	'' !== \SolSEO\Connect\Keys::sealing(), \SolSEO\Connect\Keys::sealing() );

/* ---- the same nonce is not worth a second connection ---- */

$again = new WP_REST_Request( 'GET', '/solseo/v1/google/callback' );
$again->set_param( 'nonce', $shake['nonce'] );
$again->set_param( 'code', '4/0AReplayedCode' );

rest_do_request( $again );

probe( 'a replayed handshake is refused and changes nothing',
	'ya29.freec1-probe' === \SolSEO\Connect\Google::access_token() );

/* ---- the editor route answers with the page's own figures ---- */

$post_id = wp_insert_post(
	array(
		'post_title'   => 'Mongolian bow',
		'post_content' => 'A bow.',
		'post_status'  => 'publish',
		'post_type'    => 'post',
	)
);

$editor = new WP_REST_Request( 'GET', '/solseo/v1/search-console' );
$editor->set_param( 'post_id', $post_id );

$figures = rest_do_request( $editor )->get_data();

probe( 'the editor route says the site is connected', ! empty( $figures['connected'] ) );
probe( 'and carries no trouble', '' === ( $figures['trouble'] ?? 'missing' ), (string) ( $figures['trouble'] ?? 'missing' ) );
probe( 'and the clicks are the clicks', 41 === ( $figures['clicks'] ?? 0 ) );
probe( 'and the impressions are the impressions', 1820 === ( $figures['impressions'] ?? 0 ) );
probe( 'and twenty eight days is what it covers', 28 === ( $figures['days'] ?? 0 ) );
probe( 'and the queries came back too', 2 === count( $figures['queries'] ?? array() ) );
probe( 'and the range stops short of today, because Google is behind',
	! empty( $figures['to'] ) && $figures['to'] < gmdate( 'Y-m-d' ), (string) ( $figures['to'] ?? '' ) );

/* AND THE SECOND LOOK ASKS GOOGLE NOTHING. */
$before = count( $GLOBALS['solseo_freec1_asked'] );

rest_do_request( $editor );

probe( 'opening the same page again asks Google nothing',
	$before === count( $GLOBALS['solseo_freec1_asked'] ) );

/* ---- the Connections screen draws, inside the admin ---- */

set_current_screen( 'solseo_page_solseo-settings' );

ob_start();
\SolSEO\Admin\Connect_Screen::render();
$screen = ob_get_clean();

probe( 'the Connections screen renders', strlen( $screen ) > 500 );
probe( 'and it has the Google card on it', false !== strpos( $screen, 'Google Search Console' ) );
probe( 'and it offers the advanced path', false !== strpos( $screen, 'solseo_google_client_id' )
	&& false !== strpos( $screen, 'solseo_google_client_secret' ) );
probe( 'and it shows the redirect URI to register', false !== strpos( $screen, 'solseo-google-own-redirect' ) );
probe( 'and it names the read only scope',
	false !== strpos( $screen, 'webmasters.readonly' ) );
probe( 'AND IT NEVER PRINTS THE TOKEN',
	false === strpos( $screen, 'ya29.freec1-probe' ) && false === strpos( $screen, '1//0g-freec1-probe' ) );
probe( 'and the property picker holds what the account can see',
	false !== strpos( $screen, 'sc-domain:' . $host ) );

/* ---- every table on it is in a scroller (D-53.3) ---- */

$tables = preg_match_all( '/<table[^>]*class="[^"]*solseo-table/', $screen );
$wraps  = preg_match_all( '/<div class="table-wrap">/', $screen );

probe( 'every table on the screen sits in a scroller', $tables <= $wraps, $tables . ' tables, ' . $wraps . ' scrollers' );

/* ---- disconnecting stops everything in the same request ---- */

$asked_before = count( $GLOBALS['solseo_freec1_asked'] );

\SolSEO\Connect\Google::disconnect();

$after = \SolSEO\Connect\Google::status();

probe( 'disconnecting clears the connection', false === $after['connected'] );
probe( 'and there is no token left to read', '' === \SolSEO\Connect\Google::access_token() );
probe( 'and the sealed store no longer holds one', false === \SolSEO\Connect\Keys::has( \SolSEO\Connect\Google::REFRESH ) );

$revoked = array_slice( $GLOBALS['solseo_freec1_asked'], $asked_before );

probe( 'and Google was told to forget it, in the same request',
	1 === count( $revoked ) && 0 === strpos( $revoked[0]['url'], 'https://oauth2.googleapis.com/revoke' ),
	wp_json_encode( array_column( $revoked, 'url' ) ) );

$stale = \SolSEO\Connect\Search_Console::page( get_permalink( $post_id ) );

probe( 'and what was read is not shown to the next person', is_wp_error( $stale ) );

/*
 * ---- EVERY ADDRESS THE PLUGIN REACHED, LISTED --------------------------
 *
 * The readme's External services section names four, and this is the list a
 * reviewer would be reading it against. Anything here that is not one of them
 * is an undeclared destination.
 */
$hosts = array();

foreach ( $GLOBALS['solseo_freec1_asked'] as $one ) {
	$hosts[ (string) wp_parse_url( $one['url'], PHP_URL_HOST ) ] = true;
}

$hosts = array_keys( $hosts );
sort( $hosts );

probe(
	'the plugin reached exactly the hosts the readme declares',
	array( 'oauth2.googleapis.com', 'searchconsole.googleapis.com', 'solseo.com.au' ) === $hosts,
	implode( ', ', $hosts )
);

/*
 * ---- A REGEX REDIRECT SURVIVES A REAL DATABASE ROUND TRIP ---------------
 *
 * THIS IS THE ONLY PLACE THE FIX IS ACTUALLY PROVED. The unit suite watches
 * what save() hands a recorder, which is the right thing to assert and is
 * still a recorder. Here the rule goes into MySQL through $wpdb->insert and
 * comes back out through a SELECT, so the column type, the escaping and
 * everything else between the two is in the path (D-158.1).
 */
$typed = '^/old-shop/(.*)$';

$rule_id = \SolSEO\Redirects\Manager::save(
	array(
		'source'      => $typed,
		'target'      => '/shop/$1',
		'status_code' => 301,
		'match_type'  => 'regex',
		'enabled'     => 1,
	)
);

probe( 'a regex redirect saves', $rule_id > 0, (string) $rule_id );

$stored = $wpdb->get_row(
	$wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'solseo_redirects WHERE id = %d', (int) $rule_id ),
	ARRAY_A
);

probe( 'and the row comes back out of the database exactly as it was typed',
	is_array( $stored ) && $typed === (string) $stored['source'],
	is_array( $stored ) ? var_export( $stored['source'], true ) : 'no row' );

probe( 'and it is stored as a regex rule',
	is_array( $stored ) && 'regex' === (string) $stored['match_type'] );

/* AND IT MATCHES, which is the thing the customer was missing. */
$hit = \SolSEO\Redirects\Manager::match( '/old-shop/quivers' );

probe( 'and the stored rule matches the address it was written for',
	is_array( $hit ) && (int) $hit['id'] === (int) $rule_id,
	is_array( $hit ) ? 'matched' : 'no match' );

probe( 'and the capture reaches the target',
	is_array( $hit ) && '/shop/quivers' === (string) $hit['target'],
	is_array( $hit ) ? (string) $hit['target'] : '' );

/* AND AN EXACT RULE IS STILL PUT INTO ONE SHAPE. */
$exact_id = \SolSEO\Redirects\Manager::save(
	array(
		'source'      => 'old-page',
		'target'      => '/new-page',
		'status_code' => 301,
		'match_type'  => 'exact',
		'enabled'     => 1,
	)
);

$exact = $wpdb->get_row(
	$wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'solseo_redirects WHERE id = %d', (int) $exact_id ),
	ARRAY_A
);

probe( 'an exact rule typed without its slash is still normalised',
	is_array( $exact ) && '/old-page' === (string) $exact['source'],
	is_array( $exact ) ? (string) $exact['source'] : 'no row' );

probe( 'and it matches the address it was written for',
	is_array( \SolSEO\Redirects\Manager::match( '/old-page' ) ) );

/*
 * ---- AND THE REPAIR MOVES A BROKEN ROW AND ONLY A BROKEN ROW -----------
 *
 * Two rows planted straight into the table the way the bug would have left
 * them: one dead, one that was typed with its slash and works. The repair
 * must move the first and not the second, because the two are the same bytes
 * in the column and only the `/^` shape is provably dead (D-158.2).
 */
$wpdb->insert(
	$wpdb->prefix . 'solseo_redirects',
	array(
		'source'      => '/^/dead-shop/(.*)$',
		'target'      => '/shop/$1',
		'status_code' => 301,
		'match_type'  => 'regex',
		'enabled'     => 1,
		'created_at'  => current_time( 'mysql', true ),
	)
);

$dead_id = (int) $wpdb->insert_id;

$wpdb->insert(
	$wpdb->prefix . 'solseo_redirects',
	array(
		'source'      => '/live-shop/(.*)',
		'target'      => '/shop/$1',
		'status_code' => 301,
		'match_type'  => 'regex',
		'enabled'     => 1,
		'created_at'  => current_time( 'mysql', true ),
	)
);

$live_id = (int) $wpdb->insert_id;

probe( 'the dead rule matches nothing before the repair',
	null === \SolSEO\Redirects\Manager::match( '/dead-shop/quivers' ) );

$moved = \SolSEO\Redirects\Manager::repair_regex_rules();

probe( 'the repair changed exactly one row', 1 === (int) $moved, (string) $moved );

probe( 'and the dead rule now says what was typed',
	'^/dead-shop/(.*)$' === (string) $wpdb->get_var(
		$wpdb->prepare( 'SELECT source FROM ' . $wpdb->prefix . 'solseo_redirects WHERE id = %d', $dead_id )
	) );

probe( 'AND THE WORKING RULE WAS NOT TOUCHED',
	'/live-shop/(.*)' === (string) $wpdb->get_var(
		$wpdb->prepare( 'SELECT source FROM ' . $wpdb->prefix . 'solseo_redirects WHERE id = %d', $live_id )
	) );

probe( 'and the repaired rule matches now',
	is_array( \SolSEO\Redirects\Manager::match( '/dead-shop/quivers' ) ) );

probe( 'and the untouched one still does',
	is_array( \SolSEO\Redirects\Manager::match( '/live-shop/quivers' ) ) );

/*
 * ---- THE SCOPE SEAM IS THERE AND ASKS FOR NOTHING NEW ------------------
 *
 * The filter exists so Local and Shop can hang their scope on it the day the
 * Google app is published (D-159.1). Today nothing listens, and that is what
 * this asserts: on a real WordPress with a real filter system, the consent
 * screen is asked for one scope.
 */
probe( 'the scope list is the one scope with nothing listening',
	array( 'https://www.googleapis.com/auth/webmasters.readonly' ) === \SolSEO\Connect\Google::scopes() );

add_filter(
	'solseo_google_scopes',
	function ( $list ) {
		$list[] = 'https://www.googleapis.com/auth/drive';

		return $list;
	}
);

probe( 'and a pack can add one through the real filter system',
	in_array( 'https://www.googleapis.com/auth/drive', \SolSEO\Connect\Google::scopes(), true ) );

probe( 'and the read only scope is still first',
	'https://www.googleapis.com/auth/webmasters.readonly' === \SolSEO\Connect\Google::scopes()[0] );

remove_all_filters( 'solseo_google_scopes' );

probe( 'and taking the filter away puts it back to one',
	1 === count( \SolSEO\Connect\Google::scopes() ) );

/*
 * ---- AND THE BACKFILL IS UNBOOKED ON DEACTIVATION ----------------------
 *
 * solseo_links_backfill books itself with an argument, which is why
 * wp_clear_scheduled_hook() walked past it and a chain of single events
 * outlived the plugin that started it (D-158.3).
 */
wp_schedule_single_event( time() + 30, 'solseo_links_backfill', array( 0 ) );

probe( 'the backfill can be booked', false !== wp_next_scheduled( 'solseo_links_backfill', array( 0 ) ) );

/*
 * AND THE ESTATE GUARD CAN SEE IT, which is the loose end ProB2 reported and
 * the answer is neither of the two it offered. This is the same scan
 * plugin/tests/wave/probe.php runs against an allowlist of one name, so a
 * single event is as visible to it as a recurring one. That run passes only
 * because it creates no post and fires no upgrade, so the hook is never
 * booked while it is looking (D-162.2).
 */
$booked = array();

foreach ( (array) _get_cron_array() as $events ) {
	foreach ( array_keys( (array) $events ) as $hook ) {
		if ( 0 === strpos( (string) $hook, 'solseo' ) ) {
			$booked[ $hook ] = true;
		}
	}
}

probe( 'AND A WAVE STYLE SCAN OF THE LIVE CRON ARRAY SEES IT',
	isset( $booked['solseo_links_backfill'] ),
	implode( ', ', array_keys( $booked ) ) );

\SolSEO\Install::deactivate();

probe( 'AND DEACTIVATING UNBOOKS IT, argument and all',
	false === wp_next_scheduled( 'solseo_links_backfill', array( 0 ) ) );

/*
 * ---- AND NOTHING WAS WRITTEN TO debug.log ------------------------------
 *
 * WP_DEBUG_LOG is on in the blueprint. A notice on every request is what
 * D-124.3 is about, and three lanes shipped one on the same day by asking a
 * question before init.
 */
$log = '/wordpress/wp-content/debug.log';
$lines = is_file( $log ) ? array_filter( explode( "\n", (string) file_get_contents( $log ) ) ) : array();

probe( 'nothing wrote a notice, warning or deprecation to debug.log',
	array() === $lines, implode( ' | ', array_slice( $lines, 0, 4 ) ) );

file_put_contents(
	'/wordpress/review-out/freec1.txt',
	implode( "\n", $out ) . "\n\n" . $pass . ' passed, ' . $fail . " failed\n"
);
