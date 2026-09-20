<?php
/**
 * The crawl: a frontier, a budget, a pace and a place to carry on from.
 *
 * It rides the job runner, so the browser drives it a chunk at a time, the bar
 * moves because work happened, and closing the tab stops it with its place
 * saved. The frontier is the table rather than anything held in the state, so
 * "carry on later" is the same query it always was.
 *
 * Everything it fetches goes through Fetch, which is the only file in this
 * plugin allowed to open a connection to the site itself and refuses every
 * other address. See D-84.1.
 *
 * @package SolSEO
 */

namespace SolSEO\Crawl;

use SolSEO\Change_Log;
use SolSEO\Jobs\Job;
use SolSEO\Links\Checker;
use SolSEO\Links\Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * The crawl job.
 */
class Crawler extends Job {

	/** Where the last finished crawl is remembered. */
	const LAST = 'solseo_crawl_last';

	/** How many pages of the site one free crawl covers. */
	const BUDGET = 300;

	/** How many addresses found along the way it will also look at. */
	const LINK_BUDGET = 300;

	/** How many days between one finished crawl and the next. */
	const EVERY_DAYS = 30;

	/**
	 * The key this job is known by.
	 *
	 * @return string
	 */
	public static function id() {
		return 'crawl';
	}

	/**
	 * How many addresses one chunk may ask for.
	 *
	 * The pace usually stops the chunk long before this does. At the default
	 * of thirty a minute a ten second chunk covers five pages, and this is the
	 * ceiling for a site that has asked to go faster.
	 *
	 * @return int
	 */
	public static function chunk() {
		return 8;
	}

	/**
	 * How long one chunk may spend.
	 *
	 * Longer than the other jobs because most of it is spent deliberately
	 * waiting between requests, and short enough that one page timing out at
	 * fifteen seconds inside it still leaves room under a thirty second host.
	 *
	 * @return int
	 */
	public static function budget() {
		return 10;
	}

	/**
	 * What the screen says while this is running.
	 *
	 * @param array $args The arguments the job was started with.
	 * @return string
	 */
	public static function label( array $args ) {
		unset( $args );

		return __( 'Reading your pages, slowly, so your host does not notice.', 'solseo' );
	}

	/**
	 * Whether a new crawl may start today.
	 *
	 * The wait starts when a crawl finishes, not when one begins. Charging
	 * somebody a month for a crawl they stopped after four pages would make
	 * stopping the expensive choice, and the button marked Stop has to be safe
	 * to press.
	 *
	 * @return bool
	 */
	public static function may_start() {
		return time() >= self::waiting_until();
	}

	/**
	 * When the next crawl may start.
	 *
	 * @return int A timestamp, or 0 when one may start now.
	 */
	public static function waiting_until() {
		$last = (int) get_option( self::LAST, 0 );

		return $last > 0 ? $last + ( self::every_days() * DAY_IN_SECONDS ) : 0;
	}

	/**
	 * How many days apart the crawls are.
	 *
	 * @return int
	 */
	public static function every_days() {
		/**
		 * Filter the wait between crawls.
		 *
		 * This is the tier limit, not the courtesy. The add-on lowers it; the
		 * requests per minute in Fetch are clamped and cannot be lowered by
		 * anybody, because that one is about not taking a site down.
		 *
		 * @param int $days Days between one finished crawl and the next.
		 */
		return (int) max( 0, (int) apply_filters( 'solseo_crawl_every_days', self::EVERY_DAYS ) );
	}

	/**
	 * Choose the pages and fill the frontier.
	 *
	 * @param array $args Raw arguments from the request.
	 * @return array|\WP_Error
	 */
	public static function plan( array $args ) {
		unset( $args );

		if ( ! self::may_start() ) {
			return new \WP_Error(
				'solseo_crawl_too_soon',
				sprintf(
					/* translators: 1: a number of days. 2: a date. */
					__( 'A crawl runs once every %1$d days on the free plugin. The next one can start on %2$s.', 'solseo' ),
					self::every_days(),
					wp_date( 'j F Y', self::waiting_until() )
				)
			);
		}

		$chosen = Selection::choose( self::BUDGET );

		if ( ! $chosen ) {
			return new \WP_Error(
				'solseo_crawl_nothing',
				__( 'There are no published pages to crawl yet.', 'solseo' )
			);
		}

		Pages::reset();
		Pages::seed( $chosen );

		$found = self::seed_link_targets();

		return array(
			'total'  => count( $chosen ) + $found,
			'cursor' => 0,
			'args'   => array(
				'pages'   => count( $chosen ),
				'links'   => $found,
				'of'      => Selection::total_pages(),
				'started' => time(),
			),
		);
	}

	/**
	 * Fetch up to one chunk of the frontier.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function work( array $state ) {
		$started = microtime( true );
		$after   = (int) $state['cursor'];
		$rows    = static::pending( (int) $state['chunk'], $after );

		if ( ! $rows ) {
			return self::result( $after, true );
		}

		$cursor = $after;
		$done   = 0;
		$extra  = 0;

		foreach ( $rows as $row ) {
			$answer = Fetch::get( (string) $row['url'], self::method( $row ) );

			if ( ! is_wp_error( $answer ) && 429 === (int) $answer['code'] ) {
				/*
				 * The host has said stop. Retrying into that is how a crawler
				 * meant to be polite takes a shared server down, and the run is
				 * resumable, so stopping costs nothing but time.
				 */
				return array(
					'cursor'   => $cursor,
					'done'     => $done,
					'changed'  => $done,
					'failed'   => array(),
					'finished' => false,
					'halt'     => true,
					'message'  => __( 'Your host answered "too many requests", so the crawl stopped where it is. Carry on later, or slow it down on the Crawl tab first.', 'solseo' ),
				);
			}

			$result = is_wp_error( $answer )
				? self::unreachable( $answer )
				: self::from_answer( $answer );

			static::record( $row, $result );

			if ( '' !== $result['redirect_to'] && static::discover( $result['redirect_to'], $row ) ) {
				++$extra;
			}

			$cursor = (int) $row['id'];
			++$done;

			if ( self::out_of_time( $started ) ) {
				break;
			}
		}

		return array(
			'cursor'   => $cursor,
			'done'     => $done,
			'changed'  => $done,
			'failed'   => array(),
			'total'    => (int) $state['total'] + $extra,
			'finished' => 0 === static::waiting( $cursor ),
		);
	}

	/**
	 * Say what the crawl found, and start the clock on the next one.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function verify( array $state ) {
		$counts = Pages::summary();

		update_option( self::LAST, time(), false );

		Change_Log::record(
			array(
				'what'  => 'crawl',
				'label' => sprintf(
					/* translators: %s: a number of addresses. */
					__( 'Crawled %s addresses on this site.', 'solseo' ),
					number_format_i18n( (int) $state['done'] )
				),
				'merge' => 'crawl',
			)
		);

		if ( $counts['unreachable'] > 0 && 0 === $counts['ok'] ) {
			return array(
				'ok'      => false,
				'message' => __( 'Not one page answered. That usually means this server cannot make a request to itself, which some hosts block. Nothing on your site is wrong.', 'solseo' ),
				'failed'  => array(),
			);
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: 1: addresses answered. 2: addresses that did not. */
				__( '%1$s answered, %2$s did not.', 'solseo' ),
				number_format_i18n( $counts['ok'] + $counts['moved'] ),
				number_format_i18n( $counts['gone'] + $counts['broken'] + $counts['unreachable'] )
			),
			'failed'  => array(),
		);
	}

	/**
	 * Everything a page says about itself, read out of its markup.
	 *
	 * Pure. Every wrong answer in here becomes a row in a report somebody acts
	 * on, so it takes strings and returns an array and the tests can read every
	 * branch of it.
	 *
	 * @param string $body The page.
	 * @param string $url  Where it came from.
	 * @return array
	 */
	public static function read( $body, $url ) {
		$body = (string) $body;

		return array(
			'title'       => self::tag_text( $body, 'title' ),
			'description' => self::meta( $body, 'description' ),
			'robots'      => self::meta( $body, 'robots' ),
			'canonical'   => self::canonical( $body ),
			'h1'          => self::first_h1( $body ),
			'h1_count'    => self::count_h1( $body ),
			'words'       => self::count_words( $body ),
			'internal'    => self::internal_links( $body, $url ),
		);
	}

	/**
	 * Whether this address wants the whole page or only its status.
	 *
	 * A page we chose is read. An address we only found in somebody's link is
	 * a yes or no question, and asking for the body would download a site's
	 * worth of markup to learn what the status line already said.
	 *
	 * @param array $row A frontier row.
	 * @return string
	 */
	protected static function method( array $row ) {
		return isset( $row['source'] ) && 'link' === $row['source'] ? 'HEAD' : 'GET';
	}

	/**
	 * Turn a fetch into a row.
	 *
	 * @param array $answer What Fetch returned.
	 * @return array
	 */
	protected static function from_answer( array $answer ) {
		$read = self::read( $answer['body'], $answer['url'] );

		$read['status_code'] = (int) $answer['code'];
		$read['redirect_to'] = self::moved_to( $answer );
		$read['note']        = '';

		unset( $read['internal'] );

		return $read;
	}

	/**
	 * Where a redirect pointed, resolved against the address it came from.
	 *
	 * @param array $answer What Fetch returned.
	 * @return string
	 */
	protected static function moved_to( array $answer ) {
		$code = (int) $answer['code'];

		if ( $code < 300 || $code >= 400 || '' === $answer['location'] ) {
			return '';
		}

		$path = Resolver::normalise( $answer['location'], home_url() );

		if ( '' !== $path ) {
			return home_url( $path );
		}

		// Off site, which is a perfectly ordinary thing for a redirect to do.
		return (string) $answer['location'];
	}

	/**
	 * A row for an address that did not answer at all.
	 *
	 * @param \WP_Error $error What went wrong.
	 * @return array
	 */
	protected static function unreachable( $error ) {
		return array(
			'status_code' => 0,
			'redirect_to' => '',
			'canonical'   => '',
			'robots'      => '',
			'title'       => '',
			'description' => '',
			'h1'          => '',
			'h1_count'    => 0,
			'words'       => 0,
			'note'        => $error->get_error_message(),
		);
	}

	/**
	 * The next rows waiting.
	 *
	 * Swappable, because the frontier is a table and the unit tests have no
	 * database. What is under test is the chunking, the pacing and the halt,
	 * and those are the same whatever holds the rows.
	 *
	 * @param int $limit How many.
	 * @param int $after The last row id finished.
	 * @return array
	 */
	protected static function pending( $limit, $after ) {
		return Pages::waiting_rows( $limit, $after );
	}

	/**
	 * How many rows are still waiting.
	 *
	 * @param int $after The last row id finished.
	 * @return int
	 */
	protected static function waiting( $after ) {
		return Pages::waiting_count( $after );
	}

	/**
	 * Write one answer back.
	 *
	 * @param array $row    The frontier row.
	 * @param array $result What the fetch said.
	 */
	protected static function record( array $row, array $result ) {
		Pages::record( (int) $row['id'], $result );
	}

	/**
	 * Note an address the run turned up along the way.
	 *
	 * Only a redirect target, and only one on this site, because following the
	 * hops is how a chain of three is told apart from a redirect. It goes in as
	 * a waiting row with a higher id than the cursor, so the same loop picks it
	 * up without anything having to know it was added late.
	 *
	 * @param string $url  The address.
	 * @param array  $from The row it was found on.
	 * @return bool
	 */
	protected static function discover( $url, array $from ) {
		if ( ! Fetch::ours( $url ) ) {
			return false;
		}

		$counts = Pages::summary();

		if ( $counts['links'] >= self::LINK_BUDGET ) {
			return false;
		}

		return Pages::add( $url, 'link', isset( $from['post_id'] ) ? (int) $from['post_id'] : 0 );
	}

	/**
	 * Put the addresses the site links to but cannot account for in the queue.
	 *
	 * @return int How many went in.
	 */
	protected static function seed_link_targets() {
		$added = 0;

		foreach ( Checker::suspects( self::LINK_BUDGET ) as $path => $sources ) {
			if ( Pages::add( home_url( $path ), 'link', $sources ? (int) $sources[0] : 0 ) ) {
				++$added;
			}
		}

		return $added;
	}

	/**
	 * The text inside one tag.
	 *
	 * @param string $html The page.
	 * @param string $tag  Tag name.
	 * @return string
	 */
	protected static function tag_text( $html, $tag ) {
		if ( ! preg_match( '#<' . $tag . '[^>]*>(.*?)</' . $tag . '>#is', $html, $found ) ) {
			return '';
		}

		return self::plain( $found[1] );
	}

	/**
	 * One meta tag's content, whichever order the attributes were written in.
	 *
	 * @param string $html The page.
	 * @param string $name Meta name.
	 * @return string
	 */
	protected static function meta( $html, $name ) {
		if ( ! preg_match_all( '#<meta\s[^>]*>#i', $html, $tags ) ) {
			return '';
		}

		foreach ( $tags[0] as $tag ) {
			if ( ! preg_match( '#name=["\']\s*' . preg_quote( $name, '#' ) . '\s*["\']#i', $tag ) ) {
				continue;
			}

			if ( preg_match( '#content=["\']([^"\']*)["\']#i', $tag, $content ) ) {
				return self::plain( $content[1] );
			}
		}

		return '';
	}

	/**
	 * The canonical the page names for itself.
	 *
	 * @param string $html The page.
	 * @return string
	 */
	protected static function canonical( $html ) {
		if ( ! preg_match_all( '#<link\s[^>]*>#i', $html, $tags ) ) {
			return '';
		}

		foreach ( $tags[0] as $tag ) {
			if ( ! preg_match( '#rel=["\']\s*canonical\s*["\']#i', $tag ) ) {
				continue;
			}

			if ( preg_match( '#href=["\']([^"\']*)["\']#i', $tag, $href ) ) {
				return trim( $href[1] );
			}
		}

		return '';
	}

	/**
	 * The first h1, which is the one a reader and a search engine both take.
	 *
	 * @param string $html The page.
	 * @return string
	 */
	protected static function first_h1( $html ) {
		return preg_match( '#<h1[^>]*>(.*?)</h1>#is', $html, $found ) ? self::plain( $found[1] ) : '';
	}

	/**
	 * How many h1s there are, because two is a problem worth naming.
	 *
	 * @param string $html The page.
	 * @return int
	 */
	protected static function count_h1( $html ) {
		return (int) preg_match_all( '#<h1[^>]*>#i', $html );
	}

	/**
	 * How many words the page says.
	 *
	 * A crawled page is the whole document, so the menu, the cookie bar and the
	 * footer are in there. Counting those would say every page on the site has
	 * four hundred words and the thin ones would never show up. So the main
	 * region is preferred where the theme marks one, and where it does not the
	 * parts every theme repeats are taken out first.
	 *
	 * @param string $html The page.
	 * @return int
	 */
	protected static function count_words( $html ) {
		$html = (string) preg_replace( '#<(script|style|noscript|template|svg)[^>]*>.*?</\1>#is', ' ', $html );

		if ( preg_match( '#<main[^>]*>(.*?)</main>#is', $html, $main ) ) {
			$html = $main[1];
		} elseif ( preg_match( '#<article[^>]*>(.*?)</article>#is', $html, $article ) ) {
			$html = $article[1];
		} else {
			if ( preg_match( '#<body[^>]*>(.*)</body>#is', $html, $body ) ) {
				$html = $body[1];
			}

			$html = (string) preg_replace( '#<(nav|header|footer|aside|form)[^>]*>.*?</\1>#is', ' ', $html );
		}

		$text = self::plain( $html );

		return '' === $text ? 0 : count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );
	}

	/**
	 * The addresses on this site that the page points at.
	 *
	 * Normalised the same way the link index normalises them, so the two can be
	 * compared. Links going anywhere else are not collected at all: nothing in
	 * the free plugin checks those, and a list nobody reads is a list that goes
	 * quietly wrong.
	 *
	 * @param string $html The page.
	 * @param string $url  Where the page came from.
	 * @return array
	 */
	protected static function internal_links( $html, $url ) {
		unset( $url );

		if ( ! preg_match_all( '#<a\s[^>]*href=["\']([^"\']+)["\']#i', $html, $found ) ) {
			return array();
		}

		$paths = array();

		foreach ( $found[1] as $href ) {
			$path = Resolver::normalise( $href, home_url() );

			if ( '' === $path || in_array( $path, $paths, true ) ) {
				continue;
			}

			$paths[] = $path;
		}

		return $paths;
	}

	/**
	 * Markup reduced to the words in it.
	 *
	 * @param string $html Any markup.
	 * @return string
	 */
	protected static function plain( $html ) {
		$text = (string) preg_replace( '#<[^>]*>#', ' ', (string) $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/\x{00a0}/u', ' ', $text );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
