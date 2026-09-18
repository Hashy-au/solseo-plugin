<?php
/**
 * Talks to the SolSEO service.
 *
 * @package SolSEO
 */

namespace SolSEO\Hub;

defined( 'ABSPATH' ) || exit;

/**
 * A thin HTTP client. Nothing here runs until the site has been connected.
 */
class Client {

	/** The wire format this plugin speaks. */
	const CONTRACT = '1';

	/** Where the service lives. */
	const BASE_URL = 'https://solseo.com.au';

	/**
	 * Exchange a pairing code for a key.
	 *
	 * @param string $code The code shown in the dashboard.
	 * @return array|\WP_Error
	 */
	public static function pair( $code ) {
		return self::request(
			'POST',
			'/api/v1/plugin/pair',
			array(
				'pairing_code'   => strtoupper( trim( $code ) ),
				'home_url'       => home_url(),
				'plugin_version' => SOLSEO_VERSION,
			),
			false
		);
	}

	/**
	 * What the connected account is entitled to.
	 *
	 * @return array|\WP_Error
	 */
	public static function entitlement() {
		return self::request( 'GET', '/api/v1/plugin/entitlement' );
	}

	/**
	 * Tell the service what this site is running.
	 *
	 * @param array $facts Site facts.
	 * @return array|\WP_Error
	 */
	public static function facts( array $facts ) {
		return self::request( 'POST', '/api/v1/plugin/facts', $facts );
	}

	/**
	 * The account's sites, when the service offers the summary.
	 *
	 * @return array|null Null when the service does not answer with one.
	 */
	public static function overview() {
		$response = self::request( 'GET', '/api/v1/plugin/overview' );

		return is_wp_error( $response ) ? null : $response;
	}

	/**
	 * The address of the service.
	 *
	 * @return string
	 */
	public static function base_url() {
		$stored = Connection::get( 'url' );

		/**
		 * Filter the address of the SolSEO service.
		 *
		 * @param string $url Base address, with no trailing slash.
		 */
		return untrailingslashit( apply_filters( 'solseo_hub_url', $stored ? $stored : self::BASE_URL ) );
	}

	/**
	 * Make a request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path below the base address.
	 * @param array  $body   Body for a write.
	 * @param bool   $auth   Whether the request needs the stored key.
	 * @return array|\WP_Error
	 */
	protected static function request( $method, $path, array $body = array(), $auth = true ) {
		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Accept'            => 'application/json',
				'X-SolSEO-Contract' => self::CONTRACT,
			),
		);

		if ( $auth ) {
			$key = Connection::get( 'key' );

			if ( ! $key ) {
				return new \WP_Error( 'solseo_not_connected', __( 'This site is not connected to SolSEO.', 'solseo' ) );
			}

			$args['headers']['Authorization'] = 'Bearer ' . $key;
		}

		if ( $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::base_url() . $path, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 204 === $code ) {
			return array();
		}

		if ( $code >= 400 ) {
			$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : __( 'SolSEO could not be reached.', 'solseo' );

			return new \WP_Error( 'solseo_http_' . $code, $message, array( 'status' => $code ) );
		}

		return is_array( $data ) ? $data : array();
	}
}
