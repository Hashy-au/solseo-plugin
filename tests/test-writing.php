<?php
/**
 * The writing profile.
 *
 * @package SolSEO
 */

use SolSEO\Analysis\Checks;
use SolSEO\Analysis\Writing_Checks;
use SolSEO\Options;

/**
 * Run the writing group over a sentence.
 *
 * @param string $text Sentence.
 * @return array Check id to result.
 */
function solseo_writing( $text ) {
	$paper = array( 'words' => \SolSEO\Analysis\Text::words( $text ) );
	$by_id = array();

	foreach ( Writing_Checks::run( $paper ) as $check ) {
		$by_id[ $check['id'] ] = $check;
	}

	return $by_id;
}

Options::update(
	array(
		'writing_variant' => 'au',
		'writing_banned'  => array(),
	)
);

/* AUSTRALIAN SPELLING PASSES, AND ITS UNITED STATES FORM DOES NOT. */
$solseo_ok = solseo_writing( 'We optimise the colour of every catalogue we organise.' );

solseo_assert_same( Checks::GOOD, $solseo_ok['spelling']['status'], 'Australian spelling passes' );

$solseo_bad = solseo_writing( 'We optimize the color of every catalog we organize.' );

solseo_assert_same( Checks::POOR, $solseo_bad['spelling']['status'], 'and its United States form does not' );

solseo_assert(
	false !== strpos( $solseo_bad['spelling']['note'], 'optimize should be optimise' ),
	'and the note says which word and what it should be'
);

solseo_assert(
	false !== strpos( $solseo_bad['spelling']['note'], 'and 1 more' ),
	'and it names three at most, then counts the rest'
);

/* THE SAME LIST, READ THE OTHER WAY. */
Options::update( array( 'writing_variant' => 'us' ) );

$solseo_us = solseo_writing( 'We optimize the color of every catalog.' );

solseo_assert_same( Checks::GOOD, $solseo_us['spelling']['status'], 'United States spelling passes when that is what the site uses' );

$solseo_us_bad = solseo_writing( 'We optimise the colour of every catalogue.' );

solseo_assert_same( Checks::POOR, $solseo_us_bad['spelling']['status'], 'and Australian spelling is what gets flagged instead' );

/* AND IT CAN BE SWITCHED OFF WITHOUT SWITCHING OFF READABILITY. */
Options::update( array( 'writing_variant' => 'off' ) );

$solseo_off = solseo_writing( 'We optimize the color of every catalog.' );

solseo_assert_same( Checks::SKIPPED, $solseo_off['spelling']['status'], 'a site that does not care about the variety is not marked down for it' );

Options::update( array( 'writing_variant' => 'au' ) );

/*
 * THE BANNED LIST SHIPS EMPTY AND COSTS NOTHING WHEN IT IS.
 *
 * Skipped rather than passed, because a check that says "good" for a list
 * nobody has written is a check taking credit for doing nothing.
 */
$solseo_none = solseo_writing( 'Anything at all.' );

solseo_assert_same( Checks::SKIPPED, $solseo_none['banned_words']['status'], 'an empty list of words to avoid is skipped, not passed' );

Options::update( array( 'writing_banned' => array( 'leverage', 'synergy' ) ) );

$solseo_clean = solseo_writing( 'We help small shops sell more.' );

solseo_assert_same( Checks::GOOD, $solseo_clean['banned_words']['status'], 'a page with none of them passes' );

$solseo_dirty = solseo_writing( 'We leverage synergy to win.' );

solseo_assert_same( Checks::POOR, $solseo_dirty['banned_words']['status'], 'and a page with them does not' );

solseo_assert(
	false !== strpos( $solseo_dirty['banned_words']['note'], 'leverage' ) && false !== strpos( $solseo_dirty['banned_words']['note'], 'synergy' ),
	'and the note names them'
);

Options::update( array( 'writing_banned' => array() ) );

/* WHAT IS TYPED INTO THE BOX BECOMES A LIST, HOWEVER IT IS TYPED. */
solseo_assert_same(
	array( 'one', 'two', 'three' ),
	\SolSEO\Admin\Writing_Tab::clean_banned( "One\ntwo , three\n\n one " ),
	'lines, commas, spaces and repeats all come out as one clean list'
);

solseo_assert(
	count( \SolSEO\Admin\Writing_Tab::clean_banned( implode( "\n", range( 1, 500 ) ) ) ) <= \SolSEO\Admin\Writing_Tab::MAX_BANNED,
	'and the list is capped, because an unbounded option is a bug waiting'
);

/*
 * THE TARGETS TUNE THE READABILITY CHECKS RATHER THAN ADDING A SECOND OPINION.
 *
 * Two groups marking the same sentence would count it twice and then disagree
 * about it, so the profile owns the numbers and readability does the marking.
 */
$solseo_writing_src = (string) file_get_contents( SOLSEO_PATH . 'includes/analysis/class-readability-checks.php' );

solseo_assert(
	false !== strpos( $solseo_writing_src, "Options::get( 'writing_sentence' )" ),
	'the sentence target is read by the readability group'
);

solseo_assert(
	false !== strpos( $solseo_writing_src, "Options::get( 'writing_reading_ease' )" ),
	'and so is the reading ease target'
);

$solseo_group_src = (string) file_get_contents( SOLSEO_PATH . 'includes/analysis/class-writing-checks.php' );

foreach ( array( 'reading_ease', 'sentence_length' ) as $solseo_theirs ) {
	solseo_assert(
		false === strpos( $solseo_group_src, "'" . $solseo_theirs . "'" ),
		'the writing group does not mark ' . $solseo_theirs . ' as well'
	);
}

/* THE DEFAULTS ARE WHAT THE CHECKS USED BEFORE THE PROFILE EXISTED. */
solseo_assert_same( 20, Options::defaults()['writing_sentence'], 'the sentence target ships at what the check used to hardcode' );
solseo_assert_same( 60, Options::defaults()['writing_reading_ease'], 'and so does the reading ease target' );

/*
 * THE CHECKLIST READS THE SHAPE THE SCORE ROUTE ACTUALLY SENDS.
 *
 * The route sends score, band, label, groups, title and description, and the
 * checks live inside the groups. There is no flat analysis.checks, and a script
 * that reads one finds undefined, reports an all clear and tells somebody their
 * page is fine when nine things are wrong with it. That shipped for an hour and
 * a real editor caught it, so it is pinned here: there is no JavaScript test
 * runner in this plugin, and this is the next best thing.
 */
$solseo_rest_src = (string) file_get_contents( SOLSEO_PATH . 'includes/class-rest.php' );
$solseo_list_src = (string) file_get_contents( SOLSEO_PATH . 'assets/js/editor-checklist.js' );

solseo_assert(
	false === strpos( $solseo_rest_src, "'checks'      => \$analysis['checks']" ),
	'the score route sends no flat list of checks'
);

solseo_assert(
	false !== strpos( $solseo_rest_src, "'groups'      => \$analysis['groups']" ),
	'it sends them inside their groups'
);

/*
 * Comments are stripped before matching, because a note explaining why we do
 * not read that shape is the opposite of reading it. D-73.3 settled this for
 * the hub's claims guard and the reasoning is the same here.
 */
$solseo_list_code = preg_replace( '#/\*.*?\*/#s', '', $solseo_list_src );
$solseo_list_code = preg_replace( '#^\s*//.*$#m', '', (string) $solseo_list_code );

solseo_assert(
	false === strpos( (string) $solseo_list_code, 'analysis.checks' ),
	'and the checklist does not go looking for the flat list that is not there'
);

solseo_assert(
	false !== strpos( $solseo_list_src, 'analysis.groups' ),
	'it reads the groups, the way the sidebar does'
);
