<?php
/**
 * Which analytics tags are on the front of this site, and who put them there.
 *
 * Three questions and no more. Is a tag there, does it load once or twice, and
 * is the consent plugin on this site holding it back. No wiring, no events, no
 * report: naming the plugin that owns a tag is the whole of what this does, and
 * it is the question nobody can answer by looking, because six plugins can each
 * add the same tag and none of them says so.
 *
 * IT OPENS NO CONNECTION OF ITS OWN. The only way to know what is on the front
 * of the site is to read the front of the site, and that goes through
 * SolSEO\Crawl\Fetch, which is the one file in this plugin allowed to reach this
 * site and refuses everything else. There is no new destination here and no new
 * entry in the egress map: this is a caller of the existing choke point, which
 * is exactly what D-84.2 said that choke point was for.
 *
 * @package SolSEO
 */

namespace SolSEO\Analytics;

use SolSEO\Crawl\Fetch;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the home page and says what is measuring it.
 */
class Tag_Check {

	/** The last reading. Never autoloaded. */
	const OPTION = 'solseo_tag_check';

	/**
	 * The tags this knows how to recognise.
	 *
	 * Each entry carries two patterns. `loader` matches the thing that actually
	 * starts the tag, which is what makes "twice" answerable: a normal Google
	 * Analytics page holds its measurement id two or three times over, once in
	 * the script address and again in every config call, so counting the id
	 * would tell almost every site on earth that its tag fires three times.
	 * `id` matches the measurement id anywhere, which catches a tag loaded
	 * through a tag manager with no loader of its own.
	 *
	 * Every row cites the operator's own documentation, and a test fails on one
	 * that does not, which is D-81.4 again: a list of script addresses somebody
	 * half remembers finds nothing and tells the reader they are covered.
	 *
	 * @return array
	 */
	public static function tags() {
		return array(
			'ga4'       => array(
				'label'  => __( 'Google Analytics 4', 'solseo' ),
				'loader' => '#googletagmanager\.com/gtag/js\?[^"\'<>]*id=(G-[A-Z0-9]+)#i',
				'id'     => '#\b(G-[A-Z0-9]{6,})\b#',
				'docs'   => 'https://developers.google.com/analytics/devguides/collection/ga4',
			),
			'gtm'       => array(
				'label'  => __( 'Google Tag Manager', 'solseo' ),
				'loader' => '#googletagmanager\.com/gtm\.js[^"\'<>]*[?&]id=(GTM-[A-Z0-9]+)#i',
				'id'     => '#\b(GTM-[A-Z0-9]{4,})\b#',
				'docs'   => 'https://developers.google.com/tag-platform/tag-manager/web',
			),
			'universal' => array(
				'label'  => __( 'Google Analytics, the old one', 'solseo' ),
				'loader' => '#google-analytics\.com/(?:analytics|ga)\.js#i',
				'id'     => '#\b(UA-\d{4,}-\d+)\b#',
				'docs'   => 'https://support.google.com/analytics/answer/11583528',
			),
			'meta'      => array(
				'label'  => __( 'Meta pixel', 'solseo' ),
				'loader' => '#fbq\(\s*[\'"]init[\'"]\s*,\s*[\'"](\d{6,})[\'"]#i',
				'id'     => '#fbq\(\s*[\'"]init[\'"]\s*,\s*[\'"](\d{6,})[\'"]#i',
				'docs'   => 'https://developers.facebook.com/docs/meta-pixel/get-started',
			),
			'clarity'   => array(
				'label'  => __( 'Microsoft Clarity', 'solseo' ),
				'loader' => '#clarity\.ms/tag/([a-z0-9]+)#i',
				'id'     => '#clarity\.ms/tag/([a-z0-9]+)#i',
				'docs'   => 'https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-setup',
			),
			'uet'       => array(
				'label'  => __( 'Microsoft Advertising UET', 'solseo' ),
				'loader' => '#bat\.bing\.com/(?:bat|action/0)\.js#i',
				'id'     => '#\bti\s*:\s*[\'"](\d{6,})[\'"]#i',
				'docs'   => 'https://help.ads.microsoft.com/#apex/ads/en/56681',
			),
			'tiktok'    => array(
				'label'  => __( 'TikTok pixel', 'solseo' ),
				'loader' => '#analytics\.tiktok\.com/i18n/pixel#i',
				'id'     => '#ttq\.load\(\s*[\'"]([A-Z0-9]{10,})[\'"]#i',
				'docs'   => 'https://business-api.tiktok.com/portal/docs?id=1739584860883969',
			),
			'hotjar'    => array(
				'label'  => __( 'Hotjar', 'solseo' ),
				'loader' => '#static\.hotjar\.com/c/hotjar#i',
				'id'     => '#hjid\s*:\s*(\d{5,})#i',
				'docs'   => 'https://help.hotjar.com/hc/en-us/articles/115009336727',
			),
			'pinterest' => array(
				'label'  => __( 'Pinterest tag', 'solseo' ),
				'loader' => '#s\.pinimg\.com/ct/core\.js#i',
				'id'     => '#pintrk\(\s*[\'"]load[\'"]\s*,\s*[\'"](\d{6,})[\'"]#i',
				'docs'   => 'https://help.pinterest.com/en/business/article/install-the-pinterest-tag',
			),
			'matomo'    => array(
				'label'  => __( 'Matomo', 'solseo' ),
				'loader' => '#/(?:matomo|piwik)\.js#i',
				'id'     => '#setSiteId[\'"\s,\]\[]+(\d+)#i',
				'docs'   => 'https://matomo.org/faq/new-to-piwik/how-do-i-install-the-matomo-tracking-code',
			),
		);
	}

	/**
	 * The comments the tag plugins print about themselves.
	 *
	 * These are markers a plugin puts on its own output, which is the only
	 * reliable way to say who printed a script: by the time the page is HTML,
	 * nothing in it remembers which plugin echoed it.
	 *
	 * @return array Marker to plugin name.
	 */
	public static function markers() {
		return array(
			'added by Site Kit'                   => 'Site Kit by Google',
			'Google Analytics by MonsterInsights' => 'MonsterInsights',
			'ExactMetrics'                        => 'ExactMetrics',
			'Google Tag Manager for WordPress'    => 'GTM4WP',
			'gtm4wp'                              => 'GTM4WP',
			'PixelYourSite'                       => 'PixelYourSite',
			'Analytify'                           => 'Analytify',
			'WPCode'                              => 'WPCode',
			'woocommerce-google-analytics'        => 'Google Analytics for WooCommerce',
			'Independent Analytics'               => 'Independent Analytics',
		);
	}

	/**
	 * Plugins that hold a tag back until somebody agrees to it.
	 *
	 * Keyed by the attribute or class each one puts on the script it is holding,
	 * because the marker in the markup is the evidence that it is actually doing
	 * it on this page. Being installed is not the same as being switched on.
	 *
	 * @return array
	 */
	public static function consent_markers() {
		return array(
			'data-cookieconsent'  => 'Cookiebot',
			'data-cookieyes'      => 'CookieYes',
			'data-cmplz-src'      => 'Complianz',
			'data-cli-class'      => 'GDPR Cookie Consent',
			'data-borlabs-cookie' => 'Borlabs Cookie',
			'data-iub'            => 'iubenda',
			'data-rcb'            => 'Real Cookie Banner',
			'data-cookie-consent' => 'a consent plugin',
			'type="text/plain"'   => 'a consent plugin',
			"type='text/plain'"   => 'a consent plugin',
		);
	}

	/**
	 * What one page says about itself.
	 *
	 * Pure. Takes markup and gives back one row per tag found.
	 *
	 * @param string $html The front page markup.
	 * @return array Each with key, label, ids, loads, blocked, blocked_by and docs.
	 */
	public static function read( $html ) {
		$html  = (string) $html;
		$found = array();

		foreach ( self::tags() as $key => $tag ) {
			$loads = preg_match_all( $tag['loader'], $html, $loaded );
			$ids   = array();

			if ( $loads && ! empty( $loaded[1] ) ) {
				$ids = array_values( array_filter( $loaded[1] ) );
			}

			if ( preg_match_all( $tag['id'], $html, $named ) && ! empty( $named[1] ) ) {
				$ids = array_merge( $ids, array_filter( $named[1] ) );
			}

			$ids = array_values( array_unique( $ids ) );

			if ( ! $loads && ! $ids ) {
				continue;
			}

			$held = self::held_back( $html, $tag['loader'] );

			$found[ $key ] = array(
				'key'        => $key,
				'label'      => $tag['label'],
				'ids'        => $ids,
				'loads'      => (int) $loads,
				'blocked'    => '' !== $held,
				'blocked_by' => $held,
				'docs'       => $tag['docs'],
			);
		}

		return $found;
	}

	/**
	 * Whether the script that starts a tag is being held back on this page.
	 *
	 * Pure. A consent plugin holds a script by rewriting the tag around it, so
	 * the evidence is on the script element itself rather than in the list of
	 * plugins: a consent plugin that is installed and configured to allow
	 * analytics is not blocking anything, and saying it is would send somebody
	 * looking for a problem they do not have.
	 *
	 * @param string $html   The markup.
	 * @param string $loader The pattern that matches the loader.
	 * @return string The plugin holding it, or an empty string.
	 */
	public static function held_back( $html, $loader ) {
		if ( ! preg_match_all( '#<script\b[^>]*>#i', (string) $html, $tags ) ) {
			return '';
		}

		foreach ( $tags[0] as $tag ) {
			if ( ! preg_match( $loader, $tag ) ) {
				continue;
			}

			foreach ( self::consent_markers() as $marker => $name ) {
				if ( false !== stripos( $tag, $marker ) ) {
					return $name;
				}
			}
		}

		return '';
	}

	/**
	 * The plugins that said in the markup that this is theirs.
	 *
	 * Pure.
	 *
	 * @param string $html The markup.
	 * @return array Plugin names.
	 */
	public static function owners_in_markup( $html ) {
		$html  = (string) $html;
		$found = array();

		foreach ( self::markers() as $marker => $name ) {
			if ( false !== stripos( $html, $marker ) ) {
				$found[ $name ] = true;
			}
		}

		return array_keys( $found );
	}

	/**
	 * The active plugins on this site whose job is tags.
	 *
	 * Named from each plugin's own header rather than from a table of our own,
	 * which is D-82.2 applied again: a list of names written by hand is out of
	 * date the week it is written, and a plugin's header is always what the
	 * plugin is called.
	 *
	 * @return array Plugin names.
	 */
	public static function owners_installed() {
		$names = array();

		foreach ( self::active_plugins() as $header ) {
			$name = isset( $header['Name'] ) ? (string) $header['Name'] : '';

			if ( '' === $name ) {
				continue;
			}

			if ( ! preg_match( '/analytic|tag manager|pixel|gtm|clarity|hotjar|matomo|measurement|conversion track/i', $name . ' ' . ( isset( $header['Description'] ) ? $header['Description'] : '' ) ) ) {
				continue;
			}

			$names[ $name ] = true;
		}

		return array_keys( $names );
	}

	/**
	 * Read the front page and keep what it said.
	 *
	 * @return array|\WP_Error
	 */
	public static function check() {
		$answer = Fetch::get( home_url( '/' ) );

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		if ( (int) $answer['code'] < 200 || (int) $answer['code'] >= 400 ) {
			return new \WP_Error(
				'solseo_tag_unreadable',
				sprintf(
					/* translators: %d: an HTTP status code such as 503. */
					__( 'Your home page answered %d, so there was nothing to read.', 'solseo' ),
					(int) $answer['code']
				)
			);
		}

		$report = array(
			'read_at'   => time(),
			'url'       => (string) $answer['url'],
			'tags'      => self::read( (string) $answer['body'] ),
			'markup'    => self::owners_in_markup( (string) $answer['body'] ),
			'installed' => self::owners_installed(),
		);

		update_option( self::OPTION, $report, false );

		return $report;
	}

	/**
	 * The last reading.
	 *
	 * @return array
	 */
	public static function stored() {
		$stored = get_option( self::OPTION, array() );

		return array_merge(
			array(
				'read_at'   => 0,
				'url'       => '',
				'tags'      => array(),
				'markup'    => array(),
				'installed' => array(),
			),
			is_array( $stored ) ? $stored : array()
		);
	}

	/**
	 * Whether anything at all is measuring this site.
	 *
	 * @param array $report A report from check() or stored().
	 * @return bool
	 */
	public static function measuring( array $report ) {
		return ! empty( $report['tags'] );
	}

	/**
	 * Whether anything is holding a tag back on this site.
	 *
	 * @param array $report A report from check() or stored().
	 * @return string The plugin holding one, or an empty string.
	 */
	public static function consenting( array $report ) {
		foreach ( (array) $report['tags'] as $tag ) {
			if ( ! empty( $tag['blocked'] ) ) {
				return (string) $tag['blocked_by'];
			}
		}

		return '';
	}

	/**
	 * One sentence about one tag.
	 *
	 * Pure.
	 *
	 * @param array $tag A row from read().
	 * @return string
	 */
	public static function describe( array $tag ) {
		if ( (int) $tag['loads'] > 1 ) {
			return sprintf(
				/* translators: %d: how many times a tag starts on one page. */
				__( 'This starts %d times on one page, so every visit and every sale is counted more than once. Something is adding it twice: a plugin and the theme, or two plugins.', 'solseo' ),
				(int) $tag['loads']
			);
		}

		if ( ! empty( $tag['blocked'] ) ) {
			return sprintf(
				/* translators: %s: the name of a consent plugin. */
				__( 'This is on the page and held back by %s until a visitor agrees, which is why your numbers are lower than your sales.', 'solseo' ),
				(string) $tag['blocked_by']
			);
		}

		if ( ! (int) $tag['loads'] ) {
			return __( 'The measurement id is on the page but nothing on the page starts it, which usually means a tag manager is meant to and has not been told to.', 'solseo' );
		}

		return __( 'On the page, starting once.', 'solseo' );
	}

	/**
	 * Every active plugin's header.
	 *
	 * @return array
	 */
	protected static function active_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			return array();
		}

		$active  = (array) get_option( 'active_plugins', array() );
		$headers = array();

		foreach ( get_plugins() as $file => $header ) {
			if ( in_array( $file, $active, true ) ) {
				$headers[ $file ] = $header;
			}
		}

		return $headers;
	}
}
