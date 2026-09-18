<?php
/**
 * Keeps a copy of what robots.txt said before this plugin arrived.
 *
 * Two things are worth recording and they are not the same thing. Most sites
 * have no robots.txt file at all and WordPress writes one on the fly, so the
 * only record possible is the text it was writing. A few sites do have a file,
 * and that one cannot be reconstructed from anything, so it is copied whole.
 *
 * Nothing here ever deletes a file.
 *
 * @package SolSEO
 */

namespace SolSEO;

use SolSEO\Frontend\Robots_Txt;

defined( 'ABSPATH' ) || exit;

/**
 * The snapshot, the restore and the offer to take a file over.
 */
class Robots_Backup {

	/** Where the snapshot is kept. */
	const OPTION = 'solseo_robots_backup';

	/** What a file is renamed to when the site asks us to manage it. */
	const MOVED = 'robots.txt.solseo-backup';

	/**
	 * Record the site as it was, once and once only.
	 *
	 * Written with add_option rather than update_option deliberately. The
	 * install steps run again on every version change, and by then our own
	 * filter is registered, so update_option would overwrite the record of the
	 * site before us with a record of the site after us, on the first update.
	 * add_option writes nothing when the row is already there, which makes
	 * "do not overwrite" a property of the call rather than a flag somebody
	 * has to remember to check.
	 */
	public static function snapshot() {
		$disk = self::file_on_disk();

		add_option(
			self::OPTION,
			array(
				'taken'      => gmdate( 'c' ),
				'version'    => SOLSEO_VERSION,
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- this is core's own filter, not ours.
				'served'     => (string) apply_filters( 'robots_txt', Robots_Txt::wordpress_default(), get_option( 'blog_public' ) ),
				'disk'       => null === $disk ? null : $disk,
				'renamed_to' => '',
			),
			'',
			false
		);
	}

	/**
	 * What was recorded, if anything.
	 *
	 * @return array|null
	 */
	public static function stored() {
		$stored = get_option( self::OPTION, null );

		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * The contents of a real robots.txt in the site's folder.
	 *
	 * @return string|null Null when there is no file.
	 */
	public static function file_on_disk() {
		$path = ABSPATH . 'robots.txt';

		if ( ! file_exists( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $path );

		return false === $contents ? '' : (string) $contents;
	}

	/**
	 * Whether the file can be moved out of the way, and what to say if not.
	 *
	 * Only a host WordPress can write to directly is offered this. Asking for
	 * FTP details inside a settings tab is worse for the reader than one plain
	 * sentence telling them what to rename.
	 *
	 * @return array Keys: possible, reason.
	 */
	public static function may_take_over() {
		if ( null === self::file_on_disk() ) {
			return array(
				'possible' => false,
				'reason'   => '',
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( 'direct' !== get_filesystem_method( array(), ABSPATH, false ) ) {
			return array(
				'possible' => false,
				'reason'   => __( 'WordPress cannot write to the folder your site lives in on this host, so it cannot move the file for you. Rename robots.txt to robots.txt.solseo-backup with your file manager or FTP client, and this screen takes over from there.', 'solseo' ),
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- asking whether WP_Filesystem could work at all, before loading it.
		if ( ! is_writable( ABSPATH ) || ! is_writable( ABSPATH . 'robots.txt' ) ) {
			return array(
				'possible' => false,
				'reason'   => __( 'The file, or the folder it is in, is read only on this server. Rename robots.txt to robots.txt.solseo-backup with your file manager or FTP client, and this screen takes over from there.', 'solseo' ),
			);
		}

		return array(
			'possible' => true,
			'reason'   => '',
		);
	}

	/**
	 * Copy the file's lines into the box, then move the file aside.
	 *
	 * The order matters. The box is filled before the move, so a move that
	 * fails leaves a site with its file where it was and its rules populated,
	 * which somebody can undo. The other order leaves a site with neither.
	 *
	 * @return string|\WP_Error What to tell the reader.
	 */
	public static function take_over() {
		$allowed = self::may_take_over();

		if ( ! $allowed['possible'] ) {
			return new \WP_Error( 'solseo_robots_locked', $allowed['reason'] ? $allowed['reason'] : __( 'There is no robots.txt file to take over.', 'solseo' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem( false, ABSPATH, false ) ) {
			return new \WP_Error( 'solseo_robots_filesystem', __( 'WordPress could not open the folder your site lives in, so nothing was changed.', 'solseo' ) );
		}

		global $wp_filesystem;

		$contents = $wp_filesystem->get_contents( ABSPATH . 'robots.txt' );

		if ( false === $contents ) {
			return new \WP_Error( 'solseo_robots_unreadable', __( 'The robots.txt file could not be read, so nothing was changed.', 'solseo' ) );
		}

		$previous = (string) get_option( 'solseo_robots_rules', '' );

		update_option( 'solseo_robots_rules', Robots_Txt::sanitise_rules( $contents ), false );

		$target = self::MOVED;

		if ( $wp_filesystem->exists( ABSPATH . $target ) ) {
			$target = self::MOVED . '-' . gmdate( 'Ymd-His' );
		}

		if ( ! $wp_filesystem->move( ABSPATH . 'robots.txt', ABSPATH . $target, false ) ) {
			update_option( 'solseo_robots_rules', $previous, false );

			return new \WP_Error( 'solseo_robots_move', __( 'The file could not be renamed, so nothing was changed.', 'solseo' ) );
		}

		$stored = self::stored();

		if ( is_array( $stored ) ) {
			$stored['renamed_to'] = $target;

			update_option( self::OPTION, $stored, false );
		}

		return sprintf(
			/* translators: %s: the new name of the file. */
			__( 'Done. The file is now called %s and its lines are in the box below. Check the preview, then save.', 'solseo' ),
			$target
		);
	}

	/**
	 * Put robots.txt back the way it was.
	 *
	 * Two different acts wear the same label, because from the reader's side
	 * they are one thing. Where a file was taken over, it goes back and the
	 * box is emptied, which is exact. Where there never was a file, the box is
	 * emptied and the text that used to be served is shown, because restoring
	 * another plugin's output is not something we can honestly claim to do.
	 *
	 * @return string|\WP_Error What to tell the reader.
	 */
	public static function restore() {
		$moved = self::moved_file();

		if ( $moved ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			if ( ! WP_Filesystem( false, ABSPATH, false ) ) {
				return new \WP_Error( 'solseo_robots_filesystem', __( 'WordPress could not open the folder your site lives in, so nothing was changed.', 'solseo' ) );
			}

			global $wp_filesystem;

			if ( $wp_filesystem->exists( ABSPATH . 'robots.txt' ) ) {
				return new \WP_Error( 'solseo_robots_exists', __( 'There is a robots.txt file there again, so the old one was left where it is rather than written over.', 'solseo' ) );
			}

			if ( ! $wp_filesystem->move( ABSPATH . $moved, ABSPATH . 'robots.txt', false ) ) {
				return new \WP_Error( 'solseo_robots_move', __( 'The file could not be moved back, so nothing was changed.', 'solseo' ) );
			}

			update_option( 'solseo_robots_rules', '', false );

			$stored = self::stored();

			if ( is_array( $stored ) ) {
				$stored['renamed_to'] = '';

				update_option( self::OPTION, $stored, false );
			}

			return __( 'The robots.txt file is back on the server and the box is empty.', 'solseo' );
		}

		update_option( 'solseo_robots_rules', '', false );

		return __( 'The box is empty. WordPress now serves what it did before SolSEO was installed.', 'solseo' );
	}

	/**
	 * The name of the file we moved aside, if it is still there.
	 *
	 * Found by looking rather than by trusting what we wrote down, because
	 * somebody may have renamed it back by hand.
	 *
	 * @return string Empty when there is nothing to put back.
	 */
	public static function moved_file() {
		$found = glob( ABSPATH . self::MOVED . '*' );

		if ( ! $found ) {
			return '';
		}

		sort( $found );

		return basename( $found[0] );
	}
}
