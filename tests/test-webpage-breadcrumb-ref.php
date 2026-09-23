<?php
/**
 * The WebPage node points at a BreadcrumbList only when one is in the graph.
 *
 * On the front page there is no trail, so no BreadcrumbList is written. The
 * WebPage node still named "#breadcrumb" as its breadcrumb, and a reference to
 * an id nothing else carries is read by Google as a BreadcrumbList with no
 * fields. Search Console reported it on solkarra.com.au on 2026-09-23 as
 * "Missing field itemListElement" against the home page, and the Rich Results
 * Test showed it as an unnamed item with one critical issue.
 *
 * The query conditionals come from test-shop-context.php, which the runner
 * loads first (files load in name order). This file reuses its view global.
 *
 * @package SolSEO
 */

use SolSEO\Context;
use SolSEO\Frontend\Schema;
use SolSEO\Options;

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- WordPress stubs.
if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	function get_post_type_archive_link( $post_type ) {
		return home_url( '/' . $post_type . '/' );
	}
}
// phpcs:enable

/**
 * The nodes of one type in a graph.
 *
 * @param array  $graph Graph.
 * @param string $type  Schema type.
 * @return array
 */
function solseo_test_nodes_of( array $graph, $type ) {
	return array_values(
		array_filter(
			$graph,
			function ( $node ) use ( $type ) {
				return is_array( $node ) && isset( $node['@type'] ) && $type === $node['@type'];
			}
		)
	);
}

Options::update( array( 'breadcrumbs_enabled' => true ) );
update_option( 'page_on_front', 78 );

/*
 * The front page: no trail, so no BreadcrumbList, so no reference to one.
 */
$GLOBALS['solseo_test_view'] = 'front_page';
Context::reset();
$graph      = Schema::graph();
$page_nodes = solseo_test_nodes_of( $graph, 'WebPage' );

solseo_assert( 1 === count( $page_nodes ), 'The front page graph has one WebPage node' );
solseo_assert( empty( solseo_test_nodes_of( $graph, 'BreadcrumbList' ) ), 'The front page carries no BreadcrumbList' );
solseo_assert( ! isset( $page_nodes[0]['breadcrumb'] ), 'so its WebPage names no breadcrumb' );

/*
 * A view with a trail: the reference and the node it names are both there,
 * under the same id.
 */
$GLOBALS['solseo_test_view'] = 'shop';
Context::reset();
$graph      = Schema::graph();
$page_nodes = solseo_test_nodes_of( $graph, 'WebPage' );
$trails     = solseo_test_nodes_of( $graph, 'BreadcrumbList' );

solseo_assert( 1 === count( $trails ), 'The shop graph has a BreadcrumbList' );
solseo_assert( isset( $trails[0]['itemListElement'] ) && count( $trails[0]['itemListElement'] ) >= 2, 'with at least two items in it' );
solseo_assert( isset( $page_nodes[0]['breadcrumb']['@id'] ), 'and its WebPage points at a breadcrumb' );
solseo_assert_same(
	isset( $trails[0]['@id'] ) ? $trails[0]['@id'] : '',
	isset( $page_nodes[0]['breadcrumb']['@id'] ) ? $page_nodes[0]['breadcrumb']['@id'] : null,
	'the id the WebPage names is the id the BreadcrumbList carries'
);

/*
 * Breadcrumbs switched off: no node and no reference, on any view.
 */
Options::update( array( 'breadcrumbs_enabled' => false ) );
Context::reset();
$graph      = Schema::graph();
$page_nodes = solseo_test_nodes_of( $graph, 'WebPage' );

solseo_assert( empty( solseo_test_nodes_of( $graph, 'BreadcrumbList' ) ), 'Breadcrumbs off means no BreadcrumbList' );
solseo_assert( ! isset( $page_nodes[0]['breadcrumb'] ), 'and no WebPage reference to one' );

Options::update( array( 'breadcrumbs_enabled' => true ) );
$GLOBALS['solseo_test_view'] = 'other';
Context::reset();
