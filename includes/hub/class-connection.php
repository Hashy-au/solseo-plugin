<?php
/**
 * The link between this site and a SolSEO account.
 *
 * Nothing in this file runs until somebody pastes a pairing code. Until then
 * the plugin makes no outbound request of any kind.
 *
 * @package SolSEO
 */

namespace SolSEO\Hub;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the pairing, refreshes it and reports the site's facts.
 */
class Connection {

	const OPTION = 'solseo_hub';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'solseo_hub_sync', array( __CLASS__, 'sync' ) );

		if ( self::is_connected() && ! wp_next_scheduled( 'solseo_hub_sync' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'twicedaily', 'solseo_hub_sync' );
		}
	}

	/**
	 * Whether this site is connected.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return '' !== (string) self::get( 'key' );
	}

	/**
	 * One value from the stored connection.
	 *
	 * @param string $field Field name.
	 * @return mixed
	 */
	public static function get( $field ) {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) && isset( $stored[ $field ] ) ? $stored[ $field ] : '';
	}

	/**
	 * The whole stored connection, without the key.
	 *
	 * @return array
	 */
	public static function summary() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		unset( $stored['key'] );

		return $stored;
	}

	/**
	 * Redeem a pairing code.
	 *
	 * @param string $code Code from the dashboard.
	 * @return true|\WP_Error
	 */
	public static function connect( $code ) {
		$response = Client::pair( $code );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['pairing_key'] ) ) {
			return new \WP_Error( 'solseo_pair_failed', __( 'SolSEO did not return a key for that code.', 'solseo' ) );
		}

		update_option(
			self::OPTION,
			array(
				'key'       => (string) $response['pairing_key'],
				'site_id'   => isset( $response['site_id'] ) ? (int) $response['site_id'] : 0,
				'url'       => isset( $response['hub_url'] ) ? esc_url_raw( $response['hub_url'] ) : Client::BASE_URL,
				'paired_at' => current_time( 'mysql', true ),
			),
			false
		);

		if ( ! wp_next_scheduled( 'solseo_hub_sync' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'twicedaily', 'solseo_hub_sync' );
		}

		self::sync();

		return true;
	}

	/**
	 * Forget the connection.
	 */
	public static function disconnect() {
		delete_option( self::OPTION );
		delete_transient( 'solseo_hub_overview' );
		wp_clear_scheduled_hook( 'solseo_hub_sync' );

		/**
		 * Fires when this site stops being connected, however that happened.
		 *
		 * A refused key disconnects the site inside sync(), so this fires for a
		 * revoked connection as well as for somebody pressing the button. An add-on
		 * holding cached account data drops it here.
		 *
		 * @since 1.3.0
		 */
		do_action( 'solseo_hub_disconnected' );
	}

	/**
	 * Send this site's facts and read back what the account is entitled to.
	 */
	public static function sync() {
		if ( ! self::is_connected() ) {
			return;
		}

		$stored = get_option( self::OPTION, array() );
		$plan   = Client::entitlement();

		if ( is_wp_error( $plan ) ) {
			$stored['status']     = $plan->get_error_message();
			$stored['checked_at'] = current_time( 'mysql', true );

			if ( 401 === (int) $plan->get_error_data( 'status' ) ) {
				self::disconnect();

				return;
			}

			update_option( self::OPTION, $stored, false );

			return;
		}

		$stored['plan']       = isset( $plan['plan_code'] ) ? $plan['plan_code'] : '';
		$stored['level']      = isset( $plan['level'] ) ? $plan['level'] : 'free';
		$stored['status']     = '';
		$stored['checked_at'] = current_time( 'mysql', true );

		update_option( self::OPTION, $stored, false );

		Client::facts( self::facts() );

		$overview = Client::overview();

		if ( is_array( $overview ) ) {
			/*
			 * THE MONITOR READING RIDES ALONG WITH THE OVERVIEW, because the
			 * dashboard widget draws both and a widget must not make a request
			 * while somebody waits for a screen. Both were measured before the
			 * question was asked, so the only cost of asking twice a day is two
			 * requests twice a day.
			 */
			$monitor = Client::monitor();

			if ( is_array( $monitor ) ) {
				$overview['monitor'] = $monitor;
			}

			set_transient( 'solseo_hub_overview', $overview, 6 * HOUR_IN_SECONDS );
		}

		/**
		 * Fires after a successful sync with the SolSEO service.
		 *
		 * What is handed over is summary(), the stored connection WITHOUT the
		 * key. The key is a credential and a hook is a public address: anything
		 * subscribing to this can send what it is given anywhere it likes.
		 *
		 * @since 1.3.0
		 *
		 * @param array $connection site_id, url, plan, level, status, checked_at, paired_at.
		 */
		do_action( 'solseo_hub_synced', self::summary() );
	}

	/**
	 * A masked form of the key, so support can tell two sites apart.
	 *
	 * @return string
	 */
	public static function key_hint() {
		$key = (string) self::get( 'key' );

		if ( '' === $key ) {
			return '';
		}

		return substr( hash( 'sha256', $key ), 0, 8 );
	}

	/**
	 * What this site is running.
	 *
	 * @return array
	 */
	protected static function facts() {
		global $wp_version;

		$counts = array();

		foreach ( solseo_post_types() as $post_type ) {
			$count = wp_count_posts( $post_type );

			if ( $count && isset( $count->publish ) ) {
				$counts[ $post_type ] = (int) $count->publish;
			}
		}

		return array(
			'plugin_version'      => SOLSEO_VERSION,
			'wp_version'          => $wp_version,
			'wc_version'          => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'php_version'         => PHP_VERSION,
			'seo_plugin_detected' => 'solseo',
			'site_kit_connected'  => false,
			'post_counts'         => $counts,
			'permalink_structure' => get_option( 'permalink_structure' ),
			'home_url'            => home_url(),
			'timezone'            => wp_timezone_string(),
		);
	}
}
