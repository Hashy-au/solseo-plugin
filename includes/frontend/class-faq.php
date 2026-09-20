<?php
/**
 * Questions and answers, and what a search engine is told about them.
 *
 * @package SolSEO
 */

namespace SolSEO\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the block back out of a post and turns it into a FAQPage node.
 *
 * ONE READER, TWO CALLERS. The front end renders from these pairs and the
 * structured data is built from these pairs, so the page and the markup cannot
 * come to disagree about what the questions are.
 */
class Faq {

	const BLOCK = 'solseo/faq';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_filter( 'solseo_schema_graph', array( __CLASS__, 'add_node' ), 10, 2 );
	}

	/**
	 * Clean a block's stored items into usable pairs.
	 *
	 * A pair needs both halves. A question with no answer is somebody halfway
	 * through writing, not something to publish or to mark up, and the same
	 * question twice is dropped to the first one, because two answers to one
	 * question is invalid markup and a confusing page.
	 *
	 * @param array $items Raw items from the block.
	 * @return array List of question and answer.
	 */
	public static function pairs( array $items ) {
		$pairs = array();
		$seen  = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$question = isset( $item['question'] ) ? trim( wp_strip_all_tags( (string) $item['question'] ) ) : '';
			$answer   = isset( $item['answer'] ) ? trim( (string) $item['answer'] ) : '';

			if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
				continue;
			}

			$key = strtolower( $question );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;

			$pairs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		return $pairs;
	}

	/**
	 * Every pair on a post, across however many of the blocks it has.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function from_post( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || ! has_blocks( $post->post_content ) ) {
			return array();
		}

		$items = array();

		foreach ( self::collect( parse_blocks( $post->post_content ) ) as $block ) {
			if ( isset( $block['attrs']['items'] ) && is_array( $block['attrs']['items'] ) ) {
				$items = array_merge( $items, $block['attrs']['items'] );
			}
		}

		return self::pairs( $items );
	}

	/**
	 * Add the FAQPage node when the page has questions on it.
	 *
	 * @param array $graph   Nodes.
	 * @param array $context View context.
	 * @return array
	 */
	public static function add_node( $graph, $context ) {
		if ( ! is_array( $graph ) || ! isset( $context['type'] ) || 'singular' !== $context['type'] ) {
			return $graph;
		}

		$pairs = self::from_post( $context['object_id'] );

		if ( ! $pairs ) {
			return $graph;
		}

		$questions = array();

		foreach ( $pairs as $index => $pair ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $pair['question'],
				'position'       => $index + 1,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $pair['answer'] ),
				),
			);
		}

		$graph[] = array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $context['object_id'] ) . '#faq',
			'mainEntity' => $questions,
		);

		return $graph;
	}

	/**
	 * Find our blocks, however deeply they are nested in other ones.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array
	 */
	protected static function collect( array $blocks ) {
		$found = array();

		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && self::BLOCK === $block['blockName'] ) {
				$found[] = $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, self::collect( $block['innerBlocks'] ) );
			}
		}

		return $found;
	}
}
