<?php
/**
 * Redirect CSV import/export.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Columns: source, target, status, match_type, ignore_query. Files with only
 * "source,target" (common in other tools) are accepted too.
 */
final class Csv {

	public const COLUMNS = array( 'source', 'target', 'status', 'match_type', 'ignore_query' );

	/**
	 * CSV text for rules.
	 *
	 * @param list<array<string, mixed>> $rules Rules.
	 */
	public static function export( array $rules ): string {
		$lines = array( implode( ',', self::COLUMNS ) );

		foreach ( $rules as $rule ) {
			$lines[] = implode(
				',',
				array_map(
					array( self::class, 'cell' ),
					array(
						(string) $rule['source'],
						(string) $rule['target'],
						(string) $rule['status'],
						(string) $rule['match_type'],
						! empty( $rule['ignore_query'] ) ? '1' : '0',
					)
				)
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Parses CSV text into rule data (not yet validated).
	 *
	 * @param string $text CSV.
	 * @return list<array<string, mixed>>
	 */
	public static function parse( string $text ): array {
		$text  = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		$rules = array();

		foreach ( is_array( $lines ) ? $lines : array() as $index => $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			$cells = array_map( 'trim', str_getcsv( $line, ',', '"', '\\' ) );

			// Skip a header row.
			if ( 0 === $index && in_array( strtolower( (string) $cells[0] ), array( 'source', 'from', 'url', 'old url', 'origin' ), true ) ) {
				continue;
			}

			if ( '' === (string) ( $cells[0] ?? '' ) ) {
				continue;
			}

			$rules[] = array(
				'source'       => (string) $cells[0],
				'target'       => (string) ( $cells[1] ?? '' ),
				'status'       => isset( $cells[2] ) && '' !== $cells[2] ? (int) $cells[2] : 301,
				'match_type'   => 'regex' === strtolower( (string) ( $cells[3] ?? '' ) ) ? 'regex' : 'exact',
				'ignore_query' => ! isset( $cells[4] ) || '' === $cells[4] || '1' === $cells[4],
				'origin'       => 'import',
			);
		}

		return $rules;
	}

	/**
	 * Escapes one CSV cell (and neutralises spreadsheet formulas).
	 *
	 * @param string $value Value.
	 */
	private static function cell( string $value ): string {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			$value = "'" . $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}
}
