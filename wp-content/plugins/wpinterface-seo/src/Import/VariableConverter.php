<?php
/**
 * Template variable conversion from other SEO plugins.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Converts Yoast (`%%title%%`) and Rank Math (`%title%`) variables to ours.
 * Variables without an equivalent are removed.
 */
final class VariableConverter {

	/**
	 * Source variable => our variable.
	 */
	private const MAP = array(
		'title'                  => 'title',
		'archive_title'          => 'title',
		'sitename'               => 'sitename',
		'sitedesc'               => 'tagline',
		'sep'                    => 'sep',
		'excerpt'                => 'excerpt',
		'excerpt_only'           => 'excerpt',
		'category'               => 'category',
		'categories'             => 'category',
		'primary_category'       => 'primary_category',
		'primary_taxonomy_terms' => 'primary_category',
		'tag'                    => 'tag',
		'tags'                   => 'tag',
		'term'                   => 'term_title',
		'term_title'             => 'term_title',
		'category_title'         => 'term_title',
		'term_description'       => 'term_description',
		'category_description'   => 'term_description',
		'tag_description'        => 'term_description',
		'name'                   => 'author',
		'date'                   => 'date',
		'modified'               => 'modified',
		'page'                   => 'page',
		'pagenumber'             => 'pagenumber',
		'pagetotal'              => 'pagetotal',
		'searchphrase'           => 'search_query',
		'search_query'           => 'search_query',
		'pt_single'              => 'post_type_name',
		'pt_plural'              => 'post_type_name',
		'currentyear'            => 'currentyear',
		'currentmonth'           => 'currentmonth',
		'currentdate'            => 'currentdate',
		'id'                     => 'id',
	);

	/**
	 * Converts a Yoast template.
	 *
	 * @param string $template Template.
	 */
	public static function from_yoast( string $template ): string {
		$converted = preg_replace_callback(
			'/%%([a-z0-9_-]+)%%/i',
			static function ( array $matches ): string {
				$name = strtolower( $matches[1] );

				if ( 0 === strpos( $name, 'cf_' ) ) {
					return '%custom_field(' . substr( $name, 3 ) . ')%';
				}

				if ( 0 === strpos( $name, 'ct_desc_' ) ) {
					return '';
				}

				if ( 0 === strpos( $name, 'ct_' ) ) {
					return '%term(' . substr( $name, 3 ) . ')%';
				}

				return isset( self::MAP[ $name ] ) ? '%' . self::MAP[ $name ] . '%' : '';
			},
			$template
		);

		return self::tidy( (string) $converted );
	}

	/**
	 * Converts a Rank Math template.
	 *
	 * @param string $template Template.
	 */
	public static function from_rank_math( string $template ): string {
		$converted = preg_replace_callback(
			'/%([a-z0-9_]+)(?:\(([^()%]*)\))?%/i',
			static function ( array $matches ): string {
				$name     = strtolower( $matches[1] );
				$argument = $matches[2] ?? '';

				if ( 'customfield' === $name && '' !== $argument ) {
					return '%custom_field(' . $argument . ')%';
				}

				if ( 'customterm' === $name && '' !== $argument ) {
					return '%term(' . $argument . ')%';
				}

				if ( 'date' === $name || 'modified' === $name ) {
					return '%' . $name . ( '' !== $argument ? '(' . $argument . ')' : '' ) . '%';
				}

				return isset( self::MAP[ $name ] ) ? '%' . self::MAP[ $name ] . '%' : '';
			},
			$template
		);

		return self::tidy( (string) $converted );
	}

	/**
	 * Converts an SEOPress template (%%post_title%%).
	 *
	 * @param string $template Template.
	 */
	public static function from_seopress( string $template ): string {
		$map = array(
			'post_title'            => 'title',
			'archive_title'         => 'title',
			'post_excerpt'          => 'excerpt',
			'post_content'          => 'excerpt',
			'sitetitle'             => 'sitename',
			'tagline'               => 'tagline',
			'sep'                   => 'sep',
			'term_title'            => 'term_title',
			'_category_title'       => 'term_title',
			'tag_title'             => 'term_title',
			'term_description'      => 'term_description',
			'_category_description' => 'term_description',
			'tag_description'       => 'term_description',
			'post_author'           => 'author',
			'post_category'         => 'category',
			'post_tag'              => 'tag',
			'post_date'             => 'date',
			'post_modified_date'    => 'modified',
			'search_keywords'       => 'search_query',
			'current_pagination'    => 'page',
			'page'                  => 'page',
			'cpt_plural'            => 'post_type_name',
			'currentyear'           => 'currentyear',
			'currentmonth'          => 'currentmonth',
			'currentdate'           => 'currentdate',
		);

		$converted = preg_replace_callback(
			'/%%([a-zA-Z0-9_-]+)%%/',
			static function ( array $matches ) use ( $map ): string {
				$name = $matches[1];

				if ( 0 === strpos( $name, '_cf_' ) ) {
					return '%custom_field(' . substr( $name, 4 ) . ')%';
				}

				if ( 0 === strpos( $name, '_ct_' ) ) {
					return '%term(' . substr( $name, 4 ) . ')%';
				}

				return isset( $map[ $name ] ) ? '%' . $map[ $name ] . '%' : '';
			},
			$template
		);

		return self::tidy( (string) $converted );
	}

	/**
	 * Converts an All in One SEO template (#post_title #separator_sa #site_title).
	 * Unknown #words are kept (they may be literal hashtags).
	 *
	 * @param string $template Template.
	 */
	public static function from_aioseo( string $template ): string {
		$map = array(
			'post_title'           => 'title',
			'archive_title'        => 'title',
			'site_title'           => 'sitename',
			'blog_title'           => 'sitename',
			'separator_sa'         => 'sep',
			'tagline'              => 'tagline',
			'site_description'     => 'tagline',
			'post_excerpt'         => 'excerpt',
			'post_excerpt_only'    => 'excerpt',
			'post_content'         => 'excerpt',
			'description'          => 'excerpt',
			'taxonomy_title'       => 'term_title',
			'taxonomy_description' => 'term_description',
			'author_name'          => 'author',
			'category'             => 'category',
			'categories'           => 'category',
			'current_year'         => 'currentyear',
			'current_month'        => 'currentmonth',
			'current_date'         => 'currentdate',
			'page_number'          => 'page',
			'search_term'          => 'search_query',
			'post_date'            => 'date',
			'post_modified_date'   => 'modified',
		);

		$template = html_entity_decode( $template, ENT_QUOTES, 'UTF-8' );

		$converted = preg_replace_callback(
			'/#([a-z_]+)(?:-([a-zA-Z0-9_-]+))?(?![a-zA-Z0-9_])/',
			static function ( array $matches ) use ( $map ): string {
				$name     = $matches[1];
				$argument = $matches[2] ?? '';

				if ( 'custom_field' === $name && '' !== $argument ) {
					return '%custom_field(' . $argument . ')%';
				}

				if ( 'tax_name' === $name && '' !== $argument ) {
					return '%term(' . $argument . ')%';
				}

				return isset( $map[ $name ] ) ? '%' . $map[ $name ] . '%' : $matches[0];
			},
			$template
		);

		return self::tidy( (string) $converted );
	}

	/**
	 * Converts a Slim SEO template ({{ post.title }}).
	 *
	 * @param string $template Template.
	 */
	public static function from_slim_seo( string $template ): string {
		$map = array(
			'post.title'                => 'title',
			'post.excerpt'              => 'excerpt',
			'post.content'              => 'excerpt',
			'post.auto_description'     => 'excerpt',
			'post.date'                 => 'date',
			'post.modified_date'        => 'modified',
			'post.categories'           => 'category',
			'post.tags'                 => 'tag',
			'site.title'                => 'sitename',
			'site.description'          => 'tagline',
			'sep'                       => 'sep',
			'page'                      => 'page',
			'term.name'                 => 'term_title',
			'term.description'          => 'term_description',
			'term.auto_description'     => 'term_description',
			'author.display_name'       => 'author',
			'user.display_name'         => 'author',
			'post_type.labels.singular' => 'post_type_name',
			'post_type.labels.plural'   => 'post_type_name',
			'current.year'              => 'currentyear',
			'current.month'             => 'currentmonth',
		);

		$converted = preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
			static function ( array $matches ) use ( $map ): string {
				$name = $matches[1];

				if ( 0 === strpos( $name, 'post.custom_field.' ) ) {
					return '%custom_field(' . substr( $name, 18 ) . ')%';
				}

				if ( 0 === strpos( $name, 'post.tax.' ) ) {
					return '%term(' . substr( $name, 9 ) . ')%';
				}

				return isset( $map[ $name ] ) ? '%' . $map[ $name ] . '%' : '';
			},
			$template
		);

		return self::tidy( (string) $converted );
	}

	/**
	 * Collapses whitespace left by removed variables.
	 *
	 * @param string $template Template.
	 */
	private static function tidy( string $template ): string {
		return trim( (string) preg_replace( '/\s{2,}/', ' ', $template ) );
	}
}
