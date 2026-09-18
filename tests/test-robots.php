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
