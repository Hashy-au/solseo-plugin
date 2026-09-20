<?php
/**
 * Internal links that go nowhere, and internal links that go the long way.
 *
 * Nothing in here opens a connection. The addresses worth asking about go into
 * the crawl frontier and the crawl asks, through the one file allowed to, and
 * what comes back is a status code this reads. A link to somebody else's site
 * is never checked at all in the free plugin: that is a request to somebody
 * else's server, on a schedule, with a cache and a back off, and it is ProB2.
 *
 * @package SolSEO
 */

namespace SolSEO\Links;

use SolSEO\Change_Log;
use SolSEO\Crawl\Pages;
use SolSEO\Redirects\Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the link index and the crawl, and offers three ways out.
 */
class Checker {

	/** How many linking pages are named before the rest become a number. */
	const NAMED = 3;

	/** How far a redirect chain is followed before it is called a chain. */
	const MAX_HOPS = 5;

	/**
	 * Whether an address is one this plugin should ask about.
	 *
	 * Pure. The link table's own note says why this exists: a raw list of
	 * internal links that resolve to no post reports a working site as broken,
	 * because an upload, a feed and the login screen are all links to something
	 * that is not a post and all of them answer perfectly well. A checker that
	 * cries wolf is switched off within a week, and then the real ones go
	 * unread too.
	 *
	 * @param string $href The address, as written.
	 * @return bool
	 */
	public static function checkable( $href ) {
		$path = Resolver::normalise( $href, home_url() );

		if ( '' === $path ) {
			return false;
		}

		$skip = array(
			'#^/wp-content(/|$)#',
			'#^/wp-includes(/|$)#',
			'#^/wp-admin(/|$)#',
			'#^/wp-json(/|$)#',
			'#^/wp-login\.php$#',
			'#^/xmlrpc\.php$#',
			'#(^|/)feed$#',
			'#\.(jpe?g|png|gif|webp|avif|svgz?|ico|pdf|zip|gz|rar|docx?|xlsx?|pptx?|csv|txt|xml|json|mp3|mp4|mov|avi|wav|woff2?|ttf|eot|css|js)$#',
		);

		foreach ( $skip as $pattern ) {
			if ( preg_match( $pattern, $path ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The addresses this site links to and cannot account for.
	 *
	 * These go into the crawl frontier. Until something has fetched one, the
	 * most the plugin can honestly say is that it could not find a page there,
	 * which is a different sentence from "your site answered 404".
	 *
	 * @param int $limit How many at most.
	 * @return array Path to the post IDs that link to it.
	 */
	public static function suspects( $limit = 300 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT target_url, GROUP_CONCAT(DISTINCT source_id) AS sources
				FROM {$table}
				WHERE link_type = 'internal' AND target_id = 0 AND target_url <> ''
				GROUP BY target_url
				ORDER BY COUNT(DISTINCT source_id) DESC, target_url ASC
				LIMIT %d",
				(int) $limit * 3
			),
			ARRAY_A
		);
		// phpcs:enable

		$found = array();

		foreach ( $rows as $row ) {
			$path = (string) $row['target_url'];

			if ( ! self::checkable( $path ) ) {
				continue;
			}

			$found[ $path ] = array_map( 'intval', array_filter( explode( ',', (string) $row['sources'] ) ) );

			if ( count( $found ) >= (int) $limit ) {
				break;
			}
		}

		return $found;
	}

	/**
	 * The internal links in a piece of writing that point at no page.
	 *
	 * This is the editor's half, and it makes no request. WordPress is asked
	 * whether an address resolves to something and the redirect table is asked
	 * whether it is covered, both of which are answers this site already has.
	 * The crawl is what turns "we could not find a page there" into "your site
	 * answered 404", and the two say different things on purpose.
	 *
	 * @param string $html The content being written.
	 * @param int    $limit How many to report.
	 * @return array Each with href, path and text.
	 */
	public static function in_content( $html, $limit = 20 ) {
		$found = array();

		foreach ( self::anchors( $html ) as $anchor ) {
			if ( ! self::checkable( $anchor['href'] ) ) {
				continue;
			}

			$path = Resolver::normalise( $anchor['href'], home_url() );

			if ( isset( $found[ $path ] ) ) {
				continue;
			}

			if ( Resolver::to_post_id( home_url( $path ) ) ) {
				continue;
			}

			if ( Manager::match( $path ) ) {
				continue;
			}

			$found[ $path ] = array(
				'href' => $anchor['href'],
				'path' => $path,
				'text' => $anchor['text'],
			);

			if ( count( $found ) >= (int) $limit ) {
				break;
			}
		}

		return array_values( $found );
	}

	/**
	 * Every link in a piece of markup, with the words it was written on.
	 *
	 * Pure.
	 *
	 * @param string $html Any markup.
	 * @return array Each with href and text.
	 */
	public static function anchors( $html ) {
		if ( ! preg_match_all( '#<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is', (string) $html, $found, PREG_SET_ORDER ) ) {
			return array();
		}

		$anchors = array();

		foreach ( $found as $anchor ) {
			$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $anchor[3] ) ) );

			$anchors[] = array(
				'href' => trim( $anchor[2] ),
				'text' => $text,
			);
		}

		return $anchors;
	}

	/**
	 * The pages that link to one address.
	 *
	 * @param string $path A normalised path.
	 * @param int    $limit How many.
	 * @return array Each with id and title.
	 */
	public static function sources_of( $path, $limit = 20 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT DISTINCT source_id FROM {$table} WHERE target_url = %s AND link_type = 'internal' ORDER BY source_id ASC LIMIT %d",
				$path,
				(int) $limit
			)
		);

		$sources = array();

		foreach ( (array) $ids as $id ) {
			$post = get_post( (int) $id );

			if ( ! $post ) {
				continue;
			}

			$sources[] = array(
				'id'    => (int) $id,
				'title' => '' === $post->post_title ? __( 'A page with no title', 'solseo' ) : $post->post_title,
			);
		}

		return $sources;
	}

	/**
	 * Every address the crawl found that did not answer, with who links to it.
	 *
	 * @param int $limit How many.
	 * @return array
	 */
	public static function dead( $limit = 100 ) {
		$rows = array();

		foreach ( Pages::failures( $limit ) as $row ) {
			$path = Resolver::normalise( (string) $row['url'], home_url() );

			$rows[] = array(
				'url'         => (string) $row['url'],
				'path'        => $path,
				'status_code' => (int) $row['status_code'],
				'redirect_to' => (string) $row['redirect_to'],
				'note'        => (string) $row['note'],
				'sources'     => self::sources_of( $path ),
			);
		}

		return $rows;
	}

	/**
	 * Every internal address that takes more than one hop to arrive.
	 *
	 * The map is built from two places that both know about a move: the rules
	 * this plugin serves itself, and whatever the crawl was actually answered
	 * with, which catches a redirect somebody else's plugin is doing.
	 *
	 * @param int $limit How many.
	 * @return array Each with path, hops and sources.
	 */
	public static function chains( $limit = 100 ) {
		$map   = self::redirect_map();
		$found = array();

		foreach ( array_keys( $map ) as $path ) {
			$hops = self::hops( $path, $map, self::MAX_HOPS );

			if ( ! self::worth_saying( $hops ) ) {
				continue;
			}

			$sources = self::sources_of( $path );

			if ( ! $sources ) {
				continue;
			}

			$found[] = array(
				'path'    => $path,
				'hops'    => $hops,
				'loops'   => self::loops( $path, $map ),
				'sources' => $sources,
			);

			if ( count( $found ) >= (int) $limit ) {
				break;
			}
		}

		return $found;
	}

	/**
	 * Follow a chain as far as it goes.
	 *
	 * Pure. Stops on an address it has already been to, because a loop is a
	 * thing sites really have and following one is a request that never ends.
	 *
	 * @param string $start The first address.
	 * @param array  $map   Path to where it goes.
	 * @param int    $limit How many hops to follow.
	 * @return array The addresses, starting with the one asked about.
	 */
	public static function hops( $start, array $map, $limit ) {
		$chain = array( (string) $start );
		$at    = (string) $start;

		for ( $step = 0; $step < (int) $limit; $step++ ) {
			if ( ! isset( $map[ $at ] ) || '' === $map[ $at ] ) {
				break;
			}

			$next = (string) $map[ $at ];

			if ( in_array( $next, $chain, true ) ) {
				break;
			}

			$chain[] = $next;
			$at      = $next;
		}

		return $chain;
	}

	/**
	 * Whether a chain is worth a row of its own.
	 *
	 * Pure. One redirect is a redirect, and every site has thousands. Two is
	 * the thing that loses a little of the link and a little of the speed every
	 * time somebody follows it.
	 *
	 * @param array $hops What hops() returned.
	 * @return bool
	 */
	public static function worth_saying( array $hops ) {
		return count( $hops ) >= 3;
	}

	/**
	 * Whether following this address comes back to where it started.
	 *
	 * Pure.
	 *
	 * @param string $start The first address.
	 * @param array  $map   Path to where it goes.
	 * @return bool
	 */
	public static function loops( $start, array $map ) {
		$seen = array();
		$at   = (string) $start;

		for ( $step = 0; $step < self::MAX_HOPS; $step++ ) {
			if ( ! isset( $map[ $at ] ) || '' === $map[ $at ] ) {
				return false;
			}

			$seen[ $at ] = true;
			$at          = (string) $map[ $at ];

			if ( isset( $seen[ $at ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * One sentence naming the pages that link to an address.
	 *
	 * Pure. A list of addresses that do not answer is an interesting fact. A
	 * list of pages to go and edit is a job somebody can finish.
	 *
	 * @param array $row     The dead row.
	 * @param array $sources Each with id and title.
	 * @return string
	 */
	public static function describe( array $row, array $sources ) {
		unset( $row );

		if ( ! $sources ) {
			return __( 'Nothing links to this any more.', 'solseo' );
		}

		$names = array();

		foreach ( array_slice( $sources, 0, self::NAMED ) as $source ) {
			$names[] = (string) $source['title'];
		}

		$rest = count( $sources ) - count( $names );

		if ( $rest > 0 ) {
			$names[] = sprintf(
				/* translators: %s: a number of pages. */
				_n( '%s more page', '%s more pages', $rest, 'solseo' ),
				number_format_i18n( $rest )
			);
		}

		$last = array_pop( $names );

		$list = $names
			/* translators: 1: a comma separated list. 2: the last item. */
			? sprintf( __( '%1$s and %2$s', 'solseo' ), implode( ', ', $names ), $last )
			: $last;

		/* translators: %s: a list of page names. */
		return sprintf( __( 'Linked to from %s.', 'solseo' ), $list );
	}

	/**
	 * The three ways out of a dead link.
	 *
	 * Pure, and it builds no addresses: the view turns a post ID into an edit
	 * link, because that is a question for WordPress and this is a question
	 * about the data.
	 *
	 * @param array $row     The dead row.
	 * @param array $sources Each with id and title.
	 * @return array
	 */
	public static function fixes( array $row, array $sources ) {
		if ( ! $sources ) {
			return array();
		}

		$path = isset( $row['path'] ) ? (string) $row['path'] : (string) $row['url'];

		return array(
			array(
				'do'      => 'edit',
				'label'   => __( 'Edit the page and fix the link', 'solseo' ),
				'post_id' => (int) $sources[0]['id'],
				'path'    => $path,
			),
			array(
				'do'      => 'unlink',
				'label'   => __( 'Take the link out and keep the words', 'solseo' ),
				'post_id' => 0,
				'path'    => $path,
			),
			array(
				'do'      => 'redirect',
				'label'   => __( 'Send this address somewhere', 'solseo' ),
				'post_id' => 0,
				'path'    => $path,
			),
		);
	}

	/**
	 * Take one link out of some markup, keeping whatever was inside it.
	 *
	 * Pure, because this rewrites somebody's page and the only safe version of
	 * that is one a test can read every branch of. What was between the tags
	 * stays, whether that is a sentence or a photograph: deleting somebody's
	 * image is not unlinking.
	 *
	 * @param string $html The content.
	 * @param string $href The address to unlink.
	 * @return string
	 */
	public static function remove_link( $html, $href ) {
		$wanted = Resolver::normalise( $href, home_url() );

		if ( '' === $wanted ) {
			return (string) $html;
		}

		return (string) preg_replace_callback(
			'#<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
			static function ( $anchor ) use ( $wanted ) {
				return Resolver::normalise( $anchor[2], home_url() ) === $wanted ? $anchor[3] : $anchor[0];
			},
			(string) $html
		);
	}

	/**
	 * Take one link out of every page that holds it.
	 *
	 * The undo is WordPress's own: changing post content stores a revision, so
	 * the page before this is one click away in the editor, and the screen says
	 * so rather than offering an undo button that would be a worse copy of one
	 * WordPress already has.
	 *
	 * @param string $path  The address to unlink.
	 * @param array  $posts Post IDs to change.
	 * @return int How many pages changed.
	 */
	public static function unlink_everywhere( $path, array $posts ) {
		$changed = 0;

		foreach ( $posts as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post || ! current_user_can( 'edit_post', (int) $post_id ) ) {
				continue;
			}

			$after = self::remove_link( $post->post_content, $path );

			if ( $after === $post->post_content ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'           => (int) $post_id,
					'post_content' => $after,
				)
			);

			++$changed;
		}

		if ( $changed ) {
			Change_Log::record(
				array(
					'what'  => 'unlink',
					'label' => sprintf(
						/* translators: %s: an address. */
						__( 'Took the dead link to %s out of the pages that held it.', 'solseo' ),
						$path
					),
					'merge' => 'unlink:' . $path,
					'count' => $changed,
				)
			);
		}

		return $changed;
	}

	/**
	 * The redirect rules, as a map of where each address goes.
	 *
	 * Pure, and it takes rows the shape `Manager::all()` returns, which is
	 * associative arrays because that call passes ARRAY_A. Reading them as
	 * objects gave a map of empty strings and no error of any kind: the chain
	 * report still had rows in it, because the crawl's own recorded redirects
	 * filled the gap, so the whole rules half was dead and nothing said so.
	 * Found on a real WordPress. This is D-53.5 wearing a different hat.
	 *
	 * @param array $rules Rows from Manager::all().
	 * @return array Path to path.
	 */
	public static function rules_to_map( array $rules ) {
		$map = array();

		foreach ( $rules as $rule ) {
			$rule = (array) $rule;

			if ( empty( $rule['enabled'] ) || 'exact' !== ( isset( $rule['match_type'] ) ? $rule['match_type'] : '' ) ) {
				continue;
			}

			$from = Resolver::normalise( isset( $rule['source'] ) ? (string) $rule['source'] : '', home_url() );
			$to   = Resolver::normalise( isset( $rule['target'] ) ? (string) $rule['target'] : '', home_url() );

			if ( '' === $from || '' === $to ) {
				continue;
			}

			$map[ $from ] = $to;
		}

		return $map;
	}

	/**
	 * Where an address goes, according to everything that knows.
	 *
	 * @return array Path to path.
	 */
	protected static function redirect_map() {
		$map = self::rules_to_map( Manager::all( 500, 0 ) );

		foreach ( Pages::moves( 500 ) as $row ) {
			$from = Resolver::normalise( (string) $row['url'], home_url() );
			$to   = Resolver::normalise( (string) $row['redirect_to'], home_url() );

			if ( '' === $from || '' === $to ) {
				continue;
			}

			$map[ $from ] = $to;
		}

		unset( $map[''] );

		return $map;
	}
}
