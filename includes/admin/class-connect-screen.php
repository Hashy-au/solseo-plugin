<?php
/**
 * Connecting the site to a SolSEO account.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analytics\Tag_Check;
use SolSEO\Connect\Google;
use SolSEO\Connect\Keys;
use SolSEO\Connect\Search_Console;
use SolSEO\Hub\Connection;

defined( 'ABSPATH' ) || exit;

/**
 * The Connect screen.
 */
class Connect_Screen extends Screen {
	/*
	 * A menu item of its own until 1.4.0, now a tab on the Settings screen.
	 * The old address still works through Menu::MOVED.
	 */
	const PAGE = 'solseo-settings';

	/** Which tab on that screen. */
	const TAB = 'connections';

	/**
	 * Handle the three buttons on this screen.
	 */
	public static function load() {
		if ( self::submitted( 'solseo_connect' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.
			$code   = isset( $_POST['solseo_code'] ) ? sanitize_text_field( wp_unslash( $_POST['solseo_code'] ) ) : '';
			$result = Connection::connect( $code );

			if ( is_wp_error( $result ) ) {
				self::remember( $result->get_error_message(), 'error' );
			} else {
				self::remember( __( 'This site is now connected to SolSEO.', 'solseo' ) );
			}

			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		if ( self::submitted( 'solseo_disconnect' ) ) {
			Connection::disconnect();
			self::remember( __( 'Disconnected. Nothing is sent to SolSEO any more.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		if ( self::submitted( 'solseo_refresh' ) ) {
			Connection::sync();
			self::remember( __( 'Checked with SolSEO.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		if ( self::submitted( 'solseo_save_keys' ) ) {
			self::save_keys();
		}

		if ( self::submitted( 'solseo_tag_check' ) ) {
			self::read_tags();
		}

		if ( self::submitted( 'solseo_google_connect' ) ) {
			self::start_google();
		}

		if ( self::submitted( 'solseo_google_disconnect' ) ) {
			Google::disconnect();
			self::remember( __( 'Disconnected from Google. The token is deleted and Google has been told to forget it.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		if ( self::submitted( 'solseo_google_property' ) ) {
			self::pick_property();
		}

		if ( self::submitted( 'solseo_google_own' ) ) {
			self::save_own_app();
		}

		if ( self::submitted( 'solseo_google_properties' ) ) {
			self::read_properties();
		}
	}

	/**
	 * Ask Google again which properties this account can see.
	 *
	 * Behind a button, because it is one of only two things this plugin ever
	 * asks Search Console and the other one is the page being edited. A list
	 * refreshed on every page load would spend somebody's allowance drawing a
	 * dropdown nobody opened.
	 */
	protected static function read_properties() {
		$properties = Search_Console::properties();

		if ( is_wp_error( $properties ) ) {
			self::remember( $properties->get_error_message(), 'error' );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		Google::remember_properties( $properties );

		self::remember(
			$properties
				? sprintf(
					/* translators: %s: how many Search Console properties were found. */
					_n( 'That Google account can see %s property.', 'That Google account can see %s properties.', count( $properties ), 'solseo' ),
					number_format_i18n( count( $properties ) )
				)
				: Search_Console::unmatched_sentence( home_url( '/' ), array() )
		);

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Send somebody to Google to say yes.
	 *
	 * The handshake is minted here, behind the same nonce and capability
	 * check as every other button on this screen, which is what makes the
	 * REST route it comes back to safe to leave open. See Google::returned().
	 */
	protected static function start_google() {
		$shake = Google::begin_handshake( Google::mode() );

		Google::remember_handshake( $shake );

		wp_redirect( Google::consent_url( $shake ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- this goes to Google or to the relay, which is the whole point, and wp_safe_redirect would refuse both.
		exit;
	}

	/**
	 * Point this site at one of the properties the account can see.
	 */
	protected static function pick_property() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on this line.
		$asked = isset( $_POST['solseo_google_property'] ) ? sanitize_text_field( wp_unslash( $_POST['solseo_google_property'] ) ) : '';

		/*
		 * Only one of the properties Google itself listed. A siteUrl typed
		 * into the form would be a string this site put in an API path, and
		 * the picker exists so it never has to be.
		 */
		if ( '' !== $asked && ! in_array( $asked, Google::known_properties(), true ) ) {
			self::remember( __( 'That is not one of the properties this Google account can see.', 'solseo' ), 'error' );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		Google::set_property( $asked );
		self::remember(
			'' === $asked
				? __( 'No property is matched to this site, so nothing is being read.', 'solseo' )
				: sprintf(
					/* translators: %s: a Search Console property. */
					__( 'Now reading %s.', 'solseo' ),
					$asked
				)
		);

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Save, or clear, the customer's own Google app (D-75.3).
	 */
	protected static function save_own_app() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on this line.
		$client_id = isset( $_POST['solseo_google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['solseo_google_client_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- length is read first, then sanitised.
		$typed = isset( $_POST['solseo_google_client_secret'] ) ? (string) wp_unslash( $_POST['solseo_google_client_secret'] ) : '';

		if ( '' === $client_id ) {
			Google::forget_own_app();
			self::remember( __( 'Your own Google app is no longer used. New connections go through solseo.com.au again.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		/*
		 * The same rule the other credentials on this screen follow: a blank
		 * field means keep what is stored, because the field arrives blank on
		 * every save whether or not a secret is saved.
		 */
		$secret = 'keep' === Keys::instruction( $typed ) ? '' : sanitize_text_field( $typed );

		if ( '' === $secret && ! Keys::has( Google::OWN_SECRET ) ) {
			self::remember( __( 'A client id on its own does nothing. Paste the client secret beside it.', 'solseo' ), 'error' );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		$saved = Google::save_own_app( $client_id, $secret );

		if ( is_wp_error( $saved ) ) {
			self::remember( $saved->get_error_message(), 'error' );
		} else {
			self::remember( __( 'Saved. Connect again and nothing in the handshake will touch solseo.com.au.', 'solseo' ) );
		}

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Save the third party credentials on this screen.
	 *
	 * A credential is never printed back into its own field, so the field
	 * arrives empty on every save whether or not one is stored. Reading empty
	 * as "remove it" therefore wipes a working key the first time somebody
	 * saves this screen for any other reason, which is exactly what happened
	 * the first time this screen was opened on a real WordPress.
	 *
	 * So: nothing typed means nothing changes, and a field holding only
	 * whitespace is the deliberate way to remove one. That is what the line
	 * under the field has always said.
	 */
	protected static function save_keys() {
		$trouble = '';

		foreach ( array_keys( Keys::names() ) as $name ) {
			$field = 'solseo_key_' . $name;

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- length is all this line reads; the value is sanitised below.
			$typed = (string) wp_unslash( $_POST[ $field ] );

			if ( 'keep' === Keys::instruction( $typed ) ) {
				continue;
			}

			$value = sanitize_text_field( $typed );

			/*
			 * A field full of asterisks is the placeholder standing in for a
			 * credential already saved, which a password manager will happily
			 * fill in for somebody. Taking it literally would replace a working
			 * key with a row of dots.
			 */
			if ( '' !== $value && 1 === preg_match( '/^[*\x{2022}]+$/u', $value ) ) {
				continue;
			}

			$saved = Keys::set( $name, $value );

			if ( is_wp_error( $saved ) ) {
				$trouble = $saved->get_error_message();
			}
		}

		if ( '' !== $trouble ) {
			self::remember( $trouble, 'error' );
		} else {
			self::remember( __( 'Saved.', 'solseo' ) );
		}

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Read the home page and say what is measuring it.
	 *
	 * The one request this screen makes, and it goes to this site. It is behind
	 * a button because reading a page is slow enough that doing it every time
	 * somebody opens Connections would be rude to their own server.
	 */
	protected static function read_tags() {
		$done = Tag_Check::check();

		if ( is_wp_error( $done ) ) {
			self::remember( $done->get_error_message(), 'error' );
		} else {
			self::remember(
				$done['tags']
					? sprintf(
						/* translators: %s: how many tags were found. */
						_n( 'Read your home page. %s tag is on it.', 'Read your home page. %s tags are on it.', count( $done['tags'] ), 'solseo' ),
						number_format_i18n( count( $done['tags'] ) )
					)
					: __( 'Read your home page. Nothing this knows about is measuring your visitors.', 'solseo' )
			);
		}

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		$keys = array();

		foreach ( Keys::names() as $name => $about ) {
			$keys[ $name ] = array_merge( $about, Keys::report( $name ) );
		}

		/*
		 * The handshake comes back through a REST route, which runs with
		 * nobody signed in and so cannot use the usual notice. It leaves its
		 * sentence here instead, and this is the one place that reads it.
		 */
		$landed = Google::landing();

		if ( null !== $landed ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				$landed['ok'] ? 'success' : 'error',
				esc_html( $landed['message'] )
			);
		}

		self::view(
			'connect',
			array(
				'connected'  => Connection::is_connected(),
				'summary'    => Connection::summary(),
				'hint'       => Connection::key_hint(),
				'keys'       => $keys,
				'sealing'    => Keys::sealing(),
				'tags'       => Tag_Check::stored(),
				'google'     => Google::status(),
				'properties' => Google::known_properties(),
				'scope'      => Google::SCOPE,
			)
		);
	}
}
