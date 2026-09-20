<?php
/**
 * What this plugin changed, and who changed it.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Change_Log;

defined( 'ABSPATH' ) || exit;

/**
 * The change log.
 */
class Log_Tab extends Screen {

	const PAGE = 'solseo-settings';

	const TAB = 'log';

	/**
	 * Handle the one button on this tab.
	 */
	public static function load() {
		if ( ! self::submitted( 'solseo_log_clear' ) ) {
			return;
		}

		Change_Log::clear();

		self::remember( __( 'The log is empty.', 'solseo' ) );
		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::view(
			'settings-log',
			array(
				'entries' => Change_Log::all(),
				'limit'   => Change_Log::limit(),
			)
		);
	}
}
