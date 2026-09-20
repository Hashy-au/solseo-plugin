<?php
/**
 * What the images on this site cost, and nothing else.
 *
 * This reports. It does not resize, convert, strip or delete anything, and the
 * guard in tests/test-media-report.php fails the build on a call that could.
 * The first time a plugin rewrites somebody's photograph and gets it wrong
 * there is no copy left, so the fixing waits for the batch that can put a
 * preview and an undo in front of it.
 *
 * It rides the job runner, because reading the content of every published page
 * is the same walk the scorer does and takes the same care.
 *
 * @package SolSEO
 */

namespace SolSEO\Media;

use SolSEO\Content;
use SolSEO\Jobs\Job;
use SolSEO\Score_Report;
use SolSEO\Tools\Alt_Text;

defined( 'ABSPATH' ) || exit;

/**
 * The media report job, and the pure reading behind it.
 */
class Report extends Job {

	/** Where the last report is kept. Never autoloaded. */
	const OPTION = 'solseo_media_report';

	/** How many findings are listed. The totals are kept in full. */
	const ROWS_KEPT = 200;

	/** How many library images are followed through the content scan. */
	const CANDIDATES = 1000;

	/** How much smaller than its file an image has to be drawn to count. */
	const MARGIN = 0.75;

	/** And by how many pixels, so a 210px image drawn at 200 is left alone. */
	const SLACK = 100;

	/**
	 * The key this job is known by.
	 *
	 * @return string
	 */
	public static function id() {
		return 'media';
	}

	/**
	 * How many pages one chunk reads.
	 *
	 * @return int
	 */
	public static function chunk() {
		return 20;
	}

	/**
	 * What the screen says while this is running.
	 *
	 * @param array $args The arguments the job was started with.
	 * @return string
	 */
	public static function label( array $args ) {
		unset( $args );

		return __( 'Looking at the images on your pages.', 'solseo' );
	}

	/**
	 * Start a fresh report.
	 *
	 * @param array $args Raw arguments from the request.
	 * @return array|\WP_Error
	 */
	public static function plan( array $args ) {
		unset( $args );

		$total = Score_Report::countable( 'all' );

		if ( ! $total ) {
			return new \WP_Error( 'solseo_media_nothing', __( 'There are no published pages to look at yet.', 'solseo' ) );
		}

		self::save(
			array(
				'started'    => time(),
				'finished'   => 0,
				'pages'      => 0,
				'rows'       => array(),
				'totals'     => array(
					'oversized' => 0,
					'no_size'   => 0,
					'no_alt'    => 0,
					'unused'    => 0,
				),
				'saving'     => 0,
				'candidates' => self::loose_images( self::CANDIDATES ),
				'checked'    => 0,
			)
		);

		return array(
			'total'  => $total,
			'cursor' => 0,
			'args'   => array(),
		);
	}

	/**
	 * Read one chunk of pages.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function work( array $state ) {
		$started = microtime( true );
		$ids     = Score_Report::ids_after( 'all', (int) $state['cursor'], (int) $state['chunk'] );

		if ( ! $ids ) {
			return self::result( (int) $state['cursor'], true );
		}

		$report = self::stored();
		$cursor = (int) $state['cursor'];
		$done   = 0;

		foreach ( $ids as $post_id ) {
			$html    = Content::rendered( $post_id );
			$library = self::library_for( $html );

			foreach ( self::inspect( $html, $library ) as $finding ) {
				$finding['post_id'] = (int) $post_id;

				$report = self::note( $report, $finding );
			}

			$report['candidates'] = array_values( array_diff( $report['candidates'], array_keys( $library ) ) );
			++$report['pages'];

			$cursor = (int) $post_id;
			++$done;

			if ( self::out_of_time( $started ) ) {
				break;
			}
		}

		self::save( $report );

		return array(
			'cursor'   => $cursor,
			'done'     => $done,
			'changed'  => 0,
			'failed'   => array(),
			'finished' => (int) $state['chunk'] > count( $ids ) && count( $ids ) === $done,
		);
	}

	/**
	 * Close the report off and say what it found.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function verify( array $state ) {
		unset( $state );

		$report = self::stored();

		foreach ( $report['candidates'] as $id ) {
			$report = self::note(
				$report,
				array(
					'kind'    => 'unused',
					'id'      => (int) $id,
					'src'     => (string) wp_get_attachment_url( (int) $id ),
					'saving'  => 0,
					'says'    => __( 'In the library, not on any page, and not attached to one.', 'solseo' ),
					'post_id' => 0,
				)
			);
		}

		$report['checked']    = count( $report['candidates'] );
		$report['candidates'] = array();
		$report['finished']   = time();

		self::save( $report );

		$found = array_sum( $report['totals'] );

		if ( ! $found ) {
			return array(
				'ok'      => true,
				'message' => __( 'Nothing worth changing. Your images are the size they are shown at and they all say what they are.', 'solseo' ),
				'failed'  => array(),
			);
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: 1: a number of findings. 2: a file size such as 4.2 MB. */
				__( '%1$s things to look at, worth about %2$s.', 'solseo' ),
				number_format_i18n( $found ),
				self::readable( (int) $report['saving'] )
			),
			'failed'  => array(),
		);
	}

	/**
	 * What one page's images say about themselves.
	 *
	 * Pure. An image whose file the library has never heard of gets only the
	 * findings that are visible in the markup, because guessing an intrinsic
	 * size would put a saving in kilobytes beside a number nobody measured.
	 *
	 * @param string $html    Rendered content.
	 * @param array  $library Attachment ID to width, height, bytes, alt and file.
	 * @return array
	 */
	public static function inspect( $html, array $library ) {
		if ( ! preg_match_all( '#<img\s[^>]*>#i', (string) $html, $tags ) ) {
			return array();
		}

		$found = array();

		foreach ( $tags[0] as $tag ) {
			$src   = self::attribute( $tag, 'src' );
			$id    = self::identify( $tag, $src, $library );
			$known = $id && isset( $library[ $id ] ) ? $library[ $id ] : null;

			$width  = self::number( self::attribute( $tag, 'width' ) );
			$height = self::number( self::attribute( $tag, 'height' ) );

			if ( ! $width || ! $height ) {
				$found[] = array(
					'kind'   => 'no_size',
					'id'     => $id,
					'src'    => $src,
					'saving' => 0,
					'says'   => __( 'No width and height on the tag, so the page jumps about while it loads.', 'solseo' ),
				);
			}

			if ( $known && self::oversized( $known, $width ) ) {
				$saving = self::oversize_saving( $known, $width, $height );

				$found[] = array(
					'kind'   => 'oversized',
					'id'     => $id,
					'src'    => $src,
					'saving' => $saving,
					'says'   => sprintf(
						/* translators: 1: the file's width in pixels. 2: the width it is drawn at. */
						__( 'The file is %1$dpx wide and the page draws it at %2$dpx.', 'solseo' ),
						(int) $known['width'],
						(int) $width
					),
				);
			}

			if ( $known && self::wordless( $tag, $known ) ) {
				$found[] = array(
					'kind'   => 'no_alt',
					'id'     => $id,
					'src'    => $src,
					'saving' => 0,
					'says'   => __( 'Nothing says what this image is, here or in the library.', 'solseo' ),
				);
			}
		}

		return $found;
	}

	/**
	 * What every finding together would save.
	 *
	 * Pure.
	 *
	 * @param array $rows Findings.
	 * @return int Bytes.
	 */
	public static function saving( array $rows ) {
		$total = 0;

		foreach ( $rows as $row ) {
			$total += isset( $row['saving'] ) ? (int) $row['saving'] : 0;
		}

		return $total;
	}

	/**
	 * A number of bytes the way somebody says it.
	 *
	 * Pure.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	public static function readable( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes >= 1048576 ) {
			/* translators: %s: a number of megabytes, such as 4.2. */
			return sprintf( __( '%s MB', 'solseo' ), number_format_i18n( $bytes / 1048576, 1 ) );
		}

		/* translators: %s: a number of kilobytes. */
		return sprintf( __( '%s KB', 'solseo' ), number_format_i18n( (int) round( $bytes / 1024 ) ) );
	}

	/**
	 * The last report.
	 *
	 * @return array
	 */
	public static function stored() {
		$stored = get_option( self::OPTION, array() );

		return array_merge(
			array(
				'started'    => 0,
				'finished'   => 0,
				'pages'      => 0,
				'rows'       => array(),
				'totals'     => array(
					'oversized' => 0,
					'no_size'   => 0,
					'no_alt'    => 0,
					'unused'    => 0,
				),
				'saving'     => 0,
				'candidates' => array(),
				'checked'    => 0,
			),
			is_array( $stored ) ? $stored : array()
		);
	}

	/**
	 * What one kind of finding is called on screen.
	 *
	 * @param string $kind Finding kind.
	 * @return string
	 */
	public static function kind_label( $kind ) {
		$labels = array(
			'oversized' => __( 'Bigger than it is shown at', 'solseo' ),
			'no_size'   => __( 'No width and height', 'solseo' ),
			'no_alt'    => __( 'Nothing says what it is', 'solseo' ),
			'unused'    => __( 'On no page at all', 'solseo' ),
		);

		return isset( $labels[ $kind ] ) ? $labels[ $kind ] : $kind;
	}

	/**
	 * Fold one finding into the report.
	 *
	 * Pure.
	 *
	 * @param array $report The report so far.
	 * @param array $finding One finding.
	 * @return array
	 */
	protected static function note( array $report, array $finding ) {
		$kind = (string) $finding['kind'];

		$report['totals'][ $kind ] = isset( $report['totals'][ $kind ] ) ? $report['totals'][ $kind ] + 1 : 1;
		$report['saving']         += (int) $finding['saving'];

		if ( count( $report['rows'] ) < self::ROWS_KEPT ) {
			$report['rows'][] = $finding;
		}

		return $report;
	}

	/**
	 * Write the report back.
	 *
	 * The one place this option is written, and never autoloaded, because a
	 * list of two hundred findings on every page load of the whole site is a
	 * cost nobody asked for. This is D-81.7 applied again.
	 *
	 * @param array $report The report.
	 */
	protected static function save( array $report ) {
		update_option( self::OPTION, $report, false );
	}

	/**
	 * Which attachment a tag is showing.
	 *
	 * Pure. The class WordPress writes is the reliable answer; where a theme or
	 * a builder has dropped it, the file name is matched instead, with the size
	 * suffix taken off, because b-300x200.jpg and b.jpg are one image.
	 *
	 * @param string $tag     The img tag.
	 * @param string $src     Its src.
	 * @param array  $library The library.
	 * @return int
	 */
	protected static function identify( $tag, $src, array $library ) {
		if ( preg_match( '#wp-image-(\d+)#', $tag, $found ) ) {
			return (int) $found[1];
		}

		$name = self::basename( $src );

		if ( '' === $name ) {
			return 0;
		}

		foreach ( $library as $id => $entry ) {
			if ( isset( $entry['file'] ) && self::basename( (string) $entry['file'] ) === $name ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * A file name without its path or the size WordPress appended.
	 *
	 * Pure.
	 *
	 * @param string $url An address.
	 * @return string
	 */
	protected static function basename( $url ) {
		$url  = (string) preg_replace( '#[?\#].*$#', '', (string) $url );
		$name = basename( $url );

		return strtolower( Alt_Text::strip_size_suffix( $name ) );
	}

	/**
	 * Whether the file is meaningfully bigger than the space it is drawn in.
	 *
	 * Pure.
	 *
	 * @param array $known The library entry.
	 * @param int   $width How wide the page draws it.
	 * @return bool
	 */
	protected static function oversized( array $known, $width ) {
		$intrinsic = isset( $known['width'] ) ? (int) $known['width'] : 0;
		$width     = (int) $width;

		if ( $intrinsic < 1 || $width < 1 ) {
			return false;
		}

		return $width < ( $intrinsic * self::MARGIN ) && ( $intrinsic - $width ) >= self::SLACK;
	}

	/**
	 * Roughly what shrinking one image to the size it is shown at would save.
	 *
	 * Pure, and deliberately short of the full area ratio: a smaller JPEG is
	 * not smaller in exact proportion to its pixel count, and a figure that
	 * overstates itself is the reason nobody believes the next one.
	 *
	 * @param array $known  The library entry.
	 * @param int   $width  Drawn width.
	 * @param int   $height Drawn height.
	 * @return int Bytes.
	 */
	protected static function oversize_saving( array $known, $width, $height ) {
		$bytes = isset( $known['bytes'] ) ? (int) $known['bytes'] : 0;
		$was   = ( isset( $known['width'] ) ? (int) $known['width'] : 0 ) * ( isset( $known['height'] ) ? (int) $known['height'] : 0 );
		$now   = (int) $width * (int) $height;

		if ( $bytes < 1 || $was < 1 || $now < 1 || $now >= $was ) {
			return 0;
		}

		$after = (int) round( $bytes * ( $now / $was ) );

		return (int) max( 0, min( $bytes - 1024, $bytes - $after ) );
	}

	/**
	 * Whether anything anywhere says what this image is.
	 *
	 * Pure. An empty alt on an image the library also has nothing for is a gap.
	 * An empty alt on an image this site does not own is left alone, because
	 * alt="" is the correct way to mark a decorative image and a checker that
	 * argues with correct markup is a checker somebody switches off.
	 *
	 * @param string $tag   The img tag.
	 * @param array  $known The library entry.
	 * @return bool
	 */
	protected static function wordless( $tag, array $known ) {
		$written = trim( self::attribute( $tag, 'alt' ) );
		$stored  = trim( isset( $known['alt'] ) ? (string) $known['alt'] : '' );

		return '' === $written && '' === $stored;
	}

	/**
	 * One attribute off a tag.
	 *
	 * Pure.
	 *
	 * @param string $tag  The tag.
	 * @param string $name Attribute name.
	 * @return string
	 */
	protected static function attribute( $tag, $name ) {
		return preg_match( '#\s' . preg_quote( $name, '#' ) . '=["\']([^"\']*)["\']#i', (string) $tag, $found )
			? (string) $found[1]
			: '';
	}

	/**
	 * A pixel count, or nothing.
	 *
	 * Pure.
	 *
	 * @param string $value An attribute value.
	 * @return int
	 */
	protected static function number( $value ) {
		return preg_match( '/^\d+$/', trim( (string) $value ) ) ? (int) $value : 0;
	}

	/**
	 * What the library knows about the images on one page.
	 *
	 * @param string $html Rendered content.
	 * @return array Attachment ID to width, height, bytes, alt and file.
	 */
	protected static function library_for( $html ) {
		$ids = array();

		if ( preg_match_all( '#wp-image-(\d+)#', (string) $html, $found ) ) {
			$ids = array_map( 'intval', $found[1] );
		}

		foreach ( self::sources_without_class( $html ) as $src ) {
			$id = (int) attachment_url_to_postid( Alt_Text::strip_size_suffix( $src ) );

			if ( $id ) {
				$ids[] = $id;
			}
		}

		$library = array();

		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$meta = wp_get_attachment_metadata( $id );

			if ( ! is_array( $meta ) ) {
				continue;
			}

			$library[ $id ] = array(
				'width'  => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
				'height' => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
				'bytes'  => isset( $meta['filesize'] ) ? (int) $meta['filesize'] : 0,
				'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'file'   => isset( $meta['file'] ) ? (string) $meta['file'] : '',
			);
		}

		return $library;
	}

	/**
	 * The src of every image on a page that carries no WordPress class.
	 *
	 * @param string $html Rendered content.
	 * @return array
	 */
	protected static function sources_without_class( $html ) {
		if ( ! preg_match_all( '#<img\s[^>]*>#i', (string) $html, $tags ) ) {
			return array();
		}

		$sources = array();

		foreach ( $tags[0] as $tag ) {
			if ( preg_match( '#wp-image-\d+#', $tag ) ) {
				continue;
			}

			$src = self::attribute( $tag, 'src' );

			if ( '' !== $src ) {
				$sources[] = $src;
			}
		}

		return array_slice( array_unique( $sources ), 0, 20 );
	}

	/**
	 * Images in the library that no page has claimed.
	 *
	 * The starting list. The content scan takes one off it every time it finds
	 * that image on a page, and what is left at the end is on no page at all.
	 * Capped, because this walks a media library and some of them are enormous.
	 *
	 * @param int $limit How many to consider.
	 * @return array Attachment IDs.
	 */
	protected static function loose_images( $limit ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery -- the wildcard is ours and matches a mime prefix, not something anybody typed.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				WHERE p.post_type = 'attachment'
				AND p.post_mime_type LIKE 'image/%'
				AND p.post_parent = 0
				AND p.ID NOT IN (
					SELECT CAST(meta_value AS UNSIGNED) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'
				)
				ORDER BY p.ID ASC LIMIT %d",
				(int) $limit
			)
		);
		// phpcs:enable

		return array_map( 'intval', (array) $ids );
	}
}
