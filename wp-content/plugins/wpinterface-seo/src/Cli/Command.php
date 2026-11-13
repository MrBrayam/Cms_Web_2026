<?php
/**
 * WP-CLI commands.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Cli;

use WP_CLI;
use WP_CLI\Utils;
use WPInterface\SEO\Analysis\ServerAnalysis;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Crawl\IndexNow;
use WPInterface\SEO\Install\Upgrader;
use WPInterface\SEO\Links\Indexer;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Redirects\Csv;
use WPInterface\SEO\Redirects\Importer as RedirectImporter;
use WPInterface\SEO\Redirects\Repository;
use WPInterface\SEO\Rest\ImportController;
use WPInterface\SEO\SearchConsole\Api;
use WPInterface\SEO\SearchConsole\Auth;
use WPInterface\SEO\SearchConsole\Store;
use WPInterface\SEO\SearchConsole\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Manage WPInterface SEO.
 */
final class Command {

	/**
	 * Registers `wp wpiseo`.
	 */
	public static function register(): void {
		WP_CLI::add_command( 'wpiseo', self::class );
	}

	/**
	 * Shows version, database version and module states.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo info
	 *
	 * @subcommand info
	 */
	public function info(): void {
		WP_CLI::line( sprintf( 'Version:    %s', WPISEO_VERSION ) );
		WP_CLI::line( sprintf( 'DB version: %d (code expects %d)', (int) get_option( Upgrader::DB_VERSION_OPTION, 0 ), WPISEO_DB_VERSION ) );
		WP_CLI::line( '' );

		$this->module_list( array(), array() );
	}

	/**
	 * Lists modules.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo module-list
	 *
	 * @subcommand module-list
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function module_list( array $args, array $assoc_args ): void {
		$rows = array_map(
			static function ( array $module ): array {
				if ( ! $module['implemented'] ) {
					$status = 'planned (phase ' . $module['phase'] . ')';
				} elseif ( $module['active'] ) {
					$status = 'active';
				} elseif ( $module['enabled'] && ! $module['available'] ) {
					$status = 'unavailable';
				} else {
					$status = $module['enabled'] ? 'enabled' : 'disabled';
				}

				return array(
					'id'       => $module['id'],
					'label'    => $module['label'],
					'status'   => $status,
					'required' => $module['required'] ? 'yes' : 'no',
				);
			},
			Plugin::instance()->modules()->describe()
		);

		Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'id', 'label', 'status', 'required' ) );
	}

	/**
	 * Enables a module.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Module ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo module-enable local
	 *
	 * @subcommand module-enable
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function module_enable( array $args ): void {
		$this->toggle_module( $args[0], true );
	}

	/**
	 * Disables a module.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Module ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo module-disable local
	 *
	 * @subcommand module-disable
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function module_disable( array $args ): void {
		$this->toggle_module( $args[0], false );
	}

	/**
	 * Lists template variables.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @subcommand variables
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function variables( array $args, array $assoc_args ): void {
		$rows = array();

		foreach ( Plugin::instance()->variables()->all() as $name => $variable ) {
			$rows[] = array(
				'variable'    => $variable['example'],
				'label'       => $variable['label'],
				'description' => $variable['description'],
			);
		}

		Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'variable', 'label', 'description' ) );
	}

	/**
	 * Renders a title/description template.
	 *
	 * ## OPTIONS
	 *
	 * <template>
	 * : Template, e.g. "%title% %sep% %sitename%".
	 *
	 * [--post=<id>]
	 * : Render for a post.
	 *
	 * [--term=<id>]
	 * : Render for a term.
	 *
	 * [--user=<id>]
	 * : Render for an author archive.
	 *
	 * [--page=<n>]
	 * : Pretend to be on page n of 5 (to test %page%).
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo template "%title% %page% %sep% %sitename%" --post=1 --page=2
	 *
	 * @subcommand template
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function template( array $args, array $assoc_args ): void {
		if ( ! empty( $assoc_args['post'] ) ) {
			$context = Context::for_post( (int) $assoc_args['post'] );
		} elseif ( ! empty( $assoc_args['term'] ) ) {
			$context = Context::for_term( (int) $assoc_args['term'] );
		} elseif ( ! empty( $assoc_args['user'] ) ) {
			$context = Context::for_user( (int) $assoc_args['user'] );
		} else {
			$context = Context::for_front_page_posts();
		}

		if ( Context::OTHER === $context->type() ) {
			WP_CLI::error( 'Object not found.' );
		}

		if ( ! empty( $assoc_args['page'] ) ) {
			$context = $context->with_page( (int) $assoc_args['page'], 5 );
		}

		WP_CLI::line( Plugin::instance()->parser()->parse( $args[0], $context ) );
	}

	/**
	 * Lists redirects.
	 *
	 * ## OPTIONS
	 *
	 * [--search=<text>]
	 * : Filter by source or target.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @subcommand redirect-list
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function redirect_list( array $args, array $assoc_args ): void {
		$result = ( new Repository() )->query(
			array(
				'search'   => $assoc_args['search'] ?? '',
				'per_page' => 200,
			)
		);

		Utils\format_items( $assoc_args['format'] ?? 'table', $result['items'], array( 'id', 'source', 'target', 'status', 'match_type', 'enabled', 'hits' ) );
	}

	/**
	 * Adds a redirect.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : URL or path to redirect from (a pattern with --regex).
	 *
	 * [<target>]
	 * : URL to redirect to (not needed for 410/451).
	 *
	 * [--status=<code>]
	 * : HTTP status: 301, 302, 307, 308, 410 or 451.
	 * ---
	 * default: 301
	 * ---
	 *
	 * [--regex]
	 * : Treat the source as a regular expression.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo redirect-add /old-page /new-page
	 *     wp wpiseo redirect-add "^/blog/(.*)$" "/news/$1" --regex
	 *     wp wpiseo redirect-add /discontinued --status=410
	 *
	 * @subcommand redirect-add
	 *
	 * @param array<int, string>         $args       Positional arguments.
	 * @param array<string, string|bool> $assoc_args Named arguments.
	 */
	public function redirect_add( array $args, array $assoc_args ): void {
		$id = ( new Repository() )->create(
			array(
				'source'     => $args[0],
				'target'     => $args[1] ?? '',
				'status'     => (int) ( $assoc_args['status'] ?? 301 ),
				'match_type' => ! empty( $assoc_args['regex'] ) ? 'regex' : 'exact',
			)
		);

		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Redirect %d created.', $id ) );
	}

	/**
	 * Deletes redirects.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Redirect IDs.
	 *
	 * @subcommand redirect-delete
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function redirect_delete( array $args ): void {
		WP_CLI::success( sprintf( '%d redirect(s) deleted.', ( new Repository() )->delete( array_map( 'intval', $args ) ) ) );
	}

	/**
	 * Imports redirects from a CSV file or another plugin.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : A CSV file path, or one of: redirection, rankmath, yoast, slimseo, seopress.
	 *
	 * @subcommand redirect-import
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function redirect_import( array $args ): void {
		$repository = new Repository();
		$source     = $args[0];

		if ( in_array( $source, array( 'redirection', 'rankmath', 'yoast', 'slimseo', 'seopress' ), true ) ) {
			$result = ( new RedirectImporter( $repository ) )->import( $source );
			WP_CLI::success( sprintf( '%d imported, %d skipped.', $result['created'], $result['skipped'] ) );
			return;
		}

		if ( ! is_readable( $source ) ) {
			WP_CLI::error( 'File not found or not readable.' );
		}

		$created = 0;
		$skipped = 0;

		foreach ( Csv::parse( (string) file_get_contents( $source ) ) as $rule ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file given on the command line.
			if ( is_wp_error( $repository->create( $rule ) ) ) {
				++$skipped;
			} else {
				++$created;
			}
		}

		WP_CLI::success( sprintf( '%d imported, %d skipped.', $created, $skipped ) );
	}

	/**
	 * Prints all redirects as CSV.
	 *
	 * @subcommand redirect-export
	 */
	public function redirect_export(): void {
		WP_CLI::line( rtrim( Csv::export( ( new Repository() )->all() ) ) );
	}

	/**
	 * Imports SEO data from another plugin.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : yoast, rankmath, aioseo, seopress, tsf or slimseo.
	 *
	 * [--overwrite]
	 * : Replace values already set in WPInterface SEO.
	 *
	 * [--skip-settings]
	 * : Only import per-post, per-term and per-user data.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo import yoast
	 *
	 * @subcommand import
	 *
	 * @param array<int, string>         $args       Positional arguments.
	 * @param array<string, string|bool> $assoc_args Named arguments.
	 */
	public function import( array $args, array $assoc_args ): void {
		$importer = ImportController::importers()[ $args[0] ] ?? null;

		if ( null === $importer ) {
			WP_CLI::error( sprintf( 'Unknown source. Use one of: %s', implode( ', ', array_keys( ImportController::importers() ) ) ) );
		}

		$overwrite = ! empty( $assoc_args['overwrite'] );

		if ( empty( $assoc_args['skip-settings'] ) && array() !== $importer->settings() ) {
			Plugin::instance()->options()->update( $importer->settings() );
			WP_CLI::log( 'Settings imported.' );
		}

		$offset  = 0;
		$updated = 0;
		$total   = $importer->count_posts();
		$bar     = Utils\make_progress_bar( 'Posts', $total );

		while ( null !== $offset ) {
			$result   = $importer->import_posts( $offset, 200, $overwrite );
			$updated += $result['updated'];
			$offset   = $result['next'];
			$bar->tick( $result['processed'] );
		}

		$bar->finish();

		$terms = $importer->import_terms( $overwrite );
		$users = $importer->import_users( $overwrite );

		WP_CLI::success( sprintf( '%d posts, %d terms and %d users updated.', $updated, $terms['updated'], $users['updated'] ) );
	}

	/**
	 * Clears cached sitemap files so they are rebuilt on the next request.
	 *
	 * @subcommand sitemap-flush
	 */
	public function sitemap_flush(): void {
		update_option( 'wpiseo_sitemap_version', (string) microtime( true ), false );
		WP_CLI::success( 'Sitemap cache cleared.' );
	}

	/**
	 * Submits URLs to IndexNow.
	 *
	 * ## OPTIONS
	 *
	 * <url>...
	 * : URLs on this site.
	 *
	 * @subcommand indexnow
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function indexnow( array $args ): void {
		$options = Plugin::instance()->options();

		if ( ! $options->get( 'crawl.indexnow', false ) || '' === $options->get( 'crawl.indexnow_key', '' ) ) {
			WP_CLI::error( 'IndexNow is off. Enable it in SEO → Robots & Crawl.' );
		}

		$result = ( new IndexNow( $options ) )->submit( $args );

		WP_CLI::log( sprintf( '%d: %s', $result['code'], $result['message'] ) );
	}

	/**
	 * Runs the basic SEO checks on a post.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Post ID.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo analyze 42
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function analyze( array $args, array $assoc_args ): void {
		$post = get_post( (int) ( $args[0] ?? 0 ) );

		if ( ! $post instanceof \WP_Post ) {
			WP_CLI::error( 'Post not found.' );
		}

		$result = ServerAnalysis::analyze( $post );

		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			WP_CLI::line( (string) wp_json_encode( $result ) );
			return;
		}

		Utils\format_items( 'table', $result['checks'], array( 'status', 'message' ) );
		WP_CLI::line( sprintf( 'Score: %d/100 · %d words', $result['score'], $result['word_count'] ) );
	}

	/**
	 * Rebuilds the internal link index for all published content.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo links-rebuild
	 *
	 * @subcommand links-rebuild
	 */
	public function links_rebuild(): void {
		$indexer = new Indexer();
		$last    = 0;
		$batches = 0;

		while ( null !== $last ) {
			$last = $indexer->rebuild_batch( (int) $last, 100 );
			++$batches;
		}

		delete_option( 'wpiseo_links_rebuild' );

		WP_CLI::success( sprintf( 'Link index rebuilt (%d batches).', $batches - 1 ) );
	}

	/**
	 * Syncs Search Console data now.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpiseo gsc-sync
	 *
	 * @subcommand gsc-sync
	 */
	public function gsc_sync(): void {
		$auth = new Auth();
		$sync = new Sync( Plugin::instance()->options(), $auth, new Api( $auth ), new Store() );

		add_filter( 'wpiseo_gsc_sync_time_limit', static fn() => 600 );

		do {
			$result = $sync->run();

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
		} while ( ! empty( Sync::state()['cursor'] ) );

		wp_clear_scheduled_hook( Sync::HOOK );
		WP_CLI::success( sprintf( 'Search Console data synced up to %s.', (string) ( Sync::state()['last_date'] ?? '' ) ) );
	}

	/**
	 * Saves a module toggle.
	 *
	 * @param string $id      Module ID.
	 * @param bool   $enabled New state.
	 */
	private function toggle_module( string $id, bool $enabled ): void {
		$modules     = Plugin::instance()->modules();
		$definitions = $modules->definitions();

		if ( ! isset( $definitions[ $id ] ) ) {
			WP_CLI::error( sprintf( 'Unknown module "%s". Run `wp wpiseo module-list`.', $id ) );
		}

		if ( ! $enabled && $definitions[ $id ]['required'] ) {
			WP_CLI::error( sprintf( 'Module "%s" is required and cannot be disabled.', $id ) );
		}

		Plugin::instance()->options()->update( array( 'modules' => array( $id => $enabled ) ) );

		WP_CLI::success( sprintf( 'Module "%s" %s.', $id, $enabled ? 'enabled' : 'disabled' ) );
	}
}
