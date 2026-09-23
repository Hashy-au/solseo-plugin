<?php
/**
 * The plugin agrees with the written grading contract.
 *
 * `docs/API-CONTRACT.md`, "How a page is graded", binds the worker's audit
 * rules, the hub's analysers and this plugin's scoring. The worker carries the
 * same guard in test/contract.test.ts and the hub in bin/selftest.php. This is
 * the plugin's.
 *
 * WHY: for weeks the meta description band was 1 to 160 in the worker and 120
 * to 155 in the hub, and nothing failed. A page of 158 characters passed one
 * lane and failed the other, and neither file said which was right. Two numbers
 * in two places with no test between them is a coincidence, not a contract.
 *
 * @package SolSEO
 */

use SolSEO\Contract;

/*
 * The contract document, found relative to this plugin inside the monorepo. It
 * is not shipped in the release zip, so a missing file is skipped rather than
 * failed: this guard runs where the repository is, which is where somebody can
 * act on it.
 */
$solseo_contract_path = __DIR__ . '/../../../../docs/API-CONTRACT.md';

if ( ! is_readable( $solseo_contract_path ) ) {
	solseo_assert( true, 'the contract document is not in this checkout, so the numbers are not compared here' );
} else {
	$solseo_contract = (string) file_get_contents( $solseo_contract_path );
	$solseo_contract = str_replace( "\r\n", "\n", $solseo_contract );

	$solseo_start   = strpos( $solseo_contract, '## How a page is graded' );
	$solseo_section = false === $solseo_start ? '' : substr( $solseo_contract, $solseo_start );

	solseo_assert(
		'' !== $solseo_section,
		'the contract has a "How a page is graded" section'
	);

	/* R6. Every threshold row is built from the constant, so a drift cannot pass. */
	$solseo_rows = array(
		'| Title length, including the brand suffix | ' . Contract::TITLE_MIN . ' to ' . Contract::TITLE_MAX . ' characters | absent | `META_TITLE_MISSING` |',
		'| Title length | ' . Contract::TITLE_MIN . ' to ' . Contract::TITLE_MAX . ' | over ' . Contract::TITLE_MAX . ' | `META_TITLE_TOO_LONG` |',
		'| Meta description length | ' . Contract::DESCRIPTION_MIN . ' to ' . Contract::DESCRIPTION_MAX . ' characters | absent | `META_DESCRIPTION_MISSING` |',
		'| Meta description length | ' . Contract::DESCRIPTION_MIN . ' to ' . Contract::DESCRIPTION_MAX . ' | over ' . Contract::DESCRIPTION_MAX . ' | `META_DESCRIPTION_TOO_LONG` |',
		'| Main content words | ' . Contract::THIN_CONTENT_WORDS . ' or more | under ' . Contract::THIN_CONTENT_WORDS . ' | `THIN_CONTENT` |',
		'| Response time | ' . Contract::SLOW_RESPONSE_MS . ' ms or less | over ' . Contract::SLOW_RESPONSE_MS . ' ms | `SLOW_RESPONSE` |',
	);

	foreach ( $solseo_rows as $solseo_row ) {
		solseo_assert(
			false !== strpos( $solseo_section, $solseo_row ),
			'the contract carries this row: ' . $solseo_row
		);
	}
}

/* ---------------------------------------------------------------------------
 * R2. An empty alt is an answer, not a silence.
 * ------------------------------------------------------------------------- */

solseo_assert(
	Contract::alt_is_missing( array( 'src' => 'cat.jpg' ) ),
	'an image with no alt attribute at all is missing its alt text'
);

solseo_assert(
	! Contract::alt_is_missing( array( 'src' => 'rule.png', 'alt' => '' ) ),
	'an image that declares an empty alt is not missing one'
);

solseo_assert(
	! Contract::alt_is_missing( array( 'src' => 'dog.jpg', 'alt' => 'A dog' ) ),
	'and neither is one that describes itself'
);

/*
 * The three markers, each on its own, because the header logo that made the
 * hub's audit fire on 37 of 43 pages carried the first of them.
 */
solseo_assert(
	! Contract::alt_is_unexplained_empty(
		array( 'src' => 'logo.svg', 'alt' => '', 'aria_hidden' => true )
	),
	'aria-hidden says the empty alt was deliberate'
);

solseo_assert(
	! Contract::alt_is_unexplained_empty(
		array( 'src' => 'swirl.png', 'alt' => '', 'presentational' => true )
	),
	'role="presentation" says the same thing'
);

solseo_assert(
	! Contract::alt_is_unexplained_empty(
		array( 'src' => 'logo.svg', 'alt' => '', 'linked_with_text' => true )
	),
	'and so does a link whose own text already names where it goes'
);

solseo_assert(
	Contract::alt_is_unexplained_empty( array( 'src' => 'product.jpg', 'alt' => '' ) ),
	'an empty alt with nothing to explain it is worth a quiet note'
);

solseo_assert(
	! Contract::alt_is_unexplained_empty( array( 'src' => 'cat.jpg' ) ),
	'a missing alt is reported as missing, not twice'
);

/* alt reads as a string whether it was absent or empty, so no caller sees null. */
solseo_assert_same( '', Contract::alt_text( array( 'src' => 'cat.jpg' ) ), 'an absent alt reads as an empty string' );
solseo_assert_same( 'A dog', Contract::alt_text( array( 'src' => 'd.jpg', 'alt' => 'A dog' ) ), 'and a written one reads as itself' );

/* ---------------------------------------------------------------------------
 * The extractor keeps the two apart, which it did not before.
 * ------------------------------------------------------------------------- */

$solseo_images = SolSEO\Content::images(
	'<img src="cat.jpg">'
	. '<img src="rule.png" alt="">'
	. '<img src="logo.svg" alt="" aria-hidden="true">'
	. '<img src="swirl.png" alt="" role="presentation">'
	. '<img src="dog.jpg" alt="A dog">'
);

solseo_assert_same( 5, count( $solseo_images ), 'all five images are read' );
solseo_assert_same( null, $solseo_images[0]['alt'], 'no alt attribute reads as null' );
solseo_assert_same( '', $solseo_images[1]['alt'], 'an empty alt reads as an empty string' );
solseo_assert_same( true, $solseo_images[2]['aria_hidden'], 'aria-hidden is carried through' );
solseo_assert_same( true, $solseo_images[3]['presentational'], 'and so is role="presentation"' );
solseo_assert_same( 'A dog', $solseo_images[4]['alt'], 'and the written one survives' );

/*
 * The whole point, in one line: of five images, exactly one is a fault.
 * Before this round, four of the five were reported.
 */
$solseo_faults = 0;
foreach ( $solseo_images as $solseo_image ) {
	if ( Contract::alt_is_missing( $solseo_image ) ) {
		++$solseo_faults;
	}
}

solseo_assert_same( 1, $solseo_faults, 'one image in five has nothing saying what it is' );
