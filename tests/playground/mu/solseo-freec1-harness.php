<?php
/**
 * Plugin Name: SolSEO FreeC1 harness
 *
 * Google, the relay and Search Console, answered on this machine.
 *
 * NETWORKING IS OFF in the blueprint, so `pre_http_request` is not a
 * convenience: it is what makes "the plugin reached exactly these four
 * addresses and no others" a thing the probe can assert rather than assume.
 * Anything the plugin asks for that is not one of the four comes back as an
 * error naming the address, which fails the probe rather than hanging it.
 *
 * An mu-plugin loads before any ordinary plugin, so the filter is in place
 * before the free plugin has decided anything.
 *
 * THE PROPERTY LIST IS BUILT FROM THIS SITE'S OWN HOST at the moment it is
 * asked for, because the Playground's address is not knowable when this file
 * is written and a hard coded one would make the matching test pass or fail
 * for a reason that has nothing to do with the matching.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['solseo_freec1_asked'] = array();

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$GLOBALS['solseo_freec1_asked'][] = array(
			'url'    => $url,
			'method' => isset( $args['method'] ) ? $args['method'] : 'GET',
			'body'   => isset( $args['body'] ) ? $args['body'] : '',
		);

		$answer = function ( $code, $body ) {
			return array(
				'response' => array(
					'code'    => $code,
					'message' => 'OK',
				),
				'body'     => wp_json_encode( $body ),
				'headers'  => array(),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		if ( 'https://solseo.com.au/api/v1/plugin/google/token' === $url
			|| 'https://oauth2.googleapis.com/token' === $url ) {
			return $answer(
				200,
				array(
					'access_token'  => 'ya29.freec1-probe',
					'expires_in'    => 3599,
					'refresh_token' => '1//0g-freec1-probe',
					'scope'         => 'https://www.googleapis.com/auth/webmasters.readonly',
					'token_type'    => 'Bearer',
				)
			);
		}

		if ( 0 === strpos( $url, 'https://oauth2.googleapis.com/revoke' ) ) {
			return $answer( 200, array() );
		}

		if ( 'https://searchconsole.googleapis.com/webmasters/v3/sites' === $url ) {
			$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

			return $answer(
				200,
				array(
					'siteEntry' => array(
						array(
							'siteUrl'         => 'sc-domain:somebody-else.example',
							'permissionLevel' => 'siteOwner',
						),
						array(
							'siteUrl'         => 'sc-domain:' . $host,
							'permissionLevel' => 'siteOwner',
						),
					),
				)
			);
		}

		if ( false !== strpos( $url, 'searchconsole.googleapis.com' )
			&& false !== strpos( $url, 'searchAnalytics/query' ) ) {
			$asked = json_decode( (string) ( isset( $args['body'] ) ? $args['body'] : '' ), true );

			/*
			 * The totals call sends no dimensions and the queries call sends
			 * `query`, which is how the real API tells them apart too. One
			 * stub answering both the way Google does is what proves the
			 * plugin reads the two shapes correctly rather than one twice.
			 */
			if ( ! empty( $asked['dimensions'] ) ) {
				return $answer(
					200,
					array(
						'rows' => array(
							array(
								'keys'        => array( 'mongolian bow' ),
								'clicks'      => 22,
								'impressions' => 610,
								'ctr'         => 0.036,
								'position'    => 8.1,
							),
							array(
								'keys'        => array( 'horsebow australia' ),
								'clicks'      => 11,
								'impressions' => 420,
								'ctr'         => 0.026,
								'position'    => 14.9,
							),
						),
					)
				);
			}

			return $answer(
				200,
				array(
					'rows' => array(
						array(
							'clicks'      => 41,
							'impressions' => 1820,
							'ctr'         => 0.0225,
							'position'    => 12.4,
						),
					),
				)
			);
		}

		return new WP_Error(
			'solseo_freec1_blocked',
			'Nothing but Google and the relay may be reached from this probe: ' . $url
		);
	},
	10,
	3
);
