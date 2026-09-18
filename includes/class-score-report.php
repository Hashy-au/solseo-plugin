<?php
/**
 * Site wide view of the content scores.
 *
 * @package SolSEO
 */

namespace SolSEO;

use SolSEO\Analysis\Analyser;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the stored scores back out.
 */
class Score_Report {

	/**
	 * Average score and how the pages are spread across the bands.
	 *
	 * @return array Keys: average, total, bands.
	 */
	public static function summary() {
		global $wpdb;

		$types = self::post_types_in();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_col(
			"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_solseo_score'
			AND p.post_status = 'publish'
			AND p.post_type IN ({$types})"
		);
		// phpcs:enable

		$bands = array(
			'excellent' => 0,
			'good'      => 0,
			'fair'      => 0,
			'poor'      => 0,
		);

		$total = 0;

		foreach ( $rows as $value ) {
			$score  = (int) $value;
			$total += $score;
			++$bands[ Analyser::band( $score ) ];
		}

		$count = count( $rows );

		return array(
			'average' => $count ? (int) round( $total / $count ) : 0,
			'total'   => $count,
			'bands'   => $bands,
		);
	}

	/**
	 * The published pages with the lowest scores.
	 *
	 * @param int $limit How many to return.
	 * @return array Each entry has post_id, title, score and edit link.
	 */
	public static function weakest( $limit = 5 ) {
		global $wpdb;

		$types = self::post_types_in();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, pm.meta_value AS score FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_solseo_score'
				AND p.post_status = 'publish'
				AND p.post_type IN ({$types})
				ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC, p.ID ASC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'post_id' => (int) $row['ID'],
				'title'   => $row['post_title'] ? $row['post_title'] : __( '(no title)', 'solseo' ),
				'score'   => (int) $row['score'],
				'edit'    => get_edit_post_link( (int) $row['ID'] ),
			);
		}

		return $out;
	}

	/**
	 * How many published pages have never been scored.
	 *
	 * @return int
	 */
	public static function unscored() {
		global $wpdb;

		$types = self::post_types_in();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_solseo_score'
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$types})
			AND pm.meta_id IS NULL"
		);
		// phpcs:enable
	}

	/**
	 * How many published pages a run would cover.
	 *
	 * @param string $scope Either missing or all.
	 * @return int
	 */
	public static function countable( $scope = 'missing' ) {
		global $wpdb;

		if ( 'missing' === $scope ) {
			return self::unscored();
		}

		$types = self::post_types_in();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the post type list is prepared one value at a time by post_types_in().
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$types})"
		);
		// phpcs:enable
	}

	/**
	 * How many published pages have no meta description of their own.
	 *
	 * A page with none is not broken: the template fills the gap from the
	 * excerpt. It is a page whose line in a search result was written by a
	 * machine rather than by whoever knows what the page is for.
	 *
	 * @return int
	 */
	public static function missing_description() {
		global $wpdb;

		$types = self::post_types_in();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the post type list is prepared one value at a time by post_types_in().
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_solseo_description'
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$types})
			AND (pm.meta_id IS NULL OR pm.meta_value = '')"
		);
		// phpcs:enable
	}

	/**
	 * How many published pages carry the setting that keeps them out of search.
	 *
	 * Meta::save() deletes the row for an empty value, so a row that exists and
	 * holds 1 is somebody having ticked the box, not a leftover.
	 *
	 * @return int
	 */
	public static function hidden_count() {
		global $wpdb;

		$types = self::post_types_in();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the post type list is prepared one value at a time by post_types_in().
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_solseo_robots_noindex'
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$types})
			AND pm.meta_value = '1'"
		);
		// phpcs:enable
	}

	/**
	 * The next published pages to score, after a cursor.
	 *
	 * @param string $scope Either missing, for pages with no score, or all.
	 * @param int    $after The last post ID finished.
	 * @param int    $limit How many to return.
	 * @return array Post IDs, ascending.
	 */
	public static function ids_after( $scope, $after, $limit ) {
		global $wpdb;

		$types = self::post_types_in();
		$join  = '';
		$where = '';

		if ( 'missing' === $scope ) {
			$join  = "LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_solseo_score'";
			$where = 'AND pm.meta_id IS NULL';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the post type list is prepared one value at a time by post_types_in().
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				{$join}
				WHERE p.post_status = 'publish'
				AND p.post_type IN ({$types})
				{$where}
				AND p.ID > %d
				ORDER BY p.ID ASC LIMIT %d",
				(int) $after,
				(int) $limit
			)
		);
		// phpcs:enable

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Work out and store the score for one page.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function score_one( $post_id ) {
		Analyser::store( $post_id );

		return true;
	}

	/**
	 * The managed post types as a quoted list for a SQL IN clause.
	 *
	 * @return string
	 */
	protected static function post_types_in() {
		global $wpdb;

		$types = array_map(
			function ( $type ) use ( $wpdb ) {
				return $wpdb->prepare( '%s', $type );
			},
			solseo_post_types()
		);

		return $types ? implode( ',', $types ) : "''";
	}
}
