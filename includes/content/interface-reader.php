<?php
/**
 * What a page builder has to answer before we will read a page it drew.
 *
 * @package SolSEO
 */

namespace SolSEO\Content;

defined( 'ABSPATH' ) || exit;

/**
 * One page builder, as far as reading its text is concerned.
 *
 * Three of these questions are the work: is this builder here, did it draw
 * this page, and what does the page say. The other three are identity, and
 * version() is the one that keeps a cached extraction honest when the builder
 * updates.
 *
 * A reader never reads the builder's stored shape. That JSON changes on their
 * release schedule rather than ours, so we ask their renderer, or we ask
 * WordPress through `the_content`, and their upgrade stays their problem.
 */
interface Reader {

	/**
	 * A short name, used in the cache key and in the report.
	 *
	 * @return string
	 */
	public static function slug();

	/**
	 * What to call the builder on screen.
	 *
	 * @return string
	 */
	public static function label();

	/**
	 * Whether the builder is installed and running.
	 *
	 * This must be one expression and must not touch the database. Six readers
	 * are asked on every score on every site, and five of them will be absent.
	 *
	 * @return bool
	 */
	public static function active();

	/**
	 * Whether this builder drew this post.
	 *
	 * @param \WP_Post $post The post.
	 * @return bool
	 */
	public static function owns( $post );

	/**
	 * The builder's version, for the cache key.
	 *
	 * @return string
	 */
	public static function version();

	/**
	 * The page as markup, or an empty string to go through `the_content`.
	 *
	 * Only a builder that publishes a documented way to render a post by ID
	 * answers this. The rest return nothing and are read the way a visitor
	 * reads them.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public static function html( $post );
}
