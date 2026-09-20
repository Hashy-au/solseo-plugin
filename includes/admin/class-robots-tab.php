<?php
/**
 * The robots.txt tab.
 *
 * It lives in its own class rather than inside the Tools screen because it is
 * the largest of the four tabs by some way, and a screen that holds four
 * unrelated things is a file nobody can change safely.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Frontend\Ai_Crawlers;
use SolSEO\Frontend\Robots_Txt;
use SolSEO\Robots_Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Shows what is served, offers a few ready made sets of rules, and can put it
 * all back the way it was.
 */
class Robots_Tab extends Screen {

	/**
	 * Handle the forms.
	 */
	public static function load() {
		if ( self::submitted( 'solseo_robots' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.
			$rules = isset( $_POST['solseo_robots_rules'] ) ? wp_unslash( $_POST['solseo_robots_rules'] ) : '';
			$rules = Robots_Txt::sanitise_rules( $rules );

			update_option( 'solseo_robots_rules', $rules, false );

			self::remember( __( 'Saved.', 'solseo' ) );
			self::go_back( Technical_Screen::PAGE, array( 'tab' => 'robots' ) );
		}

		if ( self::submitted( 'solseo_robots_restore' ) ) {
			$put_back = Robots_Backup::restore();

			if ( is_wp_error( $put_back ) ) {
				self::remember( $put_back->get_error_message(), 'error' );
			} else {
				self::remember( $put_back );
			}

			self::go_back( Technical_Screen::PAGE, array( 'tab' => 'robots' ) );
		}

		if ( self::submitted( 'solseo_robots_takeover' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read as a flag, checked above.
			$understood = ! empty( $_POST['solseo_understood'] );

			if ( ! $understood ) {
				self::remember( __( 'Tick the box to say you understand the file will be renamed.', 'solseo' ), 'error' );
				self::go_back( Technical_Screen::PAGE, array( 'tab' => 'robots' ) );
			}

			$taken = Robots_Backup::take_over();

			if ( is_wp_error( $taken ) ) {
				self::remember( $taken->get_error_message(), 'error' );
			} else {
				self::remember( $taken );
			}

			self::go_back( Technical_Screen::PAGE, array( 'tab' => 'robots' ) );
		}

		if ( self::submitted( 'solseo_robots_public' ) ) {
			update_option( 'blog_public', '0' );

			self::remember( __( 'The site is now hidden from search engines in the WordPress setting as well.', 'solseo' ) );
			self::go_back( Technical_Screen::PAGE, array( 'tab' => 'robots' ) );
		}
	}

	/**
	 * Draw the tab.
	 */
	public static function render() {
		$rules = (string) get_option( 'solseo_robots_rules', '' );

		/*
		 * Composed by the same filter chain that serves the file, so what is
		 * on the screen is what a crawler gets rather than a second opinion
		 * about it. Typing in the box asks the same code again over REST: the
		 * folding of one group into another is the whole point of composing,
		 * and joining two strings in the browser is not that.
		 */
		$served = Robots_Txt::preview( $rules );

		self::view(
			'tools-robots',
			array(
				'rules'    => $rules,
				'served'   => $served,
				'presets'  => self::presets(),
				'crawlers' => self::crawlers( $served ),
				'on_disk'  => Robots_Backup::file_on_disk(),
				'takeover' => Robots_Backup::may_take_over(),
				'backup'   => Robots_Backup::stored(),
				'public'   => '0' !== (string) get_option( 'blog_public', '1' ),
			)
		);
	}

	/**
	 * The AI crawler catalogue, with each one's state read out of the file.
	 *
	 * The state is derived rather than stored. robots.txt is what a crawler
	 * obeys, so it is the only honest place to read the answer from, and a
	 * stored copy beside it is a second truth waiting to disagree.
	 *
	 * @param string $served The composed robots.txt.
	 * @return array
	 */
	protected static function crawlers( $served ) {
		$rows = array();

		foreach ( Ai_Crawlers::all() as $token => $crawler ) {
			$rows[ $token ] = array(
				'operator' => $crawler['operator'],
				'purpose'  => $crawler['purpose'],
				'cost'     => $crawler['cost'],
				'source'   => $crawler['source'],
				'blocked'  => Ai_Crawlers::blocked( $token, $served ),
			);
		}

		return $rows;
	}

	/**
	 * The ready made sets of rules.
	 *
	 * Each one fills the box. Nothing is written until Save is pressed, which
	 * is the point: the preview above the box shows what the choice does
	 * before it is made.
	 *
	 * @return array Keyed by preset id, holding label, summary and body.
	 */
	public static function presets() {
		$path  = (string) wp_parse_url( site_url(), PHP_URL_PATH );
		$path  = untrailingslashit( $path );
		$crawl = self::ai_agents();

		$presets = array(
			'open'        => array(
				'label'   => __( 'Open to everything', 'solseo' ),
				'summary' => __( 'Search engines and AI crawlers are both welcome. This empties the box, because WordPress already writes the only two lines a normal site needs.', 'solseo' ),
				'body'    => '',
			),

			'no_ai'       => array(
				'label'   => __( 'Search engines yes, AI no', 'solseo' ),
				'summary' => __( 'Keeps the site in Google and asks the crawlers that collect text for training to stay out. Each one has to be named, because a name is the only thing a robots.txt can address.', 'solseo' ),
				'body'    => "# Search engines are welcome. Crawlers that collect text to train\n"
					. "# generative models are not.\n"
					. self::agent_lines( $crawl )
					. "Disallow: /\n",
			),

			'allow_ai'    => array(
				'label'   => __( 'Everything welcome, including AI training', 'solseo' ),
				'summary' => __( 'Says yes to the same crawlers by name, so the choice is on the record rather than left to a default somebody changes later.', 'solseo' ),
				'body'    => "# Every crawler below is allowed on purpose.\n"
					. "# The two admin lines are repeated because naming a crawler means it\n"
					. "# stops reading the User-agent: * group, including those two lines.\n"
					. self::agent_lines( $crawl )
					. 'Disallow: ' . $path . "/wp-admin/\n"
					. 'Allow: ' . $path . "/wp-admin/admin-ajax.php\n",
			),

			'maintenance' => array(
				'label'   => __( 'Maintenance mode', 'solseo' ),
				'summary' => __( 'Asks every crawler to stay off the whole site. For a site that is not ready to be found yet.', 'solseo' ),
				'body'    => "# Nothing here is to be indexed.\nUser-agent: *\nDisallow: /\n",
			),
		);

		if ( solseo_has_woocommerce() ) {
			$presets['shop'] = array(
				'label'   => __( 'Shop', 'solseo' ),
				'summary' => __( 'Keeps the addresses that multiply out of the index: every sort order, every filter, every add to cart link.', 'solseo' ),
				'body'    => self::shop_rules(),
			);
		}

		return $presets;
	}

	/**
	 * The crawlers the AI presets name.
	 *
	 * Two of these do not fetch anything. They are use controls over pages
	 * that were already fetched by the ordinary search crawler, so saying no
	 * to them costs nothing in search results. One of them is a search crawler
	 * whose owner says it does not train on what it reads, so saying no to it
	 * takes the site out of answers and saves no training use. The screen says
	 * both of those out loud beside the button.
	 *
	 * @return array
	 */
	public static function ai_agents() {
		return Ai_Crawlers::tokens();
	}

	/**
	 * One group with many agents on it, rather than many groups of one.
	 *
	 * @param array $agents Agent names.
	 * @return string
	 */
	protected static function agent_lines( array $agents ) {
		$out = '';

		foreach ( $agents as $agent ) {
			$out .= 'User-agent: ' . $agent . "\n";
		}

		return $out;
	}

	/**
	 * The shop rules, with the reason the obvious ones are missing.
	 *
	 * @return string
	 */
	protected static function shop_rules() {
		$cart     = self::shop_path( 'cart' );
		$checkout = self::shop_path( 'checkout' );
		$account  = self::shop_path( 'myaccount' );

		$body = "# The cart, checkout and account pages are deliberately NOT blocked\n"
			. "# below. Your shop software already puts a noindex tag on each of them,\n"
			. "# and a page blocked here is never fetched, so that tag is never read\n"
			. "# and the address can sit in the results with no title under it.\n";

		if ( $cart || $checkout || $account ) {
			$body .= '#   ' . trim( $cart . ' ' . $checkout . ' ' . $account ) . "\n";
		}

		$body .= "#\n"
			. "# What is worth blocking is the addresses that multiply. Each of these\n"
			. "# is the same page with a parameter on it, none of them carries a\n"
			. "# noindex, and there is no end to how many a crawler can make.\n"
			. "Disallow: /*add-to-cart=\n"
			. "Disallow: /*?orderby=\n"
			. "Disallow: /*?filter_\n"
			. "Disallow: /*?min_price=\n"
			. "Disallow: /*?max_price=\n"
			. "Disallow: /*?rating_filter=\n";

		return $body;
	}

	/**
	 * The path of one of the shop's own pages, as a crawler sees it.
	 *
	 * @param string $page Which page.
	 * @return string
	 */
	protected static function shop_path( $page ) {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}

		$url = wc_get_page_permalink( $page );

		return $url ? (string) wp_make_link_relative( $url ) : '';
	}
}
