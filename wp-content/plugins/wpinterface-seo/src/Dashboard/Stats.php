<?php
/**
 * Dashboard figures.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Dashboard;

use WPInterface\SEO\Editor\Editor;
use WPInterface\SEO\Links\Indexer;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Modules\Links\LinksModule;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Modules\Sitemaps\SitemapsModule;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Redirects\NotFoundLog;
use WPInterface\SEO\Redirects\Repository;
use WPInterface\SEO\SearchConsole\Auth;
use WPInterface\SEO\SearchConsole\Store;
use WPInterface\SEO\Sitemaps\Router;
use WPInterface\SEO\Support\Conflicts;

defined( 'ABSPATH' ) || exit;

/**
 * Collects the numbers behind the SEO dashboard and the Dashboard widget.
 *
 * Only raw values are stored (no translated text), so one cached copy
 * serves every user and locale. Cached for ten minutes; the dashboard's
 * Refresh button rebuilds it.
 */
final class Stats {

	public const TRANSIENT = 'wpiseo_dashboard';

	private const TTL = 10 * MINUTE_IN_SECONDS;

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
	 * Figures: the cached ones (rebuilt when missing or when $fresh is true)
	 * plus the site settings, which are cheap and always read live.
	 *
	 * @param bool $fresh Ignore the cache.
	 * @return array<string, mixed>
	 */
	public function get( bool $fresh = false ): array {
		$stats = $fresh ? false : get_transient( self::TRANSIENT );

		if ( ! is_array( $stats ) ) {
			$stats = $this->collect();
			set_transient( self::TRANSIENT, $stats, self::TTL );
		}

		return array( 'site' => $this->site() ) + $stats;
	}

	/**
	 * Drops the cached figures.
	 */
	public static function flush(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Builds the cached figures.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		$stats = array(
			'generated' => time(),
			'content'   => $this->content(),
			'search'    => $this->search(),
			'notFound'  => $this->not_found(),
			'redirects' => $this->redirects(),
			'sitemap'   => $this->sitemap(),
		);

		/**
		 * Filters the dashboard figures before they are cached. Add-ons add
		 * their own keys here and turn them into next steps with the
		 * `wpiseo_dashboard_insights` filter. Store numbers, not text: the
		 * result is shared by every user.
		 *
		 * @param array<string, mixed> $stats Figures.
		 */
		return (array) apply_filters( 'wpiseo_dashboard_stats', $stats );
	}

	/**
	 * Published content per post type: scores, missing fields, noindex, orphans.
	 *
	 * @return array<string, mixed>
	 */
	private function content(): array {
		global $wpdb;

		$options    = $this->plugin->options();
		$post_types = Editor::post_types();
		$totals     = array(
			'published'     => 0,
			'indexable'     => 0,
			'noindex'       => 0,
			'good'          => 0,
			'ok'            => 0,
			'bad'           => 0,
			'none'          => 0,
			'scoreSum'      => 0,
			'noDescription' => 0,
			'noKeyword'     => 0,
			'orphans'       => null,
			'links'         => null,
			'pagesNoDesc'   => 0,
			'newest'        => 0,
		);
		$types      = array();

		if ( array() === $post_types ) {
			return array( 'types' => $types ) + $totals;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$prefix       = Fields::PREFIX;

		// One pass over published posts; each LEFT JOIN reads one meta key.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders built above; cached by get().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.post_type,
					COUNT(*) AS published,
					SUM(s.meta_value IS NULL) AS unscored,
					SUM(CAST(s.meta_value AS UNSIGNED) >= 70) AS good,
					SUM(CAST(s.meta_value AS UNSIGNED) BETWEEN 40 AND 69) AS ok,
					SUM(CAST(s.meta_value AS UNSIGNED) < 40) AS bad,
					SUM(CAST(s.meta_value AS UNSIGNED)) AS score_sum,
					SUM(d.meta_id IS NULL) AS no_description,
					SUM(k.meta_id IS NULL) AS no_keyword,
					SUM(n.meta_value = 'noindex') AS noindex,
					SUM(n.meta_value = 'index') AS forced_index
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} k ON k.post_id = p.ID AND k.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = %s
				WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
				GROUP BY p.post_type",
				array_merge( array( $prefix . 'score', $prefix . 'description', $prefix . 'focus_keywords', $prefix . 'noindex' ), $post_types )
			),
			ARRAY_A
		);
		// phpcs:enable

		$orphans = $this->orphans( $post_types );

		if ( null !== $orphans ) {
			$totals['links'] = $this->link_counts();
		}

		foreach ( (array) $rows as $row ) {
			$name      = (string) $row['post_type'];
			$published = (int) $row['published'];
			$noindex   = MetaModule::post_type( $options, $name )['noindex']
				? $published - (int) $row['forced_index']
				: (int) $row['noindex'];

			$type = array(
				'name'          => $name,
				'published'     => $published,
				'good'          => (int) $row['good'],
				'ok'            => (int) $row['ok'],
				'bad'           => (int) $row['bad'],
				'none'          => (int) $row['unscored'],
				'scoreSum'      => (int) $row['score_sum'],
				'noDescription' => (int) $row['no_description'],
				'noKeyword'     => (int) $row['no_keyword'],
				'noindex'       => $noindex,
				'orphans'       => null === $orphans ? null : (int) ( $orphans[ $name ] ?? 0 ),
			);

			$types[] = $type;

			foreach ( array( 'published', 'good', 'ok', 'bad', 'none', 'scoreSum', 'noDescription', 'noKeyword', 'noindex' ) as $key ) {
				$totals[ $key ] += $type[ $key ];
			}

			if ( null !== $orphans ) {
				$totals['orphans'] = (int) $totals['orphans'] + (int) $type['orphans'];
			}

			if ( 'page' === $name ) {
				$totals['pagesNoDesc'] = $type['noDescription'];
			}
		}

		// Biggest post types first, like the admin menu would rank them by use.
		usort( $types, static fn( array $a, array $b ): int => $b['published'] <=> $a['published'] );

		$totals['indexable'] = $totals['published'] - $totals['noindex'];
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders built above; cached by get().
		$totals['newest'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders}) ORDER BY post_date DESC LIMIT 1", $post_types )
		);
		// phpcs:enable

		return array( 'types' => $types ) + $totals;
	}

	/**
	 * Published posts no other published post links to, per post type.
	 * Null while the link index is off or still being built.
	 *
	 * @param string[] $post_types Post types.
	 * @return array<string, int>|null
	 */
	private function orphans( array $post_types ): ?array {
		global $wpdb;

		if ( ! $this->plugin->modules()->get( 'links' ) || false !== get_option( LinksModule::REBUILD_OPTION ) ) {
			return null;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders built above; cached by get().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.post_type, COUNT(*) AS total FROM {$wpdb->posts} p
				WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}) AND p.ID <> %d
				AND NOT EXISTS (
					SELECT 1 FROM %i l INNER JOIN {$wpdb->posts} src ON src.ID = l.post_id AND src.post_status = 'publish'
					WHERE l.target_post_id = p.ID AND l.post_id <> p.ID
				)
				GROUP BY p.post_type",
				array_merge( $post_types, array( (int) get_option( 'page_on_front' ), Indexer::table() ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['post_type'] ] = (int) $row['total'];
		}

		return $out;
	}

	/**
	 * Internal and external links in published content (from the link index).
	 *
	 * @return array{internal: int, external: int}
	 */
	private function link_counts(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cached by get().
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT type, COUNT(*) AS total FROM %i GROUP BY type', Indexer::table() ), ARRAY_A );
		$counts = array(
			'internal' => 0,
			'external' => 0,
		);

		foreach ( (array) $rows as $row ) {
			$counts[ 'internal' === $row['type'] ? 'internal' : 'external' ] += (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Site settings that affect every page.
	 *
	 * @return array<string, mixed>
	 */
	private function site(): array {
		$options = $this->plugin->options();
		$modules = $this->plugin->modules();
		$auth    = new Auth();

		return array(
			'blogPublic'    => (bool) get_option( 'blog_public' ),
			'permalinks'    => '' !== (string) get_option( 'permalink_structure' ),
			'titleTag'      => wp_is_block_theme() || current_theme_supports( 'title-tag' ),
			'sitemap'       => null !== $modules->get( 'sitemaps' ),
			'https'         => wp_is_using_https(),
			'conflicts'     => array_values( Conflicts::active() ),
			'outputEnabled' => $this->plugin->output_enabled(),
			'gscModule'     => null !== $modules->get( 'search_console' ),
			'gscConnected'  => $auth->is_connected() && '' !== (string) ( $auth->data()['property'] ?? '' ),
			'socialModule'  => null !== $modules->get( 'social' ),
			'socialImage'   => (int) $options->get( 'social.default_image', 0 ) > 0,
			'setupDone'     => (bool) $options->get( 'general.setup_completed', false ),
		);
	}

	/**
	 * Last 28 days from Search Console, compared with the 28 days before.
	 *
	 * @return array<string, mixed>|null Null when Search Console has no data.
	 */
	private function search(): ?array {
		if ( ! $this->plugin->modules()->get( 'search_console' ) ) {
			return null;
		}

		$store = new Store();

		if ( '' === $store->last_date( Store::SITE ) ) {
			return null;
		}

		$range = $store->range( 28 );

		$options = $this->plugin->options();
		$series  = $store->series( Store::SITE, '', $range['start'], $range['end'] );
		$decay   = $store->decay(
			$range['start'],
			$range['end'],
			$range['prev_start'],
			$range['prev_end'],
			(int) $options->get( 'search_console.decay_threshold', 30 ),
			(int) $options->get( 'search_console.decay_min_clicks', 10 )
		);

		return array(
			'range'     => $range,
			'totals'    => $store->totals( Store::SITE, '', $range['start'], $range['end'] ),
			'previous'  => $store->totals( Store::SITE, '', $range['prev_start'], $range['prev_end'] ),
			'series'    => array(
				'clicks'      => array_column( $series, 'clicks' ),
				'impressions' => array_column( $series, 'impressions' ),
				'position'    => array_column( $series, 'position' ),
			),
			'decay'     => count( $decay ),
			'threshold' => (int) $options->get( 'search_console.decay_threshold', 30 ),
		);
	}

	/**
	 * 404s seen in the last seven days.
	 *
	 * @return array<string, mixed>|null Null when the 404 log is off.
	 */
	private function not_found(): ?array {
		if ( ! $this->plugin->modules()->get( 'redirects' ) || ! $this->plugin->options()->get( 'redirects.log_404', true ) ) {
			return null;
		}

		return ( new NotFoundLog() )->recent( time() - WEEK_IN_SECONDS );
	}

	/**
	 * Active redirects and how often they were used.
	 *
	 * @return array<string, int>|null Null when the Redirects module is off.
	 */
	private function redirects(): ?array {
		if ( ! $this->plugin->modules()->get( 'redirects' ) ) {
			return null;
		}

		$repository = new Repository();
		$counts     = $repository->counts();

		return array(
			'active' => $counts['exact'] + $counts['regex'],
			'hits'   => $repository->hits(),
		);
	}

	/**
	 * Sitemap index URL and the number of sitemap files.
	 *
	 * @return array<string, mixed>|null Null when sitemaps are off.
	 */
	private function sitemap(): ?array {
		$module = $this->plugin->modules()->get( 'sitemaps' );

		if ( ! $module instanceof SitemapsModule ) {
			return null;
		}

		$files = 0;

		foreach ( $module->providers() as $provider ) {
			$files += $provider->max_pages();
		}

		return array(
			'url'   => Router::index_url(),
			'files' => $files,
		);
	}
}
