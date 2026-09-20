<?php
/**
 * The change log, and the rules that keep it small.
 *
 * @package SolSEO
 */

use SolSEO\Change_Log;

Change_Log::clear();

/* IT RECORDS WHAT IT IS GIVEN, NEWEST FIRST. */
Change_Log::record(
	array(
		'what'  => 'Changed title',
		'label' => 'A page',
	)
);

Change_Log::record(
	array(
		'what'  => 'Changed description',
		'label' => 'Another page',
	)
);

$solseo_log = Change_Log::all();

solseo_assert_same( 2, count( $solseo_log ), 'two changes are two entries' );
solseo_assert_same( 'Changed description', $solseo_log[0]['what'], 'and the newest is first' );
solseo_assert_same( 1, $solseo_log[0]['count'], 'each one counts once' );

/* AN ENTRY WITH NOTHING TO SAY IS NOT AN ENTRY. */
Change_Log::record( array( 'label' => 'No what' ) );

solseo_assert_same( 2, count( Change_Log::all() ), 'an entry with no description of what happened is dropped' );

/*
 * A RUN THAT TOUCHES FOUR HUNDRED PAGES IS ONE LINE.
 *
 * Every chunked job in this plugin spans several requests, so merging has to
 * work across them. The merge key is what makes that possible, and the count
 * is what makes the one line honest.
 */
Change_Log::clear();

for ( $solseo_i = 0; $solseo_i < 400; $solseo_i++ ) {
	Change_Log::record(
		array(
			'what'  => 'Changed title, description',
			'label' => 'Product ' . $solseo_i,
			'merge' => 'meta:title,description',
		)
	);
}

$solseo_bulk = Change_Log::all();

solseo_assert_same( 1, count( $solseo_bulk ), 'four hundred writes are one entry' );
solseo_assert_same( 400, $solseo_bulk[0]['count'], 'and the entry says four hundred' );

/* A DIFFERENT RUN IS A DIFFERENT ENTRY. */
Change_Log::record(
	array(
		'what'  => 'Changed canonical',
		'label' => 'One page',
		'merge' => 'meta:canonical',
	)
);

solseo_assert_same( 2, count( Change_Log::all() ), 'a run of something else is its own entry' );

/* THE LOG IS CAPPED, BECAUSE AN UNBOUNDED OPTION IS A BUG WITH A DELAY ON IT. */
Change_Log::clear();

for ( $solseo_i = 0; $solseo_i < Change_Log::LIMIT + 25; $solseo_i++ ) {
	Change_Log::record(
		array(
			'what'  => 'Change ' . $solseo_i,
			'label' => 'Page ' . $solseo_i,
		)
	);
}

solseo_assert_same( Change_Log::LIMIT, count( Change_Log::all() ), 'the log stops at its limit' );
solseo_assert_same( 'Change ' . ( Change_Log::LIMIT + 24 ), Change_Log::all()[0]['what'], 'and it is the newest that survive' );

/*
 * AND IT IS NEVER AUTOLOADED.
 *
 * An autoloaded log costs every page load on the site, for a screen almost
 * nobody opens.
 */
$solseo_log_src = (string) file_get_contents( SOLSEO_PATH . 'includes/class-change-log.php' );

solseo_assert(
	false !== strpos( $solseo_log_src, 'update_option( self::OPTION, $entries, false )' ),
	'the log is written with autoload off'
);

solseo_assert(
	1 === preg_match_all( '/update_option\(/', $solseo_log_src ),
	'and there is only one place it is written, so it cannot be written another way'
);

Change_Log::clear();

/*
 * THE ADD-ON RAISES THE CEILING, AND IT IS STILL A CEILING.
 */
add_filter(
	'solseo_change_log_limit',
	static function () {
		return 0;
	}
);

solseo_assert( Change_Log::limit() >= 1, 'a limit of nothing is refused, because a log of nothing is not a log' );

remove_all_filters( 'solseo_change_log_limit' );
