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
 * ONE ADMIN NOTICE, AND IT IS NAMED HERE. The quickest way to break guideline
 * 11 is to congratulate somebody on installing this, on every screen, until
 * they click something. This was zero until 1.2.0, and the one thing that
 * earned an exception is the one problem installing this plugin creates and
 * that nobody can see by looking at their own site: two SEO plugins writing
 * two of every tag. Guideline 11 allows a notice that is limited in scope and
 * dismissible, which that one is on all three counts below.
 *
 * A second entry in this allowlist needs an entry in docs/DECISIONS.md first.
 *
 * The settings screens print their own "Saved." inside themselves, which is a
 * different thing: it is on our page and it answers an action.
 */
$solseo_noticed = array();

foreach ( $solseo_source as $solseo_file ) {
	$solseo_lines = explode( "\n", (string) file_get_contents( $solseo_file ) );

	foreach ( $solseo_lines as $solseo_number => $solseo_line ) {
		if ( ! preg_match( "/add_action\(\s*'(?:all_|network_)?admin_notices'/", $solseo_line ) ) {
			continue;
		}

		$solseo_noticed[] = array(
			'where' => str_replace( SOLSEO_PATH, '', $solseo_file ) . ':' . ( $solseo_number + 1 ),
			'line'  => trim( $solseo_line ),
		);
	}
}

solseo_assert_same( 1, count( $solseo_noticed ), 'exactly one file in the plugin hooks an admin notice' );

solseo_assert(
	1 === count( $solseo_noticed )
		&& 0 === strpos( $solseo_noticed[0]['where'], 'includes/admin/class-conflict-notice.php:' )
		&& false !== strpos( $solseo_noticed[0]['line'], "'admin_notices'" )
		&& false !== strpos( $solseo_noticed[0]['line'], "'render'" ),
	'and it is Conflict_Notice::render, from includes/admin/class-conflict-notice.php'
);

/*
 * AND IT KEEPS ITS THREE CONDITIONS. Each of these is the thing that turns the
 * one allowed notice back into a nag if somebody removes it while tidying up.
 */
$solseo_notice_file = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-conflict-notice.php' );

solseo_assert(
	false !== strpos( $solseo_notice_file, 'get_current_screen' ),
	'the notice asks which screen it is on, so it can never be site wide'
);

solseo_assert(
	false !== strpos( $solseo_notice_file, 'update_user_meta' ) && false === strpos( $solseo_notice_file, 'set_transient' ),
	'dismissing the notice is permanent, not a transient that comes back'
);

/*
 * SWITCHING OFF SOMEBODY ELSE'S PLUGIN HAPPENS IN ONE PLACE, UNDER THE RIGHT
 * CAPABILITY. Changing a setting and switching a plugin off are not the same
 * permission, and this plugin's usual check is the first of those.
 */
$solseo_deactivators = array();

foreach ( $solseo_source as $solseo_file ) {
	if ( false !== strpos( (string) file_get_contents( $solseo_file ), 'deactivate_plugins(' ) ) {
		$solseo_deactivators[] = str_replace( SOLSEO_PATH, '', $solseo_file );
	}
}

solseo_assert_same(
	array( 'includes/admin/class-tools-screen.php' ),
	$solseo_deactivators,
	'one file switches another plugin off, and it is the Tools screen'
);

solseo_assert(
	false !== strpos( (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-tools-screen.php' ), "current_user_can( 'activate_plugins' )" ),
	'and it asks whether this person may switch plugins off, not whether they may change a setting'
);

/*
 * AND NEVER ON ITS OWN. Nothing that runs when the plugin is switched on may
 * switch anything off, or send anybody anywhere.
 */
$solseo_on_activation = array();

foreach ( $solseo_source as $solseo_file ) {
	$solseo_text = (string) file_get_contents( $solseo_file );

	if ( false === strpos( $solseo_text, 'register_activation_hook' ) && false === strpos( $solseo_text, 'class Install' ) ) {
		continue;
	}

	if ( preg_match( '/wp_(safe_)?redirect\s*\(/', $solseo_text ) || false !== strpos( $solseo_text, 'deactivate_plugins(' ) ) {
		$solseo_on_activation[] = str_replace( SOLSEO_PATH, '', $solseo_file );
	}
}

solseo_assert_same(
	array(),
	$solseo_on_activation,
	'switching the plugin on redirects nobody and switches nothing off'
);

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
 * SITE HEALTH IS WordPress's SCREEN, NOT OURS. Every test we add there is built
 * from what this plugin already knows and works with the network unplugged. A
 * plugin that answers "your images have no alt text" with a price is a plugin
 * whose warnings people learn to scroll past, and it is the kind of thing
 * guideline 11 exists for.
 */
$solseo_health_sell = array();

foreach ( glob( SOLSEO_PATH . 'includes/health/*.php' ) as $solseo_file ) {
	$solseo_body = (string) file_get_contents( $solseo_file );

	foreach ( array( 'solseo.com.au', 'upgrade', 'Upgrade', ' Pro ', 'plan', 'licence' ) as $solseo_word ) {
		if ( false !== strpos( $solseo_body, $solseo_word ) ) {
			$solseo_health_sell[] = basename( $solseo_file ) . ' says ' . trim( $solseo_word );
		}
	}
}

solseo_assert_same( array(), $solseo_health_sell, 'nothing in the Site Health tests sells anything' );

/*
 * AND IT MAKES NO REQUEST. They are registered as direct tests, which run on
 * the page load, so one HTTP call in there is a Site Health screen that hangs
 * on somebody else's server.
 */
$solseo_health_http = array();

foreach ( glob( SOLSEO_PATH . 'includes/health/*.php' ) as $solseo_file ) {
	$solseo_body = (string) file_get_contents( $solseo_file );

	foreach ( array( 'wp_remote_', 'file_get_contents', 'curl_' ) as $solseo_call ) {
		if ( false !== strpos( $solseo_body, $solseo_call ) ) {
			$solseo_health_http[] = basename( $solseo_file ) . ' calls ' . $solseo_call;
		}
	}
}

solseo_assert_same( array(), $solseo_health_http, 'and none of them opens a socket' );

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
