<?php
/**
 * Sitemap provider contract.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Sitemaps;

defined( 'ABSPATH' ) || exit;

/**
 * One family of sitemap files, e.g. `post-sitemap.xml`, `post-sitemap2.xml`.
 */
interface Provider {

	/**
	 * URL slug: post type, taxonomy or `author`.
	 */
	public function name(): string;

	/**
	 * Number of sitemap files (0 when there is nothing to list).
	 */
	public function max_pages(): int;

	/**
	 * URL entries for one file.
	 *
	 * @param int $page Page number (1-based).
	 * @return list<array{loc: string, lastmod?: string, images?: list<string>}>
	 */
	public function entries( int $page ): array;

	/**
	 * Most recent modification of the URLs in one file (W3C datetime), '' if unknown.
	 *
	 * @param int $page Page number (1-based).
	 */
	public function lastmod( int $page ): string;
}
