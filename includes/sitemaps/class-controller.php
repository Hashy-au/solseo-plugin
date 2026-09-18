<?php
/**
 * XML sitemaps.
 *
 * @package SolSEO
 */

namespace SolSEO\Sitemaps;

use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrite rules, routing and the sitemap index.
 */
class Controller {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'claim' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );
		add_action( 'transition_post_status', array( __CLASS__, 'clear_cache' ) );
	}

	/**
	 * Register the sitemap addresses.
	 */
	public static function rewrites() {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?solseo_sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap\.xsl$', 'index.php?solseo_sitemap=stylesheet', 'top' );
		add_rewrite_rule( '^sitemap-([a-z0-9_-]+?)-(\d+)\.xml$', 'index.php?solseo_sitemap=$matches[1]&solseo_page=$matches[2]', 'top' );
	}

	/**
	 * Recognise a sitemap address from the request itself.
	 *
	 * Rewrite rules alone are not enough. They go stale whenever a site is
	 * copied or another plugin saves the permalink settings, and WordPress
	 * carries its own rule for sitemap.xml that wants the same address.
	 * Reading the path settles both, before either can take the address away.
	 *
	 * @param \WP $wp The request.
	 */
	public static function claim( $wp ) {
		if ( ! empty( $wp->query_vars['solseo_sitemap'] ) ) {
			return;
		}

		$vars = self::read_path( trim( (string) $wp->request, '/' ) );

		if ( ! $vars ) {
			return;
		}

		// Everything else is dropped. Left in place, a leftover pagename sends
		// WordPress looking for a page that does not exist, and the 404
		// handling hands the address to the sitemap WordPress ships.
		$wp->query_vars = $vars;
	}

	/**
	 * Let WordPress pass our two query variables through.
	 *
	 * @param array $vars Registered query variables.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'solseo_sitemap';
		$vars[] = 'solseo_page';

		return $vars;
	}

	/**
	 * Answer a sitemap request.
	 */
	public static function serve() {
		$which = get_query_var( 'solseo_sitemap' );

		if ( $which ) {
			self::respond( $which, max( 1, (int) get_query_var( 'solseo_page' ) ) );

			return;
		}

		$asked = self::read_path( self::request_path() );

		if ( $asked ) {
			self::respond( $asked['solseo_sitemap'], isset( $asked['solseo_page'] ) ? (int) $asked['solseo_page'] : 1 );
		}
	}

	/**
	 * The path being asked for, relative to the site root.
	 *
	 * @return string
	 */
	protected static function request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read as a path and matched against a pattern.
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( '' !== $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		return trim( $path, '/' );
	}

	/**
	 * Turn a path into the query variables a sitemap request would carry.
	 *
	 * @param string $path Path relative to the site root.
	 * @return array Empty when the path is not one of ours.
	 */
	protected static function read_path( $path ) {
		if ( 'sitemap.xml' === $path ) {
			return array( 'solseo_sitemap' => 'index' );
		}

		if ( 'sitemap.xsl' === $path ) {
			return array( 'solseo_sitemap' => 'stylesheet' );
		}

		if ( preg_match( '#^sitemap-([a-z0-9_-]+?)-(\d+)\.xml$#', $path, $matches ) ) {
			return array(
				'solseo_sitemap' => $matches[1],
				'solseo_page'    => $matches[2],
			);
		}

		return array();
	}

	/**
	 * Build one sitemap and send it.
	 *
	 * @param string $which Section name, index or stylesheet.
	 * @param int    $page  Page number, from one.
	 */
	protected static function respond( $which, $page ) {
		if ( 'stylesheet' === $which ) {
			self::send( Writer::stylesheet(), 'text/xsl' );
		}

		$body = 'index' === $which ? self::index() : Writer::page( $which, max( 1, $page ) );

		if ( '' === $body ) {
			status_header( 404 );
			nocache_headers();

			if ( isset( $GLOBALS['wp_query'] ) ) {
				$GLOBALS['wp_query']->set_404();
			}

			return;
		}

		self::send( $body, 'application/xml' );
	}

	/**
	 * The list of sitemaps.
	 *
	 * @return string
	 */
	public static function index() {
		$cached = get_transient( 'solseo_sitemap_index' );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$entries = array();

		foreach ( self::sections() as $section => $count ) {
			$pages = max( 1, (int) ceil( $count / self::per_page() ) );

			for ( $page = 1; $page <= $pages; $page++ ) {
				$entries[] = array(
					'loc'     => home_url( sprintf( '/sitemap-%s-%d.xml', $section, $page ) ),
					'lastmod' => Writer::last_modified( $section ),
				);
			}
		}

		$xml = Writer::index( $entries );

		set_transient( 'solseo_sitemap_index', $xml, DAY_IN_SECONDS );

		return $xml;
	}

	/**
	 * Every section that has something in it, and how many entries it holds.
	 *
	 * @return array Section name to entry count.
	 */
	public static function sections() {
		$sections = array();

		foreach ( solseo_post_types() as $post_type ) {
			$settings = Options::post_type( $post_type );

			if ( ! empty( $settings['noindex'] ) ) {
				continue;
			}

			$count = Writer::count_posts( $post_type );

			if ( $count ) {
				$sections[ $post_type ] = $count;
			}
		}

		foreach ( solseo_taxonomies() as $taxonomy ) {
			$settings = Options::taxonomy( $taxonomy );

			if ( ! empty( $settings['noindex'] ) ) {
				continue;
			}

			$count = Writer::count_terms( $taxonomy );

			if ( $count ) {
				$sections[ 'tax-' . $taxonomy ] = $count;
			}
		}

		if ( Options::get( 'sitemap_authors' ) ) {
			$count = Writer::count_authors();

			if ( $count ) {
				$sections['author'] = $count;
			}
		}

		/**
		 * Filter the sitemap sections.
		 *
		 * @param array $sections Section name to entry count.
		 */
		return apply_filters( 'solseo_sitemap_sections', $sections );
	}

	/**
	 * How many entries fit on one sitemap.
	 *
	 * @return int
	 */
	public static function per_page() {
		$per_page = (int) Options::get( 'sitemap_per_page' );

		return max( 50, min( 2000, $per_page ) );
	}

	/**
	 * Drop the cached index when content changes.
	 */
	public static function clear_cache() {
		delete_transient( 'solseo_sitemap_index' );
	}

	/**
	 * Send a response and stop.
	 *
	 * @param string $body Response body.
	 * @param string $type Content type.
	 */
	protected static function send( $body, $type ) {
		header( 'Content-Type: ' . $type . '; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by Writer.
		exit;
	}
}
