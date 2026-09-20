<?php
/**
 * Reading a page Bricks drew.
 *
 * @package SolSEO
 */

namespace SolSEO\Content\Readers;

use SolSEO\Content\Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Bricks, through its own accessor and its own renderer.
 *
 * Bricks replaces the template rather than filtering `the_content`, and it
 * stores nothing in `post_content`, so it is the one builder that needs a
 * reader of its own or it gets nothing at all.
 *
 * Bricks publishes no developer documentation for either call below, so both
 * are checked with `is_callable` before they are made and neither is trusted
 * to return what it says. On a version that has moved them this reader
 * declines and the page is read the way it is today.
 */
class Bricks implements Reader {

	/** Where these calls are described. Not a vendor page, which is the point. */
	const SOURCE = 'https://forum.bricksbuilder.io/t/how-to-render-bricks-page-content-manually/27431';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public static function slug() {
		return 'bricks';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public static function label() {
		return 'Bricks';
	}

	/**
	 * Whether Bricks is here.
	 *
	 * @return bool
	 */
	public static function active() {
		return defined( 'BRICKS_VERSION' ) && is_callable( array( '\Bricks\Database', 'get_data' ) );
	}

	/**
	 * Version.
	 *
	 * @return string
	 */
	public static function version() {
		return defined( 'BRICKS_VERSION' ) ? (string) BRICKS_VERSION : '';
	}

	/**
	 * Whether this page was built in Bricks.
	 *
	 * @param \WP_Post $post The post.
	 * @return bool
	 */
	public static function owns( $post ) {
		return count( self::elements( $post ) ) > 0;
	}

	/**
	 * The page as Bricks draws it.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public static function html( $post ) {
		if ( ! is_callable( array( '\Bricks\Frontend', 'render_data' ) ) ) {
			return '';
		}

		$elements = self::elements( $post );

		if ( ! $elements ) {
			return '';
		}

		return (string) \Bricks\Frontend::render_data( $elements, (int) $post->ID );
	}

	/**
	 * What Bricks says is on the page.
	 *
	 * This is Bricks' own accessor handing back Bricks' own structure, which
	 * goes straight back to Bricks' own renderer. Nothing here knows or cares
	 * what shape it is, which is the whole arrangement.
	 *
	 * @param \WP_Post $post The post.
	 * @return array
	 */
	protected static function elements( $post ) {
		if ( ! is_callable( array( '\Bricks\Database', 'get_data' ) ) ) {
			return array();
		}

		$elements = \Bricks\Database::get_data( (int) $post->ID, 'content' );

		return is_array( $elements ) ? $elements : array();
	}
}
