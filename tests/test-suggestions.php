<?php
/**
 * Where a suggested link may go, and the five places it may never go.
 *
 * This rewrites somebody's published page, so every branch of the deciding has
 * a test of its own. The reading half is a query and the box says what that
 * does; the writing half is here.
 *
 * @package SolSEO
 */

use SolSEO\Links\Suggestions;

/* THE ORDINARY CASE: A PHRASE IN A SENTENCE BECOMES A LINK. */
solseo_assert_same(
	'<p>We sell the best <a href="https://example.test/bows/">recurve bow</a> in Perth.</p>',
	Suggestions::insert( '<p>We sell the best recurve bow in Perth.</p>', 'recurve bow', 'https://example.test/bows/' ),
	'a phrase in a paragraph becomes a link'
);

/* AND THE WORDS KEEP THE CASE THEY WERE WRITTEN IN. */
solseo_assert_same(
	'<p>A <a href="https://example.test/bows/">Recurve Bow</a> is a bow.</p>',
	Suggestions::insert( '<p>A Recurve Bow is a bow.</p>', 'recurve bow', 'https://example.test/bows/' ),
	'the words are linked as they were written, not as the keyword was typed'
);

/* NEVER INSIDE ANOTHER LINK. */
solseo_assert_same(
	'<p>See our <a href="/old/">recurve bow</a> page.</p>',
	Suggestions::insert( '<p>See our <a href="/old/">recurve bow</a> page.</p>', 'recurve bow', 'https://example.test/bows/' ),
	'a phrase already inside a link is left alone'
);

/* NEVER INSIDE A HEADING. */
solseo_assert_same(
	'<h2>Recurve bow</h2>',
	Suggestions::insert( '<h2>Recurve bow</h2>', 'recurve bow', 'https://example.test/bows/' ),
	'a heading is not turned into a link'
);

/* NEVER INSIDE A SHORTCODE. */
solseo_assert_same(
	'[product name="recurve bow"]',
	Suggestions::insert( '[product name="recurve bow"]', 'recurve bow', 'https://example.test/bows/' ),
	'a phrase inside a shortcode is somebody else\'s syntax'
);

/* NEVER INSIDE A TAG. */
solseo_assert_same(
	'<img src="a.jpg" alt="recurve bow">',
	Suggestions::insert( '<img src="a.jpg" alt="recurve bow">', 'recurve bow', 'https://example.test/bows/' ),
	'a phrase in an attribute is not words on the page'
);

/* NEVER INSIDE A SCRIPT OR A COMMENT. */
solseo_assert_same(
	'<script>var a = "recurve bow";</script>',
	Suggestions::insert( '<script>var a = "recurve bow";</script>', 'recurve bow', 'https://example.test/bows/' ),
	'a phrase inside a script is code'
);

solseo_assert_same(
	'<!-- wp:paragraph recurve bow -->',
	Suggestions::insert( '<!-- wp:paragraph recurve bow -->', 'recurve bow', 'https://example.test/bows/' ),
	'and a phrase inside a block comment is not prose either'
);

/* THE SECOND OCCURRENCE IS TAKEN WHEN THE FIRST IS SPOKEN FOR. */
solseo_assert_same(
	'<h2>Recurve bow</h2><p>Every <a href="https://example.test/bows/">recurve bow</a> we sell.</p>',
	Suggestions::insert( '<h2>Recurve bow</h2><p>Every recurve bow we sell.</p>', 'recurve bow', 'https://example.test/bows/' ),
	'the heading is skipped and the paragraph underneath is used'
);

/* ONE LINK, NOT ONE PER OCCURRENCE. */
solseo_assert_same(
	1,
	substr_count(
		Suggestions::insert( '<p>A recurve bow, another recurve bow, a third recurve bow.</p>', 'recurve bow', 'https://example.test/bows/' ),
		'<a href='
	),
	'one suggestion writes one link, not one for every mention'
);

/* A PHRASE INSIDE A LONGER WORD IS NOT THE PHRASE. */
solseo_assert_same(
	'<p>The bowstring is separate.</p>',
	Suggestions::insert( '<p>The bowstring is separate.</p>', 'bow', 'https://example.test/bows/' ),
	'bow does not match bowstring'
);

/* AND NOTHING TO WORK WITH CHANGES NOTHING. */
solseo_assert_same(
	'<p>Nothing here.</p>',
	Suggestions::insert( '<p>Nothing here.</p>', 'recurve bow', 'https://example.test/bows/' ),
	'a page that does not mention the phrase is not touched'
);

solseo_assert_same(
	'<p>A recurve bow.</p>',
	Suggestions::insert( '<p>A recurve bow.</p>', '', 'https://example.test/bows/' ),
	'and neither is one with no phrase to look for'
);

solseo_assert_same(
	'<p>A recurve bow.</p>',
	Suggestions::insert( '<p>A recurve bow.</p>', 'recurve bow', '' ),
	'or with nowhere to point'
);

/* THE ANCHOR A SUGGESTION OFFERS IS THE WORDS IT WOULD LINK. */
solseo_assert_same(
	'Recurve Bow',
	Suggestions::anchor_in( '<p>A Recurve Bow is a bow.</p>', 'recurve bow' ),
	'the suggested anchor is the words already on the page'
);

solseo_assert_same(
	'',
	Suggestions::anchor_in( '<h2>Recurve bow</h2>', 'recurve bow' ),
	'and a page with nowhere safe to put one offers nothing'
);

/* THE SENTENCE A SUGGESTION NAMES IS THE SENTENCE SOMEBODY WOULD READ. */
solseo_assert_same(
	'We sell the best recurve bow in Perth.',
	Suggestions::sentence_with( '<p>Hello there. We sell the best recurve bow in Perth. Come in.</p>', 'recurve bow' ),
	'the sentence holding the phrase is the one that comes back'
);

solseo_assert_same(
	'',
	Suggestions::sentence_with( '<p>Nothing about bows at all.</p>', 'recurve bow' ),
	'and a page that does not say it has no sentence to show'
);

/*
 * AND A BLOCK TAG IS A SPACE, NOT NOTHING.
 *
 * wp_strip_all_tags() takes the tags out and puts nothing in their place, so a
 * heading followed by a paragraph came back as one run of words with no full
 * stop in it, the sentence splitter found one sentence on the whole page, and
 * the suggestion offered the reader the entire page as the sentence it meant to
 * change. Every test above passed while that was true, because every one of
 * them is a single paragraph. The first real post said otherwise.
 */
solseo_assert_same(
	'Most beginners start with a recurve bow because it is simple.',
	Suggestions::sentence_with( '<h2>Recurve bows</h2><p>Most beginners start with a recurve bow because it is simple.</p>', 'recurve bow' ),
	'a heading above a paragraph does not run into it'
);

solseo_assert_same(
	'One thing.',
	Suggestions::sentence_with( '<li>One thing.</li><li>Another.</li>', 'one thing' ),
	'and neither do two list items'
);

/*
 * AND THE SENTENCE NAMED IS THE SENTENCE THAT WOULD CHANGE.
 *
 * Not the same question as "which sentence mentions this". A page with the
 * phrase in its heading and again in a paragraph gets its link in the
 * paragraph, so naming the heading would promise one thing and do another.
 * Found on a real WordPress, where the first suggestion drawn read "Recurve
 * bow" and the sentence it was going to edit was three lines further down.
 */
solseo_assert_same(
	'Most beginners start with a recurve bow because it is simple.',
	Suggestions::sentence_at( '<h2>Recurve bow</h2><p>Most beginners start with a recurve bow because it is simple.</p>', 'recurve bow' ),
	'the heading is not named as the sentence that would change'
);

solseo_assert_same(
	'We sell the best recurve bow in Perth.',
	Suggestions::sentence_at( '<p>Hello. We sell the best recurve bow in Perth.</p>', 'recurve bow' ),
	'and an ordinary page names the sentence holding the phrase'
);

solseo_assert_same(
	'',
	Suggestions::sentence_at( '<h2>Recurve bow</h2>', 'recurve bow' ),
	'and a page with nowhere safe to put a link names no sentence at all'
);

solseo_assert_same(
	'Two',
	Suggestions::block_around( '<p>One</p><p>Two</p><p>Three</p>', 13 ),
	'the block around an offset is the writing the offset sits in'
);

$solseo_long = Suggestions::sentence_with( '<p>' . str_repeat( 'word ', 80 ) . 'recurve bow here.</p>', 'recurve bow' );

solseo_assert( strlen( $solseo_long ) <= 200, 'a very long sentence is cut to something readable' );
solseo_assert( false !== strpos( $solseo_long, '...' ), 'and says it was cut' );

/* THE RANGES A LINK MAY NOT GO INTO ARE REAL RANGES. */
solseo_assert(
	Suggestions::inside( 5, 3, array( array( 0, 10 ) ) ),
	'something inside a range is inside it'
);

solseo_assert(
	Suggestions::inside( 8, 5, array( array( 0, 10 ) ) ),
	'and something overlapping the end of one is too'
);

solseo_assert(
	! Suggestions::inside( 12, 3, array( array( 0, 10 ) ) ),
	'and something past the end is not'
);

solseo_assert_same( array(), Suggestions::closed_ranges( 'Just words.' ), 'plain words close nothing off' );
solseo_assert( count( Suggestions::closed_ranges( '<a href="/a">x</a>' ) ) > 0, 'and a link closes something off' );
