<?php
/**
 * What the paid add-on adds, for people who do not have it.
 *
 * WHERE THIS SITS INSIDE THE DIRECTORY RULES. Guideline 5 allows the upsell:
 * "attempting to upsell the user on ad-hoc products and features is
 * acceptable, provided it falls within bounds of guideline 11". Guideline 11
 * draws those bounds: prompts "must be limited in scope and used sparingly, be
 * that contextually or only on the plugin's setting page", and it says in the
 * same breath that developers are "welcome and encouraged to include links to
 * their own sites".
 *
 * So this is one screen, in this plugin's own menu, reached by somebody who
 * clicked it. There are no admin notices anywhere in this plugin, nothing on a
 * screen belonging to WordPress or to anybody else, and no banner on the
 * screens that do the work.
 *
 * IT DESCRIBES THE ADD-ON RATHER THAN SHOWING IT SWITCHED OFF. Nothing in this
 * plugin is locked, which is the other half of guideline 5: a disabled control
 * with a padlock on it is trialware however it is worded.
 *
 * IT IS STATIC AND LOCAL. Nothing here reads from our server, because a plugin
 * may not contact one without consent (guideline 7), and the links carry no
 * campaign or referral parameters, because guideline 11 is explicit that
 * tracking referrals through the dashboard is not allowed.
 *
 * It takes itself off the menu once the add-on is installed. Anybody who does
 * not want it there at all can drop it with the solseo_admin_screens filter,
 * the same one the add-on uses to add its own screen.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The Upgrade screen.
 */
class Upgrade_Screen extends Screen {

	const PAGE = 'solseo-upgrade';

	/** Where the add-on is described in full. */
	const URL = 'https://solseo.com.au/pro';

	/** The plugin's own page. */
	const HOME = 'https://solseo.com.au/plugin';

	/**
	 * Whether the screen belongs in the menu.
	 *
	 * @return bool
	 */
	public static function available() {
		return ! defined( 'SOLSEO_PRO_VERSION' );
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::view(
			'upgrade',
			array(
				'features' => self::features(),
				'url'      => self::URL,
				'home'     => self::HOME,
			)
		);
	}

	/**
	 * What the add-on adds today.
	 *
	 * Three entries, and they are the three things it does. A list that ran
	 * ahead of the build would be the kind of dishonesty guideline 9 is about,
	 * and the person reading it is one click from finding out.
	 *
	 * @return array
	 */
	protected static function features() {
		return array(
			array(
				'title' => __( 'A score for every phrase', 'solseo' ),
				'text'  => __( 'The score here is for the focus keyword. The add-on scores every phrase in the Other phrases field as well, each with its own number and its own list of what is missing. A supporting phrase is judged on the checks it can meet: a page has one address and one title opening, so a second phrase is not marked down for not owning them.', 'solseo' ),
			),
			array(
				'title' => __( 'Site health findings, with fixes that report back', 'solseo' ),
				'text'  => __( 'The findings from your SolSEO health check appear on this dashboard, in the three bands the service itself uses, each with the pages that have it. Where this plugin can fix one, there is a button that fixes it here. Every fix is checked against what your page now says and sent back, so the score moves in seconds rather than at the next check. This one needs a SolSEO account on a paid plan, because the findings come from there.', 'solseo' ),
			),
			array(
				'title' => __( 'Tracked positions where you write', 'solseo' ),
				'text'  => __( 'The keywords tracked for this site appear in the editor beside the page they are aimed at, with the position, the change since the last check and a thirty day line. This one needs a SolSEO account on a paid plan, because the positions come from there.', 'solseo' ),
			),
		);
	}
}
