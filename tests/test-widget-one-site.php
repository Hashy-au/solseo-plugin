<?php
/**
 * The dashboard widget shows this site and no other.
 *
 * WHY THIS IS A GUARD. The widget listed every site on the connected account,
 * because that is what `GET /api/v1/plugin/overview` used to answer with. On an
 * agency account that put the names, addresses and health scores of every
 * client on every other client's wp-admin dashboard. Found by looking at the
 * widget on a client site on 2026-09-20 and reading six other businesses off it.
 *
 * WHAT IT CHECKS. Two things, because either on its own is half a control.
 * First, the choice: `Dashboard_Widget::this_site()` returns the paired row out
 * of a list and nothing when no row is this site, so "the first one" can never
 * creep back in as a fallback. Second, the view: nothing in it loops over a
 * list of sites, so a future hand cannot render the list the hub no longer
 * sends.
 *
 * @package SolSEO
 */

$solseo_widget_sites = array(
	array(
		'site_id'      => 11,
		'name'         => 'Somebody Else Pty Ltd',
		'home_url'     => 'https://somebody-else.example',
		'health_score' => 71,
		'is_this_site' => false,
	),
	array(
		'site_id'      => 12,
		'name'         => 'This Site',
		'home_url'     => 'https://this-site.example',
		'health_score' => 84,
		'is_this_site' => true,
	),
	array(
		'site_id'      => 13,
		'name'         => 'A Third Business',
		'home_url'     => 'https://third.example',
		'health_score' => 40,
		'is_this_site' => false,
	),
);

$solseo_widget_picked = \SolSEO\Admin\Dashboard_Widget::this_site( $solseo_widget_sites, 12, 'https://this-site.example' );

solseo_assert_same(
	'This Site',
	isset( $solseo_widget_picked['name'] ) ? $solseo_widget_picked['name'] : null,
	'the widget picks the row the hub marked as this site'
);

/*
 * A hub that has not been updated yet, or a row whose flag is missing. The
 * stored site id is what pairing wrote down, so it answers on its own.
 */
$solseo_widget_unflagged = array_map(
	function ( $solseo_widget_row ) {
		unset( $solseo_widget_row['is_this_site'] );

		return $solseo_widget_row;
	},
	$solseo_widget_sites
);

solseo_assert_same(
	'This Site',
	\SolSEO\Admin\Dashboard_Widget::this_site( $solseo_widget_unflagged, 12, '' )['name'],
	'with no flag on any row, the stored site id picks this site'
);

solseo_assert_same(
	'This Site',
	\SolSEO\Admin\Dashboard_Widget::this_site( $solseo_widget_unflagged, 0, 'https://this-site.example/' )['name'],
	'with no flag and no stored id, the address picks this site, trailing slash and all'
);

/*
 * THE POINT OF THE WHOLE GUARD. None of the three matched, so the answer is
 * nothing. Returning the first row here is the bug this file exists to stop.
 */
solseo_assert_same(
	array(),
	\SolSEO\Admin\Dashboard_Widget::this_site( $solseo_widget_unflagged, 99, 'https://unrelated.example' ),
	'a list with no row for this site answers with nothing, not with the first row'
);

solseo_assert_same(
	array(),
	\SolSEO\Admin\Dashboard_Widget::this_site( array(), 12, 'https://this-site.example' ),
	'an empty list answers with nothing'
);

solseo_assert_same(
	array(),
	\SolSEO\Admin\Dashboard_Widget::this_site( array( 'not a row', 7 ), 12, 'https://this-site.example' ),
	'rubbish in the list is skipped rather than rendered'
);

// ---- And neither side can render a list of them -----------------------------

/*
 * COMMENTS OUT, STRINGS IN. `solseo_code_only()` takes the strings as well,
 * which would blank the very array keys being looked for, and the prose in both
 * files says the words "sites" and "foreach" while explaining why neither
 * happens. So this strips comments alone.
 */
$solseo_widget_code = function ( $solseo_widget_path ) {
	$solseo_widget_out = '';

	foreach ( token_get_all( (string) file_get_contents( $solseo_widget_path ) ) as $solseo_widget_token ) {
		if ( ! is_array( $solseo_widget_token ) ) {
			$solseo_widget_out .= $solseo_widget_token;

			continue;
		}

		$solseo_widget_out .= in_array( $solseo_widget_token[0], array( T_COMMENT, T_DOC_COMMENT ), true )
			? ' '
			: $solseo_widget_token[1];
	}

	return $solseo_widget_out;
};

$solseo_widget_views   = glob( SOLSEO_PATH . 'includes/admin/views/widget-*.php' );
$solseo_widget_views[] = SOLSEO_PATH . 'includes/admin/views/dashboard-widget.php';

solseo_assert(
	count( $solseo_widget_views ) > 5,
	'the widget is drawn by a handful of views, so this guard has them all'
);

foreach ( $solseo_widget_views as $solseo_widget_path ) {
	$solseo_widget_view = $solseo_widget_code( $solseo_widget_path );
	$solseo_widget_name = basename( $solseo_widget_path );

	solseo_assert(
		false === strpos( $solseo_widget_view, "'sites'" ),
		$solseo_widget_name . ' never reads a list of sites'
	);

	solseo_assert(
		0 === preg_match( '/foreach[^)]*\$data\[\s*.sites/', $solseo_widget_view ),
		'and ' . $solseo_widget_name . ' loops over no list of them'
	);
}

$solseo_widget_class = $solseo_widget_code( SOLSEO_PATH . 'includes/admin/class-dashboard-widget.php' );

solseo_assert(
	false !== strpos( $solseo_widget_class, "'site'      => self::site()" ),
	'the widget hands the view one site'
);

solseo_assert(
	false !== strpos( $solseo_widget_class, 'self::this_site(' ),
	'and that one site is the one this_site() picked, rather than whatever came first'
);
