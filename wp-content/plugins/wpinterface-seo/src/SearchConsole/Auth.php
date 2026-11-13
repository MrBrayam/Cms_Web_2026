<?php
/**
 * Google Search Console OAuth.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\SearchConsole;

use WP_Error;
use WPInterface\SEO\Admin\Admin;
use WPInterface\SEO\Support\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Connects to Google with OAuth 2.0 + PKCE in one of two modes:
 *
 * - "broker": WPInterface's token broker holds the Google client secret and
 *   only exchanges and refreshes tokens; it stores nothing. Google API calls
 *   go straight from this site to Google.
 * - "own": the site owner's Google Cloud OAuth client; no broker involved.
 *
 * Tokens are stored encrypted in an option that is not autoloaded.
 */
final class Auth {

	public const OPTION         = 'wpiseo_gsc_auth';
	public const INSTALL_OPTION = 'wpiseo_gsc_install';

	public const SCOPE_READ  = 'https://www.googleapis.com/auth/webmasters.readonly';
	public const SCOPE_WRITE = 'https://www.googleapis.com/auth/webmasters';

	private const GOOGLE_AUTH   = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const GOOGLE_TOKEN  = 'https://oauth2.googleapis.com/token';
	private const GOOGLE_REVOKE = 'https://oauth2.googleapis.com/revoke';

	private const PENDING_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Decrypted connection data (memoised).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data = null;

	/**
	 * Broker base URL (no trailing slash).
	 */
	public static function broker_url(): string {
		$url = defined( 'WPISEO_GSC_BROKER_URL' ) ? (string) constant( 'WPISEO_GSC_BROKER_URL' ) : 'https://api.wpinterface.com';

		/**
		 * Filters the Search Console token broker URL.
		 *
		 * @param string $url Broker base URL.
		 */
		return untrailingslashit( (string) apply_filters( 'wpiseo_gsc_broker_url', $url ) );
	}

	/**
	 * The admin page Google (own mode) and the broker return to.
	 */
	public static function callback_url(): string {
		return admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '-search-console' );
	}

	/**
	 * Stored connection data.
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {
		if ( null === $this->data ) {
			$this->data = Crypto::decrypt_array( (string) get_option( self::OPTION, '' ) );
		}

		return $this->data;
	}

	/**
	 * Whether tokens are stored.
	 */
	public function is_connected(): bool {
		return '' !== (string) ( $this->data()['refresh_token'] ?? '' );
	}

	/**
	 * Whether the stored option exists but cannot be decrypted (salts changed).
	 */
	public function is_unreadable(): bool {
		return '' !== (string) get_option( self::OPTION, '' ) && array() === $this->data();
	}

	/**
	 * Whether sitemap submission is allowed (read/write scope granted).
	 */
	public function can_write(): bool {
		return in_array( self::SCOPE_WRITE, explode( ' ', (string) ( $this->data()['scope'] ?? '' ) ), true );
	}

	/**
	 * Updates stored data.
	 *
	 * @param array<string, mixed> $changes Values to merge.
	 */
	public function update( array $changes ): void {
		$this->data = array_merge( $this->data(), $changes );
		update_option( self::OPTION, Crypto::encrypt_array( $this->data ), false );
	}

	/**
	 * Install ID used with the broker.
	 */
	public static function install_id(): string {
		$id = (string) get_option( self::INSTALL_OPTION, '' );

		if ( ! preg_match( '/^[a-f0-9-]{36}$/', $id ) ) {
			$id = wp_generate_uuid4();
			update_option( self::INSTALL_OPTION, $id, false );
		}

		return $id;
	}

	/**
	 * URL to send the user to for Google consent.
	 *
	 * @param string $mode          broker|own.
	 * @param bool   $write         Ask for the read/write scope (sitemap submission).
	 * @param string $client_id     Own mode: OAuth client ID.
	 * @param string $client_secret Own mode: OAuth client secret.
	 * @return string|WP_Error
	 */
	public function start( string $mode, bool $write, string $client_id = '', string $client_secret = '' ) {
		$state    = self::random( 32 );
		$verifier = self::random( 64 );
		$scope    = $write ? self::SCOPE_WRITE : self::SCOPE_READ;

		if ( 'own' === $mode && ( '' === trim( $client_id ) || '' === trim( $client_secret ) ) ) {
			return new WP_Error( 'wpiseo_gsc_client', __( 'Enter the OAuth client ID and secret of your Google Cloud project.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		set_transient(
			self::pending_key(),
			Crypto::encrypt_array(
				array(
					'state'         => $state,
					'verifier'      => $verifier,
					'mode'          => 'own' === $mode ? 'own' : 'broker',
					'client_id'     => trim( $client_id ),
					'client_secret' => trim( $client_secret ),
				)
			),
			self::PENDING_TTL
		);

		$challenge = self::base64url( hash( 'sha256', $verifier, true ) );

		if ( 'own' === $mode ) {
			return add_query_arg(
				array_map(
					'rawurlencode',
					array(
						'client_id'              => trim( $client_id ),
						'redirect_uri'           => self::callback_url(),
						'response_type'          => 'code',
						'scope'                  => $scope,
						'access_type'            => 'offline',
						'prompt'                 => 'consent',
						'include_granted_scopes' => 'true',
						'state'                  => $state,
						'code_challenge'         => $challenge,
						'code_challenge_method'  => 'S256',
					)
				),
				self::GOOGLE_AUTH
			);
		}

		return add_query_arg(
			array_map(
				'rawurlencode',
				array(
					'return_url'     => self::callback_url(),
					'state'          => $state,
					'code_challenge' => $challenge,
					'scope'          => $write ? 'write' : 'readonly',
					'install'        => self::install_id(),
				)
			),
			self::broker_url() . '/gsc/start'
		);
	}

	/**
	 * Finishes the OAuth flow: checks the state and exchanges the code.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State returned by Google or the broker.
	 * @return true|WP_Error
	 */
	public function finish( string $code, string $state ) {
		$pending = Crypto::decrypt_array( (string) get_transient( self::pending_key() ) );
		delete_transient( self::pending_key() );

		if ( empty( $pending['state'] ) || ! hash_equals( (string) $pending['state'], $state ) ) {
			return new WP_Error( 'wpiseo_gsc_state', __( 'The connection request expired or did not start on this site. Please try again.', 'wpinterface-seo' ) );
		}

		if ( 'own' === $pending['mode'] ) {
			$tokens = $this->google_token(
				array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'client_id'     => $pending['client_id'],
					'client_secret' => $pending['client_secret'],
					'redirect_uri'  => self::callback_url(),
					'code_verifier' => $pending['verifier'],
				)
			);
		} else {
			$tokens = $this->broker(
				'/gsc/token',
				array(
					'code'          => $code,
					'code_verifier' => $pending['verifier'],
					'install'       => self::install_id(),
				)
			);
		}

		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		if ( empty( $tokens['refresh_token'] ) || empty( $tokens['access_token'] ) ) {
			return new WP_Error( 'wpiseo_gsc_tokens', __( 'Google did not return the expected tokens. Please try again.', 'wpinterface-seo' ) );
		}

		$this->data = array();
		$this->update(
			array(
				'mode'           => $pending['mode'],
				'access_token'   => (string) $tokens['access_token'],
				'refresh_token'  => (string) $tokens['refresh_token'],
				'expires_at'     => time() + (int) ( $tokens['expires_in'] ?? 3600 ),
				'scope'          => (string) ( $tokens['scope'] ?? '' ),
				'install_secret' => (string) ( $tokens['install_secret'] ?? '' ),
				'client_id'      => (string) $pending['client_id'],
				'client_secret'  => (string) $pending['client_secret'],
				'property'       => '',
				'connected_at'   => time(),
				'connected_by'   => get_current_user_id(),
				'error'          => '',
			)
		);

		return true;
	}

	/**
	 * A valid access token, refreshed when needed.
	 *
	 * @param bool $force Refresh even if the current token has not expired.
	 * @return string|WP_Error
	 */
	public function access_token( bool $force = false ) {
		$data = $this->data();

		if ( ! $this->is_connected() ) {
			return new WP_Error( 'wpiseo_gsc_disconnected', __( 'Search Console is not connected.', 'wpinterface-seo' ) );
		}

		if ( ! $force && ! empty( $data['access_token'] ) && (int) ( $data['expires_at'] ?? 0 ) > time() + 60 ) {
			return (string) $data['access_token'];
		}

		if ( 'own' === ( $data['mode'] ?? '' ) ) {
			$tokens = $this->google_token(
				array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $data['refresh_token'],
					'client_id'     => $data['client_id'] ?? '',
					'client_secret' => $data['client_secret'] ?? '',
				)
			);
		} else {
			$install   = self::install_id();
			$timestamp = (string) time();
			$tokens    = $this->broker(
				'/gsc/refresh',
				array(
					'refresh_token' => (string) $data['refresh_token'],
					'install'       => $install,
					'timestamp'     => $timestamp,
					'signature'     => hash_hmac( 'sha256', $install . '|' . $timestamp . '|' . $data['refresh_token'], (string) ( $data['install_secret'] ?? '' ) ),
				)
			);
		}

		if ( is_wp_error( $tokens ) ) {
			// A revoked or expired grant needs a new connection.
			if ( 'invalid_grant' === $tokens->get_error_code() ) {
				$this->update( array( 'error' => 'reconnect' ) );
			}

			return $tokens;
		}

		$this->update(
			array(
				'access_token' => (string) ( $tokens['access_token'] ?? '' ),
				'expires_at'   => time() + (int) ( $tokens['expires_in'] ?? 3600 ),
				'error'        => '',
			)
		);

		return (string) ( $tokens['access_token'] ?? '' );
	}

	/**
	 * Revokes the grant with Google and deletes the stored tokens.
	 */
	public function disconnect(): void {
		$token = (string) ( $this->data()['refresh_token'] ?? '' );

		if ( '' !== $token ) {
			wp_remote_post(
				self::GOOGLE_REVOKE,
				array(
					'timeout' => 10,
					'body'    => array( 'token' => $token ),
				)
			);
		}

		delete_option( self::OPTION );
		$this->data = array();
	}

	/**
	 * Google token endpoint (own mode).
	 *
	 * @param array<string, mixed> $body Form body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function google_token( array $body ) {
		$response = wp_remote_post(
			self::GOOGLE_TOKEN,
			array(
				'timeout' => 20,
				'body'    => $body,
			)
		);

		return self::decode( $response );
	}

	/**
	 * Broker request.
	 *
	 * @param string               $path Path.
	 * @param array<string, mixed> $body JSON body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function broker( string $path, array $body ) {
		$response = wp_remote_post(
			self::broker_url() . $path,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'WPInterface-SEO/' . WPISEO_VERSION . '; ' . home_url( '/' ),
				),
				'body'    => (string) wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wpiseo_gsc_broker', __( 'The connection service could not be reached. Cached data is still shown; try again later.', 'wpinterface-seo' ) );
		}

		return self::decode( $response );
	}

	/**
	 * Decodes a token response; Google errors become WP_Error with Google's code.
	 *
	 * @param array<string, mixed>|WP_Error $response HTTP response.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
			return $data;
		}

		$error = is_array( $data ) ? (string) ( $data['error'] ?? 'http_' . $code ) : 'http_' . $code;

		return new WP_Error(
			$error,
			is_array( $data ) && ! empty( $data['error_description'] )
				? (string) $data['error_description']
				/* translators: %s: error code. */
				: sprintf( __( 'Google sign-in failed (%s).', 'wpinterface-seo' ), $error )
		);
	}

	/**
	 * Per-user pending-flow transient key.
	 */
	private static function pending_key(): string {
		return 'wpiseo_gsc_pending_' . get_current_user_id();
	}

	/**
	 * URL-safe random string.
	 *
	 * @param int $bytes Random bytes.
	 */
	private static function random( int $bytes ): string {
		return self::base64url( random_bytes( $bytes ) );
	}

	/**
	 * Base64url without padding.
	 *
	 * @param string $raw Bytes.
	 */
	private static function base64url( string $raw ): string {
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PKCE encoding.
	}
}
