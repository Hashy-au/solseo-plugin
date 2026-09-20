<?php
/**
 * The AI crawler catalogue, llms.txt, and the permission map.
 *
 * @package SolSEO
 */

use SolSEO\Capabilities;
use SolSEO\Frontend\Ai_Crawlers;

/*
 * EVERY ROW NAMES A REAL CRAWLER AND CITES WHOEVER RUNS IT.
 *
 * A list of user agents somebody half remembers is worse than no list: it
 * blocks nothing, because the token is wrong, while telling the reader they are
 * covered.
 */
$solseo_crawlers = Ai_Crawlers::all();

solseo_assert( count( $solseo_crawlers ) >= 8, 'the catalogue holds the crawlers worth naming' );

foreach ( $solseo_crawlers as $solseo_token => $solseo_crawler ) {
	solseo_assert( '' !== trim( $solseo_token ), 'every row has a user agent token' );
	solseo_assert( ! empty( $solseo_crawler['operator'] ), $solseo_token . ' says who runs it' );
	solseo_assert( ! empty( $solseo_crawler['purpose'] ), $solseo_token . ' says what it does' );
	solseo_assert( ! empty( $solseo_crawler['cost'] ), $solseo_token . ' says what blocking it costs' );

	solseo_assert(
		0 === strpos( (string) $solseo_crawler['source'], 'https://' ),
		$solseo_token . ' cites the operator\'s own documentation'
	);
}

/* THE PRESETS AND THE CATALOGUE READ FROM ONE LIST. */
solseo_assert_same(
	array_keys( $solseo_crawlers ),
	\SolSEO\Admin\Robots_Tab::ai_agents(),
	'the robots presets address exactly the crawlers in the catalogue'
);

/*
 * THE STATE ON THE SCREEN IS READ OUT OF THE FILE.
 *
 * A stored copy beside robots.txt is a second truth, and the day the two
 * disagree the screen is the one that lies, because the crawler obeys the file.
 */
$solseo_served = "User-agent: *\nDisallow: /wp-admin/\n\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: PerplexityBot\nAllow: /\n";

solseo_assert( Ai_Crawlers::blocked( 'GPTBot', $solseo_served ), 'a crawler told to stay out reads as blocked' );
solseo_assert( ! Ai_Crawlers::blocked( 'PerplexityBot', $solseo_served ), 'one that is allowed does not' );
solseo_assert( ! Ai_Crawlers::blocked( 'CCBot', $solseo_served ), 'and one that is not mentioned is allowed, which is what robots.txt means' );

/*
 * A NAMED GROUP STANDS ALONE.
 *
 * A crawler that finds its own name stops reading the group for everybody
 * else, so a Disallow in the star group does not make a named crawler blocked.
 * This is the part of robots.txt most often got wrong, including by tools that
 * claim to read it.
 */
$solseo_star_only = "User-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nAllow: /\n";

solseo_assert(
	! Ai_Crawlers::blocked( 'GPTBot', $solseo_star_only ),
	'a named crawler is not blocked by the group it stopped reading'
);

/* THE PERMISSION MAP NARROWS AND NEVER WIDENS. */
$solseo_rest_src = (string) file_get_contents( SOLSEO_PATH . 'includes/class-rest.php' );

solseo_assert(
	false !== strpos( $solseo_rest_src, '$wp_says && Capabilities::can(' ),
	'WordPress is asked first and our answer can only narrow it'
);

foreach ( array_keys( Capabilities::map() ) as $solseo_cap ) {
	solseo_assert(
		in_array( 'administrator', Capabilities::roles( $solseo_cap ), true ),
		'an administrator holds ' . $solseo_cap . ', so a site cannot lock itself out'
	);
}

solseo_assert_same( array(), Capabilities::roles( 'no-such-permission' ), 'a permission that does not exist is held by nobody' );

/* AND AN ADD-ON CAN CHANGE WHO HOLDS ONE. */
add_filter(
	'solseo_capability_roles',
	static function ( $roles, $capability ) {
		return Capabilities::MANAGE_REDIRECTS === $capability ? array( 'administrator' ) : $roles;
	},
	10,
	2
);

solseo_assert_same(
	array( 'administrator' ),
	Capabilities::roles( Capabilities::MANAGE_REDIRECTS ),
	'the map is filterable, which is how the add-on makes it editable'
);

remove_all_filters( 'solseo_capability_roles' );

/*
 * LLMS.TXT PUBLISHES NOTHING THAT IS NOT ALREADY PUBLIC.
 *
 * Read from the source rather than run, because composing it needs a whole
 * WordPress. What matters is that the one exclusion is there.
 */
$solseo_llms_src = (string) file_get_contents( SOLSEO_PATH . 'includes/frontend/class-llms-txt.php' );

solseo_assert(
	false !== strpos( $solseo_llms_src, "Meta::get( \$post->ID, 'robots_noindex' )" ),
	'a page asked to stay out of search results is left out of llms.txt as well'
);

solseo_assert(
	false !== strpos( $solseo_llms_src, "'post_status'      => 'publish'" ),
	'and nothing unpublished is listed'
);

solseo_assert(
	false !== strpos( $solseo_llms_src, 'X-Robots-Tag: noindex' ),
	'the file itself asks not to be indexed'
);
