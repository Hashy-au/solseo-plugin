<?php
/**
 * The plugin's own REST routes.
 *
 * @package SolSEO
 */

namespace SolSEO;

use SolSEO\Analysis\Analyser;
use SolSEO\Analysis\Paper;

defined( 'ABSPATH' ) || exit;

/**
 * Scores content while it is still being written.
 */
class Rest {

	const NAMESPACE_V1 = 'solseo/v1';

	/**
	 * Register the routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/score',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'score' ),
				'permission_callback' => array( __CLASS__, 'may_edit' ),
				'args'                => array(
					'post_id'     => array(
						'type'     => 'integer',
						'required' => true,
					),
					'title'       => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'keyword'     => array( 'type' => 'string' ),
					'slug'        => array( 'type' => 'string' ),
					'content'     => array( 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * Whether the caller may edit the post being scored.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function may_edit( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
	}

	/**
	 * Score the values the editor sent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function score( $request ) {
		$paper = Paper::from_editor(
			array(
				'post_id'     => (int) $request->get_param( 'post_id' ),
				'title'       => (string) $request->get_param( 'title' ),
				'description' => (string) $request->get_param( 'description' ),
				'keyword'     => (string) $request->get_param( 'keyword' ),
				'slug'        => (string) $request->get_param( 'slug' ),
				'content'     => wp_kses_post( (string) $request->get_param( 'content' ) ),
			)
		);

		$analysis = Analyser::run( $paper );

		return rest_ensure_response(
			array(
				'score'       => $analysis['score'],
				'band'        => $analysis['band'],
				'label'       => Analyser::band_label( $analysis['band'] ),
				'groups'      => $analysis['groups'],
				'title'       => array(
					'width' => Analysis\Text::pixel_width( $paper['title'], 20 ),
					'limit' => Analysis\Basic_Checks::TITLE_MAX,
				),
				'description' => array(
					'width' => Analysis\Text::pixel_width( $paper['description'], 14 ),
					'limit' => Analysis\Basic_Checks::DESCRIPTION_MAX,
				),
			)
		);
	}
}
