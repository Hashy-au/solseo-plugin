<?php
/**
 * Which page builder drew this page, and what it says.
 *
 * @package SolSEO
 */

namespace SolSEO\Content;

use SolSEO\Content\Readers\Beaver;
use SolSEO\Content\Readers\Bricks;
use SolSEO\Content\Readers\Builder;
use SolSEO\Content\Readers\Divi;
use SolSEO\Content\Readers\Elementor;
use SolSEO\Content\Readers\Wpbakery;

defined( 'ABSPATH' ) || exit;

/**
 * Detection, caching and the fail safe.
 *
 * `post_content` on a page builder site is serialised JSON or an empty string,
 * so every word count, every heading check, every link this plugin indexes and
 * every score it prints is wrong on those sites, silently. This is where that
 * stops.
 *
 * Nothing here trusts a builder to be there, to publish the method we call, or
 * to survive being called. Every answer is checked against what WordPress
 * already stores, and the stored content wins unless the builder produces more
 * than it. A wrong method name, a renamed API or a renderer that throws all
 * come out the same way: today's behaviour, plus a line saying so.
 */
class Readers {

	/** Where the reduced text is kept. Post meta, so it dies with the post. */
	const META = '_solseo_content';

	/** How long a cached extraction is trusted while we could make a new one. */
	const STALE = 604800;

	/** Below this, what a builder produced is chrome rather than content. */
	const ENOUGH = 25;

	/**
	 * Which reader owns which post, for this request.
	 *
	 * @var array
	 */
	protected static $owner = array();

	/**
	 * What went wrong reading a post, for this request.
	 *
	 * @var array
	 */
	protected static $notes = array();

	/**
	 * Whether an extraction is already running, so a filter cannot loop back in.
	 *
	 * @var bool
	 */
	protected static $running = false;

	/**
	 * Every reader, in the order they are asked.
	 *
	 * The named builders come first and the unnamed one last, because it
	 * claims any page with nothing stored and would otherwise answer for a
	 * builder that has a reader of its own.
	 *
	 * @return array Class names.
	 */
	public static function all() {
		$readers = array(
			Elementor::class,
			Bricks::class,
			Beaver::class,
			Divi::class,
			Wpbakery::class,
			Builder::class,
		);

		/**
		 * Filter the page builder readers.
		 *
		 * A seventh builder is one class implementing SolSEO\Content\Reader
		 * added to this list. Put it before Builder::class, which claims
		 * anything left over.
		 *
		 * @param array $readers Class names.
		 */
		return (array) apply_filters( 'solseo_content_readers', $readers );
	}

	/**
	 * The reader that drew this post, if any.
	 *
	 * @param \WP_Post|int $post Post or post ID.
	 * @return string Class name, or an empty string.
	 */
	public static function for_post( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return '';
		}

		$id = (int) $post->ID;

		if ( isset( self::$owner[ $id ] ) ) {
			return self::$owner[ $id ];
		}

		self::$owner[ $id ] = '';

		foreach ( self::all() as $reader ) {
			if ( ! $reader::active() ) {
				continue;
			}

			if ( ! $reader::owns( $post ) ) {
				continue;
			}

			self::$owner[ $id ] = $reader;
			break;
		}

		return self::$owner[ $id ];
	}

	/**
	 * The visible text of a builder page, reduced, or nothing.
	 *
	 * An empty answer means "there is nothing here that WordPress does not
	 * already have", and the caller carries on with `post_content`.
	 *
	 * @param \WP_Post|int $post Post or post ID.
	 * @return string
	 */
	public static function html( $post ) {
		$post = get_post( $post );

		if ( ! $post || self::$running ) {
			return '';
		}

		$reader = self::for_post( $post );

		if ( ! $reader ) {
			return '';
		}

		$id     = (int) $post->ID;
		$key    = self::key( $post, $reader );
		$cached = get_post_meta( $id, self::META, true );
		$usable = is_array( $cached ) && isset( $cached['key'], $cached['html'] ) && $cached['key'] === $key;

		if ( $usable && ( ! self::may_render() || self::fresh( $cached ) ) ) {
			if ( '' === $cached['html'] ) {
				self::$notes[ $id ] = self::fell_back();
			}

			return (string) $cached['html'];
		}

		/*
		 * Not on a front end page view. The meta description falls back to a
		 * summary of the content, and that runs inside wp_head while the
		 * builder is about to draw the same page. Asking it to draw the page
		 * again, there, is a render inside a render.
		 */
		if ( ! self::may_render() ) {
			return '';
		}

		$html = self::extract( $post, $reader );

		update_post_meta(
			$id,
			self::META,
			array(
				'key'  => $key,
				'by'   => $reader::slug(),
				'at'   => time(),
				'html' => $html,
			)
		);

		return $html;
	}

	/**
	 * Which reader produced the text that was scored.
	 *
	 * @param \WP_Post|int $post Post or post ID.
	 * @return array Keys: slug, label, note.
	 */
	public static function report( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return self::stored();
		}

		$reader = self::for_post( $post );

		if ( ! $reader ) {
			return self::stored();
		}

		$id   = (int) $post->ID;
		$note = isset( self::$notes[ $id ] ) ? self::$notes[ $id ] : '';

		/*
		 * The unnamed reader claims any page with nothing stored, which
		 * includes a page that is simply empty. When it finds nothing there is
		 * nothing to report, because naming a builder on a page that has none
		 * would be an invention.
		 */
		if ( Builder::class === $reader && '' !== $note ) {
			return self::stored();
		}

		return array(
			'slug'  => $reader::slug(),
			'label' => $reader::label(),
			'note'  => $note,
		);
	}

	/**
	 * Whether a builder may be asked to render right now.
	 *
	 * @return bool
	 */
	public static function may_render() {
		$may = is_admin()
			|| self::serving_rest()
			|| wp_doing_cron()
			|| ( defined( 'WP_CLI' ) && WP_CLI );

		/**
		 * Filter whether a page builder may be asked to render a page.
		 *
		 * @param bool $may Whether it may.
		 */
		return (bool) apply_filters( 'solseo_may_render_builder', $may );
	}

	/**
	 * Throw away what was cached for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function forget( $post_id ) {
		$post_id = (int) $post_id;

		unset( self::$owner[ $post_id ], self::$notes[ $post_id ] );

		delete_post_meta( $post_id, self::META );
	}

	/**
	 * Ask the builder, and check the answer against what WordPress stores.
	 *
	 * @param \WP_Post $post   The post.
	 * @param string   $reader Reader class name.
	 * @return string
	 */
	protected static function extract( $post, $reader ) {
		$rendered = '';

		/*
		 * Everything inside here is somebody else's code, called by name. A
		 * renamed method throws an Error rather than an Exception, which is
		 * why this catches Throwable and not just the polite half of it.
		 */
		try {
			$rendered = (string) $reader::html( $post );
		} catch ( \Throwable $thrown ) {
			$rendered = '';
		}

		if ( '' === trim( $rendered ) ) {
			$rendered = self::through_the_content( $post );
		}

		$reduced = Reduce::document( $rendered );
		$words   = self::words( $reduced );

		if ( $words < self::ENOUGH || $words <= self::words( $post->post_content ) ) {
			self::$notes[ (int) $post->ID ] = self::fell_back();

			return '';
		}

		return $reduced;
	}

	/**
	 * The page the way a visitor gets it.
	 *
	 * Most builders draw their pages by filtering `the_content`, which makes
	 * this the one door that opens for a builder we have never heard of. It is
	 * only ever walked through on a page a builder owns, so the related posts
	 * and sharing buttons that also hook this filter land on pages that are
	 * currently scored as empty rather than on ordinary writing.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	protected static function through_the_content( $post ) {
		$previous = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$content  = '';

		self::$running = true;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- put back below. A builder asks which page it is drawing, and this is where it asks.
		$GLOBALS['post'] = $post;

		try {
			setup_postdata( $post );

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, which is the point: it is the door every builder already opens.
			$content = (string) apply_filters( 'the_content', $post->post_content );
		} catch ( \Throwable $thrown ) {
			$content = '';
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- see above.
		$GLOBALS['post'] = $previous;

		wp_reset_postdata();

		self::$running = false;

		return $content;
	}

	/**
	 * What a cached extraction was made from.
	 *
	 * The builder's version is in here because without it a builder update
	 * leaves every page on the site scored against last month's renderer, and
	 * nothing on any screen changes to say so.
	 *
	 * @param \WP_Post $post   The post.
	 * @param string   $reader Reader class name.
	 * @return string
	 */
	protected static function key( $post, $reader ) {
		return md5(
			implode(
				'|',
				array(
					(string) $post->post_modified,
					(string) $post->post_content,
					$reader::slug(),
					$reader::version(),
					SOLSEO_VERSION,
				)
			)
		);
	}

	/**
	 * Whether a cached extraction is recent enough to keep using.
	 *
	 * A page can change without its post changing: a builder's global block,
	 * a template, a header. Nothing tells us, so the cache expires instead.
	 *
	 * @param array $cached The cached entry.
	 * @return bool
	 */
	protected static function fresh( array $cached ) {
		return isset( $cached['at'] ) && ( time() - (int) $cached['at'] ) < self::STALE;
	}

	/**
	 * How many words are in some markup.
	 *
	 * @param string $html Markup.
	 * @return int
	 */
	protected static function words( $html ) {
		return (int) preg_match_all( '/[\p{L}\p{N}]+/u', wp_strip_all_tags( (string) $html ), $found );
	}

	/**
	 * Whether this is a REST request.
	 *
	 * @return bool
	 */
	protected static function serving_rest() {
		if ( function_exists( 'wp_is_serving_rest_request' ) ) {
			return (bool) wp_is_serving_rest_request();
		}

		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * What the report says when the builder could not be read.
	 *
	 * @return string
	 */
	protected static function fell_back() {
		return __( 'The builder could not be read on this page, so the stored content was used instead.', 'solseo' );
	}

	/**
	 * The report for a page that WordPress stores in full.
	 *
	 * @return array
	 */
	protected static function stored() {
		return array(
			'slug'  => 'stored',
			'label' => __( 'WordPress', 'solseo' ),
			'note'  => '',
		);
	}
}
