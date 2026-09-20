<?php
/**
 * The blocks this plugin adds to the editor.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the blocks, and the hand written script behind them.
 *
 * No build step here either. The editor script is plain JavaScript against the
 * handles WordPress already ships, and block.json names that handle rather than
 * a compiled file, so the source a reviewer opens is the source that runs.
 */
class Blocks {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register every block.
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'solseo-block-faq',
			SOLSEO_URL . 'assets/js/block-faq.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			SOLSEO_VERSION,
			true
		);

		wp_set_script_translations( 'solseo-block-faq', 'solseo' );

		register_block_type( SOLSEO_PATH . 'includes/blocks/faq' );
	}
}
