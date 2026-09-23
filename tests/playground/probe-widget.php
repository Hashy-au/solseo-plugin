<?php
/**
 * What a real WordPress says about the dashboard widgets.
 *
 * The unit suite proves the choice of site and the hiding arithmetic against a
 * fake WordPress. This proves the drawing and the wiring, which is the risk:
 * the widgets are registered by a hook that only fires on the Dashboard, they
 * are hidden by a filter that reads a user option, and the views call half a
 * dozen functions that only exist once wp-admin has loaded.
 *
 * Run by tests/playground/run-widget.sh. Not shipped.
 *
 * @package SolSEO
 */

// phpcs:disable

$out  = array();
$pass = 0;
$fail = 0;

function probe( $name, $ok, $detail = '' ) {
	global $out, $pass, $fail;

	if ( $ok ) {
		$pass++;
		$out[] = 'PASS ' . $name;
		return;
	}

	$fail++;
	$out[] = 'FAIL ' . $name . ( $detail ? ' :: ' . $detail : '' );
}

require_once '/wordpress/wp-load.php';

/*
 * A DASHBOARD WIDGET ONLY EVER DRAWS INSIDE wp-admin. wp_add_dashboard_widget()
 * lives in wp-admin/includes/dashboard.php, and the views reach for the admin
 * template helpers, so the same includes the Dashboard itself does are loaded
 * before anything is registered or rendered.
 */
require_once '/wordpress/wp-admin/includes/template.php';
require_once '/wordpress/wp-admin/includes/class-wp-screen.php';
require_once '/wordpress/wp-admin/includes/screen.php';
require_once '/wordpress/wp-admin/includes/plugin.php';
require_once '/wordpress/wp-admin/includes/dashboard.php';

wp_set_current_user( 1 );

probe( 'the free plugin is loaded', class_exists( '\SolSEO\Admin\Dashboard_Widget' ) );

/*
 * THE HOOKS ARE PUT ON HERE, because the admin half of the plugin only hooks
 * itself up when is_admin() is true and a runPHP step is not an admin request.
 * init() is called rather than the filter added by hand, so that "the hiding
 * filter is actually wired up" is a thing this probe asserts rather than
 * arranges.
 */
\SolSEO\Admin\Dashboard_Widget::init();

probe(
	'init puts the hiding filter on',
	false !== has_filter( 'hidden_meta_boxes', array( 'SolSEO\Admin\Dashboard_Widget', 'hide_new_ones' ) )
);
probe(
	'and registers the widgets on the hook the Dashboard fires',
	false !== has_action( 'wp_dashboard_setup', array( 'SolSEO\Admin\Dashboard_Widget', 'register' ) )
);

/**
 * Register the widgets afresh and hand back the ids WordPress now holds.
 *
 * register() IS CALLED RATHER THAN THE HOOK FIRED. The admin half of the plugin
 * only hooks itself up when is_admin() is true, and a runPHP step is not an
 * admin request however many wp-admin includes it loads. Firing
 * wp_dashboard_setup here would assert that this probe is not wp-admin, which
 * is true and worth nothing.
 *
 * AND THE SCREEN HAS TO BE THE DASHBOARD. wp_add_dashboard_widget() hands
 * get_current_screen() to add_meta_box(), so on any other screen the boxes are
 * filed under that screen's id and this probe would be looking in an empty box.
 */
function probe_register() {
	$GLOBALS['wp_meta_boxes'] = array();

	set_current_screen( 'dashboard' );

	\SolSEO\Admin\Dashboard_Widget::forget();
	\SolSEO\Admin\Dashboard_Widget::register();

	$ids = array();

	foreach ( (array) ( $GLOBALS['wp_meta_boxes']['dashboard']['normal'] ?? array() ) as $priority ) {
		foreach ( (array) $priority as $id => $box ) {
			$ids[] = $id;
		}
	}

	return $ids;
}

/**
 * Draw one widget and hand back the markup.
 */
function probe_widget_html( $section = 'panel' ) {
	ob_start();
	\SolSEO\Admin\Dashboard_Widget::draw( $section );

	return (string) ob_get_clean();
}

/* ---- a site that has never connected to anything ---- */

$fresh_ids = probe_register();

probe( 'an unconnected site is offered the full panel', in_array( 'solseo_overview', $fresh_ids, true ), implode( ',', $fresh_ids ) );
probe( 'and the content score panel', in_array( 'solseo_score', $fresh_ids, true ) );
probe( 'and the one about what needs work', in_array( 'solseo_checks', $fresh_ids, true ) );
probe( 'and is not offered a site panel it could not fill', ! in_array( 'solseo_site', $fresh_ids, true ) );
probe( 'and is not offered a monitoring panel either', ! in_array( 'solseo_monitor', $fresh_ids, true ) );

$fresh = probe_widget_html();

probe( 'the panel draws', false !== strpos( $fresh, 'solseo-widget' ), substr( $fresh, 0, 200 ) );
probe( 'and the score dial is in it', false !== strpos( $fresh, 'solseo-dial' ) );
probe( 'and the counts are in it', false !== strpos( $fresh, 'solseo-widget-stats' ) );
probe( 'and it offers to connect an account', false !== strpos( $fresh, 'tab=connections' ) );
probe( 'and it draws no site rows at all', false === strpos( $fresh, 'solseo-widget-rows' ) );

/* ---- the extras start switched off, for a user with a saved layout too ---- */

delete_user_option( 1, 'solseo_widgets_seen' );
update_user_option( 1, 'metaboxhidden_dashboard', array( 'dashboard_quick_press' ) );

$hidden = get_hidden_meta_boxes( get_current_screen() );

probe( 'the full panel is what a site gets to begin with', ! in_array( 'solseo_overview', $hidden, true ), implode( ',', $hidden ) );
probe( 'the content score panel starts switched off', in_array( 'solseo_score', $hidden, true ), implode( ',', $hidden ) );
probe( 'so does the one about what needs work', in_array( 'solseo_checks', $hidden, true ) );
probe( 'and a widget the user had hidden is left hidden', in_array( 'dashboard_quick_press', $hidden, true ) );

$seen = get_user_option( 'solseo_widgets_seen' );

probe( 'the widgets offered are recorded against the user', is_array( $seen ) && in_array( 'solseo_score', $seen, true ), wp_json_encode( $seen ) );
probe( 'and one that could not be offered is not', is_array( $seen ) && ! in_array( 'solseo_monitor', $seen, true ), wp_json_encode( $seen ) );

/*
 * THE SECOND LOAD IS THE ONE THAT MATTERS. Having been offered the panels once,
 * the user's own choice is the only thing that decides, so nothing is hidden
 * again behind their back.
 */
update_user_option( 1, 'metaboxhidden_dashboard', array( 'solseo_checks' ) );

$hidden_again = get_hidden_meta_boxes( get_current_screen() );

probe( 'a panel the user switched on stays on', ! in_array( 'solseo_score', $hidden_again, true ), implode( ',', $hidden_again ) );
probe( 'and one they left off stays off', in_array( 'solseo_checks', $hidden_again, true ) );

/* ---- connected, and the service answered with three sites ---- */

update_option(
	'solseo_hub',
	array(
		'key'     => 'solseo_pk_probe',
		'site_id' => 12,
		'url'     => 'https://solseo.com.au',
		'plan'    => 'agency',
		'level'   => 'plus',
	),
	false
);

set_transient(
	'solseo_hub_overview',
	array(
		'dashboard_url' => 'https://solseo.com.au/app',
		'sites'         => array(
			array(
				'site_id'               => 11,
				'name'                  => 'Somebody Else Pty Ltd',
				'home_url'              => 'https://somebody-else.example',
				'health_score'          => 71,
				'previous_health_score' => 71,
				'keywords'              => 18,
				'last_crawl_at'         => gmdate( 'c', time() - DAY_IN_SECONDS ),
				'is_this_site'          => false,
			),
			array(
				'site_id'               => 12,
				'name'                  => 'The Site This Plugin Is On',
				'home_url'              => home_url(),
				'health_score'          => 84,
				'previous_health_score' => 74,
				'keywords'              => 41,
				'last_crawl_at'         => gmdate( 'c', time() - ( 2 * DAY_IN_SECONDS ) ),
				'is_this_site'          => true,
			),
			array(
				'site_id'               => 13,
				'name'                  => 'A Third Business',
				'home_url'              => 'https://third-business.example',
				'health_score'          => 40,
				'previous_health_score' => 55,
				'keywords'              => 7,
				'last_crawl_at'         => null,
				'is_this_site'          => false,
			),
		),
		'monitor'       => array(
			'watched'       => true,
			'up'            => true,
			'ms'            => 412,
			'ssl_days_left' => 61,
			'sitemap_url'   => 'https://example.com/wp-sitemap.xml',
			'sitemap_ok'    => true,
			'index_blocked' => false,
		),
	),
	HOUR_IN_SECONDS
);

$connected_ids = probe_register();

probe( 'a connected site is offered the site panel', in_array( 'solseo_site', $connected_ids, true ), implode( ',', $connected_ids ) );
probe( 'and the monitoring panel', in_array( 'solseo_monitor', $connected_ids, true ) );

$hidden_connected = get_hidden_meta_boxes( get_current_screen() );

probe( 'the monitoring panel is new, so it starts switched off', in_array( 'solseo_monitor', $hidden_connected, true ), implode( ',', $hidden_connected ) );
probe( 'and the panel the user had switched on is still on', ! in_array( 'solseo_score', $hidden_connected, true ) );

$connected = probe_widget_html();

/*
 * THE WHOLE POINT. Three sites went in and one comes out, and the two that are
 * somebody else's business appear nowhere in the markup, by name or by address.
 */
probe( 'a connected site draws its own name', false !== strpos( $connected, 'The Site This Plugin Is On' ) );
probe( 'and not the second site on the account', false === strpos( $connected, 'Somebody Else' ) );
probe( 'and not the third', false === strpos( $connected, 'A Third Business' ) );
probe( 'and neither of their addresses', false === strpos( $connected, 'somebody-else.example' ) && false === strpos( $connected, 'third-business.example' ) );

probe( 'the health score is drawn', false !== strpos( $connected, '>84<' ), 'no 84 pill' );
probe( 'and the change since the last audit is named', false !== strpos( $connected, 'up 10' ) );
probe( 'the keyword count is drawn', false !== strpos( $connected, '>41<' ) );
probe( 'the last audit is dated in words', false !== strpos( $connected, ' ago' ) );

probe( 'the monitor row says the site is answering', false !== strpos( $connected, '412' ) );
probe( 'and how long the certificate has', false !== strpos( $connected, '61 days left' ) );
probe( 'and that the sitemap works', false !== strpos( $connected, 'working' ) );

/* ---- every section stands on its own, and says nothing new ---- */

foreach ( \SolSEO\Admin\Dashboard_Widget::widgets() as $id => $widget ) {
	if ( 'panel' === $widget['section'] ) {
		continue;
	}

	$alone = probe_widget_html( $widget['section'] );

	probe( 'the ' . $widget['section'] . ' widget draws on its own', false !== strpos( $alone, 'solseo-widget' ), substr( $alone, 0, 120 ) );
	probe(
		'and the ' . $widget['section'] . ' widget names nobody else\'s site',
		false === strpos( $alone, 'Somebody Else' ) && false === strpos( $alone, 'A Third Business' ),
		substr( $alone, 0, 200 )
	);
}

/* ---- a list with nothing in it for this site ---- */

set_transient(
	'solseo_hub_overview',
	array(
		'sites' => array(
			array(
				'site_id'      => 11,
				'name'         => 'Somebody Else Pty Ltd',
				'home_url'     => 'https://somebody-else.example',
				'health_score' => 71,
				'keywords'     => 18,
			),
		),
	),
	HOUR_IN_SECONDS
);

$unmatched_ids = probe_register();

probe( 'a list holding no row for this site is not offered a site panel', ! in_array( 'solseo_site', $unmatched_ids, true ), implode( ',', $unmatched_ids ) );

$unmatched = probe_widget_html();

probe( 'and the full panel draws no site rows', false === strpos( $unmatched, 'solseo-widget-rows' ) );
probe( 'and names nobody', false === strpos( $unmatched, 'Somebody Else' ) );

/* ---- nothing was written to the log while all that was drawn ---- */

$log = file_exists( '/wordpress/wp-content/debug.log' ) ? (string) file_get_contents( '/wordpress/wp-content/debug.log' ) : '';

probe( 'nothing in the widgets wrote a notice or a warning to the log', '' === trim( $log ), substr( $log, 0, 400 ) );

$out[] = $pass . ' passed, ' . $fail . ' failed';

file_put_contents( '/wordpress/review-out/widget.txt', implode( "\n", $out ) . "\n" );
