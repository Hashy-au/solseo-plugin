<?php
/**
 * Reading the visible text off a page that a builder drew.
 *
 * @package SolSEO
 */

use SolSEO\Content\Readers;
use SolSEO\Content\Reduce;

/**
 * Load one of the stored builder fixtures.
 *
 * Each fixture is that builder's characteristic wrapper markup with ordinary
 * content inside it. What they prove is that the reduction finds the content
 * through the chrome, which is the part that is ours to get right.
 *
 * @param string $name Builder slug.
 * @return string
 */
function solseo_fixture( $name ) {
	return (string) file_get_contents( __DIR__ . '/fixtures/builders/' . $name . '.html' );
}

/*
 * NO READER GOES AT A BUILDER'S STORED SHAPE.
 *
 * The JSON behind Elementor, Bricks and Beaver changes on their release
 * schedule rather than ours, so a reader that parses it is broken by somebody
 * else's Tuesday. Reading it through their own renderer, or through
 * the_content, means their upgrade is their problem.
 */
$solseo_stored_shapes = array(
	'_elementor_data',
	'_elementor_edit_mode',
	'_bricks_page_content',
	'_fl_builder_data',
	'_fl_builder_draft',
	'breakdance_data',
	'maybe_unserialize',
	'$wpdb',
);

foreach ( glob( SOLSEO_PATH . 'includes/content/readers/*.php' ) as $solseo_reader_file ) {
	$solseo_reader_src = (string) file_get_contents( $solseo_reader_file );
	$solseo_reader_src = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $solseo_reader_src );

	foreach ( $solseo_stored_shapes as $solseo_shape ) {
		solseo_assert(
			false === strpos( (string) $solseo_reader_src, $solseo_shape ),
			basename( $solseo_reader_file ) . ' does not go at ' . $solseo_shape
		);
	}
}

/*
 * A BUILDER THAT IS NOT INSTALLED COSTS ONE CHECK.
 *
 * Six readers run on every score on every site. If asking "is Breakdance here"
 * cost a query, the five builders a site does not have would cost five.
 */
foreach ( Readers::all() as $solseo_reader ) {
	$solseo_file = ( new ReflectionClass( $solseo_reader ) )->getFileName();
	$solseo_src  = (string) file_get_contents( $solseo_file );

	preg_match( '#function active\(\)\s*\{(.*?)\n\t\}#s', $solseo_src, $solseo_body );

	$solseo_active = isset( $solseo_body[1] ) ? preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $solseo_body[1] ) : '';

	solseo_assert( '' !== trim( (string) $solseo_active ), $solseo_reader::slug() . ' answers whether it is active' );

	solseo_assert(
		(bool) preg_match( '#^\s*return [^;]+;\s*$#s', (string) $solseo_active ),
		$solseo_reader::slug() . ' answers that in one expression, so five absent builders cost five checks'
	);

	foreach ( array( 'get_post', 'get_option', 'query', 'require', 'include', 'file_get_contents' ) as $solseo_expensive ) {
		solseo_assert(
			false === strpos( (string) $solseo_active, $solseo_expensive ),
			$solseo_reader::slug() . ' does not reach for ' . $solseo_expensive . ' to find out whether its builder is here'
		);
	}
}

/* AND WITH NO BUILDER PRESENT, NOTHING OWNS THE POST. */
$solseo_post = solseo_test_post(
	array(
		'ID'            => 501,
		'post_content'  => '<p>Plain content, written in the block editor.</p>',
		'post_modified' => '2026-09-19 09:00:00',
	)
);

solseo_assert_same( '', Readers::for_post( $solseo_post ), 'with no builder installed, no reader claims the post' );
solseo_assert_same( '', Readers::html( $solseo_post ), 'and the extraction adds nothing to what WordPress already stores' );

/*
 * THE REDUCTION FINDS THE CONTENT THROUGH THE CHROME.
 *
 * Six fixtures, one per builder. What comes back is the tags the checks
 * actually read: headings, paragraphs, links and images. Everything else is
 * wrapper, and a wrapper counted as content is what makes a builder page score
 * as though it were written by a committee.
 */
$solseo_expected = array(
	'elementor'  => array(
		'text'     => 'Recurve bows for beginners We fit every bow in the workshop in Fremantle before it ships. Book a fitting',
		'headings' => array( array( 1, 'Recurve bows for beginners' ) ),
		'links'    => array( 'https://example.test/contact/' ),
		'alt'      => 'A recurve bow on the workshop bench',
	),
	'bricks'     => array(
		'text'     => 'Longbow servicing String replacement, tiller checks and a new serving, done in a week. See what is included',
		'headings' => array( array( 2, 'Longbow servicing' ) ),
		'links'    => array( '/services/longbow/' ),
		'alt'      => 'A longbow on the tillering tree',
	),
	'beaver'     => array(
		'text'     => 'Arrows cut to length Tell us your draw length and we cut and fletch to match.',
		'headings' => array( array( 2, 'Arrows cut to length' ) ),
		'links'    => array(),
		'alt'      => '',
	),
	'breakdance' => array(
		'text'     => 'Bow cases and quivers Hard cases for travel, soft cases for the club, both in stock. Browse cases',
		'headings' => array( array( 2, 'Bow cases and quivers' ) ),
		'links'    => array( '/shop/cases/' ),
		'alt'      => '',
	),
	'divi'       => array(
		'text'     => 'Club nights Thursday from six, all welcome, and bows are available to borrow.',
		'headings' => array( array( 2, 'Club nights' ) ),
		'links'    => array(),
		'alt'      => '',
	),
	'wpbakery'   => array(
		'text'     => 'Lessons for juniors A six week course on Saturday mornings, for ages nine and up.',
		'headings' => array( array( 3, 'Lessons for juniors' ) ),
		'links'    => array(),
		'alt'      => '',
	),
);

foreach ( $solseo_expected as $solseo_slug => $solseo_want ) {
	$solseo_reduced = Reduce::document( solseo_fixture( $solseo_slug ) );

	solseo_assert_same(
		$solseo_want['text'],
		\SolSEO\Content::plain( $solseo_reduced ),
		$solseo_slug . ' reads as the words a visitor sees'
	);

	$solseo_got_headings = array();

	foreach ( \SolSEO\Content::headings( $solseo_reduced ) as $solseo_heading ) {
		$solseo_got_headings[] = array( $solseo_heading['level'], $solseo_heading['text'] );
	}

	solseo_assert_same( $solseo_want['headings'], $solseo_got_headings, $solseo_slug . ' keeps its headings at their own level' );

	$solseo_got_links = \SolSEO\Content::links( $solseo_reduced );

	solseo_assert_same(
		$solseo_want['links'],
		array_merge( $solseo_got_links['internal'], $solseo_got_links['external'] ),
		$solseo_slug . ' keeps the addresses it links to'
	);

	$solseo_images  = \SolSEO\Content::images( $solseo_reduced );
	$solseo_got_alt = $solseo_images ? $solseo_images[0]['alt'] : '';

	solseo_assert_same( $solseo_want['alt'], $solseo_got_alt, $solseo_slug . ' keeps the alt text on its images' );

	solseo_assert(
		'' !== trim( \SolSEO\Content::first_paragraph( $solseo_reduced ) ),
		$solseo_slug . ' has an opening paragraph, which half the readability checks need'
	);
}

/* STYLE AND SCRIPT ARE NOT WORDS. */
$solseo_elementor = Reduce::document( solseo_fixture( 'elementor' ) );

solseo_assert( false === strpos( $solseo_elementor, 'padding-top' ), 'the inline stylesheet is not counted as content' );
solseo_assert( false === strpos( $solseo_elementor, 'elementorFrontendConfig' ), 'nor is the inline script' );
solseo_assert( false === strpos( $solseo_elementor, 'data-settings' ), 'nor is a settings attribute full of JSON' );
solseo_assert( false === strpos( $solseo_elementor, 'elementor-widget-wrap' ), 'and the wrapper classes are gone with their wrappers' );

/* A HEADING WITH A SPAN INSIDE IT IS STILL ONE HEADING. */
$solseo_beaver = Reduce::document( solseo_fixture( 'beaver' ) );

solseo_assert_same( 1, substr_count( $solseo_beaver, '<h2>' ), 'the span inside the heading is unwrapped rather than left to split it' );

/**
 * A builder that only exists in here, so the cache and the fail safe can be
 * put through their paces without six commercial plugins installed.
 */
class Solseo_Test_Builder implements \SolSEO\Content\Reader {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public static function slug() {
		return 'test-builder';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public static function label() {
		return 'Test builder';
	}

	/**
	 * Whether it is here.
	 *
	 * @return bool
	 */
	public static function active() {
		return ! empty( $GLOBALS['solseo_test_builder']['active'] );
	}

	/**
	 * Whether it drew this post.
	 *
	 * @param object $post Post.
	 * @return bool
	 */
	public static function owns( $post ) {
		unset( $post );

		return ! empty( $GLOBALS['solseo_test_builder']['owns'] );
	}

	/**
	 * Its version.
	 *
	 * @return string
	 */
	public static function version() {
		return (string) $GLOBALS['solseo_test_builder']['version'];
	}

	/**
	 * What it renders.
	 *
	 * @param object $post Post.
	 * @return string
	 * @throws RuntimeException When the test asks it to fall over.
	 */
	public static function html( $post ) {
		unset( $post );

		$answer = $GLOBALS['solseo_test_builder']['html'];

		if ( $answer instanceof RuntimeException ) {
			throw $answer;
		}

		return (string) $answer;
	}
}

add_filter(
	'solseo_content_readers',
	static function ( $readers ) {
		array_unshift( $readers, 'Solseo_Test_Builder' );

		return $readers;
	}
);

// Everything from here until the front end section is the admin asking, which
// is the only place a builder is ever asked to draw a page.
$GLOBALS['solseo_test_is_admin'] = true;

/*
 * THE CACHE KEY CARRIES THE CONTENT AND THE BUILDER'S VERSION.
 *
 * Without the version, a builder update leaves every page on the site scored
 * against last month's renderer, which is the failure that is invisible
 * because nothing changed on the screen.
 */
$solseo_readers_src = (string) file_get_contents( SOLSEO_PATH . 'includes/content/class-readers.php' );
$solseo_readers_src = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $solseo_readers_src );

preg_match( '#function key\(.*?\n\t\}#s', (string) $solseo_readers_src, $solseo_key_body );

$solseo_key_src = isset( $solseo_key_body[0] ) ? $solseo_key_body[0] : '';

solseo_assert( false !== strpos( $solseo_key_src, 'post_modified' ), 'the cache key carries when the post last changed' );
solseo_assert( false !== strpos( $solseo_key_src, '::version()' ), 'and the version of the builder that drew it' );
solseo_assert( false !== strpos( $solseo_key_src, 'SOLSEO_VERSION' ), 'and our own, so a change to the reduction invalidates what it made' );

/* AND IT DOES IT IN FACT, NOT ONLY IN THE SOURCE. */
$GLOBALS['solseo_test_builder'] = array(
	'active'  => true,
	'owns'    => true,
	'version' => '3.24.0',
	'html'    => '<h2>Drawn by a builder</h2><p>Every one of these words lives in the builder rather than in the post content, which is exactly the page that scores as empty today.</p>',
);

$solseo_built = solseo_test_post(
	array(
		'ID'            => 502,
		'post_content'  => '',
		'post_modified' => '2026-09-19 10:00:00',
	)
);

$solseo_first = Readers::html( $solseo_built );

solseo_assert( false !== strpos( $solseo_first, 'Drawn by a builder' ), 'a builder page is read through its builder' );
solseo_assert_same( 'test-builder', Readers::report( $solseo_built )['slug'], 'and the score records which reader produced the text' );

$GLOBALS['solseo_test_builder']['html'] = '<h2>Changed underneath</h2><p>Every one of these words lives in the builder rather than in the post content, which is exactly the page that scores as empty today.</p>';

solseo_assert(
	false !== strpos( Readers::html( $solseo_built ), 'Drawn by a builder' ),
	'a second read comes from the cache rather than the renderer'
);

$GLOBALS['solseo_test_builder']['version'] = '3.25.0';

solseo_assert(
	false !== strpos( Readers::html( $solseo_built ), 'Changed underneath' ),
	'and a builder update throws that cache away'
);

/*
 * THE EXTRACTION ONLY REPLACES THE STORED CONTENT WHEN IT HAS MORE TO SAY.
 *
 * Every renderer here is somebody else's code called by name. If a name is
 * wrong, or a version drops a method, or a render throws, the answer is short
 * or empty. Refusing a shorter answer turns every one of those into today's
 * behaviour rather than a page that scores zero.
 */
$GLOBALS['solseo_test_builder']['html'] = '<p>The builder gives back a short line of chrome and nothing else at all, which adds up to rather fewer words than the page already holds in full.</p>';

$solseo_wordy = solseo_test_post(
	array(
		'ID'            => 503,
		'post_content'  => '<p>The stored content on this post runs to rather more than the builder gives back, because somebody wrote the whole thing out in full in the editor before the builder was ever turned on, and every word of it is still here.</p>',
		'post_modified' => '2026-09-19 11:00:00',
	)
);

solseo_assert_same( '', Readers::html( $solseo_wordy ), 'a renderer that returns less than the stored content is not used' );

/* A RENDERER THAT THROWS LEAVES THE SCORE WORKING AND SAYS SO. */
$GLOBALS['solseo_test_builder']['html'] = new RuntimeException( 'the renderer fell over' );

$solseo_broken = solseo_test_post(
	array(
		'ID'            => 504,
		'post_content'  => '<p>Stored content, still here.</p>',
		'post_modified' => '2026-09-19 12:00:00',
	)
);

solseo_assert_same( '', Readers::html( $solseo_broken ), 'a renderer that throws does not take the score down with it' );

$solseo_report = Readers::report( $solseo_broken );

solseo_assert_same( 'test-builder', $solseo_report['slug'], 'the report still names the builder that owns the page' );
solseo_assert( '' !== $solseo_report['note'], 'and says the text was read from the stored content instead' );

unset( $GLOBALS['solseo_test_builder'] );

/*
 * NOTHING RENDERS DURING A FRONT END PAGE VIEW.
 *
 * The meta description falls back to a summary of the content, and that runs
 * inside wp_head while the builder is about to render the same page. Calling
 * the renderer there is a page render inside a page render, which is slow at
 * best and re-entrant at worst. On the front end the cached text is used, or
 * the stored content, and nothing else.
 */
$GLOBALS['solseo_test_is_admin']   = false;
$GLOBALS['solseo_test_is_rest']    = false;
$GLOBALS['solseo_test_doing_cron'] = false;

solseo_assert( ! Readers::may_render(), 'a front end page view never asks a builder to render' );

$GLOBALS['solseo_test_is_admin'] = true;

solseo_assert( Readers::may_render(), 'the admin does' );

$GLOBALS['solseo_test_is_admin'] = false;
$GLOBALS['solseo_test_is_rest']  = true;

solseo_assert( Readers::may_render(), 'and so does the editor, which asks over REST' );

$GLOBALS['solseo_test_is_rest'] = false;

/* THE LIST IS AN EXTENSION POINT, SO A SEVENTH BUILDER IS ONE FILE. */
solseo_assert_same(
	array( 'elementor', 'bricks', 'beaver', 'divi', 'wpbakery', 'builder' ),
	array_map(
		static function ( $reader ) {
			return $reader::slug();
		},
		array_values(
			array_filter(
				Readers::all(),
				static function ( $reader ) {
					return 0 === strpos( $reader, 'SolSEO\\' );
				}
			)
		)
	),
	'the six builders ship, in the order they are asked'
);

foreach ( Readers::all() as $solseo_reader ) {
	solseo_assert(
		in_array( 'SolSEO\Content\Reader', class_implements( $solseo_reader ), true ),
		$solseo_reader . ' answers the reader interface'
	);
}

/*
 * EVERY READER CITES WHERE ITS CALLS COME FROM.
 *
 * Three of these builders publish no developer documentation for the method
 * being called. Writing down which page the call came off, or that there was
 * no such page, is the difference between a reader somebody can check and a
 * method name half remembered from a forum.
 */
foreach ( Readers::all() as $solseo_reader ) {
	if ( 0 !== strpos( $solseo_reader, 'SolSEO\\' ) ) {
		continue;
	}

	solseo_assert(
		defined( $solseo_reader . '::SOURCE' ) && 0 === strpos( (string) constant( $solseo_reader . '::SOURCE' ), 'https://' ),
		$solseo_reader::slug() . ' says where the calls it makes are written down'
	);
}

/*
 * THE SCORE GOES THROUGH THE READERS.
 *
 * Every content check in every plugin reads Content::rendered(). If that line
 * ever goes back to reading post_content first, the whole batch quietly stops
 * working and nothing on any screen changes to say so.
 */
$solseo_content_src = (string) file_get_contents( SOLSEO_PATH . 'includes/class-content.php' );
$solseo_content_src = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $solseo_content_src );

solseo_assert(
	false !== strpos( $solseo_content_src, 'Readers::html( $post )' ),
	'Content::rendered() asks the readers before it falls back to what is stored'
);

solseo_assert(
	strpos( $solseo_content_src, 'Readers::html( $post )' ) < strpos( $solseo_content_src, '$html = $post->post_content;' ),
	'and it asks them first, not after'
);

/*
 * AND A BUILDER IS ONLY EVER ASKED FROM ONE PLACE.
 *
 * The gate that keeps a render out of wp_head lives in Readers::html(). A
 * second caller reaching a reader directly would go around it.
 */
foreach ( glob( SOLSEO_PATH . 'includes/**/*.php' ) as $solseo_file ) {
	if ( false !== strpos( $solseo_file, 'includes/content' ) ) {
		continue;
	}

	$solseo_src = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $solseo_file ) );

	solseo_assert(
		false === strpos( $solseo_src, '::html( $post )' ) || false !== strpos( $solseo_src, 'Readers::html( $post )' ),
		basename( $solseo_file ) . ' does not reach past Readers to a builder'
	);
}
