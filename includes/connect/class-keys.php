<?php
/**
 * Credentials the site owner pastes, and what happens to them at rest.
 *
 * @package SolSEO
 */

namespace SolSEO\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * One place every third party credential is kept.
 *
 * What this protects against, exactly: a database that leaves without the
 * filesystem. A backup on an open bucket, a dump somebody can reach, a query
 * that reads `wp_options`. That is the common shape of a WordPress breach and
 * it is worth defeating.
 *
 * What it does not protect against: anybody who can also read `wp-config.php`,
 * because the secret is derived from the salts in it. Nothing here claims
 * otherwise, and the screen says the same in one line. The protection that
 * actually matters for an API key is restricting it at the provider, which is
 * why every entry in the catalogue carries the page where that is done.
 *
 * The sealing is authenticated, so a changed byte comes back as nothing rather
 * than as rubbish. On a host with neither libsodium nor OpenSSL nothing is
 * stored at all, because a credential in the clear is worse than a feature
 * that says it cannot run here.
 */
class Keys {

	/** Where the sealed credentials live. Never autoloaded. */
	const OPTION = 'solseo_keys';

	/** The Google API key, for PageSpeed Insights. */
	const GOOGLE = 'google';

	/**
	 * Every credential this plugin will hold, and where to get it.
	 *
	 * @return array
	 */
	public static function names() {
		$names = array(
			self::GOOGLE => array(
				'label'    => __( 'Google API key', 'solseo' ),
				'blurb'    => __( 'Runs the speed check. One key covers both halves of it: the lab test and the field data from real visitors to your site.', 'solseo' ),
				'where'    => 'https://developers.google.com/speed/docs/insights/v5/get-started',
				'restrict' => 'https://cloud.google.com/docs/authentication/api-keys#securing',
			),
		);

		/**
		 * Filter the credentials this plugin can hold.
		 *
		 * Each entry needs a label, a sentence, the page one is created on and
		 * the page it is restricted on.
		 *
		 * @param array $names Credential name to definition.
		 */
		return (array) apply_filters( 'solseo_keys', $names );
	}

	/**
	 * Which sealing this host can do, if any.
	 *
	 * @return string 'sodium', 'openssl', or an empty string.
	 */
	public static function sealing() {
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			return 'sodium';
		}

		if ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', (array) openssl_get_cipher_methods(), true ) ) {
			return 'openssl';
		}

		return '';
	}

	/**
	 * Seal a credential.
	 *
	 * @param string $plain  The credential.
	 * @param string $secret 32 bytes.
	 * @return string|null The sealed form, or null when it cannot be sealed.
	 */
	public static function seal( $plain, $secret ) {
		$plain  = (string) $plain;
		$secret = (string) $secret;

		if ( '' === $plain || 32 !== strlen( $secret ) ) {
			return null;
		}

		if ( 'sodium' === self::sealing() ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

			return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $secret ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- carrying bytes through an option, not hiding anything.
		}

		if ( 'openssl' === self::sealing() ) {
			$nonce  = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $secret, OPENSSL_RAW_DATA, $nonce, $tag );

			if ( false === $cipher ) {
				return null;
			}

			return 'o1:' . base64_encode( $nonce . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- as above.
		}

		return null;
	}

	/**
	 * Unseal a credential.
	 *
	 * @param string $sealed The sealed form.
	 * @param string $secret 32 bytes.
	 * @return string|null The credential, or null if it does not open.
	 */
	public static function unseal( $sealed, $secret ) {
		$sealed = (string) $sealed;
		$secret = (string) $secret;

		if ( 32 !== strlen( $secret ) || strlen( $sealed ) < 4 ) {
			return null;
		}

		$how  = substr( $sealed, 0, 3 );
		$body = base64_decode( substr( $sealed, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- as above.

		if ( ! is_string( $body ) ) {
			return null;
		}

		if ( 's1:' === $how && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			if ( strlen( $body ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return null;
			}

			$plain = sodium_crypto_secretbox_open(
				substr( $body, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $body, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				$secret
			);

			return is_string( $plain ) ? $plain : null;
		}

		if ( 'o1:' === $how && function_exists( 'openssl_decrypt' ) ) {
			if ( strlen( $body ) <= 28 ) {
				return null;
			}

			$plain = openssl_decrypt(
				substr( $body, 28 ),
				'aes-256-gcm',
				$secret,
				OPENSSL_RAW_DATA,
				substr( $body, 0, 12 ),
				substr( $body, 12, 16 )
			);

			return is_string( $plain ) ? $plain : null;
		}

		return null;
	}

	/**
	 * What a submitted field is asking for.
	 *
	 * A credential is never printed back into its own field, so the field
	 * arrives empty on every save whether or not one is stored. Reading that
	 * as "remove it" wipes a working key the first time somebody saves the
	 * screen for another reason, which is what happened the first time this
	 * screen was opened on a real WordPress.
	 *
	 * @param string $typed Exactly what was submitted, untrimmed.
	 * @return string 'keep', 'clear' or 'set'.
	 */
	public static function instruction( $typed ) {
		$typed = (string) $typed;

		if ( '' === $typed ) {
			return 'keep';
		}

		if ( '' === trim( $typed ) ) {
			return 'clear';
		}

		return 'set';
	}

	/**
	 * Save a credential, or clear it when the field is left empty.
	 *
	 * @param string $name  Which one.
	 * @param string $value The credential.
	 * @return true|\WP_Error
	 */
	public static function set( $name, $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			self::forget( $name );

			return true;
		}

		$sealed = self::seal( $value, self::secret() );

		if ( null === $sealed ) {
			return new \WP_Error(
				'solseo_cannot_seal',
				__( 'This server cannot store a credential safely, because it has neither libsodium nor OpenSSL. Nothing was saved. Your host can switch either one on.', 'solseo' )
			);
		}

		$all = self::all();

		$all[ $name ] = array(
			'sealed'      => $sealed,
			'fingerprint' => substr( hash( 'sha256', $value ), 0, 8 ),
			'hint'        => substr( $value, -4 ),
			'saved_at'    => time(),
		);

		self::put( $all );

		return true;
	}

	/**
	 * Read a credential, to make a call with it.
	 *
	 * @param string $name Which one.
	 * @return string
	 */
	public static function get( $name ) {
		$all = self::all();

		if ( empty( $all[ $name ]['sealed'] ) ) {
			return '';
		}

		$plain = self::unseal( (string) $all[ $name ]['sealed'], self::secret() );

		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Whether one is set.
	 *
	 * @param string $name Which one.
	 * @return bool
	 */
	public static function has( $name ) {
		$all = self::all();

		return ! empty( $all[ $name ]['sealed'] );
	}

	/**
	 * Remove one.
	 *
	 * @param string $name Which one.
	 */
	public static function forget( $name ) {
		$all = self::all();

		unset( $all[ $name ] );

		self::put( $all );
	}

	/**
	 * What a screen is allowed to know about a credential.
	 *
	 * @param string $name Which one.
	 * @return array Keys: set, fingerprint, hint, saved_at.
	 */
	public static function report( $name ) {
		$all = self::all();

		if ( empty( $all[ $name ]['sealed'] ) ) {
			return array(
				'set'         => false,
				'fingerprint' => '',
				'hint'        => '',
				'saved_at'    => 0,
			);
		}

		return array(
			'set'         => true,
			'fingerprint' => (string) $all[ $name ]['fingerprint'],
			'hint'        => (string) $all[ $name ]['hint'],
			'saved_at'    => (int) $all[ $name ]['saved_at'],
		);
	}

	/**
	 * Everything stored.
	 *
	 * @return array
	 */
	protected static function all() {
		$all = get_option( self::OPTION, array() );

		return is_array( $all ) ? $all : array();
	}

	/**
	 * Write everything stored.
	 *
	 * @param array $all Everything.
	 */
	protected static function put( array $all ) {
		update_option( self::OPTION, $all, false );
	}

	/**
	 * The secret the sealing uses, derived from this site's own salts.
	 *
	 * @return string 32 bytes, or an empty string when the salts are missing.
	 */
	protected static function secret() {
		$salt = '';

		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_SALT', 'LOGGED_IN_KEY' ) as $constant ) {
			if ( defined( $constant ) ) {
				$salt .= (string) constant( $constant );
			}
		}

		if ( '' === $salt ) {
			return '';
		}

		return hash( 'sha256', 'solseo-keys|' . $salt, true );
	}
}
