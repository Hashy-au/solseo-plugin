<?php
/**
 * Checks about the house style, rather than about search.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Spelling variant and the words this site has decided not to use.
 *
 * This group is the writing profile. It does not measure how the page reads:
 * the readability group already does that, and two groups marking the same
 * sentence would count it twice and then disagree about it. The profile's
 * reading and sentence targets are settings the readability group reads.
 */
class Writing_Checks extends Checks {

	/** How many offending words are named before the note says "and others". */
	const NAMED = 3;

	/**
	 * Group name.
	 *
	 * @return string
	 */
	public static function group() {
		return 'writing';
	}

	/**
	 * Run the group.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	public static function run( array $paper ) {
		return array(
			self::spelling( $paper ),
			self::banned( $paper ),
		);
	}

	/**
	 * Words spelled the way another country spells them.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function spelling( array $paper ) {
		$variant = (string) Options::get( 'writing_variant' );

		if ( 'off' === $variant || '' === $variant ) {
			return self::result( 'spelling', 2, self::SKIPPED, __( 'Spelling is not being checked against a variety of English.', 'solseo' ) );
		}

		if ( ! $paper['words'] ) {
			return self::result( 'spelling', 2, self::SKIPPED, __( 'There are no words to check yet.', 'solseo' ) );
		}

		$wanted = self::variants( $variant );
		$found  = array();

		foreach ( $paper['words'] as $word ) {
			$word = strtolower( $word );

			if ( isset( $wanted[ $word ] ) && ! isset( $found[ $word ] ) ) {
				$found[ $word ] = $wanted[ $word ];
			}
		}

		if ( ! $found ) {
			return self::result( 'spelling', 2, self::GOOD, self::right_spelling( $variant ) );
		}

		return self::result( 'spelling', 2, self::POOR, self::wrong_spelling( $found ) );
	}

	/**
	 * Words this site has decided not to use.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function banned( array $paper ) {
		$banned = self::banned_list();

		if ( ! $banned ) {
			return self::result( 'banned_words', 2, self::SKIPPED, __( 'No words have been ruled out on this site.', 'solseo' ) );
		}

		if ( ! $paper['words'] ) {
			return self::result( 'banned_words', 2, self::SKIPPED, __( 'There are no words to check yet.', 'solseo' ) );
		}

		$found = array();

		foreach ( $paper['words'] as $word ) {
			$word = strtolower( $word );

			if ( in_array( $word, $banned, true ) && ! in_array( $word, $found, true ) ) {
				$found[] = $word;
			}
		}

		if ( ! $found ) {
			return self::result( 'banned_words', 2, self::GOOD, __( 'None of the words this site rules out are here.', 'solseo' ) );
		}

		$named = array_slice( $found, 0, self::NAMED );

		$note = count( $found ) > self::NAMED
			/* translators: 1: a list of words, 2: how many more there are. */
			? sprintf( __( 'This site rules out %1$s, and %2$d more here.', 'solseo' ), self::listed( $named ), count( $found ) - self::NAMED )
			/* translators: %s: a list of words. */
			: sprintf( __( 'This site rules out %s.', 'solseo' ), self::listed( $named ) );

		return self::result( 'banned_words', 2, self::POOR, $note );
	}

	/**
	 * The words this site has ruled out.
	 *
	 * @return array
	 */
	public static function banned_list() {
		$stored = Options::get( 'writing_banned' );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'strtolower', array_map( 'trim', $stored ) ) ) );
	}

	/**
	 * The spellings to warn about, for a variety of English.
	 *
	 * @param string $variant One of au, gb or us.
	 * @return array Wrong spelling to right one.
	 */
	public static function variants( $variant ) {
		static $pairs = null;

		if ( null === $pairs ) {
			$pairs = (array) require __DIR__ . '/data/spelling.php';
		}

		// The list is written United States first, so the others read it as it stands.
		return 'us' === $variant ? array_flip( $pairs ) : $pairs;
	}

	/**
	 * What each variety is called on screen.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'au'  => __( 'Australian', 'solseo' ),
			'gb'  => __( 'British', 'solseo' ),
			'us'  => __( 'United States', 'solseo' ),
			'off' => __( 'Do not check', 'solseo' ),
		);
	}

	/**
	 * The note when every word is spelled the way this site spells.
	 *
	 * @param string $variant Variety of English.
	 * @return string
	 */
	protected static function right_spelling( $variant ) {
		$labels = self::labels();
		$label  = isset( $labels[ $variant ] ) ? $labels[ $variant ] : $labels['au'];

		/* translators: %s: a variety of English, such as Australian. */
		return sprintf( __( 'Spelling reads as %s English.', 'solseo' ), $label );
	}

	/**
	 * The note when it does not.
	 *
	 * @param array $found Wrong spelling to right one.
	 * @return string
	 */
	protected static function wrong_spelling( array $found ) {
		$pairs = array();

		foreach ( array_slice( $found, 0, self::NAMED, true ) as $wrong => $right ) {
			/* translators: 1: the spelling used, 2: the spelling this site uses. */
			$pairs[] = sprintf( __( '%1$s should be %2$s', 'solseo' ), $wrong, $right );
		}

		if ( count( $found ) > self::NAMED ) {
			/* translators: 1: a list of spelling corrections, 2: how many more there are. */
			return sprintf( __( '%1$s, and %2$d more.', 'solseo' ), self::listed( $pairs ), count( $found ) - self::NAMED );
		}

		/* translators: %s: a list of spelling corrections. */
		return sprintf( __( '%s.', 'solseo' ), self::listed( $pairs ) );
	}

	/**
	 * Join a few things into a readable list.
	 *
	 * @param array $items Items.
	 * @return string
	 */
	protected static function listed( array $items ) {
		if ( count( $items ) < 2 ) {
			return (string) reset( $items );
		}

		$last = array_pop( $items );

		/* translators: 1: a list, 2: the last item in it. */
		return sprintf( __( '%1$s and %2$s', 'solseo' ), implode( ', ', $items ), $last );
	}
}
