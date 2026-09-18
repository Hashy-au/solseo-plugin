<?php
/**
 * Saving the titles and descriptions typed into the posts list.
 *
 * The permission callback is a cheap gate on strangers. The control is the
 * check inside the loop, once per row, because a request carrying twenty rows
 * is twenty separate questions about twenty separate posts, and answering them
 * once at the door answers the wrong question.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Analyser;
use SolSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * The endpoint behind editing straight from the list.
 */
class Bulk_Edit {

	/** Most rows one request may carry. */
	const MAX_ROWS = 500;

	/**
	 * Register the route.
	 */
	public static function register_routes() {
		register_rest_route(
			'solseo/v1',
			'/bulk-meta',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save' ),
				'permission_callback' => array( __CLASS__, 'may_edit_something' ),
				'args'                => array(
					'rows' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'title'       => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * A gate on anybody who edits nothing at all.
	 *
	 * @return bool
	 */
	public static function may_edit_something() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Write what was typed, one row at a time.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save( $request ) {
		$rows = (array) $request->get_param( 'rows' );

		if ( count( $rows ) > self::MAX_ROWS ) {
			return new \WP_Error(
				'solseo_too_many',
				__( 'That is more rows than one save can carry.', 'solseo' ),
				array( 'status' => 400 )
			);
		}

		$saved  = array();
		$failed = array();
		$types  = solseo_post_types();

		foreach ( $rows as $row ) {
			$post_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$post    = $post_id ? get_post( $post_id ) : null;

			if ( ! $post || ! in_array( $post->post_type, $types, true ) ) {
				$failed[] = array(
					'id'     => $post_id,
					'reason' => 'missing',
				);

				continue;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				$failed[] = array(
					'id'     => $post_id,
					'reason' => 'permission',
				);

				continue;
			}

			$title       = isset( $row['title'] ) ? (string) $row['title'] : '';
			$description = isset( $row['description'] ) ? (string) $row['description'] : '';

			$changed = (string) Meta::get( $post_id, 'title' ) !== $title
				|| (string) Meta::get( $post_id, 'description' ) !== $description;

			Meta::save(
				$post_id,
				array(
					'title'       => $title,
					'description' => $description,
				)
			);

			/*
			 * Only a row that actually moved is scored again. Twenty rows with
			 * two edits in them cost two runs of the analyser, not twenty.
			 */
			if ( $changed ) {
				$analysis = Analyser::store( $post_id );
			} else {
				$analysis = array(
					'score' => (int) Meta::get( $post_id, 'score' ),
					'band'  => Analyser::band( (int) Meta::get( $post_id, 'score' ) ),
				);
			}

			$saved[] = array(
				'id'    => $post_id,
				'score' => (int) $analysis['score'],
				'band'  => $analysis['band'],
				'label' => Analyser::band_label( $analysis['band'] ),
			);
		}

		return rest_ensure_response(
			array(
				'saved'  => $saved,
				'failed' => $failed,
			)
		);
	}
}
