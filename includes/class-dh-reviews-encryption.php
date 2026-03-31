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
 * Handles encryption and decryption of sensitive values using AES-256-CBC.
 * The cipher key is derived by hashing wp_salt('auth') to a fixed 32-byte key.
 * The IV is prepended to the ciphertext before base64 encoding.
 */
class Encryption {

	/**
	 * Cipher algorithm.
	 *
	 * @var string
	 */
	const CIPHER = 'AES-256-CBC';

	/**
	 * Encrypt a plain text value for storage.
	 *
	 * Generates a fresh random IV on every call, prepends it to the
	 * ciphertext, and returns the whole thing base64-encoded.
	 *
	 * @param string $value Plain text value to encrypt.
	 * @return string Base64-encoded iv + ciphertext, or empty string on failure.
	 */
	public static function encrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$key    = hash( 'sha256', wp_salt( 'auth' ), true ); // 32 raw bytes.
		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$iv     = openssl_random_pseudo_bytes( $iv_len );

		$ciphertext = openssl_encrypt( $value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored encrypted value.
	 *
	 * Expects the format produced by encrypt(): base64( iv + ciphertext ).
	 * Returns empty string on any failure rather than false, so callers can
	 * always do a simple empty() check.
	 *
	 * @param string $value Base64-encoded iv + ciphertext.
	 * @return string Decrypted plain text, or empty string on failure.
	 */
	public static function decrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return '';
		}

		$key    = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv_len = openssl_cipher_iv_length( self::CIPHER );

		if ( strlen( $decoded ) <= $iv_len ) {
			return '';
		}

		$iv         = substr( $decoded, 0, $iv_len );
		$ciphertext = substr( $decoded, $iv_len );

		$plain = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}
}
