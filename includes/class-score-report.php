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
	 * Score every published page that has no score yet.
	 *
	 * @param int $limit How many to work through in one pass.
	 * @return int How many were scored.
	 */
	public static function score_missing( $limit = 50 ) {
		$posts = get_posts(
			array(
				'post_type'      => solseo_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_solseo_score',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $posts as $post_id ) {
			Analyser::store( $post_id );
		}

		return count( $posts );
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
