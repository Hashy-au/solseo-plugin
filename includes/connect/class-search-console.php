<?php
/**
 * What Google says about this site, read with the site owner's own permission.
 *
 * @package SolSEO
 */

namespace SolSEO\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Two questions, asked read only, and never more than two.
 *
 * `sites.list` is asked once, at connection, so the owner can see which of
 * their properties this site matches. `searchanalytics.query` is asked for one
 * page at a time, while somebody is editing that page, and the answer is kept
 * on this server for six hours so that opening the same page twice does not
 * ask twice.
 *
 * ── NOTHING THAT COMES BACK LEAVES THIS SERVER ──────────────────────────────
 *
 * The readme says so and tests/test-google.php proves it: this file opens
 * connections to searchconsole.googleapis.com and to nowhere else, and it is
 * asserted that it is not one of the two files that can reach solseo.com.au.
 * Catalogue wide reporting, sixteen months of history and the decay queue all
 * want this data on the hub, and every one of them is a paid batch that will
 * have to ask for that in its own words rather than inherit it from here.
 *
 * ── A FAILURE IS A SENTENCE, NEVER AN EMPTY CHART ───────────────────────────
 *
 * A chart with nothing in it says "this page gets no traffic", which is a
 * claim about the site. A revoked token, a property the account lost access
 * to and an exhausted quota are claims about the connection, and a person can
 * act on all three. classify() is the one place the difference is decided and
 * refusal() is the one place it is put into words.
 */
class Search_Console {

	/** Where both questions go. */
	const API = 'https://searchconsole.googleapis.com/webmasters/v3/';

	/** How many days one page's figures cover. */
	const DAYS = 28;

	/** Google will not answer for the last few days anyway. */
	const LAG_DAYS = 2;

	/** How long an answer is kept on this server. */
	const KEPT = 21600;

	/** How many search terms are worth showing beside one page. */
	const QUERY_LIMIT = 10;

	/** Bumped to forget everything kept, without having to enumerate it. */
	const GENERATION = 'solseo_gsc_generation';

	/**
	 * Every property this Google account can see.
	 *
	 * @return array|\WP_Error List of arrays with siteUrl and permissionLevel.
	 */
	public static function properties() {
		$answer = self::ask( 'GET', 'sites', array() );

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		$out = array();

		foreach ( isset( $answer['siteEntry'] ) ? (array) $answer['siteEntry'] : array() as $entry ) {
			if ( empty( $entry['siteUrl'] ) ) {
				continue;
			}

			$out[] = array(
				'siteUrl'         => (string) $entry['siteUrl'],
				'permissionLevel' => isset( $entry['permissionLevel'] ) ? (string) $entry['permissionLevel'] : '',
			);
		}

		return $out;
	}

	/**
	 * The property that is this site, if the account holds one.
	 *
	 * A domain property wins, because it covers www and plain and http and
	 * https at once, and picking a URL prefix over it is how somebody ends up
	 * with a connection that reports nothing for half their pages.
	 *
	 * @param string $home       This site's address.
	 * @param array  $properties What properties() returned.
	 * @return string The siteUrl, or an empty string.
	 */
	public static function best_match( $home, array $properties ) {
		$host = strtolower( (string) wp_parse_url( (string) $home, PHP_URL_HOST ) );

		if ( '' === $host ) {
			return '';
		}

		$bare   = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
		$prefix = '';

		foreach ( $properties as $property ) {
			$url = isset( $property['siteUrl'] ) ? (string) $property['siteUrl'] : '';

			if ( 0 === strpos( $url, 'sc-domain:' ) ) {
				$domain = strtolower( substr( $url, 10 ) );

				if ( $domain === $bare || $domain === $host ) {
					return $url;
				}

				continue;
			}

			$theirs = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

			if ( '' !== $theirs && ( $host === $theirs || $bare === $theirs || 'www.' . $bare === $theirs ) && '' === $prefix ) {
				$prefix = $url;
			}
		}

		return $prefix;
	}

	/**
	 * What to say when nothing matched.
	 *
	 * Listing what the account CAN see is the whole value of this message. The
	 * usual cause is that the property is the www one, or the http one, or a
	 * different Google account entirely, and the list says which at a glance.
	 *
	 * @param string $home       This site's address.
	 * @param array  $properties What properties() returned.
	 * @return string
	 */
	public static function unmatched_sentence( $home, array $properties ) {
		$host = (string) wp_parse_url( (string) $home, PHP_URL_HOST );

		if ( ! $properties ) {
			return sprintf(
				/* translators: %s: this site's host, such as example.com.au. */
				__( 'That Google account can see no Search Console properties at all, so there is nothing to match %s to. Add this site in Search Console first, then connect again.', 'solseo' ),
				$host
			);
		}

		$names = array();

		foreach ( $properties as $property ) {
			$names[] = isset( $property['siteUrl'] ) ? (string) $property['siteUrl'] : '';
		}

		return sprintf(
			/* translators: 1: this site's host. 2: a comma separated list of Search Console properties. */
			__( 'None of the properties that Google account can see is %1$s. It can see: %2$s. Pick one below if one of them is this site, or add this site in Search Console and connect again.', 'solseo' ),
			$host,
			implode( ', ', array_filter( $names ) )
		);
	}

	/**
	 * The last twenty eight days for one page.
	 *
	 * @param string $url   The page's address.
	 * @param bool   $fresh True to ignore what is kept.
	 * @return array|\WP_Error
	 */
	public static function page( $url, $fresh = false ) {
		$url = (string) $url;

		if ( '' === $url ) {
			return new \WP_Error( 'solseo_google_no_url', __( 'There is no address to ask about yet. Save this page first.', 'solseo' ) );
		}

		$slot = self::slot( $url );

		if ( ! $fresh ) {
			$kept = get_transient( $slot );

			if ( is_array( $kept ) ) {
				return $kept;
			}
		}

		$range = self::range();

		$totals = self::ask(
			'POST',
			'sites/' . rawurlencode( Google::property() ) . '/searchAnalytics/query',
			array(
				'startDate'             => $range['from'],
				'endDate'               => $range['to'],
				'dimensions'            => array(),
				'dimensionFilterGroups' => self::only( $url ),
				'rowLimit'              => 1,
			)
		);

		if ( is_wp_error( $totals ) ) {
			return $totals;
		}

		$queries = self::ask(
			'POST',
			'sites/' . rawurlencode( Google::property() ) . '/searchAnalytics/query',
			array(
				'startDate'             => $range['from'],
				'endDate'               => $range['to'],
				'dimensions'            => array( 'query' ),
				'dimensionFilterGroups' => self::only( $url ),
				'rowLimit'              => self::QUERY_LIMIT,
			)
		);

		if ( is_wp_error( $queries ) ) {
			return $queries;
		}

		$first  = isset( $totals['rows'][0] ) ? (array) $totals['rows'][0] : array();
		$result = array(
			'url'         => $url,
			'property'    => Google::property(),
			'days'        => self::DAYS,
			'from'        => $range['from'],
			'to'          => $range['to'],
			'clicks'      => isset( $first['clicks'] ) ? (int) $first['clicks'] : 0,
			'impressions' => isset( $first['impressions'] ) ? (int) $first['impressions'] : 0,
			'ctr'         => isset( $first['ctr'] ) ? (float) $first['ctr'] : 0.0,
			'position'    => isset( $first['position'] ) ? (float) $first['position'] : 0.0,
			'has'         => isset( $totals['rows'][0] ),
			'queries'     => array(),
			'read_at'     => time(),
		);

		foreach ( isset( $queries['rows'] ) ? (array) $queries['rows'] : array() as $row ) {
			if ( empty( $row['keys'][0] ) ) {
				continue;
			}

			$result['queries'][] = array(
				'query'       => (string) $row['keys'][0],
				'clicks'      => isset( $row['clicks'] ) ? (int) $row['clicks'] : 0,
				'impressions' => isset( $row['impressions'] ) ? (int) $row['impressions'] : 0,
				'position'    => isset( $row['position'] ) ? (float) $row['position'] : 0.0,
			);
		}

		set_transient( $slot, $result, self::KEPT );

		return $result;
	}

	/**
	 * Forget everything kept.
	 *
	 * By moving a number rather than by deleting rows. WordPress has no way to
	 * list transients that works on every host, and a loop over option names
	 * is a table scan on a site with an external object cache, where the
	 * transients are not in the table at all.
	 */
	public static function forget_all() {
		update_option( self::GENERATION, (int) get_option( self::GENERATION, 0 ) + 1, false );
	}

	/**
	 * What kind of refusal this is, in one word.
	 *
	 * @param int   $code HTTP status.
	 * @param array $body Decoded body.
	 * @return string|null 'revoked', 'permission', 'quota', 'refused', or null.
	 */
	public static function classify( $code, array $body ) {
		$code   = (int) $code;
		$status = isset( $body['error']['status'] ) ? (string) $body['error']['status'] : '';

		if ( 401 === $code || 'UNAUTHENTICATED' === $status ) {
			return 'revoked';
		}

		if ( 429 === $code || 'RESOURCE_EXHAUSTED' === $status ) {
			return 'quota';
		}

		if ( 403 === $code || 'PERMISSION_DENIED' === $status ) {
			return 'permission';
		}

		if ( $code >= 400 || isset( $body['error'] ) ) {
			return 'refused';
		}

		return null;
	}

	/**
	 * The refusal, in words somebody can act on.
	 *
	 * @param int   $code HTTP status.
	 * @param array $body Decoded body.
	 * @return \WP_Error
	 */
	public static function refusal( $code, array $body ) {
		$said = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : '';

		switch ( self::classify( $code, $body ) ) {
			case 'revoked':
				return new \WP_Error(
					'solseo_google_revoked',
					__( 'Google no longer accepts this connection. It was either revoked in your Google account or it expired. Nothing is being read until you connect again.', 'solseo' )
				);

			case 'permission':
				return new \WP_Error(
					'solseo_google_permission',
					sprintf(
						/* translators: %s: a Search Console property, such as sc-domain:example.com.au. */
						__( 'That Google account no longer has permission to read %s in Search Console. Ask whoever owns the property to add it again, or pick a different property.', 'solseo' ),
						Google::property()
					)
				);

			case 'quota':
				return new \WP_Error(
					'solseo_google_quota',
					__( 'Google\'s daily allowance for this account has run out. It resets at midnight Pacific time, which is early evening in Australia, and the figures already read are still shown below.', 'solseo' )
				);

			default:
				return new \WP_Error(
					'solseo_google_refused',
					sprintf(
						/* translators: 1: an HTTP status code. 2: the words Google used. */
						__( 'Search Console answered %1$d and said: %2$s', 'solseo' ),
						(int) $code,
						'' !== $said ? $said : __( 'nothing at all.', 'solseo' )
					)
				);
		}
	}

	/**
	 * One question, asked read only.
	 *
	 * @param string $method 'GET' or 'POST'.
	 * @param string $path   Under the v3 base.
	 * @param array  $ask    The body, for a POST.
	 * @return array|\WP_Error
	 */
	protected static function ask( $method, $path, array $ask ) {
		$token = Google::access_token();

		if ( '' === $token ) {
			return new \WP_Error(
				'solseo_google_off',
				__( 'This site is not connected to a Google account, so there is nothing to read. Connect one under SolSEO, Settings, Connections.', 'solseo' )
			);
		}

		if ( 0 === strpos( $path, 'sites/' ) && '' === Google::property() ) {
			return new \WP_Error(
				'solseo_google_no_property',
				__( 'No Search Console property is matched to this site yet. Pick one under SolSEO, Settings, Connections.', 'solseo' )
			);
		}

		$args = array(
			'timeout'    => 20,
			'user-agent' => 'SolSEO/' . SOLSEO_VERSION,
			'headers'    => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( $ask );
			$response     = wp_remote_post( self::API . $path, $args );
		} else {
			$response = wp_remote_get( self::API . $path, $args );
		}

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'solseo_google_unreachable',
				__( 'Google could not be reached from this server just now. Nothing has changed, and the figures shown are the ones already read.', 'solseo' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$trouble = self::classify( $code, $body );

		if ( null !== $trouble ) {
			if ( 'revoked' === $trouble ) {
				Google::mark_revoked();
			}

			return self::refusal( $code, $body );
		}

		return $body;
	}

	/**
	 * The filter that narrows a query to one page.
	 *
	 * @param string $url The page.
	 * @return array
	 */
	protected static function only( $url ) {
		return array(
			array(
				'filters' => array(
					array(
						'dimension'  => 'page',
						'operator'   => 'equals',
						'expression' => $url,
					),
				),
			),
		);
	}

	/**
	 * The twenty eight days, ending where Google's data ends.
	 *
	 * Google is two to three days behind, so a range ending today reports two
	 * days of zeros on the end of every page and makes a healthy page look
	 * like it fell off a cliff.
	 *
	 * @return array Keys: from, to.
	 */
	protected static function range() {
		$to = time() - ( self::LAG_DAYS * DAY_IN_SECONDS );

		return array(
			'from' => gmdate( 'Y-m-d', $to - ( ( self::DAYS - 1 ) * DAY_IN_SECONDS ) ),
			'to'   => gmdate( 'Y-m-d', $to ),
		);
	}

	/**
	 * Where one page's answer is kept.
	 *
	 * @param string $url The page.
	 * @return string
	 */
	protected static function slot( $url ) {
		return 'solseo_gsc_' . substr(
			hash( 'sha256', (int) get_option( self::GENERATION, 0 ) . '|' . Google::property() . '|' . $url ),
			0,
			22
		);
	}
}
