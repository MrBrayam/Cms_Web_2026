<?php
/**
 * Internal/external link index.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Links;

use WP_HTML_Tag_Processor;
use WP_Post;
use WPInterface\SEO\Editor\Editor;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table.

/**
 * `{prefix}wpiseo_links`: one row per link in a published post. Rebuilt when
 * the post is saved, so list-table counts never parse content on page load.
 */
final class Indexer {

	/**
	 * Resolved post IDs per URL (one request).
	 *
	 * @var array<string, int>
	 */
	private array $resolved = array();

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpiseo_links';
	}

	/**
	 * Whether a post's links are indexed (published, public, SEO-enabled type).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function indexable( WP_Post $post ): bool {
		return 'publish' === $post->post_status && in_array( $post->post_type, Editor::post_types(), true );
	}

	/**
	 * Replaces the stored links of a post.
	 *
	 * @param WP_Post $post Post.
	 * @return int Number of links stored.
	 */
	public function index( WP_Post $post ): int {
		global $wpdb;

		$this->forget( $post->ID );

		if ( ! self::indexable( $post ) ) {
			return 0;
		}

		$links = $this->extract( $post->post_content, $post->ID );

		foreach ( $links as $link ) {
			$wpdb->insert(
				self::table(),
				array(
					'post_id'        => $post->ID,
					'target_post_id' => $link['target_post_id'],
					'url'            => mb_substr( $link['url'], 0, 2000 ),
					'type'           => $link['type'],
					'nofollow'       => $link['nofollow'] ? 1 : 0,
				),
				array( '%d', '%d', '%s', '%s', '%d' )
			);
		}

		return count( $links );
	}

	/**
	 * Removes the stored links of a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function forget( int $post_id ): void {
		global $wpdb;

		$wpdb->delete( self::table(), array( 'post_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * Links in HTML.
	 *
	 * @param string $html    Content.
	 * @param int    $post_id Post the content belongs to (self-links are skipped).
	 * @return list<array{url: string, type: string, target_post_id: int, nofollow: bool}>
	 */
	public function extract( string $html, int $post_id = 0 ): array {
		if ( false === stripos( $html, '<a' ) ) {
			return array();
		}

		$home      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$processor = new WP_HTML_Tag_Processor( $html );
		$links     = array();

		while ( $processor->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$href = $processor->get_attribute( 'href' );

			if ( ! is_string( $href ) ) {
				continue;
			}

			$href = trim( $href );

			if ( '' === $href || '#' === $href[0] || preg_match( '/^(mailto|tel|javascript|data|sms):/i', $href ) ) {
				continue;
			}

			$url  = self::absolute( $href );
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );

			if ( '' === $host ) {
				continue;
			}

			$internal = 0 === strcasecmp( preg_replace( '/^www\./i', '', $host ), preg_replace( '/^www\./i', '', $home ) );
			$target   = $internal ? $this->resolve( $url ) : 0;

			if ( $internal && $post_id && $target === $post_id ) {
				continue;
			}

			$rel = strtolower( (string) $processor->get_attribute( 'rel' ) );

			$links[] = array(
				'url'            => $url,
				'type'           => $internal ? 'internal' : 'external',
				'target_post_id' => $target,
				'nofollow'       => (bool) preg_match( '/\b(nofollow|sponsored|ugc)\b/', $rel ),
			);
		}

		return $links;
	}

	/**
	 * Outgoing and incoming counts for posts.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array<int, array{internal: int, external: int, inbound: int}>
	 */
	public function counts( array $post_ids ): array {
		global $wpdb;

		$post_ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );
		$counts   = array_fill_keys(
			$post_ids,
			array(
				'internal' => 0,
				'external' => 0,
				'inbound'  => 0,
			)
		);

		if ( array() === $post_ids ) {
			return $counts;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$params       = array_merge( array( self::table() ), $post_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders built above.
		$out = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, type, COUNT(*) AS total FROM %i WHERE post_id IN ({$placeholders}) GROUP BY post_id, type", $params ), ARRAY_A );

		foreach ( (array) $out as $row ) {
			$counts[ (int) $row['post_id'] ][ 'internal' === $row['type'] ? 'internal' : 'external' ] = (int) $row['total'];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders built above.
		$in = $wpdb->get_results( $wpdb->prepare( "SELECT target_post_id, COUNT(DISTINCT post_id) AS total FROM %i WHERE target_post_id IN ({$placeholders}) GROUP BY target_post_id", $params ), ARRAY_A );

		foreach ( (array) $in as $row ) {
			$counts[ (int) $row['target_post_id'] ]['inbound'] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Published posts that link to a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   Maximum.
	 * @return list<int>
	 */
	public function inbound( int $post_id, int $limit = 20 ): array {
		global $wpdb;

		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT post_id FROM %i WHERE target_post_id = %d ORDER BY post_id DESC LIMIT %d', self::table(), $post_id, $limit ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Post IDs a post already links to.
	 *
	 * @param int $post_id Post ID.
	 * @return list<int>
	 */
	public function targets( int $post_id ): array {
		global $wpdb;

		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT target_post_id FROM %i WHERE post_id = %d AND target_post_id > 0', self::table(), $post_id ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Indexes the next batch of posts (background rebuild).
	 *
	 * @param int $after_id Last processed post ID.
	 * @param int $limit    Batch size.
	 * @return int|null Last processed ID, or null when done.
	 */
	public function rebuild_batch( int $after_id, int $limit = 50 ): ?int {
		global $wpdb;

		$types = Editor::post_types();

		if ( array() === $types ) {
			return null;
		}

		$type_placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders built above.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_type IN ({$type_placeholders}) ORDER BY ID ASC LIMIT %d", array_merge( array( $after_id ), $types, array( $limit ) ) ) );

		if ( empty( $ids ) ) {
			return null;
		}

		foreach ( array_map( 'intval', $ids ) as $id ) {
			$post = get_post( $id );

			if ( $post instanceof WP_Post ) {
				$this->index( $post );
			}

			$after_id = $id;
		}

		return $after_id;
	}

	/**
	 * Absolute URL for a link (relative links resolve against home).
	 *
	 * @param string $href Link.
	 */
	private static function absolute( string $href ): string {
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $href ) ) {
			return $href;
		}

		if ( 0 === strpos( $href, '//' ) ) {
			return set_url_scheme( $href );
		}

		if ( '?' === $href[0] ) {
			return home_url( '/' . $href );
		}

		return home_url( '/' . ltrim( $href, '/' ) );
	}

	/**
	 * Post ID for an internal URL (0 for archives and unknown URLs).
	 *
	 * @param string $url URL.
	 */
	private function resolve( string $url ): int {
		$key = strtok( $url, '#' );
		$key = false === $key ? $url : $key;

		if ( ! isset( $this->resolved[ $key ] ) ) {
			$this->resolved[ $key ] = (int) url_to_postid( $key ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid -- Runs on save only, cached per request.
		}

		return $this->resolved[ $key ];
	}
}
