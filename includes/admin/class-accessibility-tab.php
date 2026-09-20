<?php
/**
 * The accessibility failures in the writing, page by page.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\A11y\Editor_Checks;
use SolSEO\Content;
use SolSEO\Score_Report;

defined( 'ABSPATH' ) || exit;

/**
 * Health, Accessibility.
 */
class Accessibility_Tab extends Screen {

	const PAGE = 'solseo-health';

	const TAB = 'accessibility';

	/** How many pages one screen reads. */
	const PER_PAGE = 20;

	/**
	 * Draw the tab.
	 *
	 * The pages on this page of the list are read here and now, rather than
	 * from a report that was run last month and has been wrong ever since. It
	 * costs twenty renders, which is one chunk of the media job, and it is the
	 * whole reason this screen never disagrees with the editor panel: both of
	 * them are the same six functions over the same content.
	 */
	public static function render() {
		self::notice();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which page of a table to draw.
		$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$total = Score_Report::countable( 'all' );
		$ids   = Score_Report::ids_after( 'all', self::cursor( $page ), self::PER_PAGE );
		$rows  = array();
		$found = 0;

		foreach ( $ids as $id ) {
			$findings = Editor_Checks::run( Content::rendered( (int) $id ) );

			if ( ! $findings ) {
				continue;
			}

			$found += count( $findings );

			$rows[] = array(
				'id'       => (int) $id,
				'title'    => get_the_title( (int) $id ),
				'findings' => $findings,
			);
		}

		self::view(
			'health-accessibility',
			array(
				'rows'     => $rows,
				'read'     => count( $ids ),
				'found'    => $found,
				'total'    => $total,
				'page'     => $page,
				'per_page' => self::PER_PAGE,
				'covers'   => Editor_Checks::covers(),
			)
		);
	}

	/**
	 * Where this page of the list starts.
	 *
	 * `ids_after()` walks by post ID rather than by offset, so paging through
	 * it means knowing the last ID of the page before. Reading the ids of every
	 * earlier page is one cheap query each and keeps the paging honest on a site
	 * where somebody deletes a page halfway through reading the list.
	 *
	 * @param int $page Which page of the table.
	 * @return int The post ID this page starts after.
	 */
	protected static function cursor( $page ) {
		$after = 0;

		for ( $step = 1; $step < (int) $page; $step++ ) {
			$ids = Score_Report::ids_after( 'all', $after, self::PER_PAGE );

			if ( ! $ids ) {
				return $after;
			}

			$after = (int) end( $ids );
		}

		return $after;
	}
}
