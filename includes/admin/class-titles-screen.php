<?php
/**
 * Title and description templates, and the social defaults.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Options;
use SolSEO\Variables;

defined( 'ABSPATH' ) || exit;

/**
 * The Titles and Meta screen.
 */
class Titles_Screen extends Screen {

	const PAGE = 'solseo-titles';

	/**
	 * Save a submitted tab.
	 */
	public static function load() {
		$registered = Tabs::for_page( self::PAGE );
		Tabs::load( $registered, Tabs::current( $registered ) );

		if ( ! self::submitted( 'solseo_titles' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is sanitised below.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();
		$tab   = self::current_tab( 'general' );

		if ( ! is_array( $input ) ) {
			return;
		}

		$changes = 'post_types' === $tab ? self::sanitise_types( $input ) : array();

		if ( 'taxonomies' === $tab ) {
			$changes = self::sanitise_taxonomies( $input );
		}

		if ( 'general' === $tab || 'social' === $tab ) {
			$changes = self::sanitise_general( $input );
		}

		Options::update( $changes );

		self::remember( __( 'Saved.', 'solseo' ) );
		self::go_back( self::PAGE, array( 'tab' => $tab ) );
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		$tabs    = Tabs::for_page( self::PAGE );
		$current = Tabs::current( $tabs );

		self::tabs( self::PAGE, Tabs::labels( $tabs ), $current );

		/*
		 * The form wraps the tab rather than the other way round, because
		 * every tab on this screen saves through the same handler and the
		 * same nonce. A tab an add-on adds here is inside that form too, and
		 * gets the save button with nothing to wire up.
		 */
		echo '<form method="post" class="solseo-form">';
		self::nonce( 'solseo_titles' );

		Tabs::render( $tabs, $current );

		submit_button();

		echo '</form>';

		self::view( 'titles-variables', array( 'variables' => Variables::catalogue() ) );
	}

	/**
	 * The site wide titles and the home page.
	 */
	public static function tab_general() {
		self::tab_view( 'general' );
	}

	/**
	 * One template per post type.
	 */
	public static function tab_post_types() {
		self::tab_view( 'post-types' );
	}

	/**
	 * One template per taxonomy.
	 */
	public static function tab_taxonomies() {
		self::tab_view( 'taxonomies' );
	}

	/**
	 * What a share looks like.
	 */
	public static function tab_social() {
		self::tab_view( 'social' );
	}

	/**
	 * Draw one of this screen's views.
	 *
	 * @param string $name View name after the titles- prefix.
	 */
	protected static function tab_view( $name ) {
		self::view(
			'titles-' . $name,
			array(
				'options'   => Options::all(),
				'variables' => Variables::catalogue(),
			)
		);
	}

	/**
	 * Clean the general and social fields.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	protected static function sanitise_general( array $input ) {
		$text = array(
			'separator',
			'home_title',
			'home_description',
			'twitter_card',
			'twitter_site',
			'entity_type',
			'entity_name',
		);

		$flags = array(
			'home_noindex',
			'author_archives',
			'date_archives',
			'search_noindex',
			'paged_noindex',
			'attachment_redirect',
			'og_enabled',
			'twitter_enabled',
			'schema_enabled',
			'schema_search_action',
			'breadcrumbs_enabled',
		);

		$changes = array();

		foreach ( $text as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$changes[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		foreach ( $flags as $key ) {
			$changes[ $key ] = ! empty( $input[ $key ] );
		}

		foreach ( array( 'default_image', 'entity_logo' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$changes[ $key ] = (int) $input[ $key ];
			}
		}

		if ( isset( $input['entity_profiles'] ) ) {
			$lines                      = preg_split( '/\r\n|\r|\n/', (string) $input['entity_profiles'] );
			$changes['entity_profiles'] = array_values( array_filter( array_map( 'esc_url_raw', array_map( 'trim', $lines ) ) ) );
		}

		foreach ( array( 'breadcrumbs_home', 'breadcrumbs_prefix', 'breadcrumbs_sep' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$changes[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		return $changes;
	}

	/**
	 * Clean the per post type fields.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	protected static function sanitise_types( array $input ) {
		$stored = Options::get( 'post_types', array() );
		$posted = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? $input['post_types'] : array();

		foreach ( solseo_post_types() as $post_type ) {
			if ( ! isset( $posted[ $post_type ] ) ) {
				continue;
			}

			$row = $posted[ $post_type ];

			$stored[ $post_type ] = array(
				'title'       => sanitize_text_field( isset( $row['title'] ) ? $row['title'] : '' ),
				'description' => sanitize_text_field( isset( $row['description'] ) ? $row['description'] : '' ),
				'noindex'     => ! empty( $row['noindex'] ),
				'schema'      => sanitize_text_field( isset( $row['schema'] ) ? $row['schema'] : 'Article' ),
				'social'      => true,
			);
		}

		return array( 'post_types' => $stored );
	}

	/**
	 * Clean the per taxonomy fields.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	protected static function sanitise_taxonomies( array $input ) {
		$stored = Options::get( 'taxonomies', array() );
		$posted = isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ? $input['taxonomies'] : array();

		foreach ( solseo_taxonomies() as $taxonomy ) {
			if ( ! isset( $posted[ $taxonomy ] ) ) {
				continue;
			}

			$row = $posted[ $taxonomy ];

			$stored[ $taxonomy ] = array(
				'title'       => sanitize_text_field( isset( $row['title'] ) ? $row['title'] : '' ),
				'description' => sanitize_text_field( isset( $row['description'] ) ? $row['description'] : '' ),
				'noindex'     => ! empty( $row['noindex'] ),
			);
		}

		return array( 'taxonomies' => $stored );
	}

	/**
	 * The structured data types offered for a post type.
	 *
	 * @return array
	 */
	public static function schema_types() {
		$types = array(
			'Article'     => __( 'Article', 'solseo' ),
			'BlogPosting' => __( 'Blog post', 'solseo' ),
			'NewsArticle' => __( 'News article', 'solseo' ),
			'WebPage'     => __( 'Page', 'solseo' ),
			'Service'     => __( 'Service', 'solseo' ),
			'Course'      => __( 'Course', 'solseo' ),
			'none'        => __( 'None', 'solseo' ),
		);

		if ( solseo_has_woocommerce() ) {
			$types = array( 'Product' => __( 'Product', 'solseo' ) ) + $types;
		}

		return $types;
	}
}
