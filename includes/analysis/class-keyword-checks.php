<?php
/**
 * Checks that need a focus keyword.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

use SolSEO\Contract;

defined( 'ABSPATH' ) || exit;

/**
 * How well the page is aimed at the keyword it was written for.
 */
class Keyword_Checks extends Checks {

	/**
	 * Group name.
	 *
	 * @return string
	 */
	public static function group() {
		return 'keyword';
	}

	/**
	 * Run the group.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	public static function run( array $paper ) {
		$keyword = $paper['keyword'];

		if ( '' === $keyword ) {
			return array(
				self::result( 'keyword_set', 4, self::POOR, __( 'Set a focus keyword. Every check below measures the page against it.', 'solseo' ) ),
			);
		}

		return array(
			self::result( 'keyword_set', 4, self::GOOD, __( 'A focus keyword is set.', 'solseo' ) ),
			self::in_title( $paper ),
			self::title_position( $paper ),
			self::in_description( $paper ),
			self::in_slug( $paper ),
			self::in_opening( $paper ),
			self::in_subheading( $paper ),
			self::in_alt_text( $paper ),
			self::density( $paper ),
			self::length( $paper ),
			self::unique( $paper ),
		);
	}

	/**
	 * Keyword somewhere in the SEO title.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function in_title( array $paper ) {
		if ( Text::contains( $paper['keyword'], $paper['title'] ) ) {
			return self::result( 'keyword_in_title', 4, self::GOOD, __( 'The focus keyword is in the SEO title.', 'solseo' ) );
		}

		if ( self::shares_words( $paper['keyword'], $paper['title'] ) ) {
			return self::result( 'keyword_in_title', 4, self::FAIR, __( 'Part of the focus keyword is in the SEO title. Use the whole phrase.', 'solseo' ) );
		}

		return self::result( 'keyword_in_title', 4, self::POOR, __( 'Put the focus keyword in the SEO title.', 'solseo' ) );
	}

	/**
	 * Keyword near the front of the title.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function title_position( array $paper ) {
		$words    = Text::words( $paper['title'] );
		$needle   = Text::words( $paper['keyword'] );
		$position = self::position( $needle, $words );

		if ( null === $position ) {
			return self::result( 'keyword_title_position', 2, self::POOR, __( 'The focus keyword is not in the SEO title, so it cannot lead it.', 'solseo' ) );
		}

		if ( $position <= max( 1, (int) floor( count( $words ) / 2 ) ) ) {
			return self::result( 'keyword_title_position', 2, self::GOOD, __( 'The focus keyword is in the first half of the title.', 'solseo' ) );
		}

		return self::result( 'keyword_title_position', 2, self::FAIR, __( 'Move the focus keyword closer to the start of the title.', 'solseo' ) );
	}

	/**
	 * Keyword in the meta description.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function in_description( array $paper ) {
		if ( '' === trim( $paper['description'] ) ) {
			return self::result( 'keyword_in_description', 3, self::POOR, __( 'Write a meta description and use the focus keyword in it.', 'solseo' ) );
		}

		if ( Text::contains( $paper['keyword'], $paper['description'] ) ) {
			return self::result( 'keyword_in_description', 3, self::GOOD, __( 'The focus keyword is in the meta description.', 'solseo' ) );
		}

		return self::result( 'keyword_in_description', 3, self::POOR, __( 'Add the focus keyword to the meta description.', 'solseo' ) );
	}

	/**
	 * Keyword in the URL slug.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function in_slug( array $paper ) {
		$slug = str_replace( array( '-', '_', '/' ), ' ', rawurldecode( (string) $paper['slug'] ) );

		if ( '' === trim( $slug ) ) {
			return self::result( 'keyword_in_slug', 3, self::SKIPPED, __( 'This page has no slug yet.', 'solseo' ) );
		}

		$needle = Text::without_stop_words( Text::words( $paper['keyword'] ) );
		$found  = array_intersect( $needle, Text::words( $slug ) );

		if ( $needle && count( $found ) === count( $needle ) ) {
			return self::result( 'keyword_in_slug', 3, self::GOOD, __( 'The focus keyword is in the URL.', 'solseo' ) );
		}

		if ( $found ) {
			return self::result( 'keyword_in_slug', 3, self::FAIR, __( 'Part of the focus keyword is in the URL.', 'solseo' ) );
		}

		return self::result( 'keyword_in_slug', 3, self::POOR, __( 'Put the focus keyword in the URL.', 'solseo' ) );
	}

	/**
	 * Keyword in the opening of the content.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function in_opening( array $paper ) {
		if ( ! $paper['words'] ) {
			return self::result( 'keyword_in_opening', 3, self::SKIPPED, __( 'There is no content to check yet.', 'solseo' ) );
		}

		if ( Text::contains( $paper['keyword'], $paper['opening'] ) ) {
			return self::result( 'keyword_in_opening', 3, self::GOOD, __( 'The focus keyword appears in the first paragraph.', 'solseo' ) );
		}

		$first_tenth = array_slice( $paper['words'], 0, max( 30, (int) ceil( count( $paper['words'] ) / 10 ) ) );

		if ( Text::count_phrase( $paper['keyword'], $first_tenth ) ) {
			return self::result( 'keyword_in_opening', 3, self::FAIR, __( 'The focus keyword appears early, but not in the first paragraph.', 'solseo' ) );
		}

		return self::result( 'keyword_in_opening', 3, self::POOR, __( 'Use the focus keyword in the first paragraph.', 'solseo' ) );
	}

	/**
	 * Keyword in a subheading.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function in_subheading( array $paper ) {
		$subheadings = array_filter(
			$paper['headings'],
			function ( $heading ) {
				return $heading['level'] >= 2 && $heading['level'] <= 4;
			}
		);

		if ( ! $subheadings ) {
			return self::result( 'keyword_in_subheading', 2, self::POOR, __( 'Break the page up with subheadings and use the focus keyword in one of them.', 'solseo' ) );
		}

		foreach ( $subheadings as $heading ) {
			if ( Text::contains( $paper['keyword'], $heading['text'] ) ) {
				return self::result( 'keyword_in_subheading', 2, self::GOOD, __( 'The focus keyword is in a subheading.', 'solseo' ) );
			}
		}

		return self::result( 'keyword_in_subheading', 2, self::POOR, __( 'Use the focus keyword in at least one subheading.', 'solseo' ) );
	}

	/**
	 * Keyword in image alt text.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function in_alt_text( array $paper ) {
		if ( ! $paper['images'] ) {
			return self::result( 'keyword_in_alt', 2, self::SKIPPED, __( 'There are no images on this page.', 'solseo' ) );
		}

		foreach ( $paper['images'] as $image ) {
			// Contract::alt_text() rather than $image['alt'] directly: alt is null
			// when the attribute is absent, and '' !== null is true, which would
			// hand null to a string function (D-207.2).
			$alt = Contract::alt_text( $image );

			if ( '' !== $alt && Text::contains( $paper['keyword'], $alt ) ) {
				return self::result( 'keyword_in_alt', 2, self::GOOD, __( 'An image alt text contains the focus keyword.', 'solseo' ) );
			}
		}

		return self::result( 'keyword_in_alt', 2, self::POOR, __( 'Use the focus keyword in the alt text of one image.', 'solseo' ) );
	}

	/**
	 * How often the keyword appears.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function density( array $paper ) {
		if ( count( $paper['words'] ) < 100 ) {
			return self::result( 'keyword_density', 3, self::SKIPPED, __( 'Density needs at least 100 words to mean anything.', 'solseo' ) );
		}

		$density = Text::density( $paper['keyword'], $paper['words'] );

		if ( $density >= 0.5 && $density <= 2.5 ) {
			/* translators: %s: keyword density as a percentage. */
			return self::result( 'keyword_density', 3, self::GOOD, sprintf( __( 'Keyword density is %s%%, which sits in the useful range.', 'solseo' ), $density ) );
		}

		if ( $density > 2.5 ) {
			/* translators: %s: keyword density as a percentage. */
			return self::result( 'keyword_density', 3, $density > 3.5 ? self::POOR : self::FAIR, sprintf( __( 'Keyword density is %s%%. Cut some repetitions.', 'solseo' ), $density ) );
		}

		/* translators: %s: keyword density as a percentage. */
		return self::result( 'keyword_density', 3, $density >= 0.25 ? self::FAIR : self::POOR, sprintf( __( 'Keyword density is %s%%. Use the keyword a few more times.', 'solseo' ), $density ) );
	}

	/**
	 * How long the keyword is.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function length( array $paper ) {
		$words = count( Text::words( $paper['keyword'] ) );

		if ( $words <= 4 ) {
			return self::result( 'keyword_length', 1, self::GOOD, __( 'The focus keyword is a workable length.', 'solseo' ) );
		}

		if ( $words <= 6 ) {
			return self::result( 'keyword_length', 1, self::FAIR, __( 'The focus keyword is long. Shorter phrases are easier to rank for.', 'solseo' ) );
		}

		return self::result( 'keyword_length', 1, self::POOR, __( 'The focus keyword reads as a sentence. Cut it back to a phrase.', 'solseo' ) );
	}

	/**
	 * Whether another post already targets this keyword.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function unique( array $paper ) {
		$other = self::other_post_with_keyword( $paper['keyword'], (int) $paper['post_id'] );

		if ( ! $other ) {
			return self::result( 'keyword_unique', 2, self::GOOD, __( 'No other page targets this keyword.', 'solseo' ) );
		}

		return self::result(
			'keyword_unique',
			2,
			self::POOR,
			sprintf(
				/* translators: %s: title of the other post. */
				__( '"%s" already targets this keyword. Two pages chasing one phrase split the result.', 'solseo' ),
				get_the_title( $other )
			)
		);
	}

	/**
	 * Find another published post using the same focus keyword.
	 *
	 * @param string $keyword Focus keyword.
	 * @param int    $post_id Post to exclude.
	 * @return int Post ID, or 0.
	 */
	protected static function other_post_with_keyword( $keyword, $post_id ) {
		global $wpdb;

		$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- a meta_value lookup WP_Query cannot express, and a cache here would report a keyword clash that has just been resolved in another tab.
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_solseo_focus_keyword'
				AND pm.meta_value = %s
				AND pm.post_id != %d
				AND p.post_status = 'publish'
				LIMIT 1",
				$keyword,
				$post_id
			)
		);

		return (int) $found;
	}

	/**
	 * Index of the first keyword word inside a word list.
	 *
	 * @param array $needle Keyword words.
	 * @param array $words  Words to search.
	 * @return int|null
	 */
	protected static function position( array $needle, array $words ) {
		$length = count( $needle );

		if ( ! $length ) {
			return null;
		}

		$total = count( $words );

		for ( $i = 0; $i + $length <= $total; $i++ ) {
			if ( array_slice( $words, $i, $length ) === $needle ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Whether any meaningful keyword word appears in a string.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $text    Text to search.
	 * @return bool
	 */
	protected static function shares_words( $keyword, $text ) {
		$needle = Text::without_stop_words( Text::words( $keyword ) );

		return (bool) array_intersect( $needle, Text::words( $text ) );
	}
}
