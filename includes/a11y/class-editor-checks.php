<?php
/**
 * The accessibility failures a writer causes, read off what they wrote.
 *
 * This is the content half and only the content half. A theme with no focus
 * outline, a menu that cannot be reached with a keyboard and a colour scheme
 * that fails everywhere are real problems and none of them are here, because
 * none of them are in the box somebody is typing in. Saying so on the screen
 * matters more than the checks do: a writer who is told the page is fine and
 * then finds out otherwise stops reading anything this plugin says.
 *
 * Every function here is pure. It takes markup and returns findings, so the
 * editor, the Health screen and the tests all read the same six rules.
 *
 * @package SolSEO
 */

namespace SolSEO\A11y;

defined( 'ABSPATH' ) || exit;

/**
 * Six content level accessibility checks over a piece of markup.
 */
class Editor_Checks {

	/** How many findings one reading returns before it stops counting them out. */
	const MAX_FINDINGS = 50;

	/** The contrast a normal sized piece of text needs, from WCAG 1.4.3. */
	const CONTRAST = 4.5;

	/** Link words that describe nothing on their own. */
	const VAGUE = array(
		'click here',
		'click',
		'here',
		'read more',
		'more',
		'this link',
		'link',
		'learn more',
		'find out more',
		'details',
		'this page',
		'continue',
		'go',
	);

	/**
	 * Every finding in a piece of markup, in the order they appear.
	 *
	 * @param string $html The content being written or the content as stored.
	 * @return array Each with rule, element, says and guideline.
	 */
	public static function run( $html ) {
		$html = (string) $html;

		$found = array_merge(
			self::images( $html ),
			self::headings( $html ),
			self::links( $html ),
			self::contrast( $html ),
			self::tables( $html ),
			self::fields( $html )
		);

		return array_slice( $found, 0, self::MAX_FINDINGS );
	}

	/**
	 * What one rule is called on screen.
	 *
	 * @param string $rule Rule key.
	 * @return string
	 */
	public static function rule_label( $rule ) {
		$labels = array(
			'image_alt'     => __( 'An image with nothing saying what it is', 'solseo' ),
			'heading_order' => __( 'A heading level that was skipped', 'solseo' ),
			'link_text'     => __( 'Link words that describe nothing', 'solseo' ),
			'contrast'      => __( 'Text too close in colour to what is behind it', 'solseo' ),
			'table_header'  => __( 'A table with no header row', 'solseo' ),
			'field_label'   => __( 'A form field with no label', 'solseo' ),
		);

		return isset( $labels[ $rule ] ) ? $labels[ $rule ] : $rule;
	}

	/**
	 * The one sentence that says how far this reads.
	 *
	 * @return string
	 */
	public static function covers() {
		return __( 'This reads the content of a page: the words, images, links, tables and fields in it. It does not read your theme, your menus or anything outside the content, and it is not an audit of the site.', 'solseo' );
	}

	/**
	 * Images with nothing anywhere saying what they are.
	 *
	 * Pure. An alt attribute that is there and empty is left alone: alt="" is
	 * the correct way to mark a picture that adds nothing to the words, and a
	 * checker that argues with correct markup is a checker somebody switches
	 * off, which is D-80.4 pointed at markup instead of at spelling.
	 *
	 * @param string $html Markup.
	 * @return array
	 */
	public static function images( $html ) {
		if ( ! preg_match_all( '#<img\s[^>]*>#i', (string) $html, $tags ) ) {
			return array();
		}

		$found = array();

		foreach ( $tags[0] as $tag ) {
			if ( preg_match( '/\salt\s*=/i', $tag ) || preg_match( '/\srole\s*=\s*["\']presentation["\']/i', $tag ) ) {
				continue;
			}

			$found[] = array(
				'rule'      => 'image_alt',
				'element'   => self::shorten( $tag ),
				'says'      => __( 'This image has no alt attribute at all, so a screen reader announces the file name. Write what the image shows, or set alt="" if it is decoration.', 'solseo' ),
				'guideline' => '1.1.1',
			);
		}

		return $found;
	}

	/**
	 * Headings that skip a level on the way down.
	 *
	 * Pure. Going from a level two to a level four tells somebody reading by
	 * headings that a whole section is missing. Coming back up is normal and is
	 * not reported.
	 *
	 * @param string $html Markup.
	 * @return array
	 */
	public static function headings( $html ) {
		if ( ! preg_match_all( '#<h([1-6])\b[^>]*>(.*?)</h\1>#is', (string) $html, $tags, PREG_SET_ORDER ) ) {
			return array();
		}

		$found = array();
		$last  = 0;

		foreach ( $tags as $tag ) {
			$level = (int) $tag[1];
			$text  = trim( (string) wp_strip_all_tags( $tag[2] ) );

			if ( $last > 0 && $level > $last + 1 ) {
				$found[] = array(
					'rule'      => 'heading_order',
					'element'   => '<h' . $level . '>' . self::shorten( $text ) . '</h' . $level . '>',
					'says'      => sprintf(
						/* translators: 1: the heading level before, such as 2. 2: the heading level found, such as 4. */
						__( 'This is a level %2$d heading under a level %1$d, so a level in between is missing. Somebody reading by headings is told a section was skipped.', 'solseo' ),
						$last,
						$level
					),
					'guideline' => '1.3.1',
				);
			}

			$last = $level;
		}

		return $found;
	}

	/**
	 * Links whose words say nothing about where they go.
	 *
	 * Pure. A screen reader can list every link on a page, and a list of nine
	 * links all called "read more" is a list of nine places nobody can choose
	 * between. A link carrying an aria-label is left alone, because the label
	 * is the accessible name and that is what gets read out.
	 *
	 * @param string $html Markup.
	 * @return array
	 */
	public static function links( $html ) {
		if ( ! preg_match_all( '#<a\s[^>]*href=[^>]*>(.*?)</a>#is', (string) $html, $tags, PREG_SET_ORDER ) ) {
			return array();
		}

		$found = array();

		foreach ( $tags as $tag ) {
			if ( preg_match( '/\saria-label(?:ledby)?\s*=/i', $tag[0] ) ) {
				continue;
			}

			$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $tag[1] ) ) );
			$bare = trim( strtolower( (string) preg_replace( '/[^a-z0-9 ]+/i', '', $text ) ) );

			if ( '' === $text ) {
				// An image inside the link carries the words instead.
				if ( preg_match( '#<img\s#i', $tag[1] ) ) {
					continue;
				}

				$found[] = array(
					'rule'      => 'link_text',
					'element'   => self::shorten( $tag[0] ),
					'says'      => __( 'This link has no words in it at all, so there is nothing to read out.', 'solseo' ),
					'guideline' => '2.4.4',
				);

				continue;
			}

			if ( ! in_array( $bare, self::VAGUE, true ) ) {
				continue;
			}

			$found[] = array(
				'rule'      => 'link_text',
				'element'   => self::shorten( $tag[0] ),
				'says'      => sprintf(
					/* translators: %s: the words the link was written on, such as click here. */
					__( 'The words on this link are "%s", which describes nothing on its own. Put the name of the page being linked to in the link.', 'solseo' ),
					$text
				),
				'guideline' => '2.4.4',
			);
		}

		return $found;
	}

	/**
	 * Text whose inline colours are too close together to read.
	 *
	 * Pure, and deliberately narrow: this only judges an element that sets both
	 * its own colour and its own background in a style attribute, because those
	 * are the two numbers needed and they are both right there. Guessing the
	 * background out of the theme would be a contrast figure nobody measured,
	 * which is the objection D-79.5 raised about a number that is always zero,
	 * pointed at a number that is sometimes wrong.
	 *
	 * @param string $html Markup.
	 * @return array
	 */
	public static function contrast( $html ) {
		if ( ! preg_match_all( '#<([a-z][a-z0-9]*)\s[^>]*style\s*=\s*(["\'])(.*?)\2[^>]*>#is', (string) $html, $tags, PREG_SET_ORDER ) ) {
			return array();
		}

		$found = array();

		foreach ( $tags as $tag ) {
			$style = (string) $tag[3];

			$ink   = self::colour_in( $style, 'color' );
			$paper = self::colour_in( $style, 'background-color' );

			if ( null === $paper ) {
				$paper = self::colour_in( $style, 'background' );
			}

			if ( null === $ink || null === $paper ) {
				continue;
			}

			$ratio = self::ratio( $ink, $paper );

			if ( $ratio >= self::CONTRAST ) {
				continue;
			}

			$found[] = array(
				'rule'      => 'contrast',
				'element'   => self::shorten( $tag[0] ),
				'says'      => sprintf(
					/* translators: 1: a contrast ratio such as 2.1. 2: the ratio normal text needs, such as 4.5. */
					__( 'These two colours are %1$s to 1 apart. Normal sized text needs %2$s to 1, so darken the text or lighten what is behind it.', 'solseo' ),
					number_format_i18n( $ratio, 1 ),
					number_format_i18n( self::CONTRAST, 1 )
				),
				'guideline' => '1.4.3',
			);
		}

		return $found;
	}

	/**
	 * Tables whose rows and columns are not named.
	 *
	 * Pure. A table with no th is a grid of numbers with nothing to say which
	 * number belongs to what, which is exactly what a screen reader reads out.
	 *
	 * @param string $html Markup.
	 * @return array
	 */
	public static function tables( $html ) {
		if ( ! preg_match_all( '#<table\b[^>]*>(.*?)</table>#is', (string) $html, $tags, PREG_SET_ORDER ) ) {
			return array();
		}

		$found = array();

		foreach ( $tags as $tag ) {
			if ( preg_match( '#<th\b#i', $tag[1] ) ) {
				continue;
			}

			// A layout table declares itself, and nothing is read out of it.
			if ( preg_match( '/\srole\s*=\s*["\'](?:presentation|none)["\']/i', $tag[0] ) ) {
				continue;
			}

			$found[] = array(
				'rule'      => 'table_header',
				'element'   => self::shorten( '<table>' . wp_strip_all_tags( $tag[1] ) ),
				'says'      => __( 'This table has no header cells, so nothing says which column or row a number belongs to. Make the first row th cells with a scope.', 'solseo' ),
				'guideline' => '1.3.1',
			);
		}

		return $found;
	}

	/**
	 * Form fields nothing names.
	 *
	 * Pure. A field is named by a label pointing at its id, by an aria-label, by
	 * an aria-labelledby or by a title, and a field named by none of those is a
	 * box a screen reader reads out as "edit text, blank".
	 *
	 * @param string $html Markup.
	 * @return array
	 */
	public static function fields( $html ) {
		$html = (string) $html;

		if ( ! preg_match_all( '#<(input|select|textarea)\b[^>]*>#i', $html, $tags, PREG_SET_ORDER ) ) {
			return array();
		}

		$labelled = self::labelled_ids( $html );
		$found    = array();

		foreach ( $tags as $tag ) {
			$type = strtolower( self::attribute( $tag[0], 'type' ) );

			if ( 'input' === strtolower( $tag[1] ) && in_array( $type, array( 'hidden', 'submit', 'button', 'image', 'reset' ), true ) ) {
				continue;
			}

			if ( preg_match( '/\s(?:aria-label|aria-labelledby|title|placeholder)\s*=\s*["\'][^"\']+["\']/i', $tag[0] ) ) {
				continue;
			}

			$id = self::attribute( $tag[0], 'id' );

			if ( '' !== $id && in_array( strtolower( $id ), $labelled, true ) ) {
				continue;
			}

			$found[] = array(
				'rule'      => 'field_label',
				'element'   => self::shorten( $tag[0] ),
				'says'      => __( 'Nothing names this field, so it is read out as an empty box. Give it an id and point a label at that id.', 'solseo' ),
				'guideline' => '3.3.2',
			);
		}

		return $found;
	}

	/**
	 * The ids every label in a piece of markup points at.
	 *
	 * Pure.
	 *
	 * @param string $html Markup.
	 * @return array Lowercased ids.
	 */
	protected static function labelled_ids( $html ) {
		if ( ! preg_match_all( '#<label\b[^>]*\sfor\s*=\s*(["\'])(.*?)\1#i', (string) $html, $found ) ) {
			return array();
		}

		return array_map( 'strtolower', array_map( 'trim', $found[2] ) );
	}

	/**
	 * One declaration's colour out of a style attribute.
	 *
	 * Pure. Returns red, green and blue, or nothing when the value is a name
	 * this does not know, a gradient, a variable or a transparency: a colour
	 * nobody can resolve is a check that must not run rather than a guess.
	 *
	 * @param string $style    A style attribute.
	 * @param string $property Which declaration.
	 * @return array|null
	 */
	public static function colour_in( $style, $property ) {
		if ( ! preg_match( '/(?:^|;)\s*' . preg_quote( $property, '/' ) . '\s*:\s*([^;]+)/i', (string) $style, $found ) ) {
			return null;
		}

		return self::colour( trim( $found[1] ) );
	}

	/**
	 * A colour as red, green and blue.
	 *
	 * Pure.
	 *
	 * @param string $value A CSS colour.
	 * @return array|null
	 */
	public static function colour( $value ) {
		$value = strtolower( trim( (string) $value ) );

		$names = array(
			'black'   => array( 0, 0, 0 ),
			'white'   => array( 255, 255, 255 ),
			'red'     => array( 255, 0, 0 ),
			'green'   => array( 0, 128, 0 ),
			'blue'    => array( 0, 0, 255 ),
			'yellow'  => array( 255, 255, 0 ),
			'grey'    => array( 128, 128, 128 ),
			'gray'    => array( 128, 128, 128 ),
			'silver'  => array( 192, 192, 192 ),
			'navy'    => array( 0, 0, 128 ),
			'orange'  => array( 255, 165, 0 ),
			'purple'  => array( 128, 0, 128 ),
			'lime'    => array( 0, 255, 0 ),
			'maroon'  => array( 128, 0, 0 ),
			'olive'   => array( 128, 128, 0 ),
			'teal'    => array( 0, 128, 128 ),
			'aqua'    => array( 0, 255, 255 ),
			'fuchsia' => array( 255, 0, 255 ),
		);

		if ( isset( $names[ $value ] ) ) {
			return $names[ $value ];
		}

		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $found ) ) {
			return array(
				hexdec( str_repeat( $found[1][0], 2 ) ),
				hexdec( str_repeat( $found[1][1], 2 ) ),
				hexdec( str_repeat( $found[1][2], 2 ) ),
			);
		}

		if ( preg_match( '/^#([0-9a-f]{6})$/', $value, $found ) ) {
			return array(
				hexdec( substr( $found[1], 0, 2 ) ),
				hexdec( substr( $found[1], 2, 2 ) ),
				hexdec( substr( $found[1], 4, 2 ) ),
			);
		}

		if ( preg_match( '/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)\s*(?:[,\/]\s*([0-9.]+)\s*)?\)$/', $value, $found ) ) {
			// Anything see through is a colour over something nobody here knows.
			if ( isset( $found[4] ) && (float) $found[4] < 1 ) {
				return null;
			}

			return array( (int) $found[1], (int) $found[2], (int) $found[3] );
		}

		return null;
	}

	/**
	 * How far apart two colours are, by the WCAG formula.
	 *
	 * Pure.
	 *
	 * @param array $ink   Red, green and blue.
	 * @param array $paper Red, green and blue.
	 * @return float
	 */
	public static function ratio( array $ink, array $paper ) {
		$one = self::luminance( $ink );
		$two = self::luminance( $paper );

		$light = max( $one, $two );
		$dark  = min( $one, $two );

		return (float) round( ( $light + 0.05 ) / ( $dark + 0.05 ), 2 );
	}

	/**
	 * How bright a colour is, by the WCAG formula.
	 *
	 * Pure.
	 *
	 * @param array $colour Red, green and blue.
	 * @return float
	 */
	protected static function luminance( array $colour ) {
		$parts = array();

		foreach ( array( 0, 1, 2 ) as $index ) {
			$value = ( isset( $colour[ $index ] ) ? (int) $colour[ $index ] : 0 ) / 255;

			$parts[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $parts[0] ) + ( 0.7152 * $parts[1] ) + ( 0.0722 * $parts[2] );
	}

	/**
	 * One attribute off a tag.
	 *
	 * Pure.
	 *
	 * @param string $tag  The tag.
	 * @param string $name Attribute name.
	 * @return string
	 */
	protected static function attribute( $tag, $name ) {
		return preg_match( '#\s' . preg_quote( $name, '#' ) . '\s*=\s*["\']([^"\']*)["\']#i', (string) $tag, $found )
			? (string) $found[1]
			: '';
	}

	/**
	 * Enough of something to recognise it by.
	 *
	 * Pure.
	 *
	 * @param string $text Any markup or words.
	 * @return string
	 */
	protected static function shorten( $text ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );

		return strlen( $text ) > 120 ? substr( $text, 0, 117 ) . '...' : $text;
	}
}
