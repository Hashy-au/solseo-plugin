<?php
/**
 * Robots: what is composed, what is kept, and what the presets say.
 *
 * @package SolSEO
 */

use SolSEO\Admin\Robots_Tab;
use SolSEO\Frontend\Robots_Txt;

/*
 * THE LINES WordPress WRITES. This is the one thing in the plugin that is a
 * copy of something in core, so it is pinned. If core changes what it writes,
 * this fails rather than the preview quietly telling somebody the wrong thing.
 */

solseo_assert_same(
	"User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n",
	Robots_Txt::wordpress_default(),
	'the lines WordPress writes are what we say they are'
);

$solseo_default = Robots_Txt::wordpress_default();

/*
 * RULES MEANT FOR EVERYBODY JOIN THE GROUP THAT IS ALREADY THERE. Two groups
 * naming the star are not defined behaviour: some crawlers join them, some
 * take one and drop the rest, and the site owner does not get to choose which.
 */

$solseo_merged = Robots_Txt::compose( $solseo_default, "User-agent: *\nDisallow: /private/\n" );

solseo_assert_same(
	1,
	substr_count( $solseo_merged, 'User-agent: *' ),
	'a second group naming everybody is folded into the first'
);

solseo_assert(
	false !== strpos( $solseo_merged, 'Disallow: /private/' ),
	'and its rules survive the folding'
);

solseo_assert(
	false !== strpos( $solseo_merged, 'Allow: /wp-admin/admin-ajax.php' ),
	'along with the ones WordPress wrote'
);

/*
 * LINES WITH NO GROUP OF THEIR OWN BELONG TO EVERYBODY, which is what makes
 * the shop preset correct rather than correct-if-you-are-Google.
 */

$solseo_loose = Robots_Txt::compose( $solseo_default, "Disallow: /*?orderby=\n" );

solseo_assert_same(
	1,
	substr_count( $solseo_loose, 'User-agent:' ),
	'rules typed without a group do not invent one'
);

solseo_assert(
	false !== strpos( $solseo_loose, 'Disallow: /*?orderby=' ),
	'and they are served'
);

/*
 * A GROUP NAMING PARTICULAR CRAWLERS STAYS ITS OWN GROUP.
 */

$solseo_named = Robots_Txt::compose( $solseo_default, "User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /\n" );

solseo_assert(
	false !== strpos( $solseo_named, "User-agent: GPTBot\nUser-agent: CCBot" ),
	'two crawlers named together stay one group'
);

solseo_assert_same(
	1,
	substr_count( $solseo_named, 'User-agent: *' ),
	'and the group for everybody is still there once'
);

/*
 * SITEMAP LINES BELONG AT THE END, ONCE EACH.
 */

$solseo_maps = Robots_Txt::compose(
	$solseo_default,
	"Sitemap: https://example.test/one.xml\nDisallow: /private/\nSitemap: https://example.test/one.xml\n"
);

solseo_assert_same(
	1,
	substr_count( $solseo_maps, 'Sitemap: https://example.test/one.xml' ),
	'the same sitemap line is not served twice'
);

solseo_assert(
	strpos( $solseo_maps, 'Sitemap:' ) > strpos( $solseo_maps, 'Disallow: /private/' ),
	'and the sitemap lines come last'
);

/*
 * WHAT WAS TYPED IS WHAT IS SAVED. The function this replaced deleted every
 * per cent encoded character it found, so a line with an encoded space in it
 * has been saved wrong since the first release, with no message.
 */

solseo_assert_same(
	'Disallow: /*?q=%20',
	Robots_Txt::sanitise_rules( 'Disallow: /*?q=%20' ),
	'an encoded character survives being saved'
);

solseo_assert_same(
	"Disallow: /a/\nAllow: /a/b$",
	Robots_Txt::sanitise_rules( "Disallow: /a/\r\nAllow: /a/b$" ),
	'and so do the wildcards, anchors and line endings robots.txt actually uses'
);

solseo_assert_same(
	'Disallow: /script/',
	Robots_Txt::sanitise_rules( 'Disallow: <b>/script/</b>' ),
	'angle brackets mean nothing here and are removed'
);

/*
 * THE PRESETS.
 */

$solseo_presets = Robots_Tab::presets();

foreach ( array( 'open', 'no_ai', 'allow_ai', 'maintenance' ) as $solseo_key ) {
	solseo_assert( isset( $solseo_presets[ $solseo_key ] ), 'there is a preset called ' . $solseo_key );
	solseo_assert( '' !== $solseo_presets[ $solseo_key ]['label'], $solseo_key . ' has something to click' );
	solseo_assert( '' !== $solseo_presets[ $solseo_key ]['summary'], $solseo_key . ' says what it does' );
}

solseo_assert_same( '', $solseo_presets['open']['body'], 'the open preset empties the box, because that is what open is' );

foreach ( Robots_Tab::ai_agents() as $solseo_agent ) {
	solseo_assert(
		false !== strpos( $solseo_presets['no_ai']['body'], 'User-agent: ' . $solseo_agent ),
		$solseo_agent . ' is named in the preset that keeps them out'
	);

	solseo_assert(
		false !== strpos( $solseo_presets['allow_ai']['body'], 'User-agent: ' . $solseo_agent ),
		'and in the one that lets them in, so the choice is on the record'
	);
}

/*
 * The preset that lets them in repeats the admin lines rather than writing
 * Allow: /. A crawler that finds a group naming it stops reading the group for
 * everybody, including the two lines that keep it out of wp-admin, so the
 * obvious version of this preset hands those crawlers the admin folder.
 */

solseo_assert(
	false !== strpos( $solseo_presets['allow_ai']['body'], 'Disallow: /wp-admin/' ),
	'the preset that allows the AI crawlers still keeps them out of wp-admin'
);

solseo_assert(
	false === strpos( $solseo_presets['allow_ai']['body'], 'Allow: /' . "\n" ),
	'and does not do it by allowing the whole site'
);

$solseo_hidden = Robots_Txt::compose( $solseo_default, $solseo_presets['maintenance']['body'] );

solseo_assert_same(
	1,
	substr_count( $solseo_hidden, 'User-agent: *' ),
	'maintenance mode produces one group, not two that contradict each other'
);

solseo_assert(
	false !== strpos( $solseo_hidden, 'Disallow: /' ),
	'and it does keep everybody out'
);

/*
 * THE PREVIEW IS COMPOSED IN ONE PLACE. The first version of this screen
 * joined the two halves of the file around the box in the browser, which is
 * fast and wrong: joining is not composing. Rules meant for every crawler are
 * folded into the group WordPress already wrote, and a preview that skips
 * that step showed two groups for everybody where one would be served.
 *
 * So the browser asks the code that serves the file, and this makes sure it
 * keeps doing that.
 */
$solseo_settings_js = (string) file_get_contents( SOLSEO_PATH . 'assets/js/settings.js' );

solseo_assert(
	false !== strpos( $solseo_settings_js, 'robots-preview' ),
	'the screen asks the server what it would serve'
);

solseo_assert(
	false === strpos( $solseo_settings_js, 'data-solseo-before' ),
	'and does not try to work it out by joining strings'
);

$solseo_rest = (string) file_get_contents( SOLSEO_PATH . 'includes/class-rest.php' );

solseo_assert(
	false !== strpos( $solseo_rest, 'robots_preview' ),
	'and there is a route for it'
);

solseo_assert(
	false !== strpos( $solseo_rest, 'may_manage' ),
	'behind a check that this is somebody who looks after the site'
);

/*
 * And the thing the browser used to get wrong is still right on the server:
 * a preset joined to what WordPress wrote gives one group, not two.
 */
$solseo_preset_result = Robots_Txt::compose( $solseo_default, $solseo_presets['no_ai']['body'] );

solseo_assert_same(
	1,
	substr_count( $solseo_preset_result, 'User-agent: *' ),
	'the AI preset leaves one group for everybody, not two'
);

solseo_assert(
	false !== strpos( $solseo_preset_result, 'User-agent: GPTBot' ),
	'with the named crawlers in a group of their own'
);

/*
 * THE ADMIN PATH IS ASKED FOR, NOT SPELLED OUT.
 *
 * `/wp-admin/` is the usual answer and not the only one. A site can move its
 * admin folder, and a site in a subdirectory carries the subdirectory in front
 * of it, so a rule with the path written into it disallows a folder that is not
 * there and, worse, stops the Allow line covering the address it exists for.
 * The WordPress.org review of solseo-2.0.0 named both places this was written
 * out. Both now come from admin_url(), which is what every plugin is told to
 * use for exactly this.
 *
 * THE ALLOWLIST IS EMPTY. Nothing in this plugin may name that path itself.
 */
$solseo_admin_files = array_merge(
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' ),
	glob( SOLSEO_PATH . 'assets/js/*.js' )
);

solseo_assert( count( $solseo_admin_files ) > 100, 'the guard is reading the whole plugin' );

$solseo_hardcoded = array();

foreach ( $solseo_admin_files as $solseo_admin_file ) {
	$solseo_body = (string) file_get_contents( $solseo_admin_file );
	$solseo_body = (string) preg_replace( '#/\*.*?\*/#s', '', $solseo_body );

	foreach ( explode( "\n", $solseo_body ) as $solseo_number => $solseo_line ) {
		if ( preg_match( '#^\s*(//|\*)#', $solseo_line ) ) {
			continue;
		}

		if ( preg_match( '#[\'"][^\'"]*/wp-admin/#', $solseo_line ) ) {
			$solseo_hardcoded[] = str_replace( SOLSEO_PATH, '', $solseo_admin_file ) . ':' . ( $solseo_number + 1 );
		}
	}
}

solseo_assert_same(
	array(),
	$solseo_hardcoded,
	'nothing in the plugin writes the admin path out, so a moved or nested admin is still covered'
);

/*
 * AND THE PATHS THEMSELVES ARE THE SHAPE A ROBOTS LINE NEEDS: a folder with a
 * trailing slash, and a file without one.
 */
list( $solseo_folder, $solseo_ajax ) = Robots_Txt::admin_paths();

solseo_assert_same( '/wp-admin/', $solseo_folder, 'the admin folder comes back as a path with a trailing slash' );
solseo_assert_same( '/wp-admin/admin-ajax.php', $solseo_ajax, 'and the ajax endpoint as the file itself' );

solseo_assert(
	false !== strpos( Robots_Tab::presets()['allow_ai']['body'], 'Allow: ' . $solseo_ajax ),
	'the preset that repeats the admin lines repeats the same two paths'
);

/*
 * A WordPress THAT IS NOT WHERE THE DEFAULT SAYS. A site in a subdirectory and
 * a site whose admin folder has been renamed are both ordinary, and both were
 * getting a Disallow line for a folder that does not exist and an Allow line
 * that did not cover admin-ajax.php. These are the two cases that made the
 * hardcoded path a bug rather than a style point.
 */
$solseo_admin_was = isset( $GLOBALS['solseo_test_admin_url'] ) ? $GLOBALS['solseo_test_admin_url'] : null;

$GLOBALS['solseo_test_admin_url'] = 'https://example.test/blog/wp-admin/';

solseo_assert_same(
	array( '/blog/wp-admin/', '/blog/wp-admin/admin-ajax.php' ),
	Robots_Txt::admin_paths(),
	'a WordPress in a subdirectory gets the subdirectory in both paths'
);

solseo_assert_same(
	"User-agent: *\nDisallow: /blog/wp-admin/\nAllow: /blog/wp-admin/admin-ajax.php\n",
	Robots_Txt::wordpress_default(),
	'and the lines it writes name the folder that is actually there'
);

$GLOBALS['solseo_test_admin_url'] = 'https://example.test/manage/';

solseo_assert_same(
	array( '/manage/', '/manage/admin-ajax.php' ),
	Robots_Txt::admin_paths(),
	'a moved admin folder is named as it is, not as wp-admin'
);

/*
 * AND AN ANSWER NO RULE CAN BE WRITTEN FROM WRITES NO RULE. Disallow with
 * nothing after it means allow everything, which is wrong but harmless.
 * Disallow: / would take the whole site out of every index, so the one thing
 * this must never do on a strange answer is produce a bare slash.
 */
$GLOBALS['solseo_test_admin_url'] = 'https://example.test/';

solseo_assert_same(
	array( '', '' ),
	Robots_Txt::admin_paths(),
	'an admin at the site root gives no pair, because the folder would be the whole site'
);

solseo_assert_same(
	"User-agent: *\n",
	Robots_Txt::wordpress_default(),
	'and the group is written with no admin lines rather than with Disallow: /'
);

solseo_assert(
	false === strpos( Robots_Tab::presets()['allow_ai']['body'], 'Disallow: /' . "\n" ),
	'the preset that repeats those lines leaves them out too, rather than disallowing the site'
);

if ( null === $solseo_admin_was ) {
	unset( $GLOBALS['solseo_test_admin_url'] );
} else {
	$GLOBALS['solseo_test_admin_url'] = $solseo_admin_was;
}

solseo_assert_same(
	"User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n",
	Robots_Txt::wordpress_default(),
	'and the ordinary site is back to the lines core writes'
);
