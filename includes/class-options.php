<?php
/**
 * Settings storage.
 *
 * Everything lives in one autoloaded option so a page load costs one row.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the solseo_settings option.
 */
class Options {

	const KEY = 'solseo_settings';

	/**
	 * Cached copy for this request.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Every setting and its default.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'separator'               => '-',
			'home_title'              => '{sitename} {sep} {sitedesc}',
			'home_description'        => '',
			'home_noindex'            => false,

			'author_archives'         => false,
			'date_archives'           => false,
			'search_noindex'          => true,
			'paged_noindex'           => false,
			'attachment_redirect'     => true,

			'og_enabled'              => true,
			'twitter_enabled'         => true,
			'twitter_card'            => 'summary_large_image',
			'twitter_site'            => '',
			'default_image'           => 0,

			'schema_enabled'          => true,
			'entity_type'             => 'organisation',
			'entity_name'             => '',
			'entity_logo'             => 0,
			'entity_profiles'         => array(),
			'schema_search_action'    => true,

			'sitemap_enabled'         => true,
			'sitemap_per_page'        => 500,
			'sitemap_images'          => true,
			'sitemap_authors'         => false,

			'breadcrumbs_enabled'     => true,
			'breadcrumbs_home'        => '',
			'breadcrumbs_prefix'      => '',
			'breadcrumbs_sep'         => '/',

			'writing_variant'         => 'au',
			'writing_banned'          => array(),
			'writing_sentence'        => 20,
			'writing_reading_ease'    => 60,

			'remove_data'             => false,

			'crawl_per_minute'        => 30,

			'log_not_found'           => true,
			'redirect_on_slug_change' => true,
			'llms_txt_enabled'        => true,
			'indexnow_enabled'        => false,
			'log_retention_days'      => 30,

			'post_types'              => array(),
			'taxonomies'              => array(),
		);
	}

	/**
	 * The whole settings array.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::KEY, array() );
			self::$cache = is_array( $stored ) ? array_merge( self::defaults(), $stored ) : self::defaults();
		}

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key      Setting name.
	 * @param mixed  $fallback Returned when the setting is unknown.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Merge changes into the stored option.
	 *
	 * @param array $changes Settings to write.
	 */
	public static function update( array $changes ) {
		$all = array_merge( self::all(), $changes );

		update_option( self::KEY, $all );
		self::$cache = $all;
	}

	/**
	 * Settings for one post type, falling back to sensible defaults.
	 *
	 * @param string $post_type Post type name.
	 * @return array
	 */
	public static function post_type( $post_type ) {
		$all    = self::get( 'post_types', array() );
		$stored = isset( $all[ $post_type ] ) ? $all[ $post_type ] : array();

		return array_merge(
			array(
				'title'       => '{title} {sep} {sitename}',
				'description' => '{excerpt}',
				'noindex'     => false,
				'schema'      => self::default_schema_type( $post_type ),
				'social'      => true,
			),
			$stored
		);
	}

	/**
	 * Settings for one taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	public static function taxonomy( $taxonomy ) {
		$all    = self::get( 'taxonomies', array() );
		$stored = isset( $all[ $taxonomy ] ) ? $all[ $taxonomy ] : array();

		return array_merge(
			array(
				'title'       => '{term} {sep} {sitename}',
				'description' => '{term_description}',
				'noindex'     => in_array( $taxonomy, array( 'post_tag', 'product_tag' ), true ),
			),
			$stored
		);
	}

	/**
	 * The structured data type a post type gets before anyone changes it.
	 *
	 * @param string $post_type Post type name.
	 * @return string
	 */
	protected static function default_schema_type( $post_type ) {
		if ( 'product' === $post_type ) {
			return 'Product';
		}

		return 'page' === $post_type ? 'WebPage' : 'Article';
	}

	/**
	 * Forget the request cache. Used by tests and after a bulk write.
	 */
	public static function flush() {
		self::$cache = null;
	}
}
