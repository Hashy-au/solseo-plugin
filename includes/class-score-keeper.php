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
