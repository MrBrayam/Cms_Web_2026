<?php
/**
 * Sanitization helpers.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Type-safe sanitizers for settings and meta input.
 */
final class Sanitize {

	/**
	 * Converts form/REST input to a boolean.
	 *
	 * Like rest_sanitize_boolean(), but always returns a bool: the strings
	 * "", "0", "false", "off" and "no" (any case) are false.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), array( '', '0', 'false', 'off', 'no' ), true );
		}

		return (bool) $value;
	}
}
