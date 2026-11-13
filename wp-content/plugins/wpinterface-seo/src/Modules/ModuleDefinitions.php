<?php
/**
 * Built-in module catalogue.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Static list of modules. Classes that do not exist yet are reported as
 * "planned" in the UI and never loaded.
 *
 * Labels live in labels() so nothing is translated before `init`.
 */
final class ModuleDefinitions {

	/**
	 * Module definitions keyed by ID.
	 *
	 * Keys: class, default (enabled by default), required (cannot be disabled),
	 * phase (roadmap phase that ships it), requires (callable availability check).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		$ns = __NAMESPACE__ . '\\';

		return array(
			'meta'           => array(
				'class'    => $ns . 'Meta\\MetaModule',
				'default'  => true,
				'required' => true,
				'phase'    => 1,
			),
			'analysis'       => array(
				'class'   => $ns . 'Analysis\\AnalysisModule',
				'default' => true,
				'phase'   => 1,
			),
			'social'         => array(
				'class'   => $ns . 'Social\\SocialModule',
				'default' => true,
				'phase'   => 1,
			),
			'schema'         => array(
				'class'   => $ns . 'Schema\\SchemaModule',
				'default' => true,
				'phase'   => 1,
			),
			'sitemaps'       => array(
				'class'   => $ns . 'Sitemaps\\SitemapsModule',
				'default' => true,
				'phase'   => 1,
			),
			'breadcrumbs'    => array(
				'class'   => $ns . 'Breadcrumbs\\BreadcrumbsModule',
				'default' => true,
				'phase'   => 1,
			),
			'tools'          => array(
				'class'    => $ns . 'Tools\\ToolsModule',
				'default'  => true,
				'required' => true,
				'phase'    => 1,
			),
			'redirects'      => array(
				'class'   => $ns . 'Redirects\\RedirectsModule',
				'default' => true,
				'phase'   => 2,
			),
			'images'         => array(
				'class'   => $ns . 'Images\\ImagesModule',
				'default' => true,
				'phase'   => 2,
			),
			'crawl'          => array(
				'class'   => $ns . 'Crawl\\CrawlModule',
				'default' => true,
				'phase'   => 2,
			),
			'local'          => array(
				'class'   => $ns . 'Local\\LocalModule',
				'default' => false,
				'phase'   => 2,
			),
			'woocommerce'    => array(
				'class'    => $ns . 'WooCommerce\\WooCommerceModule',
				'default'  => true,
				'phase'    => 2,
				'requires' => static fn() => class_exists( 'WooCommerce' ),
			),
			'edd'            => array(
				'class'    => $ns . 'Edd\\EddModule',
				'default'  => true,
				'phase'    => 2,
				'requires' => static fn() => class_exists( 'Easy_Digital_Downloads' ),
			),
			'links'          => array(
				'class'   => $ns . 'Links\\LinksModule',
				'default' => true,
				'phase'   => 3,
			),
			'search_console' => array(
				'class'   => $ns . 'SearchConsole\\SearchConsoleModule',
				'default' => false,
				'phase'   => 3,
			),
			'ai'             => array(
				'class'   => $ns . 'Ai\\AiModule',
				'default' => false,
				'phase'   => 3,
			),
			'abilities'      => array(
				'class'   => $ns . 'Abilities\\AbilitiesModule',
				'default' => true,
				'phase'   => 3,
			),
		);
	}

	/**
	 * Translated labels and descriptions. Call only after `init`.
	 *
	 * @return array<string, array{label: string, description: string, requires_label?: string}>
	 */
	public static function labels(): array {
		$labels = array(
			'meta'           => array(
				'label'       => __( 'Titles & Meta', 'wpinterface-seo' ),
				'description' => __( 'SEO titles, meta descriptions, robots directives and canonical URLs.', 'wpinterface-seo' ),
			),
			'analysis'       => array(
				'label'       => __( 'Content Analysis', 'wpinterface-seo' ),
				'description' => __( 'Focus keyword and readability checks in the editor. Runs in your browser.', 'wpinterface-seo' ),
			),
			'social'         => array(
				'label'       => __( 'Social', 'wpinterface-seo' ),
				'description' => __( 'Open Graph and X (Twitter) cards with live social previews.', 'wpinterface-seo' ),
			),
			'schema'         => array(
				'label'       => __( 'Schema', 'wpinterface-seo' ),
				'description' => __( 'Structured data (JSON-LD) for articles, products, recipes, events and more.', 'wpinterface-seo' ),
			),
			'sitemaps'       => array(
				'label'       => __( 'XML Sitemaps', 'wpinterface-seo' ),
				'description' => __( 'XML, image, news and video sitemaps.', 'wpinterface-seo' ),
			),
			'breadcrumbs'    => array(
				'label'       => __( 'Breadcrumbs', 'wpinterface-seo' ),
				'description' => __( 'Breadcrumb block, shortcode and template function for any theme.', 'wpinterface-seo' ),
			),
			'tools'          => array(
				'label'       => __( 'Tools', 'wpinterface-seo' ),
				'description' => __( 'Import from other SEO plugins, export settings, bulk editing and role management.', 'wpinterface-seo' ),
			),
			'redirects'      => array(
				'label'       => __( 'Redirects & 404 Log', 'wpinterface-seo' ),
				'description' => __( 'Redirect manager (301, 302, 307, 410, regex) and a 404 log.', 'wpinterface-seo' ),
			),
			'images'         => array(
				'label'       => __( 'Image SEO', 'wpinterface-seo' ),
				'description' => __( 'Automatic alt text, attachment page handling and filename cleanup.', 'wpinterface-seo' ),
			),
			'crawl'          => array(
				'label'       => __( 'Robots & Crawl', 'wpinterface-seo' ),
				'description' => __( 'robots.txt editor, crawl cleanup, IndexNow and llms.txt.', 'wpinterface-seo' ),
			),
			'local'          => array(
				'label'       => __( 'Local SEO', 'wpinterface-seo' ),
				'description' => __( 'Business details, opening hours, map and LocalBusiness schema.', 'wpinterface-seo' ),
			),
			'woocommerce'    => array(
				'label'          => __( 'WooCommerce', 'wpinterface-seo' ),
				'description'    => __( 'Product schema, GTIN/brand fields and store-specific indexing rules.', 'wpinterface-seo' ),
				'requires_label' => __( 'Requires WooCommerce.', 'wpinterface-seo' ),
			),
			'edd'            => array(
				'label'          => __( 'Easy Digital Downloads', 'wpinterface-seo' ),
				'description'    => __( 'Product schema and indexing rules for downloads.', 'wpinterface-seo' ),
				'requires_label' => __( 'Requires Easy Digital Downloads.', 'wpinterface-seo' ),
			),
			'links'          => array(
				'label'       => __( 'Internal Links', 'wpinterface-seo' ),
				'description' => __( 'Link counts and internal link suggestions while you write.', 'wpinterface-seo' ),
			),
			'search_console' => array(
				'label'       => __( 'Google Search Console', 'wpinterface-seo' ),
				'description' => __( 'Clicks, impressions, keyword positions and index status inside WordPress.', 'wpinterface-seo' ),
			),
			'ai'             => array(
				'label'       => __( 'AI Assist', 'wpinterface-seo' ),
				'description' => __( 'Generate titles, descriptions and alt text with your own AI provider.', 'wpinterface-seo' ),
			),
			'abilities'      => array(
				'label'       => __( 'Abilities & MCP', 'wpinterface-seo' ),
				'description' => __( 'Expose SEO actions to the WordPress Abilities API and AI agents.', 'wpinterface-seo' ),
			),
		);

		/**
		 * Filters module labels: ID => label, description, requires_label (optional).
		 *
		 * @param array $labels Labels.
		 */
		return (array) apply_filters( 'wpiseo_module_labels', $labels );
	}
}
