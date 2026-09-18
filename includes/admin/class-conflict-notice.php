<?php
/**
 * The one notice this plugin prints, and the reason it is allowed to.
 *
 * Two SEO plugins running at once write two title tags, two meta descriptions
 * and two sets of structured data into the same page. Search engines pick one,
 * and which one is not something the site owner chose. It is the single
 * problem that a person cannot see by looking at their own site, and it is
 * caused by installing this plugin, so it is this plugin's job to say so.
 *
 * Every other notice is somebody else's idea of urgent. There are none.
 *
 * What keeps this honest, and what the guard test pins:
 *
 * - It shows on three screens: the Plugins list, the WordPress dashboard and
 *   our own. Never site wide.
 * - Dismissing it is permanent and per person, in user meta rather than in a
 *   transient that comes back next week.
 * - It is dismissed per plugin, so saying no to one does not hide a different
 *   one turning up next month.
 * - It disappears by itself the moment the other plugin is switched off,
 *   because the question is asked afresh every time rather than cached.
 * - Its one button goes to our import screen. There is no upsell in it.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Tools\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Warns when another SEO plugin is writing the same tags.
 */
class Conflict_Notice {

	/** Where a person's dismissals are kept. */
	const META = 'solseo_conflict_dismissed';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_init', array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * Whether the notice belongs on this screen, for this person.
	 *
	 * Takes its facts as arguments so the decision can be tested without
	 * WordPress loaded. The hook below is the only thing that gathers them.
	 *
	 * @param string $screen_id  The screen being drawn.
	 * @param array  $conflicts  Source prefixes with an active plugin.
	 * @param array  $dismissed  Source prefixes this person has dismissed.
	 * @param bool   $may        Whether this person manages the site.
	 * @return array The prefixes worth warning about.
	 */
	public static function should_show( $screen_id, array $conflicts, array $dismissed, $may ) {
		if ( ! $may ) {
			return array();
		}

		$ours = false !== strpos( (string) $screen_id, 'solseo' );

		if ( ! $ours && ! in_array( $screen_id, array( 'plugins', 'plugins-network', 'dashboard' ), true ) ) {
			return array();
		}

		return array_values( array_diff( $conflicts, $dismissed ) );
	}

	/**
	 * Print it, when it belongs here.
	 */
	public static function render() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return;
		}

		$conflicts = Import::conflicts();

		$show = self::should_show(
			$screen->id,
			array_keys( $conflicts ),
			self::dismissed(),
			current_user_can( Menu::capability() )
		);

		if ( ! $show ) {
			return;
		}

		foreach ( $show as $prefix ) {
			self::one( $prefix, $conflicts[ $prefix ] );
		}
	}

	/**
	 * One warning, about one plugin.
	 *
	 * @param string $prefix   Source prefix.
	 * @param array  $conflict Name, plugin file and how much it holds.
	 */
	protected static function one( $prefix, array $conflict ) {
		$import = add_query_arg(
			array(
				'page'   => Tools_Screen::PAGE,
				'tab'    => 'import',
				'source' => $prefix,
			),
			admin_url( 'admin.php' )
		);

		$hide = wp_nonce_url(
			add_query_arg( 'solseo_dismiss_conflict', $prefix ),
			'solseo_dismiss_conflict'
		);

		echo '<div class="notice notice-warning solseo-conflict"><p>';

		printf(
			/* translators: %s: the name of the other SEO plugin. */
			esc_html__( '%s and SolSEO are both writing title tags and meta descriptions, so this site is sending Google two of each.', 'solseo' ),
			'<strong>' . esc_html( $conflict['name'] ) . '</strong>'
		);

		echo ' ';

		if ( $conflict['found'] ) {
			printf(
				/* translators: %s: number of pages holding the other plugin's fields. */
				esc_html( _n( 'Copy its %s page across first, then switch it off.', 'Copy its %s pages across first, then switch it off.', (int) $conflict['found'], 'solseo' ) ),
				esc_html( number_format_i18n( (int) $conflict['found'] ) )
			);
		} else {
			esc_html_e( 'Switch one of them off.', 'solseo' );
		}

		echo '</p><p>';

		printf(
			'<a class="button button-primary" href="%s">%s</a> <a href="%s">%s</a>',
			esc_url( $import ),
			esc_html__( 'Open the import screen', 'solseo' ),
			esc_url( $hide ),
			esc_html__( 'Do not show this again', 'solseo' )
		);

		echo '</p></div>';
	}

	/**
	 * Remember that somebody does not want to be told about one of these.
	 *
	 * A nonced link and a redirect, rather than a script and an endpoint, for
	 * one link on three screens.
	 */
	public static function dismiss() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked on the next line.
		if ( empty( $_GET['solseo_dismiss_conflict'] ) ) {
			return;
		}

		check_admin_referer( 'solseo_dismiss_conflict' );

		if ( ! current_user_can( Menu::capability() ) ) {
			return;
		}

		$prefix    = sanitize_text_field( wp_unslash( $_GET['solseo_dismiss_conflict'] ) );
		$dismissed = self::dismissed();

		if ( ! in_array( $prefix, $dismissed, true ) ) {
			$dismissed[] = $prefix;

			update_user_meta( get_current_user_id(), self::META, $dismissed );
		}

		wp_safe_redirect( remove_query_arg( array( 'solseo_dismiss_conflict', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Which of these this person has already said no to.
	 *
	 * @return array Source prefixes.
	 */
	protected static function dismissed() {
		$stored = get_user_meta( get_current_user_id(), self::META, true );

		return is_array( $stored ) ? $stored : array();
	}
}
