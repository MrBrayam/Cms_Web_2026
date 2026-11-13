<?php
/**
 * XML sitemaps module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Sitemaps;

use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Options;
use WPInterface\SEO\Sitemaps\AuthorProvider;
use WPInterface\SEO\Sitemaps\HtmlSitemap;
use WPInterface\SEO\Support\Blocks;
use WPInterface\SEO\Sitemaps\NewsProvider;
use WPInterface\SEO\Sitemaps\PostTypeProvider;
use WPInterface\SEO\Sitemaps\Provider;
use WPInterface\SEO\Sitemaps\Renderer;
use WPInterface\SEO\Sitemaps\Router;
use WPInterface\SEO\Sitemaps\TaxonomyProvider;
use WPInterface\SEO\Sitemaps\VideoProvider;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;
use WPInterface\SEO\Support\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces WordPress core sitemaps with `/sitemap.xml` (index), per-type
 * files with image entries and lastmod, and a readable stylesheet.
 */
final class SitemapsModule extends Module {

	private const VERSION_OPTION = 'wpiseo_sitemap_version';
	private const CACHE_TTL      = 12 * HOUR_IN_SECONDS;

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'post_types'    => array(),
			'taxonomies'    => array(),
			'authors'       => true,
			'images'        => true,
			'per_page'      => 1000,
			'exclude_posts' => array(),
			'exclude_terms' => array(),
			'robots_txt'    => true,
			'news'          => false,
			'news_types'    => array( 'post' ),
			'news_name'     => '',
			'video'         => false,
		);
	}

	/**
	 * Sanitizes the section.
	 *
	 * @param array<string, mixed> $values   Values.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $values, array $defaults ): array {
		return array(
			'post_types'    => self::bool_map( $values['post_types'] ?? array() ),
			'taxonomies'    => self::bool_map( $values['taxonomies'] ?? array() ),
			'authors'       => Sanitize::to_bool( $values['authors'] ?? $defaults['authors'] ),
			'images'        => Sanitize::to_bool( $values['images'] ?? $defaults['images'] ),
			'per_page'      => max( 10, min( 50000, absint( $values['per_page'] ?? $defaults['per_page'] ) ) ),
			'exclude_posts' => self::id_list( $values['exclude_posts'] ?? array() ),
			'exclude_terms' => self::id_list( $values['exclude_terms'] ?? array() ),
			'robots_txt'    => Sanitize::to_bool( $values['robots_txt'] ?? $defaults['robots_txt'] ),
			'news'          => Sanitize::to_bool( $values['news'] ?? false ),
			'news_types'    => array_values( array_filter( array_map( 'sanitize_key', (array) ( $values['news_types'] ?? array( 'post' ) ) ) ) ),
			'news_name'     => sanitize_text_field( (string) ( $values['news_name'] ?? '' ) ),
			'video'         => Sanitize::to_bool( $values['video'] ?? false ),
		);
	}

	/**
	 * Whether a post type is listed (default: when it is not noindex by default).
	 *
	 * @param Options $options   Settings.
	 * @param string  $post_type Post type.
	 */
	public static function includes_post_type( Options $options, string $post_type ): bool {
		$stored = $options->get( 'sitemaps.post_types', array() );

		if ( is_array( $stored ) && array_key_exists( $post_type, $stored ) ) {
			return (bool) $stored[ $post_type ];
		}

		return 'attachment' !== $post_type && ! MetaModule::post_type( $options, $post_type )['noindex'];
	}

	/**
	 * Whether a taxonomy is listed (default: when it is not noindex by default).
	 *
	 * @param Options $options  Settings.
	 * @param string  $taxonomy Taxonomy.
	 */
	public static function includes_taxonomy( Options $options, string $taxonomy ): bool {
		$stored = $options->get( 'sitemaps.taxonomies', array() );

		if ( is_array( $stored ) && array_key_exists( $taxonomy, $stored ) ) {
			return (bool) $stored[ $taxonomy ];
		}

		return ! MetaModule::taxonomy( $options, $taxonomy )['noindex'];
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 40 );
		}

		// The HTML sitemap is page content, so it works even while output is paused.
		add_shortcode( 'wpiseo_sitemap', array( $this, 'html_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );

		if ( ! $this->plugin->output_enabled() ) {
			return;
		}

		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_action( 'init', array( Router::class, 'add_rules' ) );
		add_filter( 'query_vars', array( Router::class, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'redirect_core_sitemaps' ), 0 );
		add_action( 'template_redirect', array( $this, 'serve' ), 1 );
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 20, 2 );

		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'edited_term', 'created_term', 'delete_term', 'wpiseo_settings_updated' ) as $hook ) {
			add_action( $hook, array( $this, 'bump_version' ) );
		}
	}

	/**
	 * Sends requests for core's `/wp-sitemap*.xml` to our index.
	 */
	public function redirect_core_sitemaps(): void {
		if ( '' === (string) get_query_var( 'sitemap' ) && '' === (string) get_query_var( 'sitemap-stylesheet' ) ) {
			return;
		}

		if ( wp_safe_redirect( Router::index_url(), 301, Branding::redirect_by() ) ) {
			exit;
		}
	}

	/**
	 * Serves sitemap requests.
	 */
	public function serve(): void {
		$name = sanitize_key( (string) get_query_var( Router::QUERY_VAR ) );

		if ( '' === $name ) {
			return;
		}

		$renderer = new Renderer();

		if ( 'xsl' === $name ) {
			$this->send( $renderer->stylesheet(), 'text/xsl' );
		}

		$page = max( 1, absint( get_query_var( Router::PAGE_VAR ) ) );
		$xml  = $this->cached( $name, $page, $renderer );

		if ( null === $xml ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		$this->send( $xml, 'application/xml' );
	}

	/**
	 * Adds the sitemap location to robots.txt.
	 *
	 * @param string $output Robots.txt content.
	 * @param bool   $is_public Whether the site is public.
	 */
	public function robots_txt( $output, $is_public ): string {
		$output = (string) $output;

		if ( $is_public && $this->plugin->options()->get( 'sitemaps.robots_txt', true ) ) {
			$output = rtrim( $output ) . "\n\nSitemap: " . esc_url_raw( Router::index_url() ) . "\n";
		}

		return $output;
	}

	/**
	 * Invalidates cached sitemap files.
	 */
	public function bump_version(): void {
		update_option( self::VERSION_OPTION, (string) microtime( true ), false );
	}

	/**
	 * `[wpiseo_sitemap post_types="page,post" taxonomies="yes" dates="yes" limit="100" orderby="title"]`.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function html_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'post_types' => '',
				'taxonomies' => 'no',
				'dates'      => 'no',
				'limit'      => '100',
				'orderby'    => 'title',
			),
			is_array( $atts ) ? $atts : array(),
			'wpiseo_sitemap'
		);

		return ( new HtmlSitemap( $this->plugin->options() ) )->render(
			array(
				'post_types' => array_filter( array_map( 'trim', explode( ',', $atts['post_types'] ) ) ),
				'taxonomies' => Sanitize::to_bool( $atts['taxonomies'] ),
				'dates'      => Sanitize::to_bool( $atts['dates'] ),
				'limit'      => absint( $atts['limit'] ),
				'orderby'    => $atts['orderby'],
			)
		);
	}

	/**
	 * Registers the HTML Sitemap block.
	 */
	public function register_block(): void {
		Blocks::register_script();

		register_block_type(
			WPISEO_PATH . 'blocks/html-sitemap',
			array(
				'render_callback' => function ( $attributes ): string {
					$attributes = is_array( $attributes ) ? $attributes : array();
					$html       = ( new HtmlSitemap( $this->plugin->options() ) )->render(
						array(
							'post_types' => (array) ( $attributes['postTypes'] ?? array() ),
							'taxonomies' => ! empty( $attributes['showTaxonomies'] ),
							'dates'      => ! empty( $attributes['showDates'] ),
							'limit'      => (int) ( $attributes['limit'] ?? 100 ),
							'orderby'    => (string) ( $attributes['orderBy'] ?? 'title' ),
						)
					);

					return '' === $html ? '' : sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
				},
			)
		);
	}

	/**
	 * Providers in display order.
	 *
	 * @return array<string, Provider>
	 */
	public function providers(): array {
		$options    = $this->plugin->options();
		$per_page   = (int) $options->get( 'sitemaps.per_page', 1000 );
		$images     = (bool) $options->get( 'sitemaps.images', true );
		$exclude_p  = array_map( 'intval', (array) $options->get( 'sitemaps.exclude_posts', array() ) );
		$exclude_t  = array_map( 'intval', (array) $options->get( 'sitemaps.exclude_terms', array() ) );
		$providers  = array();
		$post_types = array();

		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			if ( self::includes_post_type( $options, $post_type ) ) {
				$post_types[] = $post_type;
			}
		}

		// Pages first (the home page is listed there when the front page shows posts).
		usort( $post_types, static fn( string $a, string $b ): int => ( 'page' === $b ) <=> ( 'page' === $a ) );

		$home_type = 'posts' === get_option( 'show_on_front' ) && $post_types ? $post_types[0] : '';

		foreach ( $post_types as $post_type ) {
			$providers[ $post_type ] = new PostTypeProvider(
				$post_type,
				$per_page,
				(bool) MetaModule::post_type( $options, $post_type )['noindex'],
				$exclude_p,
				$images,
				$post_type === $home_type
			);
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $taxonomy ) {
			if ( self::includes_taxonomy( $options, $taxonomy ) && ! isset( $providers[ $taxonomy ] ) ) {
				$providers[ $taxonomy ] = new TaxonomyProvider( $taxonomy, $per_page, (bool) MetaModule::taxonomy( $options, $taxonomy )['noindex'], $exclude_t );
			}
		}

		$author = MetaModule::archive( $options, 'author' );

		if ( $options->get( 'sitemaps.authors', true ) && ! $author['noindex'] && ! $author['disabled'] && ! isset( $providers['author'] ) ) {
			$author_types = array_values( array_filter( $post_types, static fn( string $type ): bool => post_type_supports( $type, 'author' ) ) );

			$providers['author'] = new AuthorProvider( $per_page, $author_types );
		}

		if ( $options->get( 'sitemaps.news', false ) ) {
			$news_name = isset( $providers['news'] ) ? 'google-news' : 'news';
			$name      = trim( (string) $options->get( 'sitemaps.news_name', '' ) );

			$providers[ $news_name ] = new NewsProvider(
				$news_name,
				array_values( array_filter( (array) $options->get( 'sitemaps.news_types', array( 'post' ) ), 'post_type_exists' ) ),
				'' !== $name ? $name : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
			);
		}

		if ( $options->get( 'sitemaps.video', false ) && ! isset( $providers['video'] ) ) {
			$providers['video'] = new VideoProvider( $post_types, $per_page );
		}

		/**
		 * Filters sitemap providers (name => Provider).
		 *
		 * @param array<string, Provider> $providers Providers.
		 */
		$providers = apply_filters( 'wpiseo_sitemap_providers', $providers );

		return array_filter( (array) $providers, static fn( $provider ): bool => $provider instanceof Provider );
	}

	/**
	 * Adds the Sitemaps settings page.
	 *
	 * @param array<string, array<string, string>> $pages Pages.
	 * @return array<string, array<string, string>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['sitemaps'] = array(
			'title'      => __( 'XML Sitemaps', 'wpinterface-seo' ),
			'menu_title' => __( 'Sitemaps', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}

	/**
	 * XML for a request, from cache when fresh. Null means "not found".
	 *
	 * @param string   $name     Provider name or `index`.
	 * @param int      $page     Page.
	 * @param Renderer $renderer Renderer.
	 */
	private function cached( string $name, int $page, Renderer $renderer ): ?string {
		$version = (string) get_option( self::VERSION_OPTION, '0' );
		$key     = 'wpiseo_sitemap_' . md5( $name . '|' . $page . '|' . $version . '|' . home_url() );
		$xml     = get_transient( $key );

		if ( is_string( $xml ) && '' !== $xml ) {
			return $xml;
		}

		$xml = $this->generate( $name, $page, $renderer );

		if ( null !== $xml ) {
			set_transient( $key, $xml, self::CACHE_TTL );
		}

		return $xml;
	}

	/**
	 * Builds the XML for a request.
	 *
	 * @param string   $name     Provider name or `index`.
	 * @param int      $page     Page.
	 * @param Renderer $renderer Renderer.
	 */
	private function generate( string $name, int $page, Renderer $renderer ): ?string {
		$providers = $this->providers();

		if ( 'index' === $name ) {
			$sitemaps = array();

			foreach ( $providers as $provider ) {
				for ( $i = 1, $max = $provider->max_pages(); $i <= $max; $i++ ) {
					$sitemaps[] = array(
						'loc'     => Router::url( $provider->name(), $i ),
						'lastmod' => $provider->lastmod( $i ),
					);
				}
			}

			return $renderer->index( $sitemaps );
		}

		$provider = $providers[ $name ] ?? null;

		if ( null === $provider || $page > $provider->max_pages() ) {
			return null;
		}

		return $renderer->urlset( $provider->entries( $page ) );
	}

	/**
	 * Sends a response and ends the request.
	 *
	 * @param string $body         Body.
	 * @param string $content_type MIME type.
	 * @return never
	 */
	private function send( string $body, string $content_type ): void {
		status_header( 200 );
		header( 'Content-Type: ' . $content_type . '; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		header( 'X-Content-Type-Options: nosniff' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML built with esc_xml()/esc_url().
		exit;
	}

	/**
	 * Map of name => bool.
	 *
	 * @param mixed $map Raw map.
	 * @return array<string, bool>
	 */
	private static function bool_map( $map ): array {
		$clean = array();

		foreach ( is_array( $map ) ? array_slice( $map, 0, 200, true ) : array() as $name => $value ) {
			$name = sanitize_key( (string) $name );
			if ( '' !== $name ) {
				$clean[ $name ] = Sanitize::to_bool( $value );
			}
		}

		return $clean;
	}

	/**
	 * List of positive integers from an array or comma-separated string.
	 *
	 * @param mixed $value Raw value.
	 * @return list<int>
	 */
	private static function id_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		$ids = array_filter( array_map( 'absint', is_array( $value ) ? $value : array() ) );

		return array_slice( array_values( array_unique( $ids ) ), 0, 5000 );
	}
}
