<?php
/**
 * Loads one editor surface, never two.
 *
 * The block editor gets a panel in the sidebar, reached from a button in the
 * top right that carries the score. The classic editor gets the box it has
 * always had. Which one loads is decided by the same question WordPress asks
 * itself, so the two answers cannot disagree.
 *
 * None of this is built. The panel is written against the script handles
 * WordPress already ships, through wp.element.createElement rather than JSX,
 * so there is no bundler, no node_modules and no compiled file in the zip.
 * The source a reviewer opens is the source that runs.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Analyser;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The editor assets, for both editors.
 */
class Editor_Assets {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'classic' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block' ) );
	}

	/**
	 * The classic editor's script, where the meta box is what is loading.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function classic( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, solseo_post_types(), true ) ) {
			return;
		}

		if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}

		wp_enqueue_script( 'solseo-editor', SOLSEO_URL . 'assets/js/editor.js', array( 'wp-api-fetch' ), SOLSEO_VERSION, true );

		wp_localize_script( 'solseo-editor', 'solseoEditor', self::data() );
	}

	/**
	 * The block editor's panel.
	 */
	public static function block() {
		$screen = get_current_screen();

		// This hook also fires for the widget and site editors, which have no post.
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		if ( ! in_array( $screen->post_type, solseo_post_types(), true ) ) {
			return;
		}

		/*
		 * PluginSidebar moved from the edit-post package to the editor package
		 * in WordPress 6.6, and the old export still works but complains. This
		 * plugin supports 6.4 and up, so both handles are asked for and the JS
		 * picks whichever one actually has the export. Both are already loaded
		 * by the block editor, so neither costs a request.
		 */
		$deps = array(
			'wp-plugins',
			'wp-editor',
			'wp-edit-post',
			'wp-components',
			'wp-element',
			'wp-data',
			'wp-api-fetch',
			'wp-i18n',
			'wp-block-editor',
		);

		wp_register_script( 'solseo-editor-api', SOLSEO_URL . 'assets/js/editor-api.js', array( 'wp-data', 'wp-element', 'wp-i18n' ), SOLSEO_VERSION, true );
		wp_register_script( 'solseo-editor-controls', SOLSEO_URL . 'assets/js/editor-controls.js', array( 'solseo-editor-api', 'wp-components', 'wp-element', 'wp-block-editor' ), SOLSEO_VERSION, true );
		wp_register_script( 'solseo-sidebar', SOLSEO_URL . 'assets/js/editor-sidebar.js', array_merge( array( 'solseo-editor-controls' ), $deps ), SOLSEO_VERSION, true );

		wp_enqueue_script( 'solseo-sidebar' );

		wp_localize_script( 'solseo-editor-api', 'solseoEditor', self::data() );

		wp_set_script_translations( 'solseo-sidebar', 'solseo' );
		wp_set_script_translations( 'solseo-editor-controls', 'solseo' );
	}

	/**
	 * What both editors need handed to them.
	 *
	 * @return array
	 */
	protected static function data() {
		$screen    = get_current_screen();
		$post_type = $screen && $screen->post_type ? $screen->post_type : 'post';
		$settings  = Options::post_type( $post_type );

		return array(
			'schemaTypes'         => Titles_Screen::schema_types(),
			'titleTemplate'       => $settings['title'],
			'descriptionTemplate' => $settings['description'],
			'bands'               => array(
				'excellent' => Analyser::band_label( 'excellent' ),
				'good'      => Analyser::band_label( 'good' ),
				'fair'      => Analyser::band_label( 'fair' ),
				'poor'      => Analyser::band_label( 'poor' ),
			),
			'strings'             => array(
				'analysing'    => __( 'Working it out', 'solseo' ),
				'failed'       => __( 'The score could not be worked out just now.', 'solseo' ),
				/* translators: 1: width of the text in pixels, 2: the width search results allow. */
				'pixels'       => __( '%1$d of %2$d pixels', 'solseo' ),
				'panel'        => __( 'SolSEO', 'solseo' ),
				'general'      => __( 'General', 'solseo' ),
				'social'       => __( 'Social', 'solseo' ),
				'advanced'     => __( 'Advanced', 'solseo' ),
				'keyword'      => __( 'Focus keyword', 'solseo' ),
				'keywordHelp'  => __( 'The phrase this page should be found for. One phrase, not a list.', 'solseo' ),
				'phrases'      => __( 'Other phrases', 'solseo' ),
				'phrasesHelp'  => __( 'Separated by commas.', 'solseo' ),
				'seoTitle'     => __( 'SEO title', 'solseo' ),
				'description'  => __( 'Meta description', 'solseo' ),
				'ogTitle'      => __( 'Title when shared', 'solseo' ),
				'ogDesc'       => __( 'Description when shared', 'solseo' ),
				'ogImage'      => __( 'Image when shared', 'solseo' ),
				'ogImageHelp'  => __( 'Falls back to the featured image, then to the site default.', 'solseo' ),
				'choose'       => __( 'Choose image', 'solseo' ),
				'chooseSocial' => __( 'Select social image', 'solseo' ),
				'remove'       => __( 'Remove', 'solseo' ),
				'cardTitle'    => __( 'Card title', 'solseo' ),
				'cardDesc'     => __( 'Card description', 'solseo' ),
				'canonical'    => __( 'Canonical address', 'solseo' ),
				'canonHelp'    => __( 'Point elsewhere only when this page repeats another one.', 'solseo' ),
				'engines'      => __( 'Search engines', 'solseo' ),
				'noindex'      => __( 'Keep this page out of search results', 'solseo' ),
				'nofollow'     => __( 'Do not follow the links on this page', 'solseo' ),
				'noimage'      => __( 'Keep the images out of image search', 'solseo' ),
				'noarchive'    => __( 'Do not keep a cached copy', 'solseo' ),
				'schema'       => __( 'Structured data', 'solseo' ),
				'schemaAuto'   => __( 'Use the default for this post type', 'solseo' ),
				/* translators: %d: how many checks in this group need attention. */
				'errors'       => __( '%d to look at', 'solseo' ),
				'allGood'      => __( 'All good', 'solseo' ),
			),
		);
	}
}
