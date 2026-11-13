<?php
/**
 * AI client contract.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Ai;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Something that answers a prompt with a JSON object (the WordPress AI
 * Client in production).
 */
interface JsonClient {

	/**
	 * Runs a prompt and returns the decoded JSON object.
	 *
	 * @param string               $system System instruction.
	 * @param string               $prompt User prompt.
	 * @param array<string, mixed> $schema JSON Schema of the expected answer.
	 * @param string               $image  Optional image as a data URI.
	 * @return array<string, mixed>|WP_Error
	 */
	public function json( string $system, string $prompt, array $schema, string $image = '' );
}
