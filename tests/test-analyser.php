<?php
/**
 * Scoring tests.
 *
 * @package SolSEO
 */

use SolSEO\Analysis\Analyser;
use SolSEO\Analysis\Paper;

/**
 * Build a paper from editor values.
 *
 * @param array $values Overrides.
 * @return array
 */
function solseo_test_paper( array $values = array() ) {
	return Paper::from_editor(
		array_merge(
			array(
				'post_id'     => 0,
				'post_type'   => 'post',
				'title'       => '',
				'description' => '',
				'keyword'     => '',
				'slug'        => '',
				'content'     => '',
			),
			$values
		)
	);
}

/**
 * Find one check in an analysis.
 *
 * @param array  $analysis Analysis.
 * @param string $id       Check name.
 * @return array
 */
function solseo_test_check( array $analysis, $id ) {
	foreach ( $analysis['checks'] as $check ) {
		if ( $check['id'] === $id ) {
			return $check;
		}
	}

	return array( 'status' => 'missing' );
}

$body = '<h2>Choosing a recurve bow</h2><p>' . str_repeat( 'A recurve bow suits a new archer because the limbs store energy smoothly. ', 6 ) . '</p>'
	. '<p>' . str_repeat( 'However, draw weight matters more than price when you start out. ', 6 ) . '</p>'
	. '<p><a href="https://example.test/arrows/">Our arrows</a> and <a href="https://archery.org.au/">the association</a> cover the rest. '
	. 'A recurve bow rewards practice. <img src="/bow.jpg" alt="A recurve bow on a stand"></p>';

$good = Analyser::run(
	solseo_test_paper(
		array(
			'title'       => 'Recurve bow guide for new archers in Australia',
			'description' => 'A recurve bow guide that covers draw weight, limbs and the first arrows you should buy when you take up target archery.',
			'keyword'     => 'recurve bow',
			'slug'        => 'recurve-bow-guide',
			'content'     => $body,
		)
	)
);

solseo_assert_same( 'good', solseo_test_check( $good, 'keyword_in_title' )['status'], 'the keyword is found in the title' );
solseo_assert_same( 'good', solseo_test_check( $good, 'keyword_in_slug' )['status'], 'the keyword is found in the slug' );
solseo_assert_same( 'good', solseo_test_check( $good, 'keyword_in_subheading' )['status'], 'the keyword is found in a subheading' );
solseo_assert_same( 'good', solseo_test_check( $good, 'keyword_in_alt' )['status'], 'the keyword is found in alt text' );
solseo_assert_same( 'good', solseo_test_check( $good, 'internal_links' )['status'], 'an internal link is recognised' );
solseo_assert_same( 'good', solseo_test_check( $good, 'outbound_links' )['status'], 'an outbound link is recognised' );
solseo_assert( $good['score'] >= 70, 'a well prepared post scores at least seventy, got ' . $good['score'] );

$empty = Analyser::run( solseo_test_paper() );

solseo_assert_same( 'poor', solseo_test_check( $empty, 'keyword_set' )['status'], 'a missing keyword fails its check' );
solseo_assert_same( 1, count( $empty['groups']['keyword']['checks'] ), 'the other keyword checks are not run without a keyword' );
solseo_assert( $empty['score'] < 20, 'an empty post scores badly, got ' . $empty['score'] );
solseo_assert_same( 'poor', $empty['band'], 'an empty post lands in the bottom band' );

$stuffed = Analyser::run(
	solseo_test_paper(
		array(
			'title'   => 'Recurve bow',
			'keyword' => 'recurve bow',
			'slug'    => 'recurve-bow',
			'content' => '<p>' . str_repeat( 'Recurve bow. ', 60 ) . '</p>',
		)
	)
);

solseo_assert_same( 'poor', solseo_test_check( $stuffed, 'keyword_density' )['status'], 'stuffing the keyword fails the density check' );

$skipped = solseo_test_check(
	Analyser::run(
		solseo_test_paper(
			array(
				'keyword' => 'recurve bow',
				'content' => '<p>Short.</p>',
			)
		)
	),
	'keyword_density'
);
solseo_assert_same( 'skipped', $skipped['status'], 'density is not measured on a short page' );

solseo_assert_same(
	100,
	Analyser::tally(
		array(
			array(
				'status' => 'good',
				'weight' => 2,
				'id'     => 'a',
				'group'  => 'basics',
			),
		)
	),
	'one passing check is full marks'
);
solseo_assert_same(
	50,
	Analyser::tally(
		array(
			array(
				'status' => 'fair',
				'weight' => 2,
				'id'     => 'a',
				'group'  => 'basics',
			),
		)
	),
	'a fair check is worth half'
);
solseo_assert_same(
	0,
	Analyser::tally(
		array(
			array(
				'status' => 'skipped',
				'weight' => 2,
				'id'     => 'a',
				'group'  => 'basics',
			),
		)
	),
	'a skipped check leaves nothing to score'
);

// The keyword group can be run against any phrase, not only the focus keyword.
// The premium add-on scores each of the other phrases this way.
$paper = solseo_test_paper(
	array(
		'title'       => 'Recurve bow guide for new archers in Australia',
		'description' => 'A recurve bow guide that covers draw weight, limbs and the arrows worth buying first.',
		'keyword'     => 'recurve bow',
		'slug'        => 'recurve-bow-guide',
		'content'     => $body,
	)
);

$primary = SolSEO\Analysis\Analyser::tally( SolSEO\Analysis\Keyword_Checks::run( $paper ) );

$paper['keyword'] = 'draw weight';
$secondary        = SolSEO\Analysis\Analyser::tally( SolSEO\Analysis\Keyword_Checks::run( $paper ) );

solseo_assert( $primary > $secondary, 'the focus keyword scores higher than a phrase that is only mentioned, got ' . $primary . ' and ' . $secondary );
solseo_assert( $secondary > 0, 'a phrase that appears in the content still scores something, got ' . $secondary );
