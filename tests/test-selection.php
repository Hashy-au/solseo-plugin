<?php
/**
 * Choosing the three hundred, and saying out loud why those three hundred.
 *
 * A truncated crawl of the whole site and a complete crawl of part of it look
 * the same in a progress bar and are not the same thing at all. The second is
 * what this plugin does, and the screen has to say which pages and on what
 * grounds, or the report reads as though it covered everything.
 *
 * @package SolSEO
 */

use SolSEO\Crawl\Selection;

/**
 * A list of addresses, to save writing them out.
 *
 * @param string $prefix Path prefix.
 * @param int    $from   First number.
 * @param int    $to     Last number.
 * @return array
 */
function solseo_test_urls( $prefix, $from, $to ) {
	$urls = array();

	for ( $n = $from; $n <= $to; $n++ ) {
		$urls[] = 'https://example.test/' . $prefix . '-' . $n . '/';
	}

	return $urls;
}

/*
 * THE ORDER IS THE FOUR RULES, AND THE FRONT PAGE IS ALWAYS FIRST.
 */
$solseo_lists = array(
	'front'   => array( 'https://example.test/' ),
	'sitemap' => solseo_test_urls( 'map', 1, 4 ),
	'linked'  => solseo_test_urls( 'hub', 1, 3 ),
	'recent'  => solseo_test_urls( 'new', 1, 5 ),
);

$solseo_chosen = Selection::merge( $solseo_lists, 100 );

solseo_assert_same( 13, count( $solseo_chosen ), 'a site smaller than the budget is crawled whole' );
solseo_assert_same( 'https://example.test/', $solseo_chosen[0]['url'], 'the front page goes first' );
solseo_assert_same( 'front', $solseo_chosen[0]['chosen_by'], 'and says so' );

solseo_assert_same(
	array( 'front', 'sitemap', 'sitemap', 'sitemap', 'sitemap', 'linked', 'linked', 'linked', 'recent', 'recent', 'recent', 'recent', 'recent' ),
	wp_list_pluck( $solseo_chosen, 'chosen_by' ),
	'and the rules run in the order the design set'
);

/*
 * AND A PAGE IS CHOSEN ONCE, BY THE FIRST RULE THAT WANTED IT.
 *
 * The four lists overlap heavily on a real site: the front page is in the
 * sitemap, the most linked page is usually in the sitemap too. Counting a page
 * twice would spend the budget on half as many pages while the screen said
 * three hundred.
 */
$solseo_overlap = Selection::merge(
	array(
		'front'   => array( 'https://example.test/' ),
		'sitemap' => array( 'https://example.test/', 'https://example.test/a/', 'https://example.test/b/' ),
		'linked'  => array( 'https://example.test/a/', 'https://example.test/c/' ),
		'recent'  => array( 'https://example.test/', 'https://example.test/c/' ),
	),
	100
);

solseo_assert_same(
	array( 'https://example.test/', 'https://example.test/a/', 'https://example.test/b/', 'https://example.test/c/' ),
	wp_list_pluck( $solseo_overlap, 'url' ),
	'a page named by three rules is crawled once'
);

solseo_assert_same(
	array( 'front', 'sitemap', 'sitemap', 'linked' ),
	wp_list_pluck( $solseo_overlap, 'chosen_by' ),
	'and the first rule that wanted it is the one that gets the credit'
);

/*
 * AND THE BUDGET IS A BUDGET.
 */
$solseo_big = Selection::merge(
	array(
		'front'   => array( 'https://example.test/' ),
		'sitemap' => solseo_test_urls( 'map', 1, 500 ),
		'linked'  => solseo_test_urls( 'hub', 1, 500 ),
		'recent'  => solseo_test_urls( 'new', 1, 500 ),
	),
	300
);

solseo_assert_same( 300, count( $solseo_big ), 'a site larger than the budget gets exactly the budget' );

solseo_assert_same(
	array( 'front', 'sitemap' ),
	array_values( array_unique( wp_list_pluck( $solseo_big, 'chosen_by' ) ) ),
	'and the budget is spent in order, so the last rules get nothing on a big site'
);

/*
 * THE SCREEN NAMES THE RULE THAT CHOSE THEM, AND NEVER IMPLIES IT SAW EVERYTHING.
 *
 * "300 of 1,240 pages" is the whole point. A report that says "300 pages" on a
 * site with twelve hundred reads as a site with three hundred pages.
 */
$solseo_says = Selection::sentence( 300, 1240 );

solseo_assert( false !== strpos( $solseo_says, '300' ), 'the sentence says how many were crawled' );
solseo_assert( false !== strpos( $solseo_says, '1,240' ), 'and how many there are' );

solseo_assert(
	0 === preg_match( '/\b(all|every|whole|entire)\b/i', $solseo_says ),
	'and never claims the whole site'
);

$solseo_whole = Selection::sentence( 40, 40 );

solseo_assert(
	false === strpos( $solseo_whole, ' of ' ),
	'a site that fits inside the budget is not told it was sampled'
);

/*
 * AND EVERY RULE CAN SAY WHAT IT IS, BECAUSE THE BREAKDOWN PRINTS THEM.
 */
$solseo_counts = Selection::breakdown( $solseo_chosen );

solseo_assert_same(
	array(
		'front'   => 1,
		'sitemap' => 4,
		'linked'  => 3,
		'recent'  => 5,
	),
	$solseo_counts,
	'the breakdown counts each rule'
);

foreach ( array_keys( Selection::RULES ) as $solseo_rule ) {
	solseo_assert(
		'' !== trim( Selection::rule_label( $solseo_rule ) ),
		'the ' . $solseo_rule . ' rule has words a reader understands'
	);
}

solseo_assert_same(
	array( 'front', 'sitemap', 'linked', 'recent' ),
	array_keys( Selection::RULES ),
	'and the rules are declared in the order they run'
);
