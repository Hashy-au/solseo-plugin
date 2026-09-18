<?php
/**
 * Scoring the published pages, as a job.
 *
 * The setup screen runs this over everything at the end, so the SEO column and
 * the dashboard have something in them the first time somebody looks.
 *
 * @package SolSEO
 */

namespace SolSEO\Jobs;

use SolSEO\Options;
use SolSEO\Score_Report;

defined( 'ABSPATH' ) || exit;

/**
 * The scoring job.
 */
class Score_Job extends Job {

	/**
	 * The key this job is known by.
	 *
	 * @return string
	 */
	public static function id() {
		return 'score';
	}

	/**
	 * How many pages one chunk covers.
	 *
	 * Smaller than the other two on purpose. Scoring a page renders its blocks
	 * and runs twenty eight checks over the result, so it is an order of
	 * magnitude dearer than copying a field, and a bar that moves every few
	 * seconds reads better than one that moves every thirty.
	 *
	 * @return int
	 */
	public static function chunk() {
		return 25;
	}

	/**
	 * What the screen says while this is running.
	 *
	 * @param array $args The arguments the job was started with.
	 * @return string
	 */
	public static function label( array $args ) {
		unset( $args );

		return __( 'Checking your pages.', 'solseo' );
	}

	/**
	 * Work out how much there is to do.
	 *
	 * @param array $args Raw arguments from the request.
	 * @return array|\WP_Error
	 */
	public static function plan( array $args ) {
		$scope = isset( $args['scope'] ) && 'all' === $args['scope'] ? 'all' : 'missing';

		return array(
			'total'  => Score_Report::countable( $scope ),
			'cursor' => 0,
			'args'   => array(
				'scope'   => $scope,
				'sitemap' => ! empty( $args['sitemap'] ),
			),
		);
	}

	/**
	 * Score up to one chunk of pages.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function work( array $state ) {
		$started = microtime( true );
		$scope   = $state['args']['scope'];
		$ids     = Score_Report::ids_after( $scope, (int) $state['cursor'], (int) $state['chunk'] );

		if ( ! $ids ) {
			return self::result( $state['cursor'], true );
		}

		$done   = 0;
		$cursor = (int) $state['cursor'];

		foreach ( $ids as $post_id ) {
			Score_Report::score_one( $post_id );

			$cursor = $post_id;
			++$done;

			if ( self::out_of_time( $started ) ) {
				break;
			}
		}

		return array(
			'cursor'   => $cursor,
			'done'     => $done,
			'changed'  => $done,
			'failed'   => array(),
			'finished' => (int) $state['chunk'] > count( $ids ) && count( $ids ) === $done,
		);
	}

	/**
	 * Say what happened, and rebuild the sitemap when the run was asked to.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function verify( array $state ) {
		if ( ! empty( $state['args']['sitemap'] ) && Options::get( 'sitemap_enabled' ) ) {
			\SolSEO\Sitemaps\Controller::clear_cache();
			flush_rewrite_rules( false );
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: number of pages scored. */
				_n( '%d page checked.', '%d pages checked.', (int) $state['done'], 'solseo' ),
				(int) $state['done']
			),
			'failed'  => array(),
		);
	}
}
