<?php
/**
 * The SolSEO menu.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One top level menu and the screens under it.
 *
 * THIS IS THE ONLY PLACE A MENU ITEM IS DECLARED, in this plugin or in any
 * add-on. An add-on adds a tab to a screen named here, and the two screens it
 * is most likely to want, Shop and Local, are declared here already and stay
 * out of the sidebar until something fills them. The reason is stated in full
 * in design/batches/Sidebar.md: thirty eight features are coming, and a menu
 * that grows a line per feature is the thing that gets an SEO plugin deleted.
 *
 * Ten items is the ceiling and a test fails on the eleventh.
 */
class Menu {

	const SLUG = 'solseo';

	/** How many items may stand in the sidebar at once. */
	const MAX_ITEMS = 10;

	/**
	 * Screens that moved, and where they went.
	 *
	 * A bookmark does not expire and neither does a link in somebody's notes,
	 * so this is a permanent part of the menu rather than a migration. The
	 * cost is one array lookup on admin requests that carry a page we no
	 * longer register.
	 */
	const MOVED = array(
		'solseo-sitemap' => array(
			'page' => 'solseo-technical',
			'tab'  => 'sitemap',
		),
		'solseo-connect' => array(
			'page' => 'solseo-settings',
			'tab'  => 'connections',
		),
	);

	/**
	 * Tabs that moved to another screen, keyed by the page they left.
	 */
	const MOVED_TABS = array(
		'solseo-tools' => array(
			'robots' => array(
				'page' => 'solseo-technical',
				'tab'  => 'robots',
			),
		),
	);

	/**
	 * Hook in.
	 */
	public static function init() {
		/*
		 * The redirect runs on admin_menu and not on admin_init, which is the
		 * hook it would obviously belong on. WordPress resolves the page hook
		 * for `?page=` and refuses an unregistered one with a 403 before
		 * admin_init fires, so a redirect hooked there never runs for exactly
		 * the addresses it exists to catch. Found on a real WordPress rather
		 * than by reading.
		 */
		add_action( 'admin_menu', array( __CLASS__, 'redirect_moved' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'register' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_pages' ) );
	}

	/**
	 * The screens, in menu order.
	 *
	 * An entry may carry two flags. `tabs_only` means the screen is nothing
	 * but its tabs, so it is left out entirely while it has none. `hidden`
	 * means it is reachable at its own address but takes no place in the
	 * sidebar, which is what a one time wizard wants.
	 *
	 * @return array
	 */
	public static function screens() {
		$screens = array(
			self::SLUG         => array(
				'title'  => __( 'Dashboard', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Dashboard_Screen',
			),
			'solseo-titles'    => array(
				'title'  => __( 'Titles and Meta', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Titles_Screen',
			),
			'solseo-content'   => array(
				'title'     => __( 'Content', 'solseo' ),
				'screen'    => __NAMESPACE__ . '\\Content_Screen',
				'tabs_only' => true,
			),
			'solseo-technical' => array(
				'title'     => __( 'Technical', 'solseo' ),
				'screen'    => __NAMESPACE__ . '\\Technical_Screen',
				'tabs_only' => true,
			),
			'solseo-redirects' => array(
				'title'  => __( 'Redirects', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Redirects_Screen',
			),
			'solseo-health'    => array(
				'title'     => __( 'Health', 'solseo' ),
				'screen'    => __NAMESPACE__ . '\\Health_Screen',
				'tabs_only' => true,
			),
			'solseo-shop'      => array(
				'title'     => __( 'Shop', 'solseo' ),
				'screen'    => __NAMESPACE__ . '\\Shop_Screen',
				'tabs_only' => true,
			),
			'solseo-local'     => array(
				'title'     => __( 'Local', 'solseo' ),
				'screen'    => __NAMESPACE__ . '\\Local_Screen',
				'tabs_only' => true,
			),
			'solseo-tools'     => array(
				'title'  => __( 'Tools', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Tools_Screen',
			),
			'solseo-settings'  => array(
				'title'     => __( 'Settings', 'solseo' ),
				'screen'    => __NAMESPACE__ . '\\Settings_Screen',
				'tabs_only' => true,
			),

			/*
			 * Reachable, and not in the sidebar. Setup is answered once and
			 * then wanted about twice a year, which is a link from the
			 * Dashboard and from Tools rather than a line in the menu for
			 * ever. It keeps its own address, so every link already written
			 * to it still works.
			 */
			'solseo-setup'     => array(
				'title'  => __( 'Setup', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Setup_Screen',
				'hidden' => true,
			),
		);

		/*
		 * The upsell, and the only one in this plugin. It is a screen somebody
		 * has to click, it comes off the menu once the add-on is installed,
		 * and the filter below can drop it altogether.
		 */
		if ( Upgrade_Screen::available() ) {
			$screens[ Upgrade_Screen::PAGE ] = array(
				'title'   => __( 'Upgrade', 'solseo' ),
				'heading' => __( 'SolSEO Pro', 'solseo' ),
				'screen'  => __NAMESPACE__ . '\\Upgrade_Screen',
			);
		}

		/**
		 * Filter the screens under the SolSEO menu.
		 *
		 * Each entry is keyed by page slug and holds a title and the name of a
		 * class with a static render() method, and optionally a static load()
		 * for handling a submission.
		 *
		 * AN ADD-ON DOES NOT USE THIS. It is how this plugin declares its own
		 * screens and how a site owner removes one. An add-on adds a tab
		 * through `solseo_screen_tabs`, and each add-on carries a test that
		 * fails if it reaches for a menu item instead.
		 *
		 * @param array $screens Screens in menu order.
		 */
		$screens = (array) apply_filters( 'solseo_admin_screens', $screens );

		return array_filter(
			$screens,
			static function ( $screen, $slug ) {
				if ( ! is_array( $screen ) || empty( $screen['screen'] ) || ! is_callable( array( $screen['screen'], 'render' ) ) ) {
					return false;
				}

				// A screen that is only its tabs, with no tabs, is nothing.
				if ( ! empty( $screen['tabs_only'] ) && ! Tabs::any( $slug ) ) {
					return false;
				}

				return true;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * The screens that stand in the sidebar.
	 *
	 * @return array
	 */
	public static function listed() {
		return array_filter(
			self::screens(),
			static function ( $screen ) {
				return empty( $screen['hidden'] );
			}
		);
	}

	/**
	 * Add the menu.
	 */
	public static function register() {
		$capability = self::capability();

		add_menu_page(
			__( 'SolSEO', 'solseo' ),
			__( 'SolSEO', 'solseo' ),
			$capability,
			self::SLUG,
			array( __CLASS__, 'render' ),
			self::icon(),
			58
		);

		foreach ( self::screens() as $slug => $screen ) {
			$hook = add_submenu_page(
				self::SLUG,
				$screen['title'],
				$screen['title'],
				$capability,
				$slug,
				array( __CLASS__, 'render' )
			);

			if ( $hook && is_callable( array( $screen['screen'], 'load' ) ) ) {
				add_action( 'load-' . $hook, array( $screen['screen'], 'load' ) );
			}
		}
	}

	/**
	 * Take the hidden screens off the menu, once it is too late to matter.
	 *
	 * A hidden screen is registered like any other and removed from the menu
	 * array at the last possible moment, which is after WordPress has worked
	 * out which page it is drawing and before it prints the sidebar.
	 *
	 * Removing it any earlier breaks it. WordPress finds a plugin page's
	 * parent by walking the submenu array looking for the slug, so a slug that
	 * is no longer in there has no parent, resolves to no hook, and answers
	 * 403 to the person following a link we told them to follow. Found on a
	 * real WordPress, which is the only place it shows.
	 */
	public static function hide_pages() {
		foreach ( self::screens() as $slug => $screen ) {
			if ( ! empty( $screen['hidden'] ) ) {
				remove_submenu_page( self::SLUG, $slug );
			}
		}
	}

	/**
	 * Send an address that used to be a screen to wherever it lives now.
	 *
	 * Runs on every admin request, so it answers on the first line for the
	 * ones that are not ours.
	 */
	public static function redirect_moved() {
		if ( wp_doing_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which screen was asked for.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' === $page || 0 !== strpos( $page, 'solseo' ) ) {
			return;
		}

		$target = self::moved_to( $page );

		if ( ! $target ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- carrying the rest of the address across.
		$carried = array_diff_key( (array) $_GET, array_flip( array( 'page', 'tab' ) ) );

		wp_safe_redirect(
			add_query_arg(
				array_merge( $carried, $target ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Where an address goes now, or nothing when it has not moved.
	 *
	 * @param string $page Page slug that was asked for.
	 * @return array Page and tab, or an empty array.
	 */
	public static function moved_to( $page ) {
		if ( isset( self::MOVED[ $page ] ) ) {
			return self::MOVED[ $page ];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which tab was asked for.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( '' !== $tab && isset( self::MOVED_TABS[ $page ][ $tab ] ) ) {
			return self::MOVED_TABS[ $page ][ $tab ];
		}

		return array();
	}

	/**
	 * Render whichever screen is being asked for.
	 */
	public static function render() {
		$screens = self::screens();
		$slug    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which screen to draw.

		if ( ! isset( $screens[ $slug ] ) || ! current_user_can( self::capability() ) ) {
			return;
		}

		$heading = isset( $screens[ $slug ]['heading'] ) ? $screens[ $slug ]['heading'] : $screens[ $slug ]['title'];

		echo '<div class="wrap solseo-wrap">';
		printf( '<h1 class="solseo-title">%s</h1>', esc_html( $heading ) );

		call_user_func( array( $screens[ $slug ]['screen'], 'render' ) );

		self::colophon();

		echo '</div>';
	}

	/**
	 * One line at the foot of every SolSEO screen.
	 *
	 * The directory rules welcome a link to the developer's own site on the
	 * plugin's own pages, and this is the whole of what this plugin takes: no
	 * banner, no notice, and nothing on a screen belonging to anybody else.
	 */
	protected static function colophon() {
		printf(
			'<p class="solseo-colophon">%1$s <a href="%2$s" target="_blank" rel="noopener">%3$s</a></p>',
			esc_html( sprintf( /* translators: %s: version number. */ __( 'SolSEO %s', 'solseo' ), SOLSEO_VERSION ) ),
			esc_url( Upgrade_Screen::HOME ),
			esc_html( 'solseo.com.au' )
		);
	}

	/**
	 * Who may see the screens.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability the SolSEO screens require.
		 *
		 * @param string $capability Capability name.
		 */
		return apply_filters( 'solseo_admin_capability', 'manage_options' );
	}

	/**
	 * The menu icon.
	 *
	 * @return string
	 */
	protected static function icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="currentColor" d="M10 3.2a1 1 0 0 1 1 1v1.3a1 1 0 0 1-2 0V4.2a1 1 0 0 1 1-1Zm0 11a1 1 0 0 1 1 1v1.3a1 1 0 0 1-2 0v-1.3a1 1 0 0 1 1-1Zm6.8-4.2a1 1 0 0 1-1 1h-1.3a1 1 0 0 1 0-2h1.3a1 1 0 0 1 1 1Zm-11 0a1 1 0 0 1-1 1H3.5a1 1 0 0 1 0-2h1.3a1 1 0 0 1 1 1Zm8.7-4.9a1 1 0 0 1 0 1.4l-.9.9a1 1 0 1 1-1.4-1.4l.9-.9a1 1 0 0 1 1.4 0Zm-7.8 7.8a1 1 0 0 1 0 1.4l-.9.9a1 1 0 0 1-1.4-1.4l.9-.9a1 1 0 0 1 1.4 0Zm7.8 2.3a1 1 0 0 1-1.4 0l-.9-.9a1 1 0 0 1 1.4-1.4l.9.9a1 1 0 0 1 0 1.4ZM6.6 6.6a1 1 0 0 1-1.4 0l-.9-.9a1 1 0 0 1 1.4-1.4l.9.9a1 1 0 0 1 0 1.4ZM10 6.7a3.3 3.3 0 1 1 0 6.6 3.3 3.3 0 0 1 0-6.6Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the menu icon is passed to WordPress as a data URI.
	}
}
