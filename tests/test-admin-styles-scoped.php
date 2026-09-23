<?php
/**
 * Nothing this plugin loads into wp-admin may reach WordPress' own interface.
 *
 * WHY THIS IS A GUARD AND NOT A CONVENTION. The colours were declared on
 * `:root`, which is the html element, so every screen that loaded admin.css
 * handed nine SolSEO custom properties to the whole document to inherit. The
 * stylesheet went out on the Dashboard, the posts list, the editor and the term
 * screen, so that was most of wp-admin, on every site with the plugin on. The
 * unscoped `.table-wrap` rule beside it did the same thing in the other
 * direction: any element another plugin drew with that class name, on any of
 * those screens, got our scrollbar. The WordPress.org plugin review of
 * solseo-2.0.0 named the first of those.
 *
 * WHAT IT CHECKS. Every selector in the plugin's stylesheet names a SolSEO
 * element or sits under one. A selector part with no `solseo` anywhere in it
 * is a rule that can match somebody else's markup, and the two ways to fix it
 * are to name the element or to put a SolSEO ancestor in front of it. There is
 * no allowlist: a rule that has to match markup this plugin does not draw does
 * not belong in a stylesheet this plugin loads.
 *
 * @package SolSEO
 */

/**
 * Split a stylesheet into its selectors, flattening at-rules.
 *
 * @param string $css Stylesheet text, comments already removed.
 * @return array Selector groups, as written.
 */
function solseo_css_selectors( $css ) {
	$out    = array();
	$cursor = 0;

	while ( true ) {
		$open = strpos( $css, '{', $cursor );

		if ( false === $open ) {
			break;
		}

		$selector = trim( substr( $css, $cursor, $open - $cursor ) );
		$depth    = 1;
		$scan     = $open + 1;
		$length   = strlen( $css );

		while ( $scan < $length && $depth > 0 ) {
			if ( '{' === $css[ $scan ] ) {
				++$depth;
			} elseif ( '}' === $css[ $scan ] ) {
				--$depth;
			}

			++$scan;
		}

		$body = substr( $css, $open + 1, $scan - $open - 2 );

		if ( 0 === strpos( $selector, '@media' ) || 0 === strpos( $selector, '@supports' ) ) {
			$out = array_merge( $out, solseo_css_selectors( $body ) );
		} elseif ( '' !== $selector && '@' !== $selector[0] ) {
			$out[] = $selector;
		}

		$cursor = $scan;
	}

	return $out;
}

$solseo_css_file = SOLSEO_PATH . 'assets/css/admin.css';

solseo_assert( file_exists( $solseo_css_file ), 'the admin stylesheet is where the enqueue says it is' );

$solseo_css = (string) file_get_contents( $solseo_css_file );
$solseo_css = (string) preg_replace( '#/\*.*?\*/#s', '', $solseo_css );

$solseo_selectors = solseo_css_selectors( $solseo_css );

solseo_assert( count( $solseo_selectors ) > 100, 'the guard is reading the whole stylesheet' );

$solseo_loose = array();

foreach ( $solseo_selectors as $solseo_group ) {
	foreach ( explode( ',', $solseo_group ) as $solseo_part ) {
		$solseo_part = trim( $solseo_part );

		if ( '' === $solseo_part ) {
			continue;
		}

		if ( false === strpos( $solseo_part, 'solseo' ) ) {
			$solseo_loose[] = $solseo_part;
		}
	}
}

solseo_assert_same(
	array(),
	array_values( array_unique( $solseo_loose ) ),
	'every rule in admin.css names a SolSEO element or sits under one'
);

/*
 * THE COLOURS ARE NOT ON THE DOCUMENT. Said separately from the sweep above,
 * because `:root` passing that sweep would need somebody to write
 * `:root.solseo-something`, which reads as scoped and is not.
 */
solseo_assert(
	false === strpos( $solseo_css, ':root' ),
	'the stylesheet declares nothing on :root, so nothing is inherited by the whole admin document'
);

/*
 * AND THE STYLESHEET ONLY GOES WHERE THERE IS SOMETHING TO STYLE. The screen
 * names this used to answer yes to outright, asked properly. `solseo_nothing`
 * stands in for a post type or taxonomy somebody else registered.
 */
$solseo_managed_types = array( 'post', 'page', 'product' );
$solseo_managed_taxes = array( 'category', 'post_tag' );

$solseo_asset_cases = array(
	array( 'toplevel_page_solseo', array(), true, 'our own screen' ),
	array( 'solseo_page_solseo-tools', array(), true, 'our own tab' ),
	array( 'post.php', array( 'post_type' => 'post' ), true, 'editing a post' ),
	array( 'post-new.php', array( 'post_type' => 'page' ), true, 'writing a page' ),
	array( 'post.php', array( 'post_type' => 'solseo_nothing' ), false, 'editing a post type we do not manage' ),
	array( 'edit.php', array( 'post_type' => 'product' ), true, 'the products list' ),
	array( 'edit.php', array( 'post_type' => 'solseo_nothing' ), false, 'the list of a post type we do not manage' ),
	array( 'term.php', array( 'taxonomy' => 'category' ), true, 'editing a category' ),
	array( 'term.php', array( 'taxonomy' => 'solseo_nothing' ), false, 'editing a taxonomy we do not manage' ),
	array( 'index.php', array( 'widget' => true ), true, 'the Dashboard, with one of our widgets on it' ),
	array( 'index.php', array( 'widget' => false ), false, 'the Dashboard, with none of ours on it' ),
	array( 'plugins.php', array(), false, 'the plugins list' ),
	array( 'options-general.php', array(), false, 'somebody else\'s settings screen' ),
	array( 'upload.php', array(), false, 'the media library' ),
	array( 'users.php', array(), false, 'the users list' ),
	array( 'edit-comments.php', array(), false, 'the comments screen' ),
	array( 'profile.php', array(), false, 'somebody\'s own profile' ),
	array( 'site-editor.php', array(), false, 'the site editor' ),
);

foreach ( $solseo_asset_cases as $solseo_case ) {
	list( $solseo_hook, $solseo_screen, $solseo_want, $solseo_says ) = $solseo_case;

	solseo_assert_same(
		$solseo_want,
		\SolSEO\Admin\Admin::needs_assets( $solseo_hook, $solseo_screen, $solseo_managed_types, $solseo_managed_taxes ),
		'the stylesheet loads on ' . $solseo_says . ' only when it should'
	);
}
