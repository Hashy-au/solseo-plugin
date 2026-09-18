<?php
/**
 * What a long running job has to be able to answer.
 *
 * A job walks an ascending list of object IDs, a chunk at a time, and keeps
 * its place with a cursor rather than an offset. That matters: two of the
 * three jobs shipped here shrink their own result set as they run, because
 * they only select rows that still need the work. An offset over a shrinking
 * list skips rows, and a list that never shrinks under an offset never ends.
 *
 * @package SolSEO
 */

namespace SolSEO\Jobs;

use SolSEO\Admin\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for a job the runner can drive.
 */
abstract class Job {

	/**
	 * The key this job is known by, in the registry and in the browser.
	 *
	 * @return string
	 */
	abstract public static function id();

	/**
	 * What the screen says while this is running.
	 *
	 * @param array $args The arguments the job was started with.
	 * @return string
	 */
	abstract public static function label( array $args );

	/**
	 * Check the arguments and work out how much there is to do.
	 *
	 * @param array $args Raw arguments from the request.
	 * @return array|\WP_Error Keys: total, args, cursor.
	 */
	abstract public static function plan( array $args );

	/**
	 * Do up to one chunk of work after the cursor.
	 *
	 * @param array $state The job state.
	 * @return array Keys: cursor, done, changed, failed, finished.
	 */
	abstract public static function work( array $state );

	/**
	 * Check the work landed, once the cursor is exhausted.
	 *
	 * A job with nothing to check says so and is done.
	 *
	 * @param array $state The job state.
	 * @return array Keys: ok, message, failed.
	 */
	public static function verify( array $state ) {
		unset( $state );

		return array(
			'ok'      => true,
			'message' => '',
			'failed'  => array(),
		);
	}

	/**
	 * How many objects one chunk covers.
	 *
	 * @return int
	 */
	public static function chunk() {
		return 100;
	}

	/**
	 * How long one chunk may spend before it reports what it has done.
	 *
	 * Eight seconds keeps a chunk inside a thirty second host limit however
	 * slow one object turns out to be.
	 *
	 * @return int Seconds.
	 */
	public static function budget() {
		return 8;
	}

	/**
	 * What somebody needs to be allowed to do to run this.
	 *
	 * @return string
	 */
	public static function capability() {
		return Menu::capability();
	}

	/**
	 * Whether this job has anything to offer on this site.
	 *
	 * @return bool
	 */
	public static function available() {
		return true;
	}

	/**
	 * Whether a chunk has spent its time budget.
	 *
	 * @param float $started When the chunk started, from microtime( true ).
	 * @return bool
	 */
	protected static function out_of_time( $started ) {
		return ( microtime( true ) - $started ) >= static::budget();
	}

	/**
	 * The empty result a chunk returns when it did nothing.
	 *
	 * @param int  $cursor   Where the job has reached.
	 * @param bool $finished Whether there is nothing left.
	 * @return array
	 */
	protected static function result( $cursor, $finished = false ) {
		return array(
			'cursor'   => (int) $cursor,
			'done'     => 0,
			'changed'  => 0,
			'failed'   => array(),
			'finished' => (bool) $finished,
		);
	}
}
