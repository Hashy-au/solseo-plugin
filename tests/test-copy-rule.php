<?php
/**
 * Never ask somebody to do X to N things.
 *
 * "Describe the next hundred" is not a feature. It is a button somebody has to
 * press eleven times while keeping count, wearing the clothes of a feature. A
 * tool that works through a list either queues the whole list and shows how far
 * it has got, or it shows the list and lets the reader tick what they want.
 *
 * The rule is a test because in this codebase a rule that is not a test is a
 * rule that comes back in six weeks, in a different file, written by somebody
 * who was being helpful.
 *
 * @package SolSEO
 */

$solseo_copy_files = array_merge(
	glob( SOLSEO_PATH . '*.php' ),
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' ),
	glob( SOLSEO_PATH . 'assets/js/*.js' )
);

$solseo_calls = '(?:__|_e|_x|_n|_nx|esc_html__|esc_html_e|esc_attr__|esc_attr_e|esc_html_x)';

/*
 * Every string a person reads, taken from the first argument of a translation
 * call. Strings in the JavaScript come through here too, because they are
 * handed over from PHP.
 */
$solseo_strings = array();

foreach ( $solseo_copy_files as $solseo_file ) {
	$solseo_text = (string) file_get_contents( $solseo_file );

	if ( preg_match_all( '~\b' . $solseo_calls . '\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'~', $solseo_text, $solseo_found ) ) {
		foreach ( $solseo_found[1] as $solseo_string ) {
			$solseo_strings[] = array(
				'where'  => basename( $solseo_file ),
				'string' => $solseo_string,
			);
		}
	}
}

solseo_assert( count( $solseo_strings ) > 400, 'the copy rule is reading the whole plugin' );

/*
 * And the extractor above cannot be dodged by reaching for the other quote.
 */
$solseo_double = array();

foreach ( $solseo_copy_files as $solseo_file ) {
	if ( preg_match( '~\b' . $solseo_calls . '\(\s*"~', (string) file_get_contents( $solseo_file ) ) ) {
		$solseo_double[] = basename( $solseo_file );
	}
}

solseo_assert_same( array(), $solseo_double, 'every translated string is written with single quotes, so the guard sees all of them' );

$solseo_count = '(?:\d{1,6}|ten|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand)';
$solseo_unit  = '(?:by|x|px|pixels?|words?|characters?|chars?|seconds?|minutes?|hours?|days?|rows?|per|of|to|and|or)';

$solseo_batch = '~^[A-Z][a-z]+(?:\s+[a-z]+){0,3}\s+' . $solseo_count . '\b(?!\s*(?:' . $solseo_unit . '\b|\d))~';
$solseo_next  = '~\b(?:next|first)\s+' . $solseo_count . '\b~i';

/**
 * Whether a string is shaped like a button rather than a sentence.
 *
 * Three things that are true of a label and false of a sentence. The batch
 * pattern is only run against labels, because "the first 20 rows of 340" in a
 * sentence under a table is reporting, not asking somebody to press a button
 * twenty times.
 *
 * @param string $text A translated string.
 * @return bool
 */
function solseo_is_label( $text ) {
	return false === strpos( $text, '%' )
		&& strlen( $text ) <= 40
		&& '.' !== substr( rtrim( $text ), -1 );
}

/**
 * Whether a string breaks the rule.
 *
 * @param string $text  A translated string.
 * @param string $batch The batch pattern.
 * @param string $next  The next-N pattern.
 * @return bool
 */
function solseo_breaks_copy_rule( $text, $batch, $next ) {
	if ( preg_match( $next, $text ) ) {
		return true;
	}

	return solseo_is_label( $text ) && preg_match( $batch, $text );
}

/*
 * THE PATTERNS ARE TESTED BEFORE THE PLUGIN IS. A guard nobody has watched
 * fire is a guard that passes because it never matches anything.
 */

$solseo_should_fire = array(
	'Describe the next hundred',
	'Import the next hundred',
	'Score the next fifty',
	'Apply the next 50',
	'Describe 100 images',
	'Score 50 more pages',
	'Work through the next fifty pages.',
	'Suggest the next 25',
	'Fill the first 100',
	'Copy the next thousand',
);

foreach ( $solseo_should_fire as $solseo_bad ) {
	solseo_assert(
		solseo_breaks_copy_rule( $solseo_bad, $solseo_batch, $solseo_next ),
		'the copy rule catches "' . $solseo_bad . '"'
	);
}

$solseo_should_not_fire = array(
	'Aim for 1200 by 630 pixels.',
	'%d images have no alt text.',
	'Density needs at least 100 words to mean anything.',
	'Page %1$d of %2$d',
	'301 moved for good',
	'Trim to 60 characters',
	'Wait 30 seconds',
	'Showing 20 of 340',
	'Keep page two and beyond out of the index',
	'Describe every image',
	'Copy them all across',
	'Score every page that has none',
	'Around 155 characters is what a search result shows.',
	'The code lasts fifteen minutes and works once.',
	'Check every page',
	'The first fifteen rows',
);

foreach ( $solseo_should_not_fire as $solseo_fine ) {
	solseo_assert(
		! solseo_breaks_copy_rule( $solseo_fine, $solseo_batch, $solseo_next ),
		'the copy rule leaves "' . $solseo_fine . '" alone'
	);
}

/*
 * THE ALLOWLIST. An entry here is a string that reads like a batch size and is
 * not one. Add it exactly, with the reason on the line above, and a line in
 * docs/DECISIONS.md if it is a pattern rather than a one off.
 */
$solseo_allowed = array();

$solseo_broken = array();

foreach ( $solseo_strings as $solseo_entry ) {
	if ( in_array( $solseo_entry['string'], $solseo_allowed, true ) ) {
		continue;
	}

	if ( solseo_breaks_copy_rule( $solseo_entry['string'], $solseo_batch, $solseo_next ) ) {
		$solseo_broken[] = $solseo_entry['where'] . ': ' . $solseo_entry['string'];
	}
}

solseo_assert_same( array(), $solseo_broken, 'nothing asks somebody to do a fixed number of things at a time' );

/*
 * And an allowlist entry that is no longer in the plugin fails the build,
 * rather than quietly widening the net for whatever comes next.
 */
$solseo_live = wp_list_pluck( $solseo_strings, 'string' );

solseo_assert_same(
	array(),
	array_values( array_diff( $solseo_allowed, $solseo_live ) ),
	'every allowed string is still somewhere in the plugin'
);
