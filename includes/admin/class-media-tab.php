<?php
/**
 * What the images on this site cost.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Media\Report;

defined( 'ABSPATH' ) || exit;

/**
 * Health, Media.
 */
class Media_Tab extends Screen {

	const PAGE = 'solseo-health';

	const TAB = 'media';

	/** How many findings the table shows at once. */
	const PER_PAGE = 50;

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::notice();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which page of a table to draw.
		$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$report = Report::stored();
		$rows   = array_slice( $report['rows'], ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );

		self::view(
			'health-media',
			array(
				'report'   => $report,
				'rows'     => $rows,
				'total'    => count( $report['rows'] ),
				'page'     => $page,
				'per_page' => self::PER_PAGE,
				'found'    => array_sum( $report['totals'] ),
				'saving'   => Report::readable( (int) $report['saving'] ),
				'kept'     => Report::ROWS_KEPT,
			)
		);
	}
}
