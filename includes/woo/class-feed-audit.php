<?php
/**
 * What Merchant Centre will refuse, worked out here instead of in three days.
 *
 * A product feed is submitted, and between one and three days later some of it
 * comes back disapproved, usually with a reason written for somebody who builds
 * feeds for a living. Every one of the twelve things below can be known from the
 * product itself before any of that happens.
 *
 * THIS WRITES NOTHING AND IT FETCHES NOTHING. It does not edit the feed, the
 * feed plugin's settings, or the product. A report that quietly repairs things
 * is a report nobody can run twice, and the feed belongs to whichever plugin
 * generates it, which is why this names that plugin on the screen rather than
 * reaching into it. A guard in tests/test-free-halves.php fails the build on a
 * write of any kind in this file.
 *
 * It does not read the generated feed either. The feed is a rendering of these
 * products, so the products are where the answer is, and asking this site for
 * its own feed file would be a request, a wait and a parse to find out something
 * the database already knows.
 *
 * @package SolSEO
 */

namespace SolSEO\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * The twelve readiness checks, and the reading behind them.
 */
class Feed_Audit {

	/** How many products one reading covers. */
	const MAX_PRODUCTS = 250;

	/** The longest title Merchant Centre accepts. */
	const TITLE_MAX = 150;

	/** The longest description it accepts. */
	const DESCRIPTION_MAX = 5000;

	/**
	 * Where the rules come from.
	 *
	 * One address, on purpose. Google's help centre numbers its articles, and a
	 * set of numbers written from memory is a set of links to the wrong page,
	 * which is worse than one link to the right one. This is the page every rule
	 * below is on. D-81.4 asks every row to cite the operator's own
	 * documentation and this is the operator's own documentation.
	 */
	const SPECIFICATION = 'https://support.google.com/merchants/answer/7052112';

	/**
	 * The plugins on this site whose job is a product feed.
	 *
	 * Named from each plugin's own header. A table of plugin names written by
	 * hand is out of date the week it is written and names nothing when somebody
	 * uses the one we had not heard of, which is D-82.2 pointed at a different
	 * kind of neighbour. A plugin's header is always what the plugin is called.
	 *
	 * @return array Each with file and name.
	 */
	public static function owners() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			return array();
		}

		$active = (array) get_option( 'active_plugins', array() );
		$found  = array();

		foreach ( get_plugins() as $file => $header ) {
			if ( ! in_array( $file, $active, true ) ) {
				continue;
			}

			$name = isset( $header['Name'] ) ? (string) $header['Name'] : '';

			if ( '' === $name || ! self::is_feed_plugin( $name, isset( $header['Description'] ) ? (string) $header['Description'] : '' ) ) {
				continue;
			}

			$found[] = array(
				'file' => (string) $file,
				'name' => $name,
			);
		}

		return $found;
	}

	/**
	 * Whether a plugin describes itself as something that makes a feed.
	 *
	 * Pure.
	 *
	 * @param string $name  The plugin's name.
	 * @param string $about The plugin's description.
	 * @return bool
	 */
	public static function is_feed_plugin( $name, $about ) {
		$words = $name . ' ' . $about;

		if ( ! preg_match( '/\bfeed\b|merchant cent(?:re|er)|google shopping|google listings|product xml/i', $words ) ) {
			return false;
		}

		// An RSS reader is a feed plugin and is not this kind of feed plugin.
		return 1 === preg_match( '/product|shop|woo|merchant|shopping|listing|catalog/i', $words );
	}

	/**
	 * Everything wrong with one product, in the order it is worth fixing.
	 *
	 * Pure. Takes the shape read() builds, so the twelve rules can be driven by
	 * a test without a WooCommerce anywhere near it.
	 *
	 * @param array $product A product, as read() shapes one.
	 * @return array Each with rule, says and fix.
	 */
	public static function inspect( array $product ) {
		$found = array();
		$title = trim( (string) self::field( $product, 'title' ) );
		$about = trim( (string) self::field( $product, 'description' ) );

		if ( '' === $title ) {
			$found[] = self::finding(
				'title_missing',
				__( 'This product has no title, and a feed row with no title is refused.', 'solseo' ),
				__( 'Give the product a name.', 'solseo' )
			);
		} elseif ( strlen( $title ) > self::TITLE_MAX ) {
			$found[] = self::finding(
				'title_long',
				sprintf(
					/* translators: 1: how long a title is. 2: how long it may be. */
					__( 'The title is %1$s characters and the limit is %2$s, so it is cut off where nobody chose.', 'solseo' ),
					number_format_i18n( strlen( $title ) ),
					number_format_i18n( self::TITLE_MAX )
				),
				__( 'Put what somebody actually searches for at the front and shorten the rest.', 'solseo' )
			);
		}

		if ( '' !== $title && self::is_shouting( $title ) ) {
			$found[] = self::finding(
				'title_promotional',
				__( 'The title carries promotional wording or is partly in capitals, which is one of the most common reasons a product is disapproved.', 'solseo' ),
				__( 'Take the sale wording and the capitals out of the title. Put the offer in the price fields, where Google reads it.', 'solseo' )
			);
		}

		if ( '' === $about ) {
			$found[] = self::finding(
				'description_missing',
				__( 'There is no description, so the row is refused and there is nothing for Google to match a search against.', 'solseo' ),
				__( 'Write a few sentences about what it is. The opening line does most of the work.', 'solseo' )
			);
		} elseif ( strlen( $about ) > self::DESCRIPTION_MAX ) {
			$found[] = self::finding(
				'description_long',
				sprintf(
					/* translators: 1: how long a description is. 2: how long it may be. */
					__( 'The description is %1$s characters and the limit is %2$s.', 'solseo' ),
					number_format_i18n( strlen( $about ) ),
					number_format_i18n( self::DESCRIPTION_MAX )
				),
				__( 'Shorten it, or move the long half into a tab on the page.', 'solseo' )
			);
		}

		if ( '' === trim( (string) self::field( $product, 'image' ) ) ) {
			$found[] = self::finding(
				'image_missing',
				__( 'There is no product image, and a row with no image link is refused outright.', 'solseo' ),
				__( 'Set a featured image on the product.', 'solseo' )
			);
		}

		if ( (float) self::field( $product, 'price', 0 ) <= 0 ) {
			$found[] = self::finding(
				'price_missing',
				__( 'There is no price, or the price is zero, which a feed cannot carry.', 'solseo' ),
				__( 'Set a price. A product genuinely priced on enquiry does not belong in a shopping feed.', 'solseo' )
			);
		}

		if ( ! self::field( $product, 'purchasable', true ) ) {
			$found[] = self::finding(
				'availability',
				__( 'This is published and cannot be bought, so the feed says in stock and the page says otherwise. Google checks the two against each other.', 'solseo' ),
				__( 'Mark it out of stock, allow backorders, or take it out of the feed.', 'solseo' )
			);
		}

		if ( ! self::identified( $product ) ) {
			$found[] = self::finding(
				'identifier_missing',
				__( 'Nothing identifies this product to Google: no GTIN, and no brand with a part number beside it. Rows with no identifier are shown less often and are refused in some categories.', 'solseo' ),
				__( 'Add the barcode number as the GTIN. Where a product genuinely has none, set a brand and a part number instead.', 'solseo' )
			);
		}

		if ( ! self::field( $product, 'categories', array() ) ) {
			$found[] = self::finding(
				'category_missing',
				__( 'This product is in no category, so the feed has no product type and Google has to guess what it is.', 'solseo' ),
				__( 'Put it in a category. The category path is what fills the product type.', 'solseo' )
			);
		}

		if ( 'variable' === self::field( $product, 'type', 'simple' ) && ! self::field( $product, 'variations_priced', true ) ) {
			$found[] = self::finding(
				'variations_priced',
				__( 'Some of the variations of this product have no price, so those rows drop out of the feed and the sizes somebody searched for are missing.', 'solseo' ),
				__( 'Give every variation a price, or turn off the ones that are not for sale.', 'solseo' )
			);
		}

		if ( ! self::field( $product, 'weight', '' ) && ! self::field( $product, 'dimensions', false ) ) {
			$found[] = self::finding(
				'shipping_missing',
				__( 'This product has no weight and no dimensions, so a calculated postage cost cannot be worked out and the feed falls back to a flat rate that is wrong for something.', 'solseo' ),
				__( 'Set a weight. Dimensions as well, for anything that is large for its weight.', 'solseo' )
			);
		}

		return $found;
	}

	/**
	 * Every rule this knows, so the screen can say what was looked for.
	 *
	 * @return array Rule key to label.
	 */
	public static function rules() {
		return array(
			'title_missing'       => __( 'No title', 'solseo' ),
			'title_long'          => __( 'Title too long', 'solseo' ),
			'title_promotional'   => __( 'Sale wording or capitals in the title', 'solseo' ),
			'description_missing' => __( 'No description', 'solseo' ),
			'description_long'    => __( 'Description too long', 'solseo' ),
			'image_missing'       => __( 'No image', 'solseo' ),
			'price_missing'       => __( 'No price', 'solseo' ),
			'availability'        => __( 'Cannot be bought', 'solseo' ),
			'identifier_missing'  => __( 'Nothing identifies it', 'solseo' ),
			'category_missing'    => __( 'In no category', 'solseo' ),
			'variations_priced'   => __( 'A variation with no price', 'solseo' ),
			'shipping_missing'    => __( 'No weight and no size', 'solseo' ),
		);
	}

	/**
	 * Whether a title is shouting or selling.
	 *
	 * Pure. A run of four or more capitals is shouting; a word from the list is
	 * selling. Both are in the specification as reasons to refuse a row, and
	 * both are things a shop owner does on purpose without knowing.
	 *
	 * @param string $title A product title.
	 * @return bool
	 */
	public static function is_shouting( $title ) {
		$title = (string) $title;

		if ( preg_match( '/\b[A-Z]{4,}\b/', $title ) ) {
			return true;
		}

		if ( substr_count( $title, '!' ) > 0 ) {
			return true;
		}

		return 1 === preg_match( '/\b(?:free shipping|free postage|best price|sale|on sale|discount|clearance|bargain|cheapest|buy now|limited time|special offer|\d+% off)\b/i', $title );
	}

	/**
	 * Whether anything identifies a product to Google.
	 *
	 * Pure.
	 *
	 * @param array $product A product.
	 * @return bool
	 */
	public static function identified( array $product ) {
		if ( '' !== trim( (string) self::field( $product, 'gtin' ) ) ) {
			return true;
		}

		return '' !== trim( (string) self::field( $product, 'brand' ) ) && '' !== trim( (string) self::field( $product, 'mpn' ) );
	}

	/**
	 * Read the shop and say what will come back refused.
	 *
	 * @param int $limit How many products.
	 * @return array|\WP_Error
	 */
	public static function audit( $limit = self::MAX_PRODUCTS ) {
		if ( ! solseo_has_woocommerce() || ! function_exists( 'wc_get_products' ) ) {
			return new \WP_Error( 'solseo_no_shop', __( 'This site has no WooCommerce on it, so there is no product feed to look at.', 'solseo' ) );
		}

		$products = wc_get_products(
			array(
				'status'  => array( 'publish' ),
				'limit'   => (int) $limit,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'objects',
			)
		);

		$rows   = array();
		$totals = array();
		$read   = 0;

		foreach ( (array) $products as $product ) {
			$shape = self::read( $product );

			++$read;

			foreach ( self::inspect( $shape ) as $finding ) {
				$rule = (string) $finding['rule'];

				$totals[ $rule ] = isset( $totals[ $rule ] ) ? $totals[ $rule ] + 1 : 1;

				$rows[] = array_merge(
					$finding,
					array(
						'id'    => (int) $shape['id'],
						'title' => (string) $shape['title'],
					)
				);
			}
		}

		return array(
			'read'    => $read,
			'total'   => self::how_many(),
			'rows'    => $rows,
			'totals'  => $totals,
			'owners'  => self::owners(),
			'read_at' => time(),
		);
	}

	/**
	 * How many published products there are altogether.
	 *
	 * @return int
	 */
	public static function how_many() {
		$counts = wp_count_posts( 'product' );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * One WooCommerce product as the checks above read one.
	 *
	 * Every read here is defended, because half of these are methods
	 * WooCommerce added in the last three years and this plugin supports what
	 * people are running rather than what is current.
	 *
	 * @param object $product A WC_Product.
	 * @return array
	 */
	public static function read( $product ) {
		$shape = array(
			'id'                => method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0,
			'title'             => method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '',
			'description'       => '',
			'image'             => '',
			'price'             => method_exists( $product, 'get_price' ) ? (float) $product->get_price() : 0,
			'purchasable'       => true,
			'gtin'              => '',
			'brand'             => '',
			'mpn'               => '',
			'categories'        => array(),
			'weight'            => method_exists( $product, 'get_weight' ) ? (string) $product->get_weight() : '',
			'dimensions'        => false,
			'type'              => method_exists( $product, 'get_type' ) ? (string) $product->get_type() : 'simple',
			'variations_priced' => true,
			'status'            => method_exists( $product, 'get_status' ) ? (string) $product->get_status() : 'publish',
		);

		if ( method_exists( $product, 'get_description' ) ) {
			$shape['description'] = trim( (string) $product->get_description() );
		}

		if ( '' === $shape['description'] && method_exists( $product, 'get_short_description' ) ) {
			$shape['description'] = trim( (string) $product->get_short_description() );
		}

		if ( method_exists( $product, 'get_image_id' ) && (int) $product->get_image_id() ) {
			$shape['image'] = (string) wp_get_attachment_url( (int) $product->get_image_id() );
		}

		if ( method_exists( $product, 'is_purchasable' ) ) {
			$shape['purchasable'] = (bool) $product->is_purchasable() && ( ! method_exists( $product, 'is_in_stock' ) || $product->is_in_stock() );
		}

		if ( method_exists( $product, 'get_category_ids' ) ) {
			$shape['categories'] = array_map( 'intval', (array) $product->get_category_ids() );
		}

		if ( method_exists( $product, 'has_dimensions' ) ) {
			$shape['dimensions'] = (bool) $product->has_dimensions();
		}

		$shape['gtin']  = self::identifier( $shape['id'], $product, array( '_wpm_gtin_code', '_gtin', '_ean', '_barcode' ), 'get_global_unique_id' );
		$shape['mpn']   = self::identifier( $shape['id'], $product, array( '_mpn', '_wpm_mpn_code' ), '' );
		$shape['brand'] = self::brand( $shape['id'] );

		if ( 'variable' === $shape['type'] && method_exists( $product, 'get_children' ) ) {
			$shape['variations_priced'] = self::variations_priced( (array) $product->get_children() );
		}

		return $shape;
	}

	/**
	 * A value out of a product, whatever it was stored as.
	 *
	 * Pure enough: it reads post meta, which is the only place a plugin that is
	 * not WooCommerce can have put a barcode.
	 *
	 * @param int    $post_id Product ID.
	 * @param object $product The product.
	 * @param array  $keys    Meta keys to try.
	 * @param string $method  A method on the product to try first.
	 * @return string
	 */
	protected static function identifier( $post_id, $product, array $keys, $method ) {
		if ( '' !== $method && method_exists( $product, $method ) ) {
			$value = trim( (string) $product->{$method}() );

			if ( '' !== $value ) {
				return $value;
			}
		}

		foreach ( $keys as $key ) {
			$value = trim( (string) get_post_meta( (int) $post_id, $key, true ) );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * A product's brand, from wherever this site keeps one.
	 *
	 * @param int $post_id Product ID.
	 * @return string
	 */
	protected static function brand( $post_id ) {
		foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'pa_brand' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_the_terms( (int) $post_id, $taxonomy );

			if ( is_array( $terms ) && $terms ) {
				return (string) $terms[0]->name;
			}
		}

		return '';
	}

	/**
	 * Whether every variation of a product has a price.
	 *
	 * @param array $children Variation IDs.
	 * @return bool
	 */
	protected static function variations_priced( array $children ) {
		foreach ( $children as $child ) {
			$variation = wc_get_product( (int) $child );

			if ( ! $variation || ! method_exists( $variation, 'get_price' ) ) {
				continue;
			}

			if ( '' === (string) $variation->get_price() || (float) $variation->get_price() <= 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One value out of a product shape.
	 *
	 * Pure.
	 *
	 * @param array  $product  The shape.
	 * @param string $name     Key.
	 * @param mixed  $fallback What it is when it is not there.
	 * @return mixed
	 */
	protected static function field( array $product, $name, $fallback = '' ) {
		return array_key_exists( $name, $product ) ? $product[ $name ] : $fallback;
	}

	/**
	 * One finding.
	 *
	 * Pure.
	 *
	 * @param string $rule Rule key.
	 * @param string $says What is wrong.
	 * @param string $fix  What to do about it.
	 * @return array
	 */
	protected static function finding( $rule, $says, $fix ) {
		$rules = self::rules();

		return array(
			'rule'   => $rule,
			'label'  => isset( $rules[ $rule ] ) ? $rules[ $rule ] : $rule,
			'says'   => $says,
			'fix'    => $fix,
			'source' => self::SPECIFICATION,
		);
	}
}
