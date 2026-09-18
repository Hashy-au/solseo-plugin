<?php
/**
 * The editor, the build step that is not there, and the links index.
 *
 * Each of these pins a decision that would otherwise be undone by somebody
 * being helpful, and four of them pin a failure that is invisible until
 * somebody tries to save.
 *
 * @package SolSEO
 */

/*
 * NO BUILD STEP. The plugin is written to be read: every file in the zip is
 * the file that runs, which is what guideline 4 asks for and what makes the
 * hand written sidebar worth the trouble it costs. One package.json is all it
 * takes to stop that being true.
 */
$solseo_built = array();

foreach ( array( 'package.json', 'package-lock.json', 'webpack.config.js', 'composer.json', 'node_modules', 'vendor' ) as $solseo_thing ) {
	if ( file_exists( SOLSEO_PATH . $solseo_thing ) ) {
		$solseo_built[] = $solseo_thing;
	}
}

foreach ( glob( SOLSEO_PATH . 'assets/*/*.min.*' ) as $solseo_min ) {
	$solseo_built[] = basename( $solseo_min );
}

foreach ( glob( SOLSEO_PATH . 'assets/*/*.map' ) as $solseo_map ) {
	$solseo_built[] = basename( $solseo_map );
}

solseo_assert_same( array(), $solseo_built, 'nothing in the plugin is compiled, minified or installed from a lock file' );

/*
 * EVERY SCRIPT SITS FLAT IN assets/js. The dash guard and the directory rules
 * guard both read assets/js/*.js and nothing below it, so a subdirectory is a
 * way out of both of them without anybody meaning it.
 */
$solseo_nested = glob( SOLSEO_PATH . 'assets/js/*/*.js' );

solseo_assert_same( array(), (array) $solseo_nested, 'every script is flat in assets/js, where the guards can see it' );

/*
 * ONE EDITOR SURFACE, NEVER TWO. Two copies of the same fields on one screen
 * is a bug report, and the only thing standing between here and there is one
 * question asked before the box is registered.
 */
$solseo_metabox = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-metabox.php' );

solseo_assert(
	false !== strpos( $solseo_metabox, 'use_block_editor_for_post' ),
	'the meta box asks which editor is loading before it registers'
);

$solseo_editor_assets = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-editor-assets.php' );

solseo_assert(
	false !== strpos( $solseo_editor_assets, 'is_block_editor' ),
	'and the scripts ask the same question'
);

solseo_assert(
	false !== strpos( $solseo_editor_assets, 'solseo-editor' ) && false !== strpos( $solseo_editor_assets, 'solseo-sidebar' ),
	'and there is a script for each of the two answers'
);

/*
 * THE SIDEBAR STILL WORKS ON THE OLDEST WordPress THIS PLUGIN SUPPORTS. The
 * panel moved house in 6.6 and the old address still answers with a warning,
 * so both are asked for and whichever exists is used. Deleting the fallback
 * because the warning went away breaks 6.4 and 6.5 silently.
 */
$solseo_sidebar = (string) file_get_contents( SOLSEO_PATH . 'assets/js/editor-sidebar.js' );

solseo_assert(
	false !== strpos( $solseo_sidebar, 'wp.editor' ) && false !== strpos( $solseo_sidebar, 'wp.editPost' ),
	'the sidebar looks for the panel in both of the places it has lived'
);

/*
 * THE ADD-ON HAS SOMETHING TO ATTACH TO, AND A WAY TO REFUSE.
 */
$solseo_api = (string) file_get_contents( SOLSEO_PATH . 'assets/js/editor-api.js' );

solseo_assert( false !== strpos( $solseo_api, 'apiVersion' ), 'the panel registry publishes a version an add-on can check' );
solseo_assert( false !== strpos( $solseo_api, 'solseo/editor' ), 'and a store the panels read from' );
solseo_assert( false !== strpos( $solseo_api, 'panels' ), 'and a registry to add a panel to' );

/*
 * AND THE CLASSIC BOX KEEPS ITS OWN WAY IN. The add-on uses these three in the
 * editor that has no sidebar, so tidying them away after the sidebar lands
 * would take the paid panels with them.
 */
$solseo_view = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/views/metabox.php' );

foreach ( array( 'solseo_metabox_tabs', 'solseo_metabox_panels', 'solseo_metabox_after_checks' ) as $solseo_hook ) {
	solseo_assert(
		false !== strpos( $solseo_view, $solseo_hook ),
		'the classic box still offers ' . $solseo_hook
	);
}

/*
 * THE THREE WAYS THE SIDEBAR SHIPS BROKEN AND SILENT.
 *
 * Without custom field support the posts controller leaves meta out of its
 * schema, so every edit goes nowhere and nothing says so. Without a sanitiser
 * the REST write path is the one path in the plugin that cleans nothing,
 * because Meta::save() is not on it. And an auth callback asking whether
 * somebody may edit posts is not asking whether they may edit this one.
 */
$solseo_meta = (string) file_get_contents( SOLSEO_PATH . 'includes/class-meta.php' );

solseo_assert( false !== strpos( $solseo_meta, 'add_post_type_support' ), 'the meta reaches the REST API on every managed post type' );
solseo_assert( false !== strpos( $solseo_meta, 'sanitize_callback' ), 'a REST write is cleaned on the way in' );
solseo_assert( false !== strpos( $solseo_meta, "current_user_can( 'edit_post'" ), 'and it asks about this post, not about posts' );

/*
 * AND THE SCORE IS NOT ONE SAVE BEHIND. The posts controller writes the post
 * and then the meta, so save_post runs before the new title exists. Scoring
 * there gives every block editor site a number that is always one save old,
 * and it looks close enough that nobody checks.
 */
$solseo_keeper = (string) file_get_contents( SOLSEO_PATH . 'includes/class-score-keeper.php' );

solseo_assert(
	false !== strpos( $solseo_keeper, 'rest_after_insert_' ),
	'the block editor is scored after its meta has landed, not before'
);

solseo_assert(
	false !== strpos( $solseo_keeper, 'REST_REQUEST' ),
	'and it is not scored twice'
);

/*
 * SORTING BY SCORE DOES NOT HIDE THE PAGES SOMEBODY IS LOOKING FOR. A plain
 * meta_key sort joins strictly, and every page that has never been scored
 * drops out of the list, which is exactly the set you sort by score to find.
 */
$solseo_columns = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-columns.php' );

solseo_assert( false !== strpos( $solseo_columns, 'NOT EXISTS' ), 'the score sort keeps the pages that have no score' );
solseo_assert( false !== strpos( $solseo_columns, "'relation' => 'OR'" ), 'by asking for them alongside the ones that do' );

/*
 * EDITING FROM THE LIST ASKS ABOUT EVERY ROW IT WRITES.
 */
$solseo_bulk = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-bulk-edit.php' );

solseo_assert( false !== strpos( $solseo_bulk, "current_user_can( 'edit_post', \$post_id )" ), 'every row written is a row this person may edit' );
solseo_assert( false !== strpos( $solseo_bulk, 'MAX_ROWS' ), 'and one request cannot be turned into the whole site' );
solseo_assert( false === strpos( $solseo_bulk, 'admin_notices' ), 'and it prints no notice of its own' );
