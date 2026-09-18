<?php
/**
 * Breadcrumb trail.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the trail and renders it.
 */
class Breadcrumbs {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_shortcode( 'solseo_breadcrumbs', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Whether breadcrumbs are switched on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) Options::get( 'breadcrumbs_enabled' );
	}

	/**
	 * The trail for the current view.
	 *
	 * @return array Each entry is array( 'label' => string, 'url' => string ).
	 */
	public static function trail() {
		$context = Context::current();
		$home    = Options::get( 'breadcrumbs_home' );

		$trail = array(
			array(
				'label' => $home ? $home : __( 'Home', 'solseo' ),
				'url'   => home_url( '/' ),
			),
		);

		switch ( $context['type'] ) {
			case 'front_page':
				$trail = array();
				break;

			case 'singular':
				$trail = array_merge( $trail, self::for_post( $context['object_id'] ) );
				break;

			case 'term':
				$trail = array_merge( $trail, self::for_term( $context['object_id'], $context['taxonomy'] ) );
				break;

			case 'post_type_archive':
				$trail[] = array(
					'label' => post_type_archive_title( '', false ),
					'url'   => '',
				);
				break;

			case 'author':
				$trail[] = array(
					'label' => get_the_author_meta( 'display_name', (int) $context['object_id'] ),
					'url'   => '',
				);
				break;

			case 'search':
				$trail[] = array(
					/* translators: %s: the search term. */
					'label' => sprintf( __( 'Search for "%s"', 'solseo' ), get_search_query() ),
					'url'   => '',
				);
				break;

			case 'not_found':
				$trail[] = array(
					'label' => __( 'Page not found', 'solseo' ),
					'url'   => '',
				);
				break;

			case 'blog_home':
				$trail[] = array(
					'label' => $context['object_id'] ? get_the_title( $context['object_id'] ) : __( 'Blog', 'solseo' ),
					'url'   => '',
				);
				break;
		}

		/**
		 * Filter the breadcrumb trail.
		 *
		 * @param array $trail   Trail entries.
		 * @param array $context View context.
		 */
		return apply_filters( 'solseo_breadcrumb_trail', $trail, $context );
	}

	/**
	 * Render the trail.
	 *
	 * @param array $args Keys: separator, before, after.
	 * @return string
	 */
	public static function render( $args = array() ) {
		$trail = self::trail();

		if ( count( $trail ) < 2 ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'separator' => Options::get( 'breadcrumbs_sep' ),
				'prefix'    => Options::get( 'breadcrumbs_prefix' ),
			)
		);

		$parts = array();
		$last  = count( $trail ) - 1;

		foreach ( array_values( $trail ) as $index => $crumb ) {
			if ( $index === $last || empty( $crumb['url'] ) ) {
				$parts[] = '<span class="solseo-breadcrumb-current">' . esc_html( $crumb['label'] ) . '</span>';
				continue;
			}

			$parts[] = '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a>';
		}

		$separator = ' <span class="solseo-breadcrumb-separator">' . esc_html( $args['separator'] ) . '</span> ';
		$inside    = $args['prefix'] ? '<span class="solseo-breadcrumb-prefix">' . esc_html( $args['prefix'] ) . '</span> ' : '';

		return '<nav class="solseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'solseo' ) . '">' . $inside . implode( $separator, $parts ) . '</nav>';
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'separator' => Options::get( 'breadcrumbs_sep' ),
			),
			$atts,
			'solseo_breadcrumbs'
		);

		return self::render( $atts );
	}

	/**
	 * The part of the trail above a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	protected static function for_post( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$trail = array();

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
				$trail[] = array(
					'label' => get_the_title( $ancestor ),
					'url'   => get_permalink( $ancestor ),
				);
			}
		} else {
			$taxonomy = 'product' === $post->post_type ? 'product_cat' : 'category';
			$terms    = get_the_terms( $post_id, $taxonomy );

			if ( is_array( $terms ) && $terms ) {
				$term  = self::deepest( $terms );
				$trail = array_merge( $trail, self::for_term( $term->term_id, $taxonomy ) );
			}
		}

		$trail[] = array(
			'label' => get_the_title( $post_id ),
			'url'   => '',
		);

		return $trail;
	}

	/**
	 * The part of the trail above a term, parents first.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	protected static function for_term( $term_id, $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		$trail = array();

		foreach ( array_reverse( get_ancestors( $term_id, $taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );

			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$trail[] = array(
					'label' => $ancestor->name,
					'url'   => get_term_link( $ancestor ),
				);
			}
		}

		$link = get_term_link( $term );

		$trail[] = array(
			'label' => $term->name,
			'url'   => is_wp_error( $link ) ? '' : $link,
		);

		return $trail;
	}

	/**
	 * The term furthest down the tree, so the trail is the longest true one.
	 *
	 * @param array $terms Terms.
	 * @return \WP_Term
	 */
	protected static function deepest( array $terms ) {
		$best  = $terms[0];
		$depth = -1;

		foreach ( $terms as $term ) {
			$count = count( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );

			if ( $count > $depth ) {
				$depth = $count;
				$best  = $term;
			}
		}

		return $best;
	}
}
