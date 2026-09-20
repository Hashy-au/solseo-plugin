<?php
/**
 * Builds the sitemap XML.
 *
 * @package SolSEO
 */

namespace SolSEO\Sitemaps;

use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Queries the content and renders it as XML.
 */
class Writer {

	/**
	 * The sitemap index.
	 *
	 * @param array $entries Each entry has loc and lastmod.
	 * @return string
	 */
	public static function index( array $entries ) {
		$xml = self::open() . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<sitemap>\n\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";

			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . esc_html( $entry['lastmod'] ) . "</lastmod>\n";
			}

			$xml .= "\t</sitemap>\n";
		}

		return $xml . "</sitemapindex>\n";
	}

	/**
	 * One sitemap.
	 *
	 * @param string $section Section name.
	 * @param int    $page    Page number, from one.
	 * @return string Empty when the section is unknown or empty.
	 */
	public static function page( $section, $page ) {
		if ( 'author' === $section ) {
			$entries = self::author_entries( $page );
		} elseif ( 0 === strpos( $section, 'tax-' ) ) {
			$entries = self::term_entries( substr( $section, 4 ), $page );
		} else {
			$entries = self::post_entries( $section, $page );
		}

		if ( ! $entries ) {
			return '';
		}

		return self::urlset( $entries );
	}

	/**
	 * The posts of one type that belong in the sitemap, in its own order.
	 *
	 * The crawler picks its pages partly from the sitemap, and it asks here
	 * rather than writing the same query again, so a page the sitemap leaves
	 * out is a page the crawl leaves out. Two queries that mean to agree about
	 * what belongs on a site eventually do not.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $limit     How many at most.
	 * @return array Post IDs.
	 */
	public static function post_ids( $post_type, $limit ) {
		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}

		$args                           = self::post_query( $post_type, 1, (int) $limit );
		$args['fields']                 = 'ids';
		$args['no_found_rows']          = true;
		$args['update_post_meta_cache'] = false;

		$query = new \WP_Query( $args );

		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * How many posts of a type belong in the sitemap.
	 *
	 * @param string $post_type Post type name.
	 * @return int
	 */
	public static function count_posts( $post_type ) {
		$query = new \WP_Query( self::post_query( $post_type, 1, 1 ) );

		return (int) $query->found_posts;
	}

	/**
	 * How many terms of a taxonomy belong in the sitemap.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return int
	 */
	public static function count_terms( $taxonomy ) {
		$count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);

		return is_wp_error( $count ) ? 0 : (int) $count;
	}

	/**
	 * How many authors have published something.
	 *
	 * @return int
	 */
	public static function count_authors() {
		return count( self::authors( 1, 10000 ) );
	}

	/**
	 * When a section last changed.
	 *
	 * @param string $section Section name.
	 * @return string
	 */
	public static function last_modified( $section ) {
		if ( 'author' === $section || 0 === strpos( $section, 'tax-' ) ) {
			return '';
		}

		$query = new \WP_Query(
			array_merge(
				self::post_query( $section, 1, 1 ),
				array(
					'orderby' => 'modified',
					'order'   => 'DESC',
				)
			)
		);

		if ( ! $query->posts ) {
			return '';
		}

		return get_post_modified_time( DATE_W3C, true, $query->posts[0] );
	}

	/**
	 * The XSL stylesheet that makes a sitemap readable in a browser.
	 *
	 * @return string
	 */
	public static function stylesheet() {
		$title = esc_html__( 'XML sitemap', 'solseo' );
		$url   = esc_html__( 'Address', 'solseo' );
		$date  = esc_html__( 'Last changed', 'solseo' );

		return '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
<xsl:output method="html" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html><head><title>' . $title . '</title>
<style>body{font:15px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;margin:2rem;color:#23262E}
h1{font-size:1.3rem;margin:0 0 1rem}table{border-collapse:collapse;width:100%}
th,td{text-align:left;padding:.5rem .75rem;border-bottom:1px solid #EAE1D2;font-size:.9rem}
th{color:#6B6659;font-weight:600}a{color:#0C5B9C}</style></head>
<body><h1>' . $title . '</h1>
<table><tr><th>' . $url . '</th><th>' . $date . '</th></tr>
<xsl:for-each select="s:sitemapindex/s:sitemap"><tr>
<td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td><td><xsl:value-of select="s:lastmod"/></td>
</tr></xsl:for-each>
<xsl:for-each select="s:urlset/s:url"><tr>
<td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td><td><xsl:value-of select="s:lastmod"/></td>
</tr></xsl:for-each>
</table></body></html>
</xsl:template>
</xsl:stylesheet>
';
	}

	/**
	 * Render a set of addresses.
	 *
	 * @param array $entries Each entry has loc, lastmod and images.
	 * @return string
	 */
	protected static function urlset( array $entries ) {
		$with_images = Options::get( 'sitemap_images' );

		$xml = self::open() . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';

		if ( $with_images ) {
			$xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
		}

		$xml .= ">\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<url>\n\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";

			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . esc_html( $entry['lastmod'] ) . "</lastmod>\n";
			}

			if ( $with_images && ! empty( $entry['images'] ) ) {
				foreach ( $entry['images'] as $image ) {
					$xml .= "\t\t<image:image><image:loc>" . esc_url( $image ) . "</image:loc></image:image>\n";
				}
			}

			$xml .= "\t</url>\n";
		}

		return $xml . "</urlset>\n";
	}

	/**
	 * The XML declaration and the stylesheet link.
	 *
	 * @return string
	 */
	protected static function open() {
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<?xml-stylesheet type="text/xsl" href="' . esc_url( home_url( '/sitemap.xsl' ) ) . '"?>' . "\n";
	}

	/**
	 * Query arguments for one page of a post type.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $page      Page number.
	 * @param int    $per_page  Entries per page.
	 * @return array
	 */
	protected static function post_query( $post_type, $page, $per_page ) {
		return array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'has_password'           => false,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed key, and the sitemap is cached.
				array(
					'key'     => '_solseo_robots_noindex',
					'compare' => 'NOT EXISTS',
				),
			),
		);
	}

	/**
	 * One page of posts.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $page      Page number.
	 * @return array
	 */
	protected static function post_entries( $post_type, $page ) {
		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}

		$query   = new \WP_Query( self::post_query( $post_type, $page, Controller::per_page() ) );
		$entries = array();

		foreach ( $query->posts as $post ) {
			$entries[] = array(
				'loc'     => get_permalink( $post ),
				'lastmod' => get_post_modified_time( DATE_W3C, true, $post ),
				'images'  => Options::get( 'sitemap_images' ) ? self::images( $post ) : array(),
			);
		}

		return $entries;
	}

	/**
	 * One page of terms.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $page     Page number.
	 * @return array
	 */
	protected static function term_entries( $taxonomy, $page ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$per_page = Controller::per_page();

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => $per_page,
				'offset'     => ( $page - 1 ) * $per_page,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$entries = array();

		foreach ( $terms as $term ) {
			if ( get_term_meta( $term->term_id, '_solseo_robots_noindex', true ) ) {
				continue;
			}

			$link = get_term_link( $term );

			if ( ! is_wp_error( $link ) ) {
				$entries[] = array(
					'loc'     => $link,
					'lastmod' => '',
				);
			}
		}

		return $entries;
	}

	/**
	 * One page of author archives.
	 *
	 * @param int $page Page number.
	 * @return array
	 */
	protected static function author_entries( $page ) {
		$per_page = Controller::per_page();
		$entries  = array();

		foreach ( self::authors( $page, $per_page ) as $author ) {
			$entries[] = array(
				'loc'     => get_author_posts_url( $author->ID ),
				'lastmod' => '',
			);
		}

		return $entries;
	}

	/**
	 * Authors with at least one published post.
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Authors per page.
	 * @return array
	 */
	protected static function authors( $page, $per_page ) {
		return get_users(
			array(
				'has_published_posts' => array( 'post' ),
				'number'              => $per_page,
				'offset'              => ( $page - 1 ) * $per_page,
				'orderby'             => 'ID',
				'fields'              => array( 'ID' ),
			)
		);
	}

	/**
	 * Image addresses worth listing for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	protected static function images( $post ) {
		$ids = array();

		$thumbnail = get_post_thumbnail_id( $post );

		if ( $thumbnail ) {
			$ids[] = $thumbnail;
		}

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post );

			if ( $product ) {
				$ids = array_merge( $ids, $product->get_gallery_image_ids() );
			}
		}

		$urls = array();

		foreach ( array_slice( array_unique( array_filter( $ids ) ), 0, 10 ) as $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );

			if ( $url ) {
				$urls[] = $url;
			}
		}

		return $urls;
	}
}
