<?php
/**
 * Reading somebody else's SEO fields.
 *
 * Every assertion here is a trap that would otherwise be found by a customer's
 * site going wrong a week after they imported, which is the worst possible
 * time and place to find it.
 *
 * @package SolSEO
 */

use SolSEO\Tools\Import;
use SolSEO\Tools\Table_Source;

/*
 * THE THREE WAYS A PLACEHOLDER IS WRITTEN.
 */

solseo_assert_same(
	'{title} {sep} {sitename}',
	Import::translate( '#post_title #separator_sa #site_title', 'hash' ),
	'a template written with hashes is translated'
);

solseo_assert_same(
	'Read more #here about #post_thing',
	Import::translate( 'Read more #here about #post_thing', 'hash' ),
	'a hash that is not one of theirs is left exactly where it is'
);

solseo_assert_same(
	'50% off, guaranteed',
	Import::translate( '50% off, guaranteed', 'none' ),
	'a source that stores finished text is not touched at all'
);

solseo_assert_same(
	'{title} {sep} {sitename}',
	Import::translate( '%%title%% %%sep%% %%sitename%%' ),
	'and the default is still the one the first two sources use'
);

/*
 * THE ROBOTS GATE. A row that says it is using the site defaults has nothing
 * per page to copy, and reading the two columns beside that flag anyway would
 * hide pages that were never set to be hidden.
 */

$solseo_aioseo = Import::definition( 'aioseo_' );

solseo_assert( is_array( $solseo_aioseo ), 'the table backed source is defined' );
solseo_assert_same( 'aioseo_posts', $solseo_aioseo['table'], 'and it names the table it lives in' );

$solseo_row = array(
	'post_id'         => 12,
	'title'           => '#post_title #separator_sa #site_title',
	'description'     => 'A description.',
	'robots_default'  => 1,
	'robots_noindex'  => 1,
	'robots_nofollow' => 1,
);

$solseo_mapped = Table_Source::map_row( $solseo_row, $solseo_aioseo );

solseo_assert_same( '{title} {sep} {sitename}', $solseo_mapped['title'], 'the title comes across translated' );
solseo_assert( ! isset( $solseo_mapped['robots_noindex'] ), 'a row using the site defaults brings no noindex across' );
solseo_assert( ! isset( $solseo_mapped['robots_nofollow'] ), 'and no nofollow either' );

$solseo_row['robots_default'] = 0;

$solseo_mapped = Table_Source::map_row( $solseo_row, $solseo_aioseo );

solseo_assert_same( true, $solseo_mapped['robots_noindex'], 'a row that overrides the defaults does bring noindex across' );
solseo_assert_same( true, $solseo_mapped['robots_nofollow'], 'and nofollow' );

/*
 * THE FOCUS PHRASE IS INSIDE A BLOB OF JSON, and the plain column beside it is
 * not always filled in.
 */

$solseo_row = array(
	'post_id'    => 12,
	'title'      => 'A title',
	'keyphrases' => '{"focus":{"keyphrase":"recurve bow","score":71},"additional":[]}',
);

$solseo_mapped = Table_Source::map_row( $solseo_row, $solseo_aioseo );

solseo_assert_same( 'recurve bow', $solseo_mapped['focus_keyword'], 'the chosen phrase is read out of the JSON' );

$solseo_row = array(
	'post_id'       => 12,
	'title'         => 'A title',
	'focus_keyword' => 'flat bow',
	'keyphrases'    => '',
);

$solseo_mapped = Table_Source::map_row( $solseo_row, $solseo_aioseo );

solseo_assert_same( 'flat bow', $solseo_mapped['focus_keyword'], 'and the plain column is used when the JSON is empty' );

/*
 * AN IMAGE STORED AS AN ADDRESS. The number cast that is right for three of
 * the five sources turns an address into 0, which reads on our side as a
 * chosen image that has since been deleted.
 */

$solseo_row = array(
	'post_id'             => 12,
	'title'               => 'A title',
	'og_image_custom_url' => 'https://example.test/wp-content/uploads/2026/01/bow.jpg',
);

$solseo_mapped = Table_Source::map_row( $solseo_row, $solseo_aioseo );

solseo_assert(
	! isset( $solseo_mapped['og_image'] ),
	'an address that matches nothing in this library is dropped rather than stored as zero'
);

$GLOBALS['solseo_test_attachments']['https://example.test/wp-content/uploads/2026/01/bow.jpg'] = 88;

$solseo_mapped = Table_Source::map_row( $solseo_row, $solseo_aioseo );

solseo_assert_same( 88, $solseo_mapped['og_image'], 'and one that does match comes across as the attachment' );

/*
 * ONE SOURCE MEANS THE OPPOSITE BY YES. Reading these two the obvious way
 * round would take a whole site out of search on import, silently, and the
 * first sign of it would be the traffic.
 */

$solseo_seopress = Import::definition( '_seopress_' );

solseo_assert_same(
	'_seopress_robots_index',
	$solseo_seopress['flags']['robots_noindex'][0],
	'the field that means keep this page out is read as noindex, not as index'
);

solseo_assert_same(
	'_seopress_robots_follow',
	$solseo_seopress['flags']['robots_nofollow'][0],
	'and the one beside it is read as nofollow'
);

/*
 * ONE SOURCE STORES THE TITLE, NOT A TEMPLATE, and adds the site name itself
 * at the point the page is built.
 */

$solseo_tsf = Import::definition( '_genesis_' );

solseo_assert_same( 'none', $solseo_tsf['variables'], 'the source holding finished titles is never translated' );
solseo_assert_same( '_tsf_title_no_blogname', $solseo_tsf['title_suffix'][0], 'and the flag deciding the site name is read' );
solseo_assert_same( ' {sep} {sitename}', $solseo_tsf['title_suffix'][2], 'so a title that was going to gain one still does' );

/*
 * ONE PHRASE, NOT A LIST. Two of the five sources keep several here, and every
 * keyword check in this plugin measures against one.
 */

$solseo_shaped = Import::shape(
	array( 'focus_keyword' => 'recurve bow, flat bow, horse bow' ),
	array( 'variables' => 'percent' )
);

solseo_assert_same( 'recurve bow', $solseo_shaped['focus_keyword'], 'a list of phrases comes across as the first of them' );

/*
 * AND EVERY SOURCE IS READABLE BY SOMETHING.
 */

foreach ( array( '_yoast_wpseo_', 'rank_math_', 'aioseo_', '_seopress_', '_genesis_' ) as $solseo_prefix ) {
	$solseo_source = Import::definition( $solseo_prefix );

	solseo_assert( is_array( $solseo_source ), $solseo_prefix . ' is defined' );
	solseo_assert( ! empty( $solseo_source['plugins'] ), $solseo_prefix . ' names the plugin it belongs to' );
	solseo_assert( class_exists( Import::reader( $solseo_source ) ), $solseo_prefix . ' has a reader that exists' );
}
