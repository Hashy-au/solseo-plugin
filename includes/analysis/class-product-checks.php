<?php
/**
 * Checks that only apply to WooCommerce products.
 *
 * These are the fields the product structured data is built from, so a
 * product that passes them is a product a shopping result can use.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Product data completeness.
 */
class Product_Checks extends Checks {

	/**
	 * Group name.
	 *
	 * @return string
	 */
	public static function group() {
		return 'product';
	}

	/**
	 * Run the group.
	 *
	 * @param array $paper Paper.
	 * @return array
	 */
	public static function run( array $paper ) {
		if ( empty( $paper['is_product'] ) || ! $paper['post_id'] || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( $paper['post_id'] );

		if ( ! $product ) {
			return array();
		}

		return array(
			self::short_description( $product ),
			self::gallery( $product ),
			self::sku( $product ),
			self::price( $product ),
			self::categories( $product ),
			self::attributes( $product ),
		);
	}

	/**
	 * The short description shown next to the price.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	protected static function short_description( $product ) {
		$words = count( Text::words( \SolSEO\Content::plain( $product->get_short_description() ) ) );

		if ( $words >= 20 ) {
			return self::result( 'product_short_description', 3, self::GOOD, __( 'The short description gives a buyer something to read.', 'solseo' ) );
		}

		if ( $words > 0 ) {
			return self::result( 'product_short_description', 3, self::FAIR, __( 'The short description is very brief. A couple of sentences sell better.', 'solseo' ) );
		}

		return self::result( 'product_short_description', 3, self::POOR, __( 'Write a short description. It is the first thing a buyer reads.', 'solseo' ) );
	}

	/**
	 * Main image plus gallery.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	protected static function gallery( $product ) {
		$count = count( $product->get_gallery_image_ids() ) + ( $product->get_image_id() ? 1 : 0 );

		if ( $count >= 3 ) {
			return self::result( 'product_images', 2, self::GOOD, __( 'The product has several photographs.', 'solseo' ) );
		}

		if ( $count > 0 ) {
			return self::result( 'product_images', 2, self::FAIR, __( 'Add more photographs. Buyers look before they read.', 'solseo' ) );
		}

		return self::result( 'product_images', 2, self::POOR, __( 'This product has no image.', 'solseo' ) );
	}

	/**
	 * Product code.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	protected static function sku( $product ) {
		if ( $product->get_sku() ) {
			return self::result( 'product_sku', 2, self::GOOD, __( 'The product has an SKU.', 'solseo' ) );
		}

		return self::result( 'product_sku', 2, self::POOR, __( 'Set an SKU. Shopping results use it to tell one listing from another.', 'solseo' ) );
	}

	/**
	 * Price.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	protected static function price( $product ) {
		if ( '' !== $product->get_price() && null !== $product->get_price() ) {
			return self::result( 'product_price', 3, self::GOOD, __( 'The product has a price.', 'solseo' ) );
		}

		return self::result( 'product_price', 3, self::POOR, __( 'A product without a price cannot appear in a shopping result.', 'solseo' ) );
	}

	/**
	 * Category placement.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	protected static function categories( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		$names = is_array( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array();
		$names = array_diff( $names, array( 'uncategorised', 'uncategorized' ) );

		if ( $names ) {
			return self::result( 'product_category', 2, self::GOOD, __( 'The product sits in a category.', 'solseo' ) );
		}

		return self::result( 'product_category', 2, self::POOR, __( 'Put the product in a category so it can be found by browsing.', 'solseo' ) );
	}

	/**
	 * Attributes, which become the product details in structured data.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	protected static function attributes( $product ) {
		if ( $product->get_attributes() ) {
			return self::result( 'product_attributes', 1, self::GOOD, __( 'The product has attributes.', 'solseo' ) );
		}

		return self::result( 'product_attributes', 1, self::FAIR, __( 'Attributes such as size, colour or material give a listing more to match on.', 'solseo' ) );
	}
}
