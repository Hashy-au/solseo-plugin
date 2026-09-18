<?php
/**
 * Template placeholder tests.
 *
 * @package SolSEO
 */

use SolSEO\Context;
use SolSEO\Options;
use SolSEO\Variables;

Options::flush();

$context = array(
	'type'      => 'front_page',
	'object_id' => 0,
	'post_type' => '',
	'taxonomy'  => '',
	'term'      => null,
);

solseo_assert_same(
	'Asiatic Bows - Traditional archery, shipped from Perth',
	Variables::apply( '{sitename} {sep} {sitedesc}', $context ),
	'the front page template fills in'
);

solseo_assert_same(
	'Asiatic Bows',
	Variables::apply( '{sitename} {sep} {excerpt}', $context ),
	'a placeholder that resolves to nothing takes its separator with it'
);

solseo_assert_same(
	'Asiatic Bows',
	Variables::apply( '{sep} {sitename} {sep}', $context ),
	'separators at either end are trimmed'
);

solseo_assert_same(
	'Asiatic Bows - Asiatic Bows',
	Variables::apply( '{sitename} {sep} {tag} {sep} {sitename}', $context ),
	'two separators in a row collapse into one'
);

solseo_assert_same( gmdate( 'Y' ), Variables::apply( '{currentyear}', $context ), 'the year resolves' );
solseo_assert_same( '', Variables::apply( '   ', $context ), 'an empty template stays empty' );
solseo_assert_same( 'Left {unknown} alone', Variables::apply( 'Left {unknown} alone', $context ), 'an unknown placeholder is left where it is' );
