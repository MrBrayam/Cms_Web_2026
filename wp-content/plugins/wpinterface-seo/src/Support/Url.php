<?php
/**
 * URL helpers.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Pagination and URL utilities that do not depend on the current request.
 */
final class Url {

	/**
	 * URL of page N of an archive (`/page/N/` or `?paged=N`).
	 *
	 * @param string $url  Archive URL (page 1).
	 * @param int    $page Page number.
	 */
	public static function paged( string $url, int $page ): string {
		global $wp_rewrite;

		if ( $page < 2 || '' === $url ) {
			return $url;
		}

		if ( $wp_rewrite->using_permalinks() && false === strpos( $url, '?' ) ) {
			return user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $page, 'paged' );
		}

		return add_query_arg( 'paged', $page, $url );
	}

	/**
	 * URL of page N of a post split with `<!--nextpage-->`.
	 *
	 * @param string $url  Post permalink.
	 * @param int    $page Page number.
	 */
	public static function post_page( string $url, int $page ): string {
		global $wp_rewrite;

		if ( $page < 2 || '' === $url ) {
			return $url;
		}

		if ( $wp_rewrite->using_permalinks() && false === strpos( $url, '?' ) ) {
			return user_trailingslashit( trailingslashit( $url ) . $page, 'single_paged' );
		}

		return add_query_arg( 'page', $page, $url );
	}

	/**
	 * Whether a URL is absolute http(s).
	 *
	 * @param string $url URL.
	 */
	public static function is_http( string $url ): bool {
		return (bool) preg_match( '#^https?://#i', $url );
	}

	/**
	 * Makes a root-relative URL absolute against home_url().
	 *
	 * @param string $url URL.
	 */
	public static function absolute( string $url ): string {
		if ( '' === $url || self::is_http( $url ) ) {
			return $url;
		}

		if ( 0 === strpos( $url, '//' ) ) {
			return set_url_scheme( $url );
		}

		if ( 0 === strpos( $url, '/' ) ) {
			return home_url( $url );
		}

		return $url;
	}
}
