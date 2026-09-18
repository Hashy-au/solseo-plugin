<?php
/**
 * Reads SEO fields that another plugin keeps in post meta.
 *
 * Four of the five sources work this way. The fifth keeps its fields in a
 * table of its own and is read by Table_Source.
 *
 * @package SolSEO
 */

namespace SolSEO\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * The post meta reader.
 */
class Meta_Source {

	/**
	 * How many posts hold a title or description from this source.
	 *
	 * @param array $source Source definition.
	 * @return int
	 */
	public static function count( array $source ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s) AND meta_value != ''",
				$source['post']['title'],
				$source['post']['description']
			)
		);
	}

	/**
	 * The next posts to work through, after a cursor.
	 *
	 * @param array $source Source definition.
	 * @param int   $after  The last post ID finished.
	 * @param int   $limit  How many to return.
	 * @return array Post IDs, ascending.
	 */
	public static function ids_after( array $source, $after, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				WHERE meta_key IN (%s, %s) AND meta_value != '' AND post_id > %d
				ORDER BY post_id ASC LIMIT %d",
				$source['post']['title'],
				$source['post']['description'],
				(int) $after,
				(int) $limit
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Posts that hold a title or description here and neither in SolSEO.
	 *
	 * @param array $source Source definition.
	 * @param int   $limit  How many to return.
	 * @return array Post IDs.
	 */
	public static function unverified( array $source, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT theirs.post_id FROM {$wpdb->postmeta} theirs
				LEFT JOIN {$wpdb->postmeta} ours
					ON ours.post_id = theirs.post_id
					AND ours.meta_key IN ('_solseo_title', '_solseo_description')
					AND ours.meta_value != ''
				WHERE theirs.meta_key IN (%s, %s)
				AND theirs.meta_value != ''
				AND ours.meta_id IS NULL
				ORDER BY theirs.post_id ASC LIMIT %d",
				$source['post']['title'],
				$source['post']['description'],
				(int) $limit
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * One post's fields, in our shape.
	 *
	 * @param array $source  Source definition.
	 * @param int   $post_id Post ID.
	 * @return array
	 */
	public static function read( array $source, $post_id ) {
		$raw = array();

		foreach ( $source['post'] as $field => $key ) {
			$stored = get_post_meta( $post_id, $key, true );

			if ( '' === $stored || null === $stored ) {
				continue;
			}

			$raw[ $field ] = $stored;
		}

		$values = Import::shape( $raw, $source );

		foreach ( isset( $source['flags'] ) ? $source['flags'] : array() as $field => $flag ) {
			if ( (string) get_post_meta( $post_id, $flag[0], true ) === (string) $flag[1] ) {
				$values[ $field ] = true;
			}
		}

		if ( ! empty( $source['robots'] ) ) {
			$robots = get_post_meta( $post_id, $source['robots'], true );

			if ( is_array( $robots ) ) {
				if ( in_array( 'noindex', $robots, true ) ) {
					$values['robots_noindex'] = true;
				}

				if ( in_array( 'nofollow', $robots, true ) ) {
					$values['robots_nofollow'] = true;
				}
			}
		}

		/*
		 * One source stores the title a search result shows and adds the site
		 * name at render time, unless a flag says the stored title is the whole
		 * of it. Copying the bare title from that source drops the site name
		 * from every page, quietly, and the import looks like it worked.
		 */
		if ( ! empty( $source['title_suffix'] ) && isset( $values['title'] ) ) {
			$rule = $source['title_suffix'];

			if ( (string) get_post_meta( $post_id, $rule[0], true ) !== (string) $rule[1] ) {
				$values['title'] .= $rule[2];
			}
		}

		return $values;
	}
}
