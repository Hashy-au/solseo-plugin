<?php
/**
 * Which tabs a screen has.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One way a tab exists.
 *
 * This plugin declares its own tabs in defaults(). An add-on adds one through
 * `solseo_screen_tabs`. Both arrive here to be normalised, ordered and checked,
 * so there is a single answer to "what is on this screen" rather than one per
 * screen class.
 *
 * A tab is the only surface an add-on gets. The menu itself belongs to this
 * plugin and to nothing else: an add-on that could add a menu item would add
 * one, and five add-ons later the sidebar is the reason people uninstall SEO
 * plugins. See design/batches/Sidebar.md.
 */
class Tabs {

	/** Where a tab sits when it does not say. */
	const DEFAULT_ORDER = 50;

	/**
	 * The tabs this plugin puts on its own screens.
	 *
	 * Keyed by page slug, then by tab key. Order runs in tens so an add-on can
	 * land between two of ours without either of us renumbering.
	 *
	 * @return array
	 */
	public static function defaults() {
		$tabs = array(
			'solseo-titles'    => array(
				'general'    => array(
					'label'  => __( 'General', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Titles_Screen', 'tab_general' ),
					'order'  => 10,
				),
				'post_types' => array(
					'label'  => __( 'Post types', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Titles_Screen', 'tab_post_types' ),
					'order'  => 20,
				),
				'taxonomies' => array(
					'label'  => __( 'Taxonomies', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Titles_Screen', 'tab_taxonomies' ),
					'order'  => 30,
				),
				'social'     => array(
					'label'  => __( 'Social', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Titles_Screen', 'tab_social' ),
					'order'  => 40,
				),
			),

			'solseo-content'   => array(
				'links'   => array(
					'label'  => __( 'Links', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Suggestions_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Suggestions_Tab', 'load' ),
					'order'  => 10,
				),
				'phrases' => array(
					'label'  => __( 'Phrases', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Phrases_Tab', 'render' ),
					'order'  => 20,
				),
				'writing' => array(
					'label'  => __( 'Writing', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Writing_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Writing_Tab', 'load' ),
					'order'  => 30,
				),
			),

			'solseo-technical' => array(
				'sitemap'  => array(
					'label'  => __( 'Sitemap', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Sitemap_Screen', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Sitemap_Screen', 'load' ),
					'order'  => 10,
				),
				'robots'   => array(
					'label'  => __( 'robots.txt', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Robots_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Robots_Tab', 'load' ),
					'order'  => 20,
				),
				'indexing' => array(
					'label'  => __( 'Indexing', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Indexing_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Indexing_Tab', 'load' ),
					'order'  => 30,
				),
				'crawl'    => array(
					'label'  => __( 'Crawl', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Crawl_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Crawl_Tab', 'load' ),
					'order'  => 40,
				),
				'links'    => array(
					'label'  => __( 'Links checked', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Links_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Links_Tab', 'load' ),
					'order'  => 50,
				),
			),

			'solseo-health'    => array(
				'speed'         => array(
					'label'  => __( 'Speed', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Speed_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Speed_Tab', 'load' ),
					'order'  => 10,
				),
				'media'         => array(
					'label'  => __( 'Media', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Media_Tab', 'render' ),
					'order'  => 20,
				),
				'accessibility' => array(
					'label'  => __( 'Accessibility', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Accessibility_Tab', 'render' ),
					'order'  => 30,
				),
				'australia'     => array(
					'label'  => __( 'Australia', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Australia_Tab', 'render' ),
					'order'  => 40,
				),
			),

			'solseo-redirects' => array(
				'rules'     => array(
					'label'  => __( 'Rules', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Redirects_Screen', 'tab_rules' ),
					'order'  => 10,
				),
				'not_found' => array(
					'label'  => __( 'Found nothing', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Redirects_Screen', 'tab_not_found' ),
					'order'  => 20,
				),
			),

			'solseo-tools'     => array(
				'import' => array(
					'label'  => __( 'Import', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Tools_Screen', 'tab_import' ),
					'order'  => 10,
				),
				'images' => array(
					'label'  => __( 'Images', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Tools_Screen', 'tab_images' ),
					'order'  => 20,
				),
				'data'   => array(
					'label'  => __( 'Data', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Tools_Screen', 'tab_data' ),
					'order'  => 90,
				),
			),

			'solseo-settings'  => array(
				'features'    => array(
					'label'  => __( 'Features', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Features_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Features_Tab', 'load' ),
					'order'  => 10,
				),
				'connections' => array(
					'label'  => __( 'Connections', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Connect_Screen', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Connect_Screen', 'load' ),
					'order'  => 20,
				),
				'roles'       => array(
					'label'  => __( 'Roles', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Roles_Tab', 'render' ),
					'order'  => 30,
				),
				'log'         => array(
					'label'  => __( 'Change log', 'solseo' ),
					'render' => array( __NAMESPACE__ . '\\Log_Tab', 'render' ),
					'load'   => array( __NAMESPACE__ . '\\Log_Tab', 'load' ),
					'order'  => 40,
				),
			),
		);

		/*
		 * The feed audit is about products, so it is drawn on a site that has
		 * products and is not there at all on one that does not. A tab that
		 * says "you have no shop" is a tab somebody clicks once to find out it
		 * was never for them, and six tabs is the ceiling.
		 *
		 * It sits under Health because the Shop screen is empty until a pack
		 * fills it, and a screen with no tabs is not in the menu at all. It
		 * moves to Shop when that screen exists. See design/batches/FreeB4.md.
		 */
		if ( solseo_has_woocommerce() ) {
			$tabs['solseo-health']['findings'] = array(
				'label'  => __( 'Findings', 'solseo' ),
				'render' => array( __NAMESPACE__ . '\\Findings_Tab', 'render' ),
				'order'  => 50,
			);
		}

		return $tabs;
	}

	/**
	 * Every tab on a screen, in the order they are drawn.
	 *
	 * @param string $page Page slug.
	 * @return array Tab key to array( label, render, load, order ).
	 */
	public static function for_page( $page ) {
		$defaults = self::defaults();
		$tabs     = isset( $defaults[ $page ] ) ? $defaults[ $page ] : array();

		/**
		 * Filter the tabs on a SolSEO screen.
		 *
		 * Each entry is keyed by tab key and holds a label, a `render`
		 * callable, an optional `load` callable run before anything is
		 * printed, and an order. An entry whose render cannot be called is
		 * dropped rather than fataling half way down somebody's screen.
		 *
		 * @param array  $tabs Tabs on this screen.
		 * @param string $page Page slug.
		 */
		$tabs = (array) apply_filters( 'solseo_screen_tabs', $tabs, $page );

		return self::sort( self::clean( $tabs ) );
	}

	/**
	 * Whether a screen has anything on it.
	 *
	 * A screen with no tabs is not drawn and does not take a place in the
	 * menu, which is how the Shop and Local slots stay out of the sidebar
	 * until the add-on that fills them is installed.
	 *
	 * @param string $page Page slug.
	 * @return bool
	 */
	public static function any( $page ) {
		return (bool) self::for_page( $page );
	}

	/**
	 * Tab key to label, which is what the tab row is drawn from.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function labels( array $tabs ) {
		$labels = array();

		foreach ( $tabs as $key => $tab ) {
			$labels[ $key ] = $tab['label'];
		}

		return $labels;
	}

	/**
	 * Which tab is open, falling back to the first one on the screen.
	 *
	 * @param array $tabs Tabs.
	 * @return string
	 */
	public static function current( array $tabs ) {
		$keys = array_keys( $tabs );

		if ( ! $keys ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which tab to draw.
		$asked = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return isset( $tabs[ $asked ] ) ? $asked : $keys[0];
	}

	/**
	 * Draw one tab.
	 *
	 * @param array  $tabs Tabs.
	 * @param string $key  Which one.
	 */
	public static function render( array $tabs, $key ) {
		if ( isset( $tabs[ $key ]['render'] ) ) {
			call_user_func( $tabs[ $key ]['render'] );
		}
	}

	/**
	 * Run one tab's submission handler, if it has one.
	 *
	 * @param array  $tabs Tabs.
	 * @param string $key  Which one.
	 */
	public static function load( array $tabs, $key ) {
		if ( isset( $tabs[ $key ]['load'] ) && is_callable( $tabs[ $key ]['load'] ) ) {
			call_user_func( $tabs[ $key ]['load'] );
		}
	}

	/**
	 * Fill in what an entry left out, and drop the ones that cannot be drawn.
	 *
	 * @param array $tabs Raw tabs.
	 * @return array
	 */
	protected static function clean( array $tabs ) {
		$clean = array();

		foreach ( $tabs as $key => $tab ) {
			if ( ! is_array( $tab ) || empty( $tab['label'] ) || empty( $tab['render'] ) ) {
				continue;
			}

			if ( ! is_callable( $tab['render'] ) ) {
				continue;
			}

			$clean[ $key ] = array(
				'label'  => (string) $tab['label'],
				'render' => $tab['render'],
				'load'   => isset( $tab['load'] ) ? $tab['load'] : null,
				'order'  => isset( $tab['order'] ) ? (int) $tab['order'] : self::DEFAULT_ORDER,
			);
		}

		return $clean;
	}

	/**
	 * Order by the number each tab asked for, and by where it was declared
	 * when two ask for the same one.
	 *
	 * PHP 7.4 does not promise a stable sort, and two add-ons both taking the
	 * default order would otherwise swap places between requests on the same
	 * site, so the declaration order is carried in and compared.
	 *
	 * @param array $tabs Cleaned tabs.
	 * @return array
	 */
	protected static function sort( array $tabs ) {
		$position = 0;

		foreach ( $tabs as $key => $tab ) {
			$tabs[ $key ]['declared'] = $position;
			++$position;
		}

		uasort(
			$tabs,
			static function ( $a, $b ) {
				if ( $a['order'] === $b['order'] ) {
					return $a['declared'] <=> $b['declared'];
				}

				return $a['order'] <=> $b['order'];
			}
		);

		foreach ( $tabs as $key => $tab ) {
			unset( $tabs[ $key ]['declared'] );
		}

		return $tabs;
	}
}
