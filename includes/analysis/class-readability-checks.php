<?php
/**
 * Checks about how the page reads.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Sentence length, paragraph length, passive voice and the rest.
 */
class Readability_Checks extends Checks {

	/** Not worth measuring below this many words. */
	const MINIMUM_WORDS = 50;

	/**
	 * Group name.
	 *
	 * @return string
	 */
	public static function group() {
		return 'readability';
	}

	/**
	 * Run the group.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	public static function run( array $paper ) {
		if ( count( $paper['words'] ) < self::MINIMUM_WORDS ) {
			return array(
				self::result( 'reading_ease', 3, self::SKIPPED, __( 'There is not enough text to measure how it reads.', 'solseo' ) ),
			);
		}

		return array(
			self::reading_ease( $paper ),
			self::sentence_length( $paper ),
			self::paragraph_length( $paper ),
			self::subheading_distribution( $paper ),
			self::passive_voice( $paper ),
			self::transitions( $paper ),
			self::sentence_variety( $paper ),
		);
	}

	/**
	 * Flesch reading ease.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function reading_ease( array $paper ) {
		$score = Text::reading_ease( $paper['text'] );

		if ( null === $score ) {
			return self::result( 'reading_ease', 3, self::SKIPPED, __( 'There is not enough text to measure how it reads.', 'solseo' ) );
		}

		if ( $score >= 60 ) {
			/* translators: %s: reading ease score out of 100. */
			return self::result( 'reading_ease', 3, self::GOOD, sprintf( __( 'Reading ease %s. Most readers will follow this.', 'solseo' ), $score ) );
		}

		if ( $score >= 45 ) {
			/* translators: %s: reading ease score out of 100. */
			return self::result( 'reading_ease', 3, self::FAIR, sprintf( __( 'Reading ease %s. Shorter words and sentences would help.', 'solseo' ), $score ) );
		}

		/* translators: %s: reading ease score out of 100. */
		return self::result( 'reading_ease', 3, self::POOR, sprintf( __( 'Reading ease %s. This is hard going. Break the sentences up.', 'solseo' ), $score ) );
	}

	/**
	 * Share of long sentences.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function sentence_length( array $paper ) {
		$total = count( $paper['sentences'] );

		if ( ! $total ) {
			return self::result( 'sentence_length', 3, self::SKIPPED, __( 'There are no sentences to measure.', 'solseo' ) );
		}

		$long = 0;

		foreach ( $paper['sentences'] as $sentence ) {
			if ( count( Text::words( $sentence ) ) > 20 ) {
				++$long;
			}
		}

		$share = round( ( $long / $total ) * 100 );

		if ( $share <= 25 ) {
			return self::result( 'sentence_length', 3, self::GOOD, __( 'Sentence length is comfortable.', 'solseo' ) );
		}

		/* translators: %d: percentage of sentences over twenty words. */
		$note = sprintf( __( '%d%% of sentences run over twenty words. Split the longest ones.', 'solseo' ), $share );

		return self::result( 'sentence_length', 3, $share <= 35 ? self::FAIR : self::POOR, $note );
	}

	/**
	 * Longest paragraph.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function paragraph_length( array $paper ) {
		if ( ! $paper['paragraphs'] ) {
			return self::result( 'paragraph_length', 2, self::SKIPPED, __( 'There are no paragraphs to measure.', 'solseo' ) );
		}

		$longest = 0;

		foreach ( $paper['paragraphs'] as $paragraph ) {
			$longest = max( $longest, count( Text::words( $paragraph ) ) );
		}

		if ( $longest <= 120 ) {
			return self::result( 'paragraph_length', 2, self::GOOD, __( 'No paragraph runs too long.', 'solseo' ) );
		}

		/* translators: %d: length of the longest paragraph in words. */
		$note = sprintf( __( 'The longest paragraph is %d words. Break it in two.', 'solseo' ), $longest );

		return self::result( 'paragraph_length', 2, $longest <= 150 ? self::FAIR : self::POOR, $note );
	}

	/**
	 * How much text sits between subheadings.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function subheading_distribution( array $paper ) {
		$total = count( $paper['words'] );

		if ( $total < 300 ) {
			return self::result( 'subheading_distribution', 2, self::SKIPPED, __( 'The page is short enough to read in one run.', 'solseo' ) );
		}

		$sections = self::section_lengths( $paper );
		$longest  = $sections ? max( $sections ) : $total;

		if ( $longest <= 250 ) {
			return self::result( 'subheading_distribution', 2, self::GOOD, __( 'Subheadings are spread evenly through the page.', 'solseo' ) );
		}

		/* translators: %d: number of words in the longest run without a subheading. */
		$note = sprintf( __( '%d words run on without a subheading. Add one part way through.', 'solseo' ), $longest );

		return self::result( 'subheading_distribution', 2, $longest <= 350 ? self::FAIR : self::POOR, $note );
	}

	/**
	 * Share of passive sentences.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function passive_voice( array $paper ) {
		$total = count( $paper['sentences'] );

		if ( ! $total ) {
			return self::result( 'passive_voice', 2, self::SKIPPED, __( 'There are no sentences to measure.', 'solseo' ) );
		}

		$passive = 0;

		foreach ( $paper['sentences'] as $sentence ) {
			if ( Text::is_passive( $sentence ) ) {
				++$passive;
			}
		}

		$share = round( ( $passive / $total ) * 100 );

		if ( $share <= 10 ) {
			return self::result( 'passive_voice', 2, self::GOOD, __( 'The writing is mostly active.', 'solseo' ) );
		}

		/* translators: %d: percentage of sentences in the passive voice. */
		$note = sprintf( __( '%d%% of sentences are passive. Say who does the doing.', 'solseo' ), $share );

		return self::result( 'passive_voice', 2, $share <= 15 ? self::FAIR : self::POOR, $note );
	}

	/**
	 * Share of sentences carrying a transition word.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function transitions( array $paper ) {
		$share = Text::transition_share( $paper['sentences'] );

		if ( $share >= 30 ) {
			return self::result( 'transition_words', 2, self::GOOD, __( 'Sentences are linked with transition words.', 'solseo' ) );
		}

		/* translators: %s: percentage of sentences containing a transition word. */
		$note = sprintf( __( 'Only %s%% of sentences use a transition word, so the page reads as a list of statements.', 'solseo' ), $share );

		return self::result( 'transition_words', 2, $share >= 20 ? self::FAIR : self::POOR, $note );
	}

	/**
	 * Runs of sentences that start with the same word.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function sentence_variety( array $paper ) {
		$previous = '';
		$run      = 1;
		$worst    = 1;

		foreach ( $paper['sentences'] as $sentence ) {
			$words = Text::words( $sentence );
			$first = $words ? $words[0] : '';

			if ( '' !== $first && $first === $previous ) {
				++$run;
				$worst = max( $worst, $run );
			} else {
				$run = 1;
			}

			$previous = $first;
		}

		if ( $worst < 3 ) {
			return self::result( 'sentence_variety', 1, self::GOOD, __( 'Sentences start in different ways.', 'solseo' ) );
		}

		/* translators: %d: number of sentences in a row starting with the same word. */
		$note = sprintf( __( '%d sentences in a row start with the same word.', 'solseo' ), $worst );

		return self::result( 'sentence_variety', 1, self::POOR, $note );
	}

	/**
	 * Word counts of the runs of text between subheadings.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function section_lengths( array $paper ) {
		$parts = preg_split( '#<h[2-4][^>]*>.*?</h[2-4]>#si', (string) $paper['content'] );

		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return array( count( $paper['words'] ) );
		}

		$lengths = array();

		foreach ( $parts as $part ) {
			$lengths[] = count( Text::words( \SolSEO\Content::plain( $part ) ) );
		}

		return $lengths;
	}
}
