<?php
/**
 * Yoast SEO importer.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Import;

use WPInterface\SEO\Meta\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Imports Yoast SEO (free and Premium) post meta, `wpseo_taxonomy_meta`,
 * user contact methods and the `wpseo_titles` / `wpseo_social` options.
 */
final class YoastImporter extends Importer {

	private const PREFIX = '_yoast_wpseo_';

	/**
	 * Yoast separator option values.
	 */
	private const SEPARATORS = array(
		'sc-dash'   => '-',
		'sc-ndash'  => '–',
		'sc-mdash'  => '—',
		'sc-colon'  => ':',
		'sc-middot' => '·',
		'sc-bull'   => '•',
		'sc-star'   => '*',
		'sc-smstar' => '⋆',
		'sc-pipe'   => '|',
		'sc-tilde'  => '~',
		'sc-laquo'  => '«',
		'sc-raquo'  => '»',
		'sc-lt'     => '>',
		'sc-gt'     => '<',
	);

	/**
	 * Machine name.
	 */
	public function id(): string {
		return 'yoast';
	}

	/**
	 * Human name.
	 */
	public function label(): string {
		return 'Yoast SEO';
	}

	/**
	 * Post meta prefix.
	 */
	protected function post_meta_prefix(): string {
		return self::PREFIX;
	}

	/**
	 * Maps one post's Yoast meta.
	 *
	 * @param array<string, string> $meta Source meta.
	 * @return array<string, mixed>
	 */
	protected function map_post( array $meta ): array {
		$get    = static fn( string $key ): string => (string) ( $meta[ self::PREFIX . $key ] ?? '' );
		$fields = array(
			'title'               => VariableConverter::from_yoast( $get( 'title' ) ),
			'description'         => VariableConverter::from_yoast( $get( 'metadesc' ) ),
			'canonical'           => $get( 'canonical' ),
			'breadcrumb_title'    => $get( 'bctitle' ),
			'og_title'            => VariableConverter::from_yoast( $get( 'opengraph-title' ) ),
			'og_description'      => VariableConverter::from_yoast( $get( 'opengraph-description' ) ),
			'og_image'            => self::image_id( $get( 'opengraph-image-id' ), $get( 'opengraph-image' ) ),
			'twitter_title'       => VariableConverter::from_yoast( $get( 'twitter-title' ) ),
			'twitter_description' => VariableConverter::from_yoast( $get( 'twitter-description' ) ),
			'twitter_image'       => self::image_id( $get( 'twitter-image-id' ), $get( 'twitter-image' ) ),
			'nofollow'            => '1' === $get( 'meta-robots-nofollow' ),
		);

		$noindex = $get( 'meta-robots-noindex' );
		if ( '1' === $noindex ) {
			$fields['noindex'] = 'noindex';
		} elseif ( '2' === $noindex ) {
			$fields['noindex'] = 'index';
		}

		$advanced = array_map( 'trim', explode( ',', $get( 'meta-robots-adv' ) ) );
		$advanced = array_values( array_intersect( array( 'noarchive', 'nosnippet', 'noimageindex' ), $advanced ) );
		if ( $advanced ) {
			$fields['robots_advanced'] = $advanced;
		}

		$keywords = array( $get( 'focuskw' ) );
		$extra    = json_decode( $get( 'focuskeywords' ), true );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $item ) {
				if ( is_array( $item ) && isset( $item['keyword'] ) ) {
					$keywords[] = (string) $item['keyword'];
				}
			}
		}
		$fields['focus_keywords'] = array_values( array_filter( $keywords ) );

		$page_type = $get( 'schema_page_type' );
		if ( in_array( $page_type, Fields::PAGE_TYPES, true ) ) {
			$fields['schema_page_type'] = $page_type;
		}

		$article_type = $get( 'schema_article_type' );
		if ( 'None' === $article_type ) {
			$fields['schema_article_type'] = 'none';
		} elseif ( in_array( $article_type, Fields::ARTICLE_TYPES, true ) ) {
			$fields['schema_article_type'] = $article_type;
		}

		$primary = array();
		foreach ( $meta as $key => $value ) {
			if ( 0 === strpos( $key, self::PREFIX . 'primary_' ) && is_numeric( $value ) && (int) $value > 0 ) {
				$primary[ substr( $key, strlen( self::PREFIX . 'primary_' ) ) ] = (int) $value;
			}
		}
		if ( $primary ) {
			$fields['primary_terms'] = $primary;
		}

		return $fields;
	}

	/**
	 * Term data from `wpseo_taxonomy_meta`.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function term_data(): array {
		$option = get_option( 'wpseo_taxonomy_meta', array() );
		$data   = array();

		if ( ! is_array( $option ) ) {
			return $data;
		}

		foreach ( $option as $terms ) {
			if ( ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term_id => $meta ) {
				if ( ! is_array( $meta ) ) {
					continue;
				}

				$get    = static fn( string $key ): string => (string) ( $meta[ 'wpseo_' . $key ] ?? '' );
				$fields = array(
					'title'               => VariableConverter::from_yoast( $get( 'title' ) ),
					'description'         => VariableConverter::from_yoast( $get( 'desc' ) ),
					'canonical'           => $get( 'canonical' ),
					'breadcrumb_title'    => $get( 'bctitle' ),
					'og_title'            => VariableConverter::from_yoast( $get( 'opengraph-title' ) ),
					'og_description'      => VariableConverter::from_yoast( $get( 'opengraph-description' ) ),
					'og_image'            => self::image_id( $get( 'opengraph-image-id' ), $get( 'opengraph-image' ) ),
					'twitter_title'       => VariableConverter::from_yoast( $get( 'twitter-title' ) ),
					'twitter_description' => VariableConverter::from_yoast( $get( 'twitter-description' ) ),
					'twitter_image'       => self::image_id( $get( 'twitter-image-id' ), $get( 'twitter-image' ) ),
				);

				if ( in_array( $get( 'noindex' ), array( 'noindex', 'index' ), true ) ) {
					$fields['noindex'] = $get( 'noindex' );
				}

				$data[ (int) $term_id ] = $fields;
			}
		}

		return $data;
	}

	/**
	 * User profiles from Yoast's contact methods.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function user_profiles(): array {
		$networks = array( 'facebook', 'twitter', 'instagram', 'linkedin', 'pinterest', 'youtube', 'wikipedia' );

		return self::profiles_from_user_meta( $networks, array( 'twitter' => 'x' ) );
	}

	/**
	 * Settings from `wpseo_titles` and `wpseo_social`.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		$titles = get_option( 'wpseo_titles', array() );
		$social = get_option( 'wpseo_social', array() );

		if ( ! is_array( $titles ) || array() === $titles ) {
			return array();
		}

		$text = static fn( string $key ): string => VariableConverter::from_yoast( (string) ( $titles[ $key ] ?? '' ) );
		$bool = static fn( string $key ): bool => ! empty( $titles[ $key ] );
		$meta = array(
			'post_types'         => array(),
			'taxonomies'         => array(),
			'post_type_archives' => array(),
			'archives'           => array(
				'home'      => array_filter(
					array(
						'title'       => $text( 'title-home-wpseo' ),
						'description' => $text( 'metadesc-home-wpseo' ),
					)
				),
				'author'    => array(
					'title'       => $text( 'title-author-wpseo' ),
					'description' => $text( 'metadesc-author-wpseo' ),
					'noindex'     => $bool( 'noindex-author-wpseo' ),
					'disabled'    => $bool( 'disable-author' ),
				),
				'date'      => array(
					'title'       => $text( 'title-archive-wpseo' ),
					'description' => $text( 'metadesc-archive-wpseo' ),
					'noindex'     => $bool( 'noindex-archive-wpseo' ),
					'disabled'    => $bool( 'disable-date' ),
				),
				'search'    => array_filter( array( 'title' => $text( 'title-search-wpseo' ) ) ),
				'not_found' => array_filter( array( 'title' => $text( 'title-404-wpseo' ) ) ),
			),
		);

		$schema      = array( 'post_types' => array() );
		$breadcrumbs = array( 'taxonomies' => array() );

		foreach ( $titles as $key => $value ) {
			$key = (string) $key;

			if ( preg_match( '/^(title|metadesc|noindex)-(tax|ptarchive)-(.+)$/', $key, $m ) ) {
				$group = 'tax' === $m[2] ? 'taxonomies' : 'post_type_archives';
				$meta[ $group ][ $m[3] ][ self::setting_key( $m[1] ) ] = 'noindex' === $m[1] ? ! empty( $value ) : VariableConverter::from_yoast( (string) $value );
			} elseif ( preg_match( '/^(title|metadesc|noindex)-(.+)$/', $key, $m ) && post_type_exists( $m[2] ) ) {
				$meta['post_types'][ $m[2] ][ self::setting_key( $m[1] ) ] = 'noindex' === $m[1] ? ! empty( $value ) : VariableConverter::from_yoast( (string) $value );
			} elseif ( preg_match( '/^schema-(page|article)-type-(.+)$/', $key, $m ) ) {
				$value = 'None' === $value ? 'none' : (string) $value;
				$schema['post_types'][ $m[2] ][ $m[1] . '_type' ] = $value;
			} elseif ( preg_match( '/^post_types-(.+)-maintax$/', $key, $m ) ) {
				$breadcrumbs['taxonomies'][ $m[1] ] = '0' === (string) $value ? '' : (string) $value;
			}
		}

		$separator = (string) ( $titles['separator'] ?? '' );

		$settings = array(
			'general'     => array( 'separator' => self::SEPARATORS[ $separator ] ?? '-' ),
			'meta'        => self::drop_empty_strings( $meta ),
			'schema'      => array_merge(
				$schema,
				array(
					'site_type'      => 'person' === ( $titles['company_or_person'] ?? '' ) ? 'person' : 'organization',
					'name'           => (string) ( $titles['company_name'] ?? '' ),
					'alternate_name' => (string) ( $titles['company_alternate_name'] ?? '' ),
					'logo'           => (int) ( $titles['company_logo_id'] ?? 0 ),
					'person'         => (int) ( $titles['company_or_person_user_id'] ?? 0 ),
				)
			),
			'breadcrumbs' => array_merge(
				$breadcrumbs,
				array_filter(
					array(
						'separator'  => html_entity_decode( (string) ( $titles['breadcrumbs-sep'] ?? '' ), ENT_QUOTES, 'UTF-8' ),
						'home_label' => (string) ( $titles['breadcrumbs-home'] ?? '' ),
						'prefix'     => (string) ( $titles['breadcrumbs-prefix'] ?? '' ),
					)
				)
			),
		);

		if ( is_array( $social ) && array() !== $social ) {
			$other = array_values( array_filter( (array) ( $social['other_social_urls'] ?? array() ), 'is_string' ) );

			$settings['social'] = array(
				'opengraph'     => ! isset( $social['opengraph'] ) || ! empty( $social['opengraph'] ),
				'twitter'       => ! isset( $social['twitter'] ) || ! empty( $social['twitter'] ),
				'default_image' => self::image_id( (string) ( $social['og_default_image_id'] ?? '' ), (string) ( $social['og_default_image'] ?? '' ) ),
				'profiles'      => array(
					'facebook' => (string) ( $social['facebook_site'] ?? '' ),
					'x'        => (string) ( $social['twitter_site'] ?? '' ),
					'other'    => $other,
				),
			);
		}

		return $settings;
	}

	/**
	 * Removes empty template strings so our defaults apply instead.
	 *
	 * @param array<mixed> $values Values.
	 * @return array<mixed>
	 */
	public static function drop_empty_strings( array $values ): array {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$values[ $key ] = self::drop_empty_strings( $value );
			} elseif ( '' === $value ) {
				unset( $values[ $key ] );
			}
		}

		return $values;
	}

	/**
	 * Yoast option prefix to our setting key.
	 *
	 * @param string $prefix `title`, `metadesc` or `noindex`.
	 */
	private static function setting_key( string $prefix ): string {
		return 'metadesc' === $prefix ? 'description' : $prefix;
	}

	/**
	 * Reads plain user meta keys into profile URLs.
	 *
	 * @param string[]              $keys   User meta keys.
	 * @param array<string, string> $rename Key => our network name.
	 * @return array<int, array<string, string>>
	 */
	public static function profiles_from_user_meta( array $keys, array $rename = array() ): array {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-off import; placeholders built above.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders}) AND meta_value <> ''", $keys ) );

		$profiles = array();

		foreach ( (array) $rows as $row ) {
			$network = $rename[ (string) $row->meta_key ] ?? (string) $row->meta_key;

			if ( in_array( $network, Fields::NETWORKS, true ) ) {
				$profiles[ (int) $row->user_id ][ $network ] = (string) $row->meta_value;
			}
		}

		foreach ( $profiles as $user_id => $networks ) {
			$profiles[ $user_id ] = Fields::sanitize_profiles( $networks );
		}

		return array_filter( $profiles );
	}
}
