<?php
/**
 * Shared behaviour for the SolSEO screens.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for an admin screen.
 */
abstract class Screen {

	/**
	 * Draw the screen.
	 */
	abstract public static function render();

	/**
	 * Handle a submission before anything is printed.
	 */
	public static function load() {
	}

	/**
	 * Check the nonce and the capability for a submitted form.
	 *
	 * @param string $action Nonce action.
	 * @return bool
	 */
	protected static function submitted( $action ) {
		if ( empty( $_POST['_solseo_nonce'] ) ) {
			return false;
		}

		if ( ! current_user_can( Menu::capability() ) ) {
			return false;
		}

		return (bool) wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_solseo_nonce'] ) ), $action );
	}

	/**
	 * Print the nonce field for a form.
	 *
	 * @param string $action Nonce action.
	 */
	protected static function nonce( $action ) {
		wp_nonce_field( $action, '_solseo_nonce' );
	}

	/**
	 * Remember a message to show after the redirect.
	 *
	 * @param string $message What happened.
	 * @param string $type    Either success or error.
	 */
	protected static function remember( $message, $type = 'success' ) {
		set_transient( 'solseo_notice_' . get_current_user_id(), array( $message, $type ), 60 );
	}

	/**
	 * Print anything remembered from the last request.
	 */
	protected static function notice() {
		$stored = get_transient( 'solseo_notice_' . get_current_user_id() );

		if ( ! is_array( $stored ) ) {
			return;
		}

		delete_transient( 'solseo_notice_' . get_current_user_id() );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'error' === $stored[1] ? 'error' : 'success',
			esc_html( $stored[0] )
		);
	}

	/**
	 * Go back to a screen after handling a form.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query arguments.
	 */
	protected static function go_back( $page, array $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Print a row of tabs.
	 *
	 * @param string $page    Page slug.
	 * @param array  $tabs    Tab key to label.
	 * @param string $current Active tab.
	 */
	protected static function tabs( $page, array $tabs, $current ) {
		echo '<nav class="nav-tab-wrapper solseo-tabs">';

		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'page' => $page,
							'tab'  => $key,
						),
						admin_url( 'admin.php' )
					)
				),
				$key === $current ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';
	}

	/**
	 * Which tab is open.
	 *
	 * @param string $fallback Tab shown when none is asked for.
	 * @return string
	 */
	protected static function current_tab( $fallback ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which tab to draw.
		return isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $fallback;
	}

	/**
	 * Print the pager under a table, when there is more than one page.
	 *
	 * @param int $total    How many rows there are.
	 * @param int $per_page Rows per page.
	 * @param int $page     Current page.
	 */
	public static function pagination( $total, $per_page, $page ) {
		$pages = (int) ceil( $total / max( 1, $per_page ) );

		if ( $pages < 2 ) {
			return;
		}

		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'total'   => $pages,
				'current' => max( 1, $page ),
				'type'    => 'plain',
			)
		);

		if ( ! $links ) {
			return;
		}

		echo '<div class="tablenav bottom"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
	}

	/**
	 * Load a view.
	 *
	 * @param string $name Base name of the file in admin/views.
	 * @param array  $data Read inside the view as $data.
	 */
	public static function view( $name, array $data = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the view reads $data.
		$file = SOLSEO_PATH . 'includes/admin/views/' . $name . '.php';

		if ( is_readable( $file ) ) {
			include $file;
		}
	}
}
