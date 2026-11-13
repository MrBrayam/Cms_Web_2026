<?php
/**
 * Internal link suggestions.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Links;

use WP_Post;
use WP_Query;
use WPInterface\SEO\Editor\Editor;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Suggests published posts to link to from the post being edited:
 *
 * 1. Posts whose focus keyword appears in this text (the keyword is the anchor).
 * 2. Posts that match this post's own focus keywords in a WordPress search.
 *
 * Runs on request from the editor only; nothing is precomputed.
 */
final class Suggestions {

	/**
	 * Link index.
	 *
	 * @var Indexer
	 */
	private Indexer $indexer;

	/**
	 * Constructor.
	 *
	 * @param Indexer $indexer Link index.
	 */
	public function __construct( Indexer $indexer ) {
		$this->indexer = $indexer;
	}

	/**
	 * Suggestions for a post.
	 *
	 * @param int      $post_id  Post being edited.
	 * @param string   $content  Current content (HTML).
	 * @param string[] $keywords Current focus keywords.
	 * @param int      $limit    Maximum suggestions.
	 * @return list<array{id: int, title: string, url: string, anchor: string, reason: string}>
	 */
	public function for_post( int $post_id, string $content, array $keywords, int $limit = 8 ): array {
		$text    = self::normalize( wp_strip_all_tags( $content ) );
		$linked  = array_merge( array( $post_id ), $this->linked_ids( $content, $post_id ) );
		$results = array();

		foreach ( $this->keyword_targets( $post_id ) as $target ) {
			if ( in_array( $target['id'], $linked, true ) ) {
				continue;
			}

			foreach ( $target['keywords'] as $keyword ) {
				if ( '' !== $text && self::contains( $text, self::normalize( $keyword ) ) ) {
					$results[ $target['id'] ] = array(
						'id'     => $target['id'],
						'title'  => $target['title'],
						'url'    => $target['url'],
						'anchor' => $keyword,
						'reason' => 'keyword',
					);
					break;
				}
			}

			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		foreach ( array_slice( array_filter( array_map( 'trim', $keywords ) ), 0, 3 ) as $keyword ) {
			if ( count( $results ) >= $limit ) {
				break;
			}

			$query = new WP_Query(
				array(
					'post_type'           => Editor::post_types(),
					'post_status'         => 'publish',
					's'                   => $keyword,
					'posts_per_page'      => $limit,
					'post__not_in'        => $linked, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Small list: this post and its existing link targets.
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				)
			);

			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof WP_Post || isset( $results[ $post->ID ] ) || count( $results ) >= $limit ) {
					continue;
				}

				$results[ $post->ID ] = array(
					'id'     => $post->ID,
					'title'  => wp_strip_all_tags( get_the_title( $post ) ),
					'url'    => (string) get_permalink( $post ),
					'anchor' => '',
					'reason' => 'related',
				);
			}
		}

		return array_values( $results );
	}

	/**
	 * Published posts with focus keywords (most recent 500).
	 *
	 * @param int $exclude Post to skip.
	 * @return list<array{id: int, title: string, url: string, keywords: list<string>}>
	 */
	private function keyword_targets( int $exclude ): array {
		$cache_key = 'wpiseo_link_targets';
		$targets   = wp_cache_get( $cache_key, 'wpiseo' );

		if ( ! is_array( $targets ) ) {
			$query = new WP_Query(
				array(
					'post_type'           => Editor::post_types(),
					'post_status'         => 'publish',
					'posts_per_page'      => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- IDs only, cached; editor request.
					'fields'              => 'ids',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'orderby'             => 'modified',
					'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Editor-only request.
						array(
							'key'     => Fields::PREFIX . 'focus_keywords',
							'compare' => 'EXISTS',
						),
					),
				)
			);

			$targets = array();

			foreach ( $query->posts as $id ) {
				$keywords = array_values( array_filter( (array) Meta::post( (int) $id, 'focus_keywords' ), 'is_string' ) );

				if ( $keywords ) {
					$targets[] = array(
						'id'       => (int) $id,
						'title'    => wp_strip_all_tags( get_the_title( (int) $id ) ),
						'url'      => (string) get_permalink( (int) $id ),
						'keywords' => $keywords,
					);
				}
			}

			wp_cache_set( $cache_key, $targets, 'wpiseo', 5 * MINUTE_IN_SECONDS );
		}

		return array_values( array_filter( $targets, static fn( array $target ): bool => $target['id'] !== $exclude ) );
	}

	/**
	 * Posts the content already links to.
	 *
	 * @param string $content Content.
	 * @param int    $post_id Post ID.
	 * @return list<int>
	 */
	private function linked_ids( string $content, int $post_id ): array {
		$ids = array_column( $this->indexer->extract( $content, $post_id ), 'target_post_id' );

		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Lowercase, single-spaced text.
	 *
	 * @param string $text Text.
	 */
	private static function normalize( string $text ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) ) );
	}

	/**
	 * Whole-phrase match (letters, marks and digits count as word characters).
	 *
	 * @param string $haystack Normalized text.
	 * @param string $needle   Normalized phrase.
	 */
	private static function contains( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return false;
		}

		return (bool) preg_match( '/(?<![\p{L}\p{M}\p{N}])' . preg_quote( $needle, '/' ) . '(?![\p{L}\p{M}\p{N}])/u', $haystack );
	}
}
