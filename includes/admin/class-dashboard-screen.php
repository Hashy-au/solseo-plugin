<?php
/**
 * The screen the menu opens on.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Analyser;
use SolSEO\Hub\Connection;
use SolSEO\Options;
use SolSEO\Redirects\Log;
use SolSEO\Redirects\Manager;
use SolSEO\Score_Report;

defined( 'ABSPATH' ) || exit;

/**
 * Where the site stands, in one screen.
 */
class Dashboard_Screen extends Screen {

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		$summary = Score_Report::summary();

		echo '<div class="solseo-grid">';

		self::view(
			'dashboard-score',
			array(
				'summary'  => $summary,
				'unscored' => Score_Report::unscored(),
				'weakest'  => Score_Report::weakest( 5 ),
				'band'     => Analyser::band( $summary['average'] ),
				'label'    => Analyser::band_label( Analyser::band( $summary['average'] ) ),
			)
		);

		self::view(
			'dashboard-health',
			array(
				'checks' => self::site_checks(),
			)
		);

		self::view(
			'dashboard-account',
			array(
				'connected' => Connection::is_connected(),
				'summary'   => Connection::summary(),
				'redirects' => Manager::count(),
				'not_found' => Log::count(),
			)
		);

		/**
		 * Fires inside the dashboard grid, after the cards this plugin draws.
		 *
		 * An add-on prints its own `.solseo-card` here. Nothing in this plugin
		 * listens, and nothing on this screen depends on anything printed.
		 *
		 * @since 1.3.0
		 */
		do_action( 'solseo_dashboard_panels' );

		echo '</div>';
	}

	/**
	 * The handful of site settings worth checking on every visit.
	 *
	 * KEYED, because WordPress's own Site Health screen asks for the same five
	 * answers one at a time and a numeric index would tie that class to the
	 * order they happen to be written in here.
	 *
	 * @return array Keyed by check. Each entry has label, status and note.
	 */
	public static function site_checks() {
		$checks = array();

		$public = (int) get_option( 'blog_public' );

		$checks['search'] = array(
			'label'  => __( 'Search engines can read the site', 'solseo' ),
			'status' => $public ? 'good' : 'poor',
			'note'   => $public
				? __( 'Reading is allowed under Settings, Reading.', 'solseo' )
				: __( 'WordPress is asking search engines to stay away. Turn that off under Settings, Reading.', 'solseo' ),
		);

		$permalinks = get_option( 'permalink_structure' );

		$checks['permalinks'] = array(
			'label'  => __( 'Readable addresses', 'solseo' ),
			'status' => $permalinks ? 'good' : 'poor',
			'note'   => $permalinks
				? __( 'Pages have words in their addresses.', 'solseo' )
				: __( 'Addresses are still numbered. Choose a permalink structure under Settings.', 'solseo' ),
		);

		$sitemap = Options::get( 'sitemap_enabled' );

		$checks['sitemap'] = array(
			'label'  => __( 'XML sitemap', 'solseo' ),
			'status' => $sitemap ? 'good' : 'fair',
			'note'   => $sitemap
				? sprintf( '<a href="%1$s" target="_blank" rel="noopener">%1$s</a>', esc_url( home_url( '/sitemap.xml' ) ) )
				: __( 'The sitemap is switched off.', 'solseo' ),
		);

		$checks['schema'] = array(
			'label'  => __( 'Structured data', 'solseo' ),
			'status' => Options::get( 'schema_enabled' ) ? 'good' : 'fair',
			'note'   => Options::get( 'entity_name' )
				? __( 'The site describes itself to search engines.', 'solseo' )
				: __( 'Set the organisation name under Titles and Meta so the markup is complete.', 'solseo' ),
		);

		$checks['https'] = array(
			'label'  => __( 'Site address uses HTTPS', 'solseo' ),
			'status' => 0 === strpos( home_url(), 'https://' ) ? 'good' : 'poor',
			'note'   => 0 === strpos( home_url(), 'https://' )
				? __( 'The site is served over a secure connection.', 'solseo' )
				: __( 'Move the site to HTTPS. Search results treat it as a baseline.', 'solseo' ),
		);

		return $checks;
	}
}
