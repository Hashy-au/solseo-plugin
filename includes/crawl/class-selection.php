<?php
/**
 * Which three hundred pages, and the sentence that says why those three hundred.
 *
 * A truncated crawl of a whole site and a complete crawl of part of one look
 * identical in a progress bar and are not the same thing. This plugin does the
 * second, which means the report can be trusted about the pages it covers and
 * has to say out loud which pages those were. A report that says "300 pages"
 * on a site with twelve hundred reads as a site with three hundred pages.
 *
 * @package SolSEO
 */

namespace SolSEO\Crawl;

use SolSEO\Links\Counts;
use SolSEO\Sitemaps\Controller;
use SolSEO\Sitemaps\Writer;

defined( 'ABSPATH' ) || exit;

/**
 * Picks the pages and explains the pick.
 */
class Selection {

	/**
	 * The four rules, in the order they spend the budget.
	 *
	 * Order runs in tens, the way the tab registry does, so a later rule can
	 * land between two of these without renumbering them.
	 */
	const RULES = array(
		'front'   => 10,
		'sitemap' => 20,
		'linked'  => 30,
		'recent'  => 40,
	);

	/**
	 * The pages to crawl, in the order they will be crawled.
	 *
	 * @param int $limit How many at most.
	 * @return array Each entry has url, chosen_by and post_id.
	 */
	public static function choose( $limit ) {
		return self::merge(
			array(
				'front'   => array( home_url( '/' ) ),
				'sitemap' => self::from_sitemap( $limit ),
				'linked'  => self::most_linked( $limit ),
				'recent'  => self::most_recent( $limit ),
			),
			$limit
		);
	}

	/**
	 * Fold the four lists into one, first rule wins, no address twice.
	 *
	 * Pure. The four lists overlap heavily on a real site, because the front
	 * page is in the sitemap and the page the rest of the site links to most
	 * usually is too. Counting one page twice would spend the budget on half as
	 * many pages while the screen carried on saying three hundred.
	 *
	 * @param array $lists Rule key to a list of addresses, in that rule's order.
	 * @param int   $limit How many at most.
	 * @return array
	 */
	public static function merge( array $lists, $limit ) {
		$limit  = (int) $limit;
		$chosen = array();
		$seen   = array();

		foreach ( array_keys( self::RULES ) as $rule ) {
			if ( empty( $lists[ $rule ] ) ) {
				continue;
			}

			foreach ( (array) $lists[ $rule ] as $entry ) {
				if ( count( $chosen ) >= $limit ) {
					return $chosen;
				}

				$url     = is_array( $entry ) ? (string) $entry['url'] : (string) $entry;
				$post_id = is_array( $entry ) && isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;
				$key     = self::key( $url );

				if ( '' === $key || isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;

				$chosen[] = array(
					'url'       => $url,
					'chosen_by' => $rule,
					'post_id'   => $post_id,
				);
			}
		}

		return $chosen;
	}

	/**
	 * How many of the chosen came from each rule.
	 *
	 * Pure.
	 *
	 * @param array $rows Chosen rows.
	 * @return array Rule key to count, in the order the rules run.
	 */
	public static function breakdown( array $rows ) {
		$counts = array();

		foreach ( array_keys( self::RULES ) as $rule ) {
			$found = 0;

			foreach ( $rows as $row ) {
				if ( isset( $row['chosen_by'] ) && $rule === $row['chosen_by'] ) {
					++$found;
				}
			}

			if ( $found ) {
				$counts[ $rule ] = $found;
			}
		}

		return $counts;
	}

	/**
	 * What one rule is called, in words somebody reads once and understands.
	 *
	 * @param string $rule Rule key.
	 * @return string
	 */
	public static function rule_label( $rule ) {
		$labels = array(
			'front'   => __( 'Your front page', 'solseo' ),
			'sitemap' => __( 'In your sitemap', 'solseo' ),
			'linked'  => __( 'Linked to most from the rest of the site', 'solseo' ),
			'recent'  => __( 'Changed most recently', 'solseo' ),
		);

		return isset( $labels[ $rule ] ) ? $labels[ $rule ] : '';
	}

	/**
	 * The line above the table.
	 *
	 * Pure.
	 *
	 * @param int $chosen How many were crawled.
	 * @param int $total  How many pages the site has.
	 * @return string
	 */
	public static function sentence( $chosen, $total ) {
		$chosen = (int) $chosen;
		$total  = (int) $total;

		if ( $chosen >= $total ) {
			return sprintf(
				/* translators: %s: a number of pages. */
				_n( '%s page, which is this site.', '%s pages, which is this site.', $chosen, 'solseo' ),
				number_format_i18n( $chosen )
			);
		}

		return sprintf(
			/* translators: 1: pages crawled, such as 300. 2: pages on the site, such as 1,240. */
			__( '%1$s of %2$s pages, picked in this order: your front page, then your sitemap in its own order, then the pages the rest of the site links to most, then the ones changed most recently.', 'solseo' ),
			number_format_i18n( $chosen ),
			number_format_i18n( $total )
		);
	}

	/**
	 * How many pages this site has that a crawl would want.
	 *
	 * @return int
	 */
	public static function total_pages() {
		$total = 0;

		foreach ( Controller::sections() as $section => $count ) {
			if ( 'author' === $section || 0 === strpos( $section, 'tax-' ) ) {
				continue;
			}

			$total += (int) $count;
		}

		return $total;
	}

	/**
	 * The addresses in the sitemap, in the sitemap's own order.
	 *
	 * Asked of the code that builds the sitemap rather than worked out again
	 * here, so a page the sitemap leaves out is a page the crawl leaves out and
	 * the two cannot start disagreeing about what belongs on this site.
	 *
	 * @param int $limit How many at most.
	 * @return array
	 */
	protected static function from_sitemap( $limit ) {
		$urls = array();

		foreach ( Controller::sections() as $section => $count ) {
			if ( 'author' === $section || 0 === strpos( $section, 'tax-' ) ) {
				continue;
			}

			foreach ( Writer::post_ids( $section, $limit ) as $post_id ) {
				$urls[] = array(
					'url'     => (string) get_permalink( $post_id ),
					'post_id' => (int) $post_id,
				);

				if ( count( $urls ) >= $limit ) {
					return $urls;
				}
			}
		}

		return $urls;
	}

	/**
	 * The pages the rest of the site points at most.
	 *
	 * @param int $limit How many at most.
	 * @return array
	 */
	protected static function most_linked( $limit ) {
		$urls = array();

		foreach ( Counts::most_linked( $limit ) as $post_id ) {
			$urls[] = array(
				'url'     => (string) get_permalink( $post_id ),
				'post_id' => (int) $post_id,
			);
		}

		return $urls;
	}

	/**
	 * The pages somebody touched most recently.
	 *
	 * @param int $limit How many at most.
	 * @return array
	 */
	protected static function most_recent( $limit ) {
		$query = new \WP_Query(
			array(
				'post_type'              => solseo_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => (int) $limit,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'has_password'           => false,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$urls = array();

		foreach ( (array) $query->posts as $post_id ) {
			$urls[] = array(
				'url'     => (string) get_permalink( $post_id ),
				'post_id' => (int) $post_id,
			);
		}

		return $urls;
	}

	/**
	 * What two addresses have to share to be the same page.
	 *
	 * Pure.
	 *
	 * @param string $url An address.
	 * @return string
	 */
	protected static function key( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		return rtrim( strtolower( $url ), '/' );
	}
}
