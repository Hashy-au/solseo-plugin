<?php
/**
 * The plugin's own REST routes.
 *
 * @package SolSEO
 */

namespace SolSEO;

use SolSEO\A11y\Editor_Checks;
use SolSEO\Analysis\Analyser;
use SolSEO\Analysis\Duplicates;
use SolSEO\Analysis\Paper;
use SolSEO\Links\Checker;
use SolSEO\Links\Suggestions;

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

		/*
		 * What robots.txt would say, composed by the code that serves it.
		 *
		 * The settings screen used to join the two halves of the file around
		 * the box in the browser, which is fast and wrong: the joining is not
		 * the composing. Rules meant for every crawler are folded into the
		 * group WordPress already wrote, and a preview that skips that step
		 * shows two groups for everybody where one will be served. Nobody
		 * would have noticed until a crawler read the one we did not mean.
		 */
		register_rest_route(
			self::NAMESPACE_V1,
			'/robots-preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'robots_preview' ),
				'permission_callback' => array( __CLASS__, 'may_manage' ),
				'args'                => array(
					'rules' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		/*
		 * The pages that could link to this one.
		 *
		 * Its own route rather than part of the score, because finding them
		 * means reading the content of every page that mentions the phrase and
		 * the score route runs six hundred milliseconds after every keystroke.
		 * This one runs when somebody opens the panel and when they accept
		 * something, which is twice in an editing session instead of forty
		 * times a minute.
		 */
		register_rest_route(
			self::NAMESPACE_V1,
			'/suggestions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'suggestions' ),
					'permission_callback' => array( __CLASS__, 'may_edit' ),
					'args'                => array(
						'post_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'accept_suggestion' ),
					'permission_callback' => array( __CLASS__, 'may_accept' ),
					'args'                => array(
						'post_id'   => array(
							'type'     => 'integer',
							'required' => true,
						),
						'source_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		/*
		 * What Google says about the page being edited, over the last twenty
		 * eight days. Its own route rather than part of the score, because it
		 * leaves this server and the score route does not: a call to Google
		 * on every keystroke would spend a customer's daily allowance in an
		 * afternoon. This runs when somebody opens the panel, and the answer
		 * is kept for six hours.
		 */
		register_rest_route(
			self::NAMESPACE_V1,
			'/search-console',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_console' ),
				'permission_callback' => array( __CLASS__, 'may_edit' ),
				'args'                => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		Connect\Google::register_rest();

		Admin\Bulk_Edit::register_routes();
	}

	/**
	 * The last twenty eight days for one page.
	 *
	 * A failure comes back as a sentence with a 200, not as an error, because
	 * the panel's job is to say what is happening. A revoked token drawn as a
	 * red REST failure in the editor sidebar tells somebody their editor is
	 * broken; the same fact as a sentence tells them to reconnect.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|array
	 */
	public static function search_console( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$status  = Connect\Google::status();

		if ( ! $status['connected'] ) {
			return array(
				'connected' => false,
				'trouble'   => $status['revoked']
					? __( 'Google no longer accepts this connection. Reconnect under SolSEO, Settings, Connections.', 'solseo' )
					: '',
			);
		}

		$figures = Connect\Search_Console::page( (string) get_permalink( $post_id ) );

		if ( is_wp_error( $figures ) ) {
			return array(
				'connected' => true,
				'trouble'   => $figures->get_error_message(),
			);
		}

		return array_merge(
			array(
				'connected' => true,
				'trouble'   => '',
			),
			$figures
		);
	}

	/**
	 * The pages that could link to the one being edited.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function suggestions( $request ) {
		return rest_ensure_response(
			array( 'suggestions' => Suggestions::for_post( (int) $request->get_param( 'post_id' ) ) )
		);
	}

	/**
	 * Whether the caller may write a link into the page a suggestion names.
	 *
	 * Two questions, not one. The page being edited is not the page being
	 * written to, and the one at risk is the second. Suggestions::accept asks
	 * the same thing again before it writes, which is the rule: hiding the
	 * button is a courtesy and refusing the write is the control, so the write
	 * refuses on its own whichever way in somebody found.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function may_accept( $request ) {
		$source = (int) $request->get_param( 'source_id' );

		return self::may_edit( $request ) && $source > 0 && current_user_can( 'edit_post', $source );
	}

	/**
	 * Write one suggested link.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function accept_suggestion( $request ) {
		$done = Suggestions::accept( (int) $request->get_param( 'source_id' ), (int) $request->get_param( 'post_id' ) );

		if ( is_wp_error( $done ) ) {
			return $done;
		}

		return rest_ensure_response(
			array(
				'done'        => $done,
				'suggestions' => Suggestions::for_post( (int) $request->get_param( 'post_id' ) ),
			)
		);
	}

	/**
	 * Whether the caller looks after this site.
	 *
	 * @return bool
	 */
	public static function may_manage() {
		return current_user_can( Admin\Menu::capability() );
	}

	/**
	 * Compose robots.txt from rules that have not been saved yet.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function robots_preview( $request ) {
		$rules = Frontend\Robots_Txt::sanitise_rules( (string) $request->get_param( 'rules' ) );

		return rest_ensure_response( array( 'robots' => Frontend\Robots_Txt::preview( $rules ) ) );
	}

	/**
	 * Whether the caller may edit the post being scored.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function may_edit( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$wp_says = $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );

		/*
		 * WordPress first, us second, and the second answer can only narrow the
		 * first. A map that could grant would be a privilege escalation with a
		 * settings screen in front of it.
		 */
		return $wp_says && Capabilities::can( Capabilities::EDIT_META );
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
				'source'      => $analysis['source'],

				/*
				 * The links on this page that point at no page of this site.
				 * Worked out here rather than in the browser, because "does
				 * this address resolve" is a question only the server can
				 * answer, and answered without a request, because the whole
				 * point of the editor half is that it keeps up with typing.
				 */
				'links'       => Checker::in_content( $paper['content'] ),

				/*
				 * The accessibility failures this writing causes. Pure, over
				 * the same content the score just read, so it costs no query
				 * and cannot disagree with the Health screen, which runs the
				 * same six functions.
				 */
				'a11y'        => Editor_Checks::run( $paper['content'] ),

				/*
				 * Anything else of theirs going for the same phrase. One index
				 * read on a meta key WordPress indexes, which is why this can
				 * sit on the route that runs while somebody is typing.
				 */
				'duplicates'  => Duplicates::for_post(
					(int) $request->get_param( 'post_id' ),
					$paper['keyword'],
					(string) get_post_type( (int) $request->get_param( 'post_id' ) )
				),
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
