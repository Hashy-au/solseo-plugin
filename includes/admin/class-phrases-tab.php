<?php
/**
 * Phrases more than one page is going for.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Duplicates;

defined( 'ABSPATH' ) || exit;

/**
 * Content, Phrases.
 */
class Phrases_Tab extends Screen {

	const PAGE = 'solseo-content';

	const TAB = 'phrases';

	/** How many phrases the table shows at once. */
	const PER_PAGE = 25;

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::notice();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which page of a table to draw.
		$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$all = Duplicates::all();

		$competing = array();
		$alongside = array();

		foreach ( $all as $one ) {
			if ( $one['competing'] ) {
				$competing[] = $one;

				continue;
			}

			$alongside[] = $one;
		}

		self::view(
			'content-phrases',
			array(
				'rows'      => array_slice( $competing, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ),
				'total'     => count( $competing ),
				'alongside' => $alongside,
				'page'      => $page,
				'per_page'  => self::PER_PAGE,
			)
		);
	}
}
