<?php
/**
 * Gets readable text out of a post.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Content extraction shared by the analyser, the tag output and the sitemap.
 */
class Content {

	/**
	 * Post content with blocks rendered and shortcodes removed.
	 *
	 * The `the_content` filter is deliberately not applied. Page builders and
	 * related-post plugins hook it and would put their markup into the score.
	 *
	 * @param \WP_Post|int $post Post or post ID.
	 * @return string HTML.
	 */
	public static function rendered( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return '';
		}

		$html = $post->post_content;

		if ( has_blocks( $html ) ) {
			$html = do_blocks( $html );
		}

		$html = strip_shortcodes( $html );
		$html = wpautop( $html );

		if ( 'product' === $post->post_type && $post->post_excerpt ) {
			$html = wpautop( $post->post_excerpt ) . $html;
		}

		return $html;
	}

	/**
	 * Plain text, with entities decoded and whitespace collapsed.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function plain( $html ) {
		$text = preg_replace( '#<(script|style)[^>]*>.*?</\1>#si', ' ', (string) $html );
		$text = str_replace( array( '<br>', '<br/>', '<br />' ), ' ', $text );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\x{00a0}/u', ' ', $text );

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * A short description of a post, for meta descriptions and previews.
	 *
	 * @param int $post_id Post ID.
	 * @param int $words   Word limit.
	 * @return string
	 */
	public static function summary( $post_id, $words = 30 ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		$source = $post->post_excerpt ? $post->post_excerpt : self::plain( self::rendered( $post ) );

		return wp_trim_words( self::plain( $source ), $words, '' );
	}

	/**
	 * The first paragraph of a post, as plain text.
	 *
	 * @param string $html Rendered content.
	 * @return string
	 */
	public static function first_paragraph( $html ) {
		if ( preg_match( '#<p[^>]*>(.*?)</p>#si', (string) $html, $match ) ) {
			return self::plain( $match[1] );
		}

		$text = self::plain( $html );

		return $text ? implode( ' ', array_slice( explode( ' ', $text ), 0, 60 ) ) : '';
	}

	/**
	 * Every heading in the content, keyed by level.
	 *
	 * @param string $html Rendered content.
	 * @return array Each entry is array( 'level' => int, 'text' => string ).
	 */
	public static function headings( $html ) {
		if ( ! preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#si', (string) $html, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$headings = array();

		foreach ( $matches as $match ) {
			$headings[] = array(
				'level' => (int) $match[1],
				'text'  => self::plain( $match[2] ),
			);
		}

		return $headings;
	}

	/**
	 * Every link in the content, split into internal and external.
	 *
	 * @param string $html Rendered content.
	 * @return array Keys: internal, external, nofollow.
	 */
	public static function links( $html ) {
		$links = array(
			'internal' => array(),
			'external' => array(),
			'nofollow' => 0,
		);

		if ( ! preg_match_all( '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>#i', (string) $html, $matches, PREG_SET_ORDER ) ) {
			return $links;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( $matches as $match ) {
			$href = trim( $match[1] );

			if ( '' === $href || 0 === strpos( $href, '#' ) || preg_match( '#^(mailto|tel|javascript):#i', $href ) ) {
				continue;
			}

			if ( false !== stripos( $match[0], 'nofollow' ) ) {
				++$links['nofollow'];
			}

			$link_host = wp_parse_url( $href, PHP_URL_HOST );

			if ( ! $link_host || $link_host === $host ) {
				$links['internal'][] = $href;
			} else {
				$links['external'][] = $href;
			}
		}

		return $links;
	}

	/**
	 * Every image in the content, with its alt text.
	 *
	 * @param string $html Rendered content.
	 * @return array Each entry is array( 'src' => string, 'alt' => string ).
	 */
	public static function images( $html ) {
		if ( ! preg_match_all( '#<img\s[^>]*>#i', (string) $html, $matches ) ) {
			return array();
		}

		$images = array();

		foreach ( $matches[0] as $tag ) {
			preg_match( '#src=["\']([^"\']*)["\']#i', $tag, $src );
			preg_match( '#alt=["\']([^"\']*)["\']#i', $tag, $alt );

			$images[] = array(
				'src' => isset( $src[1] ) ? $src[1] : '',
				'alt' => isset( $alt[1] ) ? self::plain( $alt[1] ) : '',
			);
		}

		return $images;
	}
}
