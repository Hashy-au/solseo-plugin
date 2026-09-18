<?php
/**
 * The job state machine.
 *
 * Runner::advance() and Runner::retry() take a state and a result and give a
 * state back. No database, no options, no WordPress. That is what lets every
 * branch of the thing that drives a two hour import be exercised here,
 * including the branches nobody can reach by clicking.
 *
 * @package SolSEO
 */

use SolSEO\Jobs\Runner;

$solseo_blank = Runner::blank( 'import', 100 );

solseo_assert_same( 'idle', $solseo_blank['status'], 'a job that has never run is idle' );
solseo_assert_same( 100, $solseo_blank['chunk'], 'a blank job carries the chunk size it was given' );
solseo_assert_same( 100, $solseo_blank['full_chunk'], 'and remembers it, so a halved chunk can be put back' );

/*
 * A chunk that did some of the work leaves the job running.
 */
$solseo_state           = $solseo_blank;
$solseo_state['status'] = 'running';

$solseo_after = Runner::advance(
	$solseo_state,
	array(
		'cursor'   => 412,
		'done'     => 100,
		'changed'  => 96,
		'failed'   => array(),
		'finished' => false,
	)
);

solseo_assert_same( 'running', $solseo_after['status'], 'a partial chunk leaves the job running' );
solseo_assert_same( 412, $solseo_after['cursor'], 'the cursor moves to the last object finished' );
solseo_assert_same( 100, $solseo_after['done'], 'the work done adds up' );
solseo_assert_same( 96, $solseo_after['changed'], 'and so does the work that changed something' );
solseo_assert_same( 0, $solseo_after['attempting'], 'a reported chunk is no longer in flight' );

/*
 * A chunk that ran out of objects moves to checking, not to done. Nothing is
 * finished until it has been checked, which is what earns the offer to switch
 * the other plugin off.
 */
$solseo_done = Runner::advance(
	$solseo_after,
	array(
		'cursor'   => 990,
		'done'     => 12,
		'changed'  => 12,
		'failed'   => array(),
		'finished' => true,
	)
);

solseo_assert_same( 'verifying', $solseo_done['status'], 'an exhausted cursor moves to checking, not straight to done' );
solseo_assert_same( 112, $solseo_done['done'], 'and the totals carry over' );

/*
 * A clean check finishes the job and says so.
 */
$solseo_clean = Runner::advance(
	$solseo_done,
	array(
		'verify' => array(
			'ok'      => true,
			'message' => 'Everything checked.',
			'failed'  => array(),
		),
	)
);

solseo_assert_same( 'done', $solseo_clean['status'], 'a clean check finishes the job' );
solseo_assert_same( true, $solseo_clean['verified'], 'and records that it was checked' );
solseo_assert_same( 'Everything checked.', $solseo_clean['message'], 'and keeps what it said' );
solseo_assert_same( 0, $solseo_clean['failed_total'], 'with nothing outstanding' );

/*
 * A check that found something outstanding finishes the job and does not
 * record it as verified, which is what keeps the handover offer off the
 * screen.
 */
$solseo_dirty = Runner::advance(
	$solseo_done,
	array(
		'verify' => array(
			'ok'      => false,
			'message' => 'Two pages did not come across.',
			'failed'  => array( 31, 44 ),
		),
	)
);

solseo_assert_same( 'done', $solseo_dirty['status'], 'a check that found something still finishes the job' );
solseo_assert_same( false, $solseo_dirty['verified'], 'but does not claim it was verified' );
solseo_assert_same( array( 31, 44 ), $solseo_dirty['failed'], 'and names what was left behind' );
solseo_assert_same( 2, $solseo_dirty['failed_total'], 'and counts it' );

/*
 * A chunk that never reported is halved and tried again from where it started.
 * This is the difference between one bad row stopping a site for good and one
 * bad row being named.
 */
$solseo_stuck               = $solseo_blank;
$solseo_stuck['status']     = 'running';
$solseo_stuck['cursor']     = 500;
$solseo_stuck['attempting'] = 501;

$solseo_halved = Runner::retry( $solseo_stuck );

solseo_assert_same( 50, $solseo_halved['chunk'], 'a chunk that fell over is halved' );
solseo_assert_same( 501, $solseo_halved['attempting'], 'and is tried again from where it started' );
solseo_assert_same( 500, $solseo_halved['cursor'], 'with the cursor untouched' );

$solseo_halved['chunk'] = 1;

$solseo_poison = Runner::retry( $solseo_halved );

solseo_assert_same( array( 501 ), $solseo_poison['failed'], 'one object that fails twice on its own is named' );
solseo_assert_same( 501, $solseo_poison['cursor'], 'and the job steps past it' );
solseo_assert_same( 0, $solseo_poison['attempting'], 'so nothing is left in flight' );
solseo_assert_same( 100, $solseo_poison['chunk'], 'and the chunk size goes back to what it was' );

/*
 * The list of failures is capped and the count is not, because a site with
 * four thousand bad rows must not put four thousand numbers in an option.
 */
$solseo_many           = $solseo_blank;
$solseo_many['status'] = 'running';
$solseo_lots           = range( 1, 250 );

$solseo_capped = Runner::advance(
	$solseo_many,
	array(
		'cursor'   => 250,
		'done'     => 250,
		'changed'  => 0,
		'failed'   => $solseo_lots,
		'finished' => false,
	)
);

solseo_assert_same( 200, count( $solseo_capped['failed'] ), 'the list of failures is capped' );
solseo_assert_same( 250, $solseo_capped['failed_total'], 'and the count of them is not' );

/*
 * WHICH OF THE TWO THINGS A STEP DOES, decided before anything touches the
 * status. Reading it afterwards is how the verification pass became
 * unreachable: every step ran the work again, found nothing left, set the
 * status back to verifying and went round again. The bar sat at full, the
 * message never changed, and nothing reported an error, so from the outside
 * it looked exactly like a job that was working.
 */
$solseo_running           = Runner::blank( 'import', 100 );
$solseo_running['status'] = 'running';

solseo_assert_same( 'work', Runner::next_action( $solseo_running ), 'a running job does a chunk of work' );

$solseo_checking           = Runner::blank( 'import', 100 );
$solseo_checking['status'] = 'verifying';

solseo_assert_same( 'verify', Runner::next_action( $solseo_checking ), 'a job with nothing left to do checks itself' );

$solseo_finished           = Runner::blank( 'import', 100 );
$solseo_finished['status'] = 'done';

solseo_assert_same( 'nothing', Runner::next_action( $solseo_finished ), 'and a finished job does neither' );

/*
 * The round trip, so the two functions cannot disagree: work that runs out
 * asks for a check, and a check finishes the job for good.
 */
$solseo_trip           = Runner::blank( 'import', 100 );
$solseo_trip['status'] = 'running';

$solseo_trip = Runner::advance(
	$solseo_trip,
	array(
		'cursor'   => 10,
		'done'     => 3,
		'changed'  => 3,
		'failed'   => array(),
		'finished' => true,
	)
);

solseo_assert_same( 'verify', Runner::next_action( $solseo_trip ), 'work that runs out asks to be checked' );

$solseo_trip = Runner::advance(
	$solseo_trip,
	array(
		'verify' => array(
			'ok'      => true,
			'message' => 'Checked.',
			'failed'  => array(),
		),
	)
);

solseo_assert_same( 'nothing', Runner::next_action( $solseo_trip ), 'and a check that passes ends it, rather than going round again' );
