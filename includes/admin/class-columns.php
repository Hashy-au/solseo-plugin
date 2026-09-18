<?php
/**
 * The score column on the post and product lists.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Analyser;
use SolSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a sortable score column.
 */
class Columns {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Attach to every post type the plugin manages.
	 */
	public static function register() {
		foreach ( solseo_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( __CLASS__, 'sortable' ) );
		}

		add_action( 'pre_get_posts', array( __CLASS__, 'order_by_score' ) );
	}

	/**
	 * Add the column after the title.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['solseo_score'] = __( 'SEO', 'solseo' );
			}
		}

		return isset( $out['solseo_score'] ) ? $out : $out + array( 'solseo_score' => __( 'SEO', 'solseo' ) );
	}

	/**
	 * Draw a cell.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render( $column, $post_id ) {
		if ( 'solseo_score' !== $column ) {
			return;
		}

		$score = get_post_meta( $post_id, '_solseo_score', true );

		if ( '' === $score ) {
			echo '<span class="solseo-pill solseo-band-none">&#8212;</span>';

			return;
		}

		$band    = Analyser::band( (int) $score );
		$keyword = Meta::get( $post_id, 'focus_keyword' );

		printf(
			'<span class="solseo-pill solseo-band-%1$s" title="%2$s">%3$d</span>',
			esc_attr( $band ),
			esc_attr( $keyword ? $keyword : __( 'No focus keyword', 'solseo' ) ),
			(int) $score
		);
	}

	/**
	 * Let the column be sorted.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['solseo_score'] = 'solseo_score';

		return $columns;
	}

	/**
	 * Sort by the stored score when the column is clicked.
	 *
	 * @param \WP_Query $query The query about to run.
	 */
	public static function order_by_score( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'solseo_score' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', '_solseo_score' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
