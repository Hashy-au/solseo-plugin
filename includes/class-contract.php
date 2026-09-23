<?php
/**
 * The grading contract, in code.
 *
 * `docs/API-CONTRACT.md`, "How a page is graded", is the written contract that
 * binds three implementations: the worker's audit rules, the hub's own page
 * analysers, and this plugin's page scoring. This file is the plugin's copy of
 * the numbers and the rules in it, and tests/test-contract.php fails if the two
 * disagree.
 *
 * WHY A COPY RATHER THAN A SHARED LIBRARY. Three lanes in two languages on
 * three hosts, one of which is a WordPress plugin that ships with no build
 * step and no Composer. There is nothing all three can import, so the contract
 * document is the shared thing and each lane proves it still agrees.
 *
 * WHY IT MATTERS HERE MORE THAN ANYWHERE. A page scored in wp-admin and the
 * same page scored by the hub have to reach the same verdict, or the number is
 * meaningless in both places. Thomas put it plainly on 2026-09-21: "We need a
 * consistent ranking across the products." D-207.6, D-207.10.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * The shared thresholds and the shared judgements about markup.
 */
class Contract {

	/**
	 * R6. Fewer main content words than this is thin.
	 *
	 * @var int
	 */
	const THIN_CONTENT_WORDS = 300;

	/**
	 * R6. A response slower than this, end of body, not first byte.
	 *
	 * @var int
	 */
	const SLOW_RESPONSE_MS = 800;

	/**
	 * R6. Title length, brand suffix included.
	 *
	 * @var int
	 */
	const TITLE_MIN = 50;

	/**
	 * R6. Longer than this is cut off in a result.
	 *
	 * @var int
	 */
	const TITLE_MAX = 60;

	/**
	 * R6. Meta description length.
	 *
	 * Was 160 in the worker and 120 to 155 in the hub for weeks, so a
	 * description of 158 characters passed one lane and failed the other. The
	 * contract resolved it to 120 to 155 and this is that number.
	 *
	 * @var int
	 */
	const DESCRIPTION_MIN = 120;

	/**
	 * R6. Longer than this is cut off in a result.
	 *
	 * @var int
	 */
	const DESCRIPTION_MAX = 155;

	/**
	 * R2. Does this image have no alt text at all?
	 *
	 * An absent alt attribute is the fault. `alt=""` is not: it is the correct
	 * markup for a decoration, and it is what our own advice text tells people
	 * to write. Reporting it as missing is what made our own audit fire on 37
	 * of 43 pages for one header logo sitting beside a text wordmark, while the
	 * fix text on the same finding told the reader to do exactly what the page
	 * had already done.
	 *
	 * @param array $image An image from Content::images().
	 * @return bool
	 */
	public static function alt_is_missing( array $image ) {
		return ! isset( $image['alt'] ) || null === $image['alt'];
	}

	/**
	 * R2. Is this an empty alt that nothing on the page explains?
	 *
	 * Three markers count as an explanation, and each is a deliberate statement
	 * that the image carries no information a reader needs:
	 *
	 *   - aria-hidden="true"
	 *   - role="presentation" or role="none"
	 *   - sitting inside a link or button whose own text names the destination
	 *
	 * An empty alt with none of them is worth a quiet note rather than a fault,
	 * because a product photo with a blank alt is a real loss and a spacer
	 * image with one is not, and from outside the page they look the same.
	 *
	 * @param array $image An image from Content::images().
	 * @return bool
	 */
	public static function alt_is_unexplained_empty( array $image ) {
		if ( self::alt_is_missing( $image ) ) {
			return false;
		}

		if ( '' !== trim( (string) $image['alt'] ) ) {
			return false;
		}

		return empty( $image['aria_hidden'] )
			&& empty( $image['presentational'] )
			&& empty( $image['linked_with_text'] );
	}

	/**
	 * The alt text as a string, whether it was absent or empty.
	 *
	 * For every caller that wants to read the words rather than judge the
	 * markup. Added because alt became nullable and `'' !== $image['alt']` is
	 * true for null, which would have handed null to a string function.
	 *
	 * @param array $image An image from Content::images().
	 * @return string
	 */
	public static function alt_text( array $image ) {
		return isset( $image['alt'] ) ? (string) $image['alt'] : '';
	}
}
