<?php
/**
 * Reading a page drawn by a builder we have never heard of.
 *
 * @package SolSEO
 */

namespace SolSEO\Content\Readers;

use SolSEO\Content\Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Anything that leaves `post_content` empty and puts words on the page anyway.
 *
 * Breakdance, Oxygen, Brizy, Zion, Themify, whatever ships next year. Naming
 * builders one at a time is a list that is out of date the week it is written,
 * and a reader that names a builder whose method it cannot call is worse than
 * no reader: it reports success and changes nothing.
 *
 * So this one names nobody. It claims a post with nothing stored, asks
 * WordPress what the page says, and keeps the answer only if it is a real
 * page's worth of words. A builder that does not answer on `the_content`
 * produces nothing here, and the page is read the way it is today.
 */
class Builder implements Reader {

	/** Where the filter this leans on is documented. */
	const SOURCE = 'https://developer.wordpress.org/reference/hooks/the_content/';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public static function slug() {
		return 'builder';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public static function label() {
		return __( 'a page builder', 'solseo' );
	}

	/**
	 * Always. There is no plugin to look for.
	 *
	 * @return bool
	 */
	public static function active() {
		return true;
	}

	/**
	 * Version. Core's, so a WordPress update reruns the extraction.
	 *
	 * @return string
	 */
	public static function version() {
		return (string) get_bloginfo( 'version' );
	}

	/**
	 * Whether there is nothing stored to read.
	 *
	 * A page with nothing stored and nothing to show is simply an empty page.
	 * That is decided after the render, by the word count, rather than here.
	 *
	 * @param \WP_Post $post The post.
	 * @return bool
	 */
	public static function owns( $post ) {
		return '' === trim( (string) $post->post_content );
	}

	/**
	 * Nothing of its own, by definition.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public static function html( $post ) {
		unset( $post );

		return '';
	}
}
