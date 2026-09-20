<?php
/**
 * Turning an address in a speed report into something worth doing.
 *
 * @package SolSEO
 */

namespace SolSEO\Speed;

defined( 'ABSPATH' ) || exit;

/**
 * Which plugin, which theme, which image.
 *
 * "Reduce unused JavaScript" is what every other tool prints and it tells the
 * person running a shop nothing at all. They cannot reduce unused JavaScript.
 * They can deactivate the slider plugin that is loading 290 kilobytes of it on
 * a page with no slider, and that is the same finding said usefully.
 *
 * Everything below works from the address, because the address is all a speed
 * report gives us. When the address names nothing we recognise, the finding
 * says that, which is the honest end of the same sentence.
 */
class Causes {

	/**
	 * What is behind one address.
	 *
	 * The installed list is passed in rather than read here, so a page with
	 * forty findings reads it once and so this is testable without a
	 * WordPress.
	 *
	 * @param string $url   The address in the report.
	 * @param array  $known Keys: plugins (slug to name), themes (slug to name), home.
	 * @return array Keys: kind, name, says.
	 */
	public static function for_url( $url, array $known ) {
		$url  = (string) $url;
		$home = isset( $known['home'] ) ? (string) $known['home'] : '';
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( '' === $host || 0 !== strpos( $url, 'http' ) ) {
			return self::unknown();
		}

		$home_host = '' !== $home ? (string) wp_parse_url( $home, PHP_URL_HOST ) : '';

		if ( '' !== $home_host && $host !== $home_host ) {
			return array(
				'kind' => 'other-site',
				'name' => $host,
				/* translators: %s: a host name, such as cdn.example.com. */
				'says' => sprintf( __( 'Loaded from %s, which is somebody else\'s server', 'solseo' ), $host ),
			);
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $path, $found ) ) {
			$slug = $found[1];
			$name = isset( $known['plugins'][ $slug ] ) ? $known['plugins'][ $slug ] : $slug;

			return array(
				'kind' => 'plugin',
				'name' => $name,
				/* translators: %s: a plugin name. */
				'says' => sprintf( __( 'The %s plugin', 'solseo' ), $name ),
			);
		}

		if ( preg_match( '#/wp-content/themes/([^/]+)/#', $path, $found ) ) {
			$slug = $found[1];
			$name = isset( $known['themes'][ $slug ] ) ? $known['themes'][ $slug ] : $slug;

			return array(
				'kind' => 'theme',
				'name' => $name,
				/* translators: %s: a theme name. */
				'says' => sprintf( __( 'The %s theme', 'solseo' ), $name ),
			);
		}

		if ( false !== strpos( $path, '/wp-content/uploads/' ) ) {
			$file = basename( $path );

			return array(
				'kind' => 'upload',
				'name' => $file,
				/* translators: %s: a file name. */
				'says' => sprintf( __( 'The file %s in your media library', 'solseo' ), $file ),
			);
		}

		if ( false !== strpos( $path, '/wp-includes/' ) ) {
			return array(
				'kind' => 'wordpress',
				'name' => 'WordPress',
				'says' => __( 'WordPress itself, which is not worth changing', 'solseo' ),
			);
		}

		return self::unknown();
	}

	/**
	 * A size in the words somebody uses out loud.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	public static function weight( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes < 1024 ) {
			return '';
		}

		if ( $bytes >= 1000000 ) {
			return number_format_i18n( round( $bytes / 1000000, 1 ), 1 ) . ' MB';
		}

		return number_format_i18n( (int) round( $bytes / 1000 ) ) . ' KB';
	}

	/**
	 * What is installed on this site, read once.
	 *
	 * @return array
	 */
	public static function installed() {
		$plugins = array();
		$themes  = array();

		if ( ! function_exists( 'get_plugins' ) && is_admin() ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'get_plugins' ) ) {
			foreach ( get_plugins() as $file => $data ) {
				$slug = strtok( (string) $file, '/' );

				if ( $slug ) {
					$plugins[ $slug ] = isset( $data['Name'] ) ? (string) $data['Name'] : $slug;
				}
			}
		}

		if ( function_exists( 'wp_get_themes' ) ) {
			foreach ( wp_get_themes() as $slug => $theme ) {
				$themes[ (string) $slug ] = (string) $theme->get( 'Name' );
			}
		}

		return array(
			'plugins' => $plugins,
			'themes'  => $themes,
			'home'    => home_url( '/' ),
		);
	}

	/**
	 * The honest end of the sentence.
	 *
	 * @return array
	 */
	protected static function unknown() {
		return array(
			'kind' => 'unknown',
			'name' => '',
			'says' => __( 'We could not work out what this is part of', 'solseo' ),
		);
	}
}
