<?php
/**
 * Links on this site that go nowhere, and links that go the long way.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Crawl\Pages;
use SolSEO\Links\Checker;
use SolSEO\Links\Resolver;
use SolSEO\Redirects\Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Technical, Links checked.
 */
class Links_Tab extends Screen {

	const PAGE = 'solseo-technical';

	const TAB = 'links';

	/**
	 * Take a link out, or send an address somewhere.
	 */
	public static function load() {
		if ( self::submitted( 'solseo_links_unlink' ) ) {
			self::take_out();
		}

		if ( self::submitted( 'solseo_links_redirect' ) ) {
			self::send_elsewhere();
		}
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::notice();

		$summary = Pages::summary();

		self::view(
			'technical-links',
			array(
				'dead'      => Checker::dead( 100 ),
				'chains'    => Checker::chains( 50 ),
				'crawled'   => (int) $summary['seeds'] + (int) $summary['links'] - (int) $summary['waiting'],
				'suspects'  => Checker::suspects( 100 ),
				'crawl_url' => add_query_arg(
					array(
						'page' => self::PAGE,
						'tab'  => Crawl_Tab::TAB,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	/**
	 * Take one dead link out of every page that holds it.
	 */
	protected static function take_out() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised on the next line.
		$path = isset( $_POST['solseo_links_path'] ) ? wp_unslash( $_POST['solseo_links_path'] ) : '';
		$path = Resolver::normalise( $path, home_url() );

		if ( '' === $path ) {
			self::remember( __( 'That is not an address on this site.', 'solseo' ), 'error' );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		$sources = wp_list_pluck( Checker::sources_of( $path, 100 ), 'id' );
		$changed = Checker::unlink_everywhere( $path, $sources );

		self::remember(
			$changed
				? sprintf(
					/* translators: %s: a number of pages. */
					_n( 'Taken out of %s page. The words are still there, and the page before this is in that page\'s revisions.', 'Taken out of %s pages. The words are still there, and the page before this is in each page\'s revisions.', $changed, 'solseo' ),
					number_format_i18n( $changed )
				)
				: __( 'Nothing changed. Either the link has already gone, or you are not allowed to edit the pages that hold it.', 'solseo' ),
			$changed ? 'success' : 'error'
		);

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Send a dead address somewhere that works.
	 */
	protected static function send_elsewhere() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised on the next line.
		$path = isset( $_POST['solseo_links_path'] ) ? wp_unslash( $_POST['solseo_links_path'] ) : '';
		$path = Resolver::normalise( $path, home_url() );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned on this line.
		$target = isset( $_POST['solseo_links_target'] ) ? esc_url_raw( wp_unslash( $_POST['solseo_links_target'] ) ) : '';

		if ( '' === $path || '' === $target ) {
			self::remember( __( 'Give an address for it to go to, and it will be sent there.', 'solseo' ), 'error' );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		$saved = Manager::save(
			array(
				'source'      => $path,
				'target'      => $target,
				'status_code' => 301,
				'match_type'  => 'exact',
				'enabled'     => 1,
			)
		);

		self::remember(
			$saved
				? sprintf(
					/* translators: 1: the old address. 2: the new one. */
					__( '%1$s now goes to %2$s. The rule is on the Redirects screen, where it can be changed or removed.', 'solseo' ),
					$path,
					$target
				)
				: __( 'That rule could not be saved.', 'solseo' ),
			$saved ? 'success' : 'error'
		);

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}
}
