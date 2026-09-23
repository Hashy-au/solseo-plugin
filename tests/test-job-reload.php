<?php
/**
 * A finished job reloads the page once, not for ever.
 *
 * The job screen reloads itself when a run finishes, because what a finished
 * run unlocks is decided on the server. The state the screen reads when it
 * loads also says 'done', so a reload fired from that state reloads the page
 * that has just reloaded, and the tab sits there refreshing every second and a
 * bit with the work already finished. That is what the import screen did on a
 * customer site on 2026-09-20.
 *
 * The rule: a reload only ever follows a run this page watched finish, which is
 * what `solseoRan` records.
 *
 * @package SolSEO
 */

$solseo_reload_files = glob( SOLSEO_PATH . 'assets/js/*.js' );

solseo_assert( count( $solseo_reload_files ) > 5, 'the reload guard is reading the admin JavaScript' );

$solseo_unguarded = array();
$solseo_reloads   = 0;

foreach ( $solseo_reload_files as $solseo_file ) {
	$solseo_text = (string) file_get_contents( $solseo_file );

	if ( ! preg_match_all( '~location\s*\.\s*reload\s*\(~', $solseo_text, $solseo_found, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}

	foreach ( $solseo_found[0] as $solseo_hit ) {
		++$solseo_reloads;

		$solseo_before = substr( $solseo_text, max( 0, $solseo_hit[1] - 400 ), min( 400, $solseo_hit[1] ) );

		if ( false === strpos( $solseo_before, 'solseoRan' ) ) {
			$solseo_unguarded[] = basename( $solseo_file );
		}
	}
}

solseo_assert( $solseo_reloads > 0, 'the reload guard found the reload it is guarding' );
solseo_assert_same( array(), $solseo_unguarded, 'every page reload follows a run this page watched finish' );

/*
 * And the flag it reads is only ever raised by starting a run. Raising it when
 * the screen first reads its state puts the loop straight back.
 */
$solseo_jobs = (string) file_get_contents( SOLSEO_PATH . 'assets/js/jobs.js' );

solseo_assert_same(
	1,
	preg_match_all( '~solseoRan\s*=\s*true~', $solseo_jobs ),
	'the flag saying this page ran the job is raised in one place'
);

solseo_assert(
	(bool) preg_match( '~function start\([^}]*?solseoRan\s*=\s*true~s', $solseo_jobs ),
	'the flag saying this page ran the job is raised by starting it'
);
