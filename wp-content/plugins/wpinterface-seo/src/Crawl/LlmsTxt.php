<?php
/**
 * Generates llms.txt.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Crawl;

use WP_Post;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Options;
use WPInterface\SEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Serves `/llms.txt` (https://llmstxt.org/): a Markdown index of the site's
 * key pages for AI tools. Off by default; Google does not use it.
 */
final class LlmsTxt {

	private const CACHE_KEY = 'wpiseo_llms_txt';

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'serve' ), 0 );
		add_action( 'save_post', array( $this, 'flush' ) );
		add_action( 'wpiseo_settings_updated', array( $this, 'flush' ) );
	}

	/**
	 * Clears the cached file.
	 */
	public function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Serves the file when requested and no physical file exists.
	 */
	public function serve(): void {
		if ( ! is_404() ) {
			return;
		}

		$path      = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
		$home_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

		if ( $home_path . '/llms.txt' !== $path ) {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex', true );
		header( 'X-Content-Type-Options: nosniff' );
		echo $this->content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown served as text/markdown with nosniff, built from sanitized values; HTML escaping would corrupt it.
		exit;
	}

	/**
	 * The Markdown document.
	 */
	public function content(): string {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$name    = self::clean( (string) get_bloginfo( 'name' ) );
		$tagline = self::clean( (string) get_bloginfo( 'description' ) );
		$intro   = self::clean( (string) $this->options->get( 'crawl.llms_intro', '' ) );
		$limit   = max( 1, min( 500, (int) $this->options->get( 'crawl.llms_limit', 30 ) ) );
		$lines   = array( '# ' . $name, '' );

		if ( '' !== $tagline ) {
			$lines[] = '> ' . $tagline;
			$lines[] = '';
		}

		if ( '' !== $intro ) {
			$lines[] = $intro;
			$lines[] = '';
		}

		foreach ( (array) $this->options->get( 'crawl.llms_post_types', array( 'page', 'post' ) ) as $post_type ) {
			$object = get_post_type_object( (string) $post_type );

			if ( ! $object || ! $object->public ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'      => $object->name,
					'post_status'    => 'publish',
					'has_password'   => false,
					'posts_per_page' => $limit,
					'orderby'        => 'page' === $object->name ? 'menu_order title' : 'date',
					'order'          => 'page' === $object->name ? 'ASC' : 'DESC',
				)
			);

			$entries = array();

			foreach ( $posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$presentation = Plugin::instance()->presentation( Context::for_post( $post ) );

				if ( ! $presentation->is_indexable() ) {
					continue;
				}

				$description = self::clean( $presentation->description() );
				$entries[]   = sprintf( '- [%s](%s)%s', self::clean( get_the_title( $post ) ), esc_url_raw( (string) get_permalink( $post ) ), '' !== $description ? ': ' . $description : '' );
			}

			if ( $entries ) {
				$lines[] = '## ' . self::clean( (string) $object->labels->name );
				$lines[] = '';
				$lines   = array_merge( $lines, $entries, array( '' ) );
			}
		}

		$content = implode( "\n", $lines );

		set_transient( self::CACHE_KEY, $content, 12 * HOUR_IN_SECONDS );

		return $content;
	}

	/**
	 * Single-line plain text.
	 *
	 * @param string $text Text.
	 */
	private static function clean( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}
}
