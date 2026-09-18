<?php
/**
 * The SolSEO box in the editor.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Analyser;
use SolSEO\Analysis\Paper;
use SolSEO\Meta;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the editor box and saves what it collects.
 */
class Metabox {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
	}

	/**
	 * Add the box to every post type the plugin manages.
	 */
	public static function register() {
		foreach ( solseo_post_types() as $post_type ) {
			add_meta_box(
				'solseo',
				__( 'SolSEO', 'solseo' ),
				array( __CLASS__, 'render' ),
				$post_type,
				'normal',
				'high',
				array( '__block_editor_compatible_meta_box' => true )
			);
		}
	}

	/**
	 * Draw the box.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'solseo_metabox', 'solseo_metabox_nonce' );

		$analysis = Analyser::run( Paper::from_post( $post ) );

		Screen::view(
			'metabox',
			array(
				'post'     => $post,
				'analysis' => $analysis,
				'meta'     => self::values( $post->ID ),
				'settings' => Options::post_type( $post->post_type ),
			)
		);
	}

	/**
	 * Save what the box collected.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST['solseo_metabox_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['solseo_metabox_nonce'] ) ), 'solseo_metabox' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Meta::save sanitises every field by type.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

		if ( ! is_array( $input ) ) {
			return;
		}

		Meta::save(
			$post_id,
			array(
				'title'               => isset( $input['title'] ) ? $input['title'] : '',
				'description'         => isset( $input['description'] ) ? $input['description'] : '',
				'focus_keyword'       => isset( $input['focus_keyword'] ) ? $input['focus_keyword'] : '',
				'keywords'            => isset( $input['keywords'] ) ? $input['keywords'] : '',
				'canonical'           => isset( $input['canonical'] ) ? $input['canonical'] : '',
				'robots_noindex'      => ! empty( $input['robots_noindex'] ),
				'robots_nofollow'     => ! empty( $input['robots_nofollow'] ),
				'robots_advanced'     => isset( $input['robots_advanced'] ) ? (array) $input['robots_advanced'] : array(),
				'og_title'            => isset( $input['og_title'] ) ? $input['og_title'] : '',
				'og_description'      => isset( $input['og_description'] ) ? $input['og_description'] : '',
				'og_image'            => isset( $input['og_image'] ) ? $input['og_image'] : 0,
				'twitter_title'       => isset( $input['twitter_title'] ) ? $input['twitter_title'] : '',
				'twitter_description' => isset( $input['twitter_description'] ) ? $input['twitter_description'] : '',
				'schema_type'         => isset( $input['schema_type'] ) ? $input['schema_type'] : '',
			)
		);
	}

	/**
	 * Everything the box needs to fill its fields.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	protected static function values( $post_id ) {
		return array(
			'title'               => Meta::get( $post_id, 'title' ),
			'description'         => Meta::get( $post_id, 'description' ),
			'focus_keyword'       => Meta::get( $post_id, 'focus_keyword' ),
			'keywords'            => implode( ', ', Meta::get( $post_id, 'keywords' ) ),
			'canonical'           => Meta::get( $post_id, 'canonical' ),
			'robots_noindex'      => Meta::get( $post_id, 'robots_noindex' ),
			'robots_nofollow'     => Meta::get( $post_id, 'robots_nofollow' ),
			'robots_advanced'     => Meta::get( $post_id, 'robots_advanced' ),
			'og_title'            => Meta::get( $post_id, 'og_title' ),
			'og_description'      => Meta::get( $post_id, 'og_description' ),
			'og_image'            => Meta::get( $post_id, 'og_image' ),
			'twitter_title'       => Meta::get( $post_id, 'twitter_title' ),
			'twitter_description' => Meta::get( $post_id, 'twitter_description' ),
			'schema_type'         => Meta::get( $post_id, 'schema_type' ),
		);
	}
}
