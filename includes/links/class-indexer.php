<?php
/**
 * Reads the links out of a page and writes down where they go.
 *
 * It reuses Content::rendered() and Content::links(), which is what the checks
 * in the score already use, so the number in the column and the number the
 * check measured cannot disagree about the same page.
 *
 * @package SolSEO
 */

namespace SolSEO\Links;

use SolSEO\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the link table in step with the content.
 */
class Indexer {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 25, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'trashed_post', array( __CLASS__, 'on_gone' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_back' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_delete' ) );
		add_action( 'solseo_daily', array( __CLASS__, 'sweep' ) );
		add_action( 'solseo_links_backfill', array( __CLASS__, 'backfill' ) );
	}

	/**
	 * Reindex a page when it is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, solseo_post_types(), true ) ) {
			return;
		}

		if ( 'publish' === $post->post_status ) {
			self::index( $post_id );

			return;
		}

		/*
		 * A draft is not a link anybody can follow, so it must not count
		 * towards anything's incoming total.
		 */
		self::clear( $post_id );
	}

	/**
	 * Catch a page being published or unpublished with no content change.
	 *
	 * @param string   $status New status.
	 * @param string   $old    Old status.
	 * @param \WP_Post $post   Post.
	 */
	public static function on_status( $status, $old, $post ) {
		if ( $status === $old || ! $post || ! in_array( $post->post_type, solseo_post_types(), true ) ) {
			return;
		}

		if ( 'publish' === $status ) {
			self::index( $post->ID );

			// Something written before this page existed may now point at it.
			self::resolve_orphans( $post->ID );

			return;
		}

		if ( 'publish' === $old ) {
			self::clear( $post->ID );
		}
	}

	/**
	 * A trashed page links to nothing.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_gone( $post_id ) {
		self::clear( $post_id );
	}

	/**
	 * A page out of the bin is indexed again.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_back( $post_id ) {
		$post = get_post( $post_id );

		if ( $post && 'publish' === $post->post_status ) {
			self::index( $post_id );
		}
	}

	/**
	 * Tidy up after a page that is gone for good.
	 *
	 * The links pointing at it are kept, with the post ID cleared. They still
	 * exist in somebody's HTML; they are broken now, which is a different
	 * thing from never having been written.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete( $post_id ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'solseo_links';
		$sources = self::sources_pointing_at( $post_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'source_id' => (int) $post_id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'target_id' => 0 ), array( 'target_id' => (int) $post_id ), array( '%d' ), array( '%d' ) );

		Counts::forget( $post_id );

		if ( $sources ) {
			self::rewrite_counts( $sources );
		}
	}

	/**
	 * Read one page's links and write them down.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function index( $post_id ) {
		global $wpdb;

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$was   = self::targets_of( $post_id );
		$home  = home_url();
		$links = Content::links( Content::rendered( $post ) );
		$table = $wpdb->prefix . 'solseo_links';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'source_id' => (int) $post_id ), array( '%d' ) );

		$now  = array();
		$seen = array();

		foreach ( (array) $links['internal'] as $href ) {
			$path = Resolver::normalise( $href, $home );

			if ( '' === $path || isset( $seen[ $path ] ) ) {
				continue;
			}

			$seen[ $path ] = true;
			$target        = Resolver::to_post_id( $href );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'source_id'  => (int) $post_id,
					'target_id'  => (int) $target,
					'target_url' => $path,
					'link_type'  => 'internal',
				),
				array( '%d', '%d', '%s', '%s' )
			);

			if ( $target ) {
				$now[] = (int) $target;
			}
		}

		update_post_meta( $post_id, Counts::INTERNAL, count( $seen ) );
		update_post_meta( $post_id, Counts::OUTBOUND, count( (array) $links['external'] ) );

		Counts::refresh( array_merge( $was, $now ) );
	}

	/**
	 * Take one page's links out of the table.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function clear( $post_id ) {
		global $wpdb;

		$was = self::targets_of( $post_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'solseo_links', array( 'source_id' => (int) $post_id ), array( '%d' ) );

		update_post_meta( $post_id, Counts::INTERNAL, 0 );
		update_post_meta( $post_id, Counts::OUTBOUND, 0 );

		Counts::refresh( $was );
	}

	/**
	 * Point rows at a page that has only just turned up.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function resolve_orphans( $post_id ) {
		global $wpdb;

		$path = Resolver::normalise( (string) get_permalink( $post_id ), home_url() );

		if ( '' === $path ) {
			return;
		}

		$table = $wpdb->prefix . 'solseo_links';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array( 'target_id' => (int) $post_id ),
			array(
				'target_url' => $path,
				'target_id'  => 0,
			),
			array( '%d' ),
			array( '%s', '%d' )
		);

		Counts::refresh( array( $post_id ) );
	}

	/**
	 * The internal addresses this page links to that resolve to no page.
	 *
	 * The caller decides which of these count as broken. A link to an uploaded
	 * file, a feed or a category archive resolves to no POST and is perfectly
	 * fine, so a raw count of these would report a working page as broken.
	 *
	 * @param int $post_id Post ID.
	 * @return array Paths, as stored.
	 */
	public static function unresolved_targets( $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$paths = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT DISTINCT target_url FROM {$table}
				WHERE source_id = %d AND target_id = 0 AND link_type = 'internal'",
				(int) $post_id
			)
		);

		return array_values( array_filter( array_map( 'strval', (array) $paths ) ) );
	}

	/**
	 * The daily tidy up.
	 *
	 * Rows written before their target existed are looked at again, and the
	 * backfill is nudged along if it never finished.
	 */
	public static function sweep() {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$orphans = $wpdb->get_col( "SELECT DISTINCT target_url FROM {$table} WHERE target_id = 0 LIMIT 500" );

		$found = array();

		foreach ( (array) $orphans as $path ) {
			$post_id = Resolver::to_post_id( home_url( $path ) );

			if ( ! $post_id ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array( 'target_id' => (int) $post_id ),
				array(
					'target_url' => $path,
					'target_id'  => 0,
				),
				array( '%d' ),
				array( '%s', '%d' )
			);

			$found[] = (int) $post_id;
		}

		if ( $found ) {
			Counts::refresh( $found );
		}

		if ( ! get_option( 'solseo_links_indexed' ) ) {
			self::start_backfill( 0 );
		}
	}

	/**
	 * Index the pages that were already here when this arrived.
	 *
	 * One hundred at a time, each run booking the next, so the queue never
	 * holds more than one thing and a site with fifty thousand pages does not
	 * try to do them all in one request.
	 *
	 * @param int $after The last post ID indexed.
	 */
	public static function backfill( $after = 0 ) {
		$ids = \SolSEO\Score_Report::ids_after( 'all', (int) $after, 100 );

		if ( ! $ids ) {
			update_option( 'solseo_links_indexed', 1, false );

			return;
		}

		foreach ( $ids as $id ) {
			self::index( $id );
		}

		self::start_backfill( end( $ids ) );
	}

	/**
	 * Book the next run of the backfill.
	 *
	 * @param int $after The last post ID indexed.
	 */
	public static function start_backfill( $after ) {
		if ( wp_next_scheduled( 'solseo_links_backfill', array( (int) $after ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + 30, 'solseo_links_backfill', array( (int) $after ) );
	}

	/**
	 * The pages one page currently links to.
	 *
	 * @param int $post_id Post ID.
	 * @return array Post IDs.
	 */
	protected static function targets_of( $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT target_id FROM {$table} WHERE source_id = %d AND target_id > 0", (int) $post_id ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * The pages linking at one page.
	 *
	 * @param int $post_id Post ID.
	 * @return array Post IDs.
	 */
	protected static function sources_pointing_at( $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT source_id FROM {$table} WHERE target_id = %d", (int) $post_id ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Recount the internal links held by a set of pages.
	 *
	 * @param array $post_ids Post IDs.
	 */
	protected static function rewrite_counts( array $post_ids ) {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		foreach ( $post_ids as $post_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source_id = %d AND link_type = 'internal'", (int) $post_id ) );

			update_post_meta( $post_id, Counts::INTERNAL, $total );
		}
	}
}
