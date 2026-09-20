<?php
/**
 * The six content level accessibility checks.
 *
 * Every one of them names an element and a rule, because "this page has an
 * accessibility problem" is a sentence nobody can act on.
 *
 * @package SolSEO
 */

use SolSEO\A11y\Editor_Checks;

/* AN IMAGE WITH NO ALT AT ALL IS A FINDING, AND alt="" IS NOT. */
$solseo_a11y = Editor_Checks::images( '<img src="cat.jpg"><img src="rule.png" alt=""><img src="dog.jpg" alt="A dog">' );

solseo_assert_same( 1, count( $solseo_a11y ), 'one image in three has nothing saying what it is' );
solseo_assert_same( 'image_alt', $solseo_a11y[0]['rule'], 'and the finding names the rule' );
solseo_assert( false !== strpos( $solseo_a11y[0]['element'], 'cat.jpg' ), 'and names the element it found' );
solseo_assert_same( '1.1.1', $solseo_a11y[0]['guideline'], 'and the guideline behind it' );

solseo_assert_same(
	array(),
	Editor_Checks::images( '<img src="swirl.png" role="presentation">' ),
	'a picture that declares itself decoration is left alone'
);

/* A SKIPPED HEADING LEVEL IS A FINDING, AND COMING BACK UP IS NOT. */
solseo_assert_same(
	array(),
	Editor_Checks::headings( '<h2>One</h2><h3>Two</h3><h2>Three</h2>' ),
	'going down one and back up again is ordinary writing'
);

$solseo_a11y = Editor_Checks::headings( '<h2>One</h2><h4>Two</h4>' );

solseo_assert_same( 1, count( $solseo_a11y ), 'a level four under a level two is a level missing' );
solseo_assert_same( 'heading_order', $solseo_a11y[0]['rule'], 'and it says which rule' );
solseo_assert( false !== strpos( $solseo_a11y[0]['says'], 'level 4' ), 'and says which level it found' );

solseo_assert_same(
	array(),
	Editor_Checks::headings( '<h4>First thing on the page</h4><h5>Under it</h5>' ),
	'the first heading on a page sets the level rather than breaking a rule'
);

/* LINK WORDS THAT DESCRIBE NOTHING. */
$solseo_a11y = Editor_Checks::links(
	'<a href="/a">Click here</a><a href="/b">Our returns policy</a><a href="/c">read more</a>'
);

solseo_assert_same( 2, count( $solseo_a11y ), 'two of those three links say nothing about where they go' );
solseo_assert_same( 'link_text', $solseo_a11y[0]['rule'], 'and the finding names the rule' );
solseo_assert( false !== strpos( $solseo_a11y[0]['says'], 'Click here' ), 'and quotes the words it read' );

solseo_assert_same(
	array(),
	Editor_Checks::links( '<a href="/a" aria-label="Read our returns policy">Read more</a>' ),
	'a link carrying its own label is left alone, because the label is what is read out'
);

solseo_assert_same(
	array(),
	Editor_Checks::links( '<a href="/a"><img src="logo.png" alt="Home"></a>' ),
	'and a link whose words are an image is left to the image rule'
);

$solseo_a11y = Editor_Checks::links( '<a href="/a"></a>' );

solseo_assert_same( 1, count( $solseo_a11y ), 'a link with nothing in it has nothing to read out' );

/* COLOURS TOO CLOSE TOGETHER, AND ONLY WHERE BOTH ARE THERE TO READ. */
solseo_assert_same( array( 255, 255, 255 ), Editor_Checks::colour( '#fff' ), 'a three digit hex colour is read' );
solseo_assert_same( array( 17, 34, 51 ), Editor_Checks::colour( '#112233' ), 'and a six digit one' );
solseo_assert_same( array( 10, 20, 30 ), Editor_Checks::colour( 'rgb(10, 20, 30)' ), 'and rgb()' );
solseo_assert_same( null, Editor_Checks::colour( 'rgba(10, 20, 30, 0.4)' ), 'and anything see through is refused' );
solseo_assert_same( null, Editor_Checks::colour( 'var(--brand)' ), 'and so is a variable nobody here can resolve' );

solseo_assert_same( 21.0, Editor_Checks::ratio( array( 0, 0, 0 ), array( 255, 255, 255 ) ), 'black on white is 21 to 1' );
solseo_assert_same( 1.0, Editor_Checks::ratio( array( 90, 90, 90 ), array( 90, 90, 90 ) ), 'and a colour on itself is 1 to 1' );

$solseo_a11y = Editor_Checks::contrast( '<p style="color:#999;background-color:#fff">Faint</p>' );

solseo_assert_same( 1, count( $solseo_a11y ), 'light grey on white is too close to read' );
solseo_assert_same( 'contrast', $solseo_a11y[0]['rule'], 'and the finding names the rule' );

solseo_assert_same(
	array(),
	Editor_Checks::contrast( '<p style="color:#222;background-color:#fff">Dark</p>' ),
	'near black on white is fine'
);

solseo_assert_same(
	array(),
	Editor_Checks::contrast( '<p style="color:#999">Faint, on who knows what</p>' ),
	'and a colour with no background beside it is not judged at all'
);

/* A TABLE WITH NO HEADER CELLS. */
solseo_assert_same(
	1,
	count( Editor_Checks::tables( '<table><tr><td>1</td><td>2</td></tr></table>' ) ),
	'a table with no th says nothing about what its numbers are'
);

solseo_assert_same(
	array(),
	Editor_Checks::tables( '<table><tr><th scope="col">Size</th></tr><tr><td>Large</td></tr></table>' ),
	'and one with headers is fine'
);

solseo_assert_same(
	array(),
	Editor_Checks::tables( '<table role="presentation"><tr><td>Left</td><td>Right</td></tr></table>' ),
	'and a table that declares itself layout is not read out at all'
);

/* A FORM FIELD NOTHING NAMES. */
solseo_assert_same(
	1,
	count( Editor_Checks::fields( '<input type="text" name="q">' ) ),
	'a text box with no name of any kind is read out as an empty box'
);

solseo_assert_same(
	array(),
	Editor_Checks::fields( '<label for="q">Search</label><input type="text" id="q">' ),
	'a label pointing at the id is what names a field'
);

solseo_assert_same(
	array(),
	Editor_Checks::fields( '<input type="text" aria-label="Search">' ),
	'and so is an aria-label'
);

solseo_assert_same(
	array(),
	Editor_Checks::fields( '<input type="hidden" name="nonce"><input type="submit" value="Go">' ),
	'and a hidden field and a button are not fields anybody types in'
);

/* AND THE WHOLE READING PUTS THEM TOGETHER WITHOUT LOSING ANY. */
$solseo_a11y = Editor_Checks::run(
	'<h2>Sizes</h2><h4>Large</h4><img src="a.jpg"><a href="/b">click here</a>'
	. '<table><tr><td>1</td></tr></table><input type="text" name="q">'
	. '<p style="color:#aaa;background:#fff">Faint</p>'
);

$solseo_rules = array();

foreach ( $solseo_a11y as $solseo_finding ) {
	$solseo_rules[ $solseo_finding['rule'] ] = true;
}

ksort( $solseo_rules );

solseo_assert_same(
	array( 'contrast', 'field_label', 'heading_order', 'image_alt', 'link_text', 'table_header' ),
	array_keys( $solseo_rules ),
	'one reading finds all six kinds'
);

foreach ( $solseo_a11y as $solseo_finding ) {
	solseo_assert( '' !== $solseo_finding['element'], 'every finding names an element' );
	solseo_assert( '' !== $solseo_finding['guideline'], 'and every finding names a rule' );
	solseo_assert(
		Editor_Checks::rule_label( $solseo_finding['rule'] ) !== $solseo_finding['rule'],
		'and every rule has a label written for it'
	);
}

solseo_assert_same( array(), Editor_Checks::run( '<p>Just some words.</p>' ), 'and a page with nothing wrong has nothing to say' );

/* AND THE SENTENCE ABOUT HOW FAR IT READS IS THERE AND SAYS SO. */
solseo_assert(
	false !== strpos( Editor_Checks::covers(), 'theme' ),
	'the covering sentence says the theme is not read'
);

solseo_assert(
	false !== strpos( Editor_Checks::covers(), 'not an audit' ),
	'and that this is not an audit'
);
