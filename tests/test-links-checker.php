<?php
/**
 * Internal dead links and redirect chains.
 *
 * Everything here works on addresses on this site. The free half of F13 checks
 * nothing else on purpose: a link to somebody else's site is a request to
 * somebody else's server, on a schedule, with a cache, and that is ProB2.
 *
 * @package SolSEO
 */

use SolSEO\Links\Checker;

/*
 * NO EXTERNAL HTTP FROM THE LINK CHECKER.
 *
 * Two halves. Nothing in includes/links/ opens a socket at all, which the
 * declaration in test-directory-rules.php also pins; and the gate that decides
 * what is worth checking refuses an address that is not ours before anything
 * downstream gets the chance.
 */
$solseo_link_files = glob( SOLSEO_PATH . 'includes/links/*.php' );

solseo_assert( count( $solseo_link_files ) >= 4, 'the link guard is reading the link files' );

$solseo_link_http = array();

foreach ( $solseo_link_files as $solseo_file ) {
	if ( preg_match( '/\b(wp_(safe_)?remote_(request|get|post|head)|curl_init|fsockopen|stream_socket_client|file_get_contents)\s*\(/', (string) file_get_contents( $solseo_file ) ) ) {
		$solseo_link_http[] = basename( $solseo_file );
	}
}

solseo_assert_same( array(), $solseo_link_http, 'nothing in the link checker opens a connection of its own' );

$solseo_elsewhere = array(
	'https://example.com/page/'      => 'another site',
	'//example.com/page/'            => 'another site without a scheme',
	'https://example.test.evil.com/' => 'a host that merely starts the same',
	'mailto:someone@example.test'    => 'an email address',
	'tel:+61400000000'               => 'a phone number',
	'#section'                       => 'a jump to somewhere on the same page',
);

foreach ( $solseo_elsewhere as $solseo_href => $solseo_what ) {
	solseo_assert(
		! Checker::checkable( $solseo_href ),
		'the checker leaves ' . $solseo_what . ' alone'
	);
}

/*
 * AND IT LEAVES ALONE THE THINGS THAT RESOLVE TO NO PAGE AND ARE PERFECTLY
 * FINE.
 *
 * The link table's own note says this: a raw list of internal links that map to
 * no post reports a working site as broken, because an upload, a feed and a
 * category archive are all links to something that is not a post. A checker
 * that cries wolf is switched off in a week, and then the real ones go unread
 * too. This is D-80.4 pointed at addresses.
 */
$solseo_fine = array(
	'/wp-content/uploads/2026/09/price-list.pdf' => 'a file somebody uploaded',
	'/wp-content/uploads/2026/09/photo.jpg'      => 'an image',
	'/feed/'                                     => 'a feed',
	'/comments/feed/'                            => 'another feed',
	'/wp-admin/options-general.php'              => 'the admin',
	'/wp-login.php'                              => 'the login screen',
	'/wp-json/wp/v2/posts'                       => 'the REST API',
	'/xmlrpc.php'                                => 'the other API',
);

foreach ( $solseo_fine as $solseo_path => $solseo_what ) {
	solseo_assert(
		! Checker::checkable( $solseo_path ),
		'the checker leaves ' . $solseo_what . ' alone'
	);
}

$solseo_worth = array( '/about/', '/shop/boots/', '/2026/09/a-post/', '/contact', '/' );

foreach ( $solseo_worth as $solseo_path ) {
	solseo_assert( Checker::checkable( $solseo_path ), $solseo_path . ' is worth checking' );
}

/*
 * A DEAD LINK NAMES THE PAGE THAT LINKS TO IT.
 *
 * A list of addresses that do not answer is an interesting fact. A list of
 * pages to go and edit is a job somebody can finish.
 */
$solseo_row = array(
	'url'         => '/old-offer/',
	'status_code' => 404,
	'redirect_to' => '',
);

$solseo_sources = array(
	array(
		'id'    => 12,
		'title' => 'Our September sale',
	),
	array(
		'id'    => 34,
		'title' => 'Frequently asked questions',
	),
);

$solseo_said = Checker::describe( $solseo_row, $solseo_sources );

solseo_assert( false !== strpos( $solseo_said, 'Our September sale' ), 'the sentence names the page that links to it' );
solseo_assert( false !== strpos( $solseo_said, 'Frequently asked questions' ), 'and the other one' );

$solseo_many = array();

for ( $solseo_n = 1; $solseo_n <= 9; $solseo_n++ ) {
	$solseo_many[] = array(
		'id'    => $solseo_n,
		'title' => 'Page ' . $solseo_n,
	);
}

$solseo_lots = Checker::describe( $solseo_row, $solseo_many );

solseo_assert( false !== strpos( $solseo_lots, '6 more' ), 'nine linking pages become three and a count' );

/*
 * AND EVERY DEAD LINK OFFERS THE SAME THREE FIXES.
 */
$solseo_fixes = Checker::fixes( $solseo_row, $solseo_sources );

solseo_assert_same( 3, count( $solseo_fixes ), 'a dead link offers three fixes' );

solseo_assert_same(
	array( 'edit', 'unlink', 'redirect' ),
	wp_list_pluck( $solseo_fixes, 'do' ),
	'and they are editing the link, taking it out, and sending the address somewhere'
);

foreach ( $solseo_fixes as $solseo_fix ) {
	solseo_assert( '' !== trim( $solseo_fix['label'] ), $solseo_fix['do'] . ' says what it does' );
}

solseo_assert_same(
	0,
	count( Checker::fixes( $solseo_row, array() ) ),
	'a dead address nothing links to any more is not offered a fix, because there is nothing to fix'
);

/*
 * TAKING A LINK OUT KEEPS THE WORDS.
 *
 * Pure, because this rewrites somebody's page and the only safe version of that
 * is one a test can read every branch of.
 */
$solseo_before = '<p>Read <a href="/old-offer/" class="x">our September offer</a> before it goes.</p>';

solseo_assert_same(
	'<p>Read our September offer before it goes.</p>',
	Checker::remove_link( $solseo_before, '/old-offer/' ),
	'the anchor goes and the sentence survives'
);

solseo_assert_same(
	'<p>A <a href="/live/">live one</a> and gone.</p>',
	Checker::remove_link( '<p>A <a href="/live/">live one</a> and <a href="/old-offer/">gone</a>.</p>', '/old-offer/' ),
	'and the links that work are not touched'
);

solseo_assert_same(
	'<p>one and two</p>',
	Checker::remove_link( '<p><a href="/old-offer/">one</a> and <a href=\'/old-offer/\'>two</a></p>', '/old-offer/' ),
	'both copies of the same dead link go, however the quotes were written'
);

$solseo_untouched = '<p>Nothing here points anywhere.</p>';

solseo_assert_same(
	$solseo_untouched,
	Checker::remove_link( $solseo_untouched, '/old-offer/' ),
	'and a page that does not hold the link is handed back unchanged'
);

solseo_assert_same(
	'<p>An <img src="/a.png" alt="image"> link</p>',
	Checker::remove_link( '<p>An <a href="/old-offer/"><img src="/a.png" alt="image"></a> link</p>', '/old-offer/' ),
	'an image inside the link is kept, because deleting somebody\'s photo is not unlinking'
);

/*
 * A REDIRECT CHAIN IS COUNTED, AND A LOOP IS NOT FOLLOWED FOR EVER.
 */
$solseo_map = array(
	'/a/' => '/b/',
	'/b/' => '/c/',
	'/c/' => '/d/',
);

solseo_assert_same(
	array( '/a/', '/b/', '/c/', '/d/' ),
	Checker::hops( '/a/', $solseo_map, 10 ),
	'a chain is followed to the end'
);

solseo_assert_same(
	array( '/a/', '/b/' ),
	Checker::hops( '/a/', $solseo_map, 1 ),
	'and only as far as it was asked to go'
);

solseo_assert_same(
	array( '/x/', '/y/' ),
	Checker::hops(
		'/x/',
		array(
			'/x/' => '/y/',
			'/y/' => '/x/',
		),
		10
	),
	'a loop stops the first time it comes back to something already seen'
);

solseo_assert_same(
	array( '/nowhere/' ),
	Checker::hops( '/nowhere/', $solseo_map, 10 ),
	'and an address that redirects nowhere is a chain of one'
);

/*
 * AND A CHAIN IS ONLY WORTH REPORTING WHEN IT IS MORE THAN ONE HOP.
 *
 * One redirect is a redirect. Two is the thing that loses a bit of the link
 * and a bit of the speed every time, and it is the only one worth a row.
 */
solseo_assert( ! Checker::worth_saying( array( '/a/', '/b/' ) ), 'one hop is just a redirect' );
solseo_assert( Checker::worth_saying( array( '/a/', '/b/', '/c/' ) ), 'two hops is a chain' );
solseo_assert( ! Checker::worth_saying( array( '/a/' ) ), 'and no hops is nothing at all' );

/*
 * AND THE RULES ARE READ IN THE SHAPE THEY ARRIVE IN.
 *
 * Manager::all() passes ARRAY_A, so a rule is an associative array. Reading it
 * as an object produced a map of empty strings, silently, and the chain report
 * still had rows in it because the crawl's own recorded redirects filled the
 * gap. The whole rules half was dead and nothing anywhere said so. Found on a
 * real WordPress, and pinned here in the shape that call really returns.
 */
preg_match(
	'#function all\(.*?\n\t\}#s',
	(string) file_get_contents( SOLSEO_PATH . 'includes/redirects/class-manager.php' ),
	$solseo_all
);

solseo_assert(
	isset( $solseo_all[0] ) && false !== strpos( $solseo_all[0], 'ARRAY_A' ),
	'Manager::all still answers with associative arrays'
);

$solseo_rules = array(
	array(
		'source'      => '/one/',
		'target'      => '/two/',
		'match_type'  => 'exact',
		'enabled'     => '1',
		'status_code' => '301',
	),
	array(
		'source'      => '/two/',
		'target'      => 'https://example.test/three/',
		'match_type'  => 'exact',
		'enabled'     => '1',
		'status_code' => '301',
	),
	array(
		'source'      => '/off/',
		'target'      => '/somewhere/',
		'match_type'  => 'exact',
		'enabled'     => '0',
		'status_code' => '301',
	),
	array(
		'source'      => '^/old/(.*)',
		'target'      => '/new/$1',
		'match_type'  => 'regex',
		'enabled'     => '1',
		'status_code' => '301',
	),
	array(
		'source'      => '/gone/',
		'target'      => '',
		'match_type'  => 'exact',
		'enabled'     => '1',
		'status_code' => '410',
	),
);

solseo_assert_same(
	array(
		'/one' => '/two',
		'/two' => '/three',
	),
	Checker::rules_to_map( $solseo_rules ),
	'the rules read as a map, with a rule that is off, a pattern and a rule to nowhere all left out'
);

solseo_assert_same(
	array( '/one', '/two', '/three' ),
	Checker::hops( '/one', Checker::rules_to_map( $solseo_rules ), 5 ),
	'and two rules in a row read as one chain of two hops'
);

/*
 * THE PAGE BEING WRITTEN IS CHECKED WHERE IT IS BEING WRITTEN, AND STILL
 * WITHOUT A REQUEST.
 *
 * The editor asks the score route, the route asks this, and this asks
 * WordPress. Nothing here goes near a socket, which is what lets it keep up
 * with typing.
 */
solseo_test_http_reset();

$GLOBALS['solseo_test_urls'] = array( 'https://example.test/about' => 7 );

$solseo_written = '<p>See our <a href="/about/">about page</a>, our <a href="/gone/">old offer</a>, '
	. '<a href="https://elsewhere.test/x/">somebody else</a> and <a href="/wp-content/uploads/a.pdf">the price list</a>.</p>'
	. '<p>And <a href="/gone/">the same dead one again</a>.</p>';

$solseo_live = Checker::in_content( $solseo_written );

solseo_assert_same( 1, count( $solseo_live ), 'the editor is told about the one address that resolves to nothing' );
solseo_assert_same( '/gone', $solseo_live[0]['path'], 'and it is the right one' );
solseo_assert_same( '/gone/', $solseo_live[0]['href'], 'kept as it was written, because that is what the writer will look for' );
solseo_assert_same( 'old offer', $solseo_live[0]['text'], 'with the words it was written on' );

solseo_assert_same( array(), solseo_test_http_sent(), 'and checking the page being written opened no connection' );

$solseo_anchors = Checker::anchors( '<p><a href="/a/">one</a> <a href=\'/b/\' class="x"><em>two</em> three</a></p>' );

solseo_assert_same(
	array(
		array(
			'href' => '/a/',
			'text' => 'one',
		),
		array(
			'href' => '/b/',
			'text' => 'two three',
		),
	),
	$solseo_anchors,
	'the anchors come back with their words, tags and all taken off'
);

/*
 * AND THE ROUTE SENDS WHAT THE SIDEBAR READS.
 *
 * D-80.8: the checklist read analysis.checks, the route sent groups, and the
 * result was a clean bill of health on a page with nine things wrong with it.
 * It shipped that way because there is no JavaScript test runner here, so the
 * contract is pinned from the PHP side instead. Comments are stripped before
 * matching, because a note explaining the shape is not reading it.
 */
$solseo_route = (string) file_get_contents( SOLSEO_PATH . 'includes/class-rest.php' );

solseo_assert(
	false !== strpos( $solseo_route, "'links'       => Checker::in_content(" ),
	'the score route sends the dead links it found'
);

$solseo_sidebar = (string) preg_replace(
	'#/\*.*?\*/|//[^\n]*#s',
	' ',
	(string) file_get_contents( SOLSEO_PATH . 'assets/js/editor-sidebar.js' )
);

solseo_assert(
	false !== strpos( $solseo_sidebar, 'analysis.links' ),
	'and the sidebar reads that key and not one beside it'
);

foreach ( array( 'deadLinks', 'deadLinksHelp', 'deadLinkNoText' ) as $solseo_key ) {
	solseo_assert(
		false !== strpos( $solseo_sidebar, 'strings.' . $solseo_key ),
		'the sidebar uses the ' . $solseo_key . ' string'
	);

	solseo_assert(
		false !== strpos( (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-editor-assets.php' ), "'" . $solseo_key . "'" ),
		'and the editor sends it, so it is not an undefined in somebody\'s panel'
	);
}
