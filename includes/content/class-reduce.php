<?php
/**
 * Boils a rendered page down to the tags the checks actually read.
 *
 * @package SolSEO
 */

namespace SolSEO\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Turns builder output into a small document.
 *
 * A builder renders a page as several hundred nested divs carrying layout
 * classes, inline styles and a settings attribute full of JSON. Nothing in
 * this plugin reads any of that. The checks read headings, paragraphs, links
 * and images, and nothing else, so that is what is kept.
 *
 * Two reasons it is worth doing rather than storing the markup as it arrives.
 * A hundred and fifty kilobytes of wrapper per page, cached against every
 * page, is a database nobody asked for. And a class name is not a word: a
 * score that counts `elementor-widget-container` as content is a score that
 * congratulates a page for its scaffolding.
 */
class Reduce {

	/** As much reduced markup as is worth keeping for one page. */
	const MAX = 120000;

	/** Tags whose contents are not words at all. */
	const DROPPED = 'script|style|noscript|svg|template|iframe|select|textarea|object';

	/** Tags kept, with everything but the listed attributes stripped. */
	const KEPT = 'h1|h2|h3|h4|h5|h6|p|li|blockquote|a|img|br|strong|em|b|i|code';

	/** Tags that separate one thought from the next. */
	const BLOCK = 'div|section|article|aside|header|footer|main|nav|figure|figcaption|table|thead|tbody|tfoot|tr|td|th|ul|ol|dl|dt|dd|hr|form|fieldset|address|details|summary|pre|video|audio|picture|canvas|body|html|head';

	/**
	 * The reduced document.
	 *
	 * @param string $html Rendered markup, from wherever.
	 * @return string
	 */
	public static function document( $html ) {
		$html = (string) $html;

		if ( '' === trim( $html ) ) {
			return '';
		}

		$html = preg_replace( '#<!--.*?-->#s', ' ', $html );
		$html = preg_replace( '#<(' . self::DROPPED . ')\b[^>]*/>#i', ' ', (string) $html );
		$html = preg_replace( '#<(' . self::DROPPED . ')\b[^>]*>.*?</\1\s*>#si', ' ', (string) $html );

		$html = preg_replace_callback( '#<(/?)([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>#s', array( __CLASS__, 'tag' ), (string) $html );

		return self::lines( (string) $html );
	}

	/**
	 * One tag, rewritten to its smallest useful form or thrown away.
	 *
	 * @param array $parts Closing slash, tag name, attributes.
	 * @return string
	 */
	protected static function tag( array $parts ) {
		$closing = '' !== $parts[1];
		$name    = strtolower( $parts[2] );
		$attrs   = $parts[3];

		if ( ! preg_match( '#^(' . self::KEPT . ')$#', $name ) ) {
			/*
			 * A block tag ends a line, so two cells or two widgets do not run
			 * their last and first words together. An inline one leaves a
			 * space, because two adjacent spans with an icon between them are
			 * far more common in builder output than emphasis inside a word.
			 */
			return preg_match( '#^(' . self::BLOCK . ')$#', $name ) ? "\n" : ' ';
		}

		if ( 'br' === $name ) {
			return '<br />';
		}

		if ( 'img' === $name ) {
			if ( $closing ) {
				return '';
			}

			return '<img src="' . esc_attr( self::attr( $attrs, 'src' ) ) . '" alt="' . esc_attr( self::attr( $attrs, 'alt' ) ) . '" />';
		}

		if ( 'a' === $name ) {
			if ( $closing ) {
				return '</a>';
			}

			$href = self::attr( $attrs, 'href' );

			if ( '' === $href ) {
				return ' ';
			}

			$rel = self::attr( $attrs, 'rel' );

			return '<a href="' . esc_attr( $href ) . '"' . ( '' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '' ) . '>';
		}

		return $closing ? '</' . $name . '>' : '<' . $name . '>';
	}

	/**
	 * One attribute off a tag's attribute string.
	 *
	 * @param string $attrs Everything between the tag name and the closing bracket.
	 * @param string $name  Attribute name.
	 * @return string
	 */
	protected static function attr( $attrs, $name ) {
		if ( preg_match( '#\b' . preg_quote( $name, '#' ) . '\s*=\s*"([^"]*)"#i', (string) $attrs, $match ) ) {
			return trim( $match[1] );
		}

		if ( preg_match( "#\b" . preg_quote( $name, '#' ) . "\s*=\s*'([^']*)'#i", (string) $attrs, $match ) ) {
			return trim( $match[1] );
		}

		return '';
	}

	/**
	 * Tidy the remains into paragraphs.
	 *
	 * Text that arrives with no tag of its own is wrapped, because a builder
	 * that puts a sentence straight into a div leaves the readability checks
	 * with no paragraphs to measure and the opening line blank.
	 *
	 * @param string $html Markup, tags already reduced.
	 * @return string
	 */
	protected static function lines( $html ) {
		$out    = array();
		$length = 0;

		foreach ( preg_split( '/\n+/', $html ) as $line ) {
			$line = trim( (string) preg_replace( '/[ \t\x{00a0}]+/u', ' ', $line ) );

			/*
			 * An unwrapped span leaves a space where the tag was, and a button
			 * built from two nested spans leaves two at each end of its own
			 * text. Nothing reads them, but they are stored, so they go.
			 */
			$line = (string) preg_replace( '#(<[a-z][a-z0-9]*(?: [^>]*)?>) #i', '$1', $line );
			$line = (string) preg_replace( '# (</[a-z][a-z0-9]*>)#i', '$1', $line );

			if ( '' === $line ) {
				continue;
			}

			$text  = trim( wp_strip_all_tags( $line ) );
			$shows = '' !== $text || false !== stripos( $line, '<img' );

			if ( ! $shows ) {
				continue;
			}

			if ( '' !== $text && ! preg_match( '#^<(h[1-6]|p|li|blockquote)>#i', $line ) ) {
				$line = '<p>' . $line . '</p>';
			}

			$length += strlen( $line ) + 1;

			if ( $length > self::MAX ) {
				break;
			}

			$out[] = $line;
		}

		return implode( "\n", $out );
	}
}
