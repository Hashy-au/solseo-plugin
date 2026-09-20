<?php
/**
 * Connecting a Google account, so this site can read its own Search Console.
 *
 * @package SolSEO
 */

namespace SolSEO\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * The handshake, the token, and the two ways of doing both.
 *
 * ── WHY solseo.com.au IS IN THE MIDDLE, AND WHY IT DOES NOT HAVE TO BE ──────
 *
 * Connecting to Google needs a client secret. A secret shipped inside a plugin
 * anybody can download from wordpress.org is not a secret: one person posting
 * it revokes every install's connection at once. So this plugin holds none,
 * and the two moments that need one, exchanging an authorisation code and
 * refreshing a token, go through a relay on solseo.com.au that stores nothing.
 *
 * That makes the plugin contact us on a site that may have no SolSEO account,
 * which is why the readme's External services section had to be rewritten
 * before this code was written (D-75.2, D-83.1).
 *
 * It is paid for by the other path. Paste a client id and secret from your own
 * Google Cloud project and nothing here touches solseo.com.au: this site talks
 * to accounts.google.com and oauth2.googleapis.com itself (D-75.3). The
 * privacy policy on solseo.com.au promises a reviewer that path exists, and a
 * guard on the hub fails that build if the sentence goes.
 *
 * ── ONE SCOPE ───────────────────────────────────────────────────────────────
 *
 * webmasters.readonly and nothing else, ever. It is the narrowest scope
 * Search Console publishes, it cannot add a property or submit a sitemap, and
 * it is what was submitted to Google. Business Profile and Merchant Center are
 * the same mechanism and belong to the packs that use them; adding a scope to
 * a verified app triggers a fresh review of the whole app.
 *
 * ── WHY PKCE, ON A CONFIDENTIAL CLIENT ──────────────────────────────────────
 *
 * The relay sends a browser back to an address this plugin named, and the hub
 * has no way to know that address is really the site connecting: any host can
 * serve a path that looks like our REST route. PKCE closes that (RFC 7636).
 * The verifier is minted here, kept in a transient on this server for fifteen
 * minutes, and never travels; only its SHA-256 goes out. A code that lands
 * somewhere it should not cannot be exchanged by whoever catches it.
 *
 * ── WHAT IS STORED ──────────────────────────────────────────────────────────
 *
 * The access token and the refresh token go through Keys, sealed with
 * sodium_crypto_secretbox or AES-256-GCM. Nothing else holds a copy: the
 * option beside them keeps the property, the expiry and the dates, which are
 * not credentials. tests/test-google.php walks every option looking for a
 * readable token and fails if it finds one.
 */
class Google {

	/** The one scope. See the class header before touching this. */
	const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

	/** Google's own consent screen. */
	const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

	/** Where a code and a refresh token are spent, on the advanced path. */
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/** Telling Google to forget us. Needs no client secret, so it is direct on both paths. */
	const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

	/** The relay: where the hosted path starts. */
	const RELAY_START = 'https://solseo.com.au/api/v1/plugin/google/start';

	/** The relay: where the hosted path exchanges and refreshes. */
	const RELAY_TOKEN = 'https://solseo.com.au/api/v1/plugin/google/token';

	/** This plugin's own landing place, on this site. */
	const REST_ROUTE = '/solseo/v1/google/callback';

	/** The non-secret half: property, expiry, dates, the customer's own client id. */
	const OPTION = 'solseo_google';

	/** Keys names. Not in Keys::names(), because nobody types these in. */
	const ACCESS     = 'google_sc_access';
	const REFRESH    = 'google_sc_refresh';
	const OWN_SECRET = 'google_own_secret';

	/** Where a handshake waits for the browser to come back. */
	const HANDSHAKE = 'solseo_google_shake_';

	/** How long somebody has to finish a consent screen. */
	const HANDSHAKE_SECONDS = 900;

	/** Refresh this many seconds before the token actually expires. */
	const EARLY = 120;

	/** Where the outcome of a handshake waits for the screen to draw it. */
	const LANDING = 'solseo_google_landing';

	/**
	 * The route Google, or the relay, sends the browser back to.
	 *
	 * ── WHY permission_callback IS __return_true, WRITTEN OUT ───────────────
	 *
	 * This request arrives as a plain browser navigation from another origin,
	 * with no REST nonce on it. WordPress reads that as an unauthenticated
	 * request and sets the current user to nobody, so there is no capability
	 * to ask about: a permission callback that asked for manage_options would
	 * refuse every real connection and pass none.
	 *
	 * What stands in its place is the handshake. The nonce is sixteen random
	 * bytes, it is minted by somebody who was already signed in and holding a
	 * valid form nonce on the Connections screen, it lives for fifteen
	 * minutes, and take_handshake() deletes it on first use. Without one, this
	 * route stores nothing and says so.
	 *
	 * The worst a stranger who guessed a live nonce could do is attach their
	 * own Google account to this site, which shows figures for a property
	 * this site does not own and is undone by pressing Disconnect. No data
	 * leaves, and nothing about the site is exposed by the attempt.
	 */
	public static function register_rest() {
		register_rest_route(
			'solseo/v1',
			'/google/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'returned' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * The browser is back from Google. Finish, or say why not.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public static function returned( $request ) {
		$nonce = (string) $request->get_param( 'nonce' );

		/* The advanced path has no relay to echo a nonce, so it uses state. */
		if ( '' === $nonce ) {
			$nonce = (string) $request->get_param( 'state' );
		}

		$refused = (string) $request->get_param( 'solseo_error' );

		if ( '' === $refused ) {
			$refused = (string) $request->get_param( 'error' );
		}

		if ( '' !== $refused ) {
			self::take_handshake( $nonce );

			self::land(
				false,
				'access_denied' === $refused
					? __( 'Nothing was connected, because that permission was not granted at Google.', 'solseo' )
					: sprintf(
						/* translators: %s: the word Google used, such as access_denied. */
						__( 'Google stopped the connection and said: %s', 'solseo' ),
						$refused
					)
			);

			return self::back_to_the_screen();
		}

		$done = self::finish( $nonce, (string) $request->get_param( 'code' ) );

		if ( is_wp_error( $done ) ) {
			self::land( false, $done->get_error_message() );

			return self::back_to_the_screen();
		}

		self::land( true, self::after_connecting() );

		return self::back_to_the_screen();
	}

	/**
	 * Read the property list and match one to this site, once, on connecting.
	 *
	 * Doing it here rather than making somebody press a second button is the
	 * difference between a connection that works and a screen that says
	 * "connected" over an empty panel.
	 *
	 * @return string What to tell them.
	 */
	public static function after_connecting() {
		$properties = Search_Console::properties();

		if ( is_wp_error( $properties ) ) {
			return $properties->get_error_message();
		}

		self::remember_properties( $properties );

		$match = Search_Console::best_match( home_url( '/' ), $properties );

		if ( '' === $match ) {
			return Search_Console::unmatched_sentence( home_url( '/' ), $properties );
		}

		self::set_property( $match );

		/* translators: %s: a Search Console property, such as sc-domain:example.com.au. */
		return sprintf( __( 'Connected, reading %s.', 'solseo' ), $match );
	}

	/**
	 * The properties this account could see when it was last asked.
	 *
	 * Kept so the picker has something to draw without asking Google on every
	 * page load. They are the site owner's own property names, on the site
	 * owner's own server, and none of them is a credential.
	 *
	 * @param array $properties From Search_Console::properties().
	 */
	public static function remember_properties( array $properties ) {
		$stored               = self::stored();
		$stored['properties'] = array_values(
			array_filter(
				array_map(
					static function ( $property ) {
						return isset( $property['siteUrl'] ) ? (string) $property['siteUrl'] : '';
					},
					$properties
				)
			)
		);
		$stored['listed_at']  = time();

		self::put( $stored );
	}

	/**
	 * What the picker draws.
	 *
	 * @return array
	 */
	public static function known_properties() {
		$stored = self::stored();

		return isset( $stored['properties'] ) ? (array) $stored['properties'] : array();
	}

	/**
	 * Leave a sentence for the Connections screen to print.
	 *
	 * @param bool   $ok      Whether it worked.
	 * @param string $message What to say.
	 */
	public static function land( $ok, $message ) {
		set_transient( self::LANDING, array( (bool) $ok, (string) $message ), 300 );
	}

	/**
	 * Take that sentence, once.
	 *
	 * @return array|null
	 */
	public static function landing() {
		$stored = get_transient( self::LANDING );

		if ( ! is_array( $stored ) ) {
			return null;
		}

		delete_transient( self::LANDING );

		return array(
			'ok'      => (bool) $stored[0],
			'message' => (string) $stored[1],
		);
	}

	/**
	 * Send the browser out of the REST route and back into wp-admin.
	 *
	 * @return \WP_REST_Response
	 */
	protected static function back_to_the_screen() {
		$response = new \WP_REST_Response( null, 302 );

		$response->header(
			'Location',
			add_query_arg(
				array(
					'page' => 'solseo-settings',
					'tab'  => 'connections',
				),
				admin_url( 'admin.php' )
			)
		);

		return $response;
	}

	/**
	 * Which path this site is on.
	 *
	 * @return string 'own' or 'hosted'.
	 */
	public static function mode() {
		return '' !== self::own_client_id() && Keys::has( self::OWN_SECRET ) ? 'own' : 'hosted';
	}

	/**
	 * The customer's own client id, if they pasted one.
	 *
	 * @return string
	 */
	public static function own_client_id() {
		$stored = self::stored();

		return isset( $stored['own_client_id'] ) ? (string) $stored['own_client_id'] : '';
	}

	/**
	 * Save the customer's own Google app.
	 *
	 * @param string $client_id     Their client id.
	 * @param string $client_secret Their client secret.
	 * @return true|\WP_Error
	 */
	public static function save_own_app( $client_id, $client_secret ) {
		$client_id = trim( (string) $client_id );

		$stored                  = self::stored();
		$stored['own_client_id'] = $client_id;

		self::put( $stored );

		if ( '' === trim( (string) $client_secret ) ) {
			return true;
		}

		return Keys::set( self::OWN_SECRET, $client_secret );
	}

	/**
	 * Stop using the customer's own app and go back to the relay.
	 */
	public static function forget_own_app() {
		$stored = self::stored();

		unset( $stored['own_client_id'] );

		self::put( $stored );
		Keys::forget( self::OWN_SECRET );
	}

	/**
	 * Where Google sends the browser back to, on this site.
	 *
	 * A REST route rather than an admin address, because a redirect URI with a
	 * query string in it is a fight with whichever console is validating it,
	 * and the customer has to register this exact string in their own project
	 * on the advanced path. A clean path is one they can paste without reading
	 * an error message first.
	 *
	 * @return string
	 */
	public static function redirect_uri() {
		return rest_url( ltrim( self::REST_ROUTE, '/' ) );
	}

	/**
	 * Mint a handshake: a PKCE verifier, its challenge, and a nonce.
	 *
	 * @param string $mode 'hosted' or 'own'.
	 * @return array
	 */
	public static function begin_handshake( $mode = 'hosted' ) {
		$verifier = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 7636 says base64url, and this is not hiding anything.

		return array(
			'verifier'  => $verifier,
			'challenge' => rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- as above.
			'nonce'     => bin2hex( random_bytes( 16 ) ),
			'mode'      => 'own' === $mode ? 'own' : 'hosted',
		);
	}

	/**
	 * Keep a handshake until the browser comes back.
	 *
	 * @param array $shake From begin_handshake().
	 */
	public static function remember_handshake( array $shake ) {
		set_transient( self::HANDSHAKE . $shake['nonce'], $shake, self::HANDSHAKE_SECONDS );
	}

	/**
	 * Take a handshake back, once.
	 *
	 * Once is the point. A code replayed at the relay has nothing left to be
	 * checked against, so the second attempt fails on this server rather than
	 * at Google.
	 *
	 * @param string $nonce The nonce that came back.
	 * @return array|null
	 */
	public static function take_handshake( $nonce ) {
		$nonce = (string) $nonce;

		if ( 1 !== preg_match( '/^[A-Za-z0-9]{16,64}$/', $nonce ) ) {
			return null;
		}

		$shake = get_transient( self::HANDSHAKE . $nonce );

		if ( ! is_array( $shake ) || empty( $shake['verifier'] ) ) {
			return null;
		}

		delete_transient( self::HANDSHAKE . $nonce );

		return $shake;
	}

	/**
	 * Every scope the consent screen will be asked for.
	 *
	 * ONE SCOPE, AND A SEAM FOR THE DAY THERE IS A SECOND.
	 *
	 * The default list is `SCOPE` and nothing else, which is what was
	 * submitted to Google and what a site is asked for today on every install
	 * in the world. `solseo_google_scopes` exists because two packs have
	 * shipped needing a second one and neither may add it: Local wants the
	 * Business Profile scope and Shop wants the Merchant Center one, and both
	 * currently read the granted list off this connection and answer no
	 * (D-148.1, D-144.1). They can hang their scope here the day the Google
	 * app is published and the permission is approved. Until then nothing
	 * listens and nothing changes.
	 *
	 * NEITHER OF THOSE TWO STRINGS IS SPELLED OUT ANYWHERE IN THIS FILE, and
	 * that is deliberate rather than tidy. tests/test-google.php scans this
	 * source for them and fails the build on a hit, in prose as much as in
	 * code, because the scan cannot tell the two apart and a scan that could
	 * would miss the string literal it exists to catch. Naming them in a
	 * comment here would have cost the guard to buy nothing (D-159.2).
	 *
	 * THE BASE SCOPE CANNOT BE FILTERED OUT. A pack that returned its own
	 * scope rather than adding to the list would connect a site to Google
	 * with no Search Console in it, and the whole dashboard would answer zero
	 * with nothing on the screen saying why. So it goes back on the front of
	 * whatever comes out.
	 *
	 * IT DOES NOT REACH THE HOSTED PATH. On the relay the hub builds the
	 * consent URL from a constant of its own that its own guards pin, so a
	 * filter here changes what the advanced path asks for and nothing else.
	 * Widening the hosted path is a hub decision and a Google app submission,
	 * not a plugin release (D-128.x).
	 *
	 * @return array Scope strings, the read only Search Console one first.
	 */
	public static function scopes() {
		$scopes = apply_filters( 'solseo_google_scopes', array( self::SCOPE ) );
		$clean  = array( self::SCOPE );

		foreach ( (array) $scopes as $scope ) {
			if ( ! is_string( $scope ) ) {
				continue;
			}

			$scope = trim( $scope );

			if ( '' === $scope || in_array( $scope, $clean, true ) ) {
				continue;
			}

			$clean[] = $scope;
		}

		return $clean;
	}

	/**
	 * Where the Connect button sends somebody.
	 *
	 * @param array $shake From begin_handshake().
	 * @return string
	 */
	public static function consent_url( array $shake ) {
		if ( 'own' === self::mode() ) {
			return self::AUTH_URL . '?' . http_build_query(
				array(
					'client_id'             => self::own_client_id(),
					'redirect_uri'          => self::redirect_uri(),
					'response_type'         => 'code',
					'scope'                 => implode( ' ', self::scopes() ),
					'access_type'           => 'offline',
					'prompt'                => 'consent',
					'code_challenge'        => $shake['challenge'],
					'code_challenge_method' => 'S256',
					'state'                 => $shake['nonce'],
				),
				'',
				'&',
				PHP_QUERY_RFC3986
			);
		}

		return self::RELAY_START . '?' . http_build_query(
			array(
				'return'    => self::redirect_uri(),
				'challenge' => $shake['challenge'],
				'nonce'     => $shake['nonce'],
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * Turn the code the browser came back with into a stored connection.
	 *
	 * @param string $nonce The nonce that came back.
	 * @param string $code  Google's authorisation code.
	 * @return true|\WP_Error
	 */
	public static function finish( $nonce, $code ) {
		$shake = self::take_handshake( $nonce );

		if ( null === $shake ) {
			return new \WP_Error(
				'solseo_google_stale',
				__( 'That connection took too long, or it was already used. Press Connect and try again.', 'solseo' )
			);
		}

		$code = trim( (string) $code );

		if ( '' === $code ) {
			return new \WP_Error(
				'solseo_google_no_code',
				__( 'Google did not send a code back, so nothing was connected.', 'solseo' )
			);
		}

		$answer = self::spend(
			array(
				'grant'         => 'authorization_code',
				'code'          => $code,
				'code_verifier' => (string) $shake['verifier'],
			)
		);

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		return self::store( $answer );
	}

	/**
	 * A fresh access token, refreshing first if the stored one has run out.
	 *
	 * Returns an empty string rather than an error, because every caller of
	 * this is about to ask Search Console something and the sentence a person
	 * reads comes from there.
	 *
	 * @return string
	 */
	public static function access_token() {
		$stored = self::stored();

		if ( empty( $stored['connected_at'] ) || ! empty( $stored['revoked'] ) ) {
			return '';
		}

		$expires = isset( $stored['expires_at'] ) ? (int) $stored['expires_at'] : 0;

		if ( $expires > time() + self::EARLY ) {
			return Keys::get( self::ACCESS );
		}

		$refreshed = self::refresh();

		return is_wp_error( $refreshed ) ? '' : Keys::get( self::ACCESS );
	}

	/**
	 * Swap the refresh token for a new access token.
	 *
	 * @return true|\WP_Error
	 */
	public static function refresh() {
		$refresh = Keys::get( self::REFRESH );

		if ( '' === $refresh ) {
			return new \WP_Error(
				'solseo_google_off',
				__( 'This site is not connected to a Google account.', 'solseo' )
			);
		}

		$answer = self::spend(
			array(
				'grant'         => 'refresh_token',
				'refresh_token' => $refresh,
			)
		);

		if ( is_wp_error( $answer ) ) {
			/*
			 * invalid_grant on a refresh means the permission is gone: the
			 * customer revoked it at Google, or the app's access expired. It
			 * is the only warning there is, so the connection is marked here
			 * rather than left to answer zeros for ever.
			 */
			if ( 'solseo_google_revoked' === $answer->get_error_code() ) {
				self::mark_revoked();
			}

			return $answer;
		}

		return self::store( $answer );
	}

	/**
	 * Cut the connection, here and at Google, in the same request.
	 *
	 * The order matters. Google is told first, because telling it after the
	 * local delete would mean nothing left to tell it with. The local half
	 * happens whether or not Google answers: somebody who pressed Disconnect
	 * has asked for the token to go, and an outage is not a reason to keep it.
	 */
	public static function disconnect() {
		$token = Keys::get( self::REFRESH );

		if ( '' === $token ) {
			$token = Keys::get( self::ACCESS );
		}

		if ( '' !== $token ) {
			wp_remote_post(
				self::REVOKE_URL,
				array(
					'timeout'    => 10,
					'user-agent' => 'SolSEO/' . SOLSEO_VERSION,
					'body'       => array( 'token' => $token ),
				)
			);
		}

		Keys::forget( self::ACCESS );
		Keys::forget( self::REFRESH );

		$stored = self::stored();

		self::put(
			array_filter(
				array(
					'own_client_id' => isset( $stored['own_client_id'] ) ? (string) $stored['own_client_id'] : '',
				),
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);

		Search_Console::forget_all();
	}

	/**
	 * Note that Google has stopped answering for this connection.
	 */
	public static function mark_revoked() {
		$stored            = self::stored();
		$stored['revoked'] = time();

		self::put( $stored );
	}

	/**
	 * Which property this site's figures are read from.
	 *
	 * @return string
	 */
	public static function property() {
		$stored = self::stored();

		return isset( $stored['property'] ) ? (string) $stored['property'] : '';
	}

	/**
	 * Point this site at a property.
	 *
	 * @param string $property A Search Console siteUrl.
	 */
	public static function set_property( $property ) {
		$stored             = self::stored();
		$stored['property'] = (string) $property;

		self::put( $stored );
		Search_Console::forget_all();
	}

	/**
	 * Move the expiry, which is the only part of a token a screen may see.
	 *
	 * @param int $when A unix time.
	 */
	public static function set_expiry( $when ) {
		$stored               = self::stored();
		$stored['expires_at'] = (int) $when;

		self::put( $stored );
	}

	/**
	 * What a screen is allowed to know.
	 *
	 * @return array
	 */
	public static function status() {
		$stored = self::stored();
		$report = Keys::report( self::REFRESH );

		return array(
			'connected'     => ! empty( $stored['connected_at'] ) && empty( $stored['revoked'] ) && $report['set'],
			'revoked'       => ! empty( $stored['revoked'] ),
			'mode'          => self::mode(),
			'property'      => isset( $stored['property'] ) ? (string) $stored['property'] : '',
			'connected_at'  => isset( $stored['connected_at'] ) ? (int) $stored['connected_at'] : 0,
			'expires_at'    => isset( $stored['expires_at'] ) ? (int) $stored['expires_at'] : 0,
			'scope'         => isset( $stored['scope'] ) ? (string) $stored['scope'] : '',
			'fingerprint'   => $report['fingerprint'],
			'hint'          => $report['hint'],
			'own_client_id' => self::own_client_id(),
			'redirect_uri'  => self::redirect_uri(),
		);
	}

	/**
	 * Spend a code or a refresh token, on whichever path this site is on.
	 *
	 * @param array $ask Keys: grant, and then code plus code_verifier, or refresh_token.
	 * @return array|\WP_Error Google's answer, narrowed.
	 */
	protected static function spend( array $ask ) {
		$response = 'own' === self::mode() ? self::spend_directly( $ask ) : self::spend_through_relay( $ask );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'solseo_google_unreadable',
				__( 'The answer to that could not be read. Nothing was changed.', 'solseo' )
			);
		}

		if ( (int) $code >= 400 || isset( $body['error'] ) ) {
			return self::refusal( (int) $code, $body );
		}

		return $body;
	}

	/**
	 * The advanced path: straight to Google, with the customer's own secret.
	 *
	 * @param array $ask As spend().
	 * @return array|\WP_Error
	 */
	protected static function spend_directly( array $ask ) {
		$form = array(
			'client_id'     => self::own_client_id(),
			'client_secret' => Keys::get( self::OWN_SECRET ),
		);

		if ( 'refresh_token' === $ask['grant'] ) {
			$form['grant_type']    = 'refresh_token';
			$form['refresh_token'] = $ask['refresh_token'];
		} else {
			$form['grant_type']    = 'authorization_code';
			$form['code']          = $ask['code'];
			$form['code_verifier'] = $ask['code_verifier'];
			$form['redirect_uri']  = self::redirect_uri();
		}

		return wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout'    => 20,
				'user-agent' => 'SolSEO/' . SOLSEO_VERSION,
				'body'       => $form,
			)
		);
	}

	/**
	 * The hosted path: through the relay, which adds the secret and keeps none
	 * of what comes back.
	 *
	 * The body carries the grant and what that grant needs. Nothing about this
	 * site, nothing about its content, and no Search Console figure has any
	 * business here: tests/test-google.php reads the keys off a real call and
	 * fails on a fourth.
	 *
	 * @param array $ask As spend().
	 * @return array|\WP_Error
	 */
	protected static function spend_through_relay( array $ask ) {
		return wp_remote_post(
			self::RELAY_TOKEN,
			array(
				'timeout'    => 20,
				'user-agent' => 'SolSEO/' . SOLSEO_VERSION,
				'headers'    => array(
					'Content-Type'      => 'application/json',
					'X-SolSEO-Contract' => '1',
				),
				'body'       => wp_json_encode( $ask ),
			)
		);
	}

	/**
	 * Google's refusal, in a sentence somebody can act on.
	 *
	 * @param int   $code HTTP status.
	 * @param array $body Decoded body.
	 * @return \WP_Error
	 */
	protected static function refusal( $code, array $body ) {
		$said = isset( $body['error'] ) ? (string) $body['error'] : '';
		$why  = isset( $body['error_description'] ) ? (string) $body['error_description'] : '';

		if ( 'invalid_grant' === $said ) {
			return new \WP_Error(
				'solseo_google_revoked',
				__( 'Google says this connection has been revoked or has expired, so it has stopped. Press Connect to set it up again.', 'solseo' )
			);
		}

		if ( 'invalid_client' === $said ) {
			return new \WP_Error(
				'solseo_google_bad_client',
				__( 'Google did not recognise the app making this request. On the advanced path, check the client id and secret and that this site\'s address is registered as a redirect URI in your own Google Cloud project.', 'solseo' )
			);
		}

		/* translators: 1: an HTTP status code. 2: what Google said. */
		return new \WP_Error(
			'solseo_google_refused',
			sprintf(
				/* translators: 1: an HTTP status code. 2: the words the service used. */
				__( 'The connection was refused with a %1$d: %2$s', 'solseo' ),
				(int) $code,
				'' !== $why ? $why : ( '' !== $said ? $said : __( 'nothing else was said.', 'solseo' ) )
			)
		);
	}

	/**
	 * Put a token away.
	 *
	 * @param array $answer Google's answer.
	 * @return true|\WP_Error
	 */
	protected static function store( array $answer ) {
		$access = isset( $answer['access_token'] ) ? (string) $answer['access_token'] : '';

		if ( '' === $access ) {
			return new \WP_Error(
				'solseo_google_empty',
				__( 'The answer held no token, so nothing was connected.', 'solseo' )
			);
		}

		$saved = Keys::set( self::ACCESS, $access );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		/*
		 * A refresh answer carries no refresh token. That is Google's
		 * behaviour and not an error: the one already stored is still the one.
		 */
		if ( ! empty( $answer['refresh_token'] ) ) {
			$saved = Keys::set( self::REFRESH, (string) $answer['refresh_token'] );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		$stored = self::stored();

		unset( $stored['revoked'] );

		$stored['connected_at'] = empty( $stored['connected_at'] ) ? time() : (int) $stored['connected_at'];
		$stored['expires_at']   = time() + max( 60, (int) ( isset( $answer['expires_in'] ) ? $answer['expires_in'] : 3600 ) );
		$stored['scope']        = isset( $answer['scope'] ) ? (string) $answer['scope'] : self::SCOPE;

		self::put( $stored );

		return true;
	}

	/**
	 * Everything kept beside the sealed tokens. None of it is a credential.
	 *
	 * @return array
	 */
	protected static function stored() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Write it.
	 *
	 * @param array $stored The lot.
	 */
	protected static function put( array $stored ) {
		update_option( self::OPTION, $stored, false );
	}
}
