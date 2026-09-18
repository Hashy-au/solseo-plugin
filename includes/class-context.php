<?php
/**
 * Describes whatever WordPress is about to render.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the query into a small array the output classes can read.
 */
class Context {

	/**
	 * Memo for this request.
	 *
	 * @var array|null
	 */
	protected static $current = null;

	/**
	 * The current view.
	 *
	 * @return array
	 */
	public static function current() {
		if ( null !== self::$current ) {
			return self::$current;
		}

		self::$current = self::detect();

		return self::$current;
	}

	/**
	 * Build a context for one post, for use outside the main query.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function for_post( $post_id ) {
		$post = get_post( $post_id );

		return array(
			'type'      => 'singular',
			'object_id' => $post ? $post->ID : 0,
			'post_type' => $post ? $post->post_type : '',
			'taxonomy'  => '',
			'term'      => null,
		);
	}

	/**
	 * Work out the view from the conditional tags.
	 *
	 * @return array
	 */
	protected static function detect() {
		$context = array(
			'type'      => 'other',
			'object_id' => 0,
			'post_type' => '',
			'taxonomy'  => '',
			'term'      => null,
		);

		if ( is_front_page() && is_home() ) {
			$context['type'] = 'front_page';
			return $context;
		}

		if ( is_front_page() ) {
			$context['type']      = 'front_page';
			$context['object_id'] = (int) get_option( 'page_on_front' );
			$context['post_type'] = 'page';
			return $context;
		}

		if ( is_home() ) {
			$context['type']      = 'blog_home';
			$context['object_id'] = (int) get_option( 'page_for_posts' );
			$context['post_type'] = 'page';
			return $context;
		}

		if ( is_singular() ) {
			$post = get_queried_object();

			$context['type']      = 'singular';
			$context['object_id'] = $post ? (int) $post->ID : 0;
			$context['post_type'] = $post ? $post->post_type : '';
			return $context;
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			$context['type']      = 'term';
			$context['object_id'] = $term ? (int) $term->term_id : 0;
			$context['taxonomy']  = $term ? $term->taxonomy : '';
			$context['term']      = $term;
			return $context;
		}

		if ( is_post_type_archive() ) {
			$context['type']      = 'post_type_archive';
			$context['post_type'] = (string) get_query_var( 'post_type' );
			return $context;
		}

		if ( is_author() ) {
			$context['type']      = 'author';
			$context['object_id'] = (int) get_query_var( 'author' );
			return $context;
		}

		if ( is_search() ) {
			$context['type'] = 'search';
			return $context;
		}

		if ( is_404() ) {
			$context['type'] = 'not_found';
			return $context;
		}

		if ( is_date() ) {
			$context['type'] = 'date';
		}

		return $context;
	}

	/**
	 * Drop the memo. Tests call this between queries.
	 */
	public static function reset() {
		self::$current = null;
	}
}
