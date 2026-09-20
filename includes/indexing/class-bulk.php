<?php
/**
 * Telling the engines about everything at once, after a migration.
 *
 * @package SolSEO
 */

namespace SolSEO\Indexing;

use SolSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * One button, for the day the site moves or the plugin is first switched on.
 *
 * Capped at a thousand pages, which is one call and covers a small business
 * site whole. A site with more than that has a reason to want the rest on a
 * schedule rather than on a button, and scheduling is the add-on's job.
 */
class Bulk {

	/** How many pages one press covers. */
	const LIMIT = 1000;

	/**
	 * The most recently changed published pages.
	 *
	 * @param int $limit How many at most.
	 * @return array Addresses.
	 */
	public static function recent( $limit = self::LIMIT ) {
		$limit = max( 1, min( self::LIMIT, (int) $limit ) );

		$found = get_posts(
			array(
				'post_type'        => solseo_post_types(),
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$urls = array();

		foreach ( (array) $found as $post ) {
			if ( ! Submit::worth_telling( $post ) ) {
				continue;
			}

			$urls[] = get_permalink( $post );
		}

		return array_values( array_filter( $urls ) );
	}

	/**
	 * Send them.
	 *
	 * @return array Keys: ok, says, sent.
	 */
	public static function run() {
		$urls = self::recent();

		if ( ! $urls ) {
			return array(
				'ok'   => true,
				'says' => __( 'There is nothing published to tell them about.', 'solseo' ),
				'sent' => 0,
			);
		}

		Submit::forget_queue();

		foreach ( $urls as $url ) {
			Submit::queue( $url );
		}

		return Submit::flush();
	}

	/**
	 * How many a press would cover, without sending anything.
	 *
	 * @return int
	 */
	public static function waiting() {
		return count( self::recent() );
	}
}
