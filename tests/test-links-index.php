<?php
/**
 * Working out whether two pages are the same page.
 *
 * Every wrong answer here is a link count that is quietly one out, on a screen
 * somebody is using to decide what to work on next.
 *
 * @package SolSEO
 */

use SolSEO\Content;
use SolSEO\Links\Resolver;

$solseo_home = 'https://example.test';

$solseo_same = array(
	'https://example.test/about/'       => 'an absolute address',
	'http://example.test/about/'        => 'the same address without the certificate',
	'https://www.example.test/about/'   => 'the same address with the www on it',
	'//example.test/about/'             => 'an address that borrows the scheme',
	'/about/'                           => 'a path from the root',
	'/about'                            => 'the same path with no trailing slash',
	'/About/'                           => 'the same path in different case',
	'https://example.test/about/?ref=1' => 'the same page with something on the end of the address',
	'https://example.test/about/#team'  => 'a link to part way down the same page',
);

foreach ( $solseo_same as $solseo_href => $solseo_what ) {
	solseo_assert_same(
		'/about',
		Resolver::normalise( $solseo_href, $solseo_home ),
		$solseo_what . ' is the same page'
	);
}

solseo_assert_same( '/', Resolver::normalise( 'https://example.test/', $solseo_home ), 'the front page is the front page' );
solseo_assert_same( '/', Resolver::normalise( 'https://example.test', $solseo_home ), 'with or without the slash' );

$solseo_not_ours = array(
	'https://example.com/about/' => 'somebody else\'s site',
	'mailto:hello@example.test'  => 'an email address',
	'tel:+61800000000'           => 'a telephone number',
	'#top'                       => 'a jump to the top of this page',
	''                           => 'an empty href',
	'   '                        => 'an href of nothing but spaces',
);

foreach ( $solseo_not_ours as $solseo_href => $solseo_what ) {
	solseo_assert_same(
		'',
		Resolver::normalise( $solseo_href, $solseo_home ),
		$solseo_what . ' is not a link into this site'
	);
}

/*
 * A SITE LIVING IN A SUBDIRECTORY carries that prefix on every link it writes,
 * and two pages that differ only by it are one page.
 */
solseo_assert_same(
	'/about',
	Resolver::normalise( 'https://example.test/shop/about/', 'https://example.test/shop' ),
	'a site in a subdirectory is compared without the subdirectory'
);

solseo_assert_same(
	'/about',
	Resolver::normalise( '/shop/about/', 'https://example.test/shop' ),
	'whether the link was written in full or not'
);

/*
 * AND THE SAME FUNCTION DECIDES INTERNAL FROM EXTERNAL.
 */
solseo_assert( Resolver::is_internal( '/about/', $solseo_home ), 'a path is a link into this site' );
solseo_assert( ! Resolver::is_internal( 'https://example.com/', $solseo_home ), 'and another site is not' );

/*
 * THE LINKS COME OUT OF THE CONTENT THE SAME WAY THE CHECKS READ THEM, so the
 * count in the column and the count the check measured cannot disagree.
 */
$solseo_html = '<p>See <a href="/about/">about</a> and <a href="https://example.test/shop/">the shop</a>,'
	. ' then <a href="https://example.com/">somewhere else</a> and <a href="mailto:x@example.test">email</a>.</p>'
	. '<p><a href="#top">back to top</a></p>';

$solseo_links = Content::links( $solseo_html );

solseo_assert_same( 2, count( $solseo_links['internal'] ), 'two links point into this site' );
solseo_assert_same( 1, count( $solseo_links['external'] ), 'one points out of it' );

$solseo_paths = array();

foreach ( $solseo_links['internal'] as $solseo_href ) {
	$solseo_paths[] = Resolver::normalise( $solseo_href, $solseo_home );
}

solseo_assert_same( array( '/about', '/shop' ), $solseo_paths, 'and both reduce to the page they mean' );
