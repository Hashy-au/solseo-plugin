<?php
/**
 * Reading a page Beaver Builder drew.
 *
 * @package SolSEO
 */

namespace SolSEO\Content\Readers;

use SolSEO\Content\Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Beaver Builder, through the filter it already hooks.
 *
 * Beaver draws its pages by adding `FLBuilder::render_content` to
 * `the_content`, so asking WordPress for the content asks Beaver, in Beaver's
 * own supported way, with no method of ours to go stale.
 */
class Beaver implements Reader {

	/** Where Beaver's own rendering is documented. */
	const SOURCE = 'https://docs.wpbeaverbuilder.com/beaver-builder/developer/how-to-tips/render-layouts-with-php/';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public static function slug() {
		return 'beaver';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public static function label() {
		return 'Beaver Builder';
	}

	/**
	 * Whether Beaver is here.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'FLBuilderModel' );
	}

	/**
	 * Version.
	 *
	 * @return string
	 */
	public static function version() {
		return defined( 'FL_BUILDER_VERSION' ) ? (string) FL_BUILDER_VERSION : '';
	}

	/**
	 * Whether this page was built in Beaver.
	 *
	 * @param \WP_Post $post The post.
	 * @return bool
	 */
	public static function owns( $post ) {
		if ( ! is_callable( array( 'FLBuilderModel', 'is_builder_enabled' ) ) ) {
			return false;
		}

		return (bool) \FLBuilderModel::is_builder_enabled( (int) $post->ID );
	}

	/**
	 * Nothing of its own: Beaver answers on `the_content`.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public static function html( $post ) {
		unset( $post );

		return '';
	}
}
