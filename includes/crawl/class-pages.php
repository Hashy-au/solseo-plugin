<?php
/**
 * The page table, which is also the frontier.
 *
 * A row with nothing in fetched_at is an address waiting to be looked at, and a
 * row with something in it is a result. Keeping both in one table is what makes
 * the crawl resumable for nothing: closing the browser leaves the rows exactly
 * where they were, and carrying on is the same query it was before.
 *
 * It is a table rather than an option because three hundred rows of a dozen
 * columns is a quarter of a megabyte, and a chunk that rewrote all of it every
 * few seconds would be the slowest part of the crawl by a distance.
 *
 * Every query here reads or writes that one table, whose name is $wpdb->prefix
 * plus a literal from Install::table() and cannot be passed through prepare(),
 * and every value in every query is prepared. The three database sniffs are
 * therefore switched off for the file rather than repeated on twenty lines,
 * and nothing else belongs in this file.
 *
 * @package SolSEO
 */

namespace SolSEO\Crawl;

use SolSEO\Install;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Reads and writes wp_solseo_crawl.
 */
class Pages {

	/**
	 * Empty the table and start again.
	 */
	public static function reset() {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Put the chosen addresses in, waiting.
	 *
	 * @param array $rows Each with url, chosen_by, post_id.
	 * @return int How many went in.
	 */
	public static function seed( array $rows ) {
		$added = 0;

		foreach ( $rows as $row ) {
			if ( self::add( isset( $row['url'] ) ? $row['url'] : '', 'seed', 0, $row ) ) {
				++$added;
			}
		}

		return $added;
	}

	/**
	 * Add one address, unless it is already in there.
	 *
	 * @param string $url      The address.
	 * @param string $source   'seed' for a page we chose, 'link' for one we found.
	 * @param int    $found_on The post whose content pointed at it.
	 * @param array  $extra    chosen_by and post_id, when the caller knows them.
	 * @return bool Whether a row was written.
	 */
	public static function add( $url, $source = 'link', $found_on = 0, array $extra = array() ) {
		global $wpdb;

		$url = trim( (string) $url );

		if ( '' === $url || ! Fetch::ours( $url ) ) {
			return false;
		}

		$table = self::table();

		$written = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (url, url_hash, source, chosen_by, post_id, found_on)
				VALUES (%s, %s, %s, %s, %d, %d)",
				substr( $url, 0, 255 ),
				md5( $url ),
				'seed' === $source ? 'seed' : 'link',
				isset( $extra['chosen_by'] ) ? substr( (string) $extra['chosen_by'], 0, 10 ) : '',
				isset( $extra['post_id'] ) ? (int) $extra['post_id'] : 0,
				(int) $found_on
			)
		);

		return (bool) $written;
	}

	/**
	 * The next addresses with nothing recorded against them.
	 *
	 * Walked by row id rather than by an offset, because the list shrinks as
	 * the crawl fills it in. An offset over a shrinking list skips rows, which
	 * is the same trap Job's own comment names.
	 *
	 * @param int $limit How many.
	 * @param int $after The last row id finished.
	 * @return array
	 */
	public static function waiting_rows( $limit, $after = 0 ) {
		global $wpdb;

		$table = self::table();

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE fetched_at IS NULL AND id > %d ORDER BY id ASC LIMIT %d",
				(int) $after,
				(int) $limit
			),
			ARRAY_A
		);
	}

	/**
	 * How many addresses are still waiting.
	 *
	 * @param int $after The last row id finished.
	 * @return int
	 */
	public static function waiting_count( $after = 0 ) {
		global $wpdb;

		$table = self::table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE fetched_at IS NULL AND id > %d",
				(int) $after
			)
		);
	}

	/**
	 * Write one answer back.
	 *
	 * @param int   $id     Row id.
	 * @param array $fields What the fetch said.
	 */
	public static function record( $id, array $fields ) {
		global $wpdb;

		$wpdb->update(
			self::table(),
			array(
				'status_code' => isset( $fields['status_code'] ) ? (int) $fields['status_code'] : 0,
				'redirect_to' => substr( isset( $fields['redirect_to'] ) ? (string) $fields['redirect_to'] : '', 0, 255 ),
				'canonical'   => substr( isset( $fields['canonical'] ) ? (string) $fields['canonical'] : '', 0, 255 ),
				'robots'      => substr( isset( $fields['robots'] ) ? (string) $fields['robots'] : '', 0, 100 ),
				'title'       => substr( isset( $fields['title'] ) ? (string) $fields['title'] : '', 0, 255 ),
				'description' => substr( isset( $fields['description'] ) ? (string) $fields['description'] : '', 0, 320 ),
				'h1'          => substr( isset( $fields['h1'] ) ? (string) $fields['h1'] : '', 0, 255 ),
				'h1_count'    => isset( $fields['h1_count'] ) ? (int) $fields['h1_count'] : 0,
				'words'       => isset( $fields['words'] ) ? (int) $fields['words'] : 0,
				'note'        => substr( isset( $fields['note'] ) ? (string) $fields['note'] : '', 0, 191 ),
				'fetched_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * How many rows there are of each kind.
	 *
	 * @return array Keys: seeds, links, waiting, ok, moved, gone, broken, unreachable.
	 */
	public static function summary() {
		global $wpdb;

		$table = self::table();

		$row = $wpdb->get_row(
			"SELECT
				SUM(source = 'seed') AS seeds,
				SUM(source = 'link') AS links,
				SUM(fetched_at IS NULL) AS waiting,
				SUM(status_code >= 200 AND status_code < 300) AS ok,
				SUM(status_code >= 300 AND status_code < 400) AS moved,
				SUM(status_code IN (404, 410)) AS gone,
				SUM(status_code >= 400 AND status_code NOT IN (404, 410)) AS broken,
				SUM(fetched_at IS NOT NULL AND status_code = 0) AS unreachable
			FROM {$table}",
			ARRAY_A
		);

		$counts = array();

		foreach ( array( 'seeds', 'links', 'waiting', 'ok', 'moved', 'gone', 'broken', 'unreachable' ) as $name ) {
			$counts[ $name ] = isset( $row[ $name ] ) ? (int) $row[ $name ] : 0;
		}

		return $counts;
	}

	/**
	 * Rows for the table on screen.
	 *
	 * @param array $args Keys: band, per_page, page.
	 * @return array Keys: rows, total.
	 */
	public static function listing( array $args = array() ) {
		global $wpdb;

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$bands    = self::bands();
		$band     = isset( $args['band'] ) ? (string) $args['band'] : '';
		$where    = isset( $bands[ $band ] ) ? $bands[ $band ] : '1=1';
		$table    = self::table();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d",
				$per_page,
				( $page - 1 ) * $per_page
			),
			ARRAY_A
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Every address that answered with a problem, or did not answer.
	 *
	 * @param int $limit How many.
	 * @return array
	 */
	public static function failures( $limit = 200 ) {
		global $wpdb;

		$table = self::table();

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE fetched_at IS NOT NULL AND (status_code >= 400 OR status_code = 0)
				ORDER BY status_code DESC, id ASC LIMIT %d",
				(int) $limit
			),
			ARRAY_A
		);
	}

	/**
	 * Every address that answered with a move.
	 *
	 * @param int $limit How many.
	 * @return array
	 */
	public static function moves( $limit = 200 ) {
		global $wpdb;

		$table = self::table();

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE redirect_to <> '' ORDER BY id ASC LIMIT %d",
				(int) $limit
			),
			ARRAY_A
		);
	}

	/**
	 * When the last row was written.
	 *
	 * @return string A MySQL date in UTC, or an empty string.
	 */
	public static function last_fetched() {
		global $wpdb;

		$table = self::table();

		return (string) $wpdb->get_var( "SELECT MAX(fetched_at) FROM {$table}" );
	}

	/**
	 * The bands the listing can be filtered by.
	 *
	 * Each one is a literal, chosen by key, so nothing anybody typed ever
	 * reaches the query.
	 *
	 * @return array Band key to a SQL condition over columns of this table.
	 */
	public static function bands() {
		return array(
			'ok'          => 'status_code >= 200 AND status_code < 300',
			'moved'       => 'status_code >= 300 AND status_code < 400',
			'gone'        => 'status_code >= 400',
			'unreachable' => 'fetched_at IS NOT NULL AND status_code = 0',
			'waiting'     => 'fetched_at IS NULL',
		);
	}

	/**
	 * What a band is called on screen.
	 *
	 * @param string $band Band key.
	 * @return string
	 */
	public static function band_label( $band ) {
		$labels = array(
			'ok'          => __( 'Answered', 'solseo' ),
			'moved'       => __( 'Redirected', 'solseo' ),
			'gone'        => __( 'Did not answer', 'solseo' ),
			'unreachable' => __( 'Could not be reached', 'solseo' ),
			'waiting'     => __( 'Not looked at yet', 'solseo' ),
		);

		return isset( $labels[ $band ] ) ? $labels[ $band ] : '';
	}

	/**
	 * The table name.
	 *
	 * @return string
	 */
	public static function table() {
		return Install::table( 'crawl' );
	}
}

// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
