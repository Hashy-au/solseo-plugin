<?php
/**
 * What a search engine can be told about the person who wrote this.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Four fields on the user profile screen, and nothing that WordPress already has.
 *
 * There is no second biography here. WordPress has had one since 2003, every
 * theme prints it, and a second one in our own field would disagree with it on
 * the day somebody edits one and not the other. The structured data reads the
 * one WordPress already holds.
 */
class User_Fields {

	/** Letters after the name. */
	const TITLE = '_solseo_job_title';

	/** Letters after the name. */
	const CREDENTIALS = '_solseo_credentials';

	/** What this person is an authority on. */
	const KNOWS = '_solseo_knows_about';

	/** Where else this person is, one address per line. */
	const PROFILES = '_solseo_profiles';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save' ) );
	}

	/**
	 * Draw the fields.
	 *
	 * @param \WP_User $user The user being edited.
	 */
	public static function fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		wp_nonce_field( 'solseo_user_' . $user->ID, '_solseo_user_nonce' );

		Screen::view(
			'user-fields',
			array(
				'title'       => (string) get_user_meta( $user->ID, self::TITLE, true ),
				'credentials' => (string) get_user_meta( $user->ID, self::CREDENTIALS, true ),
				'knows'       => (string) get_user_meta( $user->ID, self::KNOWS, true ),
				'profiles'    => (string) get_user_meta( $user->ID, self::PROFILES, true ),
			)
		);
	}

	/**
	 * Save them.
	 *
	 * @param int $user_id The user being saved.
	 */
	public static function save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$nonce = isset( $_POST['_solseo_user_nonce'] ) ? sanitize_key( wp_unslash( $_POST['_solseo_user_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'solseo_user_' . $user_id ) ) {
			return;
		}

		foreach ( array( self::TITLE, self::CREDENTIALS, self::KNOWS ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

			self::store( $user_id, $key, $value );
		}

		$profiles = isset( $_POST[ self::PROFILES ] ) ? wp_unslash( $_POST[ self::PROFILES ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each line is sanitised as a URL below.

		self::store( $user_id, self::PROFILES, implode( "\n", self::clean_profiles( $profiles ) ) );
	}

	/**
	 * One value per line, each of them an address.
	 *
	 * @param string $raw What was typed.
	 * @return array
	 */
	public static function clean_profiles( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$clean = array();

		foreach ( (array) $lines as $line ) {
			$url = esc_url_raw( trim( $line ) );

			if ( $url ) {
				$clean[] = $url;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Write a value, or remove the row when there is nothing to write.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param string $value   Value.
	 */
	protected static function store( $user_id, $key, $value ) {
		if ( '' === $value ) {
			delete_user_meta( $user_id, $key );

			return;
		}

		update_user_meta( $user_id, $key, $value );
	}
}
