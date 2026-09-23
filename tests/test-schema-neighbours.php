<?php
/**
 * The graph and its neighbours: WooCommerce's own Product node, and another
 * SEO plugin's Organization.
 *
 * A product page was carrying two Product entities, ours with the aggregate
 * rating and one from WooCommerce's WC_Structured_Data, printed in the footer.
 * A search engine shown two descriptions of one product picks one, and which
 * one is not something the shop chose. So when this plugin publishes a Product
 * node for the page, WooCommerce's is unhooked, and a filter lets a shop keep
 * WooCommerce's instead.
 *
 * @package SolSEO
 */

use SolSEO\Frontend\Schema;
use SolSEO\Options;

/**
 * Enough of WooCommerce for the seam: WC()->structured_data holds the
 * object whose method is hooked at 60.
 *
 * @return object
 */
function WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- the name WooCommerce uses.
	static $wc = null;

	if ( null === $wc ) {
		$wc                  = new stdClass();
		$wc->structured_data = new stdClass();
	}

	return $wc;
}

/**
 * Stand in for wc_get_product(), so a Product schema type resolves.
 *
 * @param int $id Product ID.
 * @return null
 */
function wc_get_product( $id ) {
	return null;
}

$solseo_product = solseo_test_post(
	array(
		'ID'          => 96001,
		'post_type'   => 'product',
		'post_title'  => 'Mongolian bow',
		'post_status' => 'publish',
	)
);

$solseo_product_context = array(
	'type'      => 'singular',
	'object_id' => (int) $solseo_product->ID,
	'post_type' => 'product',
	'taxonomy'  => '',
	'term'      => null,
);

/**
 * The WooCommerce removals recorded so far.
 *
 * @return array
 */
function solseo_test_woo_removals() {
	return array_values(
		array_filter(
			$GLOBALS['solseo_test_removed'],
			function ( $removed ) {
				return 'woocommerce_single_product_summary' === $removed['tag']
					&& 60 === $removed['priority']
					&& is_array( $removed['callback'] )
					&& WC()->structured_data === $removed['callback'][0]
					&& 'generate_product_data' === $removed['callback'][1];
			}
		)
	);
}

/* ---- WITH SCHEMA ON AND A PRODUCT, WOOCOMMERCE'S NODE IS UNHOOKED ------- */
Options::update( array( 'schema_enabled' => true ) );
$GLOBALS['solseo_test_removed'] = array();

solseo_assert( Schema::replaces_woo( $solseo_product_context ), 'a product page whose main entity is a Product replaces the WooCommerce node' );

Schema::replace_woo( $solseo_product_context );

solseo_assert_same( 1, count( solseo_test_woo_removals() ), 'and generate_product_data is removed from woocommerce_single_product_summary at 60' );

/* ---- A PAGE THAT IS NOT A PRODUCT LEAVES WOOCOMMERCE ALONE -------------- */
$GLOBALS['solseo_test_removed'] = array();

$solseo_post = solseo_test_post(
	array(
		'ID'          => 96002,
		'post_type'   => 'post',
		'post_title'  => 'How to string a bow',
		'post_status' => 'publish',
	)
);

Schema::replace_woo(
	array(
		'type'      => 'singular',
		'object_id' => (int) $solseo_post->ID,
		'post_type' => 'post',
		'taxonomy'  => '',
		'term'      => null,
	)
);

solseo_assert_same( array(), solseo_test_woo_removals(), 'an article does not touch the WooCommerce hook' );

/* ---- A PRODUCT WHOSE SCHEMA TYPE IS NOT PRODUCT LEAVES IT ALONE TOO ----- */
update_post_meta( $solseo_product->ID, '_solseo_schema_type', 'none' );
$GLOBALS['solseo_test_removed'] = array();

Schema::replace_woo( $solseo_product_context );

solseo_assert_same( array(), solseo_test_woo_removals(), 'a product set to publish no main entity keeps WooCommerce\'s' );

delete_post_meta( $solseo_product->ID, '_solseo_schema_type' );

/* ---- THE FILTER LETS A SHOP KEEP WOOCOMMERCE'S ------------------------- */
add_filter(
	'solseo_schema_replace_woo',
	function () {
		return false;
	}
);
$GLOBALS['solseo_test_removed'] = array();

solseo_assert( ! Schema::replaces_woo( $solseo_product_context ), 'solseo_schema_replace_woo returning false keeps the WooCommerce node' );

Schema::replace_woo( $solseo_product_context );

solseo_assert_same( array(), solseo_test_woo_removals(), 'and nothing is removed' );

remove_all_filters( 'solseo_schema_replace_woo' );

/* ---- WITH SCHEMA OFF, WOOCOMMERCE IS LEFT ALONE ------------------------- */
Options::update( array( 'schema_enabled' => false ) );
$GLOBALS['solseo_test_removed'] = array();

solseo_assert( ! Schema::replaces_woo( $solseo_product_context ), 'with structured data switched off, WooCommerce keeps its node' );

Schema::replace_woo( $solseo_product_context );

solseo_assert_same( array(), solseo_test_woo_removals(), 'and the hook is not touched' );

Options::update( array( 'schema_enabled' => true ) );

/*
 * ---- AND THE REMOVAL WAITS FOR THE QUERY -------------------------------
 *
 * WooCommerce builds WC()->structured_data on init at priority 0, and which
 * page this is cannot be known before the query runs. A removal attempted
 * from plugins_loaded finds nothing to remove. The hook is `wp`.
 */
$solseo_schema_src = (string) file_get_contents( SOLSEO_PATH . 'includes/frontend/class-schema.php' );

solseo_assert(
	false !== strpos( $solseo_schema_src, "add_action( 'wp', array( __CLASS__, 'replace_woo' )" ),
	'the WooCommerce removal is booked on wp, after the query and after WooCommerce has built its object'
);

solseo_assert(
	false !== strpos( $solseo_schema_src, "function_exists( 'WC' )" ),
	'and it asks whether WooCommerce is there before touching it'
);

/*
 * ---- ANOTHER SEO PLUGIN'S ORGANISATION BESIDE OURS ----------------------
 *
 * The customer chose SolSEO, so the graph is still published. The settings
 * screen says there are two, and does not name the other one, because the
 * free plugin names no other product.
 */
solseo_assert( ! Schema::rival_active(), 'with no other SEO plugin loaded, nothing is reported' );
solseo_assert_same( '', Schema::rival_notice(), 'and there is no sentence' );

define( 'SEOPRESS_VERSION', '9.9.9' );

solseo_assert( Schema::rival_active(), 'one of the four constants defined means another SEO plugin is publishing schema' );

$solseo_rival_sentence = Schema::rival_notice();

solseo_assert(
	false !== strpos( $solseo_rival_sentence, 'Another SEO plugin is also publishing schema on this site.' ),
	'and the settings screen sentence says so'
);

solseo_assert(
	false !== strpos( $solseo_rival_sentence, 'Turn off one of them so search engines see one description of your business.' ),
	'and says what to do about it'
);

$solseo_general_view = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/views/titles-general.php' );

solseo_assert(
	false !== strpos( $solseo_general_view, 'Schema::rival_notice()' ),
	'the sentence is drawn on the screen where structured data is switched on'
);

/*
 * ---- NO OTHER PRODUCT IS NAMED, ANYWHERE A PERSON READS -----------------
 *
 * The free plugin ships on wordpress.org and names no competitor. Detection
 * code may read a constant, so `SOMETHING_VERSION` tokens are excused before
 * the scan. The allowlist below is every other place a name is allowed to
 * stand, each with its reason, and it does not grow without a DECISIONS entry.
 */
$solseo_name_allowlist = array(
	'includes/redirects/class-slug-watch.php' => 'a code comment on why the redirect manager is free, never printed',
);

$solseo_named    = array( 'Yoast', 'Rank Math', 'RankMath', 'All in One SEO', 'AIOSEO', 'SEOPress' );
$solseo_named_in = array();

$solseo_name_paths = array_merge(
	glob( SOLSEO_PATH . '*.{php,txt}', GLOB_BRACE ),
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' ),
	glob( SOLSEO_PATH . 'assets/*/*.js' )
);

foreach ( $solseo_name_paths as $solseo_name_path ) {
	$solseo_relative = str_replace( '\\', '/', str_replace( SOLSEO_PATH, '', $solseo_name_path ) );

	if ( isset( $solseo_name_allowlist[ $solseo_relative ] ) ) {
		continue;
	}

	$solseo_scan = preg_replace( '/\b[A-Z][A-Z0-9_]*_VERSION\b/', '', (string) file_get_contents( $solseo_name_path ) );

	foreach ( $solseo_named as $solseo_name ) {
		if ( false !== strpos( $solseo_scan, $solseo_name ) ) {
			$solseo_named_in[] = $solseo_relative . ' names ' . $solseo_name;
		}
	}
}

solseo_assert( count( $solseo_name_paths ) > 40, 'the name guard is reading the whole plugin' );
solseo_assert_same( array(), $solseo_named_in, 'no file a person reads names another SEO product' );
