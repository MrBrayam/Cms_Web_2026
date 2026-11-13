<?php
/**
 * Video sitemap.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Sitemaps;

use WP_HTML_Tag_Processor;
use WP_Post;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Images;

defined( 'ABSPATH' ) || exit;

/**
 * Pages with videos: YouTube embeds, self-hosted video blocks, and posts
 * with Video schema. Vimeo and other embeds are included when the post has
 * Video schema or a featured image to use as the thumbnail.
 */
final class VideoProvider implements Provider {

	/**
	 * Post types.
	 *
	 * @var string[]
	 */
	private array $post_types;

	/**
	 * URLs per file.
	 *
	 * @var int
	 */
	private int $per_page;

	/**
	 * Cached candidate IDs.
	 *
	 * @var int[]|null
	 */
	private ?array $ids = null;

	/**
	 * Constructor.
	 *
	 * @param string[] $post_types Post types.
	 * @param int      $per_page   URLs per file.
	 */
	public function __construct( array $post_types, int $per_page ) {
		$this->post_types = $post_types;
		$this->per_page   = max( 1, $per_page );
	}

	/**
	 * Provider name.
	 */
	public function name(): string {
		return 'video';
	}

	/**
	 * Number of files.
	 */
	public function max_pages(): int {
		return (int) ceil( count( $this->ids() ) / $this->per_page );
	}

	/**
	 * Entries.
	 *
	 * @param int $page Page number.
	 * @return list<array<string, mixed>>
	 */
	public function entries( int $page ): array {
		$entries = array();

		foreach ( array_slice( $this->ids(), ( $page - 1 ) * $this->per_page, $this->per_page ) as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$videos = $this->videos( $post );
			$url    = get_permalink( $post );

			if ( $videos && is_string( $url ) ) {
				$entries[] = array(
					'loc'     => $url,
					'lastmod' => (string) get_post_modified_time( 'c', true, $post ),
					'videos'  => $videos,
				);
			}
		}

		return $entries;
	}

	/**
	 * Lastmod of a file.
	 *
	 * @param int $page Page number.
	 */
	public function lastmod( int $page ): string {
		$latest = '';

		foreach ( array_slice( $this->ids(), ( $page - 1 ) * $this->per_page, $this->per_page ) as $post_id ) {
			$modified = (string) get_post_modified_time( 'c', true, $post_id );
			$latest   = $modified > $latest ? $modified : $latest;
		}

		return $latest;
	}

	/**
	 * Videos found on a post.
	 *
	 * @param WP_Post $post Post.
	 * @return list<array<string, mixed>>
	 */
	public function videos( WP_Post $post ): array {
		$presentation = Plugin::instance()->presentation( Context::for_post( $post ) );

		if ( ! $presentation->is_indexable() || post_password_required( $post ) ) {
			return array();
		}

		$title       = wp_strip_all_tags( get_the_title( $post ) );
		$description = mb_substr( '' !== $presentation->description() ? $presentation->description() : $title, 0, 2048 );
		$featured    = Images::attachment( (int) get_post_thumbnail_id( $post ) );
		$videos      = array();
		$schema      = Meta::post( $post->ID, 'schema' );

		if ( is_array( $schema ) && 'VideoObject' === ( $schema['type'] ?? '' ) ) {
			$data      = (array) ( $schema['data'] ?? array() );
			$thumbnail = Images::attachment( (int) ( $data['thumbnail'] ?? 0 ) ) ?? $featured;

			if ( $thumbnail && ( ! empty( $data['content_url'] ) || ! empty( $data['embed_url'] ) ) ) {
				$videos[] = array(
					'thumbnail'   => $thumbnail['url'],
					'title'       => (string) ( $data['name'] ?? $title ),
					'description' => (string) ( $data['description'] ?? $description ),
					'content_loc' => (string) ( $data['content_url'] ?? '' ),
					'player_loc'  => (string) ( $data['embed_url'] ?? '' ),
					'duration'    => self::seconds( (string) ( $data['duration'] ?? '' ) ),
				);
			}
		}

		if ( preg_match_all( '#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $post->post_content, $matches ) ) {
			foreach ( array_unique( $matches[1] ) as $id ) {
				$videos[] = array(
					'thumbnail'   => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
					'title'       => $title,
					'description' => $description,
					'player_loc'  => 'https://www.youtube.com/embed/' . $id,
				);
			}
		}

		if ( false !== stripos( $post->post_content, '<video' ) ) {
			$processor = new WP_HTML_Tag_Processor( $post->post_content );

			while ( $processor->next_tag( array( 'tag_name' => 'video' ) ) ) {
				$src    = $processor->get_attribute( 'src' );
				$poster = $processor->get_attribute( 'poster' );
				$thumb  = is_string( $poster ) && '' !== $poster ? $poster : ( $featured['url'] ?? '' );

				if ( is_string( $src ) && '' !== $src && '' !== $thumb ) {
					$videos[] = array(
						'thumbnail'   => $thumb,
						'title'       => $title,
						'description' => $description,
						'content_loc' => $src,
					);
				}
			}
		}

		/**
		 * Filters the videos listed for a post in the video sitemap.
		 *
		 * @param list<array> $videos Videos: thumbnail, title, description, content_loc, player_loc, duration.
		 * @param WP_Post     $post   Post.
		 */
		return array_values( (array) apply_filters( 'wpiseo_sitemap_videos', array_slice( $videos, 0, 20 ), $post ) );
	}

	/**
	 * IDs of posts that may contain videos (content or Video schema).
	 *
	 * @return int[]
	 */
	private function ids(): array {
		if ( null !== $this->ids ) {
			return $this->ids;
		}

		global $wpdb;

		if ( array() === $this->post_types ) {
			$this->ids = array();
			return $this->ids;
		}

		$types = implode( ',', array_fill( 0, count( $this->post_types ), '%s' ) );

		// Candidate scan; the result is cached with the sitemap file. $types holds placeholders only.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ({$types})
				AND ( p.post_content LIKE %s OR p.post_content LIKE %s OR p.post_content LIKE %s OR m.meta_value LIKE %s )
				ORDER BY p.post_modified_gmt ASC, p.ID ASC",
				array_merge(
					array( Fields::PREFIX . 'schema' ),
					$this->post_types,
					array(
						'%youtube.com/%',
						'%youtu.be/%',
						'%<video%',
						'%VideoObject%',
					)
				)
			)
		);
		// phpcs:enable

		$this->ids = array_map( 'intval', (array) $ids );

		return $this->ids;
	}

	/**
	 * Seconds from minutes or h:mm(:ss).
	 *
	 * @param string $value Input.
	 */
	private static function seconds( string $value ): int {
		if ( '' === trim( $value ) ) {
			return 0;
		}

		$parts = array_map( 'intval', explode( ':', trim( $value ) ) );

		switch ( count( $parts ) ) {
			case 1:
				return $parts[0] * 60;
			case 2:
				return $parts[0] * 3600 + $parts[1] * 60;
			default:
				return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
		}
	}
}
