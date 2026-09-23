<?php
/**
 * Additions to the virtual robots.txt, and the composing behind the preview.
 *
 * The composing is a pure string to string function on purpose. It is the
 * thing the settings screen shows and the thing a crawler gets, and if those
 * two were produced by different code they would part company, which is
 * exactly the sort of difference nobody finds until traffic drops.
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

	/** Longest set of rules worth storing, in bytes. */
	const MAX_BYTES = 32768;

	/** Most lines worth storing. */
	const MAX_LINES = 500;

	/**
	 * Rules to compose with instead of the stored ones, while previewing.
	 *
	 * @var string|null
	 */
	protected static $override = null;

	/**
	 * Hook in.
	 */
	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'filter' ), 20, 2 );
	}

	/**
	 * Add to the robots.txt WordPress generates.
	 *
	 * A robots.txt file on disk wins, and nothing here can change that. The
	 * Tools screen offers to move such a file out of the way.
	 *
	 * @param string $output    Robots.txt so far.
	 * @param bool   $indexable Whether the site is set to be indexed.
	 * @return string
	 */
	public static function filter( $output, $indexable ) {
		if ( ! $indexable ) {
			return $output;
		}

		$rules = null === self::$override
			? (string) get_option( 'solseo_robots_rules', '' )
			: self::$override;

		$output = self::compose( $output, $rules );

		if ( Options::get( 'sitemap_enabled' ) ) {
			$output .= "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";
		}

		return $output;
	}

	/**
	 * What a crawler would get, without asking the network for it.
	 *
	 * Runs the real filter chain, so anything else on the site that adds to
	 * robots.txt shows up here too. A plugin that only registers its filter on
	 * the front of the site will not, which the screen says.
	 *
	 * @param string $rules Rules to compose with.
	 * @return string
	 */
	public static function preview( $rules ) {
		self::$override = (string) $rules;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- this is core's own filter, not ours.
		$composed = apply_filters( 'robots_txt', self::wordpress_default(), get_option( 'blog_public' ) );

		self::$override = null;

		return self::tidy( (string) $composed );
	}

	/**
	 * Where the admin lives, as a path a robots.txt line can carry.
	 *
	 * Asked of admin_url() rather than spelled out. /wp-admin/ is only the
	 * usual answer, not the only one: a site can move the admin, and a site
	 * in a subdirectory has the subdirectory in front of it. A rule naming a
	 * folder that is not there tells a crawler nothing, and worse, the Allow
	 * line that exists to keep admin-ajax.php reachable stops covering the
	 * address it is about. Both lines below come from the same two calls, so
	 * they cannot disagree with each other or with the site.
	 *
	 * Both come out of the one call, so the Allow line is always the file inside
	 * the folder the Disallow line names. Neither is spelled out here: a
	 * literal path is the bug this exists to remove, and a literal used as a
	 * fallback is the same bug waiting for the day the fallback runs.
	 *
	 * An empty pair means the site answered with something no rule can be
	 * written from, and the caller writes no rule rather than a wrong one. The
	 * shape that matters is Disallow: /, which would take the whole site out of
	 * every index, so nothing here may produce a bare slash.
	 *
	 * @return array Two paths: the admin folder, with a trailing slash, and
	 *               admin-ajax.php. Both empty when neither can be worked out.
	 */
	public static function admin_paths() {
		$nothing = array( '', '' );
		$ajax    = (string) wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH );

		if ( '' === $ajax || '/' === $ajax ) {
			return $nothing;
		}

		$folder = trailingslashit( str_replace( '\\', '/', dirname( $ajax ) ) );

		if ( '/' === $folder ) {
			return $nothing;
		}

		return array( $folder, $ajax );
	}

	/**
	 * The lines WordPress writes before anybody adds to them.
	 *
	 * This tracks do_robots() in wp-includes/functions.php. We do not call it:
	 * it sets a header, echoes rather than returns, and fires an action where
	 * somebody else's exit would take the settings screen with it. A test pins
	 * this string, so if core changes what it writes, a build fails rather
	 * than a preview quietly telling somebody the wrong thing.
	 *
	 * @return string
	 */
	public static function wordpress_default() {
		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			return "User-agent: *\nDisallow: /\n";
		}

		list( $folder, $ajax ) = self::admin_paths();

		if ( '' === $ajax ) {
			return "User-agent: *\n";
		}

		return "User-agent: *\n"
			. 'Disallow: ' . $folder . "\n"
			. 'Allow: ' . $ajax . "\n";
	}

	/**
	 * Fold a site's own rules into what WordPress already wrote.
	 *
	 * The merging is the point. A crawler reads the group that names it, or
	 * the star group, and only one of them. Two star groups is undefined:
	 * some crawlers join them, some take the first and drop the rest, and
	 * which one you get is not something you can choose. So lines meant for
	 * everybody are added to the star group that is already there, and a group
	 * naming particular crawlers is added after it.
	 *
	 * Pure.
	 *
	 * @param string $base  What WordPress wrote.
	 * @param string $rules What the site typed.
	 * @return string
	 */
	public static function compose( $base, $rules ) {
		$rules = trim( self::newlines( (string) $rules ) );

		if ( '' === $rules ) {
			return self::tidy( $base );
		}

		$star     = array();
		$named    = array();
		$sitemaps = array();
		$current  = -1;
		$open     = false;

		foreach ( explode( "\n", $rules ) as $line ) {
			$line = rtrim( $line );

			if ( preg_match( '/^\s*sitemap\s*:/i', $line ) ) {
				$sitemaps[] = trim( $line );

				continue;
			}

			if ( preg_match( '/^\s*user-agent\s*:\s*(.*)$/i', $line, $found ) ) {
				// Another agent line straight after one belongs to the same group.
				if ( ! $open ) {
					$named[] = array(
						'agents' => array(),
						'lines'  => array(),
					);

					$current = count( $named ) - 1;
					$open    = true;
				}

				$named[ $current ]['agents'][] = trim( $found[1] );

				continue;
			}

			if ( -1 === $current ) {
				$star[] = $line;

				continue;
			}

			$open                         = false;
			$named[ $current ]['lines'][] = $line;
		}

		$out = rtrim( self::newlines( (string) $base ) );

		$loose = trim( implode( "\n", $star ) );

		foreach ( $named as $one ) {
			if ( array( '*' ) === $one['agents'] ) {
				$loose .= "\n" . implode( "\n", $one['lines'] );
			}
		}

		$loose = trim( $loose );

		if ( '' !== $loose ) {
			$out .= "\n" . $loose;
		}

		foreach ( $named as $one ) {
			if ( array( '*' ) === $one['agents'] ) {
				continue;
			}

			$out .= "\n\n";

			foreach ( $one['agents'] as $agent ) {
				$out .= 'User-agent: ' . $agent . "\n";
			}

			$out .= trim( implode( "\n", $one['lines'] ) );
		}

		foreach ( array_unique( $sitemaps ) as $sitemap ) {
			$out .= "\n" . $sitemap;
		}

		return self::tidy( $out . "\n" );
	}

	/**
	 * Clean up a set of rules on their way into the database.
	 *
	 * What this replaced was sanitize_textarea_field(), which deletes every
	 * per cent encoded character it finds. A line reading Disallow: /*?q=%20
	 * was saved as Disallow: /*?q=, with no message and no way to tell. Every
	 * character robots.txt actually uses is kept here.
	 *
	 * @param string $rules What was typed.
	 * @return string
	 */
	public static function sanitise_rules( $rules ) {
		$rules = self::newlines( (string) $rules );

		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$rules = wp_check_invalid_utf8( $rules );
		}

		// Angle brackets mean nothing in robots.txt and everything in HTML.
		$rules = preg_replace( '/<[^>]*>/', '', $rules );
		$rules = str_replace( array( '<', '>' ), '', $rules );
		$rules = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $rules );

		$lines = array_map( 'rtrim', explode( "\n", $rules ) );
		$lines = array_slice( $lines, 0, self::MAX_LINES );
		$rules = trim( implode( "\n", $lines ) );

		if ( strlen( $rules ) > self::MAX_BYTES ) {
			$rules = substr( $rules, 0, self::MAX_BYTES );
		}

		return $rules;
	}

	/**
	 * Collapse the blank lines a merge leaves behind.
	 *
	 * Pure.
	 *
	 * @param string $text Composed robots.txt.
	 * @return string
	 */
	public static function tidy( $text ) {
		$text = self::newlines( (string) $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		return rtrim( $text ) . "\n";
	}

	/**
	 * One kind of line ending, whatever was pasted in.
	 *
	 * Pure.
	 *
	 * @param string $text Any text.
	 * @return string
	 */
	protected static function newlines( $text ) {
		return str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	}
}
