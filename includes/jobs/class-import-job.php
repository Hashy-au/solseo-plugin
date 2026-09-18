<?php
/**
 * Copying an existing set of SEO fields across, as a job.
 *
 * One click starts the whole thing. The screen used to ask somebody to press
 * a button once per hundred pages and keep count, which is a chore dressed up
 * as a feature.
 *
 * @package SolSEO
 */

namespace SolSEO\Jobs;

use SolSEO\Tools\Import;

defined( 'ABSPATH' ) || exit;

/**
 * The import job.
 */
class Import_Job extends Job {

	/**
	 * The key this job is known by.
	 *
	 * @return string
	 */
	public static function id() {
		return 'import';
	}

	/**
	 * How many posts one chunk covers.
	 *
	 * @return int
	 */
	public static function chunk() {
		return 100;
	}

	/**
	 * What the screen says while this is running.
	 *
	 * @param array $args The arguments the job was started with.
	 * @return string
	 */
	public static function label( array $args ) {
		unset( $args );

		return __( 'Copying the fields across.', 'solseo' );
	}

	/**
	 * Whether there is anything to import on this site.
	 *
	 * @return bool
	 */
	public static function available() {
		return (bool) Import::sources();
	}

	/**
	 * Check the arguments and work out how much there is to do.
	 *
	 * @param array $args Raw arguments from the request.
	 * @return array|\WP_Error
	 */
	public static function plan( array $args ) {
		$prefix  = isset( $args['source'] ) ? sanitize_text_field( (string) $args['source'] ) : '';
		$sources = Import::sources();

		if ( ! isset( $sources[ $prefix ] ) ) {
			return new \WP_Error(
				'solseo_no_source',
				__( 'There is nothing to import from that source.', 'solseo' ),
				array( 'status' => 400 )
			);
		}

		$only = self::only( $args );

		return array(
			'total'  => $only ? count( $only ) : (int) $sources[ $prefix ]['found'],
			'cursor' => 0,
			'args'   => array(
				'source'    => $prefix,
				'overwrite' => ! empty( $args['overwrite'] ),
				'only'      => $only,
			),
		);
	}

	/**
	 * Copy up to one chunk of posts.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function work( array $state ) {
		$started   = microtime( true );
		$prefix    = $state['args']['source'];
		$overwrite = ! empty( $state['args']['overwrite'] );
		$ids       = self::ids( $state );

		if ( ! $ids ) {
			return self::result( $state['cursor'], true );
		}

		$done    = 0;
		$changed = 0;
		$cursor  = (int) $state['cursor'];

		foreach ( $ids as $post_id ) {
			if ( Import::copy( $prefix, $post_id, $overwrite ) ) {
				++$changed;
			}

			$cursor = $post_id;
			++$done;

			if ( self::out_of_time( $started ) ) {
				break;
			}
		}

		return array(
			'cursor'   => $cursor,
			'done'     => $done,
			'changed'  => $changed,
			'failed'   => array(),
			'finished' => (int) $state['chunk'] > count( $ids ) && count( $ids ) === $done,
		);
	}

	/**
	 * Check every page that had something now has it here.
	 *
	 * This is what earns the offer to switch the other plugin off. A run that
	 * cannot answer it does not get to make that offer, because the offer is a
	 * promise that nothing was lost.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function verify( array $state ) {
		$prefix = $state['args']['source'];

		// A run over a chosen list only speaks for that list.
		if ( ! empty( $state['args']['only'] ) ) {
			return array(
				'ok'      => true,
				'message' => sprintf(
					/* translators: %d: number of pages. */
					_n( '%d page copied.', '%d pages copied.', (int) $state['changed'], 'solseo' ),
					(int) $state['changed']
				),
				'failed'  => array(),
			);
		}

		$missed = Import::unverified( $prefix, 200 );

		if ( $missed ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %d: number of pages that were not copied. */
					_n(
						'%d page still holds a title or description that did not come across.',
						'%d pages still hold a title or description that did not come across.',
						count( $missed ),
						'solseo'
					),
					count( $missed )
				),
				'failed'  => $missed,
			);
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: number of pages checked. */
				_n(
					'%d page checked. Every page that had a title or description has one here.',
					'%d pages checked. Every page that had a title or description has one here.',
					(int) $state['done'],
					'solseo'
				),
				(int) $state['done']
			),
			'failed'  => array(),
		);
	}

	/**
	 * The posts this chunk covers.
	 *
	 * @param array $state The job state.
	 * @return array Post IDs.
	 */
	protected static function ids( array $state ) {
		$chunk = (int) $state['chunk'];

		if ( empty( $state['args']['only'] ) ) {
			return Import::ids_after( $state['args']['source'], (int) $state['cursor'], $chunk );
		}

		$after = (int) $state['cursor'];
		$out   = array();

		foreach ( $state['args']['only'] as $id ) {
			if ( (int) $id <= $after ) {
				continue;
			}

			$out[] = (int) $id;

			if ( count( $out ) >= $chunk ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * A list of chosen posts, capped so one request cannot become the site.
	 *
	 * @param array $args Raw arguments.
	 * @return array Post IDs, ascending.
	 */
	protected static function only( array $args ) {
		if ( empty( $args['only'] ) || ! is_array( $args['only'] ) ) {
			return array();
		}

		$only = array_slice( array_unique( array_map( 'intval', $args['only'] ) ), 0, 500 );

		sort( $only );

		return array_values( array_filter( $only ) );
	}
}
