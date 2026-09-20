<?php
/**
 * Two pages chasing the same phrase.
 *
 * Local, instant, and nothing is asked of anybody: the focus keyword is already
 * stored against every page, so the question "is anything else of mine going
 * for this" is one index read. No Search Console, no account, no wait.
 *
 * The interesting part is what it refuses to call a duplicate. A shop listing
 * and an article about the same phrase are usually both right, because one of
 * them wants somebody who is ready to buy and the other wants somebody who is
 * still reading, and the search results carry both. Reporting that pair would
 * teach somebody to ignore this the first week they used it, which is D-80.4
 * again: a checker that cries wolf is switched off, and then the real ones go
 * unread too.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

use SolSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the pages sharing a focus keyword.
 */
class Duplicates {

	/** How many pages one phrase reports before it stops naming them. */
	const NAMED = 10;

	/** How many phrases the screen lists at once. */
	const PHRASES = 200;

	/**
	 * Which side of the site a post type sits on.
	 *
	 * Pure. Shop is anything somebody buys, editorial is everything else, and
	 * the split exists for one reason: a pair with one on each side is not a
	 * problem and is not reported as one.
	 *
	 * The day a taxonomy term gets a focus keyword of its own, a category comes
	 * through here as a shop page or an editorial one and the same rule covers
	 * it without a second function.
	 *
	 * @param string $type A post type name.
	 * @return string Either shop or editorial.
	 */
	public static function side( $type ) {
		$shop = array( 'product', 'product_variation', 'download', 'shop_coupon' );

		/**
		 * Filter which post types count as the shop side of the site.
		 *
		 * @param array $shop Post type names.
		 */
		$shop = (array) apply_filters( 'solseo_shop_post_types', $shop );

		return in_array( (string) $type, $shop, true ) ? 'shop' : 'editorial';
	}

	/**
	 * Whether two pages sharing a phrase are actually competing.
	 *
	 * Pure.
	 *
	 * @param string $one The first post type.
	 * @param string $two The second post type.
	 * @return bool
	 */
	public static function competing( $one, $two ) {
		return self::side( $one ) === self::side( $two );
	}

	/**
	 * A phrase reduced to the thing two of them have to match on.
	 *
	 * Pure. Case and spacing are typing, not meaning.
	 *
	 * @param string $phrase A focus keyword.
	 * @return string
	 */
	public static function normalise( $phrase ) {
		$phrase = strtolower( trim( (string) $phrase ) );

		return (string) preg_replace( '/\s+/u', ' ', $phrase );
	}

	/**
	 * The published pages carrying a phrase, apart from one.
	 *
	 * @param string $phrase  A focus keyword.
	 * @param int    $exclude A post ID to leave out.
	 * @return array Each with id, title, type and side.
	 */
	public static function carrying( $phrase, $exclude = 0 ) {
		global $wpdb;

		$phrase = self::normalise( $phrase );

		if ( '' === $phrase ) {
			return array();
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = %s AND LOWER(TRIM(m.meta_value)) = %s
				AND p.post_status = 'publish' AND p.ID <> %d
				ORDER BY p.ID ASC LIMIT %d",
				Meta::PREFIX . 'focus_keyword',
				$phrase,
				(int) $exclude,
				self::NAMED
			),
			ARRAY_A
		);

		$found = array();

		foreach ( (array) $rows as $row ) {
			$found[] = array(
				'id'    => (int) $row['ID'],
				'title' => '' === (string) $row['post_title'] ? __( 'A page with no title', 'solseo' ) : (string) $row['post_title'],
				'type'  => (string) $row['post_type'],
				'side'  => self::side( (string) $row['post_type'] ),
			);
		}

		return $found;
	}

	/**
	 * What one page should be told about its phrase.
	 *
	 * Two answers, and they are different on purpose. `competing` is the pages
	 * on the same side of the site, which is the warning. `alongside` is the
	 * ones on the other side, which is a note and is never called a duplicate.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $phrase  The phrase, which the editor may have changed.
	 * @param string $type    The post type.
	 * @return array Keys: phrase, competing, alongside.
	 */
	public static function for_post( $post_id, $phrase, $type ) {
		$answer = array(
			'phrase'    => self::normalise( $phrase ),
			'competing' => array(),
			'alongside' => array(),
		);

		if ( '' === $answer['phrase'] ) {
			return $answer;
		}

		foreach ( self::carrying( $answer['phrase'], (int) $post_id ) as $other ) {
			if ( self::competing( $type, $other['type'] ) ) {
				$answer['competing'][] = $other;

				continue;
			}

			$answer['alongside'][] = $other;
		}

		return $answer;
	}

	/**
	 * One sentence for the page being edited.
	 *
	 * Pure.
	 *
	 * @param array $answer What for_post() returned.
	 * @return string
	 */
	public static function says( array $answer ) {
		if ( $answer['competing'] ) {
			$names = array();

			foreach ( $answer['competing'] as $other ) {
				$names[] = (string) $other['title'];
			}

			return sprintf(
				/* translators: 1: a focus keyword. 2: a list of page names. */
				__( '%2$s is already going for "%1$s". Two pages of yours aimed at one phrase split the links and the signals between them, and Google picks one, which is usually not the one you would have picked.', 'solseo' ),
				(string) $answer['phrase'],
				implode( ', ', $names )
			);
		}

		if ( $answer['alongside'] ) {
			return sprintf(
				/* translators: %s: a list of page names. */
				__( '%s is going for this phrase too, on the other side of the site. That is usually fine: a listing and an article about the same thing answer different questions, and the results have room for both.', 'solseo' ),
				implode( ', ', wp_list_pluck( $answer['alongside'], 'title' ) )
			);
		}

		return '';
	}

	/**
	 * Every phrase more than one page is going for.
	 *
	 * @param int $limit How many phrases.
	 * @return array Each with phrase, pages and competing.
	 */
	public static function all( $limit = self::PHRASES ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT LOWER(TRIM(m.meta_value)) AS phrase, COUNT(*) AS pages FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = %s AND TRIM(m.meta_value) <> '' AND p.post_status = 'publish'
				GROUP BY phrase HAVING pages > 1
				ORDER BY pages DESC, phrase ASC LIMIT %d",
				Meta::PREFIX . 'focus_keyword',
				(int) $limit
			),
			ARRAY_A
		);

		$found = array();

		foreach ( (array) $rows as $row ) {
			$pages = self::carrying( (string) $row['phrase'] );
			$group = self::group( $pages );

			$found[] = array(
				'phrase'    => (string) $row['phrase'],
				'pages'     => $pages,
				'competing' => $group['competing'],
				'alongside' => $group['alongside'],
			);
		}

		return $found;
	}

	/**
	 * Split a set of pages into the pairs that compete and the pairs that do not.
	 *
	 * Pure. Two pages on the same side compete; a set with one page on each side
	 * competes with nobody.
	 *
	 * @param array $pages Each with type.
	 * @return array Keys: competing, alongside.
	 */
	public static function group( array $pages ) {
		$sides = array(
			'shop'      => array(),
			'editorial' => array(),
		);

		foreach ( $pages as $page ) {
			$side = isset( $page['side'] ) ? (string) $page['side'] : self::side( isset( $page['type'] ) ? $page['type'] : '' );

			$sides[ $side ][] = $page;
		}

		$competing = array();
		$alongside = array();

		foreach ( $sides as $side ) {
			if ( count( $side ) > 1 ) {
				$competing = array_merge( $competing, $side );

				continue;
			}

			$alongside = array_merge( $alongside, $side );
		}

		return array(
			'competing' => $competing,
			'alongside' => $alongside,
		);
	}
}
