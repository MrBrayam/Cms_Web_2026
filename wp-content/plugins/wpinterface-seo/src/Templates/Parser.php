<?php
/**
 * Template parser.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Templates;

use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Renders templates such as `%title% %page% %sep% %sitename%` to plain text.
 *
 * Output is NOT escaped; callers escape for their output context.
 */
final class Parser {

	private const PATTERN = '/%([a-z0-9_]+)(?:\(([^()%]*)\))?%/';

	/**
	 * Variable registry.
	 *
	 * @var VariableRegistry
	 */
	private VariableRegistry $registry;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param VariableRegistry $registry Variable registry.
	 * @param Options          $options  Settings.
	 */
	public function __construct( VariableRegistry $registry, Options $options ) {
		$this->registry = $registry;
		$this->options  = $options;
	}

	/**
	 * Replaces variables and tidies the result.
	 *
	 * Unknown variables are left untouched so literal text like `100%` survives.
	 *
	 * @param string  $template Template.
	 * @param Context $context  Context to render for.
	 */
	public function parse( string $template, Context $context ): string {
		if ( '' !== $template ) {
			/**
			 * Filters a user-entered string before use, e.g. to translate it with
			 * a multilingual plugin.
			 *
			 * @param string $text  Template or label as saved in the settings.
			 * @param string $group Where it is used: template, breadcrumbs.
			 */
			$template = (string) apply_filters( 'wpiseo_translate_string', $template, 'template' );
		}

		if ( false === strpos( $template, '%' ) ) {
			return $this->cleanup( $template );
		}

		$output = preg_replace_callback(
			self::PATTERN,
			function ( array $matches ) use ( $context ): string {
				return $this->replace( $matches, $context );
			},
			$template
		);

		/**
		 * Filters a rendered template.
		 *
		 * @param string  $output   Rendered text.
		 * @param string  $template Original template.
		 * @param Context $context  Context.
		 */
		$output = apply_filters( 'wpiseo_parsed_template', $this->cleanup( (string) $output ), $template, $context );

		return (string) $output;
	}

	/**
	 * Collapses whitespace, duplicate separators and dangling separators.
	 *
	 * Runs on the rendered string so that empty variables (e.g. `%page%` on
	 * page 1) do not leave `Title - - Site` or `- Site` behind.
	 *
	 * @param string $text Rendered text.
	 */
	public function cleanup( string $text ): string {
		$text      = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		$separator = trim( $this->separator() );

		if ( '' === $text || '' === $separator ) {
			return $text;
		}

		$sep  = preg_quote( $separator, '/' );
		$text = (string) preg_replace( '/(?:\s*' . $sep . '\s*){2,}/u', ' ' . $separator . ' ', $text );
		$text = (string) preg_replace( '/^\s*' . $sep . '\s*|\s*' . $sep . '\s*$/u', '', $text );

		return trim( $text );
	}

	/**
	 * Current separator setting.
	 */
	public function separator(): string {
		return (string) $this->options->get( 'general.separator', '-' );
	}

	/**
	 * Resolves one regex match.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @param Context            $context Context.
	 */
	private function replace( array $matches, Context $context ): string {
		$name       = $matches[1];
		$definition = $this->registry->get( $name );

		if ( null === $definition ) {
			return $matches[0];
		}

		$value = call_user_func( $definition['callback'], $context, trim( $matches[2] ?? '' ) );

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		// Variables yield plain text: strip markup and decode entities once.
		return html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
