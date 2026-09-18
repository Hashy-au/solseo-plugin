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
 */
class Menu {

	const SLUG = 'solseo';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register' ) );
	}

	/**
	 * The screens, in menu order.
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
			'solseo-sitemap'   => array(
				'title'  => __( 'Sitemap', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Sitemap_Screen',
			),
			'solseo-redirects' => array(
				'title'  => __( 'Redirects', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Redirects_Screen',
			),
			'solseo-tools'     => array(
				'title'  => __( 'Tools', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Tools_Screen',
			),
			'solseo-connect'   => array(
				'title'  => __( 'Connect', 'solseo' ),
				'screen' => __NAMESPACE__ . '\\Connect_Screen',
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
		 * @param array $screens Screens in menu order.
		 */
		$screens = (array) apply_filters( 'solseo_admin_screens', $screens );

		return array_filter(
			$screens,
			static function ( $screen ) {
				return is_array( $screen ) && ! empty( $screen['screen'] ) && is_callable( array( $screen['screen'], 'render' ) );
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
