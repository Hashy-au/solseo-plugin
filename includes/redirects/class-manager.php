<?php
/**
 * Redirects and the log of addresses that went nowhere.
 *
 * @package SolSEO
 */

namespace SolSEO\Redirects;

use SolSEO\Install;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

// The tables below belong to this plugin. A table name cannot be passed to
// $wpdb->prepare() as a placeholder, so it is interpolated and the values
// around it are prepared. There is nothing to cache: a redirect has to be
// read on the request it answers.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

/**
 * Matches the request against the stored rules and sends the visitor on.
 */
class Manager {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_action( 'solseo_daily', array( __CLASS__, 'prune_log' ) );
	}

	/**
	 * Send the visitor on when a rule matches, and log a miss when one does not.
	 */
	public static function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$request = self::request_path();

		if ( '' === $request ) {
			return;
		}

		$rule = self::match( $request );

		if ( $rule ) {
			self::record_hit( (int) $rule['id'] );

			if ( 410 === (int) $rule['status_code'] || 451 === (int) $rule['status_code'] ) {
				status_header( (int) $rule['status_code'] );
				nocache_headers();
				include get_query_template( '404' );
				exit;
			}

			wp_safe_redirect( $rule['target'], (int) $rule['status_code'], 'SolSEO' );
			exit;
		}

		if ( is_404() && Options::get( 'log_not_found' ) ) {
			self::log_miss( $request );
		}
	}

	/**
	 * Find a rule for a request.
	 *
	 * Exact rules are checked first because they are the common case and cost
	 * one indexed lookup. Regular expressions are only read when none matched.
	 *
	 * @param string $request Request path, with its query string.
	 * @return array|null
	 */
	public static function match( $request ) {
		global $wpdb;

		$table = Install::table( 'redirects' );
		$paths = array_unique( array( $request, untrailingslashit( $request ), trailingslashit( $request ) ) );
		$slots = implode( ', ', array_fill( 0, count( $paths ), '%s' ) );

		$exact = $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE enabled = 1 AND match_type = 'exact' AND source IN ({$slots}) LIMIT 1",
				...array_values( $paths )
			),
			ARRAY_A
		);

		if ( $exact ) {
			return $exact;
		}

		$patterns = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			"SELECT * FROM {$table} WHERE enabled = 1 AND match_type = 'regex'",
			ARRAY_A
		);

		foreach ( (array) $patterns as $rule ) {
			$pattern = '#' . str_replace( '#', '\\#', $rule['source'] ) . '#i';

			if ( 1 === @preg_match( $pattern, $request ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a rule typed by hand can be an invalid pattern.
				$rule['target'] = preg_replace( $pattern, $rule['target'], $request );

				return $rule;
			}
		}

		return null;
	}

	/**
	 * Store a rule.
	 *
	 * @param array $rule Keys: source, target, status_code, match_type, enabled.
	 * @param int   $id   Rule to update, or 0 to add one.
	 * @return int|false The rule ID, or false when the rule is not usable.
	 */
	public static function save( array $rule, $id = 0 ) {
		global $wpdb;

		/*
		 * THE MATCH TYPE IS DECIDED FIRST, BECAUSE normalise() IS ABOUT PATHS.
		 *
		 * It glues a leading slash on, which is exactly right for a path and
		 * ruinous for a pattern: `^/old-shop/(.*)$` came out of it as
		 * `/^/old-shop/(.*)$`, and a `^` that is not at the start of a
		 * pattern, with no `m` modifier, can never match. The rule then sat on
		 * the Rules tab looking like the one that was typed with its hit count
		 * stuck on zero (D-158.1). A regex is stored as it was typed.
		 */
		$type   = 'regex' === ( isset( $rule['match_type'] ) ? $rule['match_type'] : '' ) ? 'regex' : 'exact';
		$typed  = isset( $rule['source'] ) ? (string) $rule['source'] : '';
		$source = 'regex' === $type ? trim( $typed ) : self::normalise( $typed );
		$target = isset( $rule['target'] ) ? trim( $rule['target'] ) : '';

		if ( '' === $source || ( '' === $target && ! in_array( (int) $rule['status_code'], array( 410, 451 ), true ) ) ) {
			return false;
		}

		$data = array(
			'source'      => $source,
			'target'      => $target,
			'status_code' => in_array( (int) $rule['status_code'], array( 301, 302, 307, 410, 451 ), true ) ? (int) $rule['status_code'] : 301,
			'match_type'  => $type,
			'enabled'     => empty( $rule['enabled'] ) ? 0 : 1,
		);

		if ( $id ) {
			$wpdb->update( Install::table( 'redirects' ), $data, array( 'id' => $id ) );
			return $id;
		}

		$data['created_at'] = current_time( 'mysql', true );

		$wpdb->insert( Install::table( 'redirects' ), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Which of these stored patterns are provably dead, and what they should say.
	 *
	 * ONLY `/^` IS TOUCHED, AND THE REASON IS THAT IT CANNOT MATCH.
	 *
	 * A pattern beginning `/^` asks the engine to consume a slash and then
	 * assert that nothing has been consumed. There is no subject and no offset
	 * where that holds, so a rule shaped like this answers nothing today, and
	 * taking the slash off it cannot change an answer anybody is getting. That
	 * is the whole of the safety argument.
	 *
	 * EVERY OTHER SHAPE IS LEFT ALONE, AND NOT OUT OF CAUTION. `/product/(.*)`
	 * in the column is the same bytes whether it was typed with the slash or
	 * without it. The two are indistinguishable, so a blanket strip would
	 * break rules that were typed correctly in order to fix rules that were
	 * not, and the ones it would break are the ones that work.
	 *
	 * @param array $sources Stored sources of regex rules.
	 * @return array Map of the stored source to what it should be. Only the
	 *               ones that change are in it.
	 */
	public static function repair_regex_sources( array $sources ) {
		$fixes = array();

		foreach ( $sources as $source ) {
			$source = (string) $source;

			if ( 0 !== strpos( $source, '/^' ) ) {
				continue;
			}

			$fixes[ $source ] = substr( $source, 1 );
		}

		return $fixes;
	}

	/**
	 * Take the stray slash off every stored regex that cannot match because of it.
	 *
	 * Runs once, from the upgrade path. The option is what stops it running
	 * again: a customer who deliberately retypes a rule as `/^...` afterwards
	 * has written a rule that matches nothing, and quietly rewriting it under
	 * them a second time would be us editing their rule rather than undoing
	 * our own bug.
	 *
	 * @return int How many rules were changed.
	 */
	public static function repair_regex_rules() {
		global $wpdb;

		$table = Install::table( 'redirects' );

		$rules = (array) $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			"SELECT id, source FROM {$table} WHERE match_type = 'regex'",
			ARRAY_A
		);

		$sources = array();

		foreach ( $rules as $rule ) {
			$sources[] = isset( $rule['source'] ) ? (string) $rule['source'] : '';
		}

		$fixes = self::repair_regex_sources( $sources );

		if ( ! $fixes ) {
			return 0;
		}

		$changed = 0;

		foreach ( $rules as $rule ) {
			$source = isset( $rule['source'] ) ? (string) $rule['source'] : '';

			if ( ! isset( $fixes[ $source ] ) ) {
				continue;
			}

			$wpdb->update(
				$table,
				array( 'source' => $fixes[ $source ] ),
				array( 'id' => (int) $rule['id'] )
			);

			++$changed;
		}

		return $changed;
	}

	/**
	 * Delete a rule.
	 *
	 * @param int $id Rule ID.
	 */
	public static function delete( $id ) {
		global $wpdb;

		$wpdb->delete( Install::table( 'redirects' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Rules, newest first.
	 *
	 * @param int $per_page How many to return.
	 * @param int $offset   Where to start.
	 * @return array
	 */
	public static function all( $per_page = 50, $offset = 0 ) {
		global $wpdb;

		$table = Install::table( 'redirects' );

		return (array) $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_A
		);
	}

	/**
	 * How many rules exist.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		$table = Install::table( 'redirects' );

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
	}

	/**
	 * Throw away log entries nobody is going to act on.
	 */
	public static function prune_log() {
		global $wpdb;

		$days  = max( 1, (int) Options::get( 'log_retention_days' ) );
		$table = Install::table( 'not_found' );

		$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", $days )
		);
	}

	/**
	 * The path being asked for, including the query string.
	 *
	 * @return string
	 */
	public static function request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$uri  = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised below.
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( $home && '/' !== $home && 0 === strpos( (string) $path, $home ) ) {
			$path = substr( (string) $path, strlen( $home ) - 1 );
		}

		return self::normalise( (string) $path );
	}

	/**
	 * Put a path into the shape rules are stored in.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	public static function normalise( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $path ) ) {
			$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		}

		$path = '/' . ltrim( $path, '/' );

		return esc_url_raw( $path, array( 'http', 'https' ) ) ? $path : '';
	}

	/**
	 * Count a rule as used.
	 *
	 * @param int $id Rule ID.
	 */
	protected static function record_hit( $id ) {
		global $wpdb;

		$table = Install::table( 'redirects' );

		$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare( "UPDATE {$table} SET hits = hits + 1, last_used = %s WHERE id = %d", current_time( 'mysql', true ), $id )
		);
	}

	/**
	 * Record an address that found nothing.
	 *
	 * The address and the page it was linked from are kept. Nothing about the
	 * visitor is.
	 *
	 * @param string $request Request path.
	 */
	protected static function log_miss( $request ) {
		global $wpdb;

		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$now      = current_time( 'mysql', true );
		$table    = Install::table( 'not_found' );

		$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare(
				"INSERT INTO {$table} (url, referrer, hits, first_seen, last_seen) VALUES (%s, %s, 1, %s, %s)
				ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = VALUES(referrer)",
				mb_substr( $request, 0, 255 ),
				mb_substr( $referrer, 0, 255 ),
				$now,
				$now
			)
		);
	}
}
