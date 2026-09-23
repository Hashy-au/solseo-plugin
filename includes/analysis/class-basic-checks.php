<?php
/**
 * Checks that apply whether or not a focus keyword is set.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

use SolSEO\Contract;

defined( 'ABSPATH' ) || exit;

/**
 * Titles, descriptions, length, links, images and headings.
 */
class Basic_Checks extends Checks {

	/** Widest a title can be before search results cut it off. */
	const TITLE_MAX = 580;

	/** Below this a title is wasting the space it has. */
	const TITLE_MIN = 285;

	/** Widest a description can be before it is cut off. */
	const DESCRIPTION_MAX = 920;

	/** Below this a description is thinner than it needs to be. */
	const DESCRIPTION_MIN = 430;

	/**
	 * Group name.
	 *
	 * @return string
	 */
	public static function group() {
		return 'basics';
	}

	/**
	 * Run the group.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	public static function run( array $paper ) {
		return array(
			self::title( $paper ),
			self::description( $paper ),
			self::length( $paper ),
			self::internal_links( $paper ),
			self::outbound_links( $paper ),
			self::images( $paper ),
			self::alt_text( $paper ),
			self::headings( $paper ),
			self::single_h1( $paper ),
			self::slug( $paper ),
		);
	}

	/**
	 * Title width.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function title( array $paper ) {
		$title = trim( $paper['title'] );

		if ( '' === $title ) {
			return self::result( 'title_width', 4, self::POOR, __( 'This page has no SEO title.', 'solseo' ) );
		}

		$width = Text::pixel_width( $title, 20 );

		if ( $width > self::TITLE_MAX ) {
			return self::result( 'title_width', 4, self::POOR, __( 'The title is too wide and will be cut off. Shorten it.', 'solseo' ) );
		}

		if ( $width < self::TITLE_MIN ) {
			return self::result( 'title_width', 4, self::FAIR, __( 'The title is short. There is room for more.', 'solseo' ) );
		}

		return self::result( 'title_width', 4, self::GOOD, __( 'The title fits the width search results allow.', 'solseo' ) );
	}

	/**
	 * Description width.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function description( array $paper ) {
		$description = trim( $paper['description'] );

		if ( '' === $description ) {
			return self::result( 'description_width', 4, self::POOR, __( 'Write a meta description. Without one the search engine picks its own text.', 'solseo' ) );
		}

		$width = Text::pixel_width( $description, 14 );

		if ( $width > self::DESCRIPTION_MAX ) {
			return self::result( 'description_width', 4, self::FAIR, __( 'The description will be cut off. Put what matters in the first sentence.', 'solseo' ) );
		}

		if ( $width < self::DESCRIPTION_MIN ) {
			return self::result( 'description_width', 4, self::FAIR, __( 'The description is short. Use the space you have.', 'solseo' ) );
		}

		return self::result( 'description_width', 4, self::GOOD, __( 'The description fits the space search results allow.', 'solseo' ) );
	}

	/**
	 * Word count.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function length( array $paper ) {
		$count  = count( $paper['words'] );
		$target = self::minimum_words( $paper['post_type'] );

		if ( $count >= $target ) {
			/* translators: %d: number of words. */
			return self::result( 'content_length', 4, self::GOOD, sprintf( __( '%d words. Long enough to say something.', 'solseo' ), $count ) );
		}

		if ( $count >= (int) round( $target * 0.6 ) ) {
			return self::result(
				'content_length',
				4,
				self::FAIR,
				sprintf(
					/* translators: 1: word count, 2: recommended word count. */
					__( '%1$d words. Around %2$d would cover the subject properly.', 'solseo' ),
					$count,
					$target
				)
			);
		}

		return self::result(
			'content_length',
			4,
			self::POOR,
			sprintf(
				/* translators: 1: word count, 2: recommended word count. */
				__( '%1$d words is thin. Aim for at least %2$d.', 'solseo' ),
				$count,
				$target
			)
		);
	}

	/**
	 * Links to other pages on this site.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function internal_links( array $paper ) {
		$count = count( $paper['links']['internal'] );

		if ( $count > 0 ) {
			return self::result( 'internal_links', 3, self::GOOD, __( 'The page links to other pages on this site.', 'solseo' ) );
		}

		return self::result( 'internal_links', 3, self::POOR, __( 'Link to at least one other page on this site.', 'solseo' ) );
	}

	/**
	 * Links off the site.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function outbound_links( array $paper ) {
		if ( $paper['links']['external'] ) {
			return self::result( 'outbound_links', 2, self::GOOD, __( 'The page links out to a source.', 'solseo' ) );
		}

		return self::result( 'outbound_links', 2, self::FAIR, __( 'Linking to a source others can check adds weight to a claim.', 'solseo' ) );
	}

	/**
	 * Whether the page has any images.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function images( array $paper ) {
		if ( $paper['images'] ) {
			return self::result( 'images', 2, self::GOOD, __( 'The page has at least one image.', 'solseo' ) );
		}

		return self::result( 'images', 2, self::POOR, __( 'Add an image. A page of unbroken text is hard to read.', 'solseo' ) );
	}

	/**
	 * Alt text coverage.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function alt_text( array $paper ) {
		if ( ! $paper['images'] ) {
			return self::result( 'image_alt', 3, self::SKIPPED, __( 'There are no images to describe.', 'solseo' ) );
		}

		/*
		 * MISSING AND EMPTY ARE COUNTED SEPARATELY (D-207.2).
		 *
		 * An absent alt is an image nobody described. An `alt=""` is somebody
		 * saying the image carries nothing, which is correct on a logo or a
		 * divider and is what this check's own advice tells people to write.
		 * Counting the second as the first is what made the hub's audit report
		 * 37 of 43 pages for one correctly marked header logo.
		 *
		 * An empty alt with no aria-hidden, no role="presentation" and no
		 * enclosing link text is still worth mentioning, because a product
		 * photo with a blank alt is a real loss. It lands as FAIR, never POOR:
		 * it is a question, not a fault.
		 */
		$missing     = 0;
		$unexplained = 0;

		foreach ( $paper['images'] as $image ) {
			if ( Contract::alt_is_missing( $image ) ) {
				++$missing;
			} elseif ( Contract::alt_is_unexplained_empty( $image ) ) {
				++$unexplained;
			}
		}

		if ( ! $missing && ! $unexplained ) {
			return self::result( 'image_alt', 3, self::GOOD, __( 'Every image has alt text.', 'solseo' ) );
		}

		if ( ! $missing ) {
			$note = sprintf(
				/* translators: %d: number of images whose alt text is empty. */
				_n(
					'%d image has empty alt text. That is right for a decoration. If it shows the product, describe it.',
					'%d images have empty alt text. That is right for a decoration. If they show the product, describe them.',
					$unexplained,
					'solseo'
				),
				$unexplained
			);

			return self::result( 'image_alt', 3, self::FAIR, $note );
		}

		$note = sprintf(
			/* translators: %d: number of images without alt text. */
			_n( '%d image has no alt text.', '%d images have no alt text.', $missing, 'solseo' ),
			$missing
		);

		return self::result( 'image_alt', 3, count( $paper['images'] ) === $missing ? self::POOR : self::FAIR, $note );
	}

	/**
	 * Whether the page is broken up with subheadings.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function headings( array $paper ) {
		if ( count( $paper['words'] ) < 300 ) {
			return self::result( 'subheadings', 2, self::SKIPPED, __( 'The page is short enough to read without subheadings.', 'solseo' ) );
		}

		foreach ( $paper['headings'] as $heading ) {
			if ( $heading['level'] >= 2 && $heading['level'] <= 4 ) {
				return self::result( 'subheadings', 2, self::GOOD, __( 'The page is broken up with subheadings.', 'solseo' ) );
			}
		}

		return self::result( 'subheadings', 2, self::POOR, __( 'Add subheadings so the page can be skimmed.', 'solseo' ) );
	}

	/**
	 * More than one first level heading inside the content.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function single_h1( array $paper ) {
		$count = 0;

		foreach ( $paper['headings'] as $heading ) {
			if ( 1 === $heading['level'] ) {
				++$count;
			}
		}

		if ( $count > 1 ) {
			return self::result( 'single_h1', 2, self::POOR, __( 'The content holds more than one H1. Most themes already print the page title as the H1.', 'solseo' ) );
		}

		return self::result( 'single_h1', 2, self::GOOD, __( 'The heading levels are in order.', 'solseo' ) );
	}

	/**
	 * Slug length.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	protected static function slug( array $paper ) {
		$length = strlen( (string) $paper['slug'] );

		if ( ! $length ) {
			return self::result( 'slug_length', 1, self::SKIPPED, __( 'This page has no slug yet.', 'solseo' ) );
		}

		if ( $length <= 75 ) {
			return self::result( 'slug_length', 1, self::GOOD, __( 'The URL is a readable length.', 'solseo' ) );
		}

		return self::result( 'slug_length', 1, $length <= 100 ? self::FAIR : self::POOR, __( 'The URL is long. Trim it to the words that matter.', 'solseo' ) );
	}

	/**
	 * Word count a post type is expected to reach.
	 *
	 * @param string $post_type Post type name.
	 * @return int
	 */
	protected static function minimum_words( $post_type ) {
		$targets = array(
			'post'    => 300,
			'page'    => 300,
			'product' => 150,
		);

		$target = isset( $targets[ $post_type ] ) ? $targets[ $post_type ] : 300;

		/**
		 * Filter the word count a post type should reach.
		 *
		 * @param int    $target    Word count.
		 * @param string $post_type Post type name.
		 */
		return (int) apply_filters( 'solseo_minimum_words', $target, $post_type );
	}
}
