<?php
/**
 * The speed check: one call, the lab result and the field data, and a cause.
 *
 * @package SolSEO
 */

use SolSEO\Connect\Keys;
use SolSEO\Connect\Psi;
use SolSEO\Speed\Causes;

$solseo_psi_body = json_decode( (string) file_get_contents( SOLSEO_PATH . 'tests/fixtures/psi/result.json' ), true );

/*
 * ONE CALL CARRIES BOTH HALVES.
 *
 * The batch asked for a PageSpeed Insights key and a Chrome UX Report key. The
 * PageSpeed response carries the field data already, in loadingExperience and
 * originLoadingExperience, so the second key and the second call would fetch
 * what the first one returned. One key, one call.
 */
$solseo_result = Psi::normalise( $solseo_psi_body );

solseo_assert_same( 42, $solseo_result['score'], 'the lab score comes through as a number out of a hundred' );
solseo_assert_same( 'AVERAGE', $solseo_result['field']['overall'], 'this page has field data' );
solseo_assert_same( 'SLOW', $solseo_result['origin']['overall'], 'and so does the site it is on' );
solseo_assert_same( 3100, $solseo_result['field']['metrics']['LARGEST_CONTENTFUL_PAINT_MS']['value'], 'with the real numbers behind it' );
solseo_assert_same( 'GOOD', $solseo_result['field']['metrics']['CUMULATIVE_LAYOUT_SHIFT_SCORE']['band'], 'and the band each one sits in' );

/*
 * A PASSING AUDIT IS NOT A FINDING, AND A SCREENSHOT IS NOT ONE EITHER.
 */
$solseo_ids = wp_list_pluck( $solseo_result['findings'], 'id' );

solseo_assert( in_array( 'uses-optimized-images', $solseo_ids, true ), 'a failing audit is a finding' );
solseo_assert( ! in_array( 'server-response-time', $solseo_ids, true ), 'one that passed is not' );
solseo_assert( ! in_array( 'final-screenshot', $solseo_ids, true ), 'and neither is the screenshot' );

/* THE WORST ONE IS FIRST, BECAUSE THAT IS THE ONE TO FIX. */
solseo_assert_same( 'font-display', $solseo_result['findings'][0]['id'], 'the findings are ordered worst first' );

/*
 * EVERY FINDING NAMES A CAUSE OR ADMITS IT COULD NOT.
 *
 * "Reduce unused JavaScript" is what every other tool already says and it
 * tells a shop owner nothing. The cause is the plugin, the theme, the image or
 * the other company's script, by name, and when it cannot be worked out the
 * finding says so rather than printing the generic line alone.
 */
foreach ( $solseo_result['findings'] as $solseo_finding ) {
	solseo_assert( ! empty( $solseo_finding['title'] ), $solseo_finding['id'] . ' has a title' );
	solseo_assert( ! empty( $solseo_finding['causes'] ), $solseo_finding['id'] . ' names at least one cause or admits it cannot' );

	foreach ( $solseo_finding['causes'] as $solseo_cause ) {
		solseo_assert(
			in_array( $solseo_cause['kind'], array( 'plugin', 'theme', 'upload', 'wordpress', 'other-site', 'unknown' ), true ),
			$solseo_finding['id'] . ' says what kind of thing the cause is'
		);

		solseo_assert( '' !== trim( (string) $solseo_cause['says'] ), $solseo_finding['id'] . ' says it in words' );
	}
}

/*
 * AND THE CAUSE IS WORKED OUT FROM THE ADDRESS, WHICH IS ALL WE HAVE.
 *
 * The installed list is passed in rather than read, so this is testable
 * without a WordPress and so a site with four hundred plugins loads it once.
 */
$solseo_known = array(
	'plugins' => array( 'slider-deluxe' => 'Slider Deluxe' ),
	'themes'  => array( 'coastline' => 'Coastline' ),
	'home'    => 'https://example.test',
);

$solseo_cases = array(
	'https://example.test/wp-content/plugins/slider-deluxe/assets/slider.min.js' => array( 'plugin', 'Slider Deluxe' ),
	'https://example.test/wp-content/plugins/not-installed/x.js' => array( 'plugin', 'not-installed' ),
	'https://example.test/wp-content/themes/coastline/img/pattern.jpg' => array( 'theme', 'Coastline' ),
	'https://example.test/wp-content/uploads/2026/03/hero-banner.png' => array( 'upload', 'hero-banner.png' ),
	'https://example.test/wp-includes/css/dist/block-library/style.min.css' => array( 'wordpress', 'WordPress' ),
	'https://cdn.example-analytics.net/tag.js' => array( 'other-site', 'cdn.example-analytics.net' ),
	'data:image/jpeg;base64,abc'               => array( 'unknown', '' ),
);

foreach ( $solseo_cases as $solseo_url => $solseo_want ) {
	$solseo_cause = Causes::for_url( $solseo_url, $solseo_known );

	solseo_assert_same( $solseo_want[0], $solseo_cause['kind'], 'a ' . $solseo_want[0] . ' address is recognised' );

	if ( '' !== $solseo_want[1] ) {
		solseo_assert(
			false !== strpos( $solseo_cause['says'], $solseo_want[1] ),
			'and it is named as ' . $solseo_want[1] . ', not as ' . $solseo_url
		);
	}
}

solseo_assert(
	false !== stripos( Causes::for_url( 'data:image/jpeg;base64,abc', $solseo_known )['says'], 'could not' ),
	'an address that names nothing says it could not work out the cause'
);

/*
 * A SIZE IS SAID IN WORDS A SHOP OWNER USES.
 *
 * "2.4 MB" is the thing to act on. "wastedBytes: 2457600" is not.
 */
solseo_assert_same( '2.4 MB', Causes::weight( 2400000 ), 'a big file is said in megabytes' );
solseo_assert_same( '58 KB', Causes::weight( 57600 ), 'a smaller one in kilobytes' );
solseo_assert_same( '', Causes::weight( 0 ), 'and nothing is said about nothing' );

/*
 * WHAT GOOGLE SAID IS WHAT THE SCREEN SAYS.
 *
 * This fixture is the real thing: the response from the keyless endpoint on
 * 2026-09-19, which is a 429 with the daily quota at zero. Paraphrasing it as
 * "the speed check failed" hides the one sentence that tells the owner what to
 * do about it.
 */
$solseo_quota = json_decode( (string) file_get_contents( SOLSEO_PATH . 'tests/fixtures/psi/quota-exceeded.json' ), true );

$solseo_problem = Psi::problem( 429, $solseo_quota );

solseo_assert( is_wp_error( $solseo_problem ), 'a refused call comes back as an error' );

solseo_assert(
	false !== strpos( $solseo_problem->get_error_message(), 'Quota exceeded for quota metric' ),
	'carrying Google\'s own words rather than ours'
);

solseo_assert_same( null, Psi::problem( 200, $solseo_psi_body ), 'and a call that worked is not a problem' );

/*
 * THE REQUEST IS SHAPED THE WAY THE REFERENCE SAYS, AND CARRIES THE KEY.
 */
solseo_test_http_reset();
Keys::set( Keys::GOOGLE, 'AIzaSyTEST-0000000000000000000000abcd' );
solseo_test_http_next( 200, $solseo_psi_body );

Psi::run( 'https://example.test/about/', 'mobile' );

$solseo_sent = solseo_test_http_sent();

solseo_assert_same( 1, count( $solseo_sent ), 'one call is made, not two' );

solseo_assert(
	0 === strpos( $solseo_sent[0]['url'], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' ),
	'to the endpoint the reference gives'
);

foreach ( array( 'url=https%3A%2F%2Fexample.test%2Fabout%2F', 'strategy=mobile', 'category=performance', 'key=AIzaSyTEST' ) as $solseo_part ) {
	solseo_assert( false !== strpos( $solseo_sent[0]['url'], $solseo_part ), 'the request carries ' . $solseo_part );
}

/* AND THE ANSWER IS KEPT, SO PRESSING THE BUTTON TWICE DOES NOT CALL TWICE. */
solseo_test_http_reset();
$solseo_again = Psi::run( 'https://example.test/about/', 'mobile' );

solseo_assert_same( array(), solseo_test_http_sent(), 'a second look at the same page reads what was already fetched' );
solseo_assert_same( 42, $solseo_again['score'], 'and gets the same answer' );

/* A CALL THAT NEVER LANDS SAYS SO, RATHER THAN SHOWING AN EMPTY CHART. */
solseo_test_http_reset();
solseo_test_http_next( 0, '', 'cURL error 28: Operation timed out' );

$solseo_dead = Psi::run( 'https://example.test/contact/', 'mobile' );

solseo_assert( is_wp_error( $solseo_dead ), 'a call that does not land comes back as an error' );
solseo_assert( false !== strpos( $solseo_dead->get_error_message(), 'timed out' ), 'saying what happened' );

/* AND NOTHING THAT COMES BACK CARRIES THE KEY. */
solseo_assert(
	false === strpos( wp_json_encode( $solseo_again ), 'AIzaSy' ),
	'the result handed to a screen does not carry the credential that fetched it'
);

Keys::forget( Keys::GOOGLE );
solseo_test_http_reset();
