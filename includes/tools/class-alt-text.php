<?php
/**
 * Fills in missing image alt text.
 *
 * @package SolSEO
 */

namespace SolSEO\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Finds images with no alt text and writes something sensible.
 */
class Alt_Text {

	/** How many images one pass works through. */
	const BATCH = 100;

	/**
	 * How many images in the library have no alt text.
	 *
	 * @return int
	 */
	public static function count_missing() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND (pm.meta_id IS NULL OR pm.meta_value = '')"
		);
	}

	/**
	 * Write alt text for the next batch of images that have none.
	 *
	 * The text comes from the post the image is attached to where there is one,
	 * because "Blue recurve bow" beats "img 2891 1".
	 *
	 * @param int $limit How many to work through.
	 * @return int How many were filled in.
	 */
	public static function fill( $limit = self::BATCH ) {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => $limit,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_wp_attachment_image_alt',
						'value'   => '',
						'compare' => '=',
					),
				),
			)
		);

		$filled = 0;

		foreach ( $attachments as $attachment ) {
			$alt = self::suggest( $attachment );

			if ( '' === $alt ) {
				continue;
			}

			update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $alt );
			++$filled;
		}

		return $filled;
	}

	/**
	 * What an image should say it is.
	 *
	 * @param \WP_Post $attachment Attachment.
	 * @return string
	 */
	public static function suggest( $attachment ) {
		if ( $attachment->post_parent ) {
			$parent = get_post( $attachment->post_parent );

			if ( $parent && $parent->post_title ) {
				return sanitize_text_field( $parent->post_title );
			}
		}

		return self::from_filename( $attachment );
	}

	/**
	 * A readable phrase from a file name.
	 *
	 * @param \WP_Post $attachment Attachment.
	 * @return string
	 */
	protected static function from_filename( $attachment ) {
		$file = get_post_meta( $attachment->ID, '_wp_attached_file', true );
		$name = $file ? pathinfo( (string) $file, PATHINFO_FILENAME ) : $attachment->post_title;

		$name = preg_replace( '/[-_]+/', ' ', (string) $name );
		$name = preg_replace( '/\b(?:\d{2,4}x\d{2,4}|scaled|copy|final|edited|img|dsc|dscn|pxl|screenshot)\b/i', ' ', $name );
		$name = preg_replace( '/\b\d{4,}\b/', ' ', $name );
		$name = trim( preg_replace( '/\s+/', ' ', $name ) );

		if ( mb_strlen( $name ) < 3 ) {
			return '';
		}

		return sanitize_text_field( ucfirst( $name ) );
	}
}
