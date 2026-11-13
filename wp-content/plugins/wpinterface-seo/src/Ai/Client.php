<?php
/**
 * AI requests through the WordPress AI Client.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Ai;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends one prompt through the WordPress AI Client (WordPress 7.0+) and
 * returns decoded JSON. The provider is the one the site owner set up in
 * Settings → Connectors; the plugin holds no credentials and calls no
 * provider itself. Requests are only made when a user clicks a button.
 */
final class Client implements JsonClient {

	/**
	 * Whether the WordPress AI Client has a text provider configured.
	 */
	public static function available(): bool {
		static $available = null;

		if ( null !== $available ) {
			return $available;
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) || ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) ) {
			$available = false;
			return $available;
		}

		try {
			// Checks configured connectors only; no request is sent.
			$available = (bool) wp_ai_client_prompt( 'test' )->is_supported_for_text_generation();
		} catch ( \Throwable $error ) {
			$available = false;
		}

		return $available;
	}

	/**
	 * Runs a prompt and returns the decoded JSON object.
	 *
	 * @param string               $system System instruction.
	 * @param string               $prompt User prompt.
	 * @param array<string, mixed> $schema JSON Schema of the expected answer.
	 * @param string               $image  Optional image as a data URI.
	 * @return array<string, mixed>|WP_Error
	 */
	public function json( string $system, string $prompt, array $schema, string $image = '' ) {
		if ( ! self::available() ) {
			return new WP_Error( 'wpiseo_ai_unavailable', __( 'No AI provider is set up. Add one in Settings → Connectors (WordPress 7.0 or later).', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		$system .= "\n\n" . __( 'Reply with a single JSON object that matches this JSON Schema, and nothing else:', 'wpinterface-seo' ) . "\n" . wp_json_encode( $schema );

		$builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $system )
			->using_temperature( 0.6 )
			->using_max_tokens( 1200 )
			->as_json_response( $schema );

		if ( '' !== $image ) {
			$builder = $builder->with_file( $image );
		}

		$text = $builder->generate_text();

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$data = self::decode( (string) $text );

		if ( null === $data ) {
			return new WP_Error( 'wpiseo_ai_bad_response', __( 'The AI provider returned an answer that could not be read. Try again.', 'wpinterface-seo' ), array( 'status' => 502 ) );
		}

		return $data;
	}

	/**
	 * Decodes a JSON object from model output (tolerates code fences and prose).
	 *
	 * @param string $text Model output.
	 * @return array<string, mixed>|null
	 */
	public static function decode( string $text ): ?array {
		$text = trim( $text );
		$text = (string) preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $text );
		$data = json_decode( $text, true );

		if ( ! is_array( $data ) ) {
			$start = strpos( $text, '{' );
			$end   = strrpos( $text, '}' );

			if ( false !== $start && false !== $end && $end > $start ) {
				$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			}
		}

		return is_array( $data ) ? $data : null;
	}
}
