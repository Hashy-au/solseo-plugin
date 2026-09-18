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

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_script( 'solseo-editor', SOLSEO_URL . 'assets/js/editor.js', array( 'wp-api-fetch' ), SOLSEO_VERSION, true );

		wp_localize_script(
			'solseo-editor',
			'solseoEditor',
			array(
				'strings' => array(
					'analysing' => __( 'Working it out', 'solseo' ),
					'failed'    => __( 'The score could not be worked out just now.', 'solseo' ),
					/* translators: 1: width of the text in pixels, 2: the width search results allow. */
					'pixels'    => __( '%1$d of %2$d pixels', 'solseo' ),
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
