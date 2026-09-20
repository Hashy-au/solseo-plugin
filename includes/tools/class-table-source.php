<?php
/**
 * Reads SEO fields that another plugin keeps in a table of its own.
 *
 * Everything else we import from puts its fields in post meta, which is one
 * query shape for all of them. One source does not, so it gets a reader
 * rather than a branch in the middle of the other one.
 *
 * A site that never ran that plugin has no such table, so every query here is
 * preceded by a check. Without it the Tools screen prints a database error.
 *
 * @package SolSEO
 */

namespace SolSEO\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * The custom table reader.
 */
class Table_Source {

	/**
	 * Whether the table this source reads exists on this site.
	 *
	 * @param array $source Source definition.
	 * @return bool
	 */
	public static function present( array $source ) {
		global $wpdb;

		static $seen = array();

		$table = self::table( $source );

		if ( isset( $seen[ $table ] ) ) {
			return $seen[ $table ];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$seen[ $table ] = ( $found === $table );

		return $seen[ $table ];
	}

	/**
	 * How many rows hold a title or description.
	 *
	 * @param array $source Source definition.
	 * @return int
	 */
	public static function count( array $source ) {
		global $wpdb;

		if ( ! self::present( $source ) ) {
			return 0;
		}

		$table = self::table( $source );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the table name comes from our own data file, never from a request.
		return (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			"SELECT COUNT(*) FROM {$table}
			WHERE ( title IS NOT NULL AND title != '' )
			OR ( description IS NOT NULL AND description != '' )"
		);
		// phpcs:enable
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

		if ( ! self::present( $source ) ) {
			return array();
		}

		$table = self::table( $source );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the table name comes from our own data file, never from a request.
		$ids = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare(
				"SELECT post_id FROM {$table}
				WHERE ( ( title IS NOT NULL AND title != '' ) OR ( description IS NOT NULL AND description != '' ) )
				AND post_id > %d
				ORDER BY post_id ASC LIMIT %d",
				(int) $after,
				(int) $limit
			)
		);
		// phpcs:enable

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

		if ( ! self::present( $source ) ) {
			return array();
		}

		$table = self::table( $source );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the table name comes from our own data file, never from a request.
		$ids = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare(
				"SELECT theirs.post_id FROM {$table} theirs
				LEFT JOIN {$wpdb->postmeta} ours
					ON ours.post_id = theirs.post_id
					AND ours.meta_key IN ('_solseo_title', '_solseo_description')
					AND ours.meta_value != ''
				WHERE ( ( theirs.title IS NOT NULL AND theirs.title != '' )
					OR ( theirs.description IS NOT NULL AND theirs.description != '' ) )
				AND ours.meta_id IS NULL
				ORDER BY theirs.post_id ASC LIMIT %d",
				(int) $limit
			)
		);
		// phpcs:enable

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
		global $wpdb;

		if ( ! self::present( $source ) ) {
			return array();
		}

		$table = self::table( $source );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d", (int) $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().

		if ( ! is_array( $row ) ) {
			return array();
		}

		return self::map_row( $row, $source );
	}

	/**
	 * Turn one database row into our fields.
	 *
	 * Public and pure, so the mapping is tested without a database. Every trap
	 * in this function is a trap that would otherwise be found by somebody's
	 * site going wrong after an import.
	 *
	 * @param array $row    One row from the source table.
	 * @param array $source Source definition.
	 * @return array
	 */
	public static function map_row( array $row, array $source ) {
		$raw = array();

		foreach ( $source['columns'] as $field => $column ) {
			if ( ! isset( $row[ $column ] ) || '' === $row[ $column ] || null === $row[ $column ] ) {
				continue;
			}

			$raw[ $field ] = $row[ $column ];
		}

		/*
		 * The focus keyword is kept as JSON with the chosen phrase inside it,
		 * and separately as a plain column that is not always filled in.
		 */
		if ( ! empty( $source['keyphrases'] ) && ! empty( $row[ $source['keyphrases'] ] ) ) {
			$decoded = json_decode( (string) $row[ $source['keyphrases'] ], true );

			if ( isset( $decoded['focus']['keyphrase'] ) && '' !== $decoded['focus']['keyphrase'] ) {
				$raw['focus_keyword'] = (string) $decoded['focus']['keyphrase'];
			}
		}

		$values = Import::shape( $raw, $source );

		/*
		 * Per page robots settings only mean anything when the row says it is
		 * not using the site defaults. Reading them regardless would noindex
		 * pages that were never set to noindex.
		 */
		$gated = empty( $source['robots_gate'] ) || empty( $row[ $source['robots_gate'] ] );

		if ( $gated ) {
			foreach ( isset( $source['robots_columns'] ) ? $source['robots_columns'] : array() as $field => $column ) {
				if ( ! empty( $row[ $column ] ) ) {
					$values[ $field ] = true;
				}
			}
		}

		return $values;
	}

	/**
	 * The table this source reads, with the site prefix on it.
	 *
	 * @param array $source Source definition.
	 * @return string
	 */
	protected static function table( array $source ) {
		global $wpdb;

		return $wpdb->prefix . $source['table'];
	}
}
