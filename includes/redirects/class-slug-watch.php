<?php
/**
 * A published page that moves leaves a forwarding address.
 *
 * @package SolSEO
 */

namespace SolSEO\Redirects;

use SolSEO\Change_Log;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Watches for a slug change on something that was already published.
 *
 * Only on something that was published and still is. A draft has never had an
 * address anybody could have followed, and renaming one is somebody deciding
 * what to call it rather than moving it. Yoast charges for the redirect manager
 * this writes into, which is the whole argument for doing it here.
 */
class Slug_Watch {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'post_updated', array( __CLASS__, 'moved' ), 10, 3 );
	}

	/**
	 * Write the redirect when a published slug changes.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $after   The post as it is now.
	 * @param \WP_Post $before  The post as it was.
	 */
	public static function moved( $post_id, $after, $before ) {
		if ( ! Options::get( 'redirect_on_slug_change' ) ) {
			return;
		}

		if ( ! is_object( $after ) || ! is_object( $before ) ) {
			return;
		}

		if ( 'publish' !== $before->post_status || 'publish' !== $after->post_status ) {
			return;
		}

		if ( $before->post_name === $after->post_name || '' === $before->post_name ) {
			return;
		}

		if ( ! in_array( $after->post_type, solseo_post_types(), true ) ) {
			return;
		}

		$from = self::path_for( $before );
		$to   = get_permalink( $post_id );

		if ( ! $from || ! $to ) {
			return;
		}

		$new_path = self::path_of( $to );

		/*
		 * A rename and a rename back would otherwise leave a rule pointing at
		 * the address it lives at, which is a loop the moment anything follows
		 * it. The matcher refuses one, and so does this, earlier and quietly.
		 */
		if ( $from === $new_path ) {
			return;
		}

		self::clear_loop( $new_path );

		$saved = Manager::save(
			array(
				'source'      => $from,
				'target'      => $to,
				'status_code' => 301,
				'match_type'  => 'exact',
				'enabled'     => 1,
			)
		);

		if ( ! $saved ) {
			return;
		}

		Change_Log::record(
			array(
				'what'  => __( 'Redirected an address that moved', 'solseo' ),
				'label' => $from,
				'merge' => 'slug-change',
			)
		);
	}

	/**
	 * The address a post used to live at.
	 *
	 * Built by asking WordPress what the permalink would be with the old slug,
	 * rather than by assembling one, because the structure can hold the date,
	 * the category and the author and we would get one of those wrong.
	 *
	 * @param \WP_Post $before The post as it was.
	 * @return string
	 */
	protected static function path_for( $before ) {
		$permalink = get_permalink( $before );

		return $permalink ? self::path_of( $permalink ) : '';
	}

	/**
	 * The path part of an address.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	protected static function path_of( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return '' === $path ? '' : $path;
	}

	/**
	 * Remove any rule that already points away from where we are moving to.
	 *
	 * Moving back to an address that redirects elsewhere is the other way a
	 * loop arrives, and it is the common one: rename, rename back.
	 *
	 * @param string $path The address being moved to.
	 */
	protected static function clear_loop( $path ) {
		$existing = Manager::match( $path );

		if ( ! $existing || empty( $existing['id'] ) ) {
			return;
		}

		/*
		 * Only an exact rule for this one address, and only when its source is
		 * this address rather than something that merely matched it. A pattern
		 * somebody wrote on purpose is not ours to delete because a slug moved,
		 * and deleting one quietly is far worse than leaving a loop the matcher
		 * already refuses to follow.
		 */
		if ( 'exact' !== ( isset( $existing['match_type'] ) ? $existing['match_type'] : '' ) ) {
			return;
		}

		if ( untrailingslashit( (string) $existing['source'] ) !== untrailingslashit( $path ) ) {
			return;
		}

		Manager::delete( (int) $existing['id'] );
	}
}
