<?php
/**
 * Telling search engines a page changed, the moment it changes.
 *
 * @package SolSEO
 */

use SolSEO\Indexing\Indexnow;
use SolSEO\Indexing\Submit;

/*
 * THE KEY IS OURS TO MAKE, NOT THEIRS TO ISSUE.
 *
 * IndexNow is the one credential in this batch that nobody hands out. You
 * invent a string, serve it as a text file at your own root, and the engines
 * read it back to check the submission came from whoever owns the site. The
 * design called it a fourth key to paste, which would have made the site owner
 * invent a hex string and create a file by hand, and then watch submissions
 * fail with 403 and nothing on screen explaining it. So we make it and we
 * serve it.
 */
$solseo_key = Indexnow::key();

solseo_assert( 32 === strlen( $solseo_key ), 'the key is 32 characters, inside the 8 to 128 the protocol allows' );
solseo_assert( 1 === preg_match( '/^[a-zA-Z0-9-]+$/', $solseo_key ), 'and uses only the characters the protocol allows' );
solseo_assert_same( $solseo_key, Indexnow::key(), 'and it is made once, not once per call' );

/* AND IT IS SERVED WHERE THE PROTOCOL SAYS TO LOOK FOR IT. */
solseo_assert_same( '/' . $solseo_key . '.txt', Indexnow::path(), 'the key file sits at the root under its own name' );
solseo_assert( Indexnow::serves( '/' . $solseo_key . '.txt' ), 'and that address is claimed' );
solseo_assert( ! Indexnow::serves( '/something-else.txt' ), 'and no other is' );
solseo_assert( ! Indexnow::serves( '/.txt' ), 'and neither is the empty one' );
solseo_assert_same( $solseo_key, Indexnow::body(), 'the file holds the key and nothing else' );

/*
 * THE PAYLOAD IS THE SHAPE THE PROTOCOL DOCUMENTS.
 */
$solseo_payload = Indexnow::payload(
	array(
		'https://example.test/about/',
		'https://example.test/contact/',
	)
);

solseo_assert_same( 'example.test', $solseo_payload['host'], 'the payload names the host' );
solseo_assert_same( $solseo_key, $solseo_payload['key'], 'and carries the key' );
solseo_assert_same( 'https://example.test/' . $solseo_key . '.txt', $solseo_payload['keyLocation'], 'and says where the key file is' );
solseo_assert_same( 2, count( $solseo_payload['urlList'] ), 'and lists the addresses' );

/* AN ADDRESS ON ANOTHER SITE IS DROPPED HERE, NOT REFUSED THERE. */
$solseo_mixed = Indexnow::payload(
	array(
		'https://example.test/about/',
		'https://somebody-else.test/about/',
		'not a url at all',
	)
);

solseo_assert_same(
	array( 'https://example.test/about/' ),
	$solseo_mixed['urlList'],
	'an address on another site is dropped before it is sent, rather than earning a 422'
);

solseo_assert_same( array(), Indexnow::payload( array() )['urlList'], 'and nothing is an empty list' );

/*
 * WHAT COMES BACK IS SAID IN WORDS SOMEBODY CAN ACT ON.
 *
 * 403 is the one that matters: it means the engine looked for the key file and
 * did not find it, which on a WordPress site almost always means a caching
 * layer or a security plugin is in front of it. "403" on a screen sends
 * somebody to a support forum. The sentence sends them to the file.
 */
$solseo_answers = array(
	200 => array( true, 'accepted' ),
	202 => array( true, 'accepted' ),
	400 => array( false, 'malformed' ),
	403 => array( false, 'key file' ),
	422 => array( false, 'belong' ),
	429 => array( false, 'too many' ),
	500 => array( false, 'again' ),
);

foreach ( $solseo_answers as $solseo_code => $solseo_want ) {
	$solseo_read = Indexnow::read( $solseo_code );

	solseo_assert_same( $solseo_want[0], $solseo_read['ok'], $solseo_code . ' is read as ' . ( $solseo_want[0] ? 'accepted' : 'refused' ) );

	solseo_assert(
		false !== stripos( $solseo_read['says'], $solseo_want[1] ),
		$solseo_code . ' says something with "' . $solseo_want[1] . '" in it, rather than printing the number'
	);
}

solseo_assert( Indexnow::read( 429 )['wait'], 'being told there are too many is a reason to stop for a while' );
solseo_assert( ! Indexnow::read( 403 )['wait'], 'and being told the key is wrong is not, because waiting will not fix it' );

/*
 * ONE CALL CARRIES THE WHOLE QUEUE.
 *
 * A bulk edit of forty products fires save_post forty times in one request. A
 * submission per save is forty calls, which is the shape that earns a 429 and
 * gets a site rate limited for the rest of the day.
 */
solseo_test_http_reset();
Submit::forget_queue();

Submit::queue( 'https://example.test/one/' );
Submit::queue( 'https://example.test/two/' );
Submit::queue( 'https://example.test/one/' );

solseo_assert_same( 2, count( Submit::queued() ), 'the same address queued twice is queued once' );

solseo_test_http_next( 200, '' );
Submit::flush();

$solseo_sent = solseo_test_http_sent();

solseo_assert_same( 1, count( $solseo_sent ), 'two changed pages are one call, not two' );
solseo_assert_same( 'https://api.indexnow.org/indexnow', $solseo_sent[0]['url'], 'to the shared endpoint, which passes it to every engine that takes part' );
solseo_assert_same( 'POST', $solseo_sent[0]['args']['method'], 'as a POST' );
solseo_assert_same( 'application/json; charset=utf-8', $solseo_sent[0]['args']['headers']['Content-Type'], 'carrying JSON' );

$solseo_body = json_decode( (string) $solseo_sent[0]['args']['body'], true );

solseo_assert_same( 2, count( $solseo_body['urlList'] ), 'with both addresses in it' );
solseo_assert_same( array(), Submit::queued(), 'and the queue is empty afterwards' );

/* A FLUSH WITH NOTHING IN IT CALLS NOBODY. */
solseo_test_http_reset();
Submit::flush();

solseo_assert_same( array(), solseo_test_http_sent(), 'nothing changed means nothing is sent' );

/*
 * A CALL THAT NEVER LANDS IS TRIED ONCE MORE, AND THEN LET GO.
 *
 * Retrying a 403 forever is how a plugin turns one misconfiguration into a
 * thousand requests. A connection that dropped is worth one more go; an answer
 * that says the key is wrong is not.
 */
solseo_test_http_reset();
Submit::queue( 'https://example.test/three/' );
solseo_test_http_next( 0, '', 'cURL error 28: Operation timed out' );
solseo_test_http_next( 200, '' );

$solseo_outcome = Submit::flush();

solseo_assert_same( 2, count( solseo_test_http_sent() ), 'a dropped connection is tried once more' );
solseo_assert( $solseo_outcome['ok'], 'and the second go is the one that counts' );

solseo_test_http_reset();
Submit::queue( 'https://example.test/four/' );
solseo_test_http_next( 403, '' );

$solseo_refused = Submit::flush();

solseo_assert_same( 1, count( solseo_test_http_sent() ), 'an answer that says the key is wrong is not tried again' );
solseo_assert( ! $solseo_refused['ok'], 'and it is reported as refused' );
solseo_assert( false !== stripos( $solseo_refused['says'], 'key file' ), 'in words that say what to look at' );

/*
 * WHAT IS WORTH TELLING AN ENGINE ABOUT, AND WHAT IS NOT.
 */
$solseo_published = solseo_test_post(
	array(
		'ID'          => 701,
		'post_status' => 'publish',
		'post_type'   => 'page',
	)
);

$solseo_draft = solseo_test_post(
	array(
		'ID'          => 702,
		'post_status' => 'draft',
		'post_type'   => 'page',
	)
);

$solseo_private = solseo_test_post(
	array(
		'ID'          => 703,
		'post_status' => 'private',
		'post_type'   => 'page',
	)
);

$solseo_hidden = solseo_test_post(
	array(
		'ID'          => 704,
		'post_status' => 'publish',
		'post_type'   => 'page',
	)
);

$GLOBALS['solseo_test_meta'][704]['_solseo_robots_noindex'] = '1';

solseo_assert( Submit::worth_telling( $solseo_published ), 'a published page is worth telling them about' );
solseo_assert( ! Submit::worth_telling( $solseo_draft ), 'a draft is not, because it has no address yet' );
solseo_assert( ! Submit::worth_telling( $solseo_private ), 'and neither is a private one' );
solseo_assert( ! Submit::worth_telling( $solseo_hidden ), 'and neither is one we are asking to stay out of search results' );

$solseo_revision = solseo_test_post(
	array(
		'ID'          => 705,
		'post_status' => 'inherit',
		'post_type'   => 'revision',
	)
);

solseo_assert( ! Submit::worth_telling( $solseo_revision ), 'and a revision is not a page at all' );

/*
 * AND THE SAME PAGE TWICE IN A MINUTE IS ONCE.
 *
 * Saving in the block editor fires this more than once on its own, before
 * anybody presses anything twice.
 */
delete_post_meta( 701, Submit::TOLD );

solseo_assert( Submit::due( $solseo_published ), 'a page that has never been submitted is due' );

Submit::mark( $solseo_published );

solseo_assert( ! Submit::due( $solseo_published ), 'and the moment after it is submitted, it is not' );

$GLOBALS['solseo_test_meta'][701][ Submit::TOLD ] = time() - ( Submit::FLOOR + 5 );

solseo_assert( Submit::due( $solseo_published ), 'and once the floor has passed it is due again' );

solseo_test_http_reset();
Submit::forget_queue();
