<?php
/**
 * Image helpers.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

use WP_HTML_Tag_Processor;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves image data for social tags, schema and sitemaps.
 *
 * Image arrays have the shape:
 * { id: int, url: string, width: int, height: int, alt: string, type: string, caption: string }
 */
final class Images {

	/**
	 * Smallest width Facebook/LinkedIn/X render as a large card.
	 */
	public const SOCIAL_MIN_WIDTH = 1200;

	/**
	 * Image data for an attachment at a size.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Registered size or `full`.
	 * @return array<string, mixed>|null
	 */
	public static function attachment( int $attachment_id, string $size = 'full' ): ?array {
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$src = wp_get_attachment_image_src( $attachment_id, $size );

		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			return null;
		}

		return array(
			'id'      => $attachment_id,
			'url'     => (string) $src[0],
			'width'   => (int) $src[1],
			'height'  => (int) $src[2],
			'alt'     => trim( wp_strip_all_tags( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ),
			'type'    => (string) get_post_mime_type( $attachment_id ),
			'caption' => trim( wp_strip_all_tags( (string) wp_get_attachment_caption( $attachment_id ) ) ),
		);
	}

	/**
	 * Image data sized for social sharing: the smallest generated size at least
	 * SOCIAL_MIN_WIDTH wide, otherwise the original. Avoids sending multi-MB
	 * originals to social crawlers when a suitable resize exists.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>|null
	 */
	public static function social( int $attachment_id ): ?array {
		$full = self::attachment( $attachment_id, 'full' );

		if ( null === $full ) {
			return null;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$best     = null;

		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $name => $size ) {
				$width = (int) ( $size['width'] ?? 0 );

				if ( $width >= self::SOCIAL_MIN_WIDTH && $width < $full['width'] && ( null === $best || $width < $best['width'] ) ) {
					$best = array(
						'name'  => (string) $name,
						'width' => $width,
					);
				}
			}
		}

		if ( null === $best ) {
			return $full;
		}

		return self::attachment( $attachment_id, $best['name'] ) ?? $full;
	}

	/**
	 * First image in the post content: the attachment when the block/classic
	 * markup carries a `wp-image-{id}` class, otherwise the raw `src`.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>|null
	 */
	public static function first_in_content( WP_Post $post ): ?array {
		$images = self::in_content( $post->post_content, 1 );

		return $images[0] ?? null;
	}

	/**
	 * Images found in HTML, in document order.
	 *
	 * @param string $html  HTML.
	 * @param int    $limit Maximum number of images.
	 * @return list<array<string, mixed>>
	 */
	public static function in_content( string $html, int $limit = 1000 ): array {
		if ( false === stripos( $html, '<img' ) ) {
			return array();
		}

		$processor = new WP_HTML_Tag_Processor( $html );
		$images    = array();
		$seen      = array();

		$found = 0;

		while ( $found < $limit && $processor->next_tag( array( 'tag_name' => 'img' ) ) ) {
			$src = $processor->get_attribute( 'src' );

			if ( ! is_string( $src ) || '' === $src || 0 === strpos( $src, 'data:' ) ) {
				continue;
			}

			$attachment_id = 0;
			$class         = $processor->get_attribute( 'class' );

			if ( is_string( $class ) && preg_match( '/\bwp-image-(\d+)\b/', $class, $matches ) ) {
				$attachment_id = (int) $matches[1];
			}

			$image = $attachment_id ? self::attachment( $attachment_id ) : null;

			if ( null === $image ) {
				$alt   = $processor->get_attribute( 'alt' );
				$image = array(
					'id'      => 0,
					'url'     => Url::absolute( html_entity_decode( $src, ENT_QUOTES ) ),
					'width'   => (int) $processor->get_attribute( 'width' ),
					'height'  => (int) $processor->get_attribute( 'height' ),
					'alt'     => is_string( $alt ) ? $alt : '',
					'type'    => '',
					'caption' => '',
				);
			}

			if ( ! Url::is_http( $image['url'] ) || isset( $seen[ $image['url'] ] ) ) {
				continue;
			}

			$seen[ $image['url'] ] = true;
			$images[]              = $image;
			++$found;
		}

		return $images;
	}
}
