<?php
/**
 * The input every content check reads.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

use SolSEO\Content;
use SolSEO\Content\Readers;
use SolSEO\Context;
use SolSEO\Meta;
use SolSEO\Options;
use SolSEO\Variables;

defined( 'ABSPATH' ) || exit;

/**
 * Normalises a post, saved or not, into one array.
 */
class Paper {

	/**
	 * Build from a saved post.
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @return array
	 */
	public static function from_post( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return self::build( array() );
		}

		$settings = Options::post_type( $post->post_type );
		$context  = Context::for_post( $post->ID );

		return self::build(
			array(
				'post_id'     => $post->ID,
				'post_type'   => $post->post_type,
				'title'       => Meta::get( $post->ID, 'title' ) ? Variables::apply( Meta::get( $post->ID, 'title' ), $context ) : Variables::apply( $settings['title'], $context ),
				'description' => Meta::get( $post->ID, 'description' ) ? Variables::apply( Meta::get( $post->ID, 'description' ), $context ) : '',
				'keyword'     => Meta::get( $post->ID, 'focus_keyword' ),
				'keywords'    => Meta::get( $post->ID, 'keywords' ),
				'slug'        => $post->post_name,
				'permalink'   => get_permalink( $post ),
				'content'     => Content::rendered( $post ),
				'noindex'     => Meta::get( $post->ID, 'robots_noindex' ),
				'thumbnail'   => (int) get_post_thumbnail_id( $post->ID ),
				'excerpt'     => $post->post_excerpt,
			)
		);
	}

	/**
	 * Build from values that are still open in the editor.
	 *
	 * @param array $values Raw values from the editor.
	 * @return array
	 */
	public static function from_editor( array $values ) {
		$post_id = isset( $values['post_id'] ) ? (int) $values['post_id'] : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( $post && empty( $values['content'] ) ) {
			$values['content'] = Content::rendered( $post );
		}

		if ( $post ) {
			$values['post_type'] = $post->post_type;
			$values['permalink'] = get_permalink( $post );
			$values['thumbnail'] = (int) get_post_thumbnail_id( $post_id );

			if ( '' === trim( (string) ( isset( $values['title'] ) ? $values['title'] : '' ) ) ) {
				// An empty SEO title field is not an empty title: the post type
				// template fills it in. Scoring the blank would mark down a page
				// that comes out fine.
				$settings        = Options::post_type( $post->post_type );
				$values['title'] = Variables::apply( $settings['title'], Context::for_post( $post_id ) );
			}

			if ( '' === trim( (string) ( isset( $values['slug'] ) ? $values['slug'] : '' ) ) ) {
				$values['slug'] = $post->post_name;
			}
		}

		if ( ! empty( $values['content'] ) && false === strpos( $values['content'], '<p' ) ) {
			$values['content'] = wpautop( $values['content'] );
		}

		return self::build( $values );
	}

	/**
	 * Fill in everything the checks need from the raw values.
	 *
	 * @param array $values Raw values.
	 * @return array
	 */
	protected static function build( array $values ) {
		$paper = array_merge(
			array(
				'post_id'     => 0,
				'post_type'   => 'post',
				'title'       => '',
				'description' => '',
				'keyword'     => '',
				'keywords'    => array(),
				'slug'        => '',
				'permalink'   => '',
				'content'     => '',
				'noindex'     => false,
				'thumbnail'   => 0,
				'excerpt'     => '',
			),
			$values
		);

		$paper['keyword']    = trim( (string) $paper['keyword'] );
		$paper['source']     = Readers::report( $paper['post_id'] );
		$paper['text']       = Content::plain( $paper['content'] );
		$paper['words']      = Text::words( $paper['text'] );
		$paper['sentences']  = Text::sentences( $paper['text'] );
		$paper['paragraphs'] = Text::paragraphs( $paper['content'] );
		$paper['headings']   = Content::headings( $paper['content'] );
		$paper['links']      = Content::links( $paper['content'] );
		$paper['images']     = Content::images( $paper['content'] );
		$paper['opening']    = Content::first_paragraph( $paper['content'] );
		$paper['is_product'] = 'product' === $paper['post_type'] && solseo_has_woocommerce();

		if ( $paper['thumbnail'] ) {
			array_unshift(
				$paper['images'],
				array(
					'src' => (string) wp_get_attachment_image_url( $paper['thumbnail'], 'full' ),
					'alt' => (string) get_post_meta( $paper['thumbnail'], '_wp_attachment_image_alt', true ),
				)
			);
		}

		return $paper;
	}
}
