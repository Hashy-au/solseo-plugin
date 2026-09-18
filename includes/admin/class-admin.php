<?php
/**
 * Loads the admin side.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the screens and the assets they need.
 */
class Admin {

	/**
	 * Hook in.
	 */
	public static function init() {
		Menu::init();
		Metabox::init();
		Columns::init();
		Term_Fields::init();
		Dashboard_Widget::init();
		Conflict_Notice::init();
		Editor_Assets::init();

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SOLSEO_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Load the stylesheet on our screens and the editor, and the editor script
	 * where a post is being written.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		$ours = false !== strpos( $hook, 'solseo' ) || in_array( $hook, array( 'post.php', 'post-new.php', 'index.php', 'edit.php', 'term.php' ), true );

		if ( ! $ours ) {
			return;
		}

		wp_enqueue_style( 'solseo-admin', SOLSEO_URL . 'assets/css/admin.css', array(), SOLSEO_VERSION );

		if ( false !== strpos( $hook, 'solseo' ) ) {
			self::settings_assets();
			self::job_assets();
		}

		if ( 'index.php' === $hook ) {
			self::job_assets();
		}

		if ( 'edit.php' === $hook ) {
			self::list_assets();
		}

		/*
		 * The editor's own scripts are Editor_Assets' job, because which of the
		 * two surfaces loads is a decision with enough in it to be worth its
		 * own file.
		 */
	}

	/**
	 * The rich column and the editing it opens up.
	 */
	protected static function list_assets() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, solseo_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script( 'solseo-list-table', SOLSEO_URL . 'assets/js/list-table.js', array( 'wp-api-fetch' ), SOLSEO_VERSION, true );

		wp_localize_script(
			'solseo-list-table',
			'solseoList',
			array(
				'max'     => Bulk_Edit::MAX_ROWS,
				'strings' => array(
					'edit'     => __( 'Edit the titles and descriptions on this page', 'solseo' ),
					'done'     => __( 'Stop editing', 'solseo' ),
					'save'     => __( 'Save these rows', 'solseo' ),
					'cancel'   => __( 'Cancel', 'solseo' ),
					'saving'   => __( 'Saving', 'solseo' ),
					/* translators: %d: how many rows were saved. */
					'saved'    => __( '%d saved.', 'solseo' ),
					/* translators: %d: how many rows could not be saved. */
					'failed'   => __( '%d could not be saved. They are still here, with what you typed.', 'solseo' ),
					'nothing'  => __( 'Nothing was changed.', 'solseo' ),
					'wrong'    => __( 'That did not reach the server. Nothing was saved and nothing was lost.', 'solseo' ),
					'unsaved'  => __( 'You have unsaved changes in this table.', 'solseo' ),
					'noRights' => __( 'You are not allowed to edit this one.', 'solseo' ),
				),
			)
		);
	}

	/**
	 * The live previews, the preset buttons and the copy buttons.
	 */
	protected static function settings_assets() {
		wp_enqueue_script( 'solseo-settings', SOLSEO_URL . 'assets/js/settings.js', array( 'wp-api-fetch' ), SOLSEO_VERSION, true );

		wp_localize_script(
			'solseo-settings',
			'solseoSettings',
			array(
				'strings' => array(
					'copied' => __( 'Copied', 'solseo' ),
					'home'   => __( 'Home', 'solseo' ),
				),
			)
		);
	}

	/**
	 * The runner behind anything that works through a list.
	 */
	protected static function job_assets() {
		wp_enqueue_script( 'solseo-jobs', SOLSEO_URL . 'assets/js/jobs.js', array( 'wp-api-fetch' ), SOLSEO_VERSION, true );

		wp_localize_script(
			'solseo-jobs',
			'solseoJobs',
			array(
				'strings' => array(
					/* translators: 1: how many are done, 2: how many there are. */
					'progress'    => __( '%1$s of %2$s done.', 'solseo' ),
					'carryOn'     => __( 'Carry on', 'solseo' ),
					'interrupted' => __( 'This stopped part way through. Carry on picks up where it left off.', 'solseo' ),
					'failed'      => __( 'That did not work. Nothing was left half done, so it is safe to try again.', 'solseo' ),
					'expired'     => __( 'Your sign in timed out. Reload this page and carry on where it stopped.', 'solseo' ),
				),
			)
		);
	}

	/**
	 * Add a settings link on the plugins screen, and one to the Upgrade screen.
	 *
	 * The second one points at our own screen rather than out at the web,
	 * because somebody reading a row of plugin links did not ask to leave
	 * their site. It goes when the add-on is installed.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=solseo' ) ) . '">' . esc_html__( 'Settings', 'solseo' ) . '</a>'
		);

		if ( Upgrade_Screen::available() ) {
			$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . Upgrade_Screen::PAGE ) ) . '">' . esc_html__( 'Upgrade', 'solseo' ) . '</a>';
		}

		return $links;
	}
}
