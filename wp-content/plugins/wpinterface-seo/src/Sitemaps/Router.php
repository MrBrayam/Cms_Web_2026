<?php
/**
 * Sitemap URLs and rewrite rules.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Sitemaps;

defined( 'ABSPATH' ) || exit;

/**
 * Maps `/sitemap.xml`, `/{name}-sitemap{N}.xml` and `/sitemap.xsl` to query vars.
 *
 * Without pretty permalinks the same files are served from
 * `/?wpiseo_sitemap=index` etc.
 */
final class Router {

	public const QUERY_VAR = 'wpiseo_sitemap';
	public const PAGE_VAR  = 'wpiseo_sitemap_page';

	/**
	 * Registers rewrite rules.
	 */
	public static function add_rules(): void {
		add_rewrite_rule( '^sitemap(?:_index)?\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top' );
		add_rewrite_rule( '^sitemap\.xsl$', 'index.php?' . self::QUERY_VAR . '=xsl', 'top' );
		add_rewrite_rule( '^([a-z0-9_-]+?)-sitemap([0-9]+)?\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]&' . self::PAGE_VAR . '=$matches[2]', 'top' );
	}

	/**
	 * Adds the query vars.
	 *
	 * @param list<string> $vars Public query vars.
	 * @return list<string>
	 */
	public static function query_vars( $vars ): array {
		$vars   = is_array( $vars ) ? $vars : array();
		$vars[] = self::QUERY_VAR;
		$vars[] = self::PAGE_VAR;

		return $vars;
	}

	/**
	 * URL of the sitemap index.
	 */
	public static function index_url(): string {
		return self::pretty() ? home_url( '/sitemap.xml' ) : add_query_arg( self::QUERY_VAR, 'index', home_url( '/' ) );
	}

	/**
	 * URL of the XSL stylesheet.
	 */
	public static function stylesheet_url(): string {
		return self::pretty() ? home_url( '/sitemap.xsl' ) : add_query_arg( self::QUERY_VAR, 'xsl', home_url( '/' ) );
	}

	/**
	 * URL of one sitemap file.
	 *
	 * @param string $name Provider name.
	 * @param int    $page Page number.
	 */
	public static function url( string $name, int $page = 1 ): string {
		if ( self::pretty() ) {
			return home_url( '/' . $name . '-sitemap' . ( $page > 1 ? $page : '' ) . '.xml' );
		}

		$args = array( self::QUERY_VAR => $name );

		if ( $page > 1 ) {
			$args[ self::PAGE_VAR ] = $page;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Whether pretty permalinks are on.
	 */
	private static function pretty(): bool {
		global $wp_rewrite;

		return $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks();
	}
}
