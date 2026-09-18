<?php
/**
 * Turning an href into something two pages can be compared by.
 *
 * The normalising half takes strings and returns strings, so it is testable
 * without WordPress, which matters: every wrong answer here shows up as a
 * link count that is quietly one out.
 *
 * @package SolSEO
 */

namespace SolSEO\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Normalises addresses and finds the post behind one.
 */
class Resolver {

	/**
	 * Reduce an address to the part worth comparing.
	 *
	 * Pure.
	 *
	 * @param string $href What was in the href.
	 * @param string $home The site address.
	 * @return string A path, or an empty string when it is not ours to follow.
	 */
	public static function normalise( $href, $home ) {
		$href = trim( (string) $href );

		if ( '' === $href || 0 === strpos( $href, '#' ) ) {
			return '';
		}

		if ( preg_match( '#^(mailto|tel|javascript|data):#i', $href ) ) {
			return '';
		}

		$home_parts = wp_parse_url( (string) $home );
		$home_host  = isset( $home_parts['host'] ) ? self::host( $home_parts['host'] ) : '';
		$home_path  = isset( $home_parts['path'] ) ? rtrim( $home_parts['path'], '/' ) : '';

		// Protocol relative, so it borrows the scheme rather than the host.
		if ( 0 === strpos( $href, '//' ) ) {
			$href = 'https:' . $href;
		}

		$parts = wp_parse_url( $href );

		if ( false === $parts ) {
			return '';
		}

		if ( ! empty( $parts['host'] ) && self::host( $parts['host'] ) !== $home_host ) {
			return '';
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '';

		if ( '' === $path ) {
			return '/';
		}

		if ( 0 !== strpos( $path, '/' ) ) {
			$path = '/' . $path;
		}

		// A site living in a subdirectory carries that prefix on every link.
		if ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		$path = rtrim( $path, '/' );

		return '' === $path ? '/' : strtolower( $path );
	}

	/**
	 * Whether an address points somewhere on this site.
	 *
	 * Pure.
	 *
	 * @param string $href What was in the href.
	 * @param string $home The site address.
	 * @return bool
	 */
	public static function is_internal( $href, $home ) {
		return '' !== self::normalise( $href, $home );
	}

	/**
	 * A host without the part nobody means.
	 *
	 * Pure.
	 *
	 * @param string $host A host name.
	 * @return string
	 */
	protected static function host( $host ) {
		$host = strtolower( (string) $host );

		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * The post an address points at, where there is one.
	 *
	 * @param string $url The address as written.
	 * @return int Post ID, or 0.
	 */
	public static function to_post_id( $url ) {
		static $seen = array();

		$url = (string) $url;

		if ( isset( $seen[ $url ] ) ) {
			return $seen[ $url ];
		}

		$seen[ $url ] = (int) url_to_postid( $url );

		return $seen[ $url ];
	}
}
