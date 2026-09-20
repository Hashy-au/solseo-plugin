<?php
/**
 * The speed check, from Google's PageSpeed Insights.
 *
 * @package SolSEO
 */

namespace SolSEO\Connect;

use SolSEO\Speed\Causes;

defined( 'ABSPATH' ) || exit;

/**
 * One call, which carries both halves of the answer.
 *
 * The design asked for a PageSpeed Insights key and a Chrome UX Report key.
 * The PageSpeed response already carries the field data, in
 * `loadingExperience` for the page and `originLoadingExperience` for the site,
 * so a second key and a second call would fetch what the first one returned.
 *
 * The key is documented as optional. It is not, in practice: the keyless
 * endpoint answered 429 with the daily quota at zero on 2026-09-19, and the
 * response is kept at `tests/fixtures/psi/quota-exceeded.json`. The screen
 * therefore asks for a key rather than offering to work without one slowly.
 */
class Psi {

	/** Where the call goes. Declared in readme.txt under External services. */
	const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	/** Lighthouse takes its time, and so must we. */
	const TIMEOUT = 60;

	/** How long an answer is kept before the button fetches a new one. */
	const KEPT = 1800;

	/** The field measurements worth showing, in the order they are shown. */
	const METRICS = array(
		'LARGEST_CONTENTFUL_PAINT_MS',
		'INTERACTION_TO_NEXT_PAINT',
		'CUMULATIVE_LAYOUT_SHIFT_SCORE',
		'EXPERIMENTAL_TIME_TO_FIRST_BYTE',
	);

	/**
	 * Check one address.
	 *
	 * @param string $url      The address.
	 * @param string $strategy 'mobile' or 'desktop'.
	 * @param bool   $fresh    True to ignore what was kept.
	 * @return array|\WP_Error
	 */
	public static function run( $url, $strategy = 'mobile', $fresh = false ) {
		$url      = esc_url_raw( (string) $url );
		$strategy = 'desktop' === $strategy ? 'desktop' : 'mobile';

		if ( '' === $url ) {
			return new \WP_Error( 'solseo_no_url', __( 'That is not an address this site serves.', 'solseo' ) );
		}

		$slot = self::slot( $url, $strategy );

		if ( ! $fresh ) {
			$kept = get_transient( $slot );

			if ( is_array( $kept ) ) {
				return $kept;
			}
		}

		$query = array(
			'url'      => $url,
			'strategy' => $strategy,
			'category' => 'performance',
		);

		$key = Keys::get( Keys::GOOGLE );

		if ( '' !== $key ) {
			$query['key'] = $key;
		}

		$response = wp_remote_get(
			self::ENDPOINT . '?' . http_build_query( $query ),
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => 'SolSEO/' . SOLSEO_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'solseo_psi_unreadable', __( 'Google answered with something this plugin could not read.', 'solseo' ) );
		}

		$problem = self::problem( $code, $body );

		if ( $problem ) {
			return $problem;
		}

		$result = self::normalise( $body );

		set_transient( $slot, $result, self::KEPT );

		return $result;
	}

	/**
	 * Whether the call was refused, in the words of whoever refused it.
	 *
	 * A paraphrase hides the sentence that says what to do about it. "Quota
	 * exceeded for quota metric Queries" tells somebody to get a key.
	 * "The speed check failed" tells them to file a support ticket.
	 *
	 * @param int   $code HTTP status.
	 * @param array $body Decoded body.
	 * @return \WP_Error|null
	 */
	public static function problem( $code, array $body ) {
		if ( ! empty( $body['error']['message'] ) ) {
			return new \WP_Error( 'solseo_psi_refused', (string) $body['error']['message'] );
		}

		if ( (int) $code >= 400 ) {
			/* translators: %d: an HTTP status code. */
			return new \WP_Error( 'solseo_psi_refused', sprintf( __( 'Google answered %d and said nothing else.', 'solseo' ), (int) $code ) );
		}

		if ( empty( $body['lighthouseResult'] ) ) {
			return new \WP_Error( 'solseo_psi_empty', __( 'Google answered without a result in it. That usually means the address could not be reached from outside.', 'solseo' ) );
		}

		return null;
	}

	/**
	 * The response, reduced to what a screen shows.
	 *
	 * @param array $body Decoded body.
	 * @return array
	 */
	public static function normalise( array $body ) {
		$lab = isset( $body['lighthouseResult'] ) ? (array) $body['lighthouseResult'] : array();

		return array(
			'url'        => isset( $lab['finalUrl'] ) ? (string) $lab['finalUrl'] : '',
			'score'      => isset( $lab['categories']['performance']['score'] )
				? (int) round( 100 * (float) $lab['categories']['performance']['score'] )
				: null,
			'field'      => self::experience( isset( $body['loadingExperience'] ) ? (array) $body['loadingExperience'] : array() ),
			'origin'     => self::experience( isset( $body['originLoadingExperience'] ) ? (array) $body['originLoadingExperience'] : array() ),
			'findings'   => self::findings( isset( $lab['audits'] ) ? (array) $lab['audits'] : array() ),
			'fetched_at' => time(),
		);
	}

	/**
	 * What real visitors measured, if Google has enough of them.
	 *
	 * @param array $raw loadingExperience or originLoadingExperience.
	 * @return array
	 */
	protected static function experience( array $raw ) {
		$metrics = array();

		foreach ( self::METRICS as $name ) {
			if ( ! isset( $raw['metrics'][ $name ]['percentile'] ) ) {
				continue;
			}

			$metrics[ $name ] = array(
				'value' => (int) $raw['metrics'][ $name ]['percentile'],
				'band'  => isset( $raw['metrics'][ $name ]['category'] ) ? (string) $raw['metrics'][ $name ]['category'] : '',
			);
		}

		return array(
			'has'     => (bool) $metrics,
			'overall' => isset( $raw['overall_category'] ) ? (string) $raw['overall_category'] : '',
			'metrics' => $metrics,
		);
	}

	/**
	 * The audits that did not pass, worst first, each with a cause.
	 *
	 * @param array $audits Lighthouse audits.
	 * @return array
	 */
	protected static function findings( array $audits ) {
		$known    = Causes::installed();
		$findings = array();

		foreach ( $audits as $id => $audit ) {
			if ( ! is_array( $audit ) || ! isset( $audit['score'] ) || null === $audit['score'] ) {
				continue;
			}

			$score = (float) $audit['score'];

			if ( $score >= 0.9 ) {
				continue;
			}

			$items = isset( $audit['details']['items'] ) && is_array( $audit['details']['items'] ) ? $audit['details']['items'] : array();

			$findings[] = array(
				'id'     => (string) $id,
				'title'  => isset( $audit['title'] ) ? (string) $audit['title'] : (string) $id,
				'says'   => isset( $audit['displayValue'] ) ? (string) $audit['displayValue'] : '',
				'score'  => $score,
				'causes' => self::causes( $items, $known ),
			);
		}

		usort(
			$findings,
			static function ( $one, $two ) {
				if ( $one['score'] === $two['score'] ) {
					return strcmp( $one['id'], $two['id'] );
				}

				return $one['score'] < $two['score'] ? -1 : 1;
			}
		);

		return $findings;
	}

	/**
	 * What is behind the addresses one audit listed.
	 *
	 * A finding with no addresses still gets a cause, because "we could not
	 * work out what this is part of" is an answer and a bare Lighthouse string
	 * on its own is not.
	 *
	 * @param array $items One audit's details.items.
	 * @param array $known What is installed.
	 * @return array
	 */
	protected static function causes( array $items, array $known ) {
		$causes = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['url'] ) ) {
				continue;
			}

			$cause  = Causes::for_url( (string) $item['url'], $known );
			$bytes  = isset( $item['wastedBytes'] ) ? (int) $item['wastedBytes'] : 0;
			$weight = Causes::weight( $bytes );

			if ( '' !== $weight ) {
				/* translators: 1: what the file belongs to, 2: a file size such as 2.4 MB. */
				$cause['says'] = sprintf( __( '%1$s, %2$s of it', 'solseo' ), $cause['says'], $weight );
			}

			$cause['url'] = (string) $item['url'];

			$causes[] = $cause;

			if ( count( $causes ) >= 5 ) {
				break;
			}
		}

		if ( ! $causes ) {
			$causes[] = Causes::for_url( '', $known );
		}

		return $causes;
	}

	/**
	 * Where one answer is kept.
	 *
	 * @param string $url      Address.
	 * @param string $strategy Strategy.
	 * @return string
	 */
	protected static function slot( $url, $strategy ) {
		return 'solseo_psi_' . substr( hash( 'sha256', $strategy . '|' . $url ), 0, 24 );
	}
}
