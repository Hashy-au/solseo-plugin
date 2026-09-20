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

use SolSEO\A11y\Editor_Checks;
use SolSEO\Analysis\Analyser;
use SolSEO\Connect\Google;
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

		/*
		 * The checklist is its own plugin registration and its own file. It
		 * reads the analysis the sidebar already put in the store, so it costs
		 * one subscription and no second round trip, and a WordPress without
		 * the pre-publish slot simply does not draw it.
		 */
		wp_register_script( 'solseo-checklist', SOLSEO_URL . 'assets/js/editor-checklist.js', array_merge( array( 'solseo-editor-api' ), $deps ), SOLSEO_VERSION, true );

		wp_enqueue_script( 'solseo-sidebar' );
		wp_enqueue_script( 'solseo-checklist' );

		wp_localize_script( 'solseo-editor-api', 'solseoEditor', self::data() );

		wp_set_script_translations( 'solseo-sidebar', 'solseo' );
		wp_set_script_translations( 'solseo-checklist', 'solseo' );
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

		/*
		 * searchConsole is whether the Search Console panel is worth drawing
		 * at all (FreeC1). A status, not a figure: nothing here asks Google
		 * anything, because this runs on every editor load and the panel asks
		 * for itself when somebody opens it.
		 */
		return array(
			'searchConsole'       => Google::status()['connected'],
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
				'analysing'      => __( 'Working it out', 'solseo' ),
				'failed'         => __( 'The score could not be worked out just now.', 'solseo' ),
				/* translators: 1: width of the text in pixels, 2: the width search results allow. */
				'pixels'         => __( '%1$d of %2$d pixels', 'solseo' ),
				'panel'          => __( 'SolSEO', 'solseo' ),
				/* translators: %d: the score out of a hundred. */
				'checklistTitle' => __( 'SolSEO: %d out of 100', 'solseo' ),
				'checklistIntro' => __( 'Worth fixing before this goes out. None of it stops you publishing.', 'solseo' ),
				'checklistClear' => __( 'Nothing is missing. Off you go.', 'solseo' ),
				/* translators: %d: how many more things there are to fix. */
				'checklistMore'  => __( '%d more are listed in the SolSEO panel.', 'solseo' ),
				/* translators: %s: the page builder's name, such as Elementor. */
				'readWith'       => __( 'Scored on the page %s draws, not on what is stored.', 'solseo' ),
				'general'        => __( 'General', 'solseo' ),
				'social'         => __( 'Social', 'solseo' ),
				'advanced'       => __( 'Advanced', 'solseo' ),
				'keyword'        => __( 'Focus keyword', 'solseo' ),
				'keywordHelp'    => __( 'The phrase this page should be found for. One phrase, not a list.', 'solseo' ),
				'phrases'        => __( 'Other phrases', 'solseo' ),
				'phrasesHelp'    => __( 'Separated by commas.', 'solseo' ),
				'seoTitle'       => __( 'SEO title', 'solseo' ),
				'description'    => __( 'Meta description', 'solseo' ),
				'ogTitle'        => __( 'Title when shared', 'solseo' ),
				'ogDesc'         => __( 'Description when shared', 'solseo' ),
				'ogImage'        => __( 'Image when shared', 'solseo' ),
				'ogImageHelp'    => __( 'Falls back to the featured image, then to the site default.', 'solseo' ),
				'choose'         => __( 'Choose image', 'solseo' ),
				'chooseSocial'   => __( 'Select social image', 'solseo' ),
				'remove'         => __( 'Remove', 'solseo' ),
				'cardTitle'      => __( 'Card title', 'solseo' ),
				'cardDesc'       => __( 'Card description', 'solseo' ),
				'canonical'      => __( 'Canonical address', 'solseo' ),
				'canonHelp'      => __( 'Point elsewhere only when this page repeats another one.', 'solseo' ),
				'engines'        => __( 'Search engines', 'solseo' ),
				'noindex'        => __( 'Keep this page out of search results', 'solseo' ),
				'nofollow'       => __( 'Do not follow the links on this page', 'solseo' ),
				'noimage'        => __( 'Keep the images out of image search', 'solseo' ),
				'noarchive'      => __( 'Do not keep a cached copy', 'solseo' ),
				'schema'         => __( 'Structured data', 'solseo' ),
				'schemaAuto'     => __( 'Use the default for this post type', 'solseo' ),
				/* translators: %d: how many checks in this group need attention. */
				'errors'         => __( '%d to look at', 'solseo' ),
				'allGood'        => __( 'All good', 'solseo' ),
				'deadLinks'      => __( 'Links to nowhere on this page', 'solseo' ),
				'deadLinksHelp'  => __( 'No page of yours is at these addresses. Links to other sites are not checked here.', 'solseo' ),
				'deadLinkNoText' => __( 'a link with no words in it', 'solseo' ),

				/*
				 * FreeB4. Every one of these is read by name in a panel and
				 * there is no JavaScript test runner here, so tests/test-menu.php
				 * asserts that the strings the panels name are the strings this
				 * list holds. D-80.8 shipped a panel reading a key nobody sent,
				 * and it looked like good news for a fortnight.
				 */
				/* translators: 1: a focus keyword. 2: the name of another page. */
				'duplicateWarn'  => __( '%2$s is already going for "%1$s". Two of your pages aimed at one phrase split the links between them, and Google picks one.', 'solseo' ),
				/* translators: 1: a focus keyword. 2: the name of another page. */
				'duplicateFine'  => __( '%2$s is going for this phrase too, on the other side of the site. That is usually fine: a listing and an article answer different questions.', 'solseo' ),
				'a11yTitle'      => __( 'Hard to read', 'solseo' ),
				'a11yClear'      => __( 'Nothing here makes this page hard to read.', 'solseo' ),
				'a11yCovers'     => Editor_Checks::covers(),
				'suggestTitle'   => __( 'Could link here', 'solseo' ),
				'suggestIntro'   => __( 'These pages of yours mention this one and do not link to it.', 'solseo' ),
				'suggestNone'    => __( 'Nothing. Either no other page of yours mentions this in a sentence that could carry a link, or the ones that do already link here.', 'solseo' ),
				/* translators: %s: the words that would become the link. */
				'suggestAdd'     => __( 'Link "%s" there', 'solseo' ),
				'suggestUndo'    => __( 'Adding one writes a real link into that page and stores a revision, so what it said before is one click away in its own editor.', 'solseo' ),
				'suggestFailed'  => __( 'That could not be done just now.', 'solseo' ),

				/*
				 * FreeC1. Read by name in the Search Console panel, in both
				 * editors, and pinned by the same guard as the block above:
				 * there is no JavaScript test runner here, so a panel reading
				 * a key nobody sends looks like good news for a fortnight.
				 */
				'gscTitle'       => __( 'In Google', 'solseo' ),
				'gscLoading'     => __( 'Asking Google about this page', 'solseo' ),
				'gscClicks'      => __( 'Clicks', 'solseo' ),
				'gscImpressions' => __( 'Impressions', 'solseo' ),
				'gscPosition'    => __( 'Average position', 'solseo' ),
				'gscQueries'     => __( 'What people searched for', 'solseo' ),
				'gscNone'        => __( 'Google has no figures for this page over the last twenty eight days. That is usually a page that is new, or one Google has not indexed yet.', 'solseo' ),
				'gscNoQueries'   => __( 'Google reported no individual search terms for this page. It withholds ones too few people used.', 'solseo' ),
				/* translators: 1: a date, 2: a date. */
				'gscRange'       => __( '%1$s to %2$s. Google is two days behind, which is why it stops there.', 'solseo' ),
				'gscFailed'      => __( 'Those figures could not be read just now.', 'solseo' ),
			),
		);
	}
}
