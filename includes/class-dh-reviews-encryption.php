<?php
/**
 * Encryption and decryption helpers.
 *
 * Provides symmetric encryption for OAuth tokens and API credentials
 * stored in wp_options. Uses wp_salt('auth') as the encryption key.
 * See SPEC.md Section 8 (Security) for storage requirements.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encryption
 *
 * Handles encryption and decryption of sensitive values.
 */
class Encryption {

	/**
	 * Encrypt a value for storage.
	 *
	 * @param string $value Plain text value to encrypt.
	 * @return string Encrypted value (base64 encoded).
	 */
	public static function encrypt( string $value ): string {
		// Stub: encrypt using openssl_encrypt with wp_salt('auth').
		return '';
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @param string $value Encrypted value (base64 encoded).
	 * @return string|false Decrypted plain text or false on failure.
	 */
	public static function decrypt( string $value ): string|false {
		// Stub: decrypt using openssl_decrypt with wp_salt('auth').
		return false;
	}
}
