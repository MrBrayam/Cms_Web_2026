<?php
/**
 * Schema module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Schema;

use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Options;
use WPInterface\SEO\Presentation\Presentation;
use WPInterface\SEO\Schema\Graph;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the JSON-LD graph.
 */
final class SchemaModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'site_type'      => 'organization',
			'name'           => '',
			'alternate_name' => '',
			'logo'           => 0,
			'person'         => 0,
			'breadcrumbs'    => true,
			'post_types'     => array(),
		);
	}

	/**
	 * Effective schema settings for a post type.
	 *
	 * @param Options $options   Settings.
	 * @param string  $post_type Post type.
	 * @return array{page_type: string, article_type: string}
	 */
	public static function post_type( Options $options, string $post_type ): array {
		$stored = $options->get( 'schema.post_types.' . $post_type, array() );

		return array_merge(
			array(
				'page_type'    => 'WebPage',
				'article_type' => 'post' === $post_type ? 'Article' : 'none',
			),
			is_array( $stored ) ? $stored : array()
		);
	}

	/**
	 * Sanitizes the section.
	 *
	 * @param array<string, mixed> $values   Values.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $values, array $defaults ): array {
		$post_types = array();

		if ( is_array( $values['post_types'] ?? null ) ) {
			foreach ( array_slice( $values['post_types'], 0, 200, true ) as $post_type => $types ) {
				$post_type = sanitize_key( (string) $post_type );

				if ( '' === $post_type || ! is_array( $types ) ) {
					continue;
				}

				$clean = array();

				if ( isset( $types['page_type'] ) && in_array( $types['page_type'], Fields::PAGE_TYPES, true ) ) {
					$clean['page_type'] = $types['page_type'];
				}

				if ( isset( $types['article_type'] ) && in_array( $types['article_type'], Fields::ARTICLE_TYPES, true ) ) {
					$clean['article_type'] = $types['article_type'];
				}

				$post_types[ $post_type ] = $clean;
			}
		}

		return array(
			'site_type'      => 'person' === ( $values['site_type'] ?? '' ) ? 'person' : 'organization',
			'name'           => sanitize_text_field( (string) ( $values['name'] ?? '' ) ),
			'alternate_name' => sanitize_text_field( (string) ( $values['alternate_name'] ?? '' ) ),
			'logo'           => absint( $values['logo'] ?? 0 ),
			'person'         => absint( $values['person'] ?? 0 ),
			'breadcrumbs'    => Sanitize::to_bool( $values['breadcrumbs'] ?? $defaults['breadcrumbs'] ),
			'post_types'     => $post_types,
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		if ( $this->plugin->output_enabled() ) {
			add_action( 'wpiseo_head', array( $this, 'render' ), 90 );
		}

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 25 );
		}
	}

	/**
	 * Prints the JSON-LD script.
	 *
	 * @param Presentation $presentation Resolved page values.
	 */
	public function render( $presentation ): void {
		if ( ! $presentation instanceof Presentation ) {
			return;
		}

		$document = ( new Graph( $this->plugin->options(), $presentation ) )->build();

		if ( array() === $document ) {
			return;
		}

		// JSON_HEX_TAG encodes < and >, so no value can close or confuse the script element.
		$json = wp_json_encode( $document, JSON_HEX_TAG );

		if ( is_string( $json ) ) {
			wp_print_inline_script_tag(
				$json,
				array(
					'type'  => 'application/ld+json',
					'class' => 'wpiseo-schema',
				)
			);
		}
	}

	/**
	 * Adds the Schema settings page.
	 *
	 * @param array<string, array<string, string>> $pages Pages.
	 * @return array<string, array<string, string>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['schema'] = array(
			'title'      => __( 'Schema', 'wpinterface-seo' ),
			'menu_title' => __( 'Schema', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}
}
