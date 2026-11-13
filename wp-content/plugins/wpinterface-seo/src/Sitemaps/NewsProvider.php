<?php
/**
 * Google News sitemap.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Sitemaps;

use WP_Post;
use WP_Query;
use WPInterface\SEO\Meta\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Articles published in the last 48 hours (Google News only reads those),
 * at most 1,000, in one file.
 */
final class NewsProvider implements Provider {

	/**
	 * Provider name (URL slug).
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Post types.
	 *
	 * @var string[]
	 */
	private array $post_types;

	/**
	 * Publication name.
	 *
	 * @var string
	 */
	private string $publication;

	/**
	 * Cached posts.
	 *
	 * @var WP_Post[]|null
	 */
	private ?array $posts = null;

	/**
	 * Constructor.
	 *
	 * @param string   $name        URL slug.
	 * @param string[] $post_types  Post types.
	 * @param string   $publication Publication name.
	 */
	public function __construct( string $name, array $post_types, string $publication ) {
		$this->name        = $name;
		$this->post_types  = $post_types;
		$this->publication = $publication;
	}

	/**
	 * Provider name.
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * One file (or none when nothing is recent).
	 */
	public function max_pages(): int {
		return array() === $this->posts() ? 0 : 1;
	}

	/**
	 * Entries.
	 *
	 * @param int $page Page number.
	 * @return list<array<string, mixed>>
	 */
	public function entries( int $page ): array {
		if ( $page > 1 ) {
			return array();
		}

		$language = strtolower( substr( get_locale(), 0, 2 ) );
		$entries  = array();

		foreach ( $this->posts() as $post ) {
			$url = get_permalink( $post );

			if ( ! is_string( $url ) ) {
				continue;
			}

			$entries[] = array(
				'loc'  => $url,
				'news' => array(
					'name'     => $this->publication,
					'language' => '' !== $language ? $language : 'en',
					'date'     => (string) get_post_time( 'c', true, $post ),
					'title'    => wp_strip_all_tags( get_the_title( $post ) ),
				),
			);
		}

		return $entries;
	}

	/**
	 * Newest publication time.
	 *
	 * @param int $page Page number.
	 */
	public function lastmod( int $page ): string {
		$posts = $this->posts();

		return $posts ? (string) get_post_time( 'c', true, $posts[0] ) : '';
	}

	/**
	 * Recent indexable posts, newest first.
	 *
	 * @return WP_Post[]
	 */
	private function posts(): array {
		if ( null !== $this->posts ) {
			return $this->posts;
		}

		$key   = Fields::PREFIX . 'noindex';
		$query = new WP_Query(
			array(
				'post_type'           => $this->post_types,
				'post_status'         => 'publish',
				'has_password'        => false,
				'posts_per_page'      => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Google News allows 1,000 URLs per sitemap.
				'orderby'             => 'date',
				'order'               => 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'date_query'          => array(
					array(
						'after'     => gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ),
						'column'    => 'post_date_gmt',
						'inclusive' => true,
					),
				),
				'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Honours per-post noindex.
					'relation' => 'OR',
					array(
						'key'     => $key,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => $key,
						'value'   => 'noindex',
						'compare' => '!=',
					),
				),
			)
		);

		$this->posts = array_values( array_filter( $query->posts, static fn( $post ) => $post instanceof WP_Post ) );

		return $this->posts;
	}
}
