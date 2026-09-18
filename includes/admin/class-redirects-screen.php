<?php
/**
 * Redirect rules and the log of addresses that found nothing.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Options;
use SolSEO\Redirects\Log;
use SolSEO\Redirects\Manager;

defined( 'ABSPATH' ) || exit;

/**
 * The Redirects screen.
 */
class Redirects_Screen extends Screen {

	const PAGE     = 'solseo-redirects';
	const PER_PAGE = 50;

	/**
	 * Handle the forms and the row actions.
	 */
	public static function load() {
		if ( self::submitted( 'solseo_redirect_add' ) ) {
			self::save_rule();
		}

		if ( self::submitted( 'solseo_log_settings' ) ) {
			self::save_log_settings();
		}

		self::handle_row_action();
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		$tabs = array(
			'rules'     => __( 'Rules', 'solseo' ),
			'not_found' => __( 'Found nothing', 'solseo' ),
		);

		$tab = self::current_tab( 'rules' );
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'rules';

		self::tabs( self::PAGE, $tabs, $tab );

		$page   = max( 1, (int) filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT ) );
		$offset = ( $page - 1 ) * self::PER_PAGE;

		if ( 'rules' === $tab ) {
			self::view(
				'redirects-rules',
				array(
					'rules'    => Manager::all( self::PER_PAGE, $offset ),
					'total'    => Manager::count(),
					'page'     => $page,
					'per_page' => self::PER_PAGE,
					'prefill'  => self::prefill(),
				)
			);

			return;
		}

		self::view(
			'redirects-log',
			array(
				'entries'  => Log::recent( self::PER_PAGE, $offset ),
				'total'    => Log::count(),
				'page'     => $page,
				'per_page' => self::PER_PAGE,
				'options'  => Options::all(),
			)
		);
	}

	/**
	 * The address a new rule should start from, when one was passed in.
	 *
	 * @return string
	 */
	protected static function prefill() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filling in a form field, not acting.
		return isset( $_GET['source'] ) ? Manager::normalise( sanitize_text_field( wp_unslash( $_GET['source'] ) ) ) : '';
	}

	/**
	 * Add or update a rule.
	 */
	protected static function save_rule() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Manager::save sanitises every field.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();
		$id    = isset( $input['id'] ) ? (int) $input['id'] : 0;

		$saved = Manager::save(
			array(
				'source'      => isset( $input['source'] ) ? sanitize_text_field( $input['source'] ) : '',
				'target'      => isset( $input['target'] ) ? esc_url_raw( $input['target'] ) : '',
				'status_code' => isset( $input['status_code'] ) ? (int) $input['status_code'] : 301,
				'match_type'  => isset( $input['match_type'] ) ? sanitize_key( $input['match_type'] ) : 'exact',
				'enabled'     => ! empty( $input['enabled'] ),
			),
			$id
		);

		if ( false === $saved ) {
			self::remember( __( 'A rule needs an address to match and somewhere to send it.', 'solseo' ), 'error' );
		} else {
			self::remember( __( 'Redirect saved.', 'solseo' ) );
		}

		self::go_back( self::PAGE );
	}

	/**
	 * Save the log settings.
	 */
	protected static function save_log_settings() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast below.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

		Options::update(
			array(
				'log_not_found'      => ! empty( $input['log_not_found'] ),
				'log_retention_days' => max( 1, min( 365, (int) ( isset( $input['log_retention_days'] ) ? $input['log_retention_days'] : 30 ) ) ),
			)
		);

		self::remember( __( 'Saved.', 'solseo' ) );
		self::go_back( self::PAGE, array( 'tab' => 'not_found' ) );
	}

	/**
	 * Delete a rule, a log entry, or empty the log.
	 */
	protected static function handle_row_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked for each action below.
		$action = isset( $_GET['solseo_action'] ) ? sanitize_key( wp_unslash( $_GET['solseo_action'] ) ) : '';

		if ( ! $action || ! current_user_can( Menu::capability() ) ) {
			return;
		}

		$id    = (int) filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT );
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'solseo_' . $action . '_' . $id ) ) {
			return;
		}

		if ( 'delete_rule' === $action ) {
			Manager::delete( $id );
			self::remember( __( 'Redirect deleted.', 'solseo' ) );
			self::go_back( self::PAGE );
		}

		if ( 'delete_miss' === $action ) {
			Log::delete( $id );
			self::remember( __( 'Entry removed.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => 'not_found' ) );
		}

		if ( 'clear_log' === $action ) {
			Log::clear();
			self::remember( __( 'Log emptied.', 'solseo' ) );
			self::go_back( self::PAGE, array( 'tab' => 'not_found' ) );
		}
	}
}
