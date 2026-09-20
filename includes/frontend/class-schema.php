<?php
/**
 * Structured data.
 *
 * One JSON-LD graph per page. Every node carries an @id so the pieces can
 * point at each other rather than repeat themselves.
 *
 * @package SolSEO
 */

namespace SolSEO\Frontend;

use SolSEO\Admin\User_Fields;
use SolSEO\Breadcrumbs;
use SolSEO\Content;
use SolSEO\Context;
use SolSEO\Meta;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and prints the JSON-LD graph.
 */
class Schema {

	/**
	 * Hook in.
	 */
	public static function init() {
		if ( ! Options::get( 'schema_enabled' ) ) {
			return;
		}

		add_action( 'wp_head', array( __CLASS__, 'render' ), 10 );
	}

	/**
	 * Print the graph.
	 */
	public static function render() {
		$graph = self::graph();

		if ( ! $graph ) {
			return;
		}

		$document = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}

	/**
	 * The nodes for the current view.
	 *
	 * @return array
	 */
	public static function graph() {
		$context = Context::current();
		$graph   = array(
			self::publisher(),
			self::website(),
		);

		$page = self::web_page( $context );

		if ( $page ) {
			$graph[] = $page;
		}

		$trail = self::breadcrumbs();

		if ( $trail ) {
			$graph[] = $trail;
		}

		if ( 'singular' === $context['type'] ) {
			$main = self::main_entity( $context );

			if ( $main ) {
				$graph[] = $main;

				/*
				 * The author stands as its own node rather than inside the
				 * article, so the two can say more than a name: what this
				 * person does, what they are an authority on and where else
				 * they are. The article points at it by id.
				 */
				if ( isset( $main['author']['@id'] ) ) {
					$person = self::person( (int) get_post_field( 'post_author', $context['object_id'] ) );

					if ( $person ) {
						$graph[] = $person;
					}
				}
			}
		}

		/**
		 * Filter the structured data nodes before they are printed.
		 *
		 * @param array $graph   Nodes.
		 * @param array $context View context.
		 */
		return apply_filters( 'solseo_schema_graph', array_filter( $graph ), $context );
	}

	/**
	 * The organisation or person behind the site.
	 *
	 * @return array
	 */
	protected static function publisher() {
		$is_person = 'person' === Options::get( 'entity_type' );
		$name      = Options::get( 'entity_name' );

		$node = array(
			'@type' => $is_person ? 'Person' : 'Organization',
			'@id'   => home_url( '/#publisher' ),
			'name'  => $name ? $name : get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);

		$profiles = array_filter( (array) Options::get( 'entity_profiles' ) );

		if ( $profiles ) {
			$node['sameAs'] = array_values( $profiles );
		}

		$logo = (int) Options::get( 'entity_logo' );

		if ( $logo ) {
			$source = wp_get_attachment_image_src( $logo, 'full' );

			if ( $source ) {
				$node['logo'] = array(
					'@type'  => 'ImageObject',
					'@id'    => home_url( '/#logo' ),
					'url'    => $source[0],
					'width'  => $source[1],
					'height' => $source[2],
				);

				$node['image'] = array( '@id' => home_url( '/#logo' ) );
			}
		}

		return $node;
	}

	/**
	 * The site itself.
	 *
	 * @return array
	 */
	protected static function website() {
		$node = array(
			'@type'     => 'WebSite',
			'@id'       => home_url( '/#website' ),
			'url'       => home_url( '/' ),
			'name'      => get_bloginfo( 'name' ),
			'publisher' => array( '@id' => home_url( '/#publisher' ) ),
		);

		$description = get_bloginfo( 'description' );

		if ( $description ) {
			$node['description'] = $description;
		}

		if ( Options::get( 'schema_search_action' ) ) {
			$node['potentialAction'] = array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			);
		}

		return $node;
	}

	/**
	 * The page node.
	 *
	 * @param array $context View context.
	 * @return array|null
	 */
	protected static function web_page( array $context ) {
		$url = self::current_url();

		if ( ! $url ) {
			return null;
		}

		$node = array(
			'@type'    => 'WebPage',
			'@id'      => $url . '#webpage',
			'url'      => $url,
			'name'     => Head::title(),
			'isPartOf' => array( '@id' => home_url( '/#website' ) ),
		);

		$description = Head::description();

		if ( $description ) {
			$node['description'] = $description;
		}

		if ( 'singular' === $context['type'] ) {
			$node['datePublished'] = get_the_date( DATE_W3C, $context['object_id'] );
			$node['dateModified']  = get_the_modified_date( DATE_W3C, $context['object_id'] );
		}

		if ( Breadcrumbs::enabled() ) {
			$node['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
		}

		return $node;
	}

	/**
	 * The breadcrumb trail as structured data.
	 *
	 * @return array|null
	 */
	protected static function breadcrumbs() {
		if ( ! Breadcrumbs::enabled() ) {
			return null;
		}

		$trail = Breadcrumbs::trail();

		if ( count( $trail ) < 2 ) {
			return null;
		}

		$items = array();
		$last  = count( $trail ) - 1;

		foreach ( array_values( $trail ) as $index => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb['label'],
			);

			// The last entry is the page being viewed, and it names itself
			// rather than pointing anywhere.
			if ( $index !== $last && ! empty( $crumb['url'] ) ) {
				$item['item'] = $crumb['url'];
			}

			$items[] = $item;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => self::current_url() . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * The thing the page is about.
	 *
	 * @param array $context View context.
	 * @return array|null
	 */
	protected static function main_entity( array $context ) {
		$type = Meta::get( $context['object_id'], 'schema_type' );

		if ( ! $type ) {
			$settings = Options::post_type( $context['post_type'] );
			$type     = $settings['schema'];
		}

		if ( 'none' === $type || 'WebPage' === $type ) {
			return null;
		}

		if ( 'Product' === $type && function_exists( 'wc_get_product' ) ) {
			return self::product( $context['object_id'] );
		}

		if ( in_array( $type, array( 'Service', 'Course' ), true ) ) {
			return self::offered( $context['object_id'], $type );
		}

		return self::article( $context['object_id'], $type );
	}

	/**
	 * An article or one of its subtypes.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Schema type.
	 * @return array
	 */
	protected static function article( $post_id, $type ) {
		$post = get_post( $post_id );
		$url  = self::current_url();

		$node = array(
			'@type'            => $type,
			'@id'              => $url . '#article',
			'headline'         => wp_strip_all_tags( get_the_title( $post_id ) ),
			'datePublished'    => get_the_date( DATE_W3C, $post_id ),
			'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			'publisher'        => array( '@id' => home_url( '/#publisher' ) ),
			'author'           => array( '@id' => self::person_id( $post ? (int) $post->post_author : 0 ) ),
		);

		$description = Head::description();

		if ( $description ) {
			$node['description'] = $description;
		}

		$image = get_post_thumbnail_id( $post_id );

		if ( $image ) {
			$source = wp_get_attachment_image_src( $image, 'full' );

			if ( $source ) {
				$node['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $source[0],
					'width'  => $source[1],
					'height' => $source[2],
				);
			}
		}

		$words = str_word_count( Content::plain( Content::rendered( $post ) ) );

		if ( $words ) {
			$node['wordCount'] = $words;
		}

		return $node;
	}

	/**
	 * Something the business offers: a service, or a course.
	 *
	 * Both are built from what a page already has, which is why these two and
	 * not Event or Recipe. An event needs a start date and a place, and a
	 * recipe needs ingredients and steps; a page holds neither, so those two
	 * wait for the release that gives them somewhere to be typed. Emitting
	 * them from a title and a description would be inventing the facts.
	 *
	 * A page with no description emits nothing at all, because a service with
	 * a name and nothing else describes nothing.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Service or Course.
	 * @return array|null
	 */
	protected static function offered( $post_id, $type ) {
		$name        = wp_strip_all_tags( get_the_title( $post_id ) );
		$description = Head::description();

		if ( '' === trim( $name ) || '' === trim( (string) $description ) ) {
			return null;
		}

		$url = self::current_url();

		$node = array(
			'@type'       => $type,
			'@id'         => $url . '#' . strtolower( $type ),
			'name'        => $name,
			'description' => $description,
			'url'         => $url,
			'provider'    => array( '@id' => home_url( '/#publisher' ) ),
		);

		$image = get_post_thumbnail_id( $post_id );

		if ( $image ) {
			$source = wp_get_attachment_image_src( $image, 'full' );

			if ( $source ) {
				$node['image'] = $source[0];
			}
		}

		return $node;
	}

	/**
	 * The person who wrote it.
	 *
	 * Name and address are always there, because an article with an author
	 * needs to say who. Everything else is added only when somebody filled it
	 * in: an empty job title is left out rather than published as an empty
	 * string, which is what "no half built node" means in practice.
	 *
	 * @param int $user_id Author ID.
	 * @return array|null
	 */
	public static function person( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! $user_id ) {
			return null;
		}

		$name = get_the_author_meta( 'display_name', $user_id );

		if ( ! $name ) {
			return null;
		}

		$node = array(
			'@type' => 'Person',
			'@id'   => self::person_id( $user_id ),
			'name'  => $name,
			'url'   => get_author_posts_url( $user_id ),
		);

		$bio = trim( (string) get_the_author_meta( 'description', $user_id ) );

		if ( $bio ) {
			$node['description'] = $bio;
		}

		$title = trim( (string) get_user_meta( $user_id, User_Fields::TITLE, true ) );

		if ( $title ) {
			$node['jobTitle'] = $title;
		}

		$credentials = trim( (string) get_user_meta( $user_id, User_Fields::CREDENTIALS, true ) );

		if ( $credentials ) {
			$node['honorificSuffix'] = $credentials;
		}

		$knows = self::split_list( (string) get_user_meta( $user_id, User_Fields::KNOWS, true ), ',' );

		if ( $knows ) {
			$node['knowsAbout'] = $knows;
		}

		$profiles = self::split_list( (string) get_user_meta( $user_id, User_Fields::PROFILES, true ), "\n" );

		if ( $profiles ) {
			$node['sameAs'] = $profiles;
		}

		return $node;
	}

	/**
	 * The address a person node is known by.
	 *
	 * @param int $user_id Author ID.
	 * @return string
	 */
	protected static function person_id( $user_id ) {
		return get_author_posts_url( (int) $user_id ) . '#person';
	}

	/**
	 * Split a stored list into its parts, with the empties dropped.
	 *
	 * @param string $stored    What was saved.
	 * @param string $separator What separates one from the next.
	 * @return array
	 */
	protected static function split_list( $stored, $separator ) {
		$parts = array_map( 'trim', explode( $separator, $stored ) );

		return array_values( array_filter( $parts, 'strlen' ) );
	}

	/**
	 * A WooCommerce product, with one offer per variation.
	 *
	 * @param int $post_id Product ID.
	 * @return array|null
	 */
	protected static function product( $post_id ) {
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return null;
		}

		$url  = self::current_url();
		$node = array(
			'@type'       => 'Product',
			'@id'         => $url . '#product',
			'name'        => wp_strip_all_tags( $product->get_name() ),
			'url'         => $url,
			'description' => Content::plain( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
		);

		if ( $product->get_sku() ) {
			$node['sku'] = $product->get_sku();
		}

		$images = self::product_images( $product );

		if ( $images ) {
			$node['image'] = $images;
		}

		$brand = self::product_brand( $product );

		if ( $brand ) {
			$node['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		$node['offers'] = $product->is_type( 'variable' )
			? self::variable_offers( $product, $url )
			: self::single_offer( $product, $url );

		if ( $product->get_rating_count() ) {
			$node['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (int) $product->get_review_count(),
			);
		}

		return $node;
	}

	/**
	 * One offer for a product with no variations.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $url     Product address.
	 * @return array
	 */
	protected static function single_offer( $product, $url ) {
		return array(
			'@type'         => 'Offer',
			'url'           => $url,
			'price'         => wc_format_decimal( $product->get_price(), wc_get_price_decimals() ),
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => self::availability( $product ),
			'itemCondition' => 'https://schema.org/NewCondition',
		);
	}

	/**
	 * An aggregate offer holding one offer per variation.
	 *
	 * A shopper searching for one size should reach the variation that has it,
	 * so every variation is listed with its own price and stock state.
	 *
	 * @param \WC_Product_Variable $product Product.
	 * @param string               $url     Product address.
	 * @return array
	 */
	protected static function variable_offers( $product, $url ) {
		$offers   = array();
		$prices   = array();
		$currency = get_woocommerce_currency();

		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( ! $variation || ! $variation->exists() || '' === $variation->get_price() ) {
				continue;
			}

			$price    = wc_format_decimal( $variation->get_price(), wc_get_price_decimals() );
			$prices[] = (float) $price;

			$offer = array(
				'@type'         => 'Offer',
				'url'           => $variation->get_permalink(),
				'price'         => $price,
				'priceCurrency' => $currency,
				'availability'  => self::availability( $variation ),
				'itemCondition' => 'https://schema.org/NewCondition',
				'name'          => wp_strip_all_tags( implode( ', ', $variation->get_variation_attributes() ) ),
			);

			if ( $variation->get_sku() ) {
				$offer['sku'] = $variation->get_sku();
			}

			$offers[] = $offer;
		}

		if ( ! $offers ) {
			return self::single_offer( $product, $url );
		}

		return array(
			'@type'         => 'AggregateOffer',
			'priceCurrency' => $currency,
			'lowPrice'      => (string) min( $prices ),
			'highPrice'     => (string) max( $prices ),
			'offerCount'    => count( $offers ),
			'offers'        => $offers,
		);
	}

	/**
	 * Stock state as a schema.org value.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return string
	 */
	protected static function availability( $product ) {
		if ( $product->is_on_backorder() ) {
			return 'https://schema.org/BackOrder';
		}

		return $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
	}

	/**
	 * Product images, main first.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	protected static function product_images( $product ) {
		$ids = array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) );
		$out = array();

		foreach ( array_slice( $ids, 0, 6 ) as $id ) {
			$source = wp_get_attachment_image_src( $id, 'full' );

			if ( $source ) {
				$out[] = $source[0];
			}
		}

		return $out;
	}

	/**
	 * A brand name from a brand taxonomy or a brand attribute.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	protected static function product_brand( $product ) {
		foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_the_terms( $product->get_id(), $taxonomy );

			if ( is_array( $terms ) && $terms ) {
				return $terms[0]->name;
			}
		}

		$attribute = $product->get_attribute( 'pa_brand' );

		return $attribute ? $attribute : '';
	}

	/**
	 * The address of the page being rendered.
	 *
	 * @return string
	 */
	protected static function current_url() {
		$context = Context::current();

		if ( 'singular' === $context['type'] ) {
			return (string) get_permalink( $context['object_id'] );
		}

		if ( 'term' === $context['type'] ) {
			$link = get_term_link( (int) $context['object_id'], $context['taxonomy'] );

			return is_wp_error( $link ) ? '' : $link;
		}

		if ( 'post_type_archive' === $context['type'] ) {
			return (string) get_post_type_archive_link( $context['post_type'] );
		}

		return home_url( '/' );
	}
}
