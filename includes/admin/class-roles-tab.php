<?php
/**
 * Who may do what.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * The permission map, shown rather than edited.
 *
 * The free plugin ships the defaults and says what they are, which is the
 * question somebody actually has: who on my site can change this. Editing the
 * map is the add-on's job, and this screen says so in one line rather than
 * drawing a control that does not work.
 */
class Roles_Tab extends Screen {

	const PAGE = 'solseo-settings';

	const TAB = 'roles';

	/**
	 * Draw the tab.
	 */
	public static function render() {
		$rows = array();

		foreach ( Capabilities::map() as $capability => $entry ) {
			$rows[ $capability ] = array(
				'label' => $entry['label'],
				'blurb' => $entry['blurb'],
				'roles' => self::names( Capabilities::roles( $capability ) ),
			);
		}

		self::view( 'settings-roles', array( 'rows' => $rows ) );
	}

	/**
	 * Role slugs as the names WordPress shows.
	 *
	 * @param array $slugs Role slugs.
	 * @return array
	 */
	protected static function names( array $slugs ) {
		$names = array();

		foreach ( $slugs as $slug ) {
			$role = get_role( $slug );

			if ( ! $role ) {
				continue;
			}

			$names[] = translate_user_role( ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ) );
		}

		return $names;
	}
}
