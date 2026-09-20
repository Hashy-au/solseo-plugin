<?php
/**
 * The questions and answers block.
 *
 * @package SolSEO
 */

use SolSEO\Frontend\Faq;

/* A PAIR NEEDS BOTH HALVES. */
$solseo_pairs = Faq::pairs(
	array(
		array(
			'question' => 'How long does delivery take?',
			'answer'   => 'Two to four days across Australia.',
		),
		array(
			'question' => 'Is there a warranty?',
			'answer'   => '',
		),
		array(
			'question' => '',
			'answer'   => 'An answer to nothing.',
		),
		array(
			'question' => 'Do you ship overseas?',
			'answer'   => '<p>Yes, to New Zealand.</p>',
		),
	)
);

solseo_assert_same( 2, count( $solseo_pairs ), 'a question with no answer, and an answer with no question, are both left out' );
solseo_assert_same( 'How long does delivery take?', $solseo_pairs[0]['question'], 'and the complete ones are kept in order' );
solseo_assert_same( 'Do you ship overseas?', $solseo_pairs[1]['question'], 'including the last one' );

/* AN ANSWER OF NOTHING BUT MARKUP IS NOT AN ANSWER. */
$solseo_empty = Faq::pairs(
	array(
		array(
			'question' => 'Anything?',
			'answer'   => '<p> </p>',
		),
	)
);

solseo_assert_same( array(), $solseo_empty, 'an answer that is markup and whitespace is not an answer' );

/*
 * THE SAME QUESTION TWICE IS ONE QUESTION.
 *
 * Two answers to one question is invalid markup and a confusing page, so the
 * first one wins and the editor says so while it is being written.
 */
$solseo_dupes = Faq::pairs(
	array(
		array(
			'question' => 'Do you ship overseas?',
			'answer'   => 'Yes.',
		),
		array(
			'question' => 'do you ship OVERSEAS?',
			'answer'   => 'No.',
		),
	)
);

solseo_assert_same( 1, count( $solseo_dupes ), 'the same question twice is published once' );
solseo_assert_same( 'Yes.', $solseo_dupes[0]['answer'], 'and it is the first answer that stands' );

/*
 * ONE READER, TWO CALLERS.
 *
 * The page and the structured data are built from the same pairs, so they can
 * never come to disagree about what the questions are.
 */
$solseo_render_src = (string) file_get_contents( SOLSEO_PATH . 'includes/blocks/faq/render.php' );
$solseo_faq_src    = (string) file_get_contents( SOLSEO_PATH . 'includes/frontend/class-faq.php' );

solseo_assert(
	false !== strpos( $solseo_render_src, 'Faq::pairs(' ),
	'the front end renders from the shared reader'
);

solseo_assert(
	false !== strpos( $solseo_faq_src, 'self::pairs( $items )' ),
	'and the structured data is built from it too'
);

/* NOTHING PROMISES A RICH RESULT. */
$solseo_block_src = (string) file_get_contents( SOLSEO_PATH . 'assets/js/block-faq.js' );

foreach ( array( 'rich result', 'rich snippet', 'will appear in Google', 'guarantee' ) as $solseo_claim ) {
	solseo_assert(
		false === stripos( $solseo_block_src, $solseo_claim ),
		'the block promises no ' . $solseo_claim
	);
}

solseo_assert(
	false !== stripos( $solseo_block_src, 'not a promise' ),
	'and says out loud that it is not one'
);

/*
 * THE BLOCK IS RENDERED IN PHP, SO ITS MARKUP CAN BE CORRECTED LATER.
 *
 * A block that saves its markup into the post turns every page using it into a
 * validation error the day we improve the markup.
 */
solseo_assert(
	false !== strpos( $solseo_block_src, 'save: function () {' ) && false !== strpos( $solseo_block_src, 'return null;' ),
	'the block saves nothing but its attributes'
);

$solseo_json = json_decode( (string) file_get_contents( SOLSEO_PATH . 'includes/blocks/faq/block.json' ), true );

solseo_assert( is_array( $solseo_json ), 'block.json is readable' );
solseo_assert_same( 'solseo/faq', $solseo_json['name'], 'and names the block' );
solseo_assert_same( 'file:./render.php', $solseo_json['render'], 'and points at the renderer' );
solseo_assert_same( 'solseo-block-faq', $solseo_json['editorScript'], 'and at a handle rather than a compiled file' );
