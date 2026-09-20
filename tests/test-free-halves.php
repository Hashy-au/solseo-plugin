<?php
/**
 * The four rules FreeB4 has to keep, made mechanical.
 *
 * Six features in this batch are the free half of something paid. Each of them
 * is a demonstration, which is a job it can only do while it is honest, so the
 * guards here are about honesty rather than about correctness: nothing touches
 * another plugin's data, the legal disclaimer is the sentence it was written as,
 * nothing claims the site is compliant, and nothing on any of these screens is
 * a locked door with a price behind it.
 *
 * @package SolSEO
 */

$solseo_half_files = array(
	'includes/a11y/class-editor-checks.php',
	'includes/analysis/class-duplicates.php',
	'includes/analytics/class-tag-check.php',
	'includes/compliance/class-au-check.php',
	'includes/links/class-suggestions.php',
	'includes/woo/class-feed-audit.php',
	'includes/admin/class-accessibility-tab.php',
	'includes/admin/class-australia-tab.php',
	'includes/admin/class-findings-tab.php',
	'includes/admin/class-phrases-tab.php',
	'includes/admin/class-suggestions-tab.php',
	'includes/admin/views/content-links.php',
	'includes/admin/views/content-phrases.php',
	'includes/admin/views/health-accessibility.php',
	'includes/admin/views/health-australia.php',
	'includes/admin/views/health-findings.php',
);

$solseo_half_missing = array();

foreach ( $solseo_half_files as $solseo_relative ) {
	if ( ! is_readable( SOLSEO_PATH . $solseo_relative ) ) {
		$solseo_half_missing[] = $solseo_relative;
	}
}

solseo_assert_same( array(), $solseo_half_missing, 'every file in this batch is where it says it is' );

/*
 * NOTHING HERE WRITES TO ANYBODY ELSE'S DATA.
 *
 * The feed audit reads a feed plugin's products and says what Merchant Centre
 * will refuse. It does not edit the feed, the plugin's settings or the product,
 * and neither does the tag check, the Australian check or the accessibility
 * reading. A report that quietly repairs things is a report nobody can run
 * twice, and the first time one of them gets a product wrong the shop finds out
 * from Google.
 *
 * The one thing in this batch that does write is the link suggestion, which
 * writes one anchor into one of this site's own posts when somebody presses a
 * button. It is guarded separately, further down.
 */
$solseo_read_only = array(
	'includes/a11y/class-editor-checks.php',
	'includes/analysis/class-duplicates.php',
	'includes/compliance/class-au-check.php',
	'includes/woo/class-feed-audit.php',
);

$solseo_writing_calls = array(
	'update_option',
	'add_option',
	'delete_option',
	'update_post_meta',
	'add_post_meta',
	'delete_post_meta',
	'update_term_meta',
	'update_user_meta',
	'wp_update_post',
	'wp_insert_post',
	'wp_delete_post',
	'wp_update_term',
	'wp_set_object_terms',
	'wp_insert_term',
	'set_transient',
	'file_put_contents',
	'fwrite',
	'unlink',
	'rename',
);

$solseo_half_writes = array();

foreach ( $solseo_read_only as $solseo_relative ) {
	$solseo_code = solseo_code_only( (string) file_get_contents( SOLSEO_PATH . $solseo_relative ) );

	foreach ( $solseo_writing_calls as $solseo_call ) {
		if ( preg_match( '/(?<![\w$>:\\\\])' . preg_quote( $solseo_call, '/' ) . '\s*\(/', $solseo_code ) ) {
			$solseo_half_writes[] = basename( $solseo_relative ) . ' calls ' . $solseo_call;
		}
	}

	if ( preg_match( '/\$wpdb->(insert|update|delete|replace|query)\s*\(/', $solseo_code ) ) {
		$solseo_half_writes[] = basename( $solseo_relative ) . ' writes to the database directly';
	}
}

solseo_assert_same( array(), $solseo_half_writes, 'nothing that reports in this batch writes anything anywhere' );

/*
 * AND THE ONE THING THAT KEEPS A REPORT KEEPS ONLY ITS OWN.
 *
 * The tag check reads the home page, which is slow enough that doing it on
 * every visit to the Connections screen would be rude to the site's own server,
 * so it keeps what it read. D-81.7 again: one write, its own option, never
 * autoloaded.
 */
$solseo_tag_code = solseo_code_only( (string) file_get_contents( SOLSEO_PATH . 'includes/analytics/class-tag-check.php' ) );

solseo_assert_same(
	1,
	preg_match_all( '/(?<![\w$>:\\\\])update_option\s*\(/', $solseo_tag_code ),
	'the tag check writes one option and no more'
);

solseo_assert(
	false !== strpos( $solseo_tag_code, 'update_option( self::OPTION' ),
	'and the option it writes is its own'
);

solseo_assert(
	1 === preg_match( '/update_option\(\s*self::OPTION,\s*\$[a-z_]+,\s*false\s*\)/', (string) preg_replace( '/\s+/', '', $solseo_tag_code ) . ' ' )
		|| 1 === preg_match( '/update_option\(self::OPTION,[^)]*,false\)/', (string) preg_replace( '/\s+/', '', $solseo_tag_code ) ),
	'and it is never autoloaded'
);

/*
 * THE DISCLAIMER IS THE SENTENCE IT WAS WRITTEN AS.
 *
 * D-75.7 fixed the wording, because the difference between "these are checks
 * against published guidance" and anything warmer is the difference between a
 * useful screen and one our insurer would like a word about. A test that fails
 * on an edit is cheaper than the conversation.
 */
$solseo_disclaimer = 'These are checks against published guidance from the ACCC, the OAIC and the ATO. They are not legal advice, and passing them is not a finding of compliance.';

solseo_assert_same(
	$solseo_disclaimer,
	\SolSEO\Compliance\AU_Check::DISCLAIMER,
	'the Australian disclaimer is exactly the sentence D-75.7 fixed'
);

solseo_assert(
	false !== strpos( (string) file_get_contents( SOLSEO_PATH . 'includes/admin/views/health-australia.php' ), 'DISCLAIMER' ),
	'and the screen prints that constant rather than a copy of it'
);

/*
 * NOTHING CLAIMS THE SITE IS COMPLIANT, ACCESSIBLE OR LEGAL.
 *
 * Six content level accessibility checks and seven readings of published
 * guidance are worth having and are not an audit. A plugin that tells somebody
 * they are compliant has taken on a job it cannot do and has stopped them doing
 * it properly, which is worse than saying nothing.
 */
$solseo_claims = array(
	'/\bis compliant\b/i',
	'/\byou are compliant\b/i',
	'/\bfully compliant\b/i',
	'/\bnow compliant\b/i',
	'/\blegally safe\b/i',
	'/\bmeets the law\b/i',
	'/\bcomplies with the law\b/i',
	'/\bfully accessible\b/i',
	'/\bWCAG[- ](?:2\.\d|AA|AAA)[- ]?compliant\b/i',
	'/\bguarantees? (?:compliance|accessibility)\b/i',
	'/\bthis is legal advice\b/i',
	'/\bpasses the law\b/i',
);

$solseo_claimed = array();

foreach ( $solseo_half_files as $solseo_relative ) {
	if ( ! is_readable( SOLSEO_PATH . $solseo_relative ) ) {
		continue;
	}

	$solseo_body = (string) file_get_contents( SOLSEO_PATH . $solseo_relative );

	foreach ( $solseo_claims as $solseo_claim ) {
		if ( preg_match( $solseo_claim, $solseo_body ) ) {
			$solseo_claimed[] = basename( $solseo_relative ) . ' matches ' . $solseo_claim;
		}
	}
}

solseo_assert_same( array(), $solseo_claimed, 'nothing in this batch says the site is compliant, accessible or legal' );

/* AND THE GUARD HAS BEEN WATCHED FIRING. */
$solseo_caught = 0;

foreach ( $solseo_claims as $solseo_claim ) {
	if ( preg_match( $solseo_claim, 'Your site is compliant and fully accessible.' ) ) {
		++$solseo_caught;
	}
}

solseo_assert( $solseo_caught >= 2, 'the compliance guard catches a sentence that makes the claim' );

solseo_assert(
	0 === preg_match( '/\bis compliant\b/i', 'passing them is not a finding of compliance' ),
	'and does not read the disclaimer as the claim it refuses to make'
);

/*
 * NOTHING ON THESE SCREENS IS LOCKED.
 *
 * S3 in design/batches/Sidebar.md and D-85.9: the one place in this plugin that
 * names what somebody has not bought is the line at the foot of Settings,
 * Features, and the Upgrade screen they chose to open. Every one of these six
 * features ends where the paid half begins, and the free half says nothing
 * about it at all.
 */
$solseo_locked = array();

$solseo_selling = array(
	'/\bupgrade\b/i',
	'/\bPro\b/',
	'/\bpremium\b/i',
	'/\blicence key\b/i',
	'/\bper month\b/i',
	'/\bAUD\b/',
	'/\$\d/',
	'/\x{1F512}/u',
	'/&#128274;/',
	'/\bunlock\b/i',
	'/\bpaid plan\b/i',
);

foreach ( $solseo_half_files as $solseo_relative ) {
	if ( ! is_readable( SOLSEO_PATH . $solseo_relative ) ) {
		continue;
	}

	$solseo_body = (string) file_get_contents( SOLSEO_PATH . $solseo_relative );

	foreach ( $solseo_selling as $solseo_word ) {
		if ( preg_match( $solseo_word, $solseo_body ) ) {
			$solseo_locked[] = basename( $solseo_relative ) . ' matches ' . $solseo_word;
		}
	}

	if ( preg_match( '/\bdisabled\s*=\s*["\']?(?:disabled|true)?["\']?/i', $solseo_body ) ) {
		$solseo_locked[] = basename( $solseo_relative ) . ' draws a control nobody can press';
	}
}

solseo_assert_same( array(), $solseo_locked, 'nothing in this batch is locked, priced or greyed out' );

/*
 * THE ONE THING THAT WRITES ASKS ABOUT THE PAGE IT IS WRITING.
 *
 * Accepting a link suggestion edits somebody else's post, which is the one
 * genuinely dangerous thing in this batch. Hiding the button is a courtesy;
 * refusing the write is the control.
 */
$solseo_suggest = (string) file_get_contents( SOLSEO_PATH . 'includes/links/class-suggestions.php' );

solseo_assert(
	false !== strpos( $solseo_suggest, "current_user_can( 'edit_post'" ),
	'accepting a suggestion asks whether this person may edit that page'
);

solseo_assert(
	false !== strpos( $solseo_suggest, 'Change_Log::record' ),
	'and every accepted suggestion lands in the change log'
);

solseo_assert(
	1 === preg_match_all( '/(?<![\w$>:\\\\])wp_update_post\s*\(/', solseo_code_only( $solseo_suggest ) ),
	'and there is exactly one place a page is written'
);

$solseo_route = (string) file_get_contents( SOLSEO_PATH . 'includes/class-rest.php' );

solseo_assert(
	false !== strpos( $solseo_route, 'current_user_can( \'edit_post\', $source )' ),
	'and the route in front of it asks about the page being written to, not the page being edited'
);

/*
 * AND THE EDITOR READS THE KEYS THE ROUTE SENDS.
 *
 * D-80.8 shipped a panel reading analysis.checks over a route sending groups.
 * It reported a clean bill of health on a page with nine things wrong with it
 * and nothing failed, because there is no JavaScript test runner in this
 * plugin. So the contract is pinned from this side: every string a panel names
 * has to be a string the editor sends, and the two new keys have to be sent.
 */
$solseo_scripts = array(
	'assets/js/editor-sidebar.js',
	'assets/js/editor.js',
	'assets/js/editor-checklist.js',
);

$solseo_sent = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-editor-assets.php' );

$solseo_undefined = array();

foreach ( $solseo_scripts as $solseo_script ) {
	$solseo_js = (string) preg_replace(
		'#/\*.*?\*/|//[^\n]*#s',
		' ',
		(string) file_get_contents( SOLSEO_PATH . $solseo_script )
	);

	if ( ! preg_match_all( '/\bstrings\.(\w+)/', $solseo_js, $solseo_named ) ) {
		continue;
	}

	foreach ( array_unique( $solseo_named[1] ) as $solseo_key ) {
		if ( false === strpos( $solseo_sent, "'" . $solseo_key . "'" ) ) {
			$solseo_undefined[] = basename( $solseo_script ) . ' reads strings.' . $solseo_key . ', which nothing sends';
		}
	}
}

sort( $solseo_undefined );

solseo_assert_same( array(), $solseo_undefined, 'every string a panel reads is a string the editor sends' );

solseo_assert(
	false !== strpos( $solseo_route, "'a11y'        => Editor_Checks::run(" ),
	'the score route sends the accessibility findings'
);

solseo_assert(
	false !== strpos( $solseo_route, "'duplicates'  => Duplicates::for_post(" ),
	'and the pages going for the same phrase'
);

$solseo_sidebar = (string) preg_replace(
	'#/\*.*?\*/|//[^\n]*#s',
	' ',
	(string) file_get_contents( SOLSEO_PATH . 'assets/js/editor-sidebar.js' )
);

solseo_assert( false !== strpos( $solseo_sidebar, 'analysis.a11y' ), 'and the sidebar reads that key' );
solseo_assert( false !== strpos( $solseo_sidebar, 'analysis.duplicates' ), 'and that one' );

$solseo_classic = (string) preg_replace(
	'#/\*.*?\*/|//[^\n]*#s',
	' ',
	(string) file_get_contents( SOLSEO_PATH . 'assets/js/editor.js' )
);

solseo_assert( false !== strpos( $solseo_classic, 'result.a11y' ), 'and the classic box reads it too' );
solseo_assert( false !== strpos( $solseo_classic, 'result.duplicates' ), 'and that one as well' );

solseo_assert(
	false !== strpos( (string) file_get_contents( SOLSEO_PATH . 'includes/admin/views/metabox.php' ), 'data-solseo-notes' ),
	'and the classic box has somewhere to draw them'
);

/* AND THE ADDRESS THE PANEL ASKS FOR IS THE ADDRESS THE PLUGIN REGISTERS. */
solseo_assert(
	false !== strpos( $solseo_route, "'/suggestions'" ),
	'the suggestions route is registered at the address the panel asks for'
);

solseo_assert(
	2 === substr_count( $solseo_sidebar, '/solseo/v1/suggestions' ),
	'and the panel reads it and writes to it, and asks for nothing else'
);
