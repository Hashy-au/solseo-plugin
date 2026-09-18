<?php
/**
 * Additions to the virtual robots.txt.
 *
 * @package SolSEO
 */

namespace SolSEO\Frontend;

use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Appends the sitemap line and any rules the site has added.
 */
class Robots_Txt {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'filter' ), 20, 2 );
	}

	/**
	 * Add to the robots.txt WordPress generates.
	 *
	 * A robots.txt file on disk wins, and nothing here can change that.
	 *
	 * @param string $output    Robots.txt so far.
	 * @param bool   $indexable Whether the site is set to be indexed.
	 * @return string
	 */
	public static function filter( $output, $indexable ) {
		if ( ! $indexable ) {
			return $output;
		}

		$extra = trim( (string) get_option( 'solseo_robots_rules', '' ) );

		if ( $extra ) {
			$output .= "\n" . $extra . "\n";
		}

		if ( Options::get( 'sitemap_enabled' ) ) {
			$output .= "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";
		}

		return $output;
	}
}
