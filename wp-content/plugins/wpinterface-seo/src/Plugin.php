<?php
/**
 * Plugin bootstrap.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO;

use WP_Query;
use WPInterface\SEO\Admin\Admin;
use WPInterface\SEO\Cli\Command;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Dashboard\Stats;
use WPInterface\SEO\Dashboard\Widget;
use WPInterface\SEO\Install\Activator;
use WPInterface\SEO\Install\Upgrader;
use WPInterface\SEO\Integrations\Elementor;
use WPInterface\SEO\Integrations\Multilingual;
use WPInterface\SEO\Meta\Registrar;
use WPInterface\SEO\Modules\ModuleManager;
use WPInterface\SEO\Presentation\Presentation;
use WPInterface\SEO\Rest\BulkController;
use WPInterface\SEO\Rest\DashboardController;
use WPInterface\SEO\Rest\SettingsController;
use WPInterface\SEO\Rest\VariablesController;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Conflicts;
use WPInterface\SEO\Templates\DefaultVariables;
use WPInterface\SEO\Templates\Parser;
use WPInterface\SEO\Templates\VariableRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Wires services together and registers WordPress hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Context of the main query, memoised once the query has run.
	 *
	 * @var Context|null
	 */
	private ?Context $context = null;

	/**
	 * The query object the memoised context was resolved from.
	 *
	 * @var WP_Query|null
	 */
	private ?WP_Query $context_query = null;

	/**
	 * Presentations keyed by spl_object_id() of their Context.
	 *
	 * @var array<int, Presentation>
	 */
	private array $presentations = array();

	/**
	 * Cached result of output_enabled().
	 *
	 * @var bool|null
	 */
	private ?bool $output_enabled = null;

	/**
	 * Returns the shared instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Use instance().
	 */
	private function __construct() {
		$this->container = new Container();
		$this->register_services();
	}

	/**
	 * Registers hooks. Runs on `plugins_loaded`.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		Upgrader::maybe_upgrade();

		( new Registrar() )->register();
		( new Capabilities() )->register();
		$this->modules()->boot();
		( new Multilingual( $this->options() ) )->register();
		( new Elementor() )->register();

		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 999 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_initialize_site', array( Activator::class, 'on_new_site' ), 200 );

		// Settings and other plugins (Pro, conflicting SEO plugins) change the dashboard.
		foreach ( array( 'wpiseo_settings_updated', 'activated_plugin', 'deactivated_plugin' ) as $hook ) {
			add_action( $hook, array( Stats::class, 'flush' ) );
		}

		if ( is_admin() ) {
			( new Admin() )->register();
			( new Widget( $this ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Command::register();
		}

		/**
		 * Fires once the plugin has registered its hooks.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'wpiseo_loaded', $this );
	}

	/**
	 * Registers core services.
	 */
	private function register_services(): void {
		$this->container->set( 'options', static fn() => new Options() );
		$this->container->set( 'modules', fn( Container $c ) => new ModuleManager( $this, $c->get( 'options' ) ) );
		$this->container->set(
			'variables',
			static function ( Container $c ) {
				$registry = new VariableRegistry();
				( new DefaultVariables( $c->get( 'options' ) ) )->register( $registry );

				/**
				 * Lets modules and third parties register template variables.
				 *
				 * @param VariableRegistry $registry Registry.
				 */
				do_action( 'wpiseo_register_variables', $registry );

				return $registry;
			}
		);
		$this->container->set( 'parser', static fn( Container $c ) => new Parser( $c->get( 'variables' ), $c->get( 'options' ) ) );
	}

	/**
	 * Flushes rewrite rules once after activation or when a module requests it,
	 * after all modules have registered their rules on `init`.
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'wpiseo_flush_rewrite_rules' ) ) {
			delete_option( 'wpiseo_flush_rewrite_rules' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Registers REST controllers.
	 */
	public function register_rest_routes(): void {
		( new SettingsController( $this ) )->register_routes();
		( new VariablesController( $this ) )->register_routes();
		( new BulkController( $this ) )->register_routes();
		( new DashboardController( $this ) )->register_routes();
	}

	/**
	 * Context for the main query of the current request.
	 *
	 * Returns an "other" context (not memoised) when called before the query runs.
	 */
	public function context(): Context {
		global $wp_the_query;

		if ( ! did_action( 'wp' ) || ! $wp_the_query instanceof WP_Query ) {
			return Context::other();
		}

		// Memoised per main query object (it is replaced when the request is re-run).
		if ( null !== $this->context && $this->context_query === $wp_the_query ) {
			return $this->context;
		}

		/**
		 * Filters the resolved context of the current request.
		 *
		 * @param Context  $context Resolved context.
		 * @param WP_Query $query   Main query.
		 */
		$context = apply_filters( 'wpiseo_context', Context::from_query( $wp_the_query ), $wp_the_query );

		$this->context       = $context instanceof Context ? $context : Context::from_query( $wp_the_query );
		$this->context_query = $wp_the_query;

		return $this->context;
	}

	/**
	 * Resolved SEO values for a context (memoised per Context instance).
	 *
	 * @param Context|null $context Context; defaults to the current request.
	 */
	public function presentation( ?Context $context = null ): Presentation {
		$context = $context ?? $this->context();
		$key     = spl_object_id( $context );

		if ( ! isset( $this->presentations[ $key ] ) ) {
			$this->presentations[ $key ] = new Presentation( $this->options(), $this->parser(), $context );
		}

		return $this->presentations[ $key ];
	}

	/**
	 * Whether front-end SEO output (titles, meta, schema, sitemaps) is on.
	 *
	 * Off while another SEO plugin is active, unless the user forces it, so
	 * pages never get two sets of tags.
	 */
	public function output_enabled(): bool {
		if ( null === $this->output_enabled ) {
			$enabled = array() === Conflicts::active() || (bool) $this->options()->get( 'general.force_output', false );

			/**
			 * Filters whether the plugin prints front-end SEO output.
			 *
			 * @param bool $enabled Whether output is enabled.
			 */
			$this->output_enabled = (bool) apply_filters( 'wpiseo_output_enabled', $enabled );
		}

		return $this->output_enabled;
	}

	/**
	 * Service container.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Settings service.
	 */
	public function options(): Options {
		return $this->container->get( 'options' );
	}

	/**
	 * Module manager.
	 */
	public function modules(): ModuleManager {
		return $this->container->get( 'modules' );
	}

	/**
	 * Template variable registry.
	 */
	public function variables(): VariableRegistry {
		return $this->container->get( 'variables' );
	}

	/**
	 * Template parser.
	 */
	public function parser(): Parser {
		return $this->container->get( 'parser' );
	}
}
