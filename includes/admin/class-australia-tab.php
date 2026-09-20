<?php
/**
 * Six things an Australian business selling online is asked for.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Compliance\AU_Check;

defined( 'ABSPATH' ) || exit;

/**
 * Health, Australia.
 */
class Australia_Tab extends Screen {

	const PAGE = 'solseo-health';

	const TAB = 'australia';

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::notice();

		self::view(
			'health-australia',
			array(
				'checks'  => AU_Check::run(),
				'tag_url' => add_query_arg(
					array(
						'page' => Connect_Screen::PAGE,
						'tab'  => Connect_Screen::TAB,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}
}
