<?php
/**
 * The IndexNow key, the file that proves it, and the submission.
 *
 * @package SolSEO
 */

namespace SolSEO\Indexing;

defined( 'ABSPATH' ) || exit;

/**
 * IndexNow, which is one call to every engine that takes part.
 *
 * Bing, Yandex, Seznam and Naver all read the same endpoint, which is why this
 * is the whole of the submission story in the free plugin and why there is no
 * separate Bing call beside it doing the same work twice.
 *
 * The key is the part the design got wrong. IndexNow keys are not issued by
 * anybody: you invent a string and serve it as a text file at your own root,
 * and the engine reads it back to check the submission came from whoever owns
 * the site. Asking the owner to invent a hex string and create a file by hand
 * adds two steps that fail silently, and the failure arrives later as a 403
 * with nothing on screen to connect it to the missing file. So the key is made
 * here and served here, and the owner never has to know it exists.
 */
class Indexnow {

	/** Where the key is kept. */
	const OPTION = 'solseo_indexnow_key';

	/** The one endpoint, which passes submissions to every engine taking part. */
	const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/** The protocol takes up to ten thousand addresses in one request. */
	const BATCH = 10000;

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'claim' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );
	}

	/**
	 * This site's key, made once.
	 *
	 * @return string
	 */
	public static function key() {
		$key = (string) get_option( self::OPTION, '' );

		if ( 1 === preg_match( '/^[a-zA-Z0-9-]{8,128}$/', $key ) ) {
			return $key;
		}

		$key = bin2hex( random_bytes( 16 ) );

		update_option( self::OPTION, $key, false );

		return $key;
	}

	/**
	 * Where the key file sits.
	 *
	 * @return string
	 */
	public static function path() {
		return '/' . self::key() . '.txt';
	}

	/**
	 * The full address of the key file.
	 *
	 * @return string
	 */
	public static function key_location() {
		return home_url( self::path() );
	}

	/**
	 * What the key file holds.
	 *
	 * @return string
	 */
	public static function body() {
		return self::key();
	}

	/**
	 * Whether a request is for the key file.
	 *
	 * @param string $path The path asked for.
	 * @return bool
	 */
	public static function serves( $path ) {
		return self::path() === (string) $path;
	}

	/**
	 * Add the rewrite rule.
	 */
	public static function rules() {
		add_rewrite_rule( '^' . preg_quote( self::key(), '/' ) . '\.txt$', 'index.php?solseo_indexnow=1', 'top' );
	}

	/**
	 * Let WordPress carry our query variable.
	 *
	 * @param array $vars Query variables.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'solseo_indexnow';

		return $vars;
	}

	/**
	 * Claim the request before WordPress decides it is a 404.
	 *
	 * @param \WP $wp The request.
	 */
	public static function claim( $wp ) {
		if ( ! empty( $wp->query_vars['solseo_indexnow'] ) ) {
			return;
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';

		if ( self::serves( $path ) ) {
			$wp->query_vars['solseo_indexnow'] = 1;
		}
	}

	/**
	 * Serve the key file.
	 */
	public static function serve() {
		if ( ! get_query_var( 'solseo_indexnow' ) ) {
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

		echo esc_html( self::body() );

		exit;
	}

	/**
	 * The request body, the shape the protocol documents.
	 *
	 * Addresses on another site are dropped here rather than sent and refused.
	 * The engines answer 422 for the whole batch when one address does not
	 * belong to the host, so one stray address loses the rest of them.
	 *
	 * @param array $urls Addresses.
	 * @return array
	 */
	public static function payload( array $urls ) {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$ours = array();

		foreach ( $urls as $url ) {
			$url = trim( (string) $url );

			if ( '' === $url || 0 !== strpos( $url, 'http' ) ) {
				continue;
			}

			if ( (string) wp_parse_url( $url, PHP_URL_HOST ) !== $host ) {
				continue;
			}

			$ours[] = $url;

			if ( count( $ours ) >= self::BATCH ) {
				break;
			}
		}

		return array(
			'host'        => $host,
			'key'         => self::key(),
			'keyLocation' => self::key_location(),
			'urlList'     => array_values( array_unique( $ours ) ),
		);
	}

	/**
	 * Send a batch.
	 *
	 * @param array $urls Addresses.
	 * @return array|\WP_Error Keys: ok, says, wait.
	 */
	public static function submit( array $urls ) {
		$payload = self::payload( $urls );

		if ( ! $payload['urlList'] ) {
			return array(
				'ok'   => true,
				'says' => __( 'Nothing to send.', 'solseo' ),
				'wait' => false,
			);
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return self::read( wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * What an answer means, in words worth printing.
	 *
	 * 403 is the one that matters. It means the engine looked for the key file
	 * and did not find it, which on a WordPress site is nearly always a cache
	 * or a security plugin standing in front of the root. Printing "403" sends
	 * somebody to a forum; naming the file sends them to the thing to check.
	 *
	 * @param int $code HTTP status.
	 * @return array Keys: ok, says, wait.
	 */
	public static function read( $code ) {
		$code = (int) $code;

		if ( 200 === $code || 202 === $code ) {
			return array(
				'ok'   => true,
				'says' => 202 === $code
					? __( 'Accepted. The engines have it and are checking the key file.', 'solseo' )
					: __( 'Accepted.', 'solseo' ),
				'wait' => false,
			);
		}

		if ( 400 === $code ) {
			return array(
				'ok'   => false,
				'says' => __( 'The engines called the request malformed, which is a fault at this end. Nothing was submitted.', 'solseo' ),
				'wait' => false,
			);
		}

		if ( 403 === $code ) {
			return array(
				'ok'   => false,
				/* translators: %s: the address of the key file. */
				'says' => sprintf( __( 'The engines could not read the key file at %s. Open that address yourself: if it does not show a line of letters and numbers, a cache or a security plugin is in the way.', 'solseo' ), self::key_location() ),
				'wait' => false,
			);
		}

		if ( 422 === $code ) {
			return array(
				'ok'   => false,
				'says' => __( 'The engines said those addresses do not belong to this site. That usually means the site address setting and the address people actually visit are different.', 'solseo' ),
				'wait' => false,
			);
		}

		if ( 429 === $code ) {
			return array(
				'ok'   => false,
				'says' => __( 'The engines said that is too many for now. Nothing is lost, and the next change will be submitted as usual.', 'solseo' ),
				'wait' => true,
			);
		}

		return array(
			'ok'   => false,
			/* translators: %d: an HTTP status code. */
			'says' => sprintf( __( 'The engines answered %d. Worth trying again later.', 'solseo' ), $code ),
			'wait' => true,
		);
	}
}
