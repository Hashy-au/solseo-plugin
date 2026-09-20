<?php
/**
 * Reading a page Elementor drew.
 *
 * @package SolSEO
 */

namespace SolSEO\Content\Readers;

use SolSEO\Content\Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor, through its own renderer.
 *
 * Of the builders in this list it is the only one that publishes a documented
 * way to render a post by ID, so it is the only one that does not go through
 * `the_content`. Both calls are checked before they are made: a version that
 * drops either of them falls back rather than throwing.
 */
class Elementor implements Reader {

	/** Where the calls below are documented. */
	const SOURCE = 'https://developers.elementor.com/docs/hooks/php/';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public static function slug() {
		return 'elementor';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public static function label() {
		return 'Elementor';
	}

	/**
	 * Whether Elementor is here.
	 *
	 * @return bool
	 */
	public static function active() {
		return defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Version.
	 *
	 * @return string
	 */
	public static function version() {
		return defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '';
	}

	/**
	 * Whether this page was built in Elementor.
	 *
	 * @param \WP_Post $post The post.
	 * @return bool
	 */
	public static function owns( $post ) {
		$elementor = self::plugin();

		if ( ! $elementor || ! isset( $elementor->documents ) ) {
			return false;
		}

		$document = $elementor->documents->get( (int) $post->ID );

		return $document && method_exists( $document, 'is_built_with_elementor' ) && $document->is_built_with_elementor();
	}

	/**
	 * The page as Elementor draws it.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public static function html( $post ) {
		$elementor = self::plugin();

		if ( ! $elementor || ! isset( $elementor->frontend ) ) {
			return '';
		}

		if ( ! method_exists( $elementor->frontend, 'get_builder_content_for_display' ) ) {
			return '';
		}

		// The second argument is whether to print the page's CSS with it. We
		// are reading words, so no.
		return (string) $elementor->frontend->get_builder_content_for_display( (int) $post->ID, false );
	}

	/**
	 * Elementor itself, or null.
	 *
	 * @return object|null
	 */
	protected static function plugin() {
		if ( ! is_callable( array( '\Elementor\Plugin', 'instance' ) ) ) {
			return null;
		}

		return \Elementor\Plugin::instance();
	}
}
