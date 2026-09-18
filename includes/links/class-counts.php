<?php
/**
 * How many pages link to a page.
 *
 * The number a person reads is kept in post meta rather than worked out from
 * the table on every row of a list screen. A list of twenty posts already has
 * its meta loaded in one go, so the column costs nothing extra to draw.
 *
 * @package SolSEO
 */

namespace SolSEO\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the three stored counts in step with the table.
 */
class Counts {

	const INTERNAL = '_solseo_links_internal';
	const OUTBOUND = '_solseo_links_outbound';
	const INCOMING = '_solseo_links_incoming';

	/**
	 * Work out afresh how many pages link to each of these.
	 *
	 * One query however many were handed over, and the list is bounded by how
	 * many different pages one page can link to, so it is tens of rows rather
	 * than thousands.
	 *
	 * @param array $target_ids Post IDs whose incoming count may have moved.
	 */
	public static function refresh( array $target_ids ) {
		global $wpdb;

		$target_ids = array_values( array_unique( array_filter( array_map( 'intval', $target_ids ) ) ) );

		if ( ! $target_ids ) {
			return;
		}

		$table  = $wpdb->prefix . 'solseo_links';
		$holes  = implode( ',', array_fill( 0, count( $target_ids ), '%d' ) );
		$counts = array_fill_keys( $target_ids, 0 );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the IN list is one %d per id, built above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT target_id, COUNT(DISTINCT source_id) AS total FROM {$table}
				WHERE link_type = 'internal'
				AND source_id <> target_id
				AND target_id IN ({$holes})
				GROUP BY target_id",
				$target_ids
			),
			ARRAY_A
		);
		// phpcs:enable

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['target_id'] ] = (int) $row['total'];
		}

		foreach ( $counts as $post_id => $total ) {
			update_post_meta( $post_id, self::INCOMING, $total );
		}
	}

	/**
	 * The three numbers for one page.
	 *
	 * @param int $post_id Post ID.
	 * @return array Keys: internal, outbound, incoming. Null where unknown.
	 */
	public static function of( $post_id ) {
		$internal = get_post_meta( $post_id, self::INTERNAL, true );
		$outbound = get_post_meta( $post_id, self::OUTBOUND, true );
		$incoming = get_post_meta( $post_id, self::INCOMING, true );

		return array(
			'internal' => '' === $internal ? null : (int) $internal,
			'outbound' => '' === $outbound ? null : (int) $outbound,
			'incoming' => '' === $incoming ? null : (int) $incoming,
		);
	}

	/**
	 * Forget everything about one page.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function forget( $post_id ) {
		delete_post_meta( $post_id, self::INTERNAL );
		delete_post_meta( $post_id, self::OUTBOUND );
		delete_post_meta( $post_id, self::INCOMING );
	}
}
