<?php
/**
 * Import, bulk image work and the robots.txt additions.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Options;
use SolSEO\Tools\Alt_Text;
use SolSEO\Tools\Import;

defined( 'ABSPATH' ) || exit;

/**
 * The Tools screen.
 */
class Tools_Screen extends Screen {

	const PAGE = 'solseo-tools';

	/**
	 * Handle the forms.
	 */
	public static function load() {
		if ( self::submitted( 'solseo_import' ) ) {
			self::run_import();
		}

		if ( self::submitted( 'solseo_alt_text' ) ) {
			$filled = Alt_Text::fill();

			self::remember(
				$filled
					/* translators: %d: number of images given alt text. */
					? sprintf( _n( '%d image described.', '%d images described.', $filled, 'solseo' ), $filled )
					: __( 'Nothing left to describe.', 'solseo' )
			);

			self::go_back( self::PAGE, array( 'tab' => 'images' ) );
		}

		if ( self::submitted( 'solseo_data' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read as a flag.
			$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

			Options::update( array( 'remove_data' => ! empty( $input['remove_data'] ) ) );

			self::remember( __( 'Saved.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => 'data' ) );
		}

		if ( self::submitted( 'solseo_robots' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.
			$rules = isset( $_POST['solseo_robots_rules'] ) ? wp_unslash( $_POST['solseo_robots_rules'] ) : '';

			update_option( 'solseo_robots_rules', sanitize_textarea_field( $rules ) );

			self::remember( __( 'Saved.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => 'robots' ) );
		}
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		$tabs = array(
			'import' => __( 'Import', 'solseo' ),
			'images' => __( 'Images', 'solseo' ),
			'robots' => __( 'robots.txt', 'solseo' ),
			'data'   => __( 'Data', 'solseo' ),
		);

		$tab = self::current_tab( 'import' );
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'import';

		self::tabs( self::PAGE, $tabs, $tab );

		if ( 'import' === $tab ) {
			$prefix = self::chosen_source();

			self::view(
				'tools-import',
				array(
					'sources' => Import::sources(),
					'chosen'  => $prefix,
					'preview' => $prefix ? Import::preview( $prefix, 15 ) : array(),
				)
			);

			return;
		}

		if ( 'images' === $tab ) {
			self::view( 'tools-images', array( 'missing' => Alt_Text::count_missing() ) );

			return;
		}

		if ( 'robots' === $tab ) {
			self::view( 'tools-robots', array( 'rules' => (string) get_option( 'solseo_robots_rules', '' ) ) );

			return;
		}

		self::view( 'tools-data', array( 'remove' => (bool) Options::get( 'remove_data' ) ) );
	}

	/**
	 * Which source the screen is previewing.
	 *
	 * @return string
	 */
	protected static function chosen_source() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing what to preview.
		$prefix  = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
		$sources = Import::sources();

		if ( $prefix && isset( $sources[ $prefix ] ) ) {
			return $prefix;
		}

		$keys = array_keys( $sources );

		return $keys ? $keys[0] : '';
	}

	/**
	 * Copy one batch and report what happened.
	 */
	protected static function run_import() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is cast below.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

		$prefix    = isset( $input['source'] ) ? sanitize_text_field( $input['source'] ) : '';
		$overwrite = ! empty( $input['overwrite'] );
		$offset    = isset( $input['offset'] ) ? (int) $input['offset'] : 0;

		$sources = Import::sources();

		if ( ! isset( $sources[ $prefix ] ) ) {
			self::remember( __( 'There is nothing to import from that source.', 'solseo' ), 'error' );
			self::go_back( self::PAGE );
		}

		$result = Import::run( $prefix, $overwrite, $offset );

		self::remember(
			sprintf(
				/* translators: 1: number of pages updated, 2: number of pages still to do. */
				__( '%1$d pages updated. %2$d still to go.', 'solseo' ),
				$result['copied'],
				$result['remaining']
			)
		);

		self::go_back(
			self::PAGE,
			array(
				'source' => $prefix,
				'offset' => $result['offset'],
			)
		);
	}
}
