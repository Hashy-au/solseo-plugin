<?php
/**
 * What this plugin writes into a page cannot be closed by what somebody typed,
 * and it does not write its own style or script tags.
 *
 * Both of these were named in the WordPress.org review of solseo-2.0.0.
 *
 * THE STRUCTURED DATA. The JSON-LD graph is built out of titles, descriptions
 * and product fields, which is to say out of whatever anybody with an editor's
 * account typed. It was encoded with JSON_UNESCAPED_SLASHES, so a forward slash
 * came through as itself, so a title holding the six characters of a closing
 * script tag ended the element and everything after it in that title was markup
 * on the page. The flag is gone and JSON_HEX_TAG is on, and the test below
 * proves it with the payload rather than by reading the call.
 *
 * THE STYLE AND SCRIPT TAGS. The sitemap transform carried a style block, which
 * is the thing the directory asks plugins to enqueue instead. Its CSS is a file
 * now. Nothing else in the plugin may grow one: a static tag written into
 * output is a stylesheet or a script that no site can dequeue, defer, version
 * or put a content security policy nonce on.
 *
 * @package SolSEO
 */

/*
 * A GRAPH BUILT OUT OF AN ATTACK. Every string here is one somebody could type
 * into the title field, and none of them may come back out of the encoder as
 * markup.
 */
$solseo_nasty = array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array(
			'@type'       => 'WebPage',
			'@id'         => 'https://example.test/page/#webpage',
			'name'        => 'Closing tag: </script><img src=x onerror=alert(1)>',
			'description' => 'Comment markers: <!-- and --> and a CDATA close ]]>',
			'url'         => 'https://example.test/a/b/c/',
		),
	),
);

$solseo_encoded = wp_json_encode( $solseo_nasty, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );

solseo_assert(
	false === stripos( $solseo_encoded, '</script' ),
	'a closing script tag typed into a field cannot appear in the encoded graph'
);

solseo_assert(
	false === strpos( $solseo_encoded, '<' ) && false === strpos( $solseo_encoded, '>' ),
	'no angle bracket survives encoding, so neither half of a tag can be written'
);

// Built from chr( 92 ) so the needle cannot itself be read as an escape.
$solseo_open  = chr( 92 ) . 'u003C';
$solseo_close = chr( 92 ) . 'u003E';

solseo_assert(
	false !== strpos( $solseo_encoded, $solseo_open ) && false !== strpos( $solseo_encoded, $solseo_close ),
	'the brackets are escaped rather than dropped, so the text a person typed is still there'
);

solseo_assert_same(
	$solseo_nasty,
	json_decode( $solseo_encoded, true ),
	'a parser reads back exactly what went in, so the escaping costs nothing'
);

/*
 * AND THE SAME PAYLOAD THROUGH THE REAL RENDERER. The encoder above is the
 * arithmetic; this is the plugin. If somebody puts the flag back, this is the
 * assertion that says so.
 */
ob_start();
wp_print_inline_script_tag( $solseo_encoded, array( 'type' => 'application/ld+json' ) );
$solseo_tag = (string) ob_get_clean();

solseo_assert(
	1 === substr_count( $solseo_tag, '<script' ) && 1 === substr_count( $solseo_tag, '</script>' ),
	'the printed tag has exactly one opening and one closing script tag'
);

solseo_assert(
	false !== strpos( $solseo_tag, 'type="application/ld+json"' ),
	'the tag says what kind of document it holds'
);

// Read as code, because the comment above that call names the flag it dropped.
$solseo_render = solseo_code_only( (string) file_get_contents( SOLSEO_PATH . 'includes/frontend/class-schema.php' ) );

solseo_assert(
	false === strpos( $solseo_render, 'JSON_UNESCAPED_SLASHES' ),
	'the structured data is not encoded with JSON_UNESCAPED_SLASHES'
);

solseo_assert(
	false !== strpos( $solseo_render, 'wp_print_inline_script_tag' ),
	'the structured data goes out through the WordPress function, so a content security policy nonce reaches it'
);

/*
 * NO STYLE OR SCRIPT TAG IS WRITTEN INTO OUTPUT.
 *
 * Attributes such as style="" are a different thing and are not counted: a
 * custom property on one element is data, not a stylesheet.
 *
 * THE ALLOWLIST IS EMPTY, and widening it needs a DECISIONS entry first.
 */
$solseo_tag_files = array_merge(
	glob( SOLSEO_PATH . '*.php' ),
	glob( SOLSEO_PATH . 'includes/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*.php' ),
	glob( SOLSEO_PATH . 'includes/*/*/*.php' )
);

solseo_assert( count( $solseo_tag_files ) > 100, 'the guard is reading the whole plugin' );

$solseo_written_tags = array();

foreach ( $solseo_tag_files as $solseo_tag_file ) {
	$solseo_code = solseo_without_comments( (string) file_get_contents( $solseo_tag_file ) );

	/*
	 * A tag inside a pattern being searched for, such as '#<script\b[^>]*>#i',
	 * is this plugin reading somebody else's markup rather than writing its
	 * own. Those carry a regular expression escape straight after the name. A
	 * tag being written carries a space, a greater-than or a quote.
	 */
	if ( preg_match_all( '/<(script|style)(?=[\s>"\'])/i', $solseo_code, $solseo_hits ) ) {
		$solseo_written_tags[] = str_replace( SOLSEO_PATH, '', $solseo_tag_file )
			. ': ' . implode( ', ', $solseo_hits[0] );
	}
}

solseo_assert_same(
	array(),
	$solseo_written_tags,
	'no PHP file writes a style or script tag of its own'
);

/*
 * THE SITEMAP TRANSFORM NAMES A REAL FILE. The tag sweep above would pass on a
 * transform with no styling in it at all, which would be a regression of a
 * different kind, so the link and the file it names are both checked.
 */
$solseo_xsl = \SolSEO\Sitemaps\Writer::stylesheet();

solseo_assert(
	false !== strpos( $solseo_xsl, 'assets/css/sitemap.css' ),
	'the sitemap transform links the stylesheet file'
);

solseo_assert(
	file_exists( SOLSEO_PATH . 'assets/css/sitemap.css' ),
	'the stylesheet the sitemap transform names is in the plugin'
);

solseo_assert(
	false !== strpos( (string) file_get_contents( SOLSEO_PATH . 'assets/css/sitemap.css' ), 'border-collapse' ),
	'the sitemap stylesheet still holds the table rules the transform needs'
);

solseo_assert(
	null !== simplexml_load_string( $solseo_xsl ),
	'the transform is still well formed XML with the link in it'
);
