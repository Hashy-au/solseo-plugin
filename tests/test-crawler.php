<?php
/**
 * The crawler: courtesy, completeness and knowing when to stop.
 *
 * The frontier lives in a table and the tests have no database, so the three
 * places the crawler touches it are the three methods a test subclass swaps
 * out. Everything else in here, which is the chunking, the pacing, the halt on
 * a 429 and the decision that there is nothing left to do, is the real code.
 *
 * @package SolSEO
 */

use SolSEO\Crawl\Crawler;
use SolSEO\Crawl\Fetch;
use SolSEO\Jobs\Runner;

/**
 * A crawler whose frontier is an array.
 */
class SolSEO_Test_Crawler extends Crawler {

	/**
	 * Rows waiting to be fetched, in id order.
	 *
	 * @var array
	 */
	public static $frontier = array();

	/**
	 * Rows that were written back, keyed by row id.
	 *
	 * @var array
	 */
	public static $written = array();

	/**
	 * Addresses discovered part way through a run.
	 *
	 * @var array
	 */
	public static $found = array();

	/**
	 * Load a frontier and forget the last run.
	 *
	 * @param array $urls Addresses to crawl.
	 */
	public static function seed_with( array $urls ) {
		self::$frontier = array();
		self::$written  = array();
		self::$found    = array();

		$id = 0;

		foreach ( $urls as $url ) {
			++$id;

			self::$frontier[ $id ] = array(
				'id'        => $id,
				'url'       => $url,
				'source'    => 'seed',
				'found_on'  => 0,
				'chosen_by' => 'front',
			);
		}
	}

	/**
	 * The next rows with nothing recorded against them.
	 *
	 * @param int $limit How many.
	 * @param int $after The last row id finished.
	 * @return array
	 */
	protected static function pending( $limit, $after ) {
		$rows = array();

		foreach ( self::$frontier as $id => $row ) {
			if ( $id <= (int) $after || isset( self::$written[ $id ] ) ) {
				continue;
			}

			$rows[] = $row;

			if ( count( $rows ) >= (int) $limit ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * How many rows are still waiting.
	 *
	 * @param int $after The last row id finished.
	 * @return int
	 */
	protected static function waiting( $after ) {
		return count( self::pending( PHP_INT_MAX, $after ) );
	}

	/**
	 * Write one answer back.
	 *
	 * @param array $row    The frontier row.
	 * @param array $result What the fetch said.
	 */
	protected static function record( array $row, array $result ) {
		self::$written[ (int) $row['id'] ] = $result;
	}

	/**
	 * Note an address the run turned up.
	 *
	 * @param string $url  The address.
	 * @param array  $from The row it was found on.
	 * @return bool
	 */
	protected static function discover( $url, array $from ) {
		self::$found[] = array(
			'url'  => $url,
			'from' => $from['url'],
		);

		return true;
	}
}

/*
 * COURTESY IS ENFORCED, NOT CONFIGURED AWAY.
 *
 * This runs on somebody's shared hosting against their own server, so the
 * setting decides how slow, never whether. Every way of writing "no wait" into
 * the option comes back as a wait.
 */
$solseo_no_wait = array( 0, -1, '0', '', 'as fast as you like', null, 100000, 1.5 );

foreach ( $solseo_no_wait as $solseo_asked ) {
	\SolSEO\Options::update( array( 'crawl_per_minute' => $solseo_asked ) );

	$solseo_rate = Fetch::per_minute();

	solseo_assert(
		$solseo_rate >= Fetch::MIN_PER_MINUTE && $solseo_rate <= Fetch::MAX_PER_MINUTE,
		'a rate of ' . var_export( $solseo_asked, true ) . ' comes back inside the range (got ' . var_export( $solseo_rate, true ) . ')'
	);

	solseo_assert(
		Fetch::pace( $solseo_rate, 1000.0, 1000.0 ) > 0,
		'and two requests in the same instant still have to wait at ' . var_export( $solseo_asked, true )
	);
}

\SolSEO\Options::update( array( 'crawl_per_minute' => 30 ) );

solseo_assert_same( 30, Fetch::per_minute(), 'a rate inside the range is kept' );
solseo_assert_same( 2.0, Fetch::pace( 30, 1000.0, 1000.0 ), 'thirty a minute is one every two seconds' );
solseo_assert_same( 0.5, Fetch::pace( 30, 1000.0, 1001.5 ), 'and time already spent counts towards the gap' );
solseo_assert_same( 0.0, Fetch::pace( 30, 1000.0, 1009.0 ), 'and a long gap needs no wait at all' );
solseo_assert_same( 0.0, Fetch::pace( 30, 0.0, 1000.0 ), 'the first request of a run waits for nothing' );

/*
 * AND THE GAP IS KEPT BY THE ONE FILE THAT OPENS THE SOCKET.
 *
 * A pace worked out somewhere else and passed in is a pace the next caller
 * forgets to pass. The clock is read and remembered inside the fetcher, so
 * every request in a run is spaced from the one before it whatever called it,
 * and across the chunk boundary as well, which is where a per-request loop
 * would let a burst through.
 */
Fetch::forget();

solseo_assert_same( 0.0, Fetch::last_at(), 'a fresh run has nothing to wait behind' );

solseo_test_http_reset();
solseo_test_http_next( 200, '<html><head><title>One</title></head><body><h1>One</h1><p>Hello there.</p></body></html>' );

$solseo_before = microtime( true );

Fetch::get( home_url( '/one/' ) );

solseo_assert( Fetch::last_at() > 0, 'and it remembers when it last went out' );
solseo_assert( ( microtime( true ) - $solseo_before ) < 0.4, 'the first request is not held up' );

/*
 * THE FREE CRAWL IS COMPLETE OVER ITS SUBSET.
 *
 * Three hundred pages crawled to the end, not the first however-many of a
 * thousand. The job may only say it has finished when the frontier is empty,
 * and every address in the frontier has to have been asked for exactly once.
 */
\SolSEO\Options::update( array( 'crawl_per_minute' => Fetch::MAX_PER_MINUTE ) );

$solseo_urls = array();

for ( $solseo_n = 1; $solseo_n <= 7; $solseo_n++ ) {
	$solseo_urls[] = home_url( '/page-' . $solseo_n . '/' );
}

SolSEO_Test_Crawler::seed_with( $solseo_urls );

Fetch::forget();
solseo_test_http_reset();

foreach ( $solseo_urls as $solseo_url ) {
	solseo_test_http_next( 200, '<html><head><title>A page</title><meta name="description" content="Something."></head><body><h1>A page</h1><p>' . str_repeat( 'word ', 40 ) . '</p></body></html>' );
}

$solseo_state           = Runner::blank( 'crawl', 3 );
$solseo_state['status'] = 'running';
$solseo_state['total']  = count( $solseo_urls );

$solseo_rounds   = 0;
$solseo_finished = array();

while ( 'running' === $solseo_state['status'] && $solseo_rounds < 20 ) {
	++$solseo_rounds;

	$solseo_result     = SolSEO_Test_Crawler::work( $solseo_state );
	$solseo_finished[] = ! empty( $solseo_result['finished'] );
	$solseo_state      = Runner::advance( $solseo_state, $solseo_result );
}

solseo_assert_same( 'verifying', $solseo_state['status'], 'the crawl ran out of pages rather than out of patience' );
solseo_assert_same( 7, count( SolSEO_Test_Crawler::$written ), 'every address in the frontier was crawled' );
solseo_assert_same( 7, count( solseo_test_http_sent() ), 'and each one was asked for exactly once' );
solseo_assert_same( 7, (int) $solseo_state['done'], 'and the count on screen matches' );

solseo_assert_same(
	array( false ),
	array_values( array_unique( array_slice( $solseo_finished, 0, -1 ) ) ),
	'no chunk claimed the crawl was over while the frontier still held pages'
);

solseo_assert(
	! empty( $solseo_finished[ count( $solseo_finished ) - 1 ] ),
	'and the last one did, because by then it was'
);

/*
 * A 429 FROM THE HOST PAUSES THE CRAWL AND SAYS SO.
 *
 * Retrying into a wall is how a crawler that was meant to be polite takes a
 * shared host down. The work already done keeps its place, the rest of the
 * frontier is still there, and the button says carry on.
 */
SolSEO_Test_Crawler::seed_with( $solseo_urls );

Fetch::forget();
solseo_test_http_reset();

solseo_test_http_next( 200, '<html><head><title>One</title></head><body><h1>One</h1></body></html>' );
solseo_test_http_next( 200, '<html><head><title>Two</title></head><body><h1>Two</h1></body></html>' );
solseo_test_http_next( 429, 'Slow down' );

$solseo_state           = Runner::blank( 'crawl', 10 );
$solseo_state['status'] = 'running';
$solseo_state['total']  = count( $solseo_urls );

$solseo_state = Runner::advance( $solseo_state, SolSEO_Test_Crawler::work( $solseo_state ) );

solseo_assert_same( 'stopped', $solseo_state['status'], 'a 429 stops the crawl where it is' );
solseo_assert( false !== strpos( $solseo_state['message'], 'too many requests' ), 'and the message says what the host said' );
solseo_assert_same( 2, (int) $solseo_state['done'], 'the pages fetched before it keep their place' );
solseo_assert_same( 3, count( solseo_test_http_sent() ), 'and nothing was asked for after the refusal' );

solseo_assert_same(
	'work',
	Runner::next_action( $solseo_state ),
	'and carrying on is the next thing the runner would do'
);

/*
 * READING A PAGE IS PURE, BECAUSE EVERY WRONG ANSWER HERE IS A ROW IN A REPORT
 * SOMEBODY ACTS ON.
 */
$solseo_page = Crawler::read(
	'<html><head><title>  Boots and all  </title>'
	. '<meta name="description" content="Everything about boots.">'
	. '<meta name="robots" content="noindex, follow">'
	. '<link rel="canonical" href="https://example.test/boots/">'
	. '</head><body><h1>Boots</h1><h1>And all</h1><p>One two three four five.</p>'
	. '<a href="/socks/">Socks</a><a href="https://elsewhere.test/hats/">Hats</a></body></html>',
	'https://example.test/boots/'
);

solseo_assert_same( 'Boots and all', $solseo_page['title'], 'the title comes back trimmed' );
solseo_assert_same( 'Everything about boots.', $solseo_page['description'], 'and the description with it' );
solseo_assert_same( 'noindex, follow', $solseo_page['robots'], 'and what the page says to robots' );
solseo_assert_same( 'https://example.test/boots/', $solseo_page['canonical'], 'and the canonical it names' );
solseo_assert_same( 'Boots', $solseo_page['h1'], 'the first h1, because the first is the one that counts' );
solseo_assert_same( 2, $solseo_page['h1_count'], 'and how many there were, because two is a problem' );
solseo_assert_same( 10, $solseo_page['words'], 'the words are counted off the text, not the markup' );
solseo_assert_same( array( '/socks' ), $solseo_page['internal'], 'the internal links are kept, normalised the way the link index keeps them' );
solseo_assert( ! isset( $solseo_page['external'] ), 'and the ones going elsewhere are never collected, because nothing in the free plugin checks those' );

/*
 * AND THE CHROME EVERY THEME REPEATS IS NOT COUNTED AS WRITING.
 *
 * A crawled page is the whole document. Counting the menu, the cookie bar and
 * the footer would say every page on this site has four hundred words, and the
 * thin ones this is meant to find would never appear.
 */
$solseo_themed = Crawler::read(
	'<html><body><header><p>Shop Basket Account</p></header><nav><a href="/a/">One</a><a href="/b/">Two</a></nav>'
	. '<main><h1>Boots</h1><p>Five words in here exactly.</p></main>'
	. '<footer><p>Copyright somebody, all rights reserved, everywhere.</p></footer></body></html>',
	'https://example.test/boots/'
);

solseo_assert_same( 6, $solseo_themed['words'], 'a page with a main region is counted from the main region' );
solseo_assert_same( 'Boots', $solseo_themed['h1'], 'and the heading still comes from wherever it is' );

$solseo_bare = Crawler::read(
	'<html><body><header><p>Shop Basket Account</p></header>'
	. '<div><h1>Boots</h1><p>Five words in here exactly.</p></div>'
	. '<footer><p>Copyright somebody, all rights reserved, everywhere.</p></footer></body></html>',
	'https://example.test/boots/'
);

solseo_assert_same( 6, $solseo_bare['words'], 'and a page without one has its header and footer taken off first' );

$solseo_empty = Crawler::read( '', 'https://example.test/nothing/' );

solseo_assert_same( '', $solseo_empty['title'], 'a page with nothing in it reads as nothing' );
solseo_assert_same( 0, $solseo_empty['words'], 'and counts no words' );
solseo_assert_same( 0, $solseo_empty['h1_count'], 'and no headings' );

/*
 * ONE CRAWL A MONTH, AND A CRAWL THAT WAS STOPPED IS NOT A NEW ONE.
 *
 * The wait starts when a crawl finishes. Charging somebody a month for a crawl
 * they stopped after four pages would make stopping the expensive choice, and
 * the button that says "Stop" has to be safe to press.
 */
delete_option( Crawler::LAST );

solseo_assert( Crawler::may_start(), 'a site that has never crawled may crawl' );

update_option( Crawler::LAST, time() - ( 40 * DAY_IN_SECONDS ), false );
solseo_assert( Crawler::may_start(), 'and so may one whose last crawl was forty days ago' );

update_option( Crawler::LAST, time() - ( 3 * DAY_IN_SECONDS ), false );
solseo_assert( ! Crawler::may_start(), 'one that crawled three days ago waits' );
solseo_assert( Crawler::waiting_until() > time(), 'and is told when it may go again' );

solseo_assert(
	is_wp_error( Crawler::plan( array() ) ),
	'and asking anyway is refused rather than quietly starting'
);

delete_option( Crawler::LAST );
