<?php
/**
 * Connecting the site to a SolSEO account.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Hub\Connection;

defined( 'ABSPATH' ) || exit;

/**
 * The Connect screen.
 */
class Connect_Screen extends Screen {

	const PAGE = 'solseo-connect';

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

			self::go_back( self::PAGE );
		}

		if ( self::submitted( 'solseo_disconnect' ) ) {
			Connection::disconnect();
			self::remember( __( 'Disconnected. Nothing is sent to SolSEO any more.', 'solseo' ) );
			self::go_back( self::PAGE );
		}

		if ( self::submitted( 'solseo_refresh' ) ) {
			Connection::sync();
			self::remember( __( 'Checked with SolSEO.', 'solseo' ) );
			self::go_back( self::PAGE );
		}
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		self::view(
			'connect',
			array(
				'connected' => Connection::is_connected(),
				'summary'   => Connection::summary(),
				'hint'      => Connection::key_hint(),
			)
		);
	}
}
