<?php
/**
 * Author sitemap.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Sitemaps;

use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Lists archive URLs of users with published posts.
 */
final class AuthorProvider implements Provider {

	/**
	 * URLs per file.
	 *
	 * @var int
	 */
	private int $per_page;

	/**
	 * Post types that count as "published posts".
	 *
	 * @var list<string>
	 */
	private array $post_types;

	/**
	 * Cached total.
	 *
	 * @var int|null
	 */
	private ?int $total = null;

	/**
	 * Constructor.
	 *
	 * @param int      $per_page   URLs per file.
	 * @param string[] $post_types Indexable post types with author support.
	 */
	public function __construct( int $per_page, array $post_types ) {
		$this->per_page   = max( 1, $per_page );
		$this->post_types = $post_types;
	}

	/**
	 * Provider name.
	 */
	public function name(): string {
		return 'author';
	}

	/**
	 * Number of files.
	 */
	public function max_pages(): int {
		if ( array() === $this->post_types ) {
			return 0;
		}

		if ( null === $this->total ) {
			$query       = new \WP_User_Query(
				array_merge(
					$this->query_args(),
					array(
						'number'      => 1,
						'count_total' => true,
						'fields'      => 'ID',
					)
				)
			);
			$this->total = (int) $query->get_total();
		}

		return (int) ceil( $this->total / $this->per_page );
	}

	/**
	 * Entries of one file.
	 *
	 * @param int $page Page number.
	 * @return list<array{loc: string, lastmod?: string, images?: list<string>}>
	 */
	public function entries( int $page ): array {
		if ( array() === $this->post_types ) {
			return array();
		}

		$users = get_users(
			array_merge(
				$this->query_args(),
				array(
					'number' => $this->per_page,
					'paged'  => $page,
				)
			)
		);

		$entries = array();

		foreach ( $users as $user ) {
			if ( $user instanceof WP_User ) {
				$entries[] = array( 'loc' => get_author_posts_url( (int) $user->ID ) );
			}
		}

		return $entries;
	}

	/**
	 * Authors have no cheap lastmod; omitted (it is optional in the protocol).
	 *
	 * @param int $page Page number.
	 */
	public function lastmod( int $page ): string {
		unset( $page );

		return '';
	}

	/**
	 * Base user query arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function query_args(): array {
		return array(
			'has_published_posts' => $this->post_types,
			'orderby'             => 'ID',
			'order'               => 'ASC',
		);
	}
}
