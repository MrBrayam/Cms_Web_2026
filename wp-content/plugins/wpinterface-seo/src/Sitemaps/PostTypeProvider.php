<?php
/**
 * Post type sitemap.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Sitemaps;

use WP_Post;
use WP_Query;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Support\Images;

defined( 'ABSPATH' ) || exit;

/**
 * Lists published, indexable entries of one post type, oldest change first
 * (so each file's lastmod is its last entry).
 */
final class PostTypeProvider implements Provider {

	/**
	 * Post type.
	 *
	 * @var string
	 */
	private string $post_type;

	/**
	 * URLs per file.
	 *
	 * @var int
	 */
	private int $per_page;

	/**
	 * Whether the post type is noindex by default (then only explicit `index` posts are listed).
	 *
	 * @var bool
	 */
	private bool $noindex_default;

	/**
	 * Post IDs never listed.
	 *
	 * @var list<int>
	 */
	private array $exclude;

	/**
	 * Whether to add image entries.
	 *
	 * @var bool
	 */
	private bool $images;

	/**
	 * Whether to list the home page first (front page shows latest posts).
	 *
	 * @var bool
	 */
	private bool $include_home;

	/**
	 * Cached total.
	 *
	 * @var int|null
	 */
	private ?int $total = null;

	/**
	 * Constructor.
	 *
	 * @param string $post_type       Post type.
	 * @param int    $per_page        URLs per file.
	 * @param bool   $noindex_default Whether the post type defaults to noindex.
	 * @param int[]  $exclude         Excluded post IDs.
	 * @param bool   $images          Whether to include images.
	 * @param bool   $include_home    Whether to list the home URL first.
	 */
	public function __construct( string $post_type, int $per_page, bool $noindex_default, array $exclude, bool $images, bool $include_home ) {
		$this->post_type       = $post_type;
		$this->per_page        = max( 1, $per_page );
		$this->noindex_default = $noindex_default;
		$this->exclude         = $exclude;
		$this->images          = $images;
		$this->include_home    = $include_home;
	}

	/**
	 * Provider name.
	 */
	public function name(): string {
		return $this->post_type;
	}

	/**
	 * Number of files.
	 */
	public function max_pages(): int {
		$total = $this->total() + ( $this->include_home ? 1 : 0 );

		return (int) ceil( $total / $this->per_page );
	}

	/**
	 * Entries of one file.
	 *
	 * @param int $page Page number.
	 * @return list<array{loc: string, lastmod?: string, images?: list<string>}>
	 */
	public function entries( int $page ): array {
		$entries = array();
		$limit   = $this->per_page;
		$offset  = ( $page - 1 ) * $this->per_page;

		if ( $this->include_home ) {
			if ( 1 === $page ) {
				$entries[] = array(
					'loc'     => home_url( '/' ),
					'lastmod' => $this->latest_modified(),
				);
				--$limit;
			} else {
				--$offset;
			}
		}

		if ( $limit < 1 ) {
			return $entries;
		}

		$query = new WP_Query(
			array_merge(
				$this->query_args(),
				array(
					'posts_per_page' => $limit,
					'offset'         => max( 0, $offset ),
					'no_found_rows'  => true,
				)
			)
		);

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$entry = $this->entry( $post );

			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Lastmod of one file: modification time of its last (newest) post.
	 *
	 * @param int $page Page number.
	 */
	public function lastmod( int $page ): string {
		$total  = $this->total();
		$offset = min( $page * $this->per_page, $total + ( $this->include_home ? 1 : 0 ) ) - 1 - ( $this->include_home ? 1 : 0 );

		if ( $offset < 0 ) {
			return $this->include_home ? $this->latest_modified() : '';
		}

		$query = new WP_Query(
			array_merge(
				$this->query_args(),
				array(
					'posts_per_page' => 1,
					'offset'         => $offset,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			)
		);

		$post_id = (int) ( $query->posts[0] ?? 0 );

		return $post_id ? (string) get_post_modified_time( 'c', true, $post_id ) : '';
	}

	/**
	 * Sitemap entry for a post, or null when it should not be listed.
	 *
	 * @param WP_Post $post Post.
	 * @return array{loc: string, lastmod?: string, images?: list<string>}|null
	 */
	private function entry( WP_Post $post ): ?array {
		$url = get_permalink( $post );

		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		// A canonical pointing elsewhere means this URL is not the one to index.
		$canonical = (string) Meta::post( $post->ID, 'canonical' );
		if ( '' !== $canonical && untrailingslashit( $canonical ) !== untrailingslashit( $url ) ) {
			return null;
		}

		$entry = array(
			'loc'     => $url,
			'lastmod' => (string) get_post_modified_time( 'c', true, $post ),
		);

		if ( $this->images ) {
			$entry['images'] = $this->images( $post );
		}

		/**
		 * Filters a post's sitemap entry. Return null to skip the post.
		 *
		 * @param array|null $entry Entry: loc, lastmod, images.
		 * @param WP_Post    $post  Post.
		 */
		$entry = apply_filters( 'wpiseo_sitemap_post_entry', $entry, $post );

		return is_array( $entry ) && ! empty( $entry['loc'] ) ? $entry : null;
	}

	/**
	 * Image URLs: featured image then content images (max 50).
	 *
	 * @param WP_Post $post Post.
	 * @return list<string>
	 */
	private function images( WP_Post $post ): array {
		$urls         = array();
		$thumbnail_id = (int) get_post_thumbnail_id( $post );

		if ( $thumbnail_id ) {
			$featured = wp_get_attachment_url( $thumbnail_id );
			if ( is_string( $featured ) && '' !== $featured ) {
				$urls[] = $featured;
			}
		}

		if ( 'attachment' !== $post->post_type && ! post_password_required( $post ) ) {
			foreach ( Images::in_content( $post->post_content, 50 ) as $image ) {
				$urls[] = (string) $image['url'];
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Number of listable posts.
	 */
	private function total(): int {
		if ( null === $this->total ) {
			$query = new WP_Query(
				array_merge(
					$this->query_args(),
					array(
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'no_found_rows'  => false,
					)
				)
			);

			$this->total = (int) $query->found_posts;
		}

		return $this->total;
	}

	/**
	 * Newest modification time across the post type (for the home entry).
	 */
	private function latest_modified(): string {
		$date = get_lastpostmodified( 'gmt', $this->post_type );

		return is_string( $date ) && '' !== $date ? gmdate( 'c', (int) strtotime( $date . ' UTC' ) ) : '';
	}

	/**
	 * Base query arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function query_args(): array {
		$key = Fields::PREFIX . 'noindex';

		$meta_query = $this->noindex_default
			? array(
				array(
					'key'   => $key,
					'value' => 'index',
				),
			)
			: array(
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
			);

		$args = array(
			'post_type'              => $this->post_type,
			'post_status'            => 'attachment' === $this->post_type ? 'inherit' : 'publish',
			'has_password'           => false,
			'orderby'                => array(
				'modified' => 'ASC',
				'ID'       => 'ASC',
			),
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Needed to honour per-post noindex.
			'post__not_in'           => $this->exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		);

		/**
		 * Filters the query used to list a post type in the sitemap.
		 *
		 * @param array  $args      WP_Query arguments.
		 * @param string $post_type Post type.
		 */
		return (array) apply_filters( 'wpiseo_sitemap_post_query_args', $args, $this->post_type );
	}
}
