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

/*
 * AND THE SENTENCE ABOVE THE LIST DOES NOT COUNT IT. D-50.7 requires the
 * feature list to name what the add-on does today, and the line introducing it
 * is part of that list: it read "adds the two things below" over a list of
 * three for the whole of 1.3.0, because the list grew and the sentence did not.
 * Nothing failed, because nothing was comparing them. A count in prose is a
 * second source of truth for something the loop below already knows.
 */
$solseo_view = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/views/upgrade.php' );

solseo_assert(
	0 === preg_match( '/\b(one|two|three|four|five|six|seven|eight|nine|ten|\d+)\s+(things?|features?|items?|additions?)\b/i', $solseo_view ),
	'the upgrade screen does not count the features in a sentence the list will outgrow'
);

$solseo_features = array();

foreach ( \SolSEO\Admin\Upgrade_Screen::listed() as $solseo_feature ) {
	if ( empty( $solseo_feature['title'] ) || empty( $solseo_feature['text'] ) ) {
		$solseo_features[] = 'a feature with nothing in it';
	}
}

solseo_assert_same( array(), $solseo_features, 'and every feature it lists says what it is' );
solseo_assert( count( \SolSEO\Admin\Upgrade_Screen::listed() ) > 0, 'and there is something to list' );

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
 * EVERY PLACE THIS PLUGIN CAN REACH IS DECLARED HERE, AND EVERY THIRD PARTY
 * AMONG THEM IS NAMED IN THE README.
 *
 * Guideline 7 and the directory's External services requirement: a site owner
 * and a reviewer both read that section and take it as the whole list.
 *
 * It was written when there was one destination, and it opened by promising
 * there were none unless you connected an account. That sentence is true today
 * and stops being true the moment a feature calls anybody else, with nobody
 * having edited it and nothing having failed. A declaration that only one
 * person remembers to update is not a declaration.
 *
 * So the list is mechanical. Every file that opens a connection is named here
 * with where it opens it to, and every one of those places has to appear in
 * the readme's own section. A new destination fails twice over until both the
 * map below and the readme name it.
 *
 * A new entry needs a docs/DECISIONS.md entry first, because a new destination
 * is a change to what the plugin promises, not a change to how it works.
 *
 * THE CRAWLER IS THE EXCEPTION, AND IT IS A NARROW ONE. A site fetching its
 * own pages has not contacted a third party, and External services is a
 * declaration about third parties: putting the site's own address in there
 * tells a reader their content is leaving, which is the opposite of what
 * happens. So there is a second kind of entry, and it buys nothing for free.
 * Exactly one file may hold it, that file may reach the site's own host and
 * nothing else, and the refusal is asserted below by calling it rather than by
 * reading it. Everything that crawls goes through that one file, which is why
 * the map above can stay exact. See D-84.1.
 */
$solseo_own_site = '(this site)';

/*
 * A VALUE MAY BE A LIST. It was one host per file until FreeC1, because every
 * file that opened a connection opened it to one place. The Google connection
 * is the first that cannot: the handshake goes through solseo.com.au when the
 * plugin uses our app, and straight to Google when the site owner pastes their
 * own client id and secret (D-75.3, D-128.5). Declaring only one of those two
 * would leave the other undeclared, which is the failure this map exists for.
 */
$solseo_egress = array(
	'includes/connect/class-psi.php'            => 'www.googleapis.com',
	'includes/connect/class-google.php'         => array( 'solseo.com.au', 'oauth2.googleapis.com' ),
	'includes/connect/class-search-console.php' => 'searchconsole.googleapis.com',
	'includes/crawl/class-fetch.php'            => $solseo_own_site,
	'includes/hub/class-client.php'             => 'solseo.com.au',
	'includes/indexing/class-indexnow.php'      => 'api.indexnow.org',
);

$solseo_opens = array();

foreach ( $solseo_source as $solseo_file ) {
	if ( ! preg_match( '/\b(wp_(safe_)?remote_(request|get|post|head)|curl_init|fsockopen|stream_socket_client)\s*\(/', (string) file_get_contents( $solseo_file ) ) ) {
		continue;
	}

	$solseo_opens[] = str_replace( '\\', '/', str_replace( SOLSEO_PATH, '', $solseo_file ) );
}

sort( $solseo_opens );

$solseo_declared = array_keys( $solseo_egress );
sort( $solseo_declared );

solseo_assert_same(
	$solseo_declared,
	$solseo_opens,
	'the files that open a connection are exactly the files declared to open one'
);

/* AND THE README NAMES EVERY ONE OF THOSE PLACES THAT BELONGS TO SOMEBODY ELSE. */
$solseo_readme = (string) file_get_contents( SOLSEO_PATH . 'readme.txt' );

preg_match( '/^== External services ==\s*(.*?)^== /ms', $solseo_readme, $solseo_section );

$solseo_services = isset( $solseo_section[1] ) ? $solseo_section[1] : '';

solseo_assert( '' !== trim( $solseo_services ), 'the readme has an External services section' );

foreach ( $solseo_egress as $solseo_file => $solseo_hosts ) {
	foreach ( (array) $solseo_hosts as $solseo_host ) {
		if ( $solseo_own_site === $solseo_host ) {
			continue;
		}

		solseo_assert(
			false !== strpos( $solseo_services, $solseo_host ),
			'the readme names ' . $solseo_host . ', which ' . basename( $solseo_file ) . ' contacts'
		);
	}
}

/*
 * AND THE CONSENT SCREEN, WHICH IS NOT A SOCKET AND IS STILL A DESTINATION.
 *
 * The plugin sends a browser to accounts.google.com rather than opening a
 * connection to it, so the map above cannot see it and a reader of the
 * External services section would never learn that pressing Connect leaves
 * their site. The section is a declaration about where a site owner's data
 * goes, not a list of the plugin's sockets.
 */
solseo_assert(
	false !== strpos( $solseo_services, 'accounts.google.com' ),
	'the readme names accounts.google.com, where the Connect button sends somebody'
);

/*
 * AND IT SAYS THE GOOGLE HANDSHAKE NEEDS NO SolSEO ACCOUNT.
 *
 * The section's own opening promised, in words, that nothing was contacted
 * unless a key had been pasted, and the SolSEO entry said it was off until a
 * pairing code was. Both were true until FreeC1 and the second one stopped
 * being true with nobody editing it, which is exactly the failure D-83.1
 * mechanised the rest of this map for. The relay is the one destination the
 * plugin reaches on a site with no account, so it has to be named as one.
 */
solseo_assert(
	false !== strpos( $solseo_services, 'without a SolSEO account' ),
	'and the section says plainly that the Google handshake needs no SolSEO account'
);

/*
 * AND ONLY ONE FILE MAY SAY IT REACHES THE SITE ITSELF.
 *
 * The exemption is worth exactly one choke point. Two files holding it is a
 * category, and a category is how "we only ever fetch our own pages" becomes
 * something nobody checks.
 */
$solseo_self_only = array_keys(
	array_filter(
		$solseo_egress,
		static function ( $solseo_host ) use ( $solseo_own_site ) {
			return $solseo_own_site === $solseo_host;
		}
	)
);

solseo_assert_same(
	array( 'includes/crawl/class-fetch.php' ),
	$solseo_self_only,
	'one file reaches the site itself, and it is the crawl fetcher'
);

/*
 * AND THE SECTION SAYS SO, SO A READER IS NOT LEFT TO INFER IT.
 *
 * Leaving the crawler out of a list of third parties is correct and it is also
 * the sort of omission a reviewer asks about. The section says what the crawler
 * does and why it is not below, which costs three lines and answers the
 * question before it is asked.
 */
solseo_assert(
	false !== strpos( $solseo_services, 'your own site' ),
	'and the section says plainly that the crawler asks this site for its own pages'
);

/*
 * AND IT REFUSES ANYTHING ELSE WHEN IT IS CALLED, NOT IN A COMMENT.
 *
 * This is the half that makes the exemption safe. A declaration that a file
 * only talks to this site is a claim, and the readme going stale once already
 * is what these guards exist for, so the claim is tested by handing it an
 * address somewhere else and asserting that nothing left the building.
 */
solseo_test_http_reset();

$solseo_off_site = array(
	'https://example.com/anything'   => 'another host',
	'https://example.test.evil.com/' => 'a host that merely starts the same',
	'file:///etc/passwd'             => 'a scheme that is not the web',
	'http://127.0.0.1:9200/_search'  => 'something listening on this machine',
	'https://user@example.org/'      => 'an address with credentials in it',
	''                               => 'nothing at all',
);

foreach ( $solseo_off_site as $solseo_url => $solseo_what ) {
	$solseo_answer = \SolSEO\Crawl\Fetch::get( $solseo_url );

	solseo_assert(
		is_wp_error( $solseo_answer ),
		'the crawl fetcher refuses ' . $solseo_what
	);
}

solseo_assert_same(
	array(),
	solseo_test_http_sent(),
	'and it refused every one of them without opening a socket'
);

/* AND IT DOES FETCH AN ADDRESS ON THIS SITE, SO THE REFUSAL IS NOT JUST "NO". */
solseo_test_http_reset();
solseo_test_http_next( 200, '<html><head><title>Hello</title></head><body><h1>Hello</h1></body></html>' );

$solseo_ours = \SolSEO\Crawl\Fetch::get( home_url( '/about/' ) );

solseo_assert( ! is_wp_error( $solseo_ours ), 'and it fetches an address on this site' );
solseo_assert_same( 1, count( solseo_test_http_sent() ), 'and that one did open a socket' );

solseo_test_http_reset();

/*
 * AND THE SECTION MAKES NO CLAIM THAT COUNTS ITS OWN ENTRIES.
 *
 * D-73.4: a number in prose beside a list is a second source of truth, and the
 * list is the one that grows. The same goes for "no outbound request unless",
 * which is a count of one written out in words. The opening sentence has to
 * hold for any number of entries, so it says every connection is off until it
 * is switched on and leaves the counting to the headings underneath.
 */
solseo_assert(
	0 === preg_match( '/\bmakes no outbound request\b/i', $solseo_services ),
	'the opening sentence does not promise a number of destinations the list below can outgrow'
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

/*
 * A FILE WE SERVE OURSELVES SAYS 200.
 *
 * `template_redirect` runs after WordPress has already decided the request is a
 * 404 and already called status_header( 404 ). Echoing the right bytes after
 * that sends the right body under the wrong status line, which for a text file
 * nobody reads is invisible and for the IndexNow key file is fatal: the engines
 * fetch it to check the submission came from this site, a 404 fails that check,
 * and every submission afterwards is refused with a 403 that points at a file
 * which looks fine in a browser.
 *
 * Found on a real WordPress, where the key file served its key with a 404 and
 * llms.txt served its text with a 200, which is the same code path getting
 * away with it.
 */
$solseo_servers = array(
	'includes/frontend/class-llms-txt.php',
	'includes/indexing/class-indexnow.php',
);

foreach ( $solseo_servers as $solseo_relative ) {
	$solseo_body = (string) file_get_contents( SOLSEO_PATH . $solseo_relative );

	preg_match( '#function serve\(\).*?\n\t\}#s', $solseo_body, $solseo_serve );

	$solseo_src = isset( $solseo_serve[0] ) ? $solseo_serve[0] : '';

	solseo_assert( '' !== $solseo_src, basename( $solseo_relative ) . ' has a serve method' );

	solseo_assert(
		false !== strpos( $solseo_src, 'status_header( 200 )' ),
		basename( $solseo_relative ) . ' says 200 rather than leaving the 404 WordPress already sent'
	);
}
