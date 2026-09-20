<?php
/**
 * Reading a page Divi drew.
 *
 * @package SolSEO
 */

namespace SolSEO\Content\Readers;

use SolSEO\Content\Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Divi, through the shortcodes it stores and the filter it hooks.
 *
 * Divi keeps its layout in `post_content` as shortcodes, so what WordPress
 * stores is not empty, it is `[et_pb_section]` all the way down. Counting that
 * as writing gives a page a word count made of attribute names.
 */
class Divi implements Reader {

	/** Where Divi's own detection call is described. */
	const SOURCE = 'https://www.elegantthemes.com/documentation/developers/divi-builder/';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public static function slug() {
		return 'divi';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public static function label() {
		return 'Divi';
	}

	/**
	 * Whether Divi is here.
	 *
	 * @return bool
	 */
	public static function active() {
		return function_exists( 'et_pb_is_pagebuilder_used' );
	}

	/**
	 * Version.
	 *
	 * @return string
	 */
	public static function version() {
		if ( defined( 'ET_BUILDER_VERSION' ) ) {
			return (string) ET_BUILDER_VERSION;
		}

		return defined( 'ET_BUILDER_PRODUCT_VERSION' ) ? (string) ET_BUILDER_PRODUCT_VERSION : '';
	}

	/**
	 * Whether this page was built in Divi.
	 *
	 * @param \WP_Post $post The post.
	 * @return bool
	 */
	public static function owns( $post ) {
		return (bool) et_pb_is_pagebuilder_used( (int) $post->ID );
	}

	/**
	 * Nothing of its own: Divi answers on `the_content`.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public static function html( $post ) {
		unset( $post );

		return '';
	}
}
