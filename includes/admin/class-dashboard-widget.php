<?php
/**
 * The SolSEO panel on the WordPress dashboard.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Analyser;
use SolSEO\Hub\Connection;
use SolSEO\Score_Report;

defined( 'ABSPATH' ) || exit;

/**
 * A short summary on the dashboard home screen.
 */
class Dashboard_Widget {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	/**
	 * Add the widget for users who can see the plugin.
	 */
	public static function register() {
		if ( ! current_user_can( Menu::capability() ) ) {
			return;
		}

		wp_add_dashboard_widget( 'solseo_overview', __( 'SolSEO', 'solseo' ), array( __CLASS__, 'render' ) );
	}

	/**
	 * Draw the widget.
	 */
	public static function render() {
		$summary = Score_Report::summary();

		Screen::view(
			'dashboard-widget',
			array(
				'summary'   => $summary,
				'band'      => Analyser::band( $summary['average'] ),
				'label'     => Analyser::band_label( Analyser::band( $summary['average'] ) ),
				'weakest'   => Score_Report::weakest( 3 ),
				'connected' => Connection::is_connected(),
				'account'   => Connection::summary(),
				'sites'     => self::sites(),
			)
		);
	}

	/**
	 * The sites on the connected account, when the service has sent them.
	 *
	 * @return array
	 */
	protected static function sites() {
		if ( ! Connection::is_connected() ) {
			return array();
		}

		$stored = get_transient( 'solseo_hub_overview' );

		if ( ! is_array( $stored ) || empty( $stored['sites'] ) || ! is_array( $stored['sites'] ) ) {
			return array();
		}

		return array_slice( $stored['sites'], 0, 8 );
	}
}
