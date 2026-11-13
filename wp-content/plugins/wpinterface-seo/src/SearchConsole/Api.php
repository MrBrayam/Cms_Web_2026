<?php
/**
 * Google Search Console API client.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\SearchConsole;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the Search Console API directly from this site with the stored
 * access token. Used by the background sync and on-demand actions only;
 * report screens read the local tables.
 */
final class Api {

	private const BASE       = 'https://www.googleapis.com/webmasters/v3';
	private const INSPECTION = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

	/**
	 * OAuth.
	 *
	 * @var Auth
	 */
	private Auth $auth;

	/**
	 * Constructor.
	 *
	 * @param Auth $auth OAuth.
	 */
	public function __construct( Auth $auth ) {
		$this->auth = $auth;
	}

	/**
	 * Properties the account can read.
	 *
	 * @return list<array{url: string, permission: string}>|WP_Error
	 */
	public function sites() {
		$data = $this->request( 'GET', self::BASE . '/sites' );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$sites = array();

		foreach ( (array) ( $data['siteEntry'] ?? array() ) as $entry ) {
			if ( isset( $entry['siteUrl'] ) && 'siteUnverifiedUser' !== ( $entry['permissionLevel'] ?? '' ) ) {
				$sites[] = array(
					'url'        => (string) $entry['siteUrl'],
					'permission' => (string) ( $entry['permissionLevel'] ?? '' ),
				);
			}
		}

		return $sites;
	}

	/**
	 * Search Analytics rows.
	 *
	 * @param string               $property Property (https://example.com/ or sc-domain:example.com).
	 * @param array<string, mixed> $body     Query body.
	 * @return list<array<string, mixed>>|WP_Error
	 */
	public function search_analytics( string $property, array $body ) {
		$data = $this->request( 'POST', self::BASE . '/sites/' . rawurlencode( $property ) . '/searchAnalytics/query', $body );

		return is_wp_error( $data ) ? $data : array_values( (array) ( $data['rows'] ?? array() ) );
	}

	/**
	 * Submitted sitemaps and their status.
	 *
	 * @param string $property Property.
	 * @return list<array<string, mixed>>|WP_Error
	 */
	public function sitemaps( string $property ) {
		$data = $this->request( 'GET', self::BASE . '/sites/' . rawurlencode( $property ) . '/sitemaps' );

		return is_wp_error( $data ) ? $data : array_values( (array) ( $data['sitemap'] ?? array() ) );
	}

	/**
	 * Submits a sitemap (needs the read/write scope).
	 *
	 * @param string $property Property.
	 * @param string $url      Sitemap URL.
	 * @return true|WP_Error
	 */
	public function submit_sitemap( string $property, string $url ) {
		$data = $this->request( 'PUT', self::BASE . '/sites/' . rawurlencode( $property ) . '/sitemaps/' . rawurlencode( $url ) );

		return is_wp_error( $data ) ? $data : true;
	}

	/**
	 * URL Inspection result.
	 *
	 * @param string $property Property.
	 * @param string $url      URL to inspect.
	 * @return array<string, mixed>|WP_Error
	 */
	public function inspect( string $property, string $url ) {
		$data = $this->request(
			'POST',
			self::INSPECTION,
			array(
				'inspectionUrl' => $url,
				'siteUrl'       => $property,
				'languageCode'  => str_replace( '_', '-', get_user_locale() ),
			)
		);

		return is_wp_error( $data ) ? $data : (array) ( $data['inspectionResult'] ?? array() );
	}

	/**
	 * Authenticated JSON request; refreshes the token once on 401.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $url    URL.
	 * @param array<string, mixed>|null $body   JSON body.
	 * @param bool                      $retry  Whether a 401 may be retried.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $method, string $url, ?array $body = null, bool $retry = true ) {
		$token = $this->auth->access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $body );
		} elseif ( 'GET' !== $method && 'DELETE' !== $method ) {
			// Google answers 411 (Length Required) to a PUT/POST without a length, e.g. sitemap submission.
			$args['headers']['Content-Length'] = '0';
			$args['body']                      = '';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code && $retry ) {
			$refreshed = $this->auth->access_token( true );

			return is_wp_error( $refreshed ) ? $refreshed : $this->request( $method, $url, $body, false );
		}

		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = '' === $raw ? array() : json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';

			return new WP_Error(
				'wpiseo_gsc_api_' . $code,
				'' !== $message
					? $message
					/* translators: %d: HTTP status code. */
					: sprintf( __( 'Search Console returned an error (%d).', 'wpinterface-seo' ), $code ),
				array( 'status' => 403 === $code ? 403 : 502 )
			);
		}

		return is_array( $data ) ? $data : array();
	}
}
