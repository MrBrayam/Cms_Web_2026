<?php
/**
 * Settings storage.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO;

use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the plugin settings.
 *
 * Settings live in one autoloaded option (`wpiseo_settings`) shaped as
 * `section => [ key => value ]`. Large blobs that are not needed on every
 * request (robots.txt body, import logs, …) go in the non-autoloaded
 * `wpiseo_settings_large` option.
 */
final class Options {

	public const OPTION       = 'wpiseo_settings';
	public const LARGE_OPTION = 'wpiseo_settings_large';

	/**
	 * Merged settings cache.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Default settings. Modules add their sections through `wpiseo_default_settings`.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		$defaults = array(
			'general' => array(
				'separator'                => '-',
				'delete_data_on_uninstall' => false,
				'force_output'             => false,
				'setup_completed'          => false,
				'block_editor_metabox'     => false,
			),
			'modules' => array(),
		);

		/**
		 * Filters the default settings.
		 *
		 * @param array $defaults Defaults keyed by section.
		 */
		$filtered = apply_filters( 'wpiseo_default_settings', $defaults );

		return is_array( $filtered ) ? $filtered : $defaults;
	}

	/**
	 * All settings, stored values merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$this->cache = self::merge( $this->defaults(), $this->stored() );
		}

		return $this->cache;
	}

	/**
	 * Reads a value by dot path.
	 *
	 * @param string $path          Dot path such as `general.separator`.
	 * @param mixed  $default_value Returned when the path is missing.
	 * @return mixed
	 */
	public function get( string $path, $default_value = null ) {
		$value = $this->all();

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default_value;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Merges a partial settings array into the stored settings, sanitizes and saves.
	 *
	 * Unknown sections are discarded.
	 *
	 * @param array<string, mixed> $values Partial settings keyed by section.
	 * @return array<string, mixed> The full, sanitized settings after saving.
	 */
	public function update( array $values ): array {
		$defaults = $this->defaults();
		$merged   = self::merge( self::merge( $defaults, $this->stored() ), $values );
		$clean    = array_intersect_key( $this->sanitize( $merged, $defaults ), $defaults );

		update_option( self::OPTION, $clean, true );
		$this->reset_cache();

		/**
		 * Fires after settings were saved.
		 *
		 * @param array $clean  Saved settings.
		 * @param array $values Partial input that triggered the save.
		 */
		do_action( 'wpiseo_settings_updated', $clean, $values );

		return $this->all();
	}

	/**
	 * Clears the in-memory cache, e.g. after modules add defaults.
	 */
	public function reset_cache(): void {
		$this->cache = null;
	}

	/**
	 * Reads a key from the non-autoloaded option.
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Returned when missing.
	 * @return mixed
	 */
	public function get_large( string $key, $default_value = null ) {
		$large = get_option( self::LARGE_OPTION, array() );

		return is_array( $large ) && array_key_exists( $key, $large ) ? $large[ $key ] : $default_value;
	}

	/**
	 * Writes a key to the non-autoloaded option. Callers sanitize.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function update_large( string $key, $value ): void {
		$large = get_option( self::LARGE_OPTION, array() );
		$large = is_array( $large ) ? $large : array();

		$large[ $key ] = $value;

		update_option( self::LARGE_OPTION, $large, false );
	}

	/**
	 * Recursively merges `$values` over `$defaults`.
	 *
	 * Associative arrays merge key by key; lists and scalars are replaced.
	 *
	 * @param array<mixed> $defaults Base array.
	 * @param array<mixed> $values   Overrides.
	 * @return array<mixed>
	 */
	public static function merge( array $defaults, array $values ): array {
		foreach ( $values as $key => $value ) {
			if (
				is_array( $value )
				&& isset( $defaults[ $key ] )
				&& is_array( $defaults[ $key ] )
				&& self::is_assoc( $defaults[ $key ] )
			) {
				$defaults[ $key ] = self::merge( $defaults[ $key ], $value );
				continue;
			}

			$defaults[ $key ] = $value;
		}

		return $defaults;
	}

	/**
	 * Stored option value.
	 *
	 * @return array<string, mixed>
	 */
	private function stored(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Sanitizes the full settings array.
	 *
	 * @param array<string, mixed> $settings Merged settings.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings, array $defaults ): array {
		$general = is_array( $settings['general'] ?? null ) ? $settings['general'] : array();

		$separator = sanitize_text_field( (string) ( $general['separator'] ?? '' ) );
		$separator = '' === trim( $separator ) ? $defaults['general']['separator'] : mb_substr( $separator, 0, 10 );

		$settings['general'] = array(
			'separator'                => $separator,
			'delete_data_on_uninstall' => Sanitize::to_bool( $general['delete_data_on_uninstall'] ?? false ),
			'force_output'             => Sanitize::to_bool( $general['force_output'] ?? false ),
			'setup_completed'          => Sanitize::to_bool( $general['setup_completed'] ?? false ),
			'block_editor_metabox'     => Sanitize::to_bool( $general['block_editor_metabox'] ?? false ),
		);

		/**
		 * Filters settings before they are saved. Modules sanitize their own sections here.
		 *
		 * @param array $settings Settings keyed by section.
		 * @param array $defaults Defaults keyed by section.
		 */
		$filtered = apply_filters( 'wpiseo_sanitize_settings', $settings, $defaults );

		return is_array( $filtered ) ? $filtered : $settings;
	}

	/**
	 * Whether an array is a non-empty map. Empty arrays count as lists so that
	 * list-type settings (e.g. excluded IDs) are replaced rather than merged.
	 *
	 * @param array<mixed> $value Array.
	 */
	private static function is_assoc( array $value ): bool {
		return array() !== $value && array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}
}
