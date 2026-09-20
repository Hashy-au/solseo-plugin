<?php
/**
 * A short description of the site, for anything reading it to answer a question.
 *
 * @package SolSEO
 */

namespace SolSEO\Frontend;

use SolSEO\Meta;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Serves /llms.txt, generated from the site's own content.
 *
 * It is a convention rather than a standard, and nothing is obliged to read it.
 * What it costs is one cached page; what it buys, when something does read it,
 * is that the description of the business is the one the business wrote rather
 * than one assembled from whatever page happened to be crawled first.
 *
 * Everything in it is already public. This publishes no address that is not in
 * the sitemap and nothing that is set to stay out of search results.
 */
class Llms_Txt {

	/** Where the composed file is kept between content changes. */
	const CACHE = 'solseo_llms_txt';

	/** How many pages and how many posts are listed. */
	const PAGES = 20;

	/** How many recent posts are listed. */
	const POSTS = 20;

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'claim' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );

		add_action( 'save_post', array( __CLASS__, 'forget' ) );
		add_action( 'deleted_post', array( __CLASS__, 'forget' ) );
		add_action( 'update_option_blogname', array( __CLASS__, 'forget' ) );
		add_action( 'update_option_blogdescription', array( __CLASS__, 'forget' ) );
	}

	/**
	 * Add the rewrite rule.
	 */
	public static function rules() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?solseo_llms=1', 'top' );
	}

	/**
	 * Let WordPress carry our query variable.
	 *
	 * @param array $vars Query variables.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'solseo_llms';

		return $vars;
	}

	/**
	 * Claim the request before WordPress decides it is a 404.
	 *
	 * @param \WP $wp The request.
	 */
	public static function claim( $wp ) {
		if ( ! empty( $wp->query_vars['solseo_llms'] ) ) {
			return;
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';

		if ( '/llms.txt' === $path ) {
			$wp->query_vars['solseo_llms'] = 1;
		}
	}

	/**
	 * Serve it.
	 */
	public static function serve() {
		if ( ! get_query_var( 'solseo_llms' ) ) {
			return;
		}

		if ( ! Options::get( 'llms_txt_enabled' ) ) {
			return;
		}

		/*
		 * WordPress decided this was a 404 before template_redirect ran, and
		 * already sent that status line. The bytes below are right and the
		 * status above them is not, which nothing notices until something
		 * machine readable checks it.
		 */
		status_header( 200 );

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		echo self::body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composed below, and a text file is not HTML.

		exit;
	}

	/**
	 * The file, from cache when nothing has changed.
	 *
	 * @return string
	 */
	public static function body() {
		$cached = get_transient( self::CACHE );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$body = self::compose();

		set_transient( self::CACHE, $body, DAY_IN_SECONDS );

		return $body;
	}

	/**
	 * Throw the cached copy away.
	 */
	public static function forget() {
		delete_transient( self::CACHE );
	}

	/**
	 * Build it.
	 *
	 * @return string
	 */
	public static function compose() {
		$lines = array( '# ' . self::plain( get_bloginfo( 'name' ) ) );

		$about = self::plain( get_bloginfo( 'description' ) );

		if ( '' !== $about ) {
			$lines[] = '';
			$lines[] = '> ' . $about;
		}

		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: the site address. */
			__( 'This file describes %s. Everything listed here is public and is in the sitemap.', 'solseo' ),
			home_url( '/' )
		);

		$pages = self::listed( 'page', self::PAGES );

		if ( $pages ) {
			$lines[] = '';
			$lines[] = '## ' . __( 'Pages', 'solseo' );
			$lines[] = '';
			$lines   = array_merge( $lines, $pages );
		}

		$posts = self::listed( 'post', self::POSTS );

		if ( $posts ) {
			$lines[] = '';
			$lines[] = '## ' . __( 'Recent writing', 'solseo' );
			$lines[] = '';
			$lines   = array_merge( $lines, $posts );
		}

		$lines[] = '';
		$lines[] = '## ' . __( 'Everything else', 'solseo' );
		$lines[] = '';
		$lines[] = '- [' . __( 'Sitemap', 'solseo' ) . '](' . home_url( '/sitemap.xml' ) . ')';
		$lines[] = '';

		/**
		 * Filter the composed llms.txt.
		 *
		 * @param string $body The whole file.
		 */
		return (string) apply_filters( 'solseo_llms_txt', implode( "\n", $lines ) );
	}

	/**
	 * One markdown line per published thing, with its own description.
	 *
	 * Anything set to stay out of search results is left out of this too. A
	 * page we are asking a search engine not to index is not a page to hand to
	 * something else to read out loud.
	 *
	 * @param string $post_type Post type.
	 * @param int    $limit     How many.
	 * @return array
	 */
	protected static function listed( $post_type, $limit ) {
		$found = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'orderby'          => 'post' === $post_type ? 'date' : 'menu_order title',
				'order'            => 'post' === $post_type ? 'DESC' : 'ASC',
				'suppress_filters' => false,
			)
		);

		$lines = array();

		$skip = self::utility_pages();

		foreach ( (array) $found as $post ) {
			if ( Meta::get( $post->ID, 'robots_noindex' ) ) {
				continue;
			}

			if ( in_array( (int) $post->ID, $skip, true ) ) {
				continue;
			}

			$about = self::plain( Meta::get( $post->ID, 'description' ) );

			if ( '' === $about ) {
				$about = self::plain( get_the_excerpt( $post ) );
			}

			$line = '- [' . self::plain( get_the_title( $post ) ) . '](' . get_permalink( $post ) . ')';

			if ( '' !== $about ) {
				$line .= ': ' . $about;
			}

			$lines[] = $line;
		}

		return $lines;
	}

	/**
	 * Pages that are machinery rather than reading.
	 *
	 * A basket, a checkout and an account screen tell a reader nothing about
	 * the business and are no use to anything answering a question about it.
	 * They are also the first three pages WordPress hands back on a shop, so a
	 * file that lists them opens with three dead ends.
	 *
	 * @return array Post IDs.
	 */
	protected static function utility_pages() {
		$skip = array( (int) get_option( 'page_for_posts' ) );

		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount', 'terms' ) as $page ) {
				$skip[] = (int) wc_get_page_id( $page );
			}
		}

		/**
		 * Filter the pages left out of llms.txt.
		 *
		 * @param array $skip Post IDs.
		 */
		$skip = (array) apply_filters( 'solseo_llms_txt_skip', $skip );

		return array_values(
			array_filter(
				array_map( 'intval', $skip ),
				static function ( $id ) {
					return $id > 0;
				}
			)
		);
	}

	/**
	 * One line of plain text, however it arrived.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected static function plain( $text ) {
		$text = wp_strip_all_tags( (string) $text );

		/*
		 * Entities are decoded, because this is a text file and nothing reading
		 * it is going to turn &#8217; back into an apostrophe. WordPress puts
		 * curly quotes through wptexturize on the way out, so an excerpt
		 * arrives full of them.
		 */
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( (string) $text );
	}
}
