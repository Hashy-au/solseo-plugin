<?php
/**
 * Six widgets, one of them on, and the other five stay off until asked for.
 *
 * WHY THIS IS A GUARD. The switch is WordPress's own Screen Options, which
 * means the plugin does not own the checkbox and cannot see it. What the plugin
 * owns is the default, and the default is the thing that goes wrong quietly:
 * `default_hidden_meta_boxes` only runs for a user who has never saved a
 * dashboard layout, so on every account that has ever dragged a widget, five
 * new panels would appear unasked. `newly_offered()` is the arithmetic that
 * stops it, and this is what proves it.
 *
 * The wiring around it, which needs a dashboard, a user and a database, is
 * proved in tests/playground/run-widget.sh against a real WordPress.
 *
 * @package SolSEO
 */

$solseo_switch_widgets = \SolSEO\Admin\Dashboard_Widget::widgets();
$solseo_switch_extras  = \SolSEO\Admin\Dashboard_Widget::extras();

solseo_assert_same(
	6,
	count( $solseo_switch_widgets ),
	'the plugin offers the full panel and five sections of it'
);

solseo_assert(
	isset( $solseo_switch_widgets[ \SolSEO\Admin\Dashboard_Widget::PANEL ] ),
	'the full panel is one of them'
);

solseo_assert(
	! in_array( \SolSEO\Admin\Dashboard_Widget::PANEL, $solseo_switch_extras, true ),
	'and the full panel is never one of the extras, so nothing can hide it'
);

solseo_assert_same(
	5,
	count( $solseo_switch_extras ),
	'which leaves five extras'
);

foreach ( $solseo_switch_widgets as $solseo_switch_id => $solseo_switch_widget ) {
	solseo_assert(
		0 === strpos( $solseo_switch_id, 'solseo_' ),
		'every widget id is prefixed: ' . $solseo_switch_id
	);

	solseo_assert(
		0 === strpos( $solseo_switch_widget['title'], 'SolSEO' ),
		'and every title says whose widget it is: ' . $solseo_switch_widget['title']
	);

	if ( 'panel' === $solseo_switch_widget['section'] ) {
		continue;
	}

	solseo_assert(
		is_readable( SOLSEO_PATH . 'includes/admin/views/widget-' . $solseo_switch_widget['section'] . '.php' ),
		'every section has a view of its own: ' . $solseo_switch_widget['section']
	);
}

/*
 * THE PANEL DRAWS EVERY SECTION. A widget somebody switches on and a section of
 * the panel are the same file, so a section added to the list and left out of
 * the panel would be a feature only findable through Screen Options.
 */
$solseo_switch_panel = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/views/dashboard-widget.php' );

foreach ( $solseo_switch_widgets as $solseo_switch_widget ) {
	if ( 'panel' === $solseo_switch_widget['section'] ) {
		continue;
	}

	solseo_assert(
		false !== strpos( $solseo_switch_panel, "'" . $solseo_switch_widget['section'] . "'" ),
		'the full panel draws the ' . $solseo_switch_widget['section'] . ' section too'
	);
}

// ---- What a user who has never met them gets --------------------------------

solseo_assert_same(
	array( 'solseo_score', 'solseo_checks' ),
	\SolSEO\Admin\Dashboard_Widget::newly_offered(
		array( 'solseo_overview', 'solseo_score', 'solseo_checks' ),
		array()
	),
	'a widget nobody has been offered starts hidden, and the full panel never does'
);

solseo_assert_same(
	array(),
	\SolSEO\Admin\Dashboard_Widget::newly_offered(
		array( 'solseo_overview', 'solseo_score', 'solseo_checks' ),
		array( 'solseo_score', 'solseo_checks' )
	),
	'and once a user has been offered a widget, nothing hides it again behind their back'
);

/*
 * THE CASE THE SEEN LIST EXISTS FOR. Monitoring could not be offered on the day
 * the others were, because nothing was watching the site yet. It is new on the
 * day it can be offered, so it is hidden then rather than appearing unasked.
 */
solseo_assert_same(
	array( 'solseo_monitor' ),
	\SolSEO\Admin\Dashboard_Widget::newly_offered(
		array( 'solseo_score', 'solseo_checks', 'solseo_monitor' ),
		array( 'solseo_score', 'solseo_checks' )
	),
	'a widget that could not be offered before is new on the day it can be'
);

solseo_assert_same(
	array(),
	\SolSEO\Admin\Dashboard_Widget::newly_offered( array( 'solseo_overview' ), array() ),
	'offering nothing but the full panel hides nothing'
);

solseo_assert_same(
	array(),
	\SolSEO\Admin\Dashboard_Widget::newly_offered( array( 'some_other_plugin_widget' ), array() ),
	'and another plugin\'s widget is not this plugin\'s to hide'
);

// ---- And nothing at all happens on any other screen -------------------------

solseo_assert_same(
	array( 'kept' ),
	\SolSEO\Admin\Dashboard_Widget::hide_new_ones( array( 'kept' ), new SolSEO_Test_Screen( 'edit-post' ) ),
	'the filter leaves every screen but the dashboard exactly as it found it'
);

solseo_assert_same(
	array( 'kept' ),
	\SolSEO\Admin\Dashboard_Widget::hide_new_ones( array( 'kept' ), null ),
	'and a filter called with no screen at all changes nothing'
);
