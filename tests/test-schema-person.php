<?php
/**
 * The author node.
 *
 * @package SolSEO
 */

use SolSEO\Admin\User_Fields;
use SolSEO\Frontend\Schema;

/* A NAME AND AN ADDRESS ARE ENOUGH, AND NOTHING EMPTY IS PUBLISHED. */
$GLOBALS['solseo_users'][7] = array( 'display_name' => 'Jo Bloggs' );

$solseo_plain = Schema::person( 7 );

solseo_assert_same( 'Person', $solseo_plain['@type'], 'an author with nothing filled in still gets a node' );
solseo_assert_same( 'Jo Bloggs', $solseo_plain['name'], 'with their name on it' );
solseo_assert( ! empty( $solseo_plain['@id'] ), 'and an address the article can point at' );

foreach ( array( 'description', 'jobTitle', 'honorificSuffix', 'knowsAbout', 'sameAs' ) as $solseo_key ) {
	solseo_assert(
		! isset( $solseo_plain[ $solseo_key ] ),
		'an empty ' . $solseo_key . ' is left out rather than published empty'
	);
}

/* AND EVERYTHING THAT IS FILLED IN IS THERE. */
$GLOBALS['solseo_users'][8] = array(
	'display_name'           => 'Sam Rivers',
	'description'            => 'Bowyer since 1998.',
	User_Fields::TITLE       => 'Head Bowyer',
	User_Fields::CREDENTIALS => 'BSc',
	User_Fields::KNOWS       => 'horsebows, arrow spine , ',
	User_Fields::PROFILES    => "https://example.test/sam\n\nhttps://example.test/sam-two",
);

$solseo_full = Schema::person( 8 );

solseo_assert_same( 'Bowyer since 1998.', $solseo_full['description'], 'the biography WordPress already holds is used' );
solseo_assert_same( 'Head Bowyer', $solseo_full['jobTitle'], 'the job title is published' );
solseo_assert_same( 'BSc', $solseo_full['honorificSuffix'], 'and the letters after the name' );

solseo_assert_same(
	array( 'horsebows', 'arrow spine' ),
	$solseo_full['knowsAbout'],
	'what they know about is a list, with the empties dropped'
);

solseo_assert_same(
	array( 'https://example.test/sam', 'https://example.test/sam-two' ),
	$solseo_full['sameAs'],
	'and so are the profiles elsewhere'
);

/* NOBODY IS NOT A PERSON. */
solseo_assert( null === Schema::person( 0 ), 'no author means no node' );
solseo_assert( null === Schema::person( 99 ), 'and neither does an author who does not exist' );

/*
 * THE ARTICLE POINTS AT THE PERSON RATHER THAN CARRYING A COPY.
 *
 * Two half descriptions of one person in one graph is how a search engine ends
 * up believing there are two of them.
 */
$solseo_schema_src = (string) file_get_contents( SOLSEO_PATH . 'includes/frontend/class-schema.php' );

solseo_assert(
	false !== strpos( $solseo_schema_src, "'author'           => array( '@id' => self::person_id(" ),
	'the article references the author by address'
);

solseo_assert(
	false === strpos( $solseo_schema_src, "'name'  => get_the_author_meta( 'display_name'" ),
	'and no longer carries a second copy of the name inside itself'
);

/*
 * THE PROFILE FIELDS ARE NOT A SECOND BIOGRAPHY.
 *
 * WordPress has had one since 2003 and every theme prints it. A second one in
 * our own field would disagree with it the day somebody edits one and not the
 * other.
 */
$solseo_fields_src = (string) file_get_contents( SOLSEO_PATH . 'includes/admin/class-user-fields.php' );

solseo_assert(
	false === strpos( $solseo_fields_src, '_solseo_bio' ),
	'there is no SolSEO biography field'
);

solseo_assert(
	false !== strpos( $solseo_schema_src, "get_the_author_meta( 'description'" ),
	'and the node reads the one WordPress already holds'
);

/* AN ADDRESS IN THE PROFILES BOX IS AN ADDRESS, OR IT IS DROPPED. */
solseo_assert_same(
	array( 'https://example.test/one' ),
	User_Fields::clean_profiles( "https://example.test/one\nnot a url\n  \n" ),
	'a line that is not an address does not become one'
);
