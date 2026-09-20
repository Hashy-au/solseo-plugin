<?php
/**
 * Telling search engines a page changed.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Indexing\Bulk;
use SolSEO\Indexing\Indexnow;
use SolSEO\Indexing\Submit;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Technical, Indexing.
 */
class Indexing_Tab extends Screen {

	const PAGE = 'solseo-technical';

	const TAB = 'indexing';

	/**
	 * Handle the switch and the bulk button.
	 */
	public static function load() {
		if ( self::submitted( 'solseo_indexing_save' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- submitted() checks the nonce.
			$on = ! empty( $_POST['solseo_indexnow_enabled'] );

			Options::update( array( 'indexnow_enabled' => $on ) );

			/*
			 * The key file is served through a rewrite rule that is only added
			 * while the feature is on, so the rules have to be rebuilt the
			 * moment it is switched either way. Without this the first
			 * submission earns a 403 and the reason is invisible.
			 */
			flush_rewrite_rules( false );

			self::remember(
				$on
					? __( 'On. Publishing or updating a page now tells the search engines.', 'solseo' )
					: __( 'Off. Nothing is sent, and the key file is no longer served.', 'solseo' )
			);

			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		if ( self::submitted( 'solseo_indexing_bulk' ) ) {
			$result = Bulk::run();

			self::remember(
				$result['ok']
					? sprintf(
						/* translators: %d: how many addresses were sent. */
						_n( 'Sent %d address.', 'Sent %d addresses.', (int) $result['sent'], 'solseo' ),
						(int) $result['sent']
					)
					: $result['says'],
				$result['ok'] ? 'success' : 'error'
			);

			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::notice();

		$on = (bool) Options::get( 'indexnow_enabled' );

		self::view(
			'technical-indexing',
			array(
				'on'       => $on,
				'key_file' => $on ? Indexnow::key_location() : '',
				'waiting'  => $on ? Bulk::waiting() : 0,
				'cap'      => Bulk::LIMIT,
				'floor'    => Submit::FLOOR,
			)
		);
	}
}
