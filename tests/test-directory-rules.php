<?php
/**
 * The directory rules this plugin has to keep, made mechanical.
 *
 * Guideline 11 allows an upgrade prompt on the plugin's own settings page and
 * nowhere else, and says in the same paragraph that tracking referrals through
 * one is not permitted. Guideline 10 keeps our links off the front of somebody
 * else's site. Guideline 7 keeps the plugin from contacting us without consent.
 *
 * Each of those is a rule one well meant line could break, so each is a test.
 *
 * @package SolSEO
 */

$solseo_source = array_merge(
	glob( SOLSEO_PATH . '*.php' ),
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' ),
	glob( SOLSEO_PATH . 'assets/js/*.js' )
);

solseo_assert( count( $solseo_source ) > 30, 'the guards are reading the whole plugin' );

/*
 * NO ADMIN NOTICE, ANYWHERE. The quickest way to break guideline 11 is to
 * congratulate somebody on installing this, on every screen, until they click
 * something. The settings screens print their own "Saved." inside themselves,
 * which is a different thing: it is on our page and it answers an action.
 */
$solseo_noticed = array();

foreach ( $solseo_source as $solseo_file ) {
	$solseo_text = (string) file_get_contents( $solseo_file );

	foreach ( array( "'admin_notices'", "'all_admin_notices'", "'network_admin_notices'" ) as $solseo_hook ) {
		if ( false !== strpos( $solseo_text, $solseo_hook ) ) {
			$solseo_noticed[] = basename( $solseo_file );
		}
	}
}

solseo_assert_same( array(), $solseo_noticed, 'nothing in the plugin hooks an admin notice' );

/*
 * EVERY LINK TO US IS A BARE ADDRESS. No campaign, no referrer, no site id: a
 * query string on one of these is how the plugin would start reporting who
 * clicked what, which is the tracking guideline 11 rules out and the consent
 * guideline 7 requires.
 */
$solseo_tracked = array();

foreach ( $solseo_source as $solseo_file ) {
	if ( ! preg_match_all( '~https://sol[a-z]+[.]com[.]au[^ "]*~', (string) file_get_contents( $solseo_file ), $solseo_found ) ) {
		continue;
	}

	foreach ( $solseo_found[0] as $solseo_url ) {
		if ( false !== strpos( $solseo_url, '?' ) ) {
			$solseo_tracked[] = $solseo_url;
		}
	}
}

solseo_assert_same( array(), $solseo_tracked, 'no link to our own site carries a query string' );

/*
 * NOTHING REACHES THE FRONT OF SOMEBODY'S SITE. Guideline 10: a credit or a
 * link on the visitor side has to be opt in, so this plugin does not have one
 * to opt into. The head prints a comment with our name in it and no address.
 */
$solseo_front = array();

foreach ( glob( SOLSEO_PATH . 'includes/frontend/*.php' ) as $solseo_file ) {
	if ( false !== strpos( (string) file_get_contents( $solseo_file ), 'solseo.com.au' ) ) {
		$solseo_front[] = basename( $solseo_file );
	}
}

solseo_assert_same( array(), $solseo_front, 'nothing a visitor sees links to us' );

/*
 * THE ADD-ON IS OFFERED ON ONE SCREEN AND NOWHERE ELSE. The screen that does
 * the offering is the only file allowed to name where it is sold.
 */
$solseo_upsell = array();

foreach ( $solseo_source as $solseo_file ) {
	if ( false !== strpos( $solseo_file, 'upgrade' ) ) {
		continue;
	}

	if ( false !== strpos( (string) file_get_contents( $solseo_file ), 'solseo.com.au/pro' ) ) {
		$solseo_upsell[] = basename( $solseo_file );
	}
}

solseo_assert_same( array(), $solseo_upsell, 'the add-on is offered on the upgrade screen and nowhere else' );

$solseo_screens = \SolSEO\Admin\Menu::screens();

solseo_assert(
	isset( $solseo_screens[ \SolSEO\Admin\Upgrade_Screen::PAGE ] ),
	'the upgrade screen is in the menu while the add-on is not installed'
);

solseo_assert(
	false !== strpos( (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-admin.php' ), 'Upgrade_Screen::available()' ),
	'the plugins row link asks whether the add-on is installed before offering it'
);

/*
 * AND IT ALL GOES WHEN THE ADD-ON ARRIVES. Somebody who has bought it does not
 * need selling to. This defines the add-on's constant and cannot undefine it,
 * so it is the last thing in this file: the tests that run after it read a
 * six screen menu instead of a seven screen one, which is what they are for.
 */
define( 'SOLSEO_PRO_VERSION', '1.0.0' );

solseo_assert( ! \SolSEO\Admin\Upgrade_Screen::available(), 'the upgrade screen stands down for the add-on' );

$solseo_screens = \SolSEO\Admin\Menu::screens();

solseo_assert(
	! isset( $solseo_screens[ \SolSEO\Admin\Upgrade_Screen::PAGE ] ),
	'and it leaves the menu once the add-on is installed'
);
