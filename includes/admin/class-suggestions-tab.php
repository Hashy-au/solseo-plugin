<?php
/**
 * The pages that should link to one page, and the button that writes one.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Links\Checker;
use SolSEO\Links\Resolver;
use SolSEO\Links\Suggestions;
use SolSEO\Score_Report;

defined( 'ABSPATH' ) || exit;

/**
 * Content, Links.
 */
class Suggestions_Tab extends Screen {

	const PAGE = 'solseo-content';

	const TAB = 'links';

	/** How many pages the picker offers. */
	const PICKER = 100;

	/**
	 * Write a suggested link, when somebody has asked for one.
	 */
	public static function load() {
		if ( ! self::submitted( 'solseo_suggestion_accept' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$source = isset( $_POST['solseo_source'] ) ? (int) $_POST['solseo_source'] : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$target = isset( $_POST['solseo_target'] ) ? (int) $_POST['solseo_target'] : 0;

		$done = Suggestions::accept( $source, $target );

		if ( is_wp_error( $done ) ) {
			self::remember( $done->get_error_message(), 'error' );
		} else {
			self::remember(
				sprintf(
					/* translators: 1: the words the link was written on. 2: the page it was written into. */
					__( 'Linked "%1$s" on %2$s. That page now has a revision holding what it said before.', 'solseo' ),
					(string) $done['anchor'],
					get_the_title( $source )
				)
			);
		}

		self::go_back(
			self::PAGE,
			array(
				'tab'  => self::TAB,
				'post' => $target,
			)
		);
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		self::notice();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which page to work on.
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		$pages = self::pages();

		if ( ! $post_id && $pages ) {
			$post_id = (int) $pages[0]['id'];
		}

		self::view(
			'content-links',
			array(
				'post_id'     => $post_id,
				'pages'       => $pages,
				'phrase'      => $post_id ? Suggestions::phrase_for( $post_id ) : '',
				'suggestions' => $post_id ? Suggestions::for_post( $post_id ) : array(),
				'already'     => $post_id ? Checker::sources_of( self::path_of( $post_id ), 20 ) : array(),
				'page'        => self::PAGE,
				'tab'         => self::TAB,
			)
		);
	}

	/**
	 * The published pages somebody might want suggestions for.
	 *
	 * @return array Each with id and title.
	 */
	protected static function pages() {
		$found = array();

		foreach ( Score_Report::ids_after( 'all', 0, self::PICKER ) as $id ) {
			$post = get_post( (int) $id );

			if ( ! $post ) {
				continue;
			}

			$found[] = array(
				'id'    => (int) $id,
				'title' => '' === $post->post_title ? __( 'A page with no title', 'solseo' ) : $post->post_title,
			);
		}

		return $found;
	}

	/**
	 * A page's address, the way the link index writes one down.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	protected static function path_of( $post_id ) {
		return Resolver::normalise( (string) get_permalink( (int) $post_id ), home_url() );
	}
}
