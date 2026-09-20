<?php
/**
 * Who may do which of the things this plugin does.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Four permissions, on top of the ones WordPress already enforces.
 *
 * These narrow, they never widen. Somebody who cannot edit a post cannot edit
 * its SEO fields whatever this map says, because WordPress is asked first and
 * this is asked second. A map that could grant is a privilege escalation with a
 * settings screen in front of it.
 *
 * The free plugin ships the defaults and shows them. Changing them is the
 * add-on's job, through the filter below.
 */
class Capabilities {

	/** Editing the SEO fields on something you can already edit. */
	const EDIT_META = 'edit_meta';

	/** The redirect rules and the log of addresses that found nothing. */
	const MANAGE_REDIRECTS = 'manage_redirects';

	/** The settings screens. */
	const MANAGE_SETTINGS = 'manage_settings';

	/** Import, bulk alt text, and anything else that works through a list. */
	const RUN_TOOLS = 'run_tools';

	/**
	 * What each permission is, and who has it by default.
	 *
	 * @return array
	 */
	public static function map() {
		return array(
			self::EDIT_META        => array(
				'label' => __( 'Edit the SEO fields on a post', 'solseo' ),
				'blurb' => __( 'The title, description, keyword and robots rules on something this person can already edit.', 'solseo' ),
				'roles' => array( 'administrator', 'editor', 'author', 'contributor' ),
			),
			self::MANAGE_REDIRECTS => array(
				'label' => __( 'Manage redirects', 'solseo' ),
				'blurb' => __( 'Add, change and remove redirect rules, and read the log of addresses that found nothing.', 'solseo' ),
				'roles' => array( 'administrator', 'editor' ),
			),
			self::RUN_TOOLS        => array(
				'label' => __( 'Run the tools', 'solseo' ),
				'blurb' => __( 'Import from another plugin, write alt text in bulk, and anything else that works through a list.', 'solseo' ),
				'roles' => array( 'administrator' ),
			),
			self::MANAGE_SETTINGS  => array(
				'label' => __( 'Change the settings', 'solseo' ),
				'blurb' => __( 'Titles and meta, sitemaps, robots.txt, the writing profile and everything under Settings.', 'solseo' ),
				'roles' => array( 'administrator' ),
			),
		);
	}

	/**
	 * Which roles hold a permission.
	 *
	 * @param string $capability One of the constants.
	 * @return array Role slugs.
	 */
	public static function roles( $capability ) {
		$map = self::map();

		if ( ! isset( $map[ $capability ] ) ) {
			return array();
		}

		/**
		 * Filter which roles hold one SolSEO permission.
		 *
		 * This is how the add-on lets a site change the map. It narrows and it
		 * never widens: whatever this returns, WordPress is still asked first.
		 *
		 * @param array  $roles      Role slugs.
		 * @param string $capability The permission.
		 */
		$roles = (array) apply_filters( 'solseo_capability_roles', $map[ $capability ]['roles'], $capability );

		return array_values( array_filter( array_map( 'strval', $roles ) ) );
	}

	/**
	 * Whether the current user holds a permission.
	 *
	 * An administrator always does. A site that has locked itself out of its
	 * own settings screen has no way back in, and that is a support ticket
	 * nobody can answer without database access.
	 *
	 * @param string   $capability One of the constants.
	 * @param int|null $user_id    User, or the current one.
	 * @return bool
	 */
	public static function can( $capability, $user_id = null ) {
		$user = null === $user_id ? wp_get_current_user() : get_userdata( $user_id );

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) || user_can( $user, 'manage_options' ) ) {
			return true;
		}

		$allowed = self::roles( $capability );

		foreach ( (array) $user->roles as $role ) {
			if ( in_array( $role, $allowed, true ) ) {
				return true;
			}
		}

		return false;
	}
}
