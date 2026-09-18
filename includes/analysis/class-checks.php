<?php
/**
 * Shared plumbing for the check groups.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Base class every group of checks extends.
 */
abstract class Checks {

	const GOOD    = 'good';
	const FAIR    = 'fair';
	const POOR    = 'poor';
	const SKIPPED = 'skipped';

	/**
	 * The group name shown in the editor.
	 *
	 * @return string
	 */
	abstract public static function group();

	/**
	 * Run every check in the group.
	 *
	 * @param array $paper Paper from Paper::from_post().
	 * @return array List of results.
	 */
	abstract public static function run( array $paper );

	/**
	 * Build one result.
	 *
	 * @param string $id     Check name.
	 * @param int    $weight How much the check counts towards the score.
	 * @param string $status One of the status constants.
	 * @param string $note   What the writer should do about it.
	 * @return array
	 */
	protected static function result( $id, $weight, $status, $note ) {
		return array(
			'id'     => $id,
			'group'  => static::group(),
			'weight' => $weight,
			'status' => $status,
			'note'   => $note,
		);
	}

	/**
	 * Points a status is worth.
	 *
	 * @param string $status Status name.
	 * @return float
	 */
	public static function points( $status ) {
		if ( self::GOOD === $status ) {
			return 1.0;
		}

		return self::FAIR === $status ? 0.5 : 0.0;
	}
}
