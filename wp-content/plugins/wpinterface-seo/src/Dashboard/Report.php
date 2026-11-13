<?php
/**
 * Dashboard report.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Dashboard;

use WPInterface\SEO\Admin\Admin;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the dashboard figures into what the dashboard shows: next steps,
 * site checks and the setup checklist, in the current user's language.
 */
final class Report {

	/**
	 * User meta flag: the setup checklist was hidden.
	 */
	public const HIDE_SETUP_META = '_wpiseo_hide_setup';

	private const SEVERITY_ORDER = array(
		'critical' => 0,
		'warning'  => 1,
		'notice'   => 2,
	);

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Everything the dashboard needs.
	 *
	 * @param bool $fresh Rebuild the cached figures.
	 * @return array<string, mixed>
	 */
	public function build( bool $fresh = false ): array {
		$stats = ( new Stats( $this->plugin ) )->get( $fresh );

		return array_merge(
			$stats,
			array(
				'insights' => $this->insights( $stats ),
				'checks'   => $this->checks( $stats['site'] ),
				'setup'    => array(
					'tasks'  => $this->setup_tasks( $stats ),
					'hidden' => (bool) get_user_meta( get_current_user_id(), self::HIDE_SETUP_META, true ),
				),
				'newest'   => $this->newest_post( (int) ( $stats['content']['newest'] ?? 0 ) ),
			)
		);
	}

	/**
	 * Next steps, most important first.
	 *
	 * @param array<string, mixed> $stats Figures.
	 * @return list<array{id: string, severity: string, title: string, help: string, action: array{label: string, url: string}|null}>
	 */
	public function insights( array $stats ): array {
		$site     = $stats['site'];
		$content  = $stats['content'];
		$modules  = $this->plugin->modules();
		$insights = array();

		if ( ! $site['blogPublic'] ) {
			$insights[] = array(
				'id'       => 'indexing_off',
				'severity' => 'critical',
				'title'    => __( 'Search engines are asked not to index this site', 'wpinterface-seo' ),
				'help'     => __( '“Discourage search engines from indexing this site” is turned on, so new pages will not appear in search results.', 'wpinterface-seo' ),
				'action'   => array(
					'label' => __( 'Change setting', 'wpinterface-seo' ),
					'url'   => admin_url( 'options-reading.php' ),
				),
			);
		}

		if ( ! $site['outputEnabled'] && array() !== $site['conflicts'] ) {
			$insights[] = array(
				'id'       => 'output_paused',
				'severity' => 'critical',
				'title'    => sprintf(
					/* translators: 1: this plugin's name, 2: other plugin names. */
					__( '%1$s is paused because %2$s is active', 'wpinterface-seo' ),
					Branding::name(),
					implode( ', ', $site['conflicts'] )
				),
				'help'     => __( 'Import your data, then deactivate the other plugin to turn on titles, schema and sitemaps.', 'wpinterface-seo' ),
				'action'   => array(
					'label' => __( 'Go to Plugins', 'wpinterface-seo' ),
					'url'   => admin_url( 'plugins.php' ),
				),
			);
		}

		$tools = null !== $modules->get( 'tools' );

		if ( $content['bad'] > 0 ) {
			$insights[] = array(
				'id'       => 'poor_scores',
				'severity' => 'warning',
				'title'    => sprintf(
					/* translators: %s: number of posts. */
					_n( '%s post has a poor SEO score', '%s posts have a poor SEO score', $content['bad'], 'wpinterface-seo' ),
					number_format_i18n( $content['bad'] )
				),
				'help'     => __( 'Open them and work through the suggestions in the SEO panel.', 'wpinterface-seo' ),
				'action'   => $this->content_action( __( 'Review posts', 'wpinterface-seo' ), 'poor', $tools ),
			);
		}

		if ( $content['noDescription'] > 0 ) {
			$insights[] = array(
				'id'       => 'no_description',
				'severity' => 'warning',
				'title'    => sprintf(
					/* translators: %s: number of posts. */
					_n( '%s post has no meta description', '%s posts have no meta description', $content['noDescription'], 'wpinterface-seo' ),
					number_format_i18n( $content['noDescription'] )
				),
				'help'     => __( 'Google writes its own snippet for these, often a poor one.', 'wpinterface-seo' ),
				'action'   => $this->content_action( __( 'Write descriptions', 'wpinterface-seo' ), 'no_description', $tools ),
			);
		}

		if ( ! empty( $stats['search']['decay'] ) ) {
			$insights[] = array(
				'id'       => 'decay',
				'severity' => 'warning',
				'title'    => sprintf(
					/* translators: 1: number of pages, 2: percentage. */
					_n( '%1$s page lost over %2$s%% of its clicks', '%1$s pages lost over %2$s%% of their clicks', $stats['search']['decay'], 'wpinterface-seo' ),
					number_format_i18n( $stats['search']['decay'] ),
					number_format_i18n( $stats['search']['threshold'] )
				),
				'help'     => __( 'Compared with the previous 28 days. Updating the content often brings clicks back.', 'wpinterface-seo' ),
				'action'   => array(
					'label' => __( 'See pages', 'wpinterface-seo' ),
					'url'   => self::page_url( 'search-console' ),
				),
			);
		}

		if ( ! empty( $stats['notFound']['seen'] ) ) {
			$insights[] = array(
				'id'       => 'not_found',
				'severity' => 'warning',
				'title'    => sprintf(
					/* translators: %s: number of URLs. */
					_n( '%s missing page was visited this week', '%s missing pages were visited this week', $stats['notFound']['seen'], 'wpinterface-seo' ),
					number_format_i18n( $stats['notFound']['seen'] )
				),
				'help'     => __( 'Redirect them to the right page so visitors and links are not lost.', 'wpinterface-seo' ),
				'action'   => array(
					'label' => __( 'Add redirects', 'wpinterface-seo' ),
					'url'   => self::page_url( '404-log' ),
				),
			);
		}

		if ( ! empty( $content['orphans'] ) ) {
			$insights[] = array(
				'id'       => 'orphans',
				'severity' => 'notice',
				'title'    => sprintf(
					/* translators: %s: number of posts. */
					_n( '%s post has no links pointing to it', '%s posts have no links pointing to them', $content['orphans'], 'wpinterface-seo' ),
					number_format_i18n( $content['orphans'] )
				),
				'help'     => __( 'Link to them from related posts so readers and search engines find them.', 'wpinterface-seo' ),
				'action'   => $this->content_action( __( 'Find them', 'wpinterface-seo' ), 'orphan', $tools ),
			);
		}

		if ( $content['noKeyword'] > 0 && $content['noKeyword'] < $content['published'] ) {
			$insights[] = array(
				'id'       => 'no_keyword',
				'severity' => 'notice',
				'title'    => sprintf(
					/* translators: %s: number of posts. */
					_n( '%s post has no focus keyword', '%s posts have no focus keyword', $content['noKeyword'], 'wpinterface-seo' ),
					number_format_i18n( $content['noKeyword'] )
				),
				'help'     => __( 'With a focus keyword, the analysis checks each post against what people search for.', 'wpinterface-seo' ),
				'action'   => $this->content_action( __( 'Review posts', 'wpinterface-seo' ), 'no_keyword', $tools ),
			);
		}

		/**
		 * Filters the dashboard's next steps. Each item: id, severity
		 * (critical|warning|notice), title, help and action { label, url }.
		 * They are sorted by severity afterwards; items of equal severity keep
		 * their order.
		 *
		 * @param array                $insights Next steps.
		 * @param array<string, mixed> $stats    Dashboard figures.
		 */
		$insights = (array) apply_filters( 'wpiseo_dashboard_insights', $insights, $stats );
		$sorted   = array();

		foreach ( array_values( array_filter( $insights, array( self::class, 'valid_insight' ) ) ) as $index => $insight ) {
			$sorted[ sprintf( '%d-%04d', self::SEVERITY_ORDER[ $insight['severity'] ], $index ) ] = array(
				'id'       => (string) $insight['id'],
				'severity' => (string) $insight['severity'],
				'title'    => (string) $insight['title'],
				'help'     => (string) ( $insight['help'] ?? '' ),
				'action'   => isset( $insight['action']['label'], $insight['action']['url'] ) ? array(
					'label' => (string) $insight['action']['label'],
					'url'   => (string) $insight['action']['url'],
				) : null,
			);
		}

		ksort( $sorted );

		$insights = array_values( $sorted );

		return $insights;
	}

	/**
	 * Site checks: settings that affect every page.
	 *
	 * @param array<string, mixed> $site Site figures.
	 * @return list<array{id: string, ok: bool, label: string, help: string, fix: array{label: string, url: string}|null}>
	 */
	public function checks( array $site ): array {
		$checks = array(
			array(
				'id'    => 'indexing',
				'ok'    => $site['blogPublic'],
				'label' => $site['blogPublic'] ? __( 'Search engines can index this site', 'wpinterface-seo' ) : __( 'Search engines are asked not to index this site', 'wpinterface-seo' ),
				'help'  => __( '“Discourage search engines” is on in Reading settings.', 'wpinterface-seo' ),
				'fix'   => array(
					'label' => __( 'Change setting', 'wpinterface-seo' ),
					'url'   => admin_url( 'options-reading.php' ),
				),
			),
			array(
				'id'    => 'permalinks',
				'ok'    => $site['permalinks'],
				'label' => $site['permalinks'] ? __( 'Pretty permalinks are on', 'wpinterface-seo' ) : __( 'Permalinks are set to Plain', 'wpinterface-seo' ),
				'help'  => __( 'URLs like ?p=12 say nothing about the page.', 'wpinterface-seo' ),
				'fix'   => array(
					'label' => __( 'Change permalinks', 'wpinterface-seo' ),
					'url'   => admin_url( 'options-permalink.php' ),
				),
			),
			array(
				'id'    => 'title_tag',
				'ok'    => $site['titleTag'],
				'label' => $site['titleTag'] ? __( 'Theme lets WordPress set page titles', 'wpinterface-seo' ) : __( 'Theme does not let WordPress set page titles', 'wpinterface-seo' ),
				'help'  => __( 'SEO titles may not appear. Ask the theme author to add title-tag support.', 'wpinterface-seo' ),
				'fix'   => null,
			),
			array(
				'id'    => 'https',
				'ok'    => $site['https'],
				'label' => $site['https'] ? __( 'Site uses HTTPS', 'wpinterface-seo' ) : __( 'Site does not use HTTPS', 'wpinterface-seo' ),
				'help'  => __( 'Browsers mark plain HTTP pages as not secure.', 'wpinterface-seo' ),
				'fix'   => array(
					'label' => __( 'Site Health', 'wpinterface-seo' ),
					'url'   => admin_url( 'site-health.php' ),
				),
			),
			array(
				'id'    => 'sitemap',
				'ok'    => $site['sitemap'],
				'label' => $site['sitemap'] ? __( 'XML sitemap is active', 'wpinterface-seo' ) : __( 'XML sitemap is off', 'wpinterface-seo' ),
				'help'  => __( 'Search engines find new content faster with a sitemap.', 'wpinterface-seo' ),
				'fix'   => array(
					'label' => __( 'Turn it on', 'wpinterface-seo' ),
					'url'   => self::page_url( 'modules' ),
				),
			),
			array(
				'id'    => 'conflicts',
				'ok'    => array() === $site['conflicts'],
				'label' => array() === $site['conflicts']
					? __( 'No other SEO plugin is active', 'wpinterface-seo' )
					: sprintf(
						/* translators: %s: plugin names. */
						__( '%s is also active', 'wpinterface-seo' ),
						implode( ', ', $site['conflicts'] )
					),
				'help'  => __( 'Two SEO plugins print duplicate tags.', 'wpinterface-seo' ),
				'fix'   => array(
					'label' => __( 'Go to Plugins', 'wpinterface-seo' ),
					'url'   => admin_url( 'plugins.php' ),
				),
			),
		);

		if ( $site['gscModule'] ) {
			$checks[] = array(
				'id'    => 'search_console',
				'ok'    => $site['gscConnected'],
				'label' => $site['gscConnected'] ? __( 'Search Console is connected', 'wpinterface-seo' ) : __( 'Search Console is not connected', 'wpinterface-seo' ),
				'help'  => '',
				'fix'   => array(
					'label' => __( 'Connect', 'wpinterface-seo' ),
					'url'   => self::page_url( 'search-console' ),
				),
			);
		}

		if ( $site['socialModule'] ) {
			$checks[] = array(
				'id'    => 'social_image',
				'ok'    => $site['socialImage'],
				'label' => $site['socialImage'] ? __( 'Default social image is set', 'wpinterface-seo' ) : __( 'No default social image', 'wpinterface-seo' ),
				'help'  => __( 'Shares of pages without a featured image show no picture.', 'wpinterface-seo' ),
				'fix'   => array(
					'label' => __( 'Add one', 'wpinterface-seo' ),
					'url'   => self::page_url( 'social' ),
				),
			);
		}

		return $checks;
	}

	/**
	 * Setup checklist for new sites.
	 *
	 * @param array<string, mixed> $stats Figures.
	 * @return list<array{id: string, done: bool, label: string, help: string, action: array{label: string, url: string}|null}>
	 */
	public function setup_tasks( array $stats ): array {
		$site    = $stats['site'];
		$modules = $this->plugin->modules();
		$tools   = null !== $modules->get( 'tools' );
		$tasks   = array();

		if ( $tools ) {
			$tasks[] = array(
				'id'     => 'wizard',
				'done'   => $site['setupDone'],
				'label'  => __( 'Run the setup wizard', 'wpinterface-seo' ),
				'help'   => $site['setupDone']
					? __( 'Site name, logo and social profiles are set.', 'wpinterface-seo' )
					: __( 'Set your site name, logo and social profiles, and import data from another SEO plugin.', 'wpinterface-seo' ),
				'action' => array(
					'label' => __( 'Start', 'wpinterface-seo' ),
					'url'   => self::page_url( 'setup' ),
				),
			);
		}

		if ( $site['gscModule'] ) {
			$tasks[] = array(
				'id'     => 'search_console',
				'done'   => $site['gscConnected'],
				'label'  => __( 'Connect Google Search Console', 'wpinterface-seo' ),
				'help'   => __( 'See clicks, searches and indexing problems in WordPress.', 'wpinterface-seo' ),
				'action' => array(
					'label' => __( 'Connect', 'wpinterface-seo' ),
					'url'   => self::page_url( 'search-console' ),
				),
			);
		}

		$missing = (int) ( $stats['content']['pagesNoDesc'] ?? 0 );

		if ( $tools && post_type_exists( 'page' ) ) {
			$tasks[] = array(
				'id'     => 'descriptions',
				'done'   => 0 === $missing,
				'label'  => __( 'Write descriptions for your main pages', 'wpinterface-seo' ),
				'help'   => 0 === $missing
					? __( 'Every published page has a meta description.', 'wpinterface-seo' )
					: sprintf(
						/* translators: %s: number of pages. */
						_n( '%s published page uses automatic text.', '%s published pages use automatic text.', $missing, 'wpinterface-seo' ),
						number_format_i18n( $missing )
					),
				'action' => array(
					'label' => __( 'Open Bulk Editor', 'wpinterface-seo' ),
					'url'   => self::page_url(
						'bulk-editor',
						array(
							'post_type' => 'page',
							'show'      => 'no_description',
						)
					),
				),
			);
		}

		if ( $site['socialModule'] ) {
			$tasks[] = array(
				'id'     => 'social_image',
				'done'   => $site['socialImage'],
				'label'  => __( 'Choose a default social image', 'wpinterface-seo' ),
				'help'   => __( 'Used when you share a page that has no featured image.', 'wpinterface-seo' ),
				'action' => array(
					'label' => __( 'Choose image', 'wpinterface-seo' ),
					'url'   => self::page_url( 'social' ),
				),
			);
		}

		return $tasks;
	}

	/**
	 * URL of one of the plugin's admin pages.
	 *
	 * @param string                $id   Page ID.
	 * @param array<string, string> $args Extra query args.
	 */
	public static function page_url( string $id, array $args = array() ): string {
		$slug = 'dashboard' === $id ? Admin::MENU_SLUG : Admin::MENU_SLUG . '-' . $id;

		return add_query_arg( $args, admin_url( 'admin.php?page=' . $slug ) );
	}

	/**
	 * Link to the Bulk Editor filtered to the posts an insight counts.
	 *
	 * @param string $label Action label.
	 * @param string $show  Bulk Editor filter.
	 * @param bool   $tools Whether the Bulk Editor is available.
	 * @return array{label: string, url: string}
	 */
	private function content_action( string $label, string $show, bool $tools ): array {
		return array(
			'label' => $label,
			'url'   => $tools ? self::page_url( 'bulk-editor', array( 'show' => $show ) ) : admin_url( 'edit.php' ),
		);
	}

	/**
	 * Newest published post, for the first-run "open a post" button.
	 *
	 * @param int $post_id Post ID.
	 * @return array{title: string, edit: string}|null
	 */
	private function newest_post( int $post_id ): ?array {
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}

		return array(
			'title' => wp_strip_all_tags( get_the_title( $post_id ) ),
			'edit'  => (string) get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * Whether a filtered insight has the required shape.
	 *
	 * @param mixed $insight Item.
	 */
	private static function valid_insight( $insight ): bool {
		return is_array( $insight )
			&& isset( $insight['id'], $insight['title'], $insight['severity'] )
			&& isset( self::SEVERITY_ORDER[ $insight['severity'] ] );
	}
}
