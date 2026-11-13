<?php
/**
 * Admin screens.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Admin;

use WPInterface\SEO\Breadcrumbs\Trail;
use WPInterface\SEO\Local\Business;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Modules\Crawl\CrawlModule;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Modules\Schema\SchemaModule;
use WPInterface\SEO\Modules\SearchConsole\SearchConsoleModule;
use WPInterface\SEO\Modules\Sitemaps\SitemapsModule;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Rest\SettingsController;
use WPInterface\SEO\Sitemaps\Router;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Conflicts;
use WPInterface\SEO\Support\Svg;
use WPInterface\SEO\Support\Branding;
use WPInterface\SEO\Support\Upsell;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "SEO" menu and mounts the React settings app.
 *
 * Every page renders the same root element; the app picks the view from
 * `data-page`. Pages are filterable so modules can add their own.
 */
final class Admin {

	public const MENU_SLUG = 'wpiseo';

	/**
	 * Hook suffixes of our pages, keyed by page ID.
	 *
	 * @var array<string, string>
	 */
	private array $hooks = array();

	/**
	 * Slugs of pages left out of the menu.
	 *
	 * @var list<string>
	 */
	private array $hidden = array();

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_head', array( $this, 'hide_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . WPISEO_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Admin pages: ID => [ title, menu title, capability ].
	 *
	 * The first page is the top-level menu target.
	 *
	 * @return array<string, array{title: string, menu_title: string, capability: string}>
	 */
	public function pages(): array {
		$pages = array(
			'dashboard' => array(
				'title'      => __( 'SEO Dashboard', 'wpinterface-seo' ),
				'menu_title' => __( 'Dashboard', 'wpinterface-seo' ),
				'capability' => Capabilities::MANAGE_SETTINGS,
			),
			'general'   => array(
				'title'      => __( 'General Settings', 'wpinterface-seo' ),
				'menu_title' => __( 'Settings', 'wpinterface-seo' ),
				'capability' => Capabilities::MANAGE_SETTINGS,
			),
			'modules'   => array(
				'title'      => __( 'Modules', 'wpinterface-seo' ),
				'menu_title' => __( 'Modules', 'wpinterface-seo' ),
				'capability' => Capabilities::MANAGE_SETTINGS,
			),
		);

		/**
		 * Filters the admin pages under the SEO menu.
		 *
		 * Add-on pages may set `position` (a number) to sit between built-in
		 * pages: dashboard is 0, search-console 1 … modules 16, setup 17.
		 *
		 * @param array $pages Page ID => [ title, menu_title, capability, hidden?, position? ].
		 */
		$pages = (array) apply_filters( 'wpiseo_admin_pages', $pages );
		$order = array( 'dashboard', 'search-console', 'search-appearance', 'social', 'schema', 'sitemaps', 'breadcrumbs', 'redirects', '404-log', 'images', 'crawl', 'local', 'bulk-editor', 'ai', 'tools', 'general', 'modules', 'setup' );

		$position = static function ( $id ) use ( $order, $pages ): float {
			if ( isset( $pages[ $id ]['position'] ) && is_numeric( $pages[ $id ]['position'] ) ) {
				return (float) $pages[ $id ]['position'];
			}

			$found = array_search( $id, $order, true );

			return false === $found ? 50.0 : (float) $found;
		};

		uksort(
			$pages,
			static fn( $a, $b ): int => $position( $a ) <=> $position( $b )
		);

		return $pages;
	}

	/**
	 * Registers the menu and sub-pages.
	 */
	public function register_menu(): void {
		$pages = $this->pages();
		$first = (string) array_key_first( $pages );

		if ( '' === $first ) {
			return;
		}

		$this->hooks[ $first ] = (string) add_menu_page(
			$pages[ $first ]['title'],
			Branding::all()['menu_title'],
			$pages[ $first ]['capability'],
			self::MENU_SLUG,
			array( $this, 'render' ),
			$this->menu_icon(),
			80
		);

		foreach ( $pages as $id => $page ) {
			$slug = $id === $first ? self::MENU_SLUG : self::MENU_SLUG . '-' . $id;

			$this->hooks[ $id ] = (string) add_submenu_page(
				self::MENU_SLUG,
				$page['title'],
				$page['menu_title'],
				$page['capability'],
				$slug,
				array( $this, 'render' )
			);

			if ( ! empty( $page['hidden'] ) && $id !== $first ) {
				$this->hidden[] = $slug;
			}
		}
	}

	/**
	 * Removes hidden pages from the menu. Runs on `admin_head`: after
	 * WordPress checked access and set the page title (both need the menu
	 * entry), before the menu is printed. Hidden pages stay reachable by URL.
	 */
	public function hide_pages(): void {
		foreach ( $this->hidden as $slug ) {
			remove_submenu_page( self::MENU_SLUG, $slug );
		}
	}

	/**
	 * Renders the page header and the app root for the current page.
	 *
	 * The header is printed here rather than by the app so it shows at once
	 * and WordPress places admin notices below it (after .wp-header-end).
	 * Pages add buttons to #wpiseo-header-actions.
	 */
	public function render(): void {
		$page_id  = $this->current_page_id();
		$page     = $this->pages()[ $page_id ] ?? array();
		$branding = Branding::all();

		echo '<div class="wpiseo-topbar">';
		printf(
			'<div class="wpiseo-topbar__title"><span class="wpiseo-topbar__mark" aria-hidden="true">%1$s</span><span class="wpiseo-topbar__brand">%2$s</span><span class="wpiseo-topbar__sep" aria-hidden="true">/</span><h1>%3$s</h1></div>',
			wp_kses( self::icon_svg( '#fff' ), Svg::allowed() ),
			esc_html( $branding['name'] ),
			esc_html( (string) ( $page['menu_title'] ?? '' ) )
		);
		echo '<div class="wpiseo-topbar__actions"><div id="wpiseo-header-actions" class="wpiseo-topbar__slot"></div>';

		if ( '' !== $branding['help_url'] ) {
			printf(
				'<a href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a>',
				esc_url( $branding['help_url'] ),
				esc_html__( 'Help', 'wpinterface-seo' ),
				esc_html__( '(opens in a new tab)', 'wpinterface-seo' )
			);
		}

		if ( $branding['show_version'] ) {
			printf( '<span class="wpiseo-topbar__version">%s</span>', esc_html( WPISEO_VERSION ) );
		}

		echo '</div></div>';
		echo '<div class="wrap wpiseo-wrap"><hr class="wp-header-end">';

		if ( ! is_readable( WPISEO_PATH . 'assets/build/admin.asset.php' ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'WPInterface SEO admin assets are missing. Run "npm install && npm run build" in the plugin folder.', 'wpinterface-seo' )
			);
		}

		printf(
			'<div id="wpiseo-admin-root" data-page="%s"><p class="wpiseo-loading">%s</p></div>',
			esc_attr( $page_id ),
			esc_html__( 'Loading…', 'wpinterface-seo' )
		);

		echo '</div>';
	}

	/**
	 * Enqueues the settings app on our pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->hooks, true ) ) {
			return;
		}

		$asset_file = WPISEO_PATH . 'assets/build/admin.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wpiseo-admin',
			WPISEO_URL . 'assets/build/admin.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? WPISEO_VERSION,
			array( 'in_footer' => true )
		);

		if ( is_readable( WPISEO_PATH . 'assets/build/admin.css' ) ) {
			wp_enqueue_style( 'wpiseo-admin', WPISEO_URL . 'assets/build/admin.css', array( 'wp-components' ), $asset['version'] ?? WPISEO_VERSION );
			wp_style_add_data( 'wpiseo-admin', 'rtl', 'replace' );
		}

		wp_enqueue_media();
		wp_set_script_translations( 'wpiseo-admin', 'wpinterface-seo', WPISEO_PATH . 'languages' );

		wp_add_inline_script(
			'wpiseo-admin',
			'window.wpiseoAdmin = ' . wp_json_encode( $this->script_data(), JSON_HEX_TAG ) . ';',
			'before'
		);

		/**
		 * Fires after the admin app is enqueued on one of the plugin's pages.
		 * Add-ons enqueue their page scripts here (depend on `wpiseo-admin`).
		 *
		 * @param string $page_id Current page ID.
		 */
		do_action( 'wpiseo_admin_enqueue_scripts', $this->current_page_id() );
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param array<string> $links Links.
	 * @return array<string>
	 */
	public function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();

		if ( current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-general' ) ),
					esc_html__( 'Settings', 'wpinterface-seo' )
				)
			);
		}

		if ( Upsell::enabled() ) {
			$links[] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a>',
				esc_url( Upsell::url( 'plugins' ) ),
				esc_html__( 'Get Pro', 'wpinterface-seo' ),
				esc_html__( '(opens in a new tab)', 'wpinterface-seo' )
			);
		}

		return $links;
	}

	/**
	 * Data passed to the React app.
	 *
	 * @return array<string, mixed>
	 */
	private function script_data(): array {
		$pages = array();

		foreach ( $this->pages() as $id => $page ) {
			if ( ! current_user_can( $page['capability'] ) ) {
				continue;
			}

			$pages[] = array(
				'id'    => $id,
				'title' => $page['title'],
				'url'   => admin_url( 'admin.php?page=' . ( 'dashboard' === $id ? self::MENU_SLUG : self::MENU_SLUG . '-' . $id ) ),
			);
		}

		$plugin     = Plugin::instance();
		$post_types = array();
		$taxonomies = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$type_taxonomies = array();

			foreach ( get_object_taxonomies( $type->name, 'objects' ) as $taxonomy ) {
				if ( $taxonomy->public && $taxonomy->hierarchical ) {
					$type_taxonomies[] = array(
						'name'  => $taxonomy->name,
						'label' => $taxonomy->labels->singular_name,
					);
				}
			}

			$post_types[] = array(
				'name'       => $type->name,
				'label'      => $type->labels->name,
				'singular'   => $type->labels->singular_name,
				'hasArchive' => (bool) $type->has_archive,
				'taxonomies' => $type_taxonomies,
			);
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$taxonomies[] = array(
				'name'  => $taxonomy->name,
				'label' => $taxonomy->labels->name,
			);
		}

		$options   = $plugin->options();
		$effective = array(
			'postTypes'        => array(),
			'postTypeArchives' => array(),
			'taxonomies'       => array(),
			'archives'         => array(),
			'schema'           => array(),
			'sitemapTypes'     => array(),
			'sitemapTaxes'     => array(),
			'breadcrumbTaxes'  => array(),
		);

		foreach ( $post_types as $type ) {
			$name = $type['name'];

			$effective['postTypes'][ $name ]        = MetaModule::post_type( $options, $name );
			$effective['postTypeArchives'][ $name ] = MetaModule::post_type_archive( $options, $name );
			$effective['schema'][ $name ]           = SchemaModule::post_type( $options, $name );
			$effective['sitemapTypes'][ $name ]     = SitemapsModule::includes_post_type( $options, $name );
			$effective['breadcrumbTaxes'][ $name ]  = Trail::taxonomy_for( $options, $name );
		}

		foreach ( $taxonomies as $taxonomy ) {
			$effective['taxonomies'][ $taxonomy['name'] ]   = MetaModule::taxonomy( $options, $taxonomy['name'] );
			$effective['sitemapTaxes'][ $taxonomy['name'] ] = SitemapsModule::includes_taxonomy( $options, $taxonomy['name'] );
		}

		foreach ( array_keys( MetaModule::default_settings()['archives'] ) as $archive ) {
			$effective['archives'][ $archive ] = MetaModule::archive( $options, $archive );
		}

		$variables = array();
		foreach ( $plugin->variables()->all() as $name => $variable ) {
			$variables[] = array(
				'name'    => $name,
				'label'   => $variable['label'],
				'example' => $variable['example'],
			);
		}

		$users = array();
		foreach ( get_users(
			array(
				'capability' => array( 'edit_posts' ),
				'number'     => 200,
				'fields'     => array( 'ID', 'display_name' ),
			)
		) as $user ) {
			$users[] = array(
				'id'   => (int) $user->ID,
				'name' => (string) $user->display_name,
			);
		}

		return array(
			'gscNotice'      => 'search-console' === $this->current_page_id() ? SearchConsoleModule::pop_notice() : null,
			'pages'          => $pages,
			'restNamespace'  => SettingsController::REST_NAMESPACE,
			'version'        => WPISEO_VERSION,
			'locale'         => str_replace( '_', '-', get_user_locale() ),
			'brand'          => array( 'name' => Branding::name() ),
			'siteName'       => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'postTypes'      => $post_types,
			'effective'      => $effective,
			'variables'      => $variables,
			'networks'       => Fields::NETWORKS,
			'networkLabels'  => UserFields::labels(),
			'pageTypes'      => Fields::PAGE_TYPES,
			'articleTypes'   => Fields::ARTICLE_TYPES,
			'can'            => array(
				'manageSettings'  => current_user_can( Capabilities::MANAGE_SETTINGS ),
				'manageRedirects' => current_user_can( Capabilities::MANAGE_REDIRECTS ),
				'editSeo'         => current_user_can( Capabilities::EDIT_SEO ),
			),
			'businessTypes'  => Business::TYPES,
			'aiBots'         => CrawlModule::AI_TRAINING_BOTS,
			'robotsUrl'      => home_url( '/robots.txt' ),
			'physicalRobots' => file_exists( ABSPATH . 'robots.txt' ),
			'homeUrl'        => home_url( '/' ),
			'taxonomies'     => $taxonomies,
			'users'          => $users,
			'conflicts'      => array_values( Conflicts::active() ),
			'linkIndex'      => null !== $plugin->modules()->get( 'links' ),
			'pro'            => Upsell::enabled() ? array(
				'url'    => Upsell::url( 'admin' ),
				'hidden' => Upsell::hidden(),
			) : null,
			'sitemapUrl'     => $plugin->modules()->get( 'sitemaps' ) ? Router::index_url() : '',
		);
	}

	/**
	 * Page ID from the `page` query arg.
	 */
	private function current_page_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::MENU_SLUG;
		$id   = self::MENU_SLUG === $slug ? 'dashboard' : (string) preg_replace( '/^' . self::MENU_SLUG . '-/', '', $slug );

		return array_key_exists( $id, $this->pages() ) ? $id : 'dashboard';
	}

	/**
	 * Base64 SVG menu icon (magnifier over a bar chart) that inherits admin colours.
	 */
	private function menu_icon(): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Data URI for the menu icon.
		return 'data:image/svg+xml;base64,' . base64_encode( self::icon_svg( 'black' ) );
	}

	/**
	 * The plugin icon (magnifier over a bar chart).
	 *
	 * @param string $fill Fill colour.
	 */
	private static function icon_svg( string $fill ): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20"><path fill="' . $fill . '" d="M8.5 2a6.5 6.5 0 0 1 5.18 10.43l4.45 4.44-1.26 1.26-4.44-4.45A6.5 6.5 0 1 1 8.5 2Zm0 1.8a4.7 4.7 0 1 0 0 9.4 4.7 4.7 0 0 0 0-9.4ZM6 9h1.5v2.5H6V9Zm2-2h1.5v4.5H8V7Zm2 1h1.5v3.5H10V8Z"/></svg>';
	}
}
