<?php
/**
 * The full SolSEO panel: every section, in one widget.
 *
 * THIS FILE DRAWS NOTHING ITSELF. Each section is a partial, and each partial
 * is also a dashboard widget of its own that somebody can switch on in Screen
 * Options. Two copies of the same markup is how the standalone widget and the
 * panel would start saying different things.
 *
 * NARROW BY DEFAULT. A dashboard widget is about 250px wide in the four column
 * layout, so nothing in any partial is a table with three columns in it.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.
?>
<div class="solseo-widget">
	<?php
	foreach ( array( 'score', 'checks', 'site', 'monitor', 'pages', 'footer' ) as $section ) {
		\SolSEO\Admin\Screen::view( 'widget-' . $section, $data );
	}
	?>
</div>
