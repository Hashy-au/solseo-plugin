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
		Term_Fields::init();
		User_Fields::init();
		Conflict_Notice::init();
		Editor_Assets::init();

		// The admin side of anything that is not core. Columns and the widget live here.
		\SolSEO\Modules::boot( \SolSEO\Modules::ADMIN );

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
		/*
		 * REGISTERED EVERYWHERE, ENQUEUED WHERE IT IS NEEDED.
		 *
		 * Registering prints nothing and costs an array entry. It is here
		 * because the add-on and the packs name `solseo-admin` as a dependency
		 * of their own stylesheets, and WordPress drops an item whose
		 * dependency is not registered: the moment this stopped enqueueing on
		 * every screen, a handle that used to exist by accident would have
		 * stopped existing, and pro.css would have vanished with it rather than
		 * failing loudly. Registering separates "this file exists" from "this
		 * screen needs it", which is what the two facts actually are.
		 */
		wp_register_style( 'solseo-admin', SOLSEO_URL . 'assets/css/admin.css', array(), SOLSEO_VERSION );

		if ( ! self::draws_something( $hook ) ) {
			return;
		}

		wp_enqueue_style( 'solseo-admin' );

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
	 * Whether this plugin puts anything on the screen being drawn.
	 *
	 * A SCREEN NAME IS NOT A REASON TO LOAD A STYLESHEET. This used to answer
	 * yes to five screen names outright, so the stylesheet went out on the
	 * Dashboard of a site whose users had switched every panel off, on the
	 * posts list of a post type this plugin does not manage, and on the term
	 * screen of a taxonomy it does not touch. The plugins directory reads that
	 * as restyling somebody else's admin, and it is the same thing said in
	 * bytes: a file nobody on that screen has a use for.
	 *
	 * So each name is asked the question its own screen can answer. The answer
	 * is the same one the markup gives: the metabox and the sidebar panel are
	 * offered on the post types in solseo_post_types(), the SEO column on the
	 * same list, the term fields on the taxonomies in solseo_taxonomies(), and
	 * the Dashboard widgets only if at least one of them was registered on this
	 * request, which is where the capability check already lives.
	 *
	 * Takes its facts as arguments so the decision can be tested without a
	 * WordPress. The caller below is the only thing that gathers them.
	 *
	 * @param string $hook   Current admin page.
	 * @param array  $screen What the screen says it is: post_type, taxonomy,
	 *                       and widget for a registered dashboard panel.
	 * @param array  $types  Post types this plugin manages.
	 * @param array  $taxes  Taxonomies this plugin manages.
	 * @return bool
	 */
	public static function needs_assets( $hook, array $screen, array $types, array $taxes ) {
		if ( false !== strpos( (string) $hook, 'solseo' ) ) {
			return true;
		}

		if ( 'index.php' === $hook ) {
			return ! empty( $screen['widget'] );
		}

		if ( in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return in_array( isset( $screen['post_type'] ) ? $screen['post_type'] : '', $types, true );
		}

		if ( 'term.php' === $hook ) {
			return in_array( isset( $screen['taxonomy'] ) ? $screen['taxonomy'] : '', $taxes, true );
		}

		return false;
	}

	/**
	 * Gather what needs_assets() judges, from the screen being drawn.
	 *
	 * @param string $hook Current admin page.
	 * @return bool
	 */
	protected static function draws_something( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return self::needs_assets(
			$hook,
			array(
				'post_type' => is_object( $screen ) && isset( $screen->post_type ) ? (string) $screen->post_type : '',
				'taxonomy'  => is_object( $screen ) && isset( $screen->taxonomy ) ? (string) $screen->taxonomy : '',
				'widget'    => Dashboard_Widget::on_dashboard(),
			),
			solseo_post_types(),
			solseo_taxonomies()
		);
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
