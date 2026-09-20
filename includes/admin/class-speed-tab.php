<?php
/**
 * How fast one page is, and what is making it slow.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Connect\Keys;
use SolSEO\Connect\Psi;

defined( 'ABSPATH' ) || exit;

/**
 * Health, Speed.
 *
 * The check runs inside the request that asks for it, which is unusual for a
 * call that takes twenty seconds. The alternative is a REST route and a
 * spinner, and that is a build step and a script for one button. The button
 * says how long it takes, and the time limit is lifted for the one call.
 */
class Speed_Tab extends Screen {

	const PAGE = 'solseo-health';

	const TAB = 'speed';

	/** Where the last answer is kept between page loads. */
	const LAST = 'solseo_speed_last';

	/**
	 * Run a check when one is asked for.
	 */
	public static function load() {
		if ( ! self::submitted( 'solseo_speed_run' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on this line.
		$url = isset( $_POST['solseo_speed_url'] ) ? esc_url_raw( wp_unslash( $_POST['solseo_speed_url'] ) ) : home_url( '/' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$strategy = isset( $_POST['solseo_speed_strategy'] ) && 'desktop' === $_POST['solseo_speed_strategy'] ? 'desktop' : 'mobile';

		if ( ! self::ours( $url ) ) {
			self::remember( __( 'That address is not on this site. The check only looks at pages you run.', 'solseo' ), 'error' );
			self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
		}

		/*
		 * Lighthouse takes its time on Google's side and we wait for it. Thirty
		 * seconds is the usual limit and this usually needs twenty, which is
		 * too close to rely on.
		 */
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- the wait on Google is longer than the default limit on some hosts; refused outright on others, and nothing here depends on it working.
			@set_time_limit( 120 );
		}

		$result = Psi::run( $url, $strategy, true );

		if ( is_wp_error( $result ) ) {
			self::remember( $result->get_error_message(), 'error' );
			delete_transient( self::LAST );
		} else {
			set_transient(
				self::LAST,
				array(
					'url'      => $url,
					'strategy' => $strategy,
					'result'   => $result,
				),
				DAY_IN_SECONDS
			);
		}

		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::notice();

		$last = get_transient( self::LAST );

		self::view(
			'health-speed',
			array(
				'has_key'  => Keys::has( Keys::GOOGLE ),
				'url'      => is_array( $last ) ? $last['url'] : home_url( '/' ),
				'strategy' => is_array( $last ) ? $last['strategy'] : 'mobile',
				'result'   => is_array( $last ) ? $last['result'] : null,
				'settings' => admin_url( 'admin.php?page=solseo-settings&tab=connections' ),
			)
		);
	}

	/**
	 * Whether an address belongs to this site.
	 *
	 * @param string $url The address.
	 * @return bool
	 */
	protected static function ours( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );

		return $host && wp_parse_url( home_url(), PHP_URL_HOST ) === $host;
	}

	/**
	 * What one field measurement is called, and in what unit.
	 *
	 * @param string $metric The metric name Google uses.
	 * @return array Keys: label, unit.
	 */
	public static function measure( $metric ) {
		$known = array(
			'LARGEST_CONTENTFUL_PAINT_MS'     => array(
				'label' => __( 'Time until the main thing appears', 'solseo' ),
				'unit'  => 'ms',
			),
			'INTERACTION_TO_NEXT_PAINT'       => array(
				'label' => __( 'Delay after a tap or a click', 'solseo' ),
				'unit'  => 'ms',
			),
			'CUMULATIVE_LAYOUT_SHIFT_SCORE'   => array(
				'label' => __( 'How much the page jumps about while loading', 'solseo' ),
				'unit'  => 'cls',
			),
			'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => array(
				'label' => __( 'Time before the server answers at all', 'solseo' ),
				'unit'  => 'ms',
			),
		);

		return isset( $known[ $metric ] )
			? $known[ $metric ]
			: array(
				'label' => $metric,
				'unit'  => '',
			);
	}

	/**
	 * A measurement, said the way a person says it.
	 *
	 * @param int    $value The number Google gave.
	 * @param string $unit  Which kind of number it is.
	 * @return string
	 */
	public static function said( $value, $unit ) {
		$value = (int) $value;

		if ( 'cls' === $unit ) {
			return number_format_i18n( $value / 100, 2 );
		}

		if ( $value >= 1000 ) {
			/* translators: %s: a number of seconds, such as 3.1. */
			return sprintf( __( '%s seconds', 'solseo' ), number_format_i18n( $value / 1000, 1 ) );
		}

		/* translators: %d: a number of milliseconds. */
		return sprintf( __( '%d milliseconds', 'solseo' ), $value );
	}

	/**
	 * What Google's band is called in plain words.
	 *
	 * @param string $band FAST, AVERAGE or SLOW.
	 * @return string
	 */
	public static function band( $band ) {
		$bands = array(
			'FAST'    => __( 'Good', 'solseo' ),
			'GOOD'    => __( 'Good', 'solseo' ),
			'AVERAGE' => __( 'Could be better', 'solseo' ),
			'SLOW'    => __( 'Poor', 'solseo' ),
		);

		return isset( $bands[ $band ] ) ? $bands[ $band ] : '';
	}
}
