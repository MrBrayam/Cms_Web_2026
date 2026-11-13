<?php
/**
 * URL normalisation for redirect matching.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Turns request URLs and user-entered sources into a canonical form so
 * `/Old-Page/`, `old-page` and `https://example.org/old-page` all match.
 *
 * Paths are relative to the site's home path (subdirectory installs work),
 * percent-decoded, lowercased, without a trailing slash (except `/`).
 * Query strings are sorted so parameter order does not matter.
 */
final class Normalizer {

	/**
	 * Path and query of a URL, relative to the home path.
	 *
	 * @param string $url URL or path.
	 * @return array{path: string, query: string}
	 */
	public static function parse( string $url ): array {
		$url   = trim( $url );
		$parts = wp_parse_url( '' === $url ? '/' : $url );

		if ( ! is_array( $parts ) ) {
			return array(
				'path'  => '/',
				'query' => '',
			);
		}

		$path      = (string) ( $parts['path'] ?? '/' );
		$home_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

		// A bare "old-page" without a leading slash is a path, not a host.
		if ( ! isset( $parts['host'] ) && '' !== $path && '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		if ( '' !== $home_path && 0 === stripos( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) );
		} elseif ( '' !== $home_path && 0 === strcasecmp( $path, $home_path ) ) {
			$path = '/';
		}

		$path = rawurldecode( $path );
		$path = (string) preg_replace( '#/{2,}#', '/', $path );
		$path = mb_strtolower( $path );
		$path = '/' === $path ? '/' : '/' . trim( $path, '/' );

		return array(
			'path'  => $path,
			'query' => self::sort_query( (string) ( $parts['query'] ?? '' ) ),
		);
	}

	/**
	 * The current request.
	 *
	 * @return array{path: string, query: string}
	 */
	public static function from_request(): array {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return self::parse( '' !== $uri ? $uri : '/' );
	}

	/**
	 * Lookup key stored as the rule source for exact rules.
	 *
	 * @param array{path: string, query: string} $parsed       Parsed URL.
	 * @param bool                               $ignore_query Whether the query string is ignored.
	 */
	public static function key( array $parsed, bool $ignore_query ): string {
		return $ignore_query || '' === $parsed['query'] ? $parsed['path'] : $parsed['path'] . '?' . $parsed['query'];
	}

	/**
	 * Path plus query (for regex matching and logging).
	 *
	 * @param array{path: string, query: string} $parsed Parsed URL.
	 */
	public static function full( array $parsed ): string {
		return '' === $parsed['query'] ? $parsed['path'] : $parsed['path'] . '?' . $parsed['query'];
	}

	/**
	 * Hash used for the indexed lookup.
	 *
	 * @param string $key Lookup key.
	 */
	public static function hash( string $key ): string {
		return md5( $key );
	}

	/**
	 * Absolute URL for a redirect target (relative targets resolve against home).
	 *
	 * @param string $target Target as entered.
	 */
	public static function target_url( string $target ): string {
		$target = trim( $target );

		if ( '' === $target ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $target ) ) {
			return $target;
		}

		if ( 0 === strpos( $target, '//' ) ) {
			return set_url_scheme( $target );
		}

		return home_url( '/' . ltrim( $target, '/' ) );
	}

	/**
	 * Whether a URL points to this site.
	 *
	 * @param string $url URL.
	 */
	public static function is_internal( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return ! is_string( $host ) || 0 === strcasecmp( $host, (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * Sorts query parameters for order-independent comparison.
	 *
	 * @param string $query Query string.
	 */
	private static function sort_query( string $query ): string {
		if ( '' === $query ) {
			return '';
		}

		$pairs = array_filter( explode( '&', $query ), static fn( string $pair ): bool => '' !== $pair );
		sort( $pairs );

		return implode( '&', $pairs );
	}
}
