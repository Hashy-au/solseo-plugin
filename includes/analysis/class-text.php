<?php
/**
 * Sentence and word level text measurements.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * The linguistic side of the content score.
 */
class Text {

	/**
	 * Loaded word lists, keyed by file name.
	 *
	 * @var array
	 */
	protected static $lists = array();

	/**
	 * Split text into lowercase words.
	 *
	 * Apostrophes stay inside a word so "doesn't" counts once.
	 *
	 * @param string $text Plain text.
	 * @return array
	 */
	public static function words( $text ) {
		$text = self::normalise( $text );

		if ( ! preg_match_all( "/[\p{L}\p{N}][\p{L}\p{N}'’\-]*/u", $text, $matches ) ) {
			return array();
		}

		return array_map( 'mb_strtolower', $matches[0] );
	}

	/**
	 * Split text into sentences.
	 *
	 * @param string $text Plain text.
	 * @return array
	 */
	public static function sentences( $text ) {
		$text = self::normalise( $text );

		if ( '' === $text ) {
			return array();
		}

		$parts     = preg_split( '/(?<=[.!?])[\s\x{00a0}]+(?=[\p{Lu}\p{N}"\'“])/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = array();

		foreach ( (array) $parts as $part ) {
			$part = trim( $part );

			if ( '' !== $part ) {
				$sentences[] = $part;
			}
		}

		return $sentences;
	}

	/**
	 * Paragraph texts, taken from the markup rather than from blank lines.
	 *
	 * @param string $html Rendered content.
	 * @return array
	 */
	public static function paragraphs( $html ) {
		if ( ! preg_match_all( '#<p[^>]*>(.*?)</p>#si', (string) $html, $matches ) ) {
			return array();
		}

		$paragraphs = array();

		foreach ( $matches[1] as $paragraph ) {
			$text = \SolSEO\Content::plain( $paragraph );

			if ( '' !== $text ) {
				$paragraphs[] = $text;
			}
		}

		return $paragraphs;
	}

	/**
	 * Flesch reading ease, 0 to 100. Higher is easier.
	 *
	 * @param string $text Plain text.
	 * @return float|null Null when there is not enough text to measure.
	 */
	public static function reading_ease( $text ) {
		$sentences = self::sentences( $text );
		$words     = self::words( $text );

		if ( count( $words ) < 25 || ! $sentences ) {
			return null;
		}

		$syllables = 0;

		foreach ( $words as $word ) {
			$syllables += self::syllables( $word );
		}

		$score = 206.835
			- ( 1.015 * ( count( $words ) / count( $sentences ) ) )
			- ( 84.6 * ( $syllables / count( $words ) ) );

		return round( max( 0, min( 100, $score ) ), 1 );
	}

	/**
	 * Rough syllable count for an English word.
	 *
	 * @param string $word Single word.
	 * @return int
	 */
	public static function syllables( $word ) {
		$word = preg_replace( "/['’\-]/u", '', mb_strtolower( $word ) );
		$word = preg_replace( '/[^a-z]/', '', $word );

		if ( strlen( $word ) < 4 ) {
			return 1;
		}

		$word = preg_replace( '/(?:[^laeiouy]es|[^laeiouy]e)$/', '', $word );
		$word = preg_replace( '/^y/', '', $word );

		preg_match_all( '/[aeiouy]{1,2}/', $word, $matches );

		return max( 1, count( $matches[0] ) );
	}

	/**
	 * Whether a sentence is written in the passive voice.
	 *
	 * Looks for a form of "to be" or "to get" followed, within a short window,
	 * by a past participle. Regular participles are recognised by the -ed
	 * ending; irregular ones come from the word list.
	 *
	 * @param string $sentence One sentence.
	 * @return bool
	 */
	public static function is_passive( $sentence ) {
		$words       = self::words( $sentence );
		$auxiliaries = self::word_list( 'passive-auxiliaries' );
		$irregulars  = self::word_list( 'irregular-participles' );
		$exceptions  = self::word_list( 'not-participles' );
		$total       = count( $words );

		foreach ( $words as $index => $word ) {
			if ( ! in_array( $word, $auxiliaries, true ) ) {
				continue;
			}

			$window = min( $total, $index + 5 );

			for ( $next = $index + 1; $next < $window; $next++ ) {
				$candidate = $words[ $next ];

				if ( in_array( $candidate, $exceptions, true ) ) {
					continue;
				}

				if ( in_array( $candidate, $irregulars, true ) ) {
					return true;
				}

				if ( strlen( $candidate ) > 4 && 'ed' === substr( $candidate, -2 ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Share of sentences that open with, or contain, a transition word.
	 *
	 * @param array $sentences Sentences.
	 * @return float Percentage, 0 to 100.
	 */
	public static function transition_share( array $sentences ) {
		if ( ! $sentences ) {
			return 0.0;
		}

		$transitions = self::word_list( 'transition-words' );
		$found       = 0;

		foreach ( $sentences as $sentence ) {
			$words = self::words( $sentence );

			foreach ( $words as $word ) {
				if ( in_array( $word, $transitions, true ) ) {
					++$found;
					break;
				}
			}
		}

		return round( ( $found / count( $sentences ) ) * 100, 1 );
	}

	/**
	 * How often a phrase appears in a body of words, as a percentage.
	 *
	 * @param string $phrase Keyword or phrase.
	 * @param array  $words  Word list from words().
	 * @return float
	 */
	public static function density( $phrase, array $words ) {
		$total = count( $words );

		if ( ! $total ) {
			return 0.0;
		}

		$occurrences = self::count_phrase( $phrase, $words );
		$length      = max( 1, count( self::words( $phrase ) ) );

		return round( ( $occurrences * $length / $total ) * 100, 2 );
	}

	/**
	 * How many times a phrase appears in a word list.
	 *
	 * @param string $phrase Keyword or phrase.
	 * @param array  $words  Word list from words().
	 * @return int
	 */
	public static function count_phrase( $phrase, array $words ) {
		$needle = self::words( $phrase );
		$length = count( $needle );

		if ( ! $length ) {
			return 0;
		}

		$count = 0;
		$total = count( $words );

		for ( $i = 0; $i + $length <= $total; $i++ ) {
			if ( array_slice( $words, $i, $length ) === $needle ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Whether a phrase appears in a piece of text.
	 *
	 * @param string $phrase Keyword or phrase.
	 * @param string $text   Text to search.
	 * @return bool
	 */
	public static function contains( $phrase, $text ) {
		return self::count_phrase( $phrase, self::words( $text ) ) > 0;
	}

	/**
	 * Remove the words that carry no meaning on their own.
	 *
	 * @param array $words Word list.
	 * @return array
	 */
	public static function without_stop_words( array $words ) {
		return array_values( array_diff( $words, self::word_list( 'stop-words' ) ) );
	}

	/**
	 * How wide a string renders, in pixels.
	 *
	 * Search results are laid out in pixels rather than characters, so a title
	 * of capitals can be cut short while a longer one in lowercase survives.
	 *
	 * @param string $text Text to measure.
	 * @param int    $size Font size in pixels.
	 * @return int
	 */
	public static function pixel_width( $text, $size = 20 ) {
		$widths = self::word_list( 'character-widths' );
		$chars  = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		$total  = 0;

		foreach ( (array) $chars as $char ) {
			$total += isset( $widths[ $char ] ) ? $widths[ $char ] : 556;
		}

		return (int) round( $total / 1000 * $size );
	}

	/**
	 * Load one word list.
	 *
	 * @param string $name File name without the extension.
	 * @return array
	 */
	public static function word_list( $name ) {
		if ( isset( self::$lists[ $name ] ) ) {
			return self::$lists[ $name ];
		}

		$file = SOLSEO_PATH . 'includes/analysis/data/' . $name . '.php';

		self::$lists[ $name ] = is_readable( $file ) ? (array) require $file : array();

		return self::$lists[ $name ];
	}

	/**
	 * Straighten quotes and collapse whitespace before measuring.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	protected static function normalise( $text ) {
		$text = str_replace( array( '“', '”', '„' ), '"', (string) $text );
		$text = str_replace( array( '‘', '’', '‚' ), "'", $text );
		$text = preg_replace( '/\x{00a0}/u', ' ', $text );

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}
}
