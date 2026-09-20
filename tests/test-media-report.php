<?php
/**
 * The media report: what it finds, and everything it refuses to touch.
 *
 * @package SolSEO
 */

use SolSEO\Media\Report;

/*
 * NOTHING IN THIS BATCH WRITES A MEDIA FILE.
 *
 * The report says an image is four times the size it is shown at. It does not
 * resize it, convert it, strip it or delete it, because the first time a plugin
 * rewrites somebody's photograph and gets it wrong there is no copy left. The
 * fixing is a separate batch with a preview and an undo in front of it, and
 * until then the only safe version of this screen is one that cannot.
 */
$solseo_reporting = array_merge(
	glob( SOLSEO_PATH . 'includes/media/*.php' ),
	glob( SOLSEO_PATH . 'includes/crawl/*.php' ),
	glob( SOLSEO_PATH . 'includes/links/*.php' ),
	array(
		SOLSEO_PATH . 'includes/admin/views/health-media.php',
		SOLSEO_PATH . 'includes/admin/views/technical-crawl.php',
		SOLSEO_PATH . 'includes/admin/views/technical-links.php',
		SOLSEO_PATH . 'includes/admin/class-media-tab.php',
		SOLSEO_PATH . 'includes/admin/class-crawl-tab.php',
		SOLSEO_PATH . 'includes/admin/class-links-tab.php',
	)
);

solseo_assert( count( $solseo_reporting ) >= 12, 'the media guard is reading the whole of this batch' );

$solseo_destructive = array(
	'wp_delete_attachment',
	'wp_delete_file',
	'wp_delete_post',
	'wp_get_image_editor',
	'wp_generate_attachment_metadata',
	'wp_create_image_subsizes',
	'wp_insert_attachment',
	'wp_update_attachment_metadata',
	'file_put_contents',
	'imagejpeg',
	'imagepng',
	'imagewebp',
	'imagecreatefromjpeg',
	'imagecreatefrompng',
	'fwrite',
	'fputs',
	'fopen',
	'unlink',
	'rename',
	'copy',
	'rmdir',
	'mkdir',
	'move_uploaded_file',
);

/*
 * solseo_code_only() takes a file's prose out before any of this reads it, and
 * it lives in tests/run.php because this is no longer the only guard that
 * wants it.
 */
$solseo_writes = array();

foreach ( $solseo_reporting as $solseo_file ) {
	if ( ! is_readable( $solseo_file ) ) {
		$solseo_writes[] = basename( $solseo_file ) . ' is missing';
		continue;
	}

	$solseo_code = solseo_code_only( (string) file_get_contents( $solseo_file ) );

	foreach ( $solseo_destructive as $solseo_call ) {
		if ( preg_match( '/(?<![\w$>:\\\\])' . preg_quote( $solseo_call, '/' ) . '\s*\(/', $solseo_code ) ) {
			$solseo_writes[] = basename( $solseo_file ) . ' calls ' . $solseo_call;
		}
	}
}

solseo_assert_same( array(), $solseo_writes, 'nothing in this batch writes, converts or deletes a file' );

/*
 * AND THE GUARD HAS BEEN WATCHED FIRING. A guard nobody has seen match
 * anything is a guard that passes because it never could.
 */
solseo_assert(
	1 === preg_match( '/(?<![\w$>:\\\\])unlink\s*\(/', solseo_code_only( '<?php unlink( $file );' ) ),
	'the guard catches a call to unlink'
);

solseo_assert(
	0 === preg_match( '/(?<![\w$>:\\\\])unlink\s*\(/', solseo_code_only( '<?php self::unlink( $x ); $o->unlink(); function unlink() {} // unlink( 1 )' ) ),
	'and leaves a method of ours, a declaration and a comment alone'
);

solseo_assert(
	0 === preg_match( '/(?<![\w$>:\\\\])rename\s*\(/', solseo_code_only( '<?php esc_html_e( "nothing is renamed or deleted" );' ) ),
	'and does not read the promise as the thing it promises not to do'
);

/*
 * AND THE REPORT IS KEPT IN ONE PLACE, WITH A CEILING, AND NEVER AUTOLOADED.
 *
 * D-81.7, applied again. A per-image list on a site with forty thousand images
 * is an option nobody meant to make, and an autoloaded one costs every page
 * load on the site for ever.
 */
$solseo_report_src = (string) file_get_contents( SOLSEO_PATH . 'includes/media/class-report.php' );

solseo_assert_same(
	1,
	preg_match_all( '/\bupdate_option\s*\(/', $solseo_report_src ),
	'the report is written in exactly one place'
);

solseo_assert(
	preg_match( '/update_option\([^)]*,\s*false\s*\)/s', $solseo_report_src )
		|| false !== strpos( $solseo_report_src, 'self::OPTION, $report, false' ),
	'and it is not autoloaded'
);

solseo_assert( Report::ROWS_KEPT > 0 && Report::ROWS_KEPT <= 500, 'and the list it keeps has a ceiling' );

/*
 * AND IT NEVER MENTIONS THE ADD-ON.
 *
 * The batch doc asked this screen to say that the fixing is part of Pro. The
 * sidebar contract does not allow it: the one place in this plugin that names
 * what somebody has not bought is the line at the foot of Settings, Features,
 * and the Upgrade screen they chose to open. A report that ends every finding
 * with a price is the nag that rule exists to stop. See D-86.4.
 */
$solseo_selling = array();

foreach ( $solseo_reporting as $solseo_file ) {
	if ( ! is_readable( $solseo_file ) ) {
		continue;
	}

	$solseo_body = (string) file_get_contents( $solseo_file );

	foreach ( array( 'solseo.com.au', 'Upgrade', 'upgrade', ' Pro ', 'licence', 'Licence' ) as $solseo_word ) {
		if ( false !== strpos( $solseo_body, $solseo_word ) ) {
			$solseo_selling[] = basename( $solseo_file ) . ' says ' . trim( $solseo_word );
		}
	}
}

solseo_assert_same( array(), $solseo_selling, 'nothing in this batch sells anything' );

/*
 * WHAT ONE PAGE'S IMAGES SAY ABOUT THEMSELVES.
 */
$solseo_library = array(
	11 => array(
		'width'  => 2400,
		'height' => 1600,
		'bytes'  => 800000,
		'alt'    => 'A boot',
		'file'   => 'boot.jpg',
	),
	12 => array(
		'width'  => 600,
		'height' => 400,
		'bytes'  => 40000,
		'alt'    => '',
		'file'   => 'sock.jpg',
	),
	13 => array(
		'width'  => 800,
		'height' => 600,
		'bytes'  => 60000,
		'alt'    => 'A hat',
		'file'   => 'hat.jpg',
	),
);

$solseo_html = '<p>'
	. '<img src="/wp-content/uploads/boot.jpg" class="wp-image-11" width="600" height="400" alt="A boot">'
	. '<img src="/wp-content/uploads/sock.jpg" class="wp-image-12" width="600" height="400" alt="">'
	. '<img src="/wp-content/uploads/hat.jpg" class="wp-image-13" alt="A hat">'
	. '</p>';

$solseo_found = Report::inspect( $solseo_html, $solseo_library );

$solseo_kinds = array();

foreach ( $solseo_found as $solseo_finding ) {
	$solseo_kinds[ $solseo_finding['kind'] ][] = $solseo_finding['id'];
}

solseo_assert_same( array( 11 ), isset( $solseo_kinds['oversized'] ) ? $solseo_kinds['oversized'] : array(), 'a 2400px image shown at 600px is too big' );
solseo_assert_same( array( 12 ), isset( $solseo_kinds['no_alt'] ) ? $solseo_kinds['no_alt'] : array(), 'an image with an empty alt is named' );
solseo_assert_same( array( 13 ), isset( $solseo_kinds['no_size'] ) ? $solseo_kinds['no_size'] : array(), 'and one with no width or height is named' );

$solseo_over = null;

foreach ( $solseo_found as $solseo_finding ) {
	if ( 'oversized' === $solseo_finding['kind'] ) {
		$solseo_over = $solseo_finding;
	}
}

solseo_assert( $solseo_over['saving'] > 0, 'and the oversized one says what shrinking it would save' );
solseo_assert( $solseo_over['saving'] < 800000, 'but never more than the file weighs' );

/*
 * AND AN IMAGE SHOWN AT THE SIZE IT IS, IS FINE.
 */
$solseo_fine = Report::inspect(
	'<img src="/wp-content/uploads/hat.jpg" class="wp-image-13" width="800" height="600" alt="A hat">',
	$solseo_library
);

solseo_assert_same( array(), $solseo_fine, 'an image at its own size with alt text and dimensions raises nothing' );

/*
 * AND AN IMAGE WE KNOW NOTHING ABOUT RAISES NOTHING BUT WHAT WE CAN SEE.
 *
 * An <img> pointing at somebody else's server, or at a file the media library
 * has never heard of, has no intrinsic size to compare against. Guessing one
 * would put a saving in kilobytes next to a number nobody measured.
 */
$solseo_foreign = Report::inspect(
	'<img src="https://elsewhere.test/a.jpg" alt="Something"><img src="https://elsewhere.test/b.jpg" width="10" height="10" alt="">',
	$solseo_library
);

$solseo_foreign_kinds = wp_list_pluck( $solseo_foreign, 'kind' );

solseo_assert( ! in_array( 'oversized', $solseo_foreign_kinds, true ), 'an image with no known size is never called too big' );
solseo_assert( in_array( 'no_size', $solseo_foreign_kinds, true ), 'but a missing width and height is visible in the markup and is still said' );

/*
 * THE SAVINGS ADD UP, AND THEY ADD UP TO SOMETHING A PERSON CAN READ.
 */
$solseo_total = Report::saving(
	array(
		array( 'saving' => 500000 ),
		array( 'saving' => 250000 ),
		array( 'saving' => 0 ),
	)
);

solseo_assert_same( 750000, $solseo_total, 'the savings are totalled in bytes' );
solseo_assert_same( 0, Report::saving( array() ), 'and nothing found saves nothing' );
