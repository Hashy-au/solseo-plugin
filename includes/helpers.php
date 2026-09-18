<?php
/**
 * Functions themes and other plugins may call.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types the plugin writes tags for.
 *
 * @return array List of post type names.
 */
function solseo_post_types() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );

	/**
	 * Filter the post types SolSEO manages.
	 *
	 * @param array $types Post type names.
	 */
	return apply_filters( 'solseo_post_types', array_values( $types ) );
}

/**
 * Taxonomies the plugin writes tags for.
 *
 * @return array List of taxonomy names.
 */
function solseo_taxonomies() {
	$taxonomies = get_taxonomies(
		array(
			'public'  => true,
			'show_ui' => true,
		),
		'names'
	);

	unset( $taxonomies['post_format'] );

	/**
	 * Filter the taxonomies SolSEO manages.
	 *
	 * @param array $taxonomies Taxonomy names.
	 */
	return apply_filters( 'solseo_taxonomies', array_values( $taxonomies ) );
}

/**
 * Whether WooCommerce is running.
 *
 * @return bool
 */
function solseo_has_woocommerce() {
	return class_exists( 'WooCommerce' );
}

/**
 * The stored content score for a post, or null when it has never been scored.
 *
 * @param int $post_id Post ID. Defaults to the current post.
 * @return int|null
 */
function solseo_score( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return null;
	}

	$stored = get_post_meta( $post_id, '_solseo_score', true );

	return '' === $stored ? null : (int) $stored;
}

/**
 * Print the breadcrumb trail.
 *
 * @param array $args Passed through to the breadcrumb renderer.
 */
function solseo_breadcrumbs( $args = array() ) {
	echo wp_kses_post( \SolSEO\Breadcrumbs::render( $args ) );
}
