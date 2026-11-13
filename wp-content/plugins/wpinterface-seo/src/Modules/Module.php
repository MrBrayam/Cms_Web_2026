<?php
/**
 * Base class for feature modules.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules;

use WPInterface\SEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * A module is a self-contained feature that can be toggled in Settings → Modules.
 *
 * Disabled modules are never instantiated. Settings defaults and sanitization
 * are static so the settings UI works while a module is disabled.
 */
abstract class Module {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Registers the module's WordPress hooks. Runs on `plugins_loaded`.
	 */
	abstract public function register(): void;

	/**
	 * Default values for this module's settings section (keyed by module ID).
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array();
	}

	/**
	 * Sanitizes this module's settings section.
	 *
	 * @param array<string, mixed> $values   Merged section values.
	 * @param array<string, mixed> $defaults Section defaults.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $values, array $defaults ): array {
		return array_intersect_key( $values, $defaults );
	}

	/**
	 * Reads one of this module's settings.
	 *
	 * @param string $id            Module ID (section name).
	 * @param string $key           Setting key inside the section.
	 * @param mixed  $default_value Returned when missing.
	 * @return mixed
	 */
	protected function setting( string $id, string $key, $default_value = null ) {
		return $this->plugin->options()->get( $id . '.' . $key, $default_value );
	}
}
