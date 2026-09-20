<?php
/**
 * Reading the site the way a search engine reads it.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Crawl\Crawler;
use SolSEO\Crawl\Fetch;
use SolSEO\Crawl\Pages;
use SolSEO\Crawl\Selection;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Technical, Crawl.
 */
class Crawl_Tab extends Screen {

	const PAGE = 'solseo-technical';

	const TAB = 'crawl';

	/** How many rows the table shows at once. */
	const PER_PAGE = 50;

	/**
	 * Save the pace.
	 */
	public static function load() {
		if ( ! self::submitted( 'solseo_crawl_pace' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- submitted() checks the nonce.
		$asked = isset( $_POST['solseo_crawl_per_minute'] ) ? (int) $_POST['solseo_crawl_per_minute'] : Fetch::DEFAULT_PER_MINUTE;

		Options::update( array( 'crawl_per_minute' => $asked ) );

		self::remember(
			sprintf(
				/* translators: %d: a number of pages a minute. */
				__( 'Saved. The crawl will ask for %d pages a minute.', 'solseo' ),
				Fetch::per_minute()
			)
		);

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::notice();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which page of a table to draw.
		$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which rows to draw.
		$band = isset( $_GET['band'] ) ? sanitize_key( wp_unslash( $_GET['band'] ) ) : '';

		$summary = Pages::summary();
		$crawled = $summary['seeds'] + $summary['links'] - $summary['waiting'];

		$listing = Pages::listing(
			array(
				'band'     => $band,
				'per_page' => self::PER_PAGE,
				'page'     => $page,
			)
		);

		self::view(
			'technical-crawl',
			array(
				'summary'    => $summary,
				'rows'       => $listing['rows'],
				'total'      => $listing['total'],
				'page'       => $page,
				'per_page'   => self::PER_PAGE,
				'band'       => $band,
				'sentence'   => Selection::sentence( $summary['seeds'], max( $summary['seeds'], Selection::total_pages() ) ),
				'crawled'    => $crawled,
				'may_start'  => Crawler::may_start(),
				'next_at'    => Crawler::waiting_until(),
				'every_days' => Crawler::every_days(),
				'per_minute' => Fetch::per_minute(),
				'minutes'    => self::how_long( Crawler::BUDGET, Fetch::per_minute() ),
				'paces'      => self::paces(),
			)
		);
	}

	/**
	 * How long a crawl of this many pages takes, in whole minutes.
	 *
	 * The screen says this before anybody presses the button, because a crawl
	 * runs while the tab is open and ten minutes is a thing to know in advance
	 * rather than to discover.
	 *
	 * @param int $pages      How many pages.
	 * @param int $per_minute How many a minute.
	 * @return int
	 */
	public static function how_long( $pages, $per_minute ) {
		return (int) max( 1, (int) ceil( (int) $pages / max( 1, (int) $per_minute ) ) );
	}

	/**
	 * The paces somebody can choose, slowest first.
	 *
	 * @return array Requests a minute to what it is called.
	 */
	public static function paces() {
		return array(
			6   => __( 'Very slow, for a small shared host', 'solseo' ),
			12  => __( 'Slow', 'solseo' ),
			30  => __( 'Normal', 'solseo' ),
			60  => __( 'Quick, if your server has room', 'solseo' ),
			120 => __( 'Fastest allowed', 'solseo' ),
		);
	}
}
