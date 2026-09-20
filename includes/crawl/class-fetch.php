<?php
/**
 * The one place this plugin fetches a page of the site it is installed on.
 *
 * Every other outbound call in this plugin goes to somebody else and is named
 * in readme.txt under External services, because a site owner and a reviewer
 * both read that section as the whole list of third parties. A site reading its
 * own pages is not one of those, and putting the site's own address in a
 * declaration about third parties tells a reader their content is leaving,
 * which is the opposite of what happens.
 *
 * That exemption is worth exactly one file, and it is this one. Everything that
 * crawls, checks a link or follows a redirect comes through here, so the claim
 * "this plugin only ever fetches your own pages" is one function to read rather
 * than a habit to maintain, and the test in tests/test-directory-rules.php
 * proves it by handing this an address somewhere else and asserting that
 * nothing left the building. See D-84.1 and D-84.2.
 *
 * It also owns the courtesy, for the same reason: a pace worked out somewhere
 * else and passed in is a pace the next caller forgets to pass.
 *
 * @package SolSEO
 */

namespace SolSEO\Crawl;

use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * A polite GET, to this site and nowhere else.
 */
class Fetch {

	/** The slowest this can be asked to go. */
	const MIN_PER_MINUTE = 6;

	/** The fastest, whatever the setting says. */
	const MAX_PER_MINUTE = 120;

	/** What a site that has never chosen gets. */
	const DEFAULT_PER_MINUTE = 30;

	/** How long one page may take before we give up on it. */
	const TIMEOUT = 15;

	/** How much of a page is worth reading. A megabyte is a very long page. */
	const MAX_BYTES = 1048576;

	/**
	 * When the last request went out, from microtime( true ).
	 *
	 * @var float
	 */
	protected static $last_at = 0.0;

	/**
	 * Fetch one page of this site.
	 *
	 * Redirects are deliberately not followed. A crawler that follows them
	 * reports the page it ended up on and loses the hop, and the hops are the
	 * whole of the redirect chain report.
	 *
	 * @param string $url    The address, absolute, on this site.
	 * @param string $method GET or HEAD.
	 * @return array|\WP_Error Keys: code, location, body, ours.
	 */
	public static function get( $url, $method = 'GET' ) {
		$url = trim( (string) $url );

		if ( ! self::ours( $url ) ) {
			return new \WP_Error(
				'solseo_not_ours',
				__( 'This plugin only fetches pages of your own site, and that address is not one.', 'solseo' )
			);
		}

		self::hold();

		$response = wp_remote_request(
			$url,
			array(
				'method'              => 'HEAD' === $method ? 'HEAD' : 'GET',
				'timeout'             => self::TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BYTES,
				'user-agent'          => self::agent(),
				'headers'             => array( 'Accept' => 'text/html,*/*;q=0.5' ),

				/*
				 * A site with a self signed certificate, or one whose
				 * certificate names the public host while the loopback goes to
				 * localhost, is extremely common on staging. Refusing to read
				 * our own pages over it would turn "your crawl found nothing"
				 * into a support ticket, and there is no secret in a request
				 * for a page anybody can already read.
				 */
				'sslverify'           => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'url'      => $url,
			'code'     => (int) wp_remote_retrieve_response_code( $response ),
			'location' => (string) wp_remote_retrieve_header( $response, 'location' ),
			'body'     => 'HEAD' === $method ? '' : (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Whether an address is one this plugin is allowed to fetch.
	 *
	 * Pure, apart from asking WordPress where the site lives. Every refusal
	 * here is a request that never happens, so this is the whole of the
	 * control and the rest of the file is the courtesy.
	 *
	 * @param string $url The address.
	 * @return bool
	 */
	public static function ours( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return false;
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		/*
		 * https://user:pass@example.test/ has our host in it and is a request
		 * carrying a credential, which is not a thing a crawl of published
		 * pages ever needs to do.
		 */
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}

		$home = wp_parse_url( home_url( '/' ) );

		if ( ! is_array( $home ) || empty( $home['host'] ) ) {
			return false;
		}

		if ( self::host( $parts['host'] ) !== self::host( $home['host'] ) ) {
			return false;
		}

		$asked = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		$mine  = isset( $home['port'] ) ? (int) $home['port'] : 0;

		return $asked === $mine;
	}

	/**
	 * How many requests a minute this site allows.
	 *
	 * This runs against somebody's shared hosting, and the pages it asks for
	 * are pages that host has to render. The setting decides how slow, never
	 * whether: a zero, a blank or a number somebody typed in hope all come back
	 * inside the range. A crawler that takes a site down is a support ticket
	 * and a one star review.
	 *
	 * @return int
	 */
	public static function per_minute() {
		$asked = Options::get( 'crawl_per_minute', self::DEFAULT_PER_MINUTE );

		if ( ! is_numeric( $asked ) ) {
			return self::DEFAULT_PER_MINUTE;
		}

		return (int) max( self::MIN_PER_MINUTE, min( self::MAX_PER_MINUTE, (int) round( (float) $asked ) ) );
	}

	/**
	 * How long to wait before the next request may go out.
	 *
	 * Pure, and separate from the sleeping, because a test that had to sleep
	 * for the slow end of the range would take ten seconds to prove one line.
	 *
	 * @param int   $per_minute Requests a minute.
	 * @param float $last_at    When the last request went out, or 0.
	 * @param float $now        The time now.
	 * @return float Seconds.
	 */
	public static function pace( $per_minute, $last_at, $now ) {
		if ( $last_at <= 0 ) {
			return 0.0;
		}

		$per_minute = (int) max( self::MIN_PER_MINUTE, min( self::MAX_PER_MINUTE, (int) $per_minute ) );
		$gap        = 60 / $per_minute;

		return (float) max( 0.0, round( $gap - ( (float) $now - (float) $last_at ), 4 ) );
	}

	/**
	 * When the last request went out.
	 *
	 * @return float
	 */
	public static function last_at() {
		return (float) self::$last_at;
	}

	/**
	 * Start a run with nothing behind it.
	 */
	public static function forget() {
		self::$last_at = 0.0;
	}

	/**
	 * What this plugin calls itself when it knocks on the door.
	 *
	 * The site's own address is in there so somebody reading their access log
	 * at two in the morning can see at a glance that the traffic is their own
	 * site checking itself, rather than a scraper they need to block.
	 *
	 * @return string
	 */
	public static function agent() {
		return 'SolSEO/' . SOLSEO_VERSION . ' (site self check; +' . home_url( '/' ) . ')';
	}

	/**
	 * Wait out the gap, and note when this request went.
	 */
	protected static function hold() {
		$wait = self::pace( self::per_minute(), self::$last_at, microtime( true ) );

		if ( $wait > 0 ) {
			usleep( (int) round( $wait * 1000000 ) );
		}

		self::$last_at = microtime( true );
	}

	/**
	 * A host without the part nobody means.
	 *
	 * @param string $host A host name.
	 * @return string
	 */
	protected static function host( $host ) {
		$host = strtolower( trim( (string) $host ) );

		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}
}
