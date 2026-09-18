<?php
/**
 * Keeps the stored score in step with the content.
 *
 * @package SolSEO
 */

namespace SolSEO;

use SolSEO\Analysis\Analyser;

defined( 'ABSPATH' ) || exit;

/**
 * Rescores a post when it is saved.
 */
class Score_Keeper {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'on_delete' ) );
		add_action( 'init', array( __CLASS__, 'after_rest' ), 21 );
	}

	/**
	 * Score again once the block editor has finished writing.
	 *
	 * The posts controller updates the post and then writes the meta, so
	 * save_post fires before the new title, description and focus keyword
	 * exist. Scoring there would put every block editor site one save behind,
	 * for ever, and the number would look right enough that nobody checked.
	 *
	 * rest_after_insert_{$post_type} fires after the meta, so that is where
	 * the block editor's scoring belongs.
	 */
	public static function after_rest() {
		foreach ( solseo_post_types() as $post_type ) {
			add_action(
				'rest_after_insert_' . $post_type,
				function ( $post ) {
					Analyser::store( $post->ID );
				},
				10,
				1
			);
		}
	}

	/**
	 * Score a post after it is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// The block editor is scored by after_rest(), once its meta has landed.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( 'publish' !== $post->post_status && 'draft' !== $post->post_status ) {
			return;
		}

		if ( ! in_array( $post->post_type, solseo_post_types(), true ) ) {
			return;
		}

		Analyser::store( $post_id );
	}

	/**
	 * Forget a deleted post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete( $post_id ) {
		delete_post_meta( $post_id, '_solseo_score' );
		delete_post_meta( $post_id, '_solseo_score_summary' );
	}
}
