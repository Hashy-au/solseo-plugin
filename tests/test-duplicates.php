<?php
/**
 * Two pages going for one phrase, and the pair that is not a duplicate.
 *
 * @package SolSEO
 */

use SolSEO\Analysis\Duplicates;

/* THE TWO SIDES OF THE SITE. */
solseo_assert_same( 'shop', Duplicates::side( 'product' ), 'a product is the shop side' );
solseo_assert_same( 'editorial', Duplicates::side( 'post' ), 'a post is the editorial side' );
solseo_assert_same( 'editorial', Duplicates::side( 'page' ), 'and so is a page' );
solseo_assert_same( 'editorial', Duplicates::side( 'anything_else' ), 'and so is anything nobody has heard of' );

solseo_assert( Duplicates::competing( 'post', 'page' ), 'two editorial pages compete' );
solseo_assert( Duplicates::competing( 'product', 'product' ), 'two products compete' );
solseo_assert( ! Duplicates::competing( 'product', 'post' ), 'a listing and an article do not' );

/* A PHRASE IS THE SAME PHRASE WHATEVER SOMEBODY TYPED. */
solseo_assert_same( 'recurve bow', Duplicates::normalise( '  Recurve   Bow ' ), 'case and spacing are typing, not meaning' );
solseo_assert_same( '', Duplicates::normalise( '   ' ), 'and nothing is nothing' );

/* WHICH PAGES IN A SET ARE ACTUALLY COMPETING. */
$solseo_dupes = Duplicates::group(
	array(
		array(
			'id'   => 1,
			'type' => 'product',
			'side' => 'shop',
		),
		array(
			'id'   => 2,
			'type' => 'post',
			'side' => 'editorial',
		),
	)
);

solseo_assert_same( array(), $solseo_dupes['competing'], 'a listing and an article about one phrase are not a duplicate' );
solseo_assert_same( 2, count( $solseo_dupes['alongside'] ), 'they are a pair standing alongside each other' );

$solseo_dupes = Duplicates::group(
	array(
		array(
			'id'   => 1,
			'type' => 'post',
			'side' => 'editorial',
		),
		array(
			'id'   => 2,
			'type' => 'page',
			'side' => 'editorial',
		),
	)
);

solseo_assert_same( 2, count( $solseo_dupes['competing'] ), 'two editorial pages on one phrase are competing' );
solseo_assert_same( array(), $solseo_dupes['alongside'], 'and nothing is standing alongside' );

$solseo_dupes = Duplicates::group(
	array(
		array(
			'id'   => 1,
			'type' => 'product',
			'side' => 'shop',
		),
		array(
			'id'   => 2,
			'type' => 'product',
			'side' => 'shop',
		),
		array(
			'id'   => 3,
			'type' => 'post',
			'side' => 'editorial',
		),
	)
);

solseo_assert_same( 2, count( $solseo_dupes['competing'] ), 'two listings compete even with an article beside them' );
solseo_assert_same( 1, count( $solseo_dupes['alongside'] ), 'and the article stands alongside both' );

/* AND THE TWO SENTENCES ARE DIFFERENT SENTENCES. */
$solseo_says = Duplicates::says(
	array(
		'phrase'    => 'recurve bow',
		'competing' => array(
			array(
				'id'    => 2,
				'title' => 'Choosing a recurve bow',
				'type'  => 'post',
				'side'  => 'editorial',
			),
		),
		'alongside' => array(),
	)
);

solseo_assert( false !== strpos( $solseo_says, 'Choosing a recurve bow' ), 'the warning names the other page' );
solseo_assert( false !== strpos( $solseo_says, 'recurve bow' ), 'and the phrase' );
solseo_assert( false === stripos( $solseo_says, 'usually fine' ), 'and it does not say it is fine' );

$solseo_says = Duplicates::says(
	array(
		'phrase'    => 'recurve bow',
		'competing' => array(),
		'alongside' => array(
			array(
				'id'    => 9,
				'title' => 'Recurve bows',
				'type'  => 'product',
				'side'  => 'shop',
			),
		),
	)
);

solseo_assert( false !== strpos( $solseo_says, 'Recurve bows' ), 'the note names the page on the other side' );
solseo_assert( false !== strpos( $solseo_says, 'usually fine' ), 'and says it is usually fine' );

solseo_assert_same(
	'',
	Duplicates::says(
		array(
			'phrase'    => 'recurve bow',
			'competing' => array(),
			'alongside' => array(),
		)
	),
	'and a phrase nobody else is going for says nothing at all'
);

/* A PAGE WITH NO PHRASE IS NOT ASKED ABOUT. */
$solseo_dupes = Duplicates::for_post( 5, '   ', 'post' );

solseo_assert_same( '', $solseo_dupes['phrase'], 'an empty focus keyword is an empty phrase' );
solseo_assert_same( array(), $solseo_dupes['competing'], 'and nothing is looked up for it' );
