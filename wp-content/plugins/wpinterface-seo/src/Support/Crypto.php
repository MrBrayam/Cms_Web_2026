<?php
/**
 * Encryption for secrets stored in the database.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts OAuth tokens and API keys at rest with libsodium (bundled with
 * WordPress as sodium_compat). The key is derived from the site's salts, so a
 * database dump alone does not reveal the secrets. When the salts change the
 * values can no longer be read and the user is asked to reconnect.
 */
final class Crypto {

	private const PREFIX = 'wpiseo1:';

	/**
	 * Encrypts a string. Returns '' for ''.
	 *
	 * @param string $plain Plain text.
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}

		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );

		return self::PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext storage.
	}

	/**
	 * Decrypts a value from encrypt(). Returns '' when it cannot be decrypted.
	 *
	 * @param string $stored Stored value.
	 */
	public static function decrypt( string $stored ): string {
		if ( 0 !== strpos( $stored, self::PREFIX ) ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary ciphertext storage.

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		try {
			$plain = sodium_crypto_secretbox_open(
				substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				self::key()
			);
		} catch ( \Throwable $error ) {
			return '';
		}

		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Encrypts an array as JSON.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public static function encrypt_array( array $data ): string {
		return self::encrypt( (string) wp_json_encode( $data ) );
	}

	/**
	 * Decrypts an array stored with encrypt_array(); [] when unreadable.
	 *
	 * @param string $stored Stored value.
	 * @return array<string, mixed>
	 */
	public static function decrypt_array( string $stored ): array {
		$data = json_decode( self::decrypt( $stored ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Masks a secret for display, e.g. "sk-…a1b2".
	 *
	 * @param string $secret Secret.
	 */
	public static function mask( string $secret ): string {
		if ( strlen( $secret ) <= 8 ) {
			return '' === $secret ? '' : '••••';
		}

		return substr( $secret, 0, 3 ) . '…' . substr( $secret, -4 );
	}

	/**
	 * 32-byte key derived from the site's salts.
	 */
	private static function key(): string {
		return hash( 'sha256', wp_salt( 'secure_auth' ) . '|wpinterface-seo', true );
	}
}
