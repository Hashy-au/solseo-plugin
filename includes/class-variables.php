<?php
/**
 * Title and description templates.
 *
 * Templates hold placeholders in braces, for example
 * "{title} {sep} {sitename}". A placeholder that has nothing to show on the
 * current view is dropped, and the separators left stranded around it go with
 * it.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves template placeholders against a view.
 */
class Variables {

	/**
	 * The separator characters offered in the settings screen.
	 *
	 * @var array
	 */
	protected static $separators = array(
		'-'      => '-',
		'ndash'  => '&ndash;',
		'middot' => '&middot;',
		'bull'   => '&bull;',
		'|'      => '|',
		'/'      => '/',
		'~'      => '~',
		'raquo'  => '&raquo;',
		'gt'     => '&gt;',
	);

	/**
	 * Replace every placeholder in a template.
	 *
	 * @param string $template Template string.
	 * @param array  $context  View context from Context::current().
	 * @return string
	 */
	public static function apply( $template, array $context = array() ) {
		if ( '' === trim( (string) $template ) ) {
			return '';
		}

		$template = preg_replace_callback(
			'/\{([a-z_]+)\}/',
			function ( $found ) use ( $context ) {
				$value = self::resolve( $found[1], $context );

				return null === $value ? $found[0] : $value;
			},
			$template
		);

		return self::tidy( $template );
	}

	/**
	 * The placeholders offered in the settings screen, with a description.
	 *
	 * @return array
	 */
	public static function catalogue() {
		$list = array(
			'title'            => __( 'Title of the post, page or product', 'solseo' ),
			'sitename'         => __( 'Site title', 'solseo' ),
			'sitedesc'         => __( 'Site tagline', 'solseo' ),
			'sep'              => __( 'The separator chosen below', 'solseo' ),
			'excerpt'          => __( 'Excerpt, or the first sentences of the content', 'solseo' ),
			'category'         => __( 'Categories, comma separated', 'solseo' ),
			'primary_category' => __( 'First category only', 'solseo' ),
			'tag'              => __( 'Tags, comma separated', 'solseo' ),
			'term'             => __( 'Name of the current term', 'solseo' ),
			'term_description' => __( 'Description of the current term', 'solseo' ),
			'author'           => __( 'Author display name', 'solseo' ),
			'date'             => __( 'Publish date', 'solseo' ),
			'modified'         => __( 'Last modified date', 'solseo' ),
			'page'             => __( '"Page 2 of 6" on paged archives', 'solseo' ),
			'searchphrase'     => __( 'What the visitor searched for', 'solseo' ),
			'currentyear'      => __( 'The current year', 'solseo' ),
			'currentmonth'     => __( 'The current month', 'solseo' ),
		);

		if ( solseo_has_woocommerce() ) {
			$list['price'] = __( 'Product price', 'solseo' );
			$list['sku']   = __( 'Product SKU', 'solseo' );
			$list['stock'] = __( 'In stock or out of stock', 'solseo' );
		}

		return $list;
	}

	/**
	 * Separator options for the settings screen.
	 *
	 * @return array
	 */
	public static function separators() {
		return self::$separators;
	}

	/**
	 * The separator the site has chosen, as a character.
	 *
	 * @return string
	 */
	public static function separator() {
		$choice = Options::get( 'separator' );
		$list   = self::separators();

		return isset( $list[ $choice ] ) ? html_entity_decode( $list[ $choice ], ENT_QUOTES, 'UTF-8' ) : $choice;
	}

	/**
	 * Work out what one placeholder stands for.
	 *
	 * @param string $name    Placeholder name.
	 * @param array  $context View context.
	 * @return string|null Null when the name is not one of ours.
	 */
	protected static function resolve( $name, array $context ) {
		$post_id = isset( $context['object_id'] ) ? (int) $context['object_id'] : 0;

		switch ( $name ) {
			case 'sep':
				return self::separator();

			case 'sitename':
				return wp_strip_all_tags( get_bloginfo( 'name' ) );

			case 'sitedesc':
				return wp_strip_all_tags( get_bloginfo( 'description' ) );

			case 'currentyear':
				return gmdate( 'Y' );

			case 'currentmonth':
				return wp_date( 'F' );

			case 'searchphrase':
				return get_search_query();

			case 'title':
				return self::title( $context );

			case 'excerpt':
				return $post_id ? Content::summary( $post_id ) : '';

			case 'category':
				return self::term_list( $post_id, 'category' );

			case 'primary_category':
				$terms = self::terms( $post_id, 'category' );
				return $terms ? $terms[0]->name : '';

			case 'tag':
				return self::term_list( $post_id, 'post_tag' );

			case 'term':
				return isset( $context['term'] ) ? $context['term']->name : '';

			case 'term_description':
				return isset( $context['term'] ) ? wp_strip_all_tags( term_description( $context['term'] ) ) : '';

			case 'author':
				return self::author( $context );

			case 'date':
				return $post_id ? get_the_date( '', $post_id ) : '';

			case 'modified':
				return $post_id ? get_the_modified_date( '', $post_id ) : '';

			case 'page':
				return self::page_number();

			case 'price':
			case 'sku':
			case 'stock':
				return self::product_field( $post_id, $name );
		}

		// An unknown name is left in place, so a typo shows up rather than
		// quietly deleting part of the template.
		return null;
	}

	/**
	 * The plain title of whatever is being viewed.
	 *
	 * @param array $context View context.
	 * @return string
	 */
	protected static function title( array $context ) {
		if ( ! empty( $context['term'] ) ) {
			return $context['term']->name;
		}

		if ( ! empty( $context['object_id'] ) ) {
			return wp_strip_all_tags( get_the_title( $context['object_id'] ) );
		}

		if ( 'post_type_archive' === $context['type'] ) {
			return post_type_archive_title( '', false );
		}

		return wp_strip_all_tags( get_bloginfo( 'name' ) );
	}

	/**
	 * Display name of the author being viewed, or of the post's author.
	 *
	 * @param array $context View context.
	 * @return string
	 */
	protected static function author( array $context ) {
		if ( 'author' === $context['type'] ) {
			return get_the_author_meta( 'display_name', (int) $context['object_id'] );
		}

		if ( empty( $context['object_id'] ) ) {
			return '';
		}

		$post = get_post( $context['object_id'] );

		return $post ? get_the_author_meta( 'display_name', $post->post_author ) : '';
	}

	/**
	 * Terms of one taxonomy attached to a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	protected static function terms( $post_id, $taxonomy ) {
		if ( ! $post_id ) {
			return array();
		}

		$terms = get_the_terms( $post_id, $taxonomy );

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Term names joined with commas.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 */
	protected static function term_list( $post_id, $taxonomy ) {
		$names = wp_list_pluck( self::terms( $post_id, $taxonomy ), 'name' );

		return implode( ', ', $names );
	}

	/**
	 * "Page 2 of 6", or an empty string on the first page.
	 *
	 * @return string
	 */
	protected static function page_number() {
		global $wp_query;

		$current = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$total   = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;

		if ( $current < 2 ) {
			return '';
		}

		/* translators: 1: current page number, 2: total number of pages. */
		return sprintf( __( 'Page %1$d of %2$d', 'solseo' ), $current, max( $total, $current ) );
	}

	/**
	 * A WooCommerce field, when WooCommerce is running and the post is a product.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   One of price, sku, stock.
	 * @return string
	 */
	protected static function product_field( $post_id, $field ) {
		if ( ! $post_id || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return '';
		}

		if ( 'sku' === $field ) {
			return (string) $product->get_sku();
		}

		if ( 'stock' === $field ) {
			return $product->is_in_stock() ? __( 'In stock', 'solseo' ) : __( 'Out of stock', 'solseo' );
		}

		return wp_strip_all_tags( wc_price( $product->get_price() ) );
	}

	/**
	 * Remove the gaps left by placeholders that resolved to nothing.
	 *
	 * @param string $text Replaced template.
	 * @return string
	 */
	protected static function tidy( $text ) {
		$sep  = preg_quote( self::separator(), '/' );
		$text = preg_replace( '/\s{2,}/', ' ', $text );
		$text = preg_replace( '/^(?:\s*' . $sep . '\s*)+/', '', $text );
		$text = preg_replace( '/(?:\s*' . $sep . '\s*)+$/', '', $text );
		$text = preg_replace( '/(\s*' . $sep . '\s*){2,}/', ' ' . self::separator() . ' ', $text );

		return trim( $text );
	}
}
