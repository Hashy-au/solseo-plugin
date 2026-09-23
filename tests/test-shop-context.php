<?php
/**
 * The WooCommerce shop archive reads the shop page's own meta.
 *
 * WooCommerce draws its product archive at the address of a real page, and
 * that page has our meta box. The query calls it an archive, so before this
 * the title and description typed on the page were never read and the shop
 * printed "Shop - Sitename" whatever the editor said. Found on solkarra.com.au
 * on 2026-09-22: the shop page carried a title and description through the
 * connector and the live head showed neither.
 *
 * @package SolSEO
 */

use SolSEO\Context;
use SolSEO\Frontend\Head;

/*
 * The query conditionals, none of which the bootstrap defines. One global says
 * which view is being drawn; everything else answers false.
 */
$GLOBALS['solseo_test_view'] = 'other';

/**
 * Whether the named view is the one under test.
 *
 * @param string $view View name.
 * @return bool
 */
function solseo_test_view_is( $view ) {
	return $GLOBALS['solseo_test_view'] === $view;
}

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- WordPress and WooCommerce stubs.
function is_front_page() {
	return solseo_test_view_is( 'front_page' );
}
function is_home() {
	return solseo_test_view_is( 'blog_home' );
}
function is_singular() {
	return solseo_test_view_is( 'singular' );
}
function is_category() {
	return false;
}
function is_tag() {
	return false;
}
function is_tax() {
	return false;
}
function is_post_type_archive() {
	return in_array( $GLOBALS['solseo_test_view'], array( 'shop', 'archive' ), true );
}
function is_author() {
	return false;
}
function is_search() {
	return false;
}
function is_404() {
	return false;
}
function is_date() {
	return false;
}
function is_shop() {
	return solseo_test_view_is( 'shop' );
}
function wc_get_page_id( $page ) {
	return 'shop' === $page ? (int) $GLOBALS['solseo_test_shop_page'] : -1;
}
function get_queried_object() {
	return null;
}
function post_type_archive_title( $prefix = '', $display = true ) {
	return 'Shop';
}
function get_the_title( $post_id = 0 ) {
	return 214 === (int) $post_id ? 'Shop' : '';
}
// phpcs:enable

$GLOBALS['solseo_test_shop_page'] = 214;

/*
 * The shop archive resolves to the shop page.
 */
$GLOBALS['solseo_test_view'] = 'shop';
Context::reset();
$context = Context::current();

solseo_assert_same( 'post_type_archive', $context['type'], 'The shop is still an archive to everything that asks the type' );
solseo_assert_same( 214, $context['object_id'], 'The shop archive carries the shop page id' );

/*
 * Another post type's archive carries no page, because WooCommerce is not in it.
 */
$GLOBALS['solseo_test_view'] = 'archive';
Context::reset();
$context = Context::current();

solseo_assert_same( 'post_type_archive', $context['type'], 'A plain post type archive is still an archive' );
solseo_assert_same( 0, $context['object_id'], 'A plain post type archive has no page behind it' );

/*
 * A shop with no page picked in WooCommerce's settings is a plain archive.
 */
$GLOBALS['solseo_test_view']      = 'shop';
$GLOBALS['solseo_test_shop_page'] = -1;
Context::reset();

solseo_assert_same( 0, Context::current()['object_id'], 'No shop page chosen means no page id, not -1' );

$GLOBALS['solseo_test_shop_page'] = 214;

/*
 * The title and description typed on the shop page are what the archive prints.
 */
update_post_meta( 214, '_solseo_title', 'Shop Solkarra Stores: Archery, Aromatics and Prints' );
update_post_meta( 214, '_solseo_description', 'Five storefronts, one shed in WA.' );
Context::reset();

solseo_assert_same(
	'Shop Solkarra Stores: Archery, Aromatics and Prints',
	Head::title(),
	'The shop archive prints the title stored on the shop page'
);
solseo_assert_same(
	'Five storefronts, one shed in WA.',
	Head::description(),
	'The shop archive prints the description stored on the shop page'
);

/*
 * A noindex ticked on the shop page reaches the robots tag. Before the page
 * was carried, the tick was stored and never read, so a shop somebody had
 * hidden stayed in the index.
 */
update_post_meta( 214, '_solseo_robots_noindex', '1' );
Context::reset();
$robots = Head::robots(
	array(
		'index'  => true,
		'follow' => true,
	)
);

solseo_assert( ! empty( $robots['noindex'] ) && ! isset( $robots['index'] ), 'The shop archive honours a noindex stored on the shop page' );
delete_post_meta( 214, '_solseo_robots_noindex' );

/*
 * With nothing typed, the archive template still runs, so nothing that worked
 * before has changed.
 */
delete_post_meta( 214, '_solseo_title' );
delete_post_meta( 214, '_solseo_description' );
Context::reset();

solseo_assert( false !== strpos( Head::title(), 'Shop' ), 'An untitled shop page falls back to the archive template' );

$GLOBALS['solseo_test_view'] = 'other';
Context::reset();
