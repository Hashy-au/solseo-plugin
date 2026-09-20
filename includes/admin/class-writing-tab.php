<?php
/**
 * How this site writes.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Writing_Checks;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The writing profile: spelling, the two targets, and the words ruled out.
 */
class Writing_Tab extends Screen {

	const PAGE = 'solseo-content';

	const TAB = 'writing';

	/** Nobody needs a thousand banned words, and an unbounded option is a bug waiting. */
	const MAX_BANNED = 200;

	/**
	 * Save the profile.
	 */
	public static function load() {
		if ( ! self::submitted( 'solseo_writing' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is cast or cleaned below.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

		if ( ! is_array( $input ) ) {
			return;
		}

		$variant = isset( $input['writing_variant'] ) ? sanitize_key( $input['writing_variant'] ) : 'au';
		$labels  = Writing_Checks::labels();

		Options::update(
			array(
				'writing_variant'      => isset( $labels[ $variant ] ) ? $variant : 'au',
				'writing_sentence'     => max( 10, min( 60, (int) ( isset( $input['writing_sentence'] ) ? $input['writing_sentence'] : 20 ) ) ),
				'writing_reading_ease' => max( 20, min( 90, (int) ( isset( $input['writing_reading_ease'] ) ? $input['writing_reading_ease'] : 60 ) ) ),
				'writing_banned'       => self::clean_banned( isset( $input['writing_banned'] ) ? $input['writing_banned'] : '' ),
			)
		);

		self::remember( __( 'Saved.', 'solseo' ) );
		self::go_back( self::PAGE, array( 'tab' => self::TAB ) );
	}

	/**
	 * One word or phrase per line, lower case, no repeats.
	 *
	 * @param string $raw What was typed.
	 * @return array
	 */
	public static function clean_banned( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n|,/', (string) $raw );
		$clean = array();

		foreach ( (array) $lines as $line ) {
			$word = strtolower( trim( sanitize_text_field( $line ) ) );

			if ( '' !== $word && ! in_array( $word, $clean, true ) ) {
				$clean[] = $word;
			}
		}

		return array_slice( $clean, 0, self::MAX_BANNED );
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		$banned = Writing_Checks::banned_list();

		echo '<form method="post" class="solseo-form">';
		self::nonce( 'solseo_writing' );

		self::view(
			'content-writing',
			array(
				'variant'  => (string) Options::get( 'writing_variant' ),
				'labels'   => Writing_Checks::labels(),
				'sentence' => (int) Options::get( 'writing_sentence' ),
				'ease'     => (int) Options::get( 'writing_reading_ease' ),
				'banned'   => implode( "\n", $banned ),
			)
		);

		submit_button();

		echo '</form>';
	}
}
