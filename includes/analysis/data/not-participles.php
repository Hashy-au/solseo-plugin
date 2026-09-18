<?php
/**
 * Words ending in -ed that are not past participles, plus the small group of
 * participles that only ever describe a state.
 *
 * Without these, "the shed is red" and "she was pleased" both read as passive.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing, WordPress.Arrays.MultipleStatementAlignment -- a word list reads better packed than one entry per line.

return array(
	'aged', 'alleged', 'ashamed', 'bed', 'beloved', 'blessed', 'crooked', 'cursed',
	'dazed', 'dead', 'deed', 'dogged', 'feed', 'good', 'hatred', 'hundred', 'indeed',
	'jagged', 'learned', 'legged', 'naked', 'need', 'proceed', 'ragged', 'red',
	'rugged', 'sacred', 'seed', 'shed', 'sled', 'speed', 'wicked', 'wretched',

	'annoyed', 'bored', 'delighted', 'depressed', 'disappointed', 'embarrassed',
	'excited', 'frightened', 'interested', 'pleased', 'relaxed', 'relieved',
	'satisfied', 'scared', 'surprised', 'tired', 'worried',
);
