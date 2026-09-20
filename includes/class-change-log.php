<?php
/**
 * Who changed what, and when.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * A short log of the things this plugin wrote.
 *
 * Capped, never autoloaded, and kept in one option. A log that can grow without
 * a ceiling is a log that eventually costs every page load on the site, and an
 * autoloaded one costs them all today.
 */
class Change_Log {

	/** One option, not autoloaded. */
	const OPTION = 'solseo_change_log';

	/** How many entries the free plugin keeps. */
	const LIMIT = 30;

	/** How long a run of the same work keeps adding to one entry. */
	const MERGE_SECONDS = 3600;

	/**
	 * Record one change.
	 *
	 * A run that spans several requests, which is what every chunked job is,
	 * passes the same `merge` key each time and lands as one entry with a
	 * count rather than as one entry per chunk. Four hundred products in one
	 * bulk run is one line in this log, which is what makes the log readable.
	 *
	 * An entry may carry a `count` of its own, for work that covers several
	 * things in one go: forty addresses submitted in one call is one entry
	 * saying forty, rather than forty calls to this and forty option writes.
	 *
	 * @param array $entry Keys: what, label, merge, count.
	 */
	public static function record( array $entry ) {
		$entries = self::all();

		$row = array(
			'what'  => isset( $entry['what'] ) ? (string) $entry['what'] : '',
			'label' => isset( $entry['label'] ) ? (string) $entry['label'] : '',
			'merge' => isset( $entry['merge'] ) ? (string) $entry['merge'] : '',
			'who'   => self::who(),
			'when'  => time(),
			'count' => isset( $entry['count'] ) ? max( 1, (int) $entry['count'] ) : 1,
		);

		if ( '' === $row['what'] ) {
			return;
		}

		$newest = $entries ? $entries[0] : null;

		if ( $newest && '' !== $row['merge'] && $newest['merge'] === $row['merge'] && ( $row['when'] - (int) $newest['when'] ) < self::MERGE_SECONDS ) {
			$entries[0]['count'] = (int) $newest['count'] + $row['count'];
			$entries[0]['when']  = $row['when'];

			self::store( $entries );

			return;
		}

		array_unshift( $entries, $row );

		self::store( array_slice( $entries, 0, self::limit() ) );
	}

	/**
	 * Every entry, newest first.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Forget everything.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * How many entries are kept.
	 *
	 * @return int
	 */
	public static function limit() {
		/**
		 * Filter how many change log entries are kept.
		 *
		 * The add-on raises this. Whatever it is raised to, it is a number:
		 * an unbounded log is a bug with a delay on it.
		 *
		 * @param int $limit How many entries.
		 */
		$limit = (int) apply_filters( 'solseo_change_log_limit', self::LIMIT );

		return max( 1, $limit );
	}

	/**
	 * Who is doing this.
	 *
	 * @return string
	 */
	protected static function who() {
		$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;

		if ( $user && ! empty( $user->display_name ) ) {
			return (string) $user->display_name;
		}

		return __( 'Not signed in', 'solseo' );
	}

	/**
	 * Write the log, never autoloaded.
	 *
	 * @param array $entries Entries.
	 */
	protected static function store( array $entries ) {
		update_option( self::OPTION, $entries, false );
	}
}
