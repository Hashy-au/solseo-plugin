<?php
/**
 * Six things an Australian business selling online is asked for, read once.
 *
 * Every one of them is a published rule with a page behind it, and every row
 * links to that page, because the value here is not the tick: it is knowing
 * which regulator asked, so somebody can go and read the actual words.
 *
 * It writes nothing and it fetches nothing. Everything below is read out of
 * this WordPress: the pages, the menus, the link index and the last tag
 * reading. Six checks over a site's own database is worth having; a compliance
 * opinion is not something a plugin can give and this one does not try.
 *
 * The judging is separated from the reading on purpose. `facts()` asks
 * WordPress, and every `judge_*` below is pure, so the interesting half is the
 * half a test can drive.
 *
 * @package SolSEO
 */

namespace SolSEO\Compliance;

use SolSEO\Analytics\Tag_Check;

defined( 'ABSPATH' ) || exit;

/**
 * The Australian checks.
 */
class AU_Check {

	/**
	 * The sentence at the foot of the screen, fixed by D-75.7 and printed
	 * verbatim. A test fails on any edit to it.
	 */
	const DISCLAIMER = 'These are checks against published guidance from the ACCC, the OAIC and the ATO. They are not legal advice, and passing them is not a finding of compliance.';

	/** How many published pages are read looking for wording. */
	const PAGES_READ = 300;

	/** Refund wording that argues with the consumer guarantees. */
	const REFUND_PHRASES = array(
		'no refunds',
		'no refund',
		'all sales are final',
		'all sales final',
		'no returns',
		'no return',
		'we do not offer refunds',
		'we do not give refunds',
		'exchange only',
		'exchanges only',
		'strictly no refunds',
		'no refunds or exchanges',
		'change of mind is not accepted',
		'non refundable',
		'nonrefundable',
	);

	/**
	 * Every check, in the order they are drawn.
	 *
	 * @return array Each with id, label, status, says, source and source_label.
	 */
	public static function run() {
		$facts = self::facts();

		return array(
			self::judge_privacy( $facts ),
			self::judge_contact( $facts ),
			self::judge_abn( $facts ),
			self::judge_refunds( $facts ),
			self::judge_consent( $facts ),
			self::judge_delivery( $facts ),
		);
	}

	/**
	 * What one status is called on screen.
	 *
	 * There is no "fail". A check that cannot be answered from here says so,
	 * and one that found something says it is worth a look, because the thing
	 * on the other side of this screen is a law and we are a plugin.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'pass'    => __( 'Found', 'solseo' ),
			'look'    => __( 'Worth a look', 'solseo' ),
			'unknown' => __( 'Cannot tell from here', 'solseo' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Privacy policy, published, and reachable from somewhere a visitor looks.
	 *
	 * Pure.
	 *
	 * @param array $facts What facts() read.
	 * @return array
	 */
	public static function judge_privacy( array $facts ) {
		$check = array(
			'id'           => 'privacy',
			'label'        => __( 'A privacy policy a visitor can find', 'solseo' ),
			'source'       => 'https://www.oaic.gov.au/privacy/australian-privacy-principles',
			'source_label' => __( 'OAIC, the Australian Privacy Principles', 'solseo' ),
		);

		if ( empty( $facts['privacy_page'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'No page is set as this site\'s privacy policy. WordPress has a setting for it under Settings, Privacy, and setting it is what lets everything else on the site link to the right page.', 'solseo' ),
				)
			);
		}

		if ( empty( $facts['privacy_published'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'The page set as your privacy policy is not published, so a visitor following a link to it gets nothing.', 'solseo' ),
				)
			);
		}

		if ( empty( $facts['privacy_linked'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'Your privacy policy is published and nothing on the site links to it. It is in no menu and no published page points at it, so the only way to reach it is to know the address.', 'solseo' ),
				)
			);
		}

		return array_merge(
			$check,
			array(
				'status' => 'pass',
				'says'   => __( 'Published, and linked from a menu or from a page.', 'solseo' ),
			)
		);
	}

	/**
	 * Somewhere a customer can reach a person.
	 *
	 * Pure.
	 *
	 * @param array $facts What facts() read.
	 * @return array
	 */
	public static function judge_contact( array $facts ) {
		$check = array(
			'id'           => 'contact',
			'label'        => __( 'Contact details on the site', 'solseo' ),
			'source'       => 'https://www.accc.gov.au/consumers/online-shopping',
			'source_label' => __( 'ACCC, online shopping', 'solseo' ),
		);

		if ( empty( $facts['contact_page'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'No published page looks like a contact page. A customer who cannot find a way to reach you goes to their bank instead.', 'solseo' ),
				)
			);
		}

		if ( empty( $facts['contact_details'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'There is a contact page and nothing on it reads as an email address or a phone number. A form on its own leaves a customer with no record of having written to you.', 'solseo' ),
				)
			);
		}

		return array_merge(
			$check,
			array(
				'status' => 'pass',
				'says'   => __( 'A contact page with a way to reach you on it.', 'solseo' ),
			)
		);
	}

	/**
	 * An ABN somewhere a customer can read it.
	 *
	 * Pure.
	 *
	 * @param array $facts What facts() read.
	 * @return array
	 */
	public static function judge_abn( array $facts ) {
		$check = array(
			'id'           => 'abn',
			'label'        => __( 'An ABN on the site', 'solseo' ),
			'source'       => 'https://www.ato.gov.au/businesses-and-organisations/preparing-lodging-and-paying/tax-invoices',
			'source_label' => __( 'ATO, tax invoices', 'solseo' ),
		);

		$abn = isset( $facts['abn'] ) ? (string) $facts['abn'] : '';

		if ( '' === $abn ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'No Australian Business Number was found on any published page. A tax invoice has to carry your ABN, and a customer deciding whether to buy from a site they have not heard of looks for one.', 'solseo' ),
				)
			);
		}

		return array_merge(
			$check,
			array(
				'status' => 'pass',
				'says'   => sprintf(
					/* translators: %s: an eleven digit Australian Business Number. */
					__( 'Found %s, and the check digits on it are right.', 'solseo' ),
					self::pretty_abn( $abn )
				),
			)
		);
	}

	/**
	 * Refund wording that does not argue with the consumer guarantees.
	 *
	 * Pure.
	 *
	 * @param array $facts What facts() read.
	 * @return array
	 */
	public static function judge_refunds( array $facts ) {
		$check = array(
			'id'           => 'refunds',
			'label'        => __( 'Refund wording that agrees with the law', 'solseo' ),
			'source'       => 'https://www.accc.gov.au/consumers/buying-products-and-services/consumer-rights-and-guarantees',
			'source_label' => __( 'ACCC, consumer rights and guarantees', 'solseo' ),
		);

		$found = isset( $facts['refund_wording'] ) ? (array) $facts['refund_wording'] : array();

		if ( ! $found ) {
			return array_merge(
				$check,
				array(
					'status' => 'pass',
					'says'   => __( 'Nothing on your published pages refuses a refund outright.', 'solseo' ),
				)
			);
		}

		$first = $found[0];

		return array_merge(
			$check,
			array(
				'status' => 'look',
				'says'   => sprintf(
					/* translators: 1: the wording found, such as no refunds. 2: the title of a page. */
					__( 'The words "%1$s" are on %2$s. A consumer guarantee cannot be signed away, so a blanket no is unenforceable and the ACCC treats it as misleading. Say instead what you do for change of mind, which is your choice, and that it is on top of the guarantees.', 'solseo' ),
					(string) $first['phrase'],
					(string) $first['title']
				),
				'pages'  => $found,
			)
		);
	}

	/**
	 * A way to agree, when something is measuring people.
	 *
	 * Pure.
	 *
	 * @param array $facts What facts() read.
	 * @return array
	 */
	public static function judge_consent( array $facts ) {
		$check = array(
			'id'           => 'consent',
			'label'        => __( 'A way to agree, if anything is measuring visitors', 'solseo' ),
			'source'       => 'https://www.oaic.gov.au/privacy/australian-privacy-principles',
			'source_label' => __( 'OAIC, the Australian Privacy Principles', 'solseo' ),
		);

		if ( empty( $facts['tags_read'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'unknown',
					'says'   => __( 'Nobody has run the tag check yet, so this does not know whether anything is measuring your visitors. It is under Settings, Connections.', 'solseo' ),
				)
			);
		}

		if ( empty( $facts['analytics'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'pass',
					'says'   => __( 'No analytics or advertising tag was found on the front of your site, so there is nothing here to ask about.', 'solseo' ),
				)
			);
		}

		if ( empty( $facts['consent'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'Something is measuring your visitors and nothing on the page is holding it until they agree. Whether that needs consent depends on what is collected and what your privacy policy says, and both of those are worth reading again.', 'solseo' ),
				)
			);
		}

		return array_merge(
			$check,
			array(
				'status' => 'pass',
				'says'   => sprintf(
					/* translators: %s: the name of a consent plugin. */
					__( '%s is holding the tags until a visitor agrees.', 'solseo' ),
					(string) $facts['consent']
				),
			)
		);
	}

	/**
	 * A page that says how long delivery takes.
	 *
	 * Pure.
	 *
	 * @param array $facts What facts() read.
	 * @return array
	 */
	public static function judge_delivery( array $facts ) {
		$check = array(
			'id'           => 'delivery',
			'label'        => __( 'How long delivery takes, in writing', 'solseo' ),
			'source'       => 'https://www.accc.gov.au/consumers/buying-products-and-services/problem-with-a-product-or-service-you-bought',
			'source_label' => __( 'ACCC, delivery and the consumer guarantees', 'solseo' ),
		);

		if ( empty( $facts['sells'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'unknown',
					'says'   => __( 'This site does not sell anything that gets posted, so there is nothing here to state.', 'solseo' ),
				)
			);
		}

		if ( empty( $facts['delivery_page'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'No published page looks like a delivery or shipping page. A delivery time that was never stated becomes "a reasonable time", and the customer decides what that was.', 'solseo' ),
				)
			);
		}

		if ( empty( $facts['delivery_time'] ) ) {
			return array_merge(
				$check,
				array(
					'status' => 'look',
					'says'   => __( 'There is a delivery page and nothing on it states a length of time. Say how many days, even as a range, because a stated time is the one thing that stops an argument about what was reasonable.', 'solseo' ),
				)
			);
		}

		return array_merge(
			$check,
			array(
				'status' => 'pass',
				'says'   => __( 'A delivery page that states how long it takes.', 'solseo' ),
			)
		);
	}

	/**
	 * Whether an eleven digit number is a real ABN.
	 *
	 * Pure, and it is the ATO's own algorithm: take one off the first digit,
	 * multiply each digit by its weight, and the total divides by 89. Worth
	 * doing, because a plugin that says "we found an eleven digit number" on a
	 * page with a phone number in it is a plugin nobody trusts twice.
	 *
	 * @param string $value Eleven digits, with or without spaces.
	 * @return bool
	 */
	public static function abn_valid( $value ) {
		$digits = preg_replace( '/\D/', '', (string) $value );

		if ( 11 !== strlen( (string) $digits ) ) {
			return false;
		}

		$weights = array( 10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19 );
		$total   = 0;

		foreach ( $weights as $position => $weight ) {
			$digit = (int) $digits[ $position ];

			if ( 0 === $position ) {
				--$digit;
			}

			$total += $digit * $weight;
		}

		return 0 === $total % 89;
	}

	/**
	 * An ABN the way it is written down.
	 *
	 * Pure.
	 *
	 * @param string $value Eleven digits.
	 * @return string
	 */
	public static function pretty_abn( $value ) {
		$digits = (string) preg_replace( '/\D/', '', (string) $value );

		if ( 11 !== strlen( $digits ) ) {
			return $digits;
		}

		return substr( $digits, 0, 2 ) . ' ' . substr( $digits, 2, 3 ) . ' ' . substr( $digits, 5, 3 ) . ' ' . substr( $digits, 8, 3 );
	}

	/**
	 * The first real ABN in a piece of writing.
	 *
	 * Pure.
	 *
	 * @param string $text Any words.
	 * @return string Eleven digits, or an empty string.
	 */
	public static function find_abn( $text ) {
		if ( ! preg_match_all( '/\b(\d[\d \t]{11,16}\d)\b/', (string) $text, $found ) ) {
			return '';
		}

		foreach ( $found[1] as $candidate ) {
			$digits = (string) preg_replace( '/\D/', '', $candidate );

			if ( 11 === strlen( $digits ) && self::abn_valid( $digits ) ) {
				return $digits;
			}
		}

		return '';
	}

	/**
	 * The refund wording in a piece of writing, if any.
	 *
	 * Pure.
	 *
	 * @param string $text Any words.
	 * @return string The phrase found, or an empty string.
	 */
	public static function find_refund_wording( $text ) {
		$plain = strtolower( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );

		foreach ( self::REFUND_PHRASES as $phrase ) {
			if ( false !== strpos( $plain, $phrase ) ) {
				return $phrase;
			}
		}

		return '';
	}

	/**
	 * Whether a piece of writing states a length of time.
	 *
	 * Pure.
	 *
	 * @param string $text Any words.
	 * @return bool
	 */
	public static function states_a_time( $text ) {
		$plain = strtolower( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );

		return 1 === preg_match( '/\b\d+\s*(?:to\s*\d+\s*)?(?:business |working |week)?(?:day|days|week|weeks|hour|hours)\b/', $plain );
	}

	/**
	 * Whether a title or a slug reads like one of a set of pages.
	 *
	 * Pure.
	 *
	 * @param string $title A page title.
	 * @param string $slug  A page slug.
	 * @param string $words A regular expression body, such as contact|reach.
	 * @return bool
	 */
	public static function looks_like( $title, $slug, $words ) {
		return 1 === preg_match( '/' . $words . '/i', (string) $title . ' ' . str_replace( '-', ' ', (string) $slug ) );
	}

	/**
	 * Everything the checks above need, read out of this WordPress.
	 *
	 * @return array
	 */
	public static function facts() {
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		$report  = Tag_Check::stored();

		$facts = array(
			'privacy_page'      => $privacy > 0,
			'privacy_published' => $privacy > 0 && 'publish' === get_post_status( $privacy ),
			'privacy_linked'    => $privacy > 0 && self::is_linked( $privacy ),
			'contact_page'      => false,
			'contact_details'   => false,
			'abn'               => '',
			'refund_wording'    => array(),
			'delivery_page'     => false,
			'delivery_time'     => false,
			'sells'             => solseo_has_woocommerce(),
			'tags_read'         => (int) $report['read_at'] > 0,
			'analytics'         => Tag_Check::measuring( $report ),
			'consent'           => Tag_Check::consenting( $report ),
		);

		foreach ( self::published_pages() as $page ) {
			$words = (string) $page['title'] . ' ' . (string) $page['content'];

			if ( '' === $facts['abn'] ) {
				$facts['abn'] = self::find_abn( $words );
			}

			$phrase = self::find_refund_wording( $page['content'] );

			if ( '' !== $phrase && count( $facts['refund_wording'] ) < 5 ) {
				$facts['refund_wording'][] = array(
					'id'     => (int) $page['id'],
					'title'  => (string) $page['title'],
					'phrase' => $phrase,
				);
			}

			if ( self::looks_like( $page['title'], $page['slug'], 'contact|get in touch|reach us' ) ) {
				$facts['contact_page'] = true;

				if ( preg_match( '/[\w.+-]+@[\w-]+\.[\w.]+|\b(?:\+?61|0)[\s.()-]*[2-9](?:[\s.()-]*\d){8}\b/', $page['content'] ) ) {
					$facts['contact_details'] = true;
				}
			}

			if ( self::looks_like( $page['title'], $page['slug'], 'delivery|shipping|postage|dispatch' ) ) {
				$facts['delivery_page'] = true;

				if ( self::states_a_time( $page['content'] ) ) {
					$facts['delivery_time'] = true;
				}
			}
		}

		return $facts;
	}

	/**
	 * Whether anything on the site points at a page.
	 *
	 * The link index already knows which published page links to which, so the
	 * question "can a visitor find this" is one it can answer for nothing. A
	 * footer link is usually in a menu rather than in a page, so the menus are
	 * asked as well.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected static function is_linked( $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'solseo_links';

		$incoming = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE target_id = %d", (int) $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name comes from Install::table(), which is $wpdb->prefix plus a literal, and a table name cannot be passed through prepare().

		if ( $incoming > 0 ) {
			return true;
		}

		if ( ! function_exists( 'wp_get_nav_menus' ) ) {
			return false;
		}

		foreach ( (array) wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu ) as $item ) {
				if ( isset( $item->object_id ) && (int) $item->object_id === (int) $post_id ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * The published pages worth reading for wording.
	 *
	 * Capped, because this reads content and a site can have fifty thousand
	 * pages. Pages rather than posts: a refund policy and a contact page are
	 * pages, and reading every blog post looking for the words "no refunds"
	 * would find a sentence in an article about somebody else's shop.
	 *
	 * @return array Each with id, title, slug and content.
	 */
	protected static function published_pages() {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID, post_title, post_name, post_content FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND post_type = 'page'
				ORDER BY ID ASC LIMIT %d",
				self::PAGES_READ
			),
			ARRAY_A
		);

		$pages = array();

		foreach ( (array) $rows as $row ) {
			$pages[] = array(
				'id'      => (int) $row['ID'],
				'title'   => (string) $row['post_title'],
				'slug'    => (string) $row['post_name'],
				'content' => (string) $row['post_content'],
			);
		}

		return $pages;
	}
}
