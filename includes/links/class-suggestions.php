<?php
/**
 * The pages that should be linking to this one, and the sentence to change.
 *
 * A suggestion is not "add more internal links". It is one page, one sentence
 * already written on it, and the words in that sentence that would become the
 * link, so the person reading it can decide in about two seconds. Accepting one
 * writes a real anchor into that page and one line into the change log.
 *
 * Everything that decides WHERE a link may go is pure and has a test for each
 * branch, because this rewrites somebody's published page. It never puts a link
 * inside another link, inside a heading, inside a shortcode, or inside a tag.
 *
 * @package SolSEO
 */

namespace SolSEO\Links;

use SolSEO\Change_Log;
use SolSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the link index the other way round: who is missing.
 */
class Suggestions {

	/** How many suggestions one page gets. More than this is a list nobody reads. */
	const SHOWN = 5;

	/** How many candidate pages are read before the search gives up. */
	const CANDIDATES = 40;

	/** How much of a sentence is worth showing. */
	const SENTENCE = 200;

	/** The tags that end one block of writing and start the next. */
	const BLOCKS = '#<(?:br|/?p|/?div|/?h[1-6]|/?li|/?tr|/?td|/?th|/?section|/?article|/?blockquote|/?figure|/?figcaption)\b[^>]*>#i';

	/**
	 * The phrase a page should be found for.
	 *
	 * The focus keyword when there is one, the title when there is not, because
	 * a page nobody has given a keyword is still a page other pages should link
	 * to and its title is the best guess anybody has.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function phrase_for( $post_id ) {
		$keyword = trim( (string) get_post_meta( (int) $post_id, Meta::PREFIX . 'focus_keyword', true ) );

		if ( '' !== $keyword ) {
			return $keyword;
		}

		$post = get_post( (int) $post_id );

		return $post ? trim( (string) $post->post_title ) : '';
	}

	/**
	 * Five pages that could link to this one and do not.
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   How many.
	 * @return array Each with source_id, title, sentence, anchor and edit_url.
	 */
	public static function for_post( $post_id, $limit = self::SHOWN ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return array();
		}

		$phrase = self::phrase_for( $post_id );

		if ( '' === $phrase ) {
			return array();
		}

		$url   = (string) get_permalink( $post_id );
		$found = array();

		foreach ( self::candidates( $phrase, $post_id ) as $candidate ) {
			$anchor = self::anchor_in( $candidate['content'], $phrase );

			if ( '' === $anchor ) {
				continue;
			}

			$sentence = self::sentence_at( $candidate['content'], $phrase );

			if ( '' === $sentence ) {
				continue;
			}

			$found[] = array(
				'source_id' => (int) $candidate['id'],
				'title'     => '' === $candidate['title'] ? __( 'A page with no title', 'solseo' ) : $candidate['title'],
				'sentence'  => $sentence,
				'anchor'    => $anchor,
				'target_id' => $post_id,
				'target'    => $url,
			);

			if ( count( $found ) >= (int) $limit ) {
				break;
			}
		}

		return $found;
	}

	/**
	 * Write one suggested link into the page that would carry it.
	 *
	 * The one thing in this batch that changes anything. Hiding the button is a
	 * courtesy; this is the control, so it asks WordPress whether this person
	 * may edit that page before it reads a single byte of it, and it refuses
	 * rather than guessing when the sentence has moved since the suggestion was
	 * drawn.
	 *
	 * The undo is WordPress's own. Changing post content stores a revision, so
	 * the page as it was is one click away in the editor, which is a better undo
	 * than anything this could offer and it is already there.
	 *
	 * @param int $source_id The page that would carry the link.
	 * @param int $target_id The page being linked to.
	 * @return array|\WP_Error
	 */
	public static function accept( $source_id, $target_id ) {
		$source_id = (int) $source_id;
		$target_id = (int) $target_id;

		$source = get_post( $source_id );
		$target = get_post( $target_id );

		if ( ! $source || ! $target ) {
			return new \WP_Error( 'solseo_suggestion_gone', __( 'One of those pages is not there any more.', 'solseo' ) );
		}

		if ( ! current_user_can( 'edit_post', $source_id ) ) {
			return new \WP_Error( 'solseo_suggestion_denied', __( 'You cannot edit that page.', 'solseo' ) );
		}

		if ( 'publish' !== $target->post_status ) {
			return new \WP_Error( 'solseo_suggestion_draft', __( 'That page is not published, so nothing should link to it yet.', 'solseo' ) );
		}

		$phrase = self::phrase_for( $target_id );
		$url    = (string) get_permalink( $target_id );

		if ( '' === $phrase || '' === $url ) {
			return new \WP_Error( 'solseo_suggestion_nothing', __( 'That page has no phrase to link on.', 'solseo' ) );
		}

		$after = self::insert( $source->post_content, $phrase, $url );

		if ( $after === $source->post_content ) {
			return new \WP_Error(
				'solseo_suggestion_moved',
				__( 'That sentence has changed since this was worked out, so nothing was written. Open the page and add the link yourself.', 'solseo' )
			);
		}

		wp_update_post(
			array(
				'ID'           => $source_id,
				'post_content' => $after,
			)
		);

		Change_Log::record(
			array(
				'what'  => 'link_added',
				'label' => sprintf(
					/* translators: 1: the page the link was written into. 2: the page it points at. */
					__( 'Added a link from %1$s to %2$s.', 'solseo' ),
					$source->post_title,
					$target->post_title
				),
				'merge' => 'link_added:' . $target_id,
			)
		);

		return array(
			'source_id' => $source_id,
			'target_id' => $target_id,
			'anchor'    => self::anchor_in( $source->post_content, $phrase ),
		);
	}

	/**
	 * Put a link around the first safe occurrence of a phrase.
	 *
	 * Pure, and it gives back exactly what it was given when there is nowhere
	 * safe to put one. The casing that was written stays: linking "Recurve Bow"
	 * must not quietly turn it into "recurve bow" halfway through somebody's
	 * sentence.
	 *
	 * @param string $html   The page content.
	 * @param string $phrase The phrase to link on.
	 * @param string $url    Where the link goes.
	 * @return string
	 */
	public static function insert( $html, $phrase, $url ) {
		$html   = (string) $html;
		$phrase = trim( (string) $phrase );

		if ( '' === $phrase || '' === trim( (string) $url ) ) {
			return $html;
		}

		$where = self::first_safe_match( $html, $phrase );

		if ( null === $where ) {
			return $html;
		}

		$words = substr( $html, $where['at'], $where['length'] );

		return substr( $html, 0, $where['at'] )
			. '<a href="' . esc_url( $url ) . '">' . $words . '</a>'
			. substr( $html, $where['at'] + $where['length'] );
	}

	/**
	 * The words a suggestion would turn into a link.
	 *
	 * Pure.
	 *
	 * @param string $html   The page content.
	 * @param string $phrase The phrase.
	 * @return string
	 */
	public static function anchor_in( $html, $phrase ) {
		$where = self::first_safe_match( (string) $html, (string) $phrase );

		return null === $where ? '' : substr( (string) $html, $where['at'], $where['length'] );
	}

	/**
	 * Where in a page a link may be put, or nothing.
	 *
	 * Pure. Every occurrence of the phrase is looked at in turn and the first
	 * one that is not already inside something is taken. The length comes back
	 * with the offset because both are counted in bytes and a case insensitive
	 * match is not always the same number of them as the phrase that found it.
	 *
	 * @param string $html   The page content.
	 * @param string $phrase The phrase.
	 * @return array|null Keys: at, length.
	 */
	public static function first_safe_match( $html, $phrase ) {
		$html   = (string) $html;
		$phrase = trim( (string) $phrase );

		if ( '' === $phrase || '' === $html ) {
			return null;
		}

		if ( ! preg_match_all( '/(?<![\w-])' . preg_quote( $phrase, '/' ) . '(?![\w-])/iu', $html, $found, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$closed = self::closed_ranges( $html );

		foreach ( $found[0] as $one ) {
			$at     = (int) $one[1];
			$length = strlen( (string) $one[0] );

			if ( self::inside( $at, $length, $closed ) ) {
				continue;
			}

			return array(
				'at'     => $at,
				'length' => $length,
			);
		}

		return null;
	}

	/**
	 * The parts of a page a link may never be put into.
	 *
	 * Pure, and the whole of the safety here. Inside another anchor, because
	 * nested links are invalid and browsers guess. Inside a heading, because a
	 * heading that is a link changes what the page looks like rather than what
	 * it says. Inside a shortcode, because that is somebody else's syntax and
	 * an anchor in the middle of it breaks the shortcode rather than adding a
	 * link. Inside a tag, because that is an attribute. And inside a script or
	 * a style, because that is code.
	 *
	 * @param string $html The page content.
	 * @return array Each an offset and a length.
	 */
	public static function closed_ranges( $html ) {
		$html    = (string) $html;
		$ranges  = array();
		$sources = array(
			'#<a\b[^>]*>.*?</a>#is',
			'#<h[1-6]\b[^>]*>.*?</h[1-6]>#is',
			'#<script\b[^>]*>.*?</script>#is',
			'#<style\b[^>]*>.*?</style>#is',
			'#<!--.*?-->#s',
			'#\[/?[^\]\[\n]+\]#',
			'#<[^>]+>#s',
		);

		foreach ( $sources as $pattern ) {
			if ( ! preg_match_all( $pattern, $html, $found, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			foreach ( $found[0] as $one ) {
				$ranges[] = array( (int) $one[1], strlen( (string) $one[0] ) );
			}
		}

		return $ranges;
	}

	/**
	 * Whether a stretch of a page falls inside any of a set of ranges.
	 *
	 * Pure.
	 *
	 * @param int   $at     Where it starts.
	 * @param int   $length How long it is.
	 * @param array $ranges Each an offset and a length.
	 * @return bool
	 */
	public static function inside( $at, $length, array $ranges ) {
		$end = (int) $at + (int) $length;

		foreach ( $ranges as $range ) {
			$from = (int) $range[0];
			$to   = $from + (int) $range[1];

			if ( $at < $to && $end > $from ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The sentence that would actually change.
	 *
	 * Pure, and it is not the same question as "which sentence mentions this".
	 * A page with the phrase in its heading and again in a paragraph gets its
	 * link in the paragraph, because a heading is not a place for one, and the
	 * first version of this showed the heading as the sentence it was going to
	 * change. The reader would have pressed a button expecting one thing and
	 * got another. Found on a real WordPress, where the first suggestion drawn
	 * read simply "Recurve bow".
	 *
	 * @param string $html   The page content.
	 * @param string $phrase The phrase.
	 * @return string
	 */
	public static function sentence_at( $html, $phrase ) {
		$where = self::first_safe_match( (string) $html, (string) $phrase );

		if ( null === $where ) {
			return '';
		}

		return self::sentence_with( self::block_around( (string) $html, $where['at'] ), $phrase );
	}

	/**
	 * The stretch of markup between the block tags either side of an offset.
	 *
	 * Pure.
	 *
	 * @param string $html The page content.
	 * @param int    $at   A byte offset into it.
	 * @return string
	 */
	public static function block_around( $html, $at ) {
		$html = (string) $html;
		$at   = (int) $at;

		if ( ! preg_match_all( self::BLOCKS, $html, $found, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$from = 0;
		$to   = strlen( $html );

		foreach ( $found[0] as $one ) {
			$start = (int) $one[1];
			$after = $start + strlen( (string) $one[0] );

			if ( $after <= $at ) {
				$from = $after;

				continue;
			}

			if ( $start >= $at ) {
				$to = $start;

				break;
			}
		}

		return substr( $html, $from, max( 0, $to - $from ) );
	}

	/**
	 * The sentence a phrase sits in, as somebody reading it would see it.
	 *
	 * Pure.
	 *
	 * @param string $html   The page content.
	 * @param string $phrase The phrase.
	 * @return string
	 */
	public static function sentence_with( $html, $phrase ) {
		$phrase = trim( (string) $phrase );
		$plain  = trim( wp_strip_all_tags( self::spaced( (string) $html ) ) );

		if ( '' === $phrase || '' === $plain ) {
			return '';
		}

		/*
		 * A sentence ends at a full stop or at the end of a block. A heading
		 * has no full stop, so splitting on punctuation alone runs the heading
		 * into the paragraph under it and then treats the pair as one sentence.
		 */
		$parts = preg_split( '/(?<=[.!?])\s+|\n+/u', $plain );

		foreach ( (array) $parts as $part ) {
			$part = trim( (string) preg_replace( '/\s+/u', ' ', (string) $part ) );

			if ( '' === $part || ! preg_match( '/(?<![\w-])' . preg_quote( $phrase, '/' ) . '(?![\w-])/iu', $part ) ) {
				continue;
			}

			return strlen( $part ) > self::SENTENCE ? substr( $part, 0, self::SENTENCE - 3 ) . '...' : $part;
		}

		return '';
	}

	/**
	 * Markup with a line break where each block tag was.
	 *
	 * Pure, and it is the difference between a readable sentence and a wall.
	 * `wp_strip_all_tags()` takes the tags out and puts nothing in their place,
	 * so a heading followed by a paragraph comes back as "Recurve bowMost
	 * beginners start", the sentence splitter then finds one sentence on the
	 * whole page, and the suggestion shows the reader the entire page as the
	 * sentence it means to change. Every unit test passed on a page written as
	 * one paragraph; the first real post said otherwise.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	protected static function spaced( $html ) {
		return (string) preg_replace( self::BLOCKS, "\n", (string) $html );
	}

	/**
	 * The published pages holding a phrase that do not already link here.
	 *
	 * @param string $phrase  The phrase.
	 * @param int    $post_id The page being linked to.
	 * @return array Each with id, title and content.
	 */
	protected static function candidates( $phrase, $post_id ) {
		global $wpdb;

		$types = solseo_post_types();

		if ( ! $types ) {
			return array();
		}

		$already   = self::already_linking( $post_id );
		$already[] = (int) $post_id;

		$in_types = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$out_ids  = implode( ',', array_map( 'intval', $already ) );
		$like     = '%' . $wpdb->esc_like( $phrase ) . '%';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- the two interpolations are a list of placeholders and a list of integers this method built itself, and the replacements arrive as one array because the number of them depends on how many post types this site manages.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				AND post_type IN ({$in_types})
				AND ID NOT IN ({$out_ids})
				AND post_content LIKE %s
				ORDER BY post_modified DESC LIMIT %d",
				array_merge( $types, array( $like, self::CANDIDATES ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		$found = array();

		foreach ( (array) $rows as $row ) {
			$found[] = array(
				'id'      => (int) $row['ID'],
				'title'   => (string) $row['post_title'],
				'content' => (string) $row['post_content'],
			);
		}

		return $found;
	}

	/**
	 * The pages already linking to one page.
	 *
	 * This is the link index doing the work it was built for, read the other way
	 * round: a page that already links here is not a suggestion, and asking the
	 * index is one query rather than reading forty pages to find out.
	 *
	 * @param int $post_id Post ID.
	 * @return array Post IDs.
	 */
	protected static function already_linking( $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT source_id FROM {$table} WHERE target_id = %d", (int) $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().

		return array_map( 'intval', (array) $ids );
	}
}
