<?php
/**
 * Server-side SEO checks (for abilities, CLI and AI agents).
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Analysis;

use WP_Post;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * A compact subset of the editor's analysis that runs in PHP: lengths,
 * focus keyword placement, images, links and indexing. The full analysis
 * (readability, keyword density by language…) runs in the editor.
 */
final class ServerAnalysis {

	/**
	 * Checks for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return array{score: int, checks: list<array{id: string, status: string, message: string}>, word_count: int}
	 */
	public static function analyze( WP_Post $post ): array {
		$presentation = Plugin::instance()->presentation( Context::for_post( $post ) );
		$title        = $presentation->title();
		$description  = $presentation->description();
		$html         = (string) $post->post_content;
		$text         = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
		$words        = '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) ?: array() ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$keywords     = array_values( array_filter( (array) Meta::post( $post->ID, 'focus_keywords' ), 'is_string' ) );
		$keyword      = mb_strtolower( (string) ( $keywords[0] ?? '' ) );
		$checks       = array();

		$add = static function ( string $id, string $status, string $message ) use ( &$checks ): void {
			$checks[] = array(
				'id'      => $id,
				'status'  => $status,
				'message' => $message,
			);
		};

		$title_length = mb_strlen( $title );
		$add(
			'title-length',
			$title_length >= 30 && $title_length <= 60 ? 'good' : 'ok',
			/* translators: %d: number of characters. */
			sprintf( __( 'The SEO title is %d characters (30–60 is a good range).', 'wpinterface-seo' ), $title_length )
		);

		$description_length = mb_strlen( $description );
		if ( 0 === $description_length ) {
			$add( 'description', 'bad', __( 'There is no meta description.', 'wpinterface-seo' ) );
		} else {
			$add(
				'description',
				$description_length >= 120 && $description_length <= 160 ? 'good' : 'ok',
				/* translators: %d: number of characters. */
				sprintf( __( 'The meta description is %d characters (120–160 is a good range).', 'wpinterface-seo' ), $description_length )
			);
		}

		if ( '' === $keyword ) {
			$add( 'keyword', 'bad', __( 'No focus keyword is set.', 'wpinterface-seo' ) );
		} else {
			$contains = static fn( string $haystack ): bool => false !== mb_strpos( mb_strtolower( $haystack ), $keyword );
			$intro    = mb_substr( $text, 0, 600 );

			$add( 'keyword-title', $contains( $title ) ? 'good' : 'bad', $contains( $title ) ? __( 'The focus keyword is in the SEO title.', 'wpinterface-seo' ) : __( 'Add the focus keyword to the SEO title.', 'wpinterface-seo' ) );
			$add( 'keyword-description', $contains( $description ) ? 'good' : 'ok', $contains( $description ) ? __( 'The focus keyword is in the meta description.', 'wpinterface-seo' ) : __( 'Add the focus keyword to the meta description.', 'wpinterface-seo' ) );
			$add( 'keyword-intro', $contains( $intro ) ? 'good' : 'ok', $contains( $intro ) ? __( 'The focus keyword appears at the start of the content.', 'wpinterface-seo' ) : __( 'Use the focus keyword near the start of the content.', 'wpinterface-seo' ) );
			// Slugs have no hyphens left, so "pour-over coffee" must match "pour over coffee".
			$in_slug = false !== mb_strpos( str_replace( array( '-', '_' ), ' ', urldecode( $post->post_name ) ), str_replace( array( '-', '_' ), ' ', $keyword ) );

			$add( 'keyword-slug', $in_slug ? 'good' : 'ok', $in_slug ? __( 'The focus keyword is in the URL.', 'wpinterface-seo' ) : __( 'Consider adding the focus keyword to the URL.', 'wpinterface-seo' ) );
		}

		$add(
			'length',
			$words >= 300 ? 'good' : ( $words >= 150 ? 'ok' : 'bad' ),
			/* translators: %d: number of words. */
			sprintf( __( 'The content has %d words (300 or more is recommended).', 'wpinterface-seo' ), $words )
		);

		$images      = substr_count( strtolower( $html ), '<img' );
		$missing_alt = preg_match_all( '/<img(?![^>]*\balt\s*=\s*["\'][^"\']+["\'])[^>]*>/i', $html );
		if ( $images > 0 ) {
			$add(
				'image-alt',
				$missing_alt ? 'ok' : 'good',
				$missing_alt
					/* translators: %d: number of images. */
					? sprintf( __( '%d images have no alt text.', 'wpinterface-seo' ), (int) $missing_alt )
					: __( 'All images have alt text.', 'wpinterface-seo' )
			);
		}

		$home     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$internal = 0;
		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			foreach ( $matches[1] as $href ) {
				$host = wp_parse_url( $href, PHP_URL_HOST );
				if ( null === $host || 0 === strcasecmp( (string) $host, $home ) ) {
					++$internal;
				}
			}
		}
		$add( 'internal-links', $internal ? 'good' : 'ok', $internal ? __( 'The content links to other pages on the site.', 'wpinterface-seo' ) : __( 'Add links to related pages on your site.', 'wpinterface-seo' ) );

		if ( ! $presentation->is_indexable() ) {
			$add( 'indexable', 'bad', __( 'This page is hidden from search engines (noindex).', 'wpinterface-seo' ) );
		}

		$points = array(
			'good' => 100,
			'ok'   => 50,
			'bad'  => 0,
		);
		$score  = (int) round( array_sum( array_map( static fn( array $check ): int => $points[ $check['status'] ] ?? 0, $checks ) ) / max( 1, count( $checks ) ) );

		return array(
			'score'      => $score,
			'checks'     => $checks,
			'word_count' => $words,
		);
	}
}
