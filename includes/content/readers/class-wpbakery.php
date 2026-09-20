<?php
/**
 * Reading a page WPBakery drew.
 *
 * @package SolSEO
 */

namespace SolSEO\Content\Readers;

use SolSEO\Content\Reader;

defined( 'ABSPATH' ) || exit;

/**
 * WPBakery, through the shortcodes it stores and the filter it hooks.
 *
 * It is in this list because it is bundled into thousands of theme
 * marketplace themes, and a marketplace theme is what a small business site
 * built by somebody's nephew in 2019 is running.
 */
class Wpbakery implements Reader {

	/** Where the constant and the shortcode names are documented. */
	const SOURCE = 'https://kb.wpbakery.com/docs/developers-how-tos/';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public static function slug() {
		return 'wpbakery';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public static function label() {
		return 'WPBakery';
	}

	/**
	 * Whether WPBakery is here.
	 *
	 * @return bool
	 */
	public static function active() {
		return defined( 'WPB_VC_VERSION' );
	}

	/**
	 * Version.
	 *
	 * @return string
	 */
	public static function version() {
		return defined( 'WPB_VC_VERSION' ) ? (string) WPB_VC_VERSION : '';
	}

	/**
	 * Whether this page was built in WPBakery.
	 *
	 * Its own row shortcode is the marker, because WPBakery can be installed
	 * site wide and used on three pages out of two hundred.
	 *
	 * @param \WP_Post $post The post.
	 * @return bool
	 */
	public static function owns( $post ) {
		return false !== strpos( (string) $post->post_content, '[vc_row' );
	}

	/**
	 * Nothing of its own: WPBakery answers on `the_content`.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public static function html( $post ) {
		unset( $post );

		return '';
	}
}
