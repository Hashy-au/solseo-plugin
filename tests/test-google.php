<?php
/**
 * The Google Search Console connection (FreeC1, D-128.x).
 *
 * Three promises are made about this feature in places a person outside this
 * repository reads: the readme's External services section, which a
 * wordpress.org reviewer takes as the whole list; the privacy policy on
 * solseo.com.au, which a Google reviewer reads for the Limited Use
 * affirmation; and the sentence on the Connections screen that says a
 * customer can cut us out entirely.
 *
 * Every check in this file exists to keep one of those three true.
 *
 * @package SolSEO
 */

use SolSEO\Connect\Google;
use SolSEO\Connect\Keys;
use SolSEO\Connect\Search_Console;

$solseo_google_source = (string) file_get_contents( SOLSEO_PATH . 'includes/connect/class-google.php' );
$solseo_sc_source     = (string) file_get_contents( SOLSEO_PATH . 'includes/connect/class-search-console.php' );

/*
 * ONE SCOPE, AND IT IS THE READ ONLY ONE.
 *
 * docs/GOOGLE-APP-VERIFICATION.md submits a justification saying
 * webmasters.readonly is the narrowest scope that does this job and that
 * nothing else is asked for. Business Profile belongs to LocalB1 and Merchant
 * Center to ShopB3, and Google's own requirements say not to ask for a scope
 * whose feature is not built. Adding one to a verified app triggers a fresh
 * review of the whole app, so this is not a line somebody can add cheaply.
 */
solseo_assert_same(
	'https://www.googleapis.com/auth/webmasters.readonly',
	Google::SCOPE,
	'the plugin asks for the read only Search Console scope'
);

foreach ( array(
	'the writable Search Console scope' => '~auth/webmasters(?![.\w])~',
	'Business Profile'                  => '~business\.manage~',
	'Merchant Center'                   => '~auth/content~',
) as $solseo_what => $solseo_pattern ) {
	solseo_assert(
		0 === preg_match( $solseo_pattern, $solseo_google_source . $solseo_sc_source ),
		'and nothing in the connection asks for ' . $solseo_what
	);
}

/*
 * THE RELAY IS REACHED AT THE TWO ADDRESSES THE CONTRACT DECLARES, and at no
 * other. An invented route is a 404 on every customer's first connection with
 * nothing on this side to say why, and docs/API-CONTRACT.md is the only place
 * those two strings are agreed.
 */
solseo_assert_same(
	'https://solseo.com.au/api/v1/plugin/google/start',
	Google::RELAY_START,
	'the hosted path starts at the route the contract declares'
);

solseo_assert_same(
	'https://solseo.com.au/api/v1/plugin/google/token',
	Google::RELAY_TOKEN,
	'and exchanges at the other one'
);

solseo_assert_same(
	'/solseo/v1/google/callback',
	Google::REST_ROUTE,
	'and the plugin lands the browser back on the route the hub is told to accept'
);

/*
 * ---- PKCE, WHICH IS WHAT MAKES THE RELAY SAFE ----------------------------
 *
 * The hub sends a browser back to an address the plugin named, and the hub
 * cannot know whether that address really belongs to the site connecting. So
 * the code has to be useless to anybody who catches it. The verifier stays on
 * this server and only its S256 hash travels.
 */
$solseo_handshake = Google::begin_handshake( 'hosted' );

solseo_assert(
	1 === preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $solseo_handshake['verifier'] ),
	'a handshake mints a PKCE verifier of the length RFC 7636 allows'
);

solseo_assert_same(
	rtrim( strtr( base64_encode( hash( 'sha256', $solseo_handshake['verifier'], true ) ), '+/', '-_' ), '=' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 7636 says base64url, and computing the expected challenge here rather than calling the code under test is the point.
	$solseo_handshake['challenge'],
	'and the challenge is its S256 hash, not the verifier itself'
);

solseo_assert(
	1 === preg_match( '/^[A-Za-z0-9]{16,64}$/', $solseo_handshake['nonce'] ),
	'and a nonce the plugin can recognise on the way back'
);

$solseo_second = Google::begin_handshake( 'hosted' );

solseo_assert(
	$solseo_second['verifier'] !== $solseo_handshake['verifier']
		&& $solseo_second['nonce'] !== $solseo_handshake['nonce'],
	'and two handshakes are never the same handshake'
);

/*
 * AND THE VERIFIER NEVER TRAVELS TWICE. It is kept for one exchange and then
 * forgotten, so a code replayed at the relay has nothing to be checked with.
 */
Google::remember_handshake( $solseo_second );

solseo_assert(
	Google::take_handshake( $solseo_second['nonce'] ) !== null,
	'the handshake comes back once for the exchange'
);

solseo_assert(
	null === Google::take_handshake( $solseo_second['nonce'] ),
	'and never a second time'
);

solseo_assert(
	null === Google::take_handshake( 'someoneelsesnonce123' ),
	'and a nonce that is not the one we sent is nobody we know'
);

/*
 * ---- THE TWO PATHS, AND WHAT EACH ONE CONTACTS ---------------------------
 *
 * D-75.3: the advanced path is what makes the declaration in the readme read
 * as a convenience rather than a condition, and the privacy policy on
 * solseo.com.au promises in as many words that it exists. A guard on the hub
 * fails the build if that sentence goes; this is the other half.
 */
Keys::forget( Google::OWN_SECRET );
delete_option( Google::OPTION );

solseo_assert_same( 'hosted', Google::mode(), 'with nothing pasted, the connection goes through our relay' );

$solseo_hosted_url = Google::consent_url( $solseo_handshake );

solseo_assert(
	0 === strpos( $solseo_hosted_url, 'https://solseo.com.au/api/v1/plugin/google/start?' ),
	'and the Connect button sends the browser to the relay'
);

foreach ( array(
	'challenge=' . $solseo_handshake['challenge'],
	'nonce=' . $solseo_handshake['nonce'],
	rawurlencode( '/solseo/v1/google/callback' ),
) as $solseo_needle ) {
	solseo_assert(
		false !== strpos( $solseo_hosted_url, $solseo_needle ),
		'and it carries ' . $solseo_needle
	);
}

solseo_assert(
	false === strpos( $solseo_hosted_url, $solseo_handshake['verifier'] ),
	'AND THE VERIFIER IS NOT IN IT'
);

Google::save_own_app( '123456-mine.apps.googleusercontent.com', 'GOCSPX-mine' );

solseo_assert_same( 'own', Google::mode(), 'pasting a client id and secret switches the connection over' );

$solseo_own_url = Google::consent_url( $solseo_handshake );

solseo_assert(
	0 === strpos( $solseo_own_url, 'https://accounts.google.com/o/oauth2/v2/auth?' ),
	'and then the browser goes straight to Google'
);

solseo_assert(
	false === strpos( $solseo_own_url, 'solseo.com.au' ),
	'WITH NOTHING OF OURS IN THE LOOP'
);

solseo_assert(
	false !== strpos( $solseo_own_url, rawurlencode( '123456-mine.apps.googleusercontent.com' ) )
		|| false !== strpos( $solseo_own_url, '123456-mine.apps.googleusercontent.com' ),
	'and it is their client id being used, not ours'
);

/* AND THE EXCHANGE GOES TO GOOGLE TOO, not through us. */
solseo_test_http_reset();
solseo_test_http_next(
	200,
	array(
		'access_token'  => 'ya29.own',
		'expires_in'    => 3599,
		'refresh_token' => '1//0gown',
		'scope'         => Google::SCOPE,
		'token_type'    => 'Bearer',
	)
);

Google::remember_handshake( $solseo_handshake );
$solseo_done = Google::finish( $solseo_handshake['nonce'], '4/0Aown' );

solseo_assert( ! is_wp_error( $solseo_done ), 'the advanced path completes an exchange' );

$solseo_sent = solseo_test_http_sent();

solseo_assert_same( 1, count( $solseo_sent ), 'and it took exactly one request to do it' );

solseo_assert_same(
	'https://oauth2.googleapis.com/token',
	$solseo_sent[0]['url'],
	'and that request went to Google and not to solseo.com.au'
);

solseo_assert(
	isset( $solseo_sent[0]['args']['body']['client_secret'] )
		&& 'GOCSPX-mine' === $solseo_sent[0]['args']['body']['client_secret'],
	'carrying their own secret'
);

/*
 * ---- WHAT IS STORED, AND WHERE --------------------------------------------
 *
 * Every credential in this plugin goes through Keys, which seals with
 * sodium_crypto_secretbox and falls back to AES-256-GCM. A second store would
 * be a second thing to audit and a second thing to get wrong, and the whole
 * point of the sealing is defeated by one copy left in an option beside it.
 */
$solseo_leaked = array();

foreach ( (array) $GLOBALS['solseo_test_options'] as $solseo_name => $solseo_value ) {
	if ( Keys::OPTION === $solseo_name ) {
		continue;
	}

	$solseo_flat = wp_json_encode( $solseo_value );

	foreach ( array( 'ya29.own', '1//0gown', 'GOCSPX-mine' ) as $solseo_secret ) {
		if ( false !== strpos( (string) $solseo_flat, $solseo_secret ) ) {
			$solseo_leaked[] = $solseo_name . ' holds ' . substr( $solseo_secret, 0, 4 ) . '...';
		}
	}
}

solseo_assert_same( array(), $solseo_leaked, 'no token and no secret is stored anywhere but the sealed store' );

$solseo_sealed = wp_json_encode( get_option( Keys::OPTION ) );

foreach ( array( 'ya29.own', '1//0gown', 'GOCSPX-mine' ) as $solseo_secret ) {
	solseo_assert(
		false === strpos( (string) $solseo_sealed, $solseo_secret ),
		'and the sealed store holds no readable copy of ' . substr( $solseo_secret, 0, 4 ) . '...'
	);
}

/* AND THE SCREEN SHOWS A FINGERPRINT AND A HINT, never the thing itself. */
$solseo_status = Google::status();

solseo_assert( true === $solseo_status['connected'], 'the screen is told the connection is live' );

solseo_assert(
	8 === strlen( $solseo_status['fingerprint'] ) && 4 === strlen( $solseo_status['hint'] ),
	'and it gets a fingerprint and a hint'
);

$solseo_shown = wp_json_encode( $solseo_status );

foreach ( array( 'ya29.own', '1//0gown', 'GOCSPX-mine' ) as $solseo_secret ) {
	solseo_assert(
		false === strpos( (string) $solseo_shown, $solseo_secret ),
		'and never the credential itself'
	);
}

/*
 * ---- DISCONNECTING STOPS EVERYTHING IN THE SAME REQUEST -------------------
 *
 * Not on the next page load, not when a cron runs. The customer pressed the
 * button because they want it to stop.
 */
solseo_test_http_reset();
solseo_test_http_next( 200, '' );

Google::disconnect();

solseo_assert( false === Google::status()['connected'], 'disconnecting clears the connection' );
solseo_assert( '' === Google::access_token(), 'and there is no token left to read' );
solseo_assert( false === Keys::has( Google::REFRESH ), 'and the sealed refresh token is gone' );

$solseo_revoked = solseo_test_http_sent();

solseo_assert(
	1 === count( $solseo_revoked ) && 0 === strpos( $solseo_revoked[0]['url'], 'https://oauth2.googleapis.com/revoke' ),
	'and Google is told to forget the permission, in the same request'
);

/*
 * AND A REVOKE THAT FAILS STILL DISCONNECTS. Google being unreachable is not
 * a reason to leave a token on somebody's server after they asked for it to
 * go. The half we control happens either way.
 */
Keys::set( Google::REFRESH, '1//0gsecond' );
update_option(
	Google::OPTION,
	array(
		'connected_at' => 1,
		'property'     => 'sc-domain:example.com.au',
	)
);

solseo_test_http_reset();
solseo_test_http_next( 0, '', 'the network is down' );

Google::disconnect();

solseo_assert( false === Keys::has( Google::REFRESH ), 'a revoke that fails still clears the token here' );

/*
 * ---- MATCHING A PROPERTY TO THIS SITE -------------------------------------
 *
 * home_url() in the test bootstrap is https://asiaticbows.com.au.
 */
$solseo_properties = array(
	array(
		'siteUrl'         => 'sc-domain:example.com',
		'permissionLevel' => 'siteOwner',
	),
	array(
		'siteUrl'         => 'https://asiaticbows.com.au/',
		'permissionLevel' => 'siteFullUser',
	),
	array(
		'siteUrl'         => 'sc-domain:asiaticbows.com.au',
		'permissionLevel' => 'siteOwner',
	),
);

solseo_assert_same(
	'sc-domain:asiaticbows.com.au',
	Search_Console::best_match( 'https://asiaticbows.com.au/', $solseo_properties ),
	'a domain property beats a URL prefix, because it covers both www and plain'
);

solseo_assert_same(
	'https://asiaticbows.com.au/',
	Search_Console::best_match(
		'https://asiaticbows.com.au/',
		array(
			array(
				'siteUrl'         => 'https://asiaticbows.com.au/',
				'permissionLevel' => 'siteOwner',
			),
		)
	),
	'and a URL prefix is taken when that is all there is'
);

solseo_assert_same(
	'',
	Search_Console::best_match(
		'https://asiaticbows.com.au/',
		array(
			array(
				'siteUrl'         => 'sc-domain:someone-else.com',
				'permissionLevel' => 'siteOwner',
			),
		)
	),
	'and nothing is matched to a property that is not this site'
);

/*
 * AND A SITE WITH NO MATCH IS TOLD WHICH PROPERTIES THE ACCOUNT CAN SEE.
 * "No data" on a screen somebody just connected is the failure this avoids:
 * the usual cause is that the property is the www one, or the http one, and
 * the list says so at a glance.
 */
$solseo_none = Search_Console::unmatched_sentence(
	'https://asiaticbows.com.au/',
	array(
		array(
			'siteUrl'         => 'sc-domain:someone-else.com',
			'permissionLevel' => 'siteOwner',
		),
	)
);

solseo_assert(
	false !== strpos( $solseo_none, 'someone-else.com' ),
	'the unmatched message names what the account can see'
);

solseo_assert(
	'' !== Search_Console::unmatched_sentence( 'https://asiaticbows.com.au/', array() ),
	'and an account with no properties at all still gets a sentence'
);

/*
 * ---- FAILURE STATES ARE SENTENCES, AND NONE OF THEM DRAWS AN EMPTY CHART --
 *
 * Every one of these was a real Search Console response shape. A paraphrase
 * hides the sentence that says what to do about it.
 */
$solseo_failures = array(
	array(
		401,
		array(
			'error' => array(
				'status'  => 'UNAUTHENTICATED',
				'message' => 'Invalid Credentials',
			),
		),
		'revoked',
	),
	array(
		403,
		array(
			'error' => array(
				'status'  => 'PERMISSION_DENIED',
				'message' => 'User does not have sufficient permission for site',
			),
		),
		'permission',
	),
	array(
		429,
		array(
			'error' => array(
				'status'  => 'RESOURCE_EXHAUSTED',
				'message' => 'Quota exceeded',
			),
		),
		'quota',
	),
);

foreach ( $solseo_failures as $solseo_case ) {
	list( $solseo_code, $solseo_body, $solseo_expected ) = $solseo_case;

	solseo_assert_same(
		$solseo_expected,
		Search_Console::classify( $solseo_code, $solseo_body ),
		'a ' . $solseo_code . ' from Google is read as ' . $solseo_expected
	);

	$solseo_error = Search_Console::refusal( $solseo_code, $solseo_body );

	solseo_assert(
		is_wp_error( $solseo_error ) && strlen( $solseo_error->get_error_message() ) > 30,
		'and it comes back as a sentence rather than a code'
	);
}

solseo_assert(
	false !== strpos(
		Search_Console::refusal(
			429,
			array(
				'error' => array(
					'status'  => 'RESOURCE_EXHAUSTED',
					'message' => 'Quota exceeded',
				),
			)
		)->get_error_message(),
		'midnight'
	),
	'and a quota refusal says when the allowance comes back'
);

solseo_assert_same( null, Search_Console::classify( 200, array( 'rows' => array() ) ), 'a good answer is not a failure' );

/*
 * A REVOKED TOKEN MARKS THE CONNECTION, so the screen says revoked rather
 * than showing zeros for ever. The first 401 is the only warning there is.
 */
Keys::set( Google::REFRESH, '1//0gthird' );
update_option(
	Google::OPTION,
	array(
		'connected_at' => 1,
		'property'     => 'sc-domain:asiaticbows.com.au',
	)
);

Google::mark_revoked();

solseo_assert( true === Google::status()['revoked'], 'a revoked connection says revoked' );
solseo_assert( false === Google::status()['connected'], 'and stops calling itself connected' );

/*
 * ---- NO SEARCH CONSOLE DATA LEAVES THE SITE -------------------------------
 *
 * The readme says the figures are shown in the customer's own admin and sent
 * nowhere. The plugin has one file that posts to solseo.com.au on a paired
 * site, and one that relays the OAuth handshake, and neither has any business
 * holding a Search Console row.
 */
$solseo_reaches_hub = array();

foreach (
	array_merge(
		glob( SOLSEO_PATH . 'includes/*.php' ),
		glob( SOLSEO_PATH . 'includes/*/*.php' ),
		glob( SOLSEO_PATH . 'includes/*/*/*.php' )
	) as $solseo_file
) {
	/*
	 * WITHOUT THE PROSE, AND WITH THE STRINGS LEFT IN. The class this guard
	 * protects has a header explaining that it must not be on this list, and
	 * reading the prose would put it on it. solseo_code_only() is not the
	 * tool: it drops string literals too, which is exactly where an address
	 * lives, and using it here made this guard pass with an empty list.
	 */
	$solseo_text = '';

	foreach ( token_get_all( (string) file_get_contents( $solseo_file ) ) as $solseo_token ) {
		if ( ! is_array( $solseo_token ) ) {
			$solseo_text .= $solseo_token;

			continue;
		}

		$solseo_text .= in_array( $solseo_token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? ' ' : $solseo_token[1];
	}

	if ( ! preg_match( '/\bwp_(safe_)?remote_(request|get|post|head)\s*\(/', $solseo_text ) ) {
		continue;
	}

	if ( false === strpos( $solseo_text, 'solseo.com.au' ) ) {
		continue;
	}

	$solseo_reaches_hub[] = str_replace( '\\', '/', str_replace( SOLSEO_PATH, '', $solseo_file ) );
}

sort( $solseo_reaches_hub );

solseo_assert_same(
	array( 'includes/connect/class-google.php', 'includes/hub/class-client.php' ),
	$solseo_reaches_hub,
	'two files contact solseo.com.au, and the Search Console reader is not one of them'
);

/*
 * AND THE ONE THAT RELAYS THE HANDSHAKE SENDS ONLY HANDSHAKE FIELDS. A grep
 * for "gsc" would pass over a body built from a variable, so the bodies are
 * read from the call itself: a real exchange is driven and every key it sent
 * is checked against the list the contract declares.
 */
Keys::forget( Google::OWN_SECRET );
delete_option( Google::OPTION );

$solseo_relay_shake = Google::begin_handshake( 'hosted' );
Google::remember_handshake( $solseo_relay_shake );

solseo_test_http_reset();
solseo_test_http_next(
	200,
	array(
		'access_token'  => 'ya29.relayed',
		'expires_in'    => 3599,
		'refresh_token' => '1//0grelayed',
		'scope'         => Google::SCOPE,
		'token_type'    => 'Bearer',
	)
);

Google::finish( $solseo_relay_shake['nonce'], '4/0Arelayed' );

$solseo_relay_sent = solseo_test_http_sent();

solseo_assert_same(
	'https://solseo.com.au/api/v1/plugin/google/token',
	$solseo_relay_sent[0]['url'],
	'the hosted exchange goes to the relay'
);

solseo_assert_same(
	array( 'code', 'code_verifier', 'grant' ),
	( static function ( $solseo_body ) {
		$solseo_keys = array_keys( (array) json_decode( (string) $solseo_body, true ) );
		sort( $solseo_keys );

		return $solseo_keys;
	} )( $solseo_relay_sent[0]['args']['body'] ),
	'and it sends the grant, the code and the verifier and nothing else at all'
);

/*
 * ---- THE TWENTY EIGHT DAYS FOR ONE PAGE -----------------------------------
 */
update_option(
	Google::OPTION,
	array(
		'connected_at' => 1,
		'property'     => 'sc-domain:asiaticbows.com.au',
		'access_token' => '',
		'expires_at'   => 0,
	)
);

Keys::set( Google::ACCESS, 'ya29.stillgood' );
Keys::set( Google::REFRESH, '1//0gstillgood' );
Google::set_expiry( time() + 3000 );

solseo_test_http_reset();
solseo_test_http_next(
	200,
	array(
		'rows' => array(
			array(
				'clicks'      => 41,
				'impressions' => 1820,
				'ctr'         => 0.0225,
				'position'    => 12.4,
			),
		),
	)
);
solseo_test_http_next(
	200,
	array(
		'rows' => array(
			array(
				'keys'        => array( 'mongolian bow' ),
				'clicks'      => 22,
				'impressions' => 610,
				'ctr'         => 0.036,
				'position'    => 8.1,
			),
			array(
				'keys'        => array( 'horsebow australia' ),
				'clicks'      => 11,
				'impressions' => 420,
				'ctr'         => 0.026,
				'position'    => 14.9,
			),
		),
	)
);

$solseo_page = Search_Console::page( 'https://asiaticbows.com.au/mongolian-bow/' );

solseo_assert( ! is_wp_error( $solseo_page ), 'a connected site can read one page\'s figures' );
solseo_assert_same( 41, $solseo_page['clicks'], 'and the clicks are the clicks' );
solseo_assert_same( 1820, $solseo_page['impressions'], 'and the impressions are the impressions' );
solseo_assert_same( 12.4, $solseo_page['position'], 'and the position is the position' );
solseo_assert_same( 28, $solseo_page['days'], 'over twenty eight days' );
solseo_assert_same( 2, count( $solseo_page['queries'] ), 'with the queries that brought them' );
solseo_assert_same( 'mongolian bow', $solseo_page['queries'][0]['query'], 'best first' );

$solseo_page_sent = solseo_test_http_sent();

solseo_assert_same( 2, count( $solseo_page_sent ), 'it takes two calls: the totals and the queries' );

/*
 * AND THE READ IS ANNOUNCED, ONCE, WITH THE ADDRESS AND THE FIGURES (D-172.4).
 * The Shop pack's impressions listener waits on this hook. A read that came
 * from Google fires it; the transient hit below does not, because nothing
 * new was read.
 */
$solseo_announced = solseo_test_actions_fired( 'solseo_gsc_page_read' );

solseo_assert_same( 1, count( $solseo_announced ), 'a page read from Google fires solseo_gsc_page_read once' );
solseo_assert_same(
	'https://asiaticbows.com.au/mongolian-bow/',
	isset( $solseo_announced[0]['args'][0] ) ? $solseo_announced[0]['args'][0] : null,
	'with the address first'
);
solseo_assert_same(
	41,
	isset( $solseo_announced[0]['args'][1]['clicks'] ) ? $solseo_announced[0]['args'][1]['clicks'] : null,
	'and the figures second'
);

solseo_assert(
	0 === strpos( $solseo_page_sent[0]['url'], 'https://searchconsole.googleapis.com/webmasters/v3/sites/' ),
	'and both go to Search Console'
);

solseo_assert(
	false !== strpos( $solseo_page_sent[0]['url'], rawurlencode( 'sc-domain:asiaticbows.com.au' ) ),
	'against the property matched to this site'
);

/* AND THE SECOND LOOK IS FREE. Opening the same page twice must not ask twice. */
solseo_test_http_reset();

$solseo_again = Search_Console::page( 'https://asiaticbows.com.au/mongolian-bow/' );

solseo_assert_same( 41, $solseo_again['clicks'], 'the same page read again gives the same figures' );
solseo_assert_same( array(), solseo_test_http_sent(), 'and asks Google nothing' );
solseo_assert_same( 1, count( solseo_test_actions_fired( 'solseo_gsc_page_read' ) ), 'and announces nothing, because nothing was read' );

/* AND AN UNCONNECTED SITE IS TOLD SO RATHER THAN SHOWN ZEROS. */
Keys::forget( Google::ACCESS );
Keys::forget( Google::REFRESH );
delete_option( Google::OPTION );
Search_Console::forget_all();

$solseo_off = Search_Console::page( 'https://asiaticbows.com.au/mongolian-bow/' );

solseo_assert( is_wp_error( $solseo_off ), 'an unconnected site gets an error, not an empty chart' );
solseo_assert_same( 'solseo_google_off', $solseo_off->get_error_code(), 'and the error says which' );

/*
 * ---- THE BOOT PATH ASKS NOTHING AND TRANSLATES NOTHING (D-124.3) ----------
 *
 * Three lanes in one day shipped a notice on every request by calling
 * something that translates before `init`. This connection has nothing to do
 * at boot: the REST route is registered on rest_api_init, the screen is drawn
 * inside the admin, and the editor panel asks over REST when it is opened.
 * Nothing in the wiring may ask the connection a question on the way past.
 */
$solseo_boot = (string) file_get_contents( SOLSEO_PATH . 'includes/class-plugin.php' );

solseo_assert(
	false === strpos( $solseo_boot, 'Google::' ) && false === strpos( $solseo_boot, 'Search_Console::' ),
	'nothing in the plugin\'s boot path asks the Google connection anything'
);

/*
 * AND NEITHER FILE TRANSLATES AT THE TOP LEVEL. A __() outside a function body
 * runs on the require, which on a class the autoloader reaches early is the
 * same fault by another road.
 */
foreach ( array( $solseo_google_source, $solseo_sc_source ) as $solseo_text ) {
	$solseo_outside = preg_replace( '/\bfunction\s+\w+\s*\([^)]*\)\s*\{.*/s', '', $solseo_text );

	solseo_assert(
		false === strpos( (string) $solseo_outside, '__(' ),
		'and nothing above the first method translates anything'
	);
}

/*
 * ---- THE SCREEN OFFERS THE WAY OUT ----------------------------------------
 *
 * hub/templates/public/privacy.php promises, to a Google reviewer, that "the
 * plugin accepts your own Google client id and secret ... and cuts us out
 * entirely". A guard on the hub fails that build if the sentence goes. This
 * is the half that makes the sentence true, and the three fields are what a
 * customer needs: the two they paste, and the address they have to register
 * in their own Google Cloud project, which is the one nobody remembers.
 */
$solseo_connect_view = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/views/connect.php' );

foreach ( array(
	'solseo_google_client_id',
	'solseo_google_client_secret',
	'own-redirect',
) as $solseo_field ) {
	solseo_assert(
		false !== strpos( $solseo_connect_view, $solseo_field ),
		'the Connections screen has the ' . $solseo_field . ' field'
	);
}

solseo_assert(
	false !== strpos( $solseo_connect_view, 'solseo_google_connect' )
		&& false !== strpos( $solseo_connect_view, 'solseo_google_disconnect' ),
	'and it can both connect and disconnect'
);

/*
 * ────────────────────────────────────────────────────────────────────────────
 * A PACK MAY ASK FOR A SCOPE. NOTHING ASKS FOR ONE TODAY. (D-159.x)
 * ────────────────────────────────────────────────────────────────────────────
 *
 * Two packs shipped reading the granted scope list off this connection and
 * answering no: Local needs business.manage (D-148.1) and Shop needs
 * auth/content (D-144.1). Neither may add a scope, because the Google app is
 * in Testing for webmasters.readonly and adding one to an app in review
 * restarts the review. So what is added here is the seam and nothing else: a
 * filter over the list, defaulting to exactly the one scope, with the base
 * scope held in it so a filter cannot take away the feature this plugin owns.
 *
 * The assertions below are the promise that the default has not moved.
 */
$solseo_scopes_was = isset( $GLOBALS['solseo_filters']['solseo_google_scopes'] )
	? $GLOBALS['solseo_filters']['solseo_google_scopes']
	: null;

unset( $GLOBALS['solseo_filters']['solseo_google_scopes'] );

$solseo_has_scopes = method_exists( 'SolSEO\Connect\Google', 'scopes' );

solseo_assert( $solseo_has_scopes, 'there is one list of scopes a pack can add to' );

solseo_assert_same(
	array( 'https://www.googleapis.com/auth/webmasters.readonly' ),
	$solseo_has_scopes ? Google::scopes() : null,
	'AND WITH NOTHING LISTENING IT IS THE ONE SCOPE, which is what is submitted to Google'
);

/* A pack adding one gets it, on the path where this plugin builds the request. */
add_filter(
	'solseo_google_scopes',
	function ( $solseo_list ) {
		$solseo_list[] = 'https://www.googleapis.com/auth/content';

		return $solseo_list;
	}
);

solseo_assert_same(
	array(
		'https://www.googleapis.com/auth/webmasters.readonly',
		'https://www.googleapis.com/auth/content',
	),
	$solseo_has_scopes ? Google::scopes() : null,
	'a pack can add one through the filter'
);

Google::save_own_app( '123456-mine.apps.googleusercontent.com', 'GOCSPX-mine' );

solseo_assert(
	false !== strpos( Google::consent_url( $solseo_handshake ), rawurlencode( 'auth/content' ) ),
	'and on the advanced path it reaches the consent screen'
);

/*
 * THE FILTER CANNOT TAKE THE BASE SCOPE AWAY. A pack that returned its own
 * scope instead of adding to the list would leave a site connected to Google
 * with no Search Console, and every figure on the dashboard would go to zero
 * with nothing on the screen saying why.
 */
$GLOBALS['solseo_filters']['solseo_google_scopes'] = array();

add_filter(
	'solseo_google_scopes',
	function () {
		return array( 'https://www.googleapis.com/auth/business.manage', '', 42, 'https://www.googleapis.com/auth/business.manage' );
	}
);

$solseo_scope_list = $solseo_has_scopes ? Google::scopes() : array();

solseo_assert_same(
	'https://www.googleapis.com/auth/webmasters.readonly',
	isset( $solseo_scope_list[0] ) ? $solseo_scope_list[0] : null,
	'a filter that replaces the list still gets the read only scope back, first'
);

solseo_assert_same(
	2,
	count( $solseo_scope_list ),
	'and the empty string, the number and the duplicate are dropped'
);

/*
 * AND THE HOSTED PATH IS NOT THE PLUGIN'S TO WIDEN. On the relay the hub
 * builds the consent URL from its own pinned constant (D-128.x), so a filter
 * here changes nothing about what a hosted site is asked for. That is the
 * correct default while the app is unpublished, and it is asserted rather
 * than assumed, because a pack author reading only this file would guess the
 * other way.
 */
Keys::forget( Google::OWN_SECRET );
delete_option( Google::OPTION );

solseo_assert_same( 'hosted', Google::mode(), 'with the pasted app forgotten the connection is hosted again' );

solseo_assert(
	false === strpos( Google::consent_url( $solseo_handshake ), 'scope' ),
	'AND THE RELAY IS SENT NO SCOPE AT ALL, so the hub decides and nothing here can widen it'
);

if ( null === $solseo_scopes_was ) {
	unset( $GLOBALS['solseo_filters']['solseo_google_scopes'] );
} else {
	$GLOBALS['solseo_filters']['solseo_google_scopes'] = $solseo_scopes_was;
}
