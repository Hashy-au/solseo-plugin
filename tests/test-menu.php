<?php
/**
 * The sidebar is a contract.
 *
 * Thirty eight features are coming and a menu that grows a line per feature is
 * the thing that gets an SEO plugin deleted. These are the rules that stop it,
 * written before the add-ons that would break them exist.
 *
 * The full contract is design/batches/Sidebar.md.
 *
 * @package SolSEO
 */

use SolSEO\Admin\Menu;
use SolSEO\Admin\Tabs;
use SolSEO\Admin\Upgrade_Screen;

/**
 * One tab, enough to fill a slot.
 *
 * @param string $label What it says.
 * @return array
 */
function solseo_test_tab( $label ) {
	return array(
		'label'  => $label,
		'render' => '__return_false' === $label ? 'not_a_function' : 'solseo_test_render',
	);
}

/**
 * A tab body that draws nothing.
 */
function solseo_test_render() {
}

/*
 * TEN ITEMS, AND NEVER AN ELEVENTH.
 *
 * Counted with every conditional slot filled, which is a site owning every
 * add-on. The Upgrade screen is the one entry allowed above the cap, because
 * it leaves the menu the moment an add-on is installed and can therefore never
 * be on screen at the same time as the four slots below it.
 */
add_filter(
	'solseo_screen_tabs',
	static function ( $tabs, $page ) {
		$slots = array( 'solseo-content', 'solseo-health', 'solseo-shop', 'solseo-local' );

		if ( in_array( $page, $slots, true ) ) {
			$tabs['test'] = solseo_test_tab( 'Test' );
		}

		return $tabs;
	},
	10,
	2
);

$solseo_full = Menu::listed();

/*
 * Ten with an add-on installed, eleven without one, and the eleventh is always
 * the Upgrade screen. The two cannot be on screen together, because the four
 * slots only fill when an add-on is present and the Upgrade screen only shows
 * while one is not. Running the whole suite reads the ten item case, because
 * test-directory-rules.php defines the add-on's constant before this file runs.
 */
$solseo_cap = Upgrade_Screen::available() ? Menu::MAX_ITEMS + 1 : Menu::MAX_ITEMS;

solseo_assert(
	count( $solseo_full ) <= $solseo_cap,
	'the sidebar holds ' . $solseo_cap . ' items at most (found ' . count( $solseo_full ) . ': ' . implode( ', ', array_keys( $solseo_full ) ) . ')'
);

solseo_assert(
	Upgrade_Screen::available() === isset( $solseo_full[ Upgrade_Screen::PAGE ] ),
	'and the Upgrade screen stands in the menu exactly while there is no add-on'
);

/* NO SCREEN CARRIES MORE THAN SIX TABS. A seventh means it is the wrong screen. */
foreach ( array_keys( Menu::screens() ) as $solseo_slug ) {
	$solseo_count = count( Tabs::for_page( $solseo_slug ) );

	solseo_assert(
		$solseo_count <= 6,
		$solseo_slug . ' carries six tabs or fewer (found ' . $solseo_count . ')'
	);
}

remove_all_filters( 'solseo_screen_tabs' );

/* A SLOT IS NOT IN THE MENU UNTIL SOMETHING FILLS IT. */
$solseo_empty = Menu::listed();

foreach ( array( 'solseo-shop', 'solseo-local' ) as $solseo_slot ) {
	solseo_assert(
		! isset( $solseo_empty[ $solseo_slot ] ),
		$solseo_slot . ' stays out of the sidebar while it has no tabs'
	);
}

/*
 * And one that is filled is in it. Content held no tabs until FreeA3 put the
 * writing profile on it, which is the mechanism working rather than a change of
 * mind: the slot was declared empty in FreeA1 and cost a menu place only once
 * something arrived to go in it.
 */
solseo_assert(
	isset( $solseo_empty['solseo-content'] ),
	'solseo-content is in the sidebar now that it has a tab'
);

solseo_assert(
	isset( $solseo_empty['solseo-health'] ),
	'and so is solseo-health, now that FreeB2 has put the speed check on it'
);

solseo_assert(
	count( $solseo_empty ) <= 8,
	'and this plugin on its own stands at eight items or fewer (found ' . count( $solseo_empty ) . ')'
);

/* SETUP IS REACHABLE AND IS NOT IN THE SIDEBAR. */
$solseo_all = Menu::screens();

solseo_assert( isset( $solseo_all['solseo-setup'] ), 'the setup wizard still has an address' );
solseo_assert( ! isset( $solseo_empty['solseo-setup'] ), 'and it does not take a place in the menu' );

/* A TAB RENDERS OR IT IS NOT REGISTERED. */
add_filter(
	'solseo_screen_tabs',
	static function ( $tabs, $page ) {
		if ( 'solseo-technical' !== $page ) {
			return $tabs;
		}

		$tabs['broken'] = array(
			'label'  => 'Broken',
			'render' => 'solseo_no_such_function',
		);

		$tabs['works'] = array(
			'label'  => 'Works',
			'render' => 'solseo_test_render',
			'order'  => 5,
		);

		return $tabs;
	},
	10,
	2
);

$solseo_technical = Tabs::for_page( 'solseo-technical' );

solseo_assert( ! isset( $solseo_technical['broken'] ), 'a tab that cannot be drawn is dropped' );
solseo_assert( isset( $solseo_technical['works'] ), 'and one that can is kept' );

solseo_assert_same(
	'works',
	(string) array_key_first( $solseo_technical ),
	'a tab asking for an earlier place gets one'
);

solseo_assert_same(
	array( 'works', 'sitemap', 'robots', 'indexing', 'crawl', 'links' ),
	array_keys( $solseo_technical ),
	'and the rest keep the order they were declared in'
);

remove_all_filters( 'solseo_screen_tabs' );

/* EVERY ADDRESS THAT MOVED STILL RESOLVES. */
foreach ( Menu::MOVED as $solseo_old => $solseo_target ) {
	$solseo_to = Menu::moved_to( $solseo_old );

	solseo_assert( $solseo_to === $solseo_target, $solseo_old . ' knows where it went' );

	solseo_assert(
		isset( $solseo_all[ $solseo_target['page'] ] ),
		$solseo_old . ' went to a screen that exists'
	);

	solseo_assert(
		isset( Tabs::for_page( $solseo_target['page'] )[ $solseo_target['tab'] ] ),
		$solseo_old . ' went to a tab that exists'
	);
}

foreach ( Menu::MOVED_TABS as $solseo_page => $solseo_tabs ) {
	foreach ( $solseo_tabs as $solseo_tab => $solseo_target ) {
		$_GET['tab'] = $solseo_tab;

		solseo_assert(
			Menu::moved_to( $solseo_page ) === $solseo_target,
			$solseo_page . ' tab ' . $solseo_tab . ' knows where it went'
		);

		solseo_assert(
			isset( Tabs::for_page( $solseo_target['page'] )[ $solseo_target['tab'] ] ),
			'and the tab it went to exists'
		);

		unset( $_GET['tab'] );
	}
}

solseo_assert( array() === Menu::moved_to( 'solseo-titles' ), 'a screen that did not move is left alone' );

/*
 * NOTHING STILL POINTS AT AN ADDRESS THAT MOVED. The redirect is a courtesy for
 * a bookmark somebody else wrote, not a way for us to keep writing dead links.
 */
$solseo_sources = array_merge(
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' )
);

foreach ( $solseo_sources as $solseo_file ) {
	if ( 'class-menu.php' === basename( $solseo_file ) ) {
		continue;
	}

	$solseo_src = (string) file_get_contents( $solseo_file );

	foreach ( array_keys( Menu::MOVED ) as $solseo_old ) {
		solseo_assert(
			false === strpos( $solseo_src, 'page=' . $solseo_old ) && false === strpos( $solseo_src, "'" . $solseo_old . "'" ),
			basename( $solseo_file ) . ' does not link to ' . $solseo_old . ', which moved'
		);
	}
}

/*
 * ONLY THE MENU DECLARES A MENU ITEM. This is the rule an add-on has to obey,
 * and it holds here first: if a screen class in this plugin can reach for a
 * menu item, so can anything else.
 */
foreach ( $solseo_sources as $solseo_file ) {
	if ( 'class-menu.php' === basename( $solseo_file ) ) {
		continue;
	}

	$solseo_src = (string) file_get_contents( $solseo_file );

	foreach ( array( 'add_menu_page(', 'add_submenu_page(', 'remove_submenu_page(' ) as $solseo_call ) {
		solseo_assert(
			false === strpos( $solseo_src, $solseo_call ),
			basename( $solseo_file ) . ' leaves ' . rtrim( $solseo_call, '(' ) . ' to the menu'
		);
	}
}
