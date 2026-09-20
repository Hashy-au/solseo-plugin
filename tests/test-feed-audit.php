<?php
/**
 * The twelve things Merchant Centre refuses a product for.
 *
 * The rules are pure, so they are driven here with product shapes rather than
 * with a WooCommerce. What the reading does against a real shop is the thing
 * the box has to say.
 *
 * @package SolSEO
 */

use SolSEO\Woo\Feed_Audit;

/**
 * A product with nothing wrong with it.
 *
 * @param array $changes What to make wrong.
 * @return array
 */
function solseo_test_product( array $changes = array() ) {
	return array_merge(
		array(
			'id'                => 12,
			'title'             => 'Atlas carbon arrow, 500 spine',
			'description'       => 'A carbon arrow shaft cut and fletched in Perth, in six spines.',
			'image'             => 'https://example.test/arrow.jpg',
			'price'             => 85.0,
			'purchasable'       => true,
			'gtin'              => '9312345678907',
			'brand'             => 'Atlas',
			'mpn'               => 'ATL-500',
			'categories'        => array( 4 ),
			'weight'            => '0.02',
			'dimensions'        => true,
			'type'              => 'simple',
			'variations_priced' => true,
			'status'            => 'publish',
		),
		$changes
	);
}

/**
 * The rule keys a product fails on.
 *
 * @param array $product A product shape.
 * @return array
 */
function solseo_test_rules( array $product ) {
	$rules = array();

	foreach ( Feed_Audit::inspect( $product ) as $finding ) {
		$rules[] = $finding['rule'];
	}

	sort( $rules );

	return $rules;
}

/* A PRODUCT WITH NOTHING WRONG WITH IT HAS NOTHING WRONG WITH IT. */
solseo_assert_same( array(), solseo_test_rules( solseo_test_product() ), 'a complete product is refused for nothing' );

/* AND EACH OF THE TWELVE IS FOUND ON ITS OWN. */
$solseo_feed_cases = array(
	'title_missing'       => array( 'title' => '' ),
	'title_long'          => array( 'title' => str_repeat( 'a', 151 ) ),
	'title_promotional'   => array( 'title' => 'Atlas carbon arrow, FREE SHIPPING' ),
	'description_missing' => array( 'description' => '' ),
	'description_long'    => array( 'description' => str_repeat( 'a', 5001 ) ),
	'image_missing'       => array( 'image' => '' ),
	'price_missing'       => array( 'price' => 0 ),
	'availability'        => array( 'purchasable' => false ),
	'category_missing'    => array( 'categories' => array() ),
	'variations_priced'   => array(
		'type'              => 'variable',
		'variations_priced' => false,
	),
	'shipping_missing'    => array(
		'weight'     => '',
		'dimensions' => false,
	),
);

foreach ( $solseo_feed_cases as $solseo_rule => $solseo_change ) {
	$solseo_found = solseo_test_rules( solseo_test_product( $solseo_change ) );

	solseo_assert(
		in_array( $solseo_rule, $solseo_found, true ),
		$solseo_rule . ' is found (found ' . implode( ', ', $solseo_found ) . ')'
	);
}

/* THE TWELFTH IS THE ONE WITH TWO WAYS OUT OF IT. */
solseo_assert(
	in_array(
		'identifier_missing',
		solseo_test_rules(
			solseo_test_product(
				array(
					'gtin'  => '',
					'brand' => '',
					'mpn'   => '',
				)
			)
		),
		true
	),
	'a product with no barcode, brand or part number identifies itself to nobody'
);

solseo_assert(
	! in_array(
		'identifier_missing',
		solseo_test_rules( solseo_test_product( array( 'gtin' => '' ) ) ),
		true
	),
	'and a brand with a part number beside it is the other way of answering'
);

solseo_assert(
	! in_array(
		'identifier_missing',
		solseo_test_rules(
			solseo_test_product(
				array(
					'brand' => '',
					'mpn'   => '',
				)
			)
		),
		true
	),
	'and a barcode on its own is enough'
);

/* AND A VARIABLE PRODUCT WHOSE VARIATIONS ARE ALL PRICED IS FINE. */
solseo_assert_same(
	array(),
	solseo_test_rules( solseo_test_product( array( 'type' => 'variable' ) ) ),
	'a variable product with every variation priced is refused for nothing'
);

/* THERE ARE TWELVE RULES, AND EVERY ONE OF THEM HAS A LABEL. */
solseo_assert_same( 12, count( Feed_Audit::rules() ), 'there are twelve readiness checks' );

$solseo_feed_bad = array();

foreach ( array_keys( Feed_Audit::rules() ) as $solseo_rule ) {
	if ( '' === trim( (string) Feed_Audit::rules()[ $solseo_rule ] ) ) {
		$solseo_feed_bad[] = $solseo_rule;
	}
}

solseo_assert_same( array(), $solseo_feed_bad, 'and each of them is called something' );

/* EVERY FINDING SAYS WHAT IS WRONG, WHAT TO DO, AND WHERE THE RULE COMES FROM. */
$solseo_feed_bad = array();

foreach ( array_values( $solseo_feed_cases ) as $solseo_change ) {
	foreach ( Feed_Audit::inspect( solseo_test_product( $solseo_change ) ) as $solseo_finding ) {
		if ( '' === trim( (string) $solseo_finding['says'] ) || '' === trim( (string) $solseo_finding['fix'] ) ) {
			$solseo_feed_bad[] = $solseo_finding['rule'] . ' does not say what to do';
		}

		if ( 0 !== strpos( (string) $solseo_finding['source'], 'https://support.google.com/merchants/' ) ) {
			$solseo_feed_bad[] = $solseo_finding['rule'] . ' cites nothing';
		}
	}
}

solseo_assert_same( array(), $solseo_feed_bad, 'every finding names the fix and cites the specification' );

/* SHOUTING AND SELLING IN A TITLE. */
solseo_assert( Feed_Audit::is_shouting( 'SALE arrow' ), 'a run of capitals is shouting' );
solseo_assert( Feed_Audit::is_shouting( 'Arrow, free shipping' ), 'and an offer in the title is selling' );
solseo_assert( Feed_Audit::is_shouting( 'Best arrow!' ), 'and so is an exclamation mark' );
solseo_assert( ! Feed_Audit::is_shouting( 'Atlas carbon arrow, 500 spine' ), 'and an ordinary title is neither' );
solseo_assert( ! Feed_Audit::is_shouting( 'Arrow with EVA foam case' ), 'and a three letter acronym is not shouting' );

/* WHICH PLUGINS OWN A FEED, READ OFF WHAT THEY CALL THEMSELVES. */
solseo_assert(
	Feed_Audit::is_feed_plugin( 'Product Feed PRO for WooCommerce', 'Generate product feeds for Google Shopping.' ),
	'a product feed plugin is one'
);

solseo_assert(
	Feed_Audit::is_feed_plugin( 'Google for WooCommerce', 'Sync your catalogue to Google Merchant Center.' ),
	'and so is one that never uses the word feed in its name'
);

solseo_assert(
	! Feed_Audit::is_feed_plugin( 'RSS Feed Widget', 'Show an RSS feed of any blog in a widget.' ),
	'and an RSS reader is not a product feed plugin'
);

solseo_assert(
	! Feed_Audit::is_feed_plugin( 'Solkarra Store', 'The fleet product page.' ),
	'and neither is something with nothing to do with feeds'
);
