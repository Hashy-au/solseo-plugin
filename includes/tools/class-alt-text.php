<?php
/**
 * Fills in missing image alt text.
 *
 * @package SolSEO
 */

namespace SolSEO\Tools;

use SolSEO\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Finds images with no alt text and writes something sensible.
 */
class Alt_Text {

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
	 * The next images with no alt text, after a cursor.
	 *
	 * The cursor matters here. Selecting the first hundred images with no alt
	 * text looks like it walks the library, and it does not: an image whose
	 * file name says nothing readable is skipped rather than filled, so it
	 * comes back in the next hundred, and the hundred after that, for ever.
	 * A person pressing a button never notices. Something driving it in a loop
	 * would never stop.
	 *
	 * @param int $after The last attachment ID finished.
	 * @param int $limit How many to return.
	 * @return array Attachment IDs, ascending.
	 */
	public static function ids_after( $after, $limit ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery -- the wildcard is ours and matches a mime prefix, not something anybody typed.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
				WHERE p.post_type = 'attachment'
				AND p.post_mime_type LIKE 'image/%'
				AND (pm.meta_id IS NULL OR pm.meta_value = '')
				AND p.ID > %d
				ORDER BY p.ID ASC LIMIT %d",
				(int) $after,
				(int) $limit
			)
		);
		// phpcs:enable

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * The images one page uses that have no alt text.
	 *
	 * Four sources, because a page's images are not one list anywhere in
	 * WordPress: the media attached to it, its featured image, the images
	 * written into its content, and a product's gallery. The content ones are
	 * addresses rather than identifiers, so the size suffix comes off before
	 * WordPress is asked which attachment they belong to.
	 *
	 * @param int $post_id Post ID.
	 * @return array Attachment IDs, ascending, each with no alt text.
	 */
	public static function missing_for_post( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$ids = array();

		foreach ( (array) get_attached_media( 'image', $post_id ) as $attached ) {
			$ids[] = (int) $attached->ID;
		}

		$thumbnail = (int) get_post_thumbnail_id( $post_id );

		if ( $thumbnail ) {
			$ids[] = $thumbnail;
		}

		foreach ( Content::images( Content::rendered( $post ) ) as $image ) {
			$found = attachment_url_to_postid( self::strip_size_suffix( $image['src'] ) );

			if ( $found ) {
				$ids[] = (int) $found;
			}
		}

		if ( 'product' === $post->post_type && solseo_has_woocommerce() ) {
			$product = wc_get_product( $post_id );

			if ( $product ) {
				$ids[] = (int) $product->get_image_id();

				foreach ( (array) $product->get_gallery_image_ids() as $gallery_id ) {
					$ids[] = (int) $gallery_id;
				}
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		$alt = array();

		foreach ( $ids as $id ) {
			$alt[ $id ] = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		}

		$bare = self::without_alt( $ids, $alt );
		sort( $bare );

		return $bare;
	}

	/**
	 * An image address without the size WordPress appended to it.
	 *
	 * Pure. /a/b-300x200.jpg and /a/b-scaled.jpg are both /a/b.jpg as far as
	 * the media library is concerned, and asking about the sized copy answers
	 * nothing.
	 *
	 * @param string $url Image address.
	 * @return string
	 */
	public static function strip_size_suffix( $url ) {
		$url = (string) $url;
		$url = preg_replace( '#-\d+x\d+(\.[A-Za-z0-9]+)$#', '$1', $url );

		return (string) preg_replace( '#-scaled(\.[A-Za-z0-9]+)$#', '$1', (string) $url );
	}

	/**
	 * Which of these attachments have nothing written in their alt text.
	 *
	 * Pure.
	 *
	 * @param array $ids Attachment IDs.
	 * @param array $alt Attachment ID to its stored alt text.
	 * @return array
	 */
	public static function without_alt( array $ids, array $alt ) {
		$bare = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( '' === trim( isset( $alt[ $id ] ) ? (string) $alt[ $id ] : '' ) ) {
				$bare[] = $id;
			}
		}

		return $bare;
	}

	/**
	 * What the tick box table shows: the images and what we would write.
	 *
	 * @param int $limit How many to return.
	 * @param int $after The last attachment ID shown.
	 * @return array Each entry has id, title, thumb and suggestion.
	 */
	public static function missing( $limit = 50, $after = 0 ) {
		$rows = array();

		foreach ( self::ids_after( $after, $limit ) as $id ) {
			$attachment = get_post( $id );

			if ( ! $attachment ) {
				continue;
			}

			$rows[] = array(
				'id'         => $id,
				'title'      => $attachment->post_title,
				'thumb'      => wp_get_attachment_image_url( $id, 'thumbnail' ),
				'suggestion' => self::suggest( $attachment ),
			);
		}

		return $rows;
	}

	/**
	 * Write alt text for one image.
	 *
	 * @param int $id Attachment ID.
	 * @return bool Whether anything was written.
	 */
	public static function fill_one( $id ) {
		$attachment = get_post( $id );

		if ( ! $attachment ) {
			return false;
		}

		$alt = self::suggest( $attachment );

		if ( '' === $alt ) {
			return false;
		}

		update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $alt );

		return true;
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
