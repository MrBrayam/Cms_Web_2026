<?php
/**
 * Template variable registry.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Templates;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Holds `%variable%` definitions used in title/description templates.
 *
 * A callback receives the Context and an optional argument string
 * (`%custom_field(price)%` → `price`) and returns plain text.
 */
final class VariableRegistry {

	/**
	 * Definitions keyed by name.
	 *
	 * @var array<string, array{label: string, description: string, callback: callable, example: string, accepts_argument: bool}>
	 */
	private array $variables = array();

	/**
	 * Registers or replaces a variable.
	 *
	 * @param string               $name Lowercase name: letters, digits and underscores.
	 * @param array<string, mixed> $args {
	 *     Variable definition.
	 *
	 *     @type string   $label            Human label.
	 *     @type string   $description      Help text.
	 *     @type callable $callback         function( Context $context, string $argument ): string.
	 *     @type string   $example          Example usage shown in the UI, e.g. `%custom_field(price)%`.
	 *     @type bool     $accepts_argument Whether the variable takes an argument.
	 * }
	 * @throws InvalidArgumentException On an invalid name or missing callback.
	 */
	public function register( string $name, array $args ): void {
		if ( ! preg_match( '/^[a-z0-9_]+$/', $name ) ) {
			throw new InvalidArgumentException( sprintf( 'Invalid template variable name "%s".', esc_html( $name ) ) );
		}

		if ( empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			throw new InvalidArgumentException( sprintf( 'Template variable "%s" needs a callable callback.', esc_html( $name ) ) );
		}

		$this->variables[ $name ] = array(
			'label'            => (string) ( $args['label'] ?? $name ),
			'description'      => (string) ( $args['description'] ?? '' ),
			'callback'         => $args['callback'],
			'example'          => (string) ( $args['example'] ?? '%' . $name . '%' ),
			'accepts_argument' => (bool) ( $args['accepts_argument'] ?? false ),
		);
	}

	/**
	 * Removes a variable.
	 *
	 * @param string $name Variable name.
	 */
	public function unregister( string $name ): void {
		unset( $this->variables[ $name ] );
	}

	/**
	 * Whether a variable exists.
	 *
	 * @param string $name Variable name.
	 */
	public function has( string $name ): bool {
		return isset( $this->variables[ $name ] );
	}

	/**
	 * A variable definition.
	 *
	 * @param string $name Variable name.
	 * @return array{label: string, description: string, callback: callable, example: string, accepts_argument: bool}|null
	 */
	public function get( string $name ): ?array {
		return $this->variables[ $name ] ?? null;
	}

	/**
	 * All definitions, sorted by name.
	 *
	 * @return array<string, array{label: string, description: string, callback: callable, example: string, accepts_argument: bool}>
	 */
	public function all(): array {
		$variables = $this->variables;
		ksort( $variables );

		return $variables;
	}
}
