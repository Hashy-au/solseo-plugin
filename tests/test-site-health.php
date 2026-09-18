<?php
/**
 * Site Health: the mapping, the badge and the Info tab rows.
 *
 * Only the pure half is exercised here. The seven tests themselves read
 * options and count rows, which is what the Playground run is for.
 *
 * @package SolSEO
 */

use SolSEO\Frontend\Head;
use SolSEO\Health\Site_Health;
use SolSEO\Tools\Alt_Text;

/*
 * OUR THREE WORDS INTO THE THREE WordPress DRAWS. The one that matters is
 * poor: a page telling search engines to stay away is critical and everything
 * else is a recommendation, because a Site Health screen where every plugin
 * shouts is a screen nobody reads.
 */

solseo_assert_same( 'good', Site_Health::status_for( 'good' ), 'good stays good' );
solseo_assert_same( 'recommended', Site_Health::status_for( 'fair' ), 'fair is a recommendation' );
solseo_assert_same( 'recommended', Site_Health::status_for( 'poor' ), 'a poor answer is a recommendation by default' );
solseo_assert_same( 'critical', Site_Health::status_for( 'poor', 'critical' ), 'and critical when the test says so' );
solseo_assert_same( 'recommended', Site_Health::status_for( 'nonsense' ), 'an answer nobody defined is a recommendation' );

/* A good answer says the good thing, not the fault with a tick beside it. */

$solseo_good = Site_Health::result(
	array(
		'test'       => 'sitemap',
		'label'      => 'The XML sitemap is switched off',
		'label_good' => 'The XML sitemap is on',
		'status'     => 'good',
		'note'       => 'It is served.',
	)
);

solseo_assert_same( 'solseo_sitemap', $solseo_good['test'], 'the test name carries our prefix' );
solseo_assert_same( 'The XML sitemap is on', $solseo_good['label'], 'a passing test reads as the good news' );
solseo_assert_same( 'good', $solseo_good['status'], 'and it passes' );
solseo_assert_same( '<p>It is served.</p>', $solseo_good['description'], 'the note is wrapped as a paragraph' );
solseo_assert_same( '', $solseo_good['actions'], 'a test with nothing to do carries no link' );

$solseo_bad = Site_Health::result(
	array(
		'test'       => 'sitemap',
		'label'      => 'The XML sitemap is switched off',
		'label_good' => 'The XML sitemap is on',
		'status'     => 'fair',
		'note'       => 'Turn it on.',
		'actions'    => '<p><a href="https://example.test/">Open it</a></p>',
	)
);

solseo_assert_same( 'The XML sitemap is switched off', $solseo_bad['label'], 'a failing test names the fault' );
solseo_assert_same( '<p><a href="https://example.test/">Open it</a></p>', $solseo_bad['actions'], 'and keeps the link it was given' );

/* The badge is one badge, on every test, so they group together on the screen. */

solseo_assert_same(
	array(
		'label' => 'SEO',
		'color' => 'blue',
	),
	Site_Health::badge(),
	'every test carries the same badge'
);
solseo_assert_same( Site_Health::badge(), $solseo_good['badge'], 'including the ones that pass' );

/*
 * SEVEN TESTS, REGISTERED WITHOUT TAKING ANYBODY ELSE'S OUT. The filter is
 * handed the whole list, so an implementation that returned only its own would
 * remove every core test on the screen and nothing would say so.
 */

$solseo_registered = Site_Health::register_tests(
	array(
		'direct' => array( 'php_version' => array( 'label' => 'PHP' ) ),
		'async'  => array(),
	)
);

solseo_assert(
	isset( $solseo_registered['direct']['php_version'] ),
	'registering ours leaves the tests already there alone'
);

solseo_assert_same( 7, count( Site_Health::tests() ), 'there are seven tests' );
solseo_assert_same( 8, count( $solseo_registered['direct'] ), 'and all seven join the direct list' );

foreach ( Site_Health::tests() as $solseo_name => $solseo_method ) {
	solseo_assert(
		isset( $solseo_registered['direct'][ 'solseo_' . $solseo_name ] ),
		'the ' . $solseo_name . ' test is registered under our prefix'
	);

	solseo_assert(
		is_callable( array( 'SolSEO\\Health\\Site_Health', $solseo_method ) ),
		'and ' . $solseo_method . ' exists to answer it'
	);
}

solseo_assert(
	is_array( Site_Health::register_tests( 'not an array' ) ),
	'a filter handed something odd still returns a list'
);

/*
 * THE INFO TAB SAYS YES AND NO. Somebody pasting this into a support thread
 * needs a word, not a 1, and nothing in the section is marked private because
 * nothing in it is.
 */

$solseo_fields = Site_Health::debug_fields(
	array(
		'version'     => '1.3.0',
		'post_types'  => 'post, page',
		'sitemap'     => true,
		'schema'      => false,
		'scored'      => '12 of 30 published pages',
		'average'     => '71',
		'no_alt_text' => '4',
		'connected'   => false,
	)
);

solseo_assert_same( 'Yes', $solseo_fields['sitemap']['value'], 'a switch that is on reads as Yes' );
solseo_assert_same( 'No', $solseo_fields['schema']['value'], 'and one that is off reads as No' );
solseo_assert_same( 'No', $solseo_fields['connected']['value'], 'the connection is a word and never an address' );
solseo_assert_same( '1.3.0', $solseo_fields['version']['value'], 'the version is passed through' );
solseo_assert_same( 8, count( $solseo_fields ), 'eight rows' );

$solseo_private = array();

foreach ( $solseo_fields as $solseo_key => $solseo_field ) {
	if ( ! empty( $solseo_field['private'] ) ) {
		$solseo_private[] = $solseo_key;
	}
}

solseo_assert_same( array(), $solseo_private, 'nothing in the section is withheld, because nothing in it is private' );

solseo_assert_same(
	array(),
	Site_Health::debug_fields( array() ),
	'a fact that was not gathered prints no row at all'
);

/*
 * WHY A PAGE IS HIDDEN, not whether. The three answers are three different
 * screens, and a fix button that opens the wrong one is worse than no button.
 */

solseo_assert_same( 'post_meta', Head::noindex_source( true, true, true ), 'the page\'s own setting wins' );
solseo_assert_same( 'post_type', Head::noindex_source( false, true, true ), 'then the setting for its type' );
solseo_assert_same( 'site', Head::noindex_source( false, false, true ), 'then the one in WordPress itself' );
solseo_assert_same( '', Head::noindex_source( false, false, false ), 'and nothing at all when it is not hidden' );

/*
 * An image address without the size WordPress appended, because the media
 * library has never heard of the sized copy.
 */

solseo_assert_same( '/a/b.jpg', Alt_Text::strip_size_suffix( '/a/b-300x200.jpg' ), 'a size suffix comes off' );
solseo_assert_same( '/a/b.jpg', Alt_Text::strip_size_suffix( '/a/b-scaled.jpg' ), 'and so does the scaled one' );
solseo_assert_same( '/a/b.jpg', Alt_Text::strip_size_suffix( '/a/b.jpg' ), 'an address with neither is left alone' );
solseo_assert_same(
	'/a/2026-09-18.jpg',
	Alt_Text::strip_size_suffix( '/a/2026-09-18.jpg' ),
	'and a date in a file name is not a size'
);

solseo_assert_same(
	array( 2, 3 ),
	Alt_Text::without_alt(
		array( 1, 2, 3 ),
		array(
			1 => 'A bow',
			2 => '',
			3 => '   ',
		)
	),
	'an alt attribute of spaces is no alt text'
);
