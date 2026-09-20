<?php
/**
 * SolSEO's tests inside WordPress's own Site Health screen.
 *
 * WORKS WITH THE NETWORK UNPLUGGED. Every test here is an option read or one
 * indexed COUNT, so they are registered as direct tests and answer on the same
 * page load. Nothing in this file makes a request, names an account, or
 * mentions anything that costs money: Site Health is WordPress's screen rather
 * than ours, and a plugin that turns it into a shop window is a plugin whose
 * warnings people learn to scroll past.
 *
 * @package SolSEO
 */

namespace SolSEO\Health;

use SolSEO\Admin\Dashboard_Screen;
use SolSEO\Hub\Connection;
use SolSEO\Options;
use SolSEO\Score_Report;
use SolSEO\Tools\Alt_Text;

defined( 'ABSPATH' ) || exit;

/**
 * Seven tests, and one section on the Info tab.
 */
class Site_Health {

	/**
	 * Every test name starts with this, so ours are ours.
	 */
	const PREFIX = 'solseo_';

	/**
	 * Hook in.
	 *
	 * NOT inside the admin branch. WordPress runs the direct tests from a
	 * weekly cron event to fill the count on its dashboard widget, and that
	 * request is not an admin request. Two filter registrations cost nothing.
	 */
	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'register_tests' ) );
		add_filter( 'debug_information', array( __CLASS__, 'debug_information' ) );
	}

	/**
	 * The tests, in the order they are worth reading.
	 *
	 * @return array Test name without the prefix, to the method that answers it.
	 */
	public static function tests() {
		return array(
			'search_visibility' => 'test_search_visibility',
			'permalinks'        => 'test_permalinks',
			'sitemap'           => 'test_sitemap',
			'structured_data'   => 'test_structured_data',
			'descriptions'      => 'test_descriptions',
			'alt_text'          => 'test_alt_text',
			'hidden_pages'      => 'test_hidden_pages',
		);
	}

	/**
	 * Add ours to the list WordPress runs.
	 *
	 * @param array $tests The registered tests.
	 * @return array
	 */
	public static function register_tests( $tests ) {
		if ( ! is_array( $tests ) ) {
			$tests = array();
		}

		if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			$tests['direct'] = array();
		}

		foreach ( self::tests() as $name => $method ) {
			$tests['direct'][ self::PREFIX . $name ] = array(
				'label' => __( 'SolSEO', 'solseo' ),
				'test'  => array( __CLASS__, $method ),
			);
		}

		return $tests;
	}

	/**
	 * Whether search engines are allowed to read the site at all.
	 *
	 * @return array
	 */
	public static function test_search_visibility() {
		$checks = Dashboard_Screen::site_checks();
		$check  = $checks['search'];

		return self::result(
			array(
				'test'       => 'search_visibility',
				'label'      => __( 'Search engines are being asked to stay away', 'solseo' ),
				'label_good' => __( 'Search engines can read the site', 'solseo' ),
				'status'     => $check['status'],
				'fault'      => 'critical',
				'note'       => $check['note'],
				'actions'    => self::action( admin_url( 'options-reading.php' ), __( 'Open Settings, Reading', 'solseo' ) ),
			)
		);
	}

	/**
	 * Whether addresses have words in them.
	 *
	 * @return array
	 */
	public static function test_permalinks() {
		$checks = Dashboard_Screen::site_checks();
		$check  = $checks['permalinks'];

		return self::result(
			array(
				'test'       => 'permalinks',
				'label'      => __( 'Addresses are numbers rather than words', 'solseo' ),
				'label_good' => __( 'Pages have words in their addresses', 'solseo' ),
				'status'     => $check['status'],
				'note'       => $check['note'],
				'actions'    => self::action( admin_url( 'options-permalink.php' ), __( 'Choose a permalink structure', 'solseo' ) ),
			)
		);
	}

	/**
	 * Whether the sitemap is being served.
	 *
	 * @return array
	 */
	public static function test_sitemap() {
		$on = (bool) Options::get( 'sitemap_enabled' );

		return self::result(
			array(
				'test'       => 'sitemap',
				'label'      => __( 'The XML sitemap is switched off', 'solseo' ),
				'label_good' => __( 'The XML sitemap is on', 'solseo' ),
				'status'     => $on ? 'good' : 'fair',
				'note'       => $on
					? sprintf(
						/* translators: %s: the address of the sitemap. */
						__( 'The list of pages you want found is served at %s.', 'solseo' ),
						esc_url( home_url( '/sitemap.xml' ) )
					)
					: __( 'A sitemap is how a search engine learns about a new page without waiting to come across a link to it. Turn it on under SolSEO, Sitemap.', 'solseo' ),
				'actions'    => self::action( admin_url( 'admin.php?page=solseo-technical&tab=sitemap' ), __( 'Open the Sitemap screen', 'solseo' ) ),
			)
		);
	}

	/**
	 * Whether the site describes itself in a form search engines read.
	 *
	 * @return array
	 */
	public static function test_structured_data() {
		$on    = (bool) Options::get( 'schema_enabled' );
		$named = '' !== (string) Options::get( 'entity_name' );

		if ( ! $on ) {
			$status = 'fair';
			$note   = __( 'Structured data tells a search engine who is behind the site and what each page is. Turn it on under SolSEO, Titles and Meta.', 'solseo' );
		} elseif ( ! $named ) {
			$status = 'fair';
			$note   = __( 'The markup is being written, and it does not say who the site belongs to. Set the organisation or person name under SolSEO, Titles and Meta.', 'solseo' );
		} else {
			$status = 'good';
			$note   = __( 'The site describes itself to search engines, and says who it belongs to.', 'solseo' );
		}

		return self::result(
			array(
				'test'       => 'structured_data',
				'label'      => $on
					? __( 'The structured data does not say who the site belongs to', 'solseo' )
					: __( 'Structured data is switched off', 'solseo' ),
				'label_good' => __( 'The site describes itself to search engines', 'solseo' ),
				'status'     => $status,
				'note'       => $note,
				'actions'    => self::action( admin_url( 'admin.php?page=solseo-titles' ), __( 'Open Titles and Meta', 'solseo' ) ),
			)
		);
	}

	/**
	 * Pages whose line in a search result nobody has written.
	 *
	 * @return array
	 */
	public static function test_descriptions() {
		$missing = Score_Report::missing_description();

		return self::result(
			array(
				'test'       => 'descriptions',
				'label'      => __( 'Some pages have no description of their own', 'solseo' ),
				'label_good' => __( 'Every published page has a description of its own', 'solseo' ),
				'status'     => $missing > 0 ? 'fair' : 'good',
				'note'       => $missing > 0
					? sprintf(
						/* translators: %s: how many published pages have no description. */
						__( '%s published pages have no description written for them. SolSEO fills the gap from the page itself, and a line somebody wrote reads better in a search result than a line cut out of the first paragraph.', 'solseo' ),
						number_format_i18n( $missing )
					)
					: __( 'Whoever knows what each page is for has written the line that appears under it in a search result.', 'solseo' ),
				'actions'    => self::action( admin_url( 'admin.php?page=solseo' ), __( 'Open the SolSEO dashboard', 'solseo' ) ),
			)
		);
	}

	/**
	 * Images nobody has described.
	 *
	 * @return array
	 */
	public static function test_alt_text() {
		$missing = Alt_Text::count_missing();

		return self::result(
			array(
				'test'       => 'alt_text',
				'label'      => __( 'Some images have no alt text', 'solseo' ),
				'label_good' => __( 'Every image has alt text', 'solseo' ),
				'status'     => $missing > 0 ? 'fair' : 'good',
				'note'       => $missing > 0
					? sprintf(
						/* translators: %s: how many images in the library have no alt text. */
						__( '%s images in the media library have no alt text. That text is what a screen reader says out loud, and it is the only thing an image search has to go on. SolSEO can write a first draft for each one.', 'solseo' ),
						number_format_i18n( $missing )
					)
					: __( 'Every image in the library carries a description, so a screen reader and an image search both have something to read.', 'solseo' ),
				'actions'    => self::action( admin_url( 'admin.php?page=solseo-tools' ), __( 'Describe the images', 'solseo' ) ),
			)
		);
	}

	/**
	 * Pages deliberately kept out of search.
	 *
	 * ALWAYS GOOD. Hiding a page is usually the right answer, and a plugin that
	 * marks a deliberate decision as a fault is a plugin whose warnings nobody
	 * reads. The count is here so somebody who did not expect a number can go
	 * and look at it.
	 *
	 * @return array
	 */
	public static function test_hidden_pages() {
		$hidden = Score_Report::hidden_count();

		return self::result(
			array(
				'test'   => 'hidden_pages',
				'label'  => $hidden > 0
					? __( 'Some pages are kept out of search results on purpose', 'solseo' )
					: __( 'No page is kept out of search results by a SolSEO setting', 'solseo' ),
				'status' => 'good',
				'note'   => $hidden > 0
					? sprintf(
						/* translators: %s: how many published pages are set to stay out of search. */
						__( '%s published pages carry the setting that keeps them out of search results. That is usually on purpose. If one of them should be found, open it and untick the setting in the SolSEO panel.', 'solseo' ),
						number_format_i18n( $hidden )
					)
					: __( 'Nothing published is being held back from search by a setting in this plugin.', 'solseo' ),
			)
		);
	}

	/**
	 * One of our checks, in the shape WordPress draws.
	 *
	 * Pure: everything it needs is in the array it is handed.
	 *
	 * @param array $check Keys: test, label, status, note. Optional: label_good, fault, actions.
	 * @return array
	 */
	public static function result( array $check ) {
		$status = self::status_for(
			isset( $check['status'] ) ? $check['status'] : 'fair',
			isset( $check['fault'] ) ? $check['fault'] : 'recommended'
		);

		$label = isset( $check['label'] ) ? $check['label'] : '';

		if ( 'good' === $status && isset( $check['label_good'] ) ) {
			$label = $check['label_good'];
		}

		return array(
			'test'        => self::PREFIX . ( isset( $check['test'] ) ? $check['test'] : '' ),
			'label'       => $label,
			'status'      => $status,
			'badge'       => self::badge(),
			'description' => '<p>' . ( isset( $check['note'] ) ? $check['note'] : '' ) . '</p>',
			'actions'     => isset( $check['actions'] ) ? $check['actions'] : '',
		);
	}

	/**
	 * Our three words, in the three WordPress uses.
	 *
	 * Pure.
	 *
	 * @param string $status One of good, fair or poor.
	 * @param string $fault  What a poor answer counts as.
	 * @return string
	 */
	public static function status_for( $status, $fault = 'recommended' ) {
		if ( 'good' === $status ) {
			return 'good';
		}

		if ( 'poor' === $status ) {
			return 'critical' === $fault ? 'critical' : 'recommended';
		}

		return 'recommended';
	}

	/**
	 * The badge every one of our tests carries.
	 *
	 * Pure.
	 *
	 * @return array
	 */
	public static function badge() {
		return array(
			'label' => __( 'SEO', 'solseo' ),
			'color' => 'blue',
		);
	}

	/**
	 * One link under a test.
	 *
	 * Pure.
	 *
	 * @param string $url  Where it goes.
	 * @param string $text What it is called.
	 * @return string
	 */
	public static function action( $url, $text ) {
		return sprintf( '<p><a href="%s">%s</a></p>', esc_url( $url ), esc_html( $text ) );
	}

	/**
	 * A SolSEO section on the Info tab.
	 *
	 * @param array $info The sections.
	 * @return array
	 */
	public static function debug_information( $info ) {
		if ( ! is_array( $info ) ) {
			$info = array();
		}

		$info['solseo'] = array(
			'label'       => __( 'SolSEO', 'solseo' ),
			'description' => __( 'What SolSEO is doing on this site. Nothing in this list leaves the site.', 'solseo' ),
			'fields'      => self::debug_fields( self::facts() ),
		);

		return $info;
	}

	/**
	 * Plain values, as rows for the Info tab.
	 *
	 * Pure. A boolean reads as a word, because Yes and No are what somebody
	 * pasting this into a support thread needs to see.
	 *
	 * @param array $facts Gathered by facts().
	 * @return array
	 */
	public static function debug_fields( array $facts ) {
		$labels = array(
			'version'     => __( 'Version', 'solseo' ),
			'post_types'  => __( 'Post types scored', 'solseo' ),
			'sitemap'     => __( 'XML sitemap', 'solseo' ),
			'schema'      => __( 'Structured data', 'solseo' ),
			'scored'      => __( 'Pages with a score', 'solseo' ),
			'average'     => __( 'Average score', 'solseo' ),
			'no_alt_text' => __( 'Images with no alt text', 'solseo' ),
			'connected'   => __( 'Connected to an account', 'solseo' ),
		);

		$fields = array();

		foreach ( $labels as $key => $label ) {
			if ( ! array_key_exists( $key, $facts ) ) {
				continue;
			}

			$value = $facts[ $key ];

			if ( is_bool( $value ) ) {
				$value = $value ? __( 'Yes', 'solseo' ) : __( 'No', 'solseo' );
			}

			$fields[ $key ] = array(
				'label'   => $label,
				'value'   => (string) $value,
				'private' => false,
			);
		}

		return $fields;
	}

	/**
	 * What this site is doing, as plain values.
	 *
	 * @return array
	 */
	protected static function facts() {
		$summary = Score_Report::summary();

		return array(
			'version'     => SOLSEO_VERSION,
			'post_types'  => implode( ', ', solseo_post_types() ),
			'sitemap'     => (bool) Options::get( 'sitemap_enabled' ),
			'schema'      => (bool) Options::get( 'schema_enabled' ),
			'scored'      => sprintf(
				/* translators: 1: pages carrying a score, 2: published pages. */
				__( '%1$s of %2$s published pages', 'solseo' ),
				number_format_i18n( (int) $summary['total'] ),
				number_format_i18n( Score_Report::countable( 'all' ) )
			),
			'average'     => number_format_i18n( (int) $summary['average'] ),
			'no_alt_text' => number_format_i18n( Alt_Text::count_missing() ),
			'connected'   => Connection::is_connected(),
		);
	}
}
