<?php
/**
 * Deciding what is worth telling a search engine about, and telling it once.
 *
 * @package SolSEO
 */

namespace SolSEO\Indexing;

use SolSEO\Change_Log;
use SolSEO\Meta;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * One call at the end of the request, however many pages were saved.
 *
 * A bulk edit of forty products fires `save_post` forty times inside one
 * request. Submitting on each one is forty calls, which is the shape that
 * earns a 429 and gets the site rate limited for the rest of the day. So the
 * addresses go into a queue and the queue goes out once, on shutdown.
 */
class Submit {

	/** When a post was last submitted. */
	const TOLD = '_solseo_told';

	/** How long before the same page is worth mentioning again. */
	const FLOOR = 60;

	/**
	 * Addresses waiting to go out at the end of this request.
	 *
	 * @var array
	 */
	protected static $queue = array();

	/**
	 * Whether the shutdown flush is already booked.
	 *
	 * @var bool
	 */
	protected static $booked = false;

	/**
	 * Hook in.
	 */
	public static function init() {
		if ( ! Options::get( 'indexnow_enabled' ) ) {
			return;
		}

		/*
		 * The key file is only served while submission is on. A file at the
		 * root of somebody's site is a thing they did not ask for until they
		 * switch on the feature that needs it.
		 */
		Indexnow::init();

		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
	}

	/**
	 * A post changed state. Tell them, if it is worth telling.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       The post.
	 */
	public static function on_status( $new_status, $old_status, $post ) {
		unset( $new_status, $old_status );

		if ( ! self::worth_telling( $post ) || ! self::due( $post ) ) {
			return;
		}

		self::mark( $post );
		self::queue( get_permalink( $post ) );
	}

	/**
	 * Whether a search engine would want to know about this at all.
	 *
	 * @param mixed $post The post.
	 * @return bool
	 */
	public static function worth_telling( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return false;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		if ( in_array( $post->post_type, array( 'revision', 'attachment', 'nav_menu_item' ), true ) ) {
			return false;
		}

		if ( Meta::get( $post->ID, 'robots_noindex' ) ) {
			return false;
		}

		/**
		 * Filter whether a post is submitted to the search engines.
		 *
		 * @param bool     $worth Whether it is.
		 * @param \WP_Post $post  The post.
		 */
		return (bool) apply_filters( 'solseo_worth_telling', true, $post );
	}

	/**
	 * Whether enough time has passed since the last time we said so.
	 *
	 * Saving in the block editor fires the status transition more than once on
	 * its own, before anybody presses anything twice.
	 *
	 * @param mixed $post The post.
	 * @return bool
	 */
	public static function due( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return false;
		}

		$last = (int) get_post_meta( $post->ID, self::TOLD, true );

		return ( time() - $last ) >= self::FLOOR;
	}

	/**
	 * Remember that we have just told them.
	 *
	 * @param mixed $post The post.
	 */
	public static function mark( $post ) {
		$post = get_post( $post );

		if ( $post ) {
			update_post_meta( $post->ID, self::TOLD, time() );
		}
	}

	/**
	 * Add an address to this request's queue.
	 *
	 * @param string $url Address.
	 */
	public static function queue( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url || in_array( $url, self::$queue, true ) ) {
			return;
		}

		self::$queue[] = $url;

		if ( ! self::$booked && function_exists( 'add_action' ) ) {
			self::$booked = true;

			add_action( 'shutdown', array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * What is waiting.
	 *
	 * @return array
	 */
	public static function queued() {
		return self::$queue;
	}

	/**
	 * Throw the queue away without sending it.
	 */
	public static function forget_queue() {
		self::$queue = array();
	}

	/**
	 * Send the queue.
	 *
	 * A connection that dropped gets one more go, because a dropped connection
	 * is an accident. An answer that says the key is wrong does not, because
	 * trying again will produce the same answer and a thousand requests.
	 *
	 * @return array Keys: ok, says, sent.
	 */
	public static function flush() {
		$urls = self::$queue;

		self::$queue = array();

		if ( ! $urls ) {
			return array(
				'ok'   => true,
				'says' => '',
				'sent' => 0,
			);
		}

		$result = Indexnow::submit( $urls );

		if ( is_wp_error( $result ) ) {
			$result = Indexnow::submit( $urls );
		}

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'   => false,
				'says' => $result->get_error_message(),
				'sent' => 0,
			);
		}

		if ( $result['ok'] ) {
			Change_Log::record(
				array(
					'what'  => __( 'Told the search engines a page changed', 'solseo' ),
					'label' => (string) wp_parse_url( $urls[0], PHP_URL_PATH ),
					'merge' => 'indexnow',
					'count' => count( $urls ),
				)
			);
		}

		return array(
			'ok'   => (bool) $result['ok'],
			'says' => (string) $result['says'],
			'sent' => $result['ok'] ? count( $urls ) : 0,
		);
	}
}
