<?php
/**
 * Text measurement tests.
 *
 * @package SolSEO
 */

use SolSEO\Analysis\Text;

solseo_assert_same( 4, count( Text::words( "It doesn't matter much" ) ), 'an apostrophe keeps a word whole' );
solseo_assert_same( 3, count( Text::sentences( 'One thing. Then another! And a third?' ) ), 'sentences split on terminators' );
solseo_assert_same( 1, count( Text::sentences( 'Ring us on 08 9321 4000. ' ) ), 'a trailing space does not make a sentence' );

solseo_assert_same( 1, Text::syllables( 'cat' ), 'short words count as one syllable' );
solseo_assert_same( 3, Text::syllables( 'banana' ), 'banana has three syllables' );
solseo_assert_same( 2, Text::syllables( 'table' ), 'a silent e does not add a syllable' );

solseo_assert( Text::is_passive( 'The bow was drawn by the archer.' ), 'an irregular participle after "was" is passive' );
solseo_assert( Text::is_passive( 'The order was shipped yesterday.' ), 'a regular participle after "was" is passive' );
solseo_assert( ! Text::is_passive( 'The archer drew the bow.' ), 'an active sentence is not passive' );
solseo_assert( ! Text::is_passive( 'The shed is red.' ), 'an adjective ending in -ed is not a participle' );
solseo_assert( ! Text::is_passive( 'She was pleased with the result.' ), 'a state does not read as passive' );

solseo_assert_same( 2, Text::count_phrase( 'recurve bow', Text::words( 'A recurve bow is a bow. This recurve bow is ours.' ) ), 'phrases are counted in order' );
solseo_assert_same( 0, Text::count_phrase( 'recurve bow', Text::words( 'The bow is recurve.' ) ), 'words out of order are not the phrase' );

$ease = Text::reading_ease( str_repeat( 'The cat sat on the mat. ', 8 ) );
solseo_assert( $ease > 80, 'short words in short sentences read easily' );
solseo_assert_same( null, Text::reading_ease( 'Too short to measure.' ), 'a fragment is not measured' );

solseo_assert( Text::pixel_width( 'WWWWW', 20 ) > Text::pixel_width( 'iiiii', 20 ), 'wide letters measure wider than narrow ones' );

$sentences = Text::sentences( 'We tested it. However, it broke. We fixed it.' );
solseo_assert_same( 33.3, Text::transition_share( $sentences ), 'one sentence in three carries a transition word' );

solseo_assert_same( array( 'recurve', 'bow' ), Text::without_stop_words( Text::words( 'the recurve bow' ) ), 'stop words are dropped' );
