<?php
/**
 * Form controls used on more than one screen.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Small renderers shared by the settings views.
 */
class Fields {

	/**
	 * An attachment picker backed by the media library.
	 *
	 * @param string $name       Field name.
	 * @param int    $attachment Current attachment ID.
	 */
	public static function image( $name, $attachment ) {
		wp_enqueue_media();
		wp_enqueue_script( 'solseo-admin', SOLSEO_URL . 'assets/js/admin.js', array( 'media-editor' ), SOLSEO_VERSION, true );

		$preview = $attachment ? wp_get_attachment_image_url( $attachment, 'thumbnail' ) : '';

		printf(
			'<div class="solseo-image-field">
				<img src="%1$s" alt="" class="solseo-image-preview%2$s">
				<input type="hidden" name="%3$s" value="%4$d" class="solseo-image-id">
				<button type="button" class="button solseo-image-choose">%5$s</button>
				<button type="button" class="button-link solseo-image-clear%6$s">%7$s</button>
			</div>',
			esc_url( $preview ),
			$preview ? '' : ' hidden',
			esc_attr( $name ),
			(int) $attachment,
			esc_html__( 'Choose image', 'solseo' ),
			$attachment ? '' : ' hidden',
			esc_html__( 'Remove', 'solseo' )
		);
	}

	/**
	 * A text field holding a template.
	 *
	 * @param string $name  Field name.
	 * @param string $value Current value.
	 * @param string $label Accessible label.
	 */
	public static function template( $name, $value, $label ) {
		printf(
			'<input type="text" class="large-text code solseo-template" name="%1$s" value="%2$s" aria-label="%3$s">',
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $label )
		);
	}
}
