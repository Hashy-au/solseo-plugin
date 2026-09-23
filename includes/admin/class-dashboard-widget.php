<?php
/**
 * The SolSEO panels on the WordPress dashboard.
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
 * Where this site stands, on the screen everybody lands on.
 *
 * ONE SITE, AND IT IS THIS ONE (D-200.2). The widget used to list every site on
 * the connected account, which on an agency's account meant each client saw the
 * names, addresses and health scores of every other client. The hub stopped
 * sending the others, and this side picks the paired site out of whatever it is
 * given, so a site running an older hub stops showing them too.
 *
 * SIX WIDGETS, ONE OF THEM ON (D-201.1). The full panel is what a site gets to
 * begin with. Each of its sections is also a widget of its own, hidden until
 * somebody ticks it in Screen Options, which is WordPress's own switch for
 * exactly this and saves the plugin inventing a second one on a settings
 * screen.
 *
 * NOTHING HERE MAKES A REQUEST. Every hub figure is read from the transient the
 * twice daily sync fills. A dashboard widget that phoned a service would put
 * that service's response time in front of every wp-admin login on the site.
 */
class Dashboard_Widget {

	/** The full panel. This is the one that is on to begin with. */
	const PANEL = 'solseo_overview';

	/** Where the list of widgets a user has already been offered is kept. */
	const SEEN = 'solseo_widgets_seen';

	/**
	 * The figures every section is drawn from, worked out once per request.
	 *
	 * @var array|null
	 */
	protected static $data = null;

	/**
	 * The widget ids registered on this request.
	 *
	 * @var array
	 */
	protected static $registered = array();

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
		add_filter( 'hidden_meta_boxes', array( __CLASS__, 'hide_new_ones' ), 10, 2 );
	}

	/**
	 * Every widget this plugin offers, in the order they are registered.
	 *
	 * `needs` is what has to be true for the widget to be offered at all. A
	 * widget that could only ever draw nothing is not registered, so Screen
	 * Options does not offer somebody a panel that stays empty whatever they do
	 * with it.
	 *
	 * @return array Keyed by widget id. Each entry has title, section and needs.
	 */
	public static function widgets() {
		return array(
			self::PANEL      => array(
				'title'   => __( 'SolSEO', 'solseo' ),
				'section' => 'panel',
				'needs'   => 'always',
			),
			'solseo_score'   => array(
				'title'   => __( 'SolSEO: content score', 'solseo' ),
				'section' => 'score',
				'needs'   => 'always',
			),
			'solseo_checks'  => array(
				'title'   => __( 'SolSEO: what needs work', 'solseo' ),
				'section' => 'checks',
				'needs'   => 'always',
			),
			'solseo_site'    => array(
				'title'   => __( 'SolSEO: site health and keywords', 'solseo' ),
				'section' => 'site',
				'needs'   => 'site',
			),
			'solseo_monitor' => array(
				'title'   => __( 'SolSEO: site monitoring', 'solseo' ),
				'section' => 'monitor',
				'needs'   => 'monitor',
			),
			'solseo_pages'   => array(
				'title'   => __( 'SolSEO: pages worth fixing', 'solseo' ),
				'section' => 'pages',
				'needs'   => 'weakest',
			),
		);
	}

	/**
	 * Which of the widgets are not the full panel.
	 *
	 * @return array Widget ids.
	 */
	public static function extras() {
		return array_values( array_diff( array_keys( self::widgets() ), array( self::PANEL ) ) );
	}

	/**
	 * Add the widgets for users who can see the plugin.
	 */
	public static function register() {
		if ( ! current_user_can( Menu::capability() ) ) {
			return;
		}

		$data = self::data();

		foreach ( self::widgets() as $id => $widget ) {
			if ( 'always' !== $widget['needs'] && empty( $data[ $widget['needs'] ] ) ) {
				continue;
			}

			self::$registered[] = $id;

			wp_add_dashboard_widget(
				$id,
				$widget['title'],
				function () use ( $widget ) {
					self::draw( $widget['section'] );
				}
			);
		}
	}

	/**
	 * Whether any of these went onto the dashboard being drawn.
	 *
	 * Asked by the asset loader, which has no other way to tell a Dashboard
	 * that holds a SolSEO panel from one that does not. register() runs from
	 * wp_dashboard_setup(), and wp-admin/index.php calls that before it loads
	 * the admin header, which is what fires admin_enqueue_scripts, so by the
	 * time this is asked the answer is settled. It is false for a user who
	 * cannot see the plugin, because register() leaves before recording
	 * anything.
	 *
	 * A widget the user has hidden in Screen Options still counts. WordPress
	 * renders a hidden panel and hides it with a class, so the markup is on the
	 * page and needs the stylesheet the moment somebody ticks the box back on.
	 *
	 * @return bool
	 */
	public static function on_dashboard() {
		return ! empty( self::$registered );
	}

	/**
	 * Draw one widget.
	 *
	 * @param string $section Section name, or panel for the lot.
	 */
	public static function draw( $section ) {
		$data = self::data();

		if ( 'panel' === $section ) {
			Screen::view( 'dashboard-widget', $data );

			return;
		}

		echo '<div class="solseo-widget">';
		Screen::view( 'widget-' . $section, $data );
		echo '</div>';
	}

	/**
	 * Draw the full panel.
	 *
	 * Kept under its old name because "render the SolSEO widget" is worth being
	 * able to say without knowing there are six of them.
	 */
	public static function render() {
		self::draw( 'panel' );
	}

	/**
	 * A widget nobody has been offered yet starts switched off.
	 *
	 * WHY THIS IS NOT `default_hidden_meta_boxes`. That filter only runs for a
	 * user who has never saved a dashboard layout. Anybody who has ever dragged
	 * a widget or opened Screen Options has a saved list, a newly registered
	 * widget is not in it, and WordPress shows it. Five panels appearing unasked
	 * on the dashboard of every user who has ever tidied theirs is the opposite
	 * of switching them off by default.
	 *
	 * So the ids offered on this request are compared with the ones this user
	 * has been offered before. Anything new is hidden and recorded, and from
	 * then on the user's own choice is the only thing that decides. Only widgets
	 * actually registered this request are recorded, so a monitoring panel that
	 * could not be offered today is still new on the day it can be.
	 *
	 * @param array      $hidden Widget ids WordPress is about to hide.
	 * @param \WP_Screen $screen The screen being drawn.
	 * @return array
	 */
	public static function hide_new_ones( $hidden, $screen ) {
		if ( ! is_object( $screen ) || 'dashboard' !== $screen->id || ! self::$registered ) {
			return $hidden;
		}

		$offered = array_values( array_intersect( self::extras(), self::$registered ) );
		$seen    = get_user_option( self::SEEN );
		$seen    = is_array( $seen ) ? $seen : array();
		$fresh   = self::newly_offered( $offered, $seen );

		if ( ! $fresh ) {
			return $hidden;
		}

		update_user_option(
			get_current_user_id(),
			self::SEEN,
			array_values( array_unique( array_merge( $seen, $offered ) ) )
		);

		return array_values( array_unique( array_merge( (array) $hidden, $fresh ) ) );
	}

	/**
	 * Which of the widgets offered this time are ones this user has not met.
	 *
	 * The arithmetic of hide_new_ones(), lifted out so it can be tested against
	 * a list rather than against a dashboard, a user and a database. The full
	 * panel is never in the answer, because extras() is what is intersected.
	 *
	 * @param array $offered Widget ids registered this request.
	 * @param array $seen    Widget ids this user has been offered before.
	 * @return array Widget ids to hide.
	 */
	public static function newly_offered( array $offered, array $seen ) {
		return array_values( array_diff( array_intersect( self::extras(), $offered ), $seen ) );
	}

	/**
	 * Everything the sections draw, worked out once however many are switched on.
	 *
	 * SIX WIDGETS MUST NOT MEAN SIX PASSES OVER THE POST TABLE. Somebody with
	 * every panel ticked would otherwise pay for the score summary six times on
	 * every Dashboard load, on a shop with two thousand products.
	 *
	 * @return array
	 */
	public static function data() {
		if ( null !== self::$data ) {
			return self::$data;
		}

		$summary = Score_Report::summary();

		self::$data = array(
			'summary'   => $summary,
			'band'      => Analyser::band( $summary['average'] ),
			'label'     => Analyser::band_label( Analyser::band( $summary['average'] ) ),
			'weakest'   => Score_Report::weakest( 3 ),
			'counts'    => self::counts( $summary ),
			'checks'    => self::checks(),
			'connected' => Connection::is_connected(),
			'account'   => Connection::summary(),
			'site'      => self::site(),
			'monitor'   => self::monitor(),
		);

		return self::$data;
	}

	/**
	 * Forget the figures, so a later render in the same request reads them again.
	 *
	 * Nothing in wp-admin needs this. The probes do, because they draw the same
	 * widget either side of changing what it should say.
	 */
	public static function forget() {
		self::$data       = null;
		self::$registered = array();
	}

	/**
	 * The numbers worth a glance, all of them worked out on this site.
	 *
	 * @param array $summary What Score_Report::summary() answered.
	 * @return array Keyed by what the number counts.
	 */
	protected static function counts( $summary ) {
		return array(
			'scored'     => (int) $summary['total'],
			'unscored'   => Score_Report::unscored(),
			'no_summary' => Score_Report::missing_description(),
			'hidden'     => Score_Report::hidden_count(),
			'redirects'  => Manager::count(),
			'not_found'  => Log::count(),
		);
	}

	/**
	 * The settings that stop a site being found at all.
	 *
	 * None of them needs the account, the network or a crawl, which is why they
	 * are the ones drawn for a site that has never connected to anything.
	 *
	 * @return array Each entry has status, label, note and href.
	 */
	protected static function checks() {
		$checks = array();

		if ( ! (int) get_option( 'blog_public' ) ) {
			$checks[] = array(
				'status' => 'poor',
				'label'  => __( 'Search engines are being asked to stay away', 'solseo' ),
				'note'   => __( 'Settings, Reading', 'solseo' ),
				'href'   => admin_url( 'options-reading.php' ),
			);
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			$checks[] = array(
				'status' => 'poor',
				'label'  => __( 'Addresses are still numbered', 'solseo' ),
				'note'   => __( 'Settings, Permalinks', 'solseo' ),
				'href'   => admin_url( 'options-permalink.php' ),
			);
		}

		if ( ! Options::get( 'sitemap_enabled' ) ) {
			$checks[] = array(
				'status' => 'fair',
				'label'  => __( 'The XML sitemap is switched off', 'solseo' ),
				'note'   => __( 'Technical, Sitemap', 'solseo' ),
				'href'   => admin_url( 'admin.php?page=solseo-technical&tab=sitemap' ),
			);
		}

		return $checks;
	}

	/**
	 * This site's row from the service, and no other site's.
	 *
	 * @return array Empty when the service has sent nothing, or nothing that is this site.
	 */
	protected static function site() {
		if ( ! Connection::is_connected() ) {
			return array();
		}

		$stored = get_transient( 'solseo_hub_overview' );

		if ( ! is_array( $stored ) || empty( $stored['sites'] ) || ! is_array( $stored['sites'] ) ) {
			return array();
		}

		return self::this_site( $stored['sites'], (int) Connection::get( 'site_id' ), home_url() );
	}

	/**
	 * Pick this site out of a list of them.
	 *
	 * THREE WAYS TO SAY YES AND NO WAY TO GUESS. `is_this_site` is what the hub
	 * marks the paired row with, the stored site id is what pairing wrote down,
	 * and the address is the last resort for a connection stored before either
	 * existed. When none of the three matches, this answers with nothing: a
	 * widget showing the wrong site is the failure this method exists to stop,
	 * and "the first row" is how it would happen.
	 *
	 * Static and passed its arguments so the choice can be tested without a
	 * connection, a transient or a network.
	 *
	 * @param array  $sites    Rows from the service.
	 * @param int    $site_id  The site id pairing stored, if any.
	 * @param string $home_url This site's address.
	 * @return array The matching row, or an empty array.
	 */
	public static function this_site( $sites, $site_id = 0, $home_url = '' ) {
		$home = untrailingslashit( strtolower( (string) $home_url ) );

		foreach ( (array) $sites as $site ) {
			if ( ! is_array( $site ) ) {
				continue;
			}

			if ( ! empty( $site['is_this_site'] ) ) {
				return $site;
			}

			if ( $site_id && isset( $site['site_id'] ) && (int) $site['site_id'] === (int) $site_id ) {
				return $site;
			}

			if ( '' !== $home && isset( $site['home_url'] ) && untrailingslashit( strtolower( (string) $site['home_url'] ) ) === $home ) {
				return $site;
			}
		}

		return array();
	}

	/**
	 * What the service last measured about this site from outside it.
	 *
	 * @return array Empty when nothing is watching this site.
	 */
	protected static function monitor() {
		if ( ! Connection::is_connected() ) {
			return array();
		}

		$stored = get_transient( 'solseo_hub_overview' );

		if ( ! is_array( $stored ) || empty( $stored['monitor'] ) || ! is_array( $stored['monitor'] ) ) {
			return array();
		}

		return empty( $stored['monitor']['watched'] ) ? array() : $stored['monitor'];
	}
}
