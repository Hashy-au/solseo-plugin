<?php
/**
 * Plugin Name: SolSEO review harness
 *
 * Records every address anything asked for, and answers none of them.
 *
 * Networking is off in blueprint-review.json, so a request that got out would
 * hang rather than fail. This makes "nothing left the building" a thing the
 * probe asserts with a list rather than assumes from the absence of a crash,
 * which is what guideline 7 is about: activation must not contact a server.
 *
 * An mu-plugin loads before any ordinary plugin, so the filter is in place
 * before the free plugin has decided anything.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['solseo_review_asked'] = array();

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$GLOBALS['solseo_review_asked'][] = ( isset( $args['method'] ) ? $args['method'] : 'GET' ) . ' ' . $url;

		return new WP_Error( 'solseo_review_blocked', 'Nothing may leave during this run: ' . $url );
	},
	10,
	3
);
