<?php
/**
 * What the shopping feed will come back refused for.
 *
 * Health, Findings, and it says here that it is on this screen on purpose: the
 * Shop screen is not owned by anything yet, and a tab on a screen nothing has
 * put a tab on is a screen that is not in the menu. When the Shop pack lands
 * and fills that screen, this moves there. Named in design/batches/FreeB4.md so
 * the move is a decision rather than a discovery.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Woo\Feed_Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Health, Findings.
 */
class Findings_Tab extends Screen {

	const PAGE = 'solseo-health';

	const TAB = 'findings';

	/** How many findings the table lists. */
	const PER_PAGE = 100;

	/**
	 * Draw the tab.
	 *
	 * The reading happens here rather than in load(), because there is nothing
	 * to redirect away from: this writes nothing, so there is no risk of doing
	 * it twice and nothing to remember afterwards. Pressing the button reads
	 * the shop and draws the answer in the same request.
	 */
	public static function render() {
		self::notice();

		$report = null;

		if ( self::submitted( 'solseo_feed_audit' ) ) {
			$report = Feed_Audit::audit();
		}

		self::view(
			'health-findings',
			array(
				'report'   => is_wp_error( $report ) ? null : $report,
				'trouble'  => is_wp_error( $report ) ? $report->get_error_message() : '',
				'owners'   => Feed_Audit::owners(),
				'rules'    => Feed_Audit::rules(),
				'per_page' => self::PER_PAGE,
				'spec'     => Feed_Audit::SPECIFICATION,
			)
		);
	}
}
