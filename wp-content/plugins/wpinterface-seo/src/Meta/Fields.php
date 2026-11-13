<?php
/**
 * SEO meta field definitions.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Meta;

use WPInterface\SEO\Schema\Types;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for per-post, per-term and per-user SEO fields.
 *
 * Used for meta registration (REST/editor), the classic metabox save,
 * importers and uninstall. Keys are stored with the `_wpiseo_` prefix.
 */
final class Fields {

	public const PREFIX = '_wpiseo_';

	public const MAX_KEYWORDS = 5;

	/**
	 * Schema.org WebPage types offered per post.
	 */
	public const PAGE_TYPES = array( 'WebPage', 'AboutPage', 'ContactPage', 'CollectionPage', 'ItemPage', 'FAQPage', 'QAPage', 'ProfilePage', 'CheckoutPage', 'MedicalWebPage', 'RealEstateListing' );

	/**
	 * Schema.org Article types offered per post (`none` disables the Article node).
	 */
	public const ARTICLE_TYPES = array( 'none', 'Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'Report', 'ScholarlyArticle' );

	/**
	 * Social networks for profile URLs.
	 */
	public const NETWORKS = array( 'facebook', 'x', 'instagram', 'linkedin', 'youtube', 'pinterest', 'tiktok', 'threads', 'mastodon', 'bluesky', 'github', 'wikipedia' );

	/**
	 * Post fields: key => spec.
	 *
	 * Spec keys: type (string|boolean|integer|array|object), sanitize (see sanitize()),
	 * enum (allowed values), max (integer upper bound).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function post(): array {
		return array(
			'title'               => array(
				'type'     => 'string',
				'sanitize' => 'text',
			),
			'description'         => array(
				'type'     => 'string',
				'sanitize' => 'textarea',
			),
			'focus_keywords'      => array(
				'type'     => 'array',
				'sanitize' => 'keywords',
			),
			'noindex'             => array(
				'type'     => 'string',
				'sanitize' => 'enum',
				'enum'     => array( '', 'noindex', 'index' ),
			),
			'nofollow'            => array(
				'type'     => 'boolean',
				'sanitize' => 'bool',
			),
			'robots_advanced'     => array(
				'type'     => 'array',
				'sanitize' => 'list_enum',
				'enum'     => array( 'noarchive', 'nosnippet', 'noimageindex' ),
			),
			'canonical'           => array(
				'type'     => 'string',
				'sanitize' => 'url',
			),
			'og_title'            => array(
				'type'     => 'string',
				'sanitize' => 'text',
			),
			'og_description'      => array(
				'type'     => 'string',
				'sanitize' => 'textarea',
			),
			'og_image'            => array(
				'type'     => 'integer',
				'sanitize' => 'int',
			),
			'twitter_title'       => array(
				'type'     => 'string',
				'sanitize' => 'text',
			),
			'twitter_description' => array(
				'type'     => 'string',
				'sanitize' => 'textarea',
			),
			'twitter_image'       => array(
				'type'     => 'integer',
				'sanitize' => 'int',
			),
			'schema_page_type'    => array(
				'type'     => 'string',
				'sanitize' => 'enum',
				'enum'     => array_merge( array( '' ), self::PAGE_TYPES ),
			),
			'schema_article_type' => array(
				'type'     => 'string',
				'sanitize' => 'enum',
				'enum'     => array_merge( array( '' ), self::ARTICLE_TYPES ),
			),
			'breadcrumb_title'    => array(
				'type'     => 'string',
				'sanitize' => 'text',
			),
			'primary_terms'       => array(
				'type'     => 'object',
				'sanitize' => 'int_map',
			),
			'schema'              => array(
				'type'     => 'object',
				'sanitize' => 'schema',
			),
			'score'               => array(
				'type'     => 'integer',
				'sanitize' => 'int',
				'max'      => 100,
				'auth'     => 'edit_post',
			),
			'readability_score'   => array(
				'type'     => 'integer',
				'sanitize' => 'int',
				'max'      => 100,
				'auth'     => 'edit_post',
			),
		);
	}

	/**
	 * Term fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function term(): array {
		$post = self::post();

		return array_intersect_key(
			$post,
			array_flip( array( 'title', 'description', 'noindex', 'canonical', 'og_title', 'og_description', 'og_image', 'twitter_title', 'twitter_description', 'twitter_image', 'breadcrumb_title' ) )
		);
	}

	/**
	 * User fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function user(): array {
		return array(
			'social_profiles' => array(
				'type'     => 'object',
				'sanitize' => 'profiles',
			),
		);
	}

	/**
	 * Fields for an object type.
	 *
	 * @param string $object_type `post`, `term` or `user`.
	 * @return array<string, array<string, mixed>>
	 */
	public static function for_type( string $object_type ): array {
		switch ( $object_type ) {
			case 'term':
				return self::term();
			case 'user':
				return self::user();
			default:
				return self::post();
		}
	}

	/**
	 * Default (empty) value for a spec.
	 *
	 * @param array<string, mixed> $spec Field spec.
	 * @return mixed
	 */
	public static function default_value( array $spec ) {
		switch ( $spec['type'] ) {
			case 'boolean':
				return false;
			case 'integer':
				return 0;
			case 'array':
			case 'object':
				return array();
			default:
				return '';
		}
	}

	/**
	 * Whether a sanitized value equals the default (so the meta row can be deleted).
	 *
	 * @param array<string, mixed> $spec  Field spec.
	 * @param mixed                $value Sanitized value.
	 */
	public static function is_default( array $spec, $value ): bool {
		return self::default_value( $spec ) === $value;
	}

	/**
	 * Sanitizes a value according to its spec.
	 *
	 * @param array<string, mixed> $spec  Field spec.
	 * @param mixed                $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( array $spec, $value ) {
		switch ( $spec['sanitize'] ) {
			case 'text':
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

			case 'textarea':
				return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';

			case 'url':
				return is_string( $value ) ? self::sanitize_url( $value ) : '';

			case 'bool':
				return Sanitize::to_bool( $value );

			case 'int':
				$int = is_numeric( $value ) ? absint( $value ) : 0;
				return isset( $spec['max'] ) ? min( (int) $spec['max'], $int ) : $int;

			case 'enum':
				$value = is_scalar( $value ) ? (string) $value : '';
				return in_array( $value, $spec['enum'], true ) ? $value : '';

			case 'list_enum':
				$value = is_array( $value ) ? array_map( 'strval', array_filter( $value, 'is_scalar' ) ) : array();
				return array_values( array_intersect( $spec['enum'], $value ) );

			case 'keywords':
				return self::sanitize_keywords( $value );

			case 'int_map':
				return self::sanitize_int_map( $value );

			case 'profiles':
				return self::sanitize_profiles( $value );

			case 'schema':
				return Types::sanitize( $value );
		}

		return '';
	}

	/**
	 * REST schema for a spec.
	 *
	 * @param array<string, mixed> $spec Field spec.
	 * @return array<string, mixed>
	 */
	public static function rest_schema( array $spec ): array {
		switch ( $spec['type'] ) {
			case 'array':
				return array(
					'type'  => 'array',
					'items' => isset( $spec['enum'] )
						? array(
							'type' => 'string',
							'enum' => $spec['enum'],
						)
						: array( 'type' => 'string' ),
				);

			case 'object':
				if ( 'schema' === $spec['sanitize'] ) {
					return array(
						'type'                 => 'object',
						'properties'           => array(
							'type' => array( 'type' => 'string' ),
							'data' => array(
								'type'                 => 'object',
								'additionalProperties' => true,
							),
						),
						'additionalProperties' => false,
					);
				}

				if ( 'profiles' === $spec['sanitize'] ) {
					$properties = array();
					foreach ( self::NETWORKS as $network ) {
						$properties[ $network ] = array( 'type' => 'string' );
					}

					return array(
						'type'                 => 'object',
						'properties'           => $properties,
						'additionalProperties' => false,
					);
				}

				return array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => array( 'type' => 'integer' ),
				);

			default:
				$schema = array( 'type' => $spec['type'] );
				if ( isset( $spec['enum'] ) ) {
					$schema['enum'] = $spec['enum'];
				}
				return $schema;
		}
	}

	/**
	 * Sanitizes an http(s) URL; returns '' for anything else.
	 *
	 * @param string $url URL.
	 */
	public static function sanitize_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		$clean = esc_url_raw( $url, array( 'http', 'https' ) );

		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * Focus keywords: unique, non-empty, at most MAX_KEYWORDS (filterable), 100 chars each.
	 *
	 * Accepts an array or a comma-separated string (importers).
	 *
	 * @param mixed $value Raw value.
	 * @return list<string>
	 */
	public static function sanitize_keywords( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		/**
		 * Filters the maximum number of focus keywords per post.
		 *
		 * @param int $max Maximum (default 5).
		 */
		$max      = max( 1, (int) apply_filters( 'wpiseo_max_focus_keywords', self::MAX_KEYWORDS ) );
		$keywords = array();

		foreach ( $value as $keyword ) {
			if ( ! is_scalar( $keyword ) ) {
				continue;
			}

			$keyword = mb_substr( trim( sanitize_text_field( (string) $keyword ) ), 0, 100 );

			if ( '' !== $keyword && ! in_array( mb_strtolower( $keyword ), array_map( 'mb_strtolower', $keywords ), true ) ) {
				$keywords[] = $keyword;
			}
		}

		return array_slice( $keywords, 0, $max );
	}

	/**
	 * Map of taxonomy => term ID.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, int>
	 */
	private static function sanitize_int_map( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $key => $id ) {
			$key = sanitize_key( (string) $key );
			$id  = is_numeric( $id ) ? absint( $id ) : 0;

			if ( '' !== $key && $id > 0 ) {
				$clean[ $key ] = $id;
			}
		}

		return $clean;
	}

	/**
	 * Social profile URLs keyed by network. `@handle` is accepted for X.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, string>
	 */
	public static function sanitize_profiles( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( self::NETWORKS as $network ) {
			$url = isset( $value[ $network ] ) && is_string( $value[ $network ] ) ? trim( $value[ $network ] ) : '';

			if ( 'x' === $network && '' !== $url && preg_match( '/^@?([A-Za-z0-9_]{1,15})$/', $url, $matches ) ) {
				$url = 'https://x.com/' . $matches[1];
			}

			$url = self::sanitize_url( $url );

			if ( '' !== $url ) {
				$clean[ $network ] = $url;
			}
		}

		return $clean;
	}
}
