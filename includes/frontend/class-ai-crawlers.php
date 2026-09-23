<?php
/**
 * The crawlers that read a site for something other than a search result.
 *
 * @package SolSEO
 */

namespace SolSEO\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * A catalogue, not a policy.
 *
 * Every entry names a crawler that is real and currently documented, and says
 * what blocking it actually costs, because the honest answer differs and the
 * difference is the whole decision. A crawler that only trains a model sends no
 * traffic back and costs nothing to refuse. A crawler that fetches pages to
 * answer a question with a link to you is a referrer, and blocking it is
 * turning away readers.
 *
 * Nothing here is stored. robots.txt is the single source of truth for what a
 * site allows, and the screen reads the state back out of the file it is about
 * to serve rather than keeping a second opinion beside it.
 */
class Ai_Crawlers {

	/**
	 * Every crawler this plugin knows about.
	 *
	 * @return array Keyed by user agent token.
	 */
	public static function all() {
		return array(
			'GPTBot'             => array(
				'operator' => 'OpenAI',
				'purpose'  => __( 'Collects text to train and improve models.', 'solseo' ),
				'cost'     => __( 'Nothing you can measure. It sends no visitors, and blocking it does not affect ChatGPT search or Google.', 'solseo' ),
				'source'   => 'https://platform.openai.com/docs/bots',
			),
			'OAI-SearchBot'      => array(
				'operator' => 'OpenAI',
				'purpose'  => __( 'Indexes pages so ChatGPT can show and link to them in search.', 'solseo' ),
				'cost'     => __( 'Visitors. This is the one that puts a link to you in front of somebody asking about what you sell.', 'solseo' ),
				'source'   => 'https://platform.openai.com/docs/bots',
			),
			'ChatGPT-User'       => array(
				'operator' => 'OpenAI',
				'purpose'  => __( 'Fetches one page when somebody asks ChatGPT about it.', 'solseo' ),
				'cost'     => __( 'A person who named your page and will be told it could not be read.', 'solseo' ),
				'source'   => 'https://platform.openai.com/docs/bots',
			),
			'ClaudeBot'          => array(
				'operator' => 'Anthropic',
				'purpose'  => __( 'Collects text to train models.', 'solseo' ),
				'cost'     => __( 'Nothing you can measure. It sends no visitors.', 'solseo' ),
				'source'   => 'https://support.anthropic.com/en/articles/8896518',
			),
			'Claude-SearchBot'   => array(
				'operator' => 'Anthropic',
				'purpose'  => __( 'Indexes pages so Claude can cite and link to them.', 'solseo' ),
				'cost'     => __( 'Citations, and the visitors that follow them.', 'solseo' ),
				'source'   => 'https://support.anthropic.com/en/articles/8896518',
			),
			'Google-Extended'    => array(
				'operator' => 'Google',
				'purpose'  => __( 'Controls whether your pages train Gemini and ground its answers.', 'solseo' ),
				'cost'     => __( 'Nothing in Google Search. This token has no effect on how you rank or whether you are indexed, which is the thing people most often get wrong about it.', 'solseo' ),
				'source'   => 'https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers',
			),
			'CCBot'              => array(
				'operator' => 'Common Crawl',
				'purpose'  => __( 'Builds the free public archive that many models are trained from.', 'solseo' ),
				'cost'     => __( 'Nothing directly. Blocking it removes you from an archive used by researchers as well as by model builders.', 'solseo' ),
				'source'   => 'https://commoncrawl.org/ccbot',
			),
			'PerplexityBot'      => array(
				'operator' => 'Perplexity',
				'purpose'  => __( 'Indexes pages so Perplexity can answer with a citation to you.', 'solseo' ),
				'cost'     => __( 'Citations, and the visitors that follow them.', 'solseo' ),
				'source'   => 'https://docs.perplexity.ai/guides/bots',
			),

			/*
			 * ByteDance publishes no crawler documentation in the way the
			 * others here do. The address is the one its own user agent string
			 * carries, which is the only thing ByteDance says about this
			 * crawler in public. The page it used to point at,
			 * developer.bytedance.com/docs/bytespider, is a 404 and has been
			 * for some time, which is why a dead link went out in 2.0.0.
			 */
			'Bytespider'         => array(
				'operator' => 'ByteDance',
				'purpose'  => __( 'Collects text to train models.', 'solseo' ),
				'cost'     => __( 'Nothing you can measure. It sends no visitors and is known for crawling heavily.', 'solseo' ),
				'source'   => 'https://zhanzhang.toutiao.com/',
			),
			'Applebot-Extended'  => array(
				'operator' => 'Apple',
				'purpose'  => __( 'Controls whether your pages train Apple Intelligence.', 'solseo' ),
				'cost'     => __( 'Nothing in Siri or Spotlight. Those use Applebot, which is a different crawler and is not affected.', 'solseo' ),
				'source'   => 'https://support.apple.com/en-au/119829',
			),
			'meta-externalagent' => array(
				'operator' => 'Meta',
				'purpose'  => __( 'Collects text to train models.', 'solseo' ),
				'cost'     => __( 'Nothing you can measure. It sends no visitors.', 'solseo' ),
				'source'   => 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers',
			),
			'Amazonbot'          => array(
				'operator' => 'Amazon',
				'purpose'  => __( 'Indexes pages so Alexa can answer questions from them.', 'solseo' ),
				'cost'     => __( 'Answers that mention you on Alexa devices.', 'solseo' ),
				'source'   => 'https://developer.amazon.com/amazonbot',
			),
		);
	}

	/**
	 * Just the tokens, which is what a robots.txt addresses.
	 *
	 * @return array
	 */
	public static function tokens() {
		return array_keys( self::all() );
	}

	/**
	 * Whether a crawler is blocked by what is about to be served.
	 *
	 * Read out of the composed file rather than out of a setting of our own,
	 * so the screen can never disagree with what a crawler will actually get.
	 *
	 * @param string $token  User agent token.
	 * @param string $served The whole robots.txt as it will be served.
	 * @return bool
	 */
	public static function blocked( $token, $served ) {
		$group = self::group_for( $token, $served );

		if ( '' === $group ) {
			return false;
		}

		return (bool) preg_match( '/^\s*Disallow:\s*\/\s*$/mi', $group );
	}

	/**
	 * The lines that apply to one named crawler.
	 *
	 * A named group stands alone: a crawler that finds its own name stops
	 * reading the group for everybody else, which is the part of robots.txt
	 * most often got wrong.
	 *
	 * @param string $token  User agent token.
	 * @param string $served The whole robots.txt.
	 * @return string
	 */
	protected static function group_for( $token, $served ) {
		$lines   = preg_split( '/\r\n|\r|\n/', (string) $served );
		$in      = false;
		$group   = array();
		$pattern = '/^\s*User-agent:\s*' . preg_quote( $token, '/' ) . '\s*$/i';

		foreach ( (array) $lines as $line ) {
			if ( preg_match( '/^\s*User-agent:/i', $line ) ) {
				$in = (bool) preg_match( $pattern, $line );

				continue;
			}

			if ( $in && '' !== trim( $line ) ) {
				$group[] = $line;
			}
		}

		return implode( "\n", $group );
	}
}
