<?php
/**
 * Nothing this plugin writes from can be reached without a nonce and a rights
 * check, and the one route that is open says in its own file why.
 *
 * WHY THIS IS A GUARD AND NOT A CONVENTION. The WordPress.org review of
 * solseo-2.0.0 asked for nonces and permission checks on anything that reads
 * $_GET, $_POST or $_REQUEST and then changes something. Every one of those in
 * this plugin already had both, and that is exactly the state a codebase is in
 * the day before somebody adds a handler in a hurry. A rule nothing checks is a
 * rule until it is not.
 *
 * WHAT IT CHECKS.
 *
 * 1. Every register_rest_route() call carries a permission_callback.
 * 2. None of them is '__return_true', except the OAuth callback, which is named
 *    here and has to be reachable by a browser coming back from Google.
 * 3. Every class that reads $_POST either goes through Screen::submitted(),
 *    which checks a nonce and a capability together, or checks both itself.
 * 4. Screen::submitted() still does both. It is the one function the first
 *    three quarters of this plugin's write paths rest on.
 *
 * @package SolSEO
 */

$solseo_gate_files = array_merge(
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' )
);

solseo_assert( count( $solseo_gate_files ) > 100, 'the guard is reading the whole plugin' );

/*
 * ---- EVERY ROUTE IS BEHIND SOMETHING ----------------------------------
 *
 * THE ALLOWLIST IS ONE ROUTE. Google sends a browser back to it with nobody
 * signed in, so it cannot ask WordPress who that is. What stands in for the
 * capability check is a single use handshake minted when somebody with the
 * capability pressed Connect, which expires in ten minutes and is deleted on
 * first use. See the comment above Google::register_rest(). A second entry
 * here needs a DECISIONS entry first.
 */
$solseo_open_routes = array( '/google/callback' );

$solseo_routes  = 0;
$solseo_ungated = array();
$solseo_open    = array();

foreach ( $solseo_gate_files as $solseo_gate_file ) {
	$solseo_body = (string) file_get_contents( $solseo_gate_file );
	$solseo_name = str_replace( SOLSEO_PATH, '', $solseo_gate_file );

	if ( false === strpos( $solseo_body, 'register_rest_route' ) ) {
		continue;
	}

	/*
	 * Each argument array handed to register_rest_route(), read as text from
	 * the call to the end of the statement. A route is registered with one
	 * array or with a list of them, and both shapes end at the same place.
	 */
	if ( ! preg_match_all( '/register_rest_route\((.*?)\n\t\t\);/s', $solseo_body, $solseo_calls ) ) {
		$solseo_ungated[] = $solseo_name . ': the guard could not read its route registration';

		continue;
	}

	foreach ( $solseo_calls[1] as $solseo_call ) {
		$solseo_methods = substr_count( $solseo_call, "'methods'" );
		$solseo_gates   = substr_count( $solseo_call, "'permission_callback'" );

		$solseo_routes += $solseo_methods;

		if ( $solseo_methods !== $solseo_gates ) {
			$solseo_ungated[] = $solseo_name . ': ' . $solseo_methods . ' methods, ' . $solseo_gates . ' permission callbacks';
		}

		if ( false === strpos( $solseo_call, '__return_true' ) ) {
			continue;
		}

		$solseo_excused = false;

		foreach ( $solseo_open_routes as $solseo_allowed ) {
			if ( false !== strpos( $solseo_call, $solseo_allowed ) ) {
				$solseo_excused = true;
			}
		}

		if ( ! $solseo_excused ) {
			$solseo_open[] = $solseo_name;
		}
	}
}

solseo_assert( $solseo_routes > 5, 'the guard found the plugin\'s routes' );

solseo_assert_same(
	array(),
	$solseo_ungated,
	'every method on every REST route carries a permission callback'
);

solseo_assert_same(
	array(),
	$solseo_open,
	'and none of them is open, apart from the Google callback named in this guard'
);

/*
 * ---- EVERY $_POST READER IS BEHIND A NONCE AND A CAPABILITY -----------
 *
 * Read per file rather than per handler, because a file that reads $_POST and
 * holds neither check is the shape of the mistake. A file that holds both is
 * checked by the tests for what it does with them.
 */
$solseo_unchecked = array();

foreach ( $solseo_gate_files as $solseo_gate_file ) {
	$solseo_body = solseo_code_only( (string) file_get_contents( $solseo_gate_file ) );
	$solseo_name = str_replace( SOLSEO_PATH, '', $solseo_gate_file );

	if ( false === strpos( $solseo_body, '$_POST' ) ) {
		continue;
	}

	$nonce = false !== strpos( $solseo_body, 'submitted(' )
		|| false !== strpos( $solseo_body, 'wp_verify_nonce' )
		|| false !== strpos( $solseo_body, 'check_admin_referer' )
		|| false !== strpos( $solseo_body, 'check_ajax_referer' );

	$rights = false !== strpos( $solseo_body, 'submitted(' )
		|| false !== strpos( $solseo_body, 'current_user_can' );

	if ( ! $nonce || ! $rights ) {
		$solseo_unchecked[] = $solseo_name . ( $nonce ? '' : ' (no nonce)' ) . ( $rights ? '' : ' (no capability)' );
	}
}

solseo_assert_same(
	array(),
	$solseo_unchecked,
	'every file that reads $_POST checks a nonce and a capability'
);

/*
 * ---- AND THE FUNCTION MOST OF THEM LEAN ON STILL DOES BOTH ------------
 */
$solseo_screen = solseo_without_comments( (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-screen.php' ) );

if ( preg_match( '/function submitted\(.*?\n\t\}/s', $solseo_screen, $solseo_found ) ) {
	solseo_assert(
		false !== strpos( $solseo_found[0], 'wp_verify_nonce' ),
		'Screen::submitted() verifies the nonce'
	);

	solseo_assert(
		false !== strpos( $solseo_found[0], 'current_user_can' ),
		'and checks the capability, in the same function, so neither can be forgotten on its own'
	);
} else {
	solseo_assert( false, 'the guard can read Screen::submitted()' );
}

/*
 * ---- A GET THAT CHANGES SOMETHING IS NONCED TOO -----------------------
 *
 * There are two, and both are links rather than forms: dismissing the conflict
 * notice, and the enable, disable and delete actions on a redirect row.
 */
$solseo_dismiss = solseo_code_only( (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-conflict-notice.php' ) );

solseo_assert(
	false !== strpos( $solseo_dismiss, 'check_admin_referer' ) && false !== strpos( $solseo_dismiss, 'current_user_can' ),
	'dismissing the conflict notice needs a nonce and the capability'
);

$solseo_redirects = solseo_code_only( (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-redirects-screen.php' ) );

solseo_assert(
	false !== strpos( $solseo_redirects, 'wp_verify_nonce' ) && false !== strpos( $solseo_redirects, 'current_user_can' ),
	'and so does acting on a redirect row from a link'
);
