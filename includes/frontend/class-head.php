<?php
/**
 * Everything the plugin prints inside the head.
 *
 * @package SolSEO
 */

namespace SolSEO\Frontend;

use SolSEO\Content;
use SolSEO\Context;
use SolSEO\Meta;
use SolSEO\Options;
use SolSEO\Variables;

defined( 'ABSPATH' ) || exit;

/**
 * Title, description, canonical, robots and the social tags.
 */
class Head {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 15 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'render' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_attachments' ) );

		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );

		if ( ! current_theme_supports( 'title-tag' ) ) {
			add_theme_support( 'title-tag' );
		}
	}

	/**
	 * The contents of the title tag.
	 *
	 * @param string $title Title WordPress worked out.
	 * @return string
	 */
	public static function document_title( $title ) {
		$ours = self::title();

		return '' !== $ours ? $ours : $title;
	}

	/**
	 * The title for the current view.
	 *
	 * @return string
	 */
	public static function title() {
		return self::title_in( Context::current() );
	}

	/**
	 * The title one post would print, asked outside the loop.
	 *
	 * The template, the variables and the fallbacks are the ones that run on
	 * the page itself, because an answer worked out a second way is an answer
	 * that will disagree with the page eventually.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function title_for( $post_id ) {
		return self::title_in( Context::for_post( (int) $post_id ) );
	}

	/**
	 * The title for a context.
	 *
	 * @param array $context View context.
	 * @return string
	 */
	protected static function title_in( array $context ) {
		$page_backed_archive = in_array( $context['type'], array( 'front_page', 'post_type_archive' ), true ) && $context['object_id'];

		if ( 'singular' === $context['type'] || 'blog_home' === $context['type'] || $page_backed_archive ) {
			$stored = Meta::get( $context['object_id'], 'title' );

			if ( $stored ) {
				return self::finish( Variables::apply( $stored, $context ), $context );
			}
		}

		if ( 'term' === $context['type'] ) {
			$stored = Meta::get_term( $context['object_id'], 'title' );

			if ( $stored ) {
				return self::finish( Variables::apply( $stored, $context ), $context );
			}
		}

		return self::finish( Variables::apply( self::template( $context, 'title' ), $context ), $context );
	}

	/**
	 * The meta description for the current view.
	 *
	 * @return string
	 */
	public static function description() {
		return self::description_in( Context::current() );
	}

	/**
	 * The description one post would print, asked outside the loop.
	 *
	 * CLIPPED THE WAY render() CLIPS IT, at 320 characters, because the point
	 * of asking is to compare what the page prints with what a search engine
	 * will show, and the unclipped string is neither.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function description_for( $post_id ) {
		return self::clip( self::description_in( Context::for_post( (int) $post_id ) ), 320 );
	}

	/**
	 * The description for a context.
	 *
	 * @param array $context View context.
	 * @return string
	 */
	protected static function description_in( array $context ) {
		if ( $context['object_id'] && 'term' !== $context['type'] ) {
			$stored = Meta::get( $context['object_id'], 'description' );

			if ( $stored ) {
				return Variables::apply( $stored, $context );
			}
		}

		if ( 'term' === $context['type'] ) {
			$stored = Meta::get_term( $context['object_id'], 'description' );

			if ( $stored ) {
				return Variables::apply( $stored, $context );
			}
		}

		$template = self::template( $context, 'description' );

		if ( '' === $template && 'singular' === $context['type'] ) {
			return Content::summary( $context['object_id'] );
		}

		return Variables::apply( $template, $context );
	}

	/**
	 * Print the tags.
	 */
	public static function render() {
		$context     = Context::current();
		$description = self::description();

		echo "\n<!-- SolSEO -->\n";

		if ( $description ) {
			printf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( self::clip( $description, 320 ) ) );
		}

		$canonical = self::canonical( $context );

		if ( $canonical ) {
			printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $canonical ) );
		}

		if ( Options::get( 'og_enabled' ) ) {
			self::open_graph( $context, $description, $canonical );
		}

		if ( Options::get( 'twitter_enabled' ) ) {
			self::twitter( $context, $description );
		}

		echo "<!-- /SolSEO -->\n\n";
	}

	/**
	 * Feed the robots meta tag.
	 *
	 * @param array $robots Directives WordPress collected.
	 * @return array
	 */
	public static function robots( $robots ) {
		$context = Context::current();

		if ( self::is_hidden( $context ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = isset( $robots['nofollow'] ) ? $robots['nofollow'] : false;

			unset( $robots['index'] );
		}

		if ( $context['object_id'] && 'term' !== $context['type'] && Meta::get( $context['object_id'], 'robots_nofollow' ) ) {
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		}

		$advanced = $context['object_id'] && 'term' !== $context['type'] ? Meta::get( $context['object_id'], 'robots_advanced' ) : array();

		foreach ( (array) $advanced as $directive ) {
			$robots[ $directive ] = true;
		}

		return $robots;
	}

	/**
	 * Send attachment pages to the file they hold.
	 *
	 * An attachment page is a page with one image and no content, and it
	 * competes with the post the image belongs to.
	 */
	public static function redirect_attachments() {
		if ( ! is_attachment() || ! Options::get( 'attachment_redirect' ) ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post ) {
			return;
		}

		$target = $post->post_parent ? get_permalink( $post->post_parent ) : wp_get_attachment_url( $post->ID );

		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	/**
	 * Whether this view should be kept out of the index.
	 *
	 * @param array $context View context.
	 * @return bool
	 */
	protected static function is_hidden( array $context ) {
		if ( 'search' === $context['type'] ) {
			return (bool) Options::get( 'search_noindex' );
		}

		if ( 'author' === $context['type'] ) {
			return ! Options::get( 'author_archives' );
		}

		if ( 'date' === $context['type'] ) {
			return ! Options::get( 'date_archives' );
		}

		if ( 'not_found' === $context['type'] ) {
			return true;
		}

		if ( Options::get( 'paged_noindex' ) && is_paged() ) {
			return true;
		}

		if ( 'front_page' === $context['type'] && Options::get( 'home_noindex' ) ) {
			return true;
		}

		if ( 'term' === $context['type'] ) {
			return '' !== self::noindex_source(
				Meta::get_term( $context['object_id'], 'robots_noindex' ),
				! empty( Options::taxonomy( $context['taxonomy'] )['noindex'] ),
				false
			);
		}

		if ( $context['object_id'] ) {
			return '' !== self::noindex_source(
				Meta::get( $context['object_id'], 'robots_noindex' ),
				! empty( Options::post_type( $context['post_type'] )['noindex'] ),
				false
			);
		}

		return false;
	}

	/**
	 * Why one post is being kept out of the index, or an empty string.
	 *
	 * The three answers are three different screens, which is the whole reason
	 * for naming the source rather than answering yes or no: one is a tick box
	 * on the page, one is a setting for the whole post type, and one is a
	 * WordPress setting that has nothing to do with this plugin.
	 *
	 * @param int $post_id Post ID.
	 * @return string One of post_meta, post_type, site, or an empty string.
	 */
	public static function hidden_reason( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		$site = ! get_option( 'blog_public' )
			|| ( (int) get_option( 'page_on_front' ) === (int) $post->ID && Options::get( 'home_noindex' ) );

		return self::noindex_source(
			Meta::get( $post->ID, 'robots_noindex' ),
			! empty( Options::post_type( $post->post_type )['noindex'] ),
			$site
		);
	}

	/**
	 * The same question about a term archive.
	 *
	 * A term carries no separate setting from its taxonomy as far as the reader
	 * is concerned, so both answer taxonomy: the screen that fixes either is
	 * the same screen.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return string One of taxonomy, site, or an empty string.
	 */
	public static function term_hidden_reason( $term_id, $taxonomy ) {
		$source = self::noindex_source(
			Meta::get_term( $term_id, 'robots_noindex' ),
			! empty( Options::taxonomy( $taxonomy )['noindex'] ),
			! get_option( 'blog_public' )
		);

		return 'post_meta' === $source || 'post_type' === $source ? 'taxonomy' : $source;
	}

	/**
	 * Which of the three settings is doing the hiding.
	 *
	 * Pure, and the order is the order the tags are worked out in: the page's
	 * own setting wins, then the type's, then the site's.
	 *
	 * @param mixed $post_meta The object's own setting.
	 * @param mixed $post_type The setting for its type.
	 * @param mixed $site      The site wide setting.
	 * @return string
	 */
	public static function noindex_source( $post_meta, $post_type, $site ) {
		if ( $post_meta ) {
			return 'post_meta';
		}

		if ( $post_type ) {
			return 'post_type';
		}

		return $site ? 'site' : '';
	}

	/**
	 * The address this view should be indexed under.
	 *
	 * @param array $context View context.
	 * @return string
	 */
	protected static function canonical( array $context ) {
		if ( $context['object_id'] && 'term' !== $context['type'] ) {
			$stored = Meta::get( $context['object_id'], 'canonical' );

			if ( $stored ) {
				return $stored;
			}
		}

		if ( 'term' === $context['type'] ) {
			$stored = Meta::get_term( $context['object_id'], 'canonical' );

			return $stored ? $stored : get_term_link( (int) $context['object_id'], $context['taxonomy'] );
		}

		if ( 'singular' === $context['type'] ) {
			$link = get_permalink( $context['object_id'] );
			$page = (int) get_query_var( 'page' );

			return $page > 1 ? trailingslashit( $link ) . $page . '/' : $link;
		}

		if ( 'front_page' === $context['type'] ) {
			return home_url( '/' );
		}

		if ( 'post_type_archive' === $context['type'] ) {
			return get_post_type_archive_link( $context['post_type'] );
		}

		if ( 'author' === $context['type'] ) {
			return get_author_posts_url( (int) $context['object_id'] );
		}

		return '';
	}

	/**
	 * Print the Open Graph tags.
	 *
	 * @param array  $context     View context.
	 * @param string $description Meta description.
	 * @param string $canonical   Canonical address.
	 */
	protected static function open_graph( array $context, $description, $canonical ) {
		$tags = array(
			'og:locale'    => get_locale(),
			'og:site_name' => get_bloginfo( 'name' ),
			'og:type'      => 'singular' === $context['type'] ? 'article' : 'website',
			'og:title'     => self::social_field( $context, 'og_title', self::title() ),
			'og:url'       => $canonical,
		);

		$social_description = self::social_field( $context, 'og_description', $description );

		if ( $social_description ) {
			$tags['og:description'] = self::clip( $social_description, 300 );
		}

		foreach ( array_filter( $tags ) as $property => $content ) {
			printf( "<meta property=\"%s\" content=\"%s\" />\n", esc_attr( $property ), esc_attr( $content ) );
		}

		if ( 'singular' === $context['type'] && 'post' === get_post_type( $context['object_id'] ) ) {
			printf( "<meta property=\"article:published_time\" content=\"%s\" />\n", esc_attr( get_the_date( DATE_W3C, $context['object_id'] ) ) );
			printf( "<meta property=\"article:modified_time\" content=\"%s\" />\n", esc_attr( get_the_modified_date( DATE_W3C, $context['object_id'] ) ) );
		}

		$image = self::image( $context, 'og_image' );

		if ( $image ) {
			printf( "<meta property=\"og:image\" content=\"%s\" />\n", esc_url( $image['url'] ) );

			if ( $image['width'] ) {
				printf( "<meta property=\"og:image:width\" content=\"%d\" />\n", (int) $image['width'] );
				printf( "<meta property=\"og:image:height\" content=\"%d\" />\n", (int) $image['height'] );
			}

			if ( $image['alt'] ) {
				printf( "<meta property=\"og:image:alt\" content=\"%s\" />\n", esc_attr( $image['alt'] ) );
			}
		}
	}

	/**
	 * Print the Twitter card tags.
	 *
	 * @param array  $context     View context.
	 * @param string $description Meta description.
	 */
	protected static function twitter( array $context, $description ) {
		$tags = array(
			'twitter:card'  => Options::get( 'twitter_card' ),
			'twitter:title' => self::social_field( $context, 'twitter_title', self::title() ),
			'twitter:site'  => Options::get( 'twitter_site' ),
		);

		$card_description = self::social_field( $context, 'twitter_description', $description );

		if ( $card_description ) {
			$tags['twitter:description'] = self::clip( $card_description, 200 );
		}

		$image = self::image( $context, 'twitter_image' );

		if ( $image ) {
			$tags['twitter:image'] = $image['url'];
		}

		foreach ( array_filter( $tags ) as $name => $content ) {
			printf( "<meta name=\"%s\" content=\"%s\" />\n", esc_attr( $name ), esc_attr( $content ) );
		}
	}

	/**
	 * A social field from the post, falling back to the page value.
	 *
	 * @param array  $context  View context.
	 * @param string $field    Meta field name.
	 * @param string $fallback Value used when the field is empty.
	 * @return string
	 */
	protected static function social_field( array $context, $field, $fallback ) {
		if ( ! $context['object_id'] || 'term' === $context['type'] ) {
			return $fallback;
		}

		$stored = Meta::get( $context['object_id'], $field );

		return $stored ? Variables::apply( $stored, $context ) : $fallback;
	}

	/**
	 * The image a social card should use.
	 *
	 * @param array  $context View context.
	 * @param string $field   Meta field holding an override.
	 * @return array|null Keys: url, width, height, alt.
	 */
	protected static function image( array $context, $field ) {
		$attachment = 0;

		if ( $context['object_id'] && 'term' !== $context['type'] ) {
			$attachment = (int) Meta::get( $context['object_id'], $field );

			if ( ! $attachment ) {
				$attachment = (int) get_post_thumbnail_id( $context['object_id'] );
			}
		}

		if ( ! $attachment ) {
			$attachment = (int) Options::get( 'default_image' );
		}

		if ( ! $attachment ) {
			return null;
		}

		$source = wp_get_attachment_image_src( $attachment, 'full' );

		if ( ! $source ) {
			return null;
		}

		return array(
			'url'    => $source[0],
			'width'  => $source[1],
			'height' => $source[2],
			'alt'    => get_post_meta( $attachment, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * The template a view falls back to.
	 *
	 * @param array  $context View context.
	 * @param string $field   Either title or description.
	 * @return string
	 */
	protected static function template( array $context, $field ) {
		switch ( $context['type'] ) {
			case 'front_page':
				return 'title' === $field ? Options::get( 'home_title' ) : Options::get( 'home_description' );

			case 'term':
				$settings = Options::taxonomy( $context['taxonomy'] );
				return $settings[ $field ];

			case 'author':
				return 'title' === $field ? '{author} {sep} {sitename}' : '';

			case 'search':
				return 'title' === $field ? '{searchphrase} {sep} {sitename}' : '';

			case 'not_found':
				return 'title' === $field ? __( 'Page not found', 'solseo' ) . ' {sep} {sitename}' : '';

			case 'date':
				return 'title' === $field ? '{date} {sep} {sitename}' : '';

			case 'post_type_archive':
				return 'title' === $field ? '{title} {sep} {sitename}' : '';
		}

		$settings = Options::post_type( $context['post_type'] ? $context['post_type'] : 'post' );

		return $settings[ $field ];
	}

	/**
	 * Add the page number to a title on a paged archive.
	 *
	 * @param string $title   Title so far.
	 * @param array  $context View context.
	 * @return string
	 */
	protected static function finish( $title, array $context ) {
		if ( ! is_paged() || 'singular' === $context['type'] ) {
			return $title;
		}

		$page = Variables::apply( '{page}', $context );

		return $page ? $title . ' ' . Variables::separator() . ' ' . $page : $title;
	}

	/**
	 * Cut a string to a length without breaking a word.
	 *
	 * @param string $text   Text.
	 * @param int    $length Character limit.
	 * @return string
	 */
	protected static function clip( $text, $length ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$cut  = mb_substr( $text, 0, $length );
		$stop = mb_strrpos( $cut, ' ' );

		return rtrim( false === $stop ? $cut : mb_substr( $cut, 0, $stop ), ' ,.;:-' );
	}
}
