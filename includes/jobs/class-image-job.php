<?php
/**
 * Writing alt text for the images that have none, as a job.
 *
 * @package SolSEO
 */

namespace SolSEO\Jobs;

use SolSEO\Tools\Alt_Text;

defined( 'ABSPATH' ) || exit;

/**
 * The image description job.
 */
class Image_Job extends Job {

	/**
	 * The key this job is known by.
	 *
	 * @return string
	 */
	public static function id() {
		return 'images';
	}

	/**
	 * How many images one chunk covers.
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
		if ( ! empty( $args['post_id'] ) ) {
			return __( 'Describing the images on this page.', 'solseo' );
		}

		return __( 'Describing the images.', 'solseo' );
	}

	/**
	 * Whether there is anything to describe.
	 *
	 * @return bool
	 */
	public static function available() {
		return (bool) Alt_Text::count_missing();
	}

	/**
	 * Work out how much there is to do.
	 *
	 * @param array $args Raw arguments from the request.
	 * @return array|\WP_Error
	 */
	public static function plan( array $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$only    = self::only( $args );

		/*
		 * Pointed at one page, the list of images IS the plan. Working it out
		 * here rather than on every chunk means the bar counts down against a
		 * number that was true when somebody pressed the button, and it means
		 * a page whose images are all described says so instead of starting a
		 * job with nothing in it.
		 */
		if ( $post_id && ! $only ) {
			$only = Alt_Text::missing_for_post( $post_id );

			if ( ! $only ) {
				return new \WP_Error(
					'solseo_nothing_to_describe',
					__( 'Every image on this page already has alt text.', 'solseo' ),
					array( 'status' => 400 )
				);
			}
		}

		return array(
			'total'  => $only ? count( $only ) : Alt_Text::count_missing(),
			'cursor' => 0,
			'args'   => array(
				'only'    => $only,
				'post_id' => $post_id,
			),
		);
	}

	/**
	 * Describe up to one chunk of images.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function work( array $state ) {
		$started = microtime( true );
		$ids     = self::ids( $state );

		if ( ! $ids ) {
			return self::result( $state['cursor'], true );
		}

		$done    = 0;
		$changed = 0;
		$skipped = array();
		$cursor  = (int) $state['cursor'];

		foreach ( $ids as $id ) {
			if ( Alt_Text::fill_one( $id ) ) {
				++$changed;
			} else {
				/*
				 * An image whose file name says nothing readable and which is
				 * attached to nothing cannot be described from what is here.
				 * It is named rather than skipped in silence, so somebody can
				 * write those few themselves.
				 */
				$skipped[] = $id;
			}

			$cursor = $id;
			++$done;

			if ( self::out_of_time( $started ) ) {
				break;
			}
		}

		return array(
			'cursor'   => $cursor,
			'done'     => $done,
			'changed'  => $changed,
			'failed'   => $skipped,
			'finished' => (int) $state['chunk'] > count( $ids ) && count( $ids ) === $done,
		);
	}

	/**
	 * Say what happened.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function verify( array $state ) {
		$message = sprintf(
			/* translators: %d: number of images given alt text. */
			_n( '%d image described.', '%d images described.', (int) $state['changed'], 'solseo' ),
			(int) $state['changed']
		);

		if ( $state['failed_total'] ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of images with nothing to go on. */
				_n(
					'%d had nothing to go on: no title, no parent page and a file name that reads as nothing.',
					'%d had nothing to go on: no title, no parent page and file names that read as nothing.',
					(int) $state['failed_total'],
					'solseo'
				),
				(int) $state['failed_total']
			);
		}

		return array(
			'ok'      => true,
			'message' => $message,
			'failed'  => array(),
		);
	}

	/**
	 * The images this chunk covers.
	 *
	 * @param array $state The job state.
	 * @return array Attachment IDs.
	 */
	protected static function ids( array $state ) {
		$chunk = (int) $state['chunk'];

		if ( empty( $state['args']['only'] ) ) {
			return Alt_Text::ids_after( (int) $state['cursor'], $chunk );
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
	 * The images somebody ticked, capped and sorted.
	 *
	 * @param array $args Raw arguments.
	 * @return array Attachment IDs, ascending.
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
