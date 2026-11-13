<?php
/**
 * Robots & crawl module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Crawl;

use WP_REST_Request;
use WP_REST_Server;
use WPInterface\SEO\Crawl\IndexNow;
use WPInterface\SEO\Crawl\LlmsTxt;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Rest\SettingsController;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Adds robots.txt rules, head clean-up, feed indexing, an RSS footer, IndexNow and llms.txt.
 */
final class CrawlModule extends Module {

	/**
	 * Crawlers that collect AI training data (blocked by the "block AI
	 * training" option). Search crawlers are not on this list.
	 */
	public const AI_TRAINING_BOTS = array( 'GPTBot', 'ClaudeBot', 'anthropic-ai', 'CCBot', 'Google-Extended', 'Applebot-Extended', 'Bytespider', 'meta-externalagent', 'cohere-training-data-crawler', 'Diffbot', 'omgili', 'Timpibot' );

	/**
	 * Head clean-up options and their defaults.
	 */
	public const CLEANUP = array(
		'generator'        => true,
		'rsd'              => false,
		'shortlink'        => false,
		'rest_links'       => false,
		'oembed_links'     => false,
		'emoji'            => false,
		'feed_links_extra' => false,
	);

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'robots_rules'    => '',
			'block_ai'        => false,
			'cleanup'         => self::CLEANUP,
			'noindex_feeds'   => true,
			'rss_before'      => '',
			'rss_after'       => '',
			'indexnow'        => false,
			'indexnow_key'    => '',
			'llms'            => false,
			'llms_post_types' => array( 'page', 'post' ),
			'llms_limit'      => 30,
			'llms_intro'      => '',
		);
	}

	/**
	 * Sanitizes the section. Generates the IndexNow key when IndexNow is first enabled.
	 *
	 * @param array<string, mixed> $values   Values.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $values, array $defaults ): array {
		$cleanup = array();

		foreach ( self::CLEANUP as $key => $default ) {
			$cleanup[ $key ] = Sanitize::to_bool( $values['cleanup'][ $key ] ?? $default );
		}

		$indexnow = Sanitize::to_bool( $values['indexnow'] ?? false );
		$key      = preg_replace( '/[^a-zA-Z0-9-]/', '', (string) ( $values['indexnow_key'] ?? '' ) );

		if ( $indexnow && strlen( (string) $key ) < 8 ) {
			$key = bin2hex( random_bytes( 16 ) );
		}

		$post_types = array_values( array_filter( array_map( 'sanitize_key', (array) ( $values['llms_post_types'] ?? array() ) ) ) );

		return array(
			'robots_rules'    => mb_substr( sanitize_textarea_field( (string) ( $values['robots_rules'] ?? '' ) ), 0, 20000 ),
			'block_ai'        => Sanitize::to_bool( $values['block_ai'] ?? false ),
			'cleanup'         => $cleanup,
			'noindex_feeds'   => Sanitize::to_bool( $values['noindex_feeds'] ?? $defaults['noindex_feeds'] ),
			'rss_before'      => wp_kses_post( (string) ( $values['rss_before'] ?? '' ) ),
			'rss_after'       => wp_kses_post( (string) ( $values['rss_after'] ?? '' ) ),
			'indexnow'        => $indexnow,
			'indexnow_key'    => (string) $key,
			'llms'            => Sanitize::to_bool( $values['llms'] ?? false ),
			'llms_post_types' => $post_types,
			'llms_limit'      => max( 1, min( 500, absint( $values['llms_limit'] ?? 30 ) ) ),
			'llms_intro'      => mb_substr( sanitize_textarea_field( (string) ( $values['llms_intro'] ?? '' ) ), 0, 2000 ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		$options = $this->plugin->options();

		if ( $this->plugin->output_enabled() ) {
			add_filter( 'robots_txt', array( $this, 'robots_txt' ), 10, 2 );
			add_action( 'wp', array( $this, 'cleanup_head' ) );
			add_action( 'template_redirect', array( $this, 'feed_headers' ) );
			add_filter( 'the_content_feed', array( $this, 'rss_content' ) );
			add_filter( 'the_excerpt_rss', array( $this, 'rss_content' ) );

			if ( $options->get( 'crawl.llms', false ) ) {
				( new LlmsTxt( $options ) )->register();
			}
		}

		// IndexNow notifies search engines; it is independent of page output.
		if ( $options->get( 'crawl.indexnow', false ) && '' !== $options->get( 'crawl.indexnow_key', '' ) ) {
			( new IndexNow( $options ) )->register();
		}

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 55 );
		}
	}

	/**
	 * Adds custom rules and the AI-training block list to the virtual robots.txt.
	 *
	 * @param string $output    robots.txt content.
	 * @param bool   $is_public Whether the site is public.
	 */
	public function robots_txt( $output, $is_public ): string {
		$output  = rtrim( (string) $output );
		$options = $this->plugin->options();

		if ( ! $is_public ) {
			return $output . "\n";
		}

		$rules = trim( (string) $options->get( 'crawl.robots_rules', '' ) );

		if ( '' !== $rules ) {
			$output .= "\n\n" . $rules;
		}

		if ( $options->get( 'crawl.block_ai', false ) ) {
			$output .= "\n\n# " . 'AI training crawlers';
			foreach ( self::AI_TRAINING_BOTS as $bot ) {
				$output .= "\nUser-agent: " . $bot . "\nDisallow: /";
			}
		}

		return $output . "\n";
	}

	/**
	 * Removes optional `<head>` output.
	 */
	public function cleanup_head(): void {
		$cleanup = (array) $this->plugin->options()->get( 'crawl.cleanup', array() );

		if ( ! empty( $cleanup['generator'] ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		if ( ! empty( $cleanup['rsd'] ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( ! empty( $cleanup['shortlink'] ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}

		if ( ! empty( $cleanup['rest_links'] ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head' );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		}

		if ( ! empty( $cleanup['oembed_links'] ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}

		if ( ! empty( $cleanup['emoji'] ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		}

		if ( ! empty( $cleanup['feed_links_extra'] ) ) {
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}

	/**
	 * Keeps feeds out of search results (links are still followed).
	 */
	public function feed_headers(): void {
		if ( is_feed() && $this->plugin->options()->get( 'crawl.noindex_feeds', true ) && ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, follow', true );
		}
	}

	/**
	 * Adds text before/after feed items (credits the original source when content is scraped).
	 *
	 * Variables: %post_link%, %blog_link%, %post_title%, %site_name%.
	 *
	 * @param string $content Feed content.
	 */
	public function rss_content( $content ): string {
		$options = $this->plugin->options();
		$before  = (string) $options->get( 'crawl.rss_before', '' );
		$after   = (string) $options->get( 'crawl.rss_after', '' );

		if ( '' === $before && '' === $after ) {
			return (string) $content;
		}

		$link   = (string) get_permalink();
		$title  = (string) get_the_title();
		$site   = (string) get_bloginfo( 'name' );
		$values = array(
			'%post_link%'  => sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $title ) ),
			'%blog_link%'  => sprintf( '<a href="%s">%s</a>', esc_url( home_url( '/' ) ), esc_html( $site ) ),
			'%post_title%' => esc_html( $title ),
			'%site_name%'  => esc_html( $site ),
		);

		$render = static fn( string $text ): string => '' === $text ? '' : '<p>' . strtr( $text, $values ) . '</p>';

		return $render( $before ) . (string) $content . $render( $after );
	}

	/**
	 * REST: IndexNow history and manual submission, llms.txt preview.
	 */
	public function register_routes(): void {
		$permission = static fn() => current_user_can( Capabilities::MANAGE_SETTINGS );
		$indexnow   = new IndexNow( $this->plugin->options() );

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/indexnow',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response(
						array(
							'history'  => $indexnow->history(),
							'keyUrl'   => '' !== $indexnow->key() ? home_url( '/' . $indexnow->key() . '.txt' ) : '',
							'isPublic' => IndexNow::is_public_site( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
						)
					),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static function ( WP_REST_Request $request ) use ( $indexnow ) {
						if ( '' === $indexnow->key() ) {
							return new \WP_Error( 'wpiseo_indexnow_off', __( 'Turn on IndexNow and save first.', 'wpinterface-seo' ), array( 'status' => 400 ) );
						}

						$urls = array_slice( array_map( 'strval', (array) $request['urls'] ), 0, 100 );

						return rest_ensure_response( $indexnow->submit( $urls ) );
					},
					'permission_callback' => $permission,
					'args'                => array(
						'urls' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'string' ),
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/llms-txt',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response(
					array(
						'content' => ( new LlmsTxt( $this->plugin->options() ) )->content(),
						'url'     => home_url( '/llms.txt' ),
					)
				),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Adds the Robots & Crawl page.
	 *
	 * @param array<string, array<string, mixed>> $pages Pages.
	 * @return array<string, array<string, mixed>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['crawl'] = array(
			'title'      => __( 'Robots & Crawl', 'wpinterface-seo' ),
			'menu_title' => __( 'Robots & Crawl', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}
}
