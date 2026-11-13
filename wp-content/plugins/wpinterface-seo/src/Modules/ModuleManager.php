<?php
/**
 * Module loader.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules;

use WPInterface\SEO\Options;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Registers module definitions, merges their settings and boots enabled ones.
 */
final class ModuleManager {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Normalised definitions keyed by module ID.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $definitions = null;

	/**
	 * Booted module instances keyed by ID.
	 *
	 * @var array<string, Module>
	 */
	private array $instances = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 *
	 * @param Plugin  $plugin  Plugin instance.
	 * @param Options $options Settings.
	 */
	public function __construct( Plugin $plugin, Options $options ) {
		$this->plugin  = $plugin;
		$this->options = $options;
	}

	/**
	 * Hooks settings integration and instantiates enabled, available modules.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_filter( 'wpiseo_default_settings', array( $this, 'filter_default_settings' ) );
		add_filter( 'wpiseo_sanitize_settings', array( $this, 'filter_sanitize_settings' ), 5, 2 );
		$this->options->reset_cache();

		foreach ( array_keys( $this->definitions() ) as $id ) {
			if ( ! $this->is_enabled( $id ) || ! $this->is_available( $id ) ) {
				continue;
			}

			$class  = $this->definitions()[ $id ]['class'];
			$module = new $class( $this->plugin );

			if ( ! $module instanceof Module ) {
				continue;
			}

			$this->instances[ $id ] = $module;
			$module->register();
		}

		// Modules add rewrite rules (e.g. sitemaps); rebuild them when the active set changes.
		$signature = md5( WPISEO_VERSION . '|' . implode( ',', array_keys( $this->instances ) ) . '|' . ( $this->plugin->output_enabled() ? '1' : '0' ) );

		if ( get_option( 'wpiseo_modules_signature' ) !== $signature ) {
			update_option( 'wpiseo_modules_signature', $signature, true );
			update_option( 'wpiseo_flush_rewrite_rules', 1, true );
		}

		/**
		 * Fires after enabled modules registered their hooks.
		 *
		 * @param array<string, Module> $instances Booted modules keyed by ID.
		 */
		do_action( 'wpiseo_modules_loaded', $this->instances );
	}

	/**
	 * Normalised module definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function definitions(): array {
		if ( null !== $this->definitions ) {
			return $this->definitions;
		}

		/**
		 * Filters module definitions. Add-ons (e.g. Pro) register modules here.
		 *
		 * @param array $definitions Definitions keyed by module ID.
		 */
		$definitions = apply_filters( 'wpiseo_module_definitions', ModuleDefinitions::all() );
		$normalised  = array();

		foreach ( (array) $definitions as $id => $definition ) {
			if ( ! is_string( $id ) || ! is_array( $definition ) || empty( $definition['class'] ) ) {
				continue;
			}

			$normalised[ sanitize_key( $id ) ] = array(
				'class'    => (string) $definition['class'],
				'default'  => (bool) ( $definition['default'] ?? false ),
				'required' => (bool) ( $definition['required'] ?? false ),
				'phase'    => (int) ( $definition['phase'] ?? 0 ),
				'requires' => isset( $definition['requires'] ) && is_callable( $definition['requires'] ) ? $definition['requires'] : null,
			);
		}

		$this->definitions = $normalised;

		return $this->definitions;
	}

	/**
	 * Whether the module code exists and its dependencies are active.
	 *
	 * @param string $id Module ID.
	 */
	public function is_available( string $id ): bool {
		$definition = $this->definitions()[ $id ] ?? null;

		if ( null === $definition || ! $this->is_implemented( $id ) ) {
			return false;
		}

		return null === $definition['requires'] || (bool) call_user_func( $definition['requires'] );
	}

	/**
	 * Whether the module class has been written (false for roadmap placeholders).
	 *
	 * @param string $id Module ID.
	 */
	public function is_implemented( string $id ): bool {
		$definition = $this->definitions()[ $id ] ?? null;

		return null !== $definition
			&& class_exists( $definition['class'] )
			&& is_subclass_of( $definition['class'], Module::class );
	}

	/**
	 * Whether the module is switched on (regardless of availability).
	 *
	 * @param string $id Module ID.
	 */
	public function is_enabled( string $id ): bool {
		$definition = $this->definitions()[ $id ] ?? null;

		if ( null === $definition ) {
			return false;
		}

		$enabled = $definition['required'] || (bool) $this->options->get( 'modules.' . $id, $definition['default'] );

		/**
		 * Filters whether a module is enabled.
		 *
		 * @param bool   $enabled Whether the module is enabled.
		 * @param string $id      Module ID.
		 */
		return (bool) apply_filters( 'wpiseo_module_enabled', $enabled, $id );
	}

	/**
	 * A booted module instance.
	 *
	 * @param string $id Module ID.
	 */
	public function get( string $id ): ?Module {
		return $this->instances[ $id ] ?? null;
	}

	/**
	 * Module states for the admin UI and CLI. Call after `init` (labels are translated).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function describe(): array {
		$labels = ModuleDefinitions::labels();
		$out    = array();

		foreach ( $this->definitions() as $id => $definition ) {
			$label = $labels[ $id ] ?? array();

			$out[] = array(
				'id'             => $id,
				'label'          => $label['label'] ?? $id,
				'description'    => $label['description'] ?? '',
				'requires_label' => $label['requires_label'] ?? '',
				'enabled'        => $this->is_enabled( $id ),
				'required'       => $definition['required'],
				'implemented'    => $this->is_implemented( $id ),
				'available'      => $this->is_available( $id ),
				'active'         => isset( $this->instances[ $id ] ),
				'phase'          => $definition['phase'],
			);
		}

		return $out;
	}

	/**
	 * Adds the `modules` toggles and each implemented module's settings section to the defaults.
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public function filter_default_settings( $defaults ): array {
		$defaults = is_array( $defaults ) ? $defaults : array();

		foreach ( $this->definitions() as $id => $definition ) {
			$defaults['modules'][ $id ] = $definition['default'];

			if ( $this->is_implemented( $id ) ) {
				$section = call_user_func( array( $definition['class'], 'default_settings' ) );

				if ( is_array( $section ) && array() !== $section ) {
					$defaults[ $id ] = $section;
				}
			}
		}

		return $defaults;
	}

	/**
	 * Sanitizes module toggles and delegates each section to its module class.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public function filter_sanitize_settings( $settings, $defaults ): array {
		$settings = is_array( $settings ) ? $settings : array();
		$defaults = is_array( $defaults ) ? $defaults : array();
		$toggles  = is_array( $settings['modules'] ?? null ) ? $settings['modules'] : array();

		$settings['modules'] = array();

		foreach ( $this->definitions() as $id => $definition ) {
			$settings['modules'][ $id ] = $definition['required']
				|| Sanitize::to_bool( $toggles[ $id ] ?? $definition['default'] );

			if ( isset( $defaults[ $id ] ) && is_array( $defaults[ $id ] ) && $this->is_implemented( $id ) ) {
				$values          = is_array( $settings[ $id ] ?? null ) ? $settings[ $id ] : array();
				$settings[ $id ] = call_user_func( array( $definition['class'], 'sanitize_settings' ), $values, $defaults[ $id ] );
			}
		}

		return $settings;
	}
}
