<?php
/**
 * SEOPress importer.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Imports SEOPress post/term meta (`_seopress_*`) and the
 * `seopress_titles_option_name` / `seopress_social_option_name` options.
 */
final class SeoPressImporter extends Importer {

	private const PREFIX = '_seopress_';

	/**
	 * Machine name.
	 */
	public function id(): string {
		return 'seopress';
	}

	/**
	 * Human name.
	 */
	public function label(): string {
		return 'SEOPress';
	}

	/**
	 * Post meta prefix.
	 */
	protected function post_meta_prefix(): string {
		return self::PREFIX;
	}

	/**
	 * Maps one post's meta.
	 *
	 * @param array<string, string> $meta Source meta.
	 * @return array<string, mixed>
	 */
	protected function map_post( array $meta ): array {
		$fields = $this->map_object( $meta );
		$get    = static fn( string $key ): string => (string) ( $meta[ self::PREFIX . $key ] ?? '' );

		$fields['focus_keywords'] = $get( 'analysis_target_kw' );

		if ( is_numeric( $get( 'robots_primary_cat' ) ) ) {
			$fields['primary_terms'] = array( 'category' => (int) $get( 'robots_primary_cat' ) );
		}

		return $fields;
	}

	/**
	 * Term data from `_seopress_*` term meta.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function term_data(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off import query.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( self::PREFIX ) . '%' ) );
		$by   = array();

		foreach ( (array) $rows as $row ) {
			$by[ (int) $row->term_id ][ (string) $row->meta_key ] = (string) $row->meta_value;
		}

		return array_map( array( $this, 'map_object' ), $by );
	}

	/**
	 * SEOPress has no per-user profile fields in the free plugin.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function user_profiles(): array {
		return array();
	}

	/**
	 * Settings.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		$titles = get_option( 'seopress_titles_option_name', array() );
		$social = get_option( 'seopress_social_option_name', array() );

		if ( ! is_array( $titles ) || array() === $titles ) {
			return array();
		}

		$text = static fn( $value ): string => VariableConverter::from_seopress( (string) $value );
		$flag = static fn( string $key ): bool => ! empty( $titles[ $key ] );
		$meta = array(
			'post_types'         => array(),
			'taxonomies'         => array(),
			'post_type_archives' => array(),
			'archives'           => array(
				'home'      => array(
					'title'       => $text( $titles['seopress_titles_home_site_title'] ?? '' ),
					'description' => $text( $titles['seopress_titles_home_site_desc'] ?? '' ),
				),
				'author'    => array(
					'title'       => $text( $titles['seopress_titles_archives_author_title'] ?? '' ),
					'description' => $text( $titles['seopress_titles_archives_author_desc'] ?? '' ),
					'noindex'     => $flag( 'seopress_titles_archives_author_noindex' ),
					'disabled'    => $flag( 'seopress_titles_archives_author_disable' ),
				),
				'date'      => array(
					'title'       => $text( $titles['seopress_titles_archives_date_title'] ?? '' ),
					'description' => $text( $titles['seopress_titles_archives_date_desc'] ?? '' ),
					'noindex'     => $flag( 'seopress_titles_archives_date_noindex' ),
					'disabled'    => $flag( 'seopress_titles_archives_date_disable' ),
				),
				'search'    => array( 'title' => $text( $titles['seopress_titles_archives_search_title'] ?? '' ) ),
				'not_found' => array( 'title' => $text( $titles['seopress_titles_archives_404_title'] ?? '' ) ),
			),
			'noindex_paginated'  => $flag( 'seopress_titles_paged_noindex' ),
		);

		$groups = array(
			'seopress_titles_single_titles'  => 'post_types',
			'seopress_titles_tax_titles'     => 'taxonomies',
			'seopress_titles_archive_titles' => 'post_type_archives',
		);

		foreach ( $groups as $option_key => $group ) {
			foreach ( (array) ( $titles[ $option_key ] ?? array() ) as $name => $values ) {
				if ( ! is_array( $values ) ) {
					continue;
				}

				$meta[ $group ][ (string) $name ] = array(
					'title'       => $text( $values['title'] ?? '' ),
					'description' => $text( $values['description'] ?? '' ),
					'noindex'     => ! empty( $values['noindex'] ),
				);
			}
		}

		$settings = array(
			'general' => array( 'separator' => html_entity_decode( (string) ( $titles['seopress_titles_sep'] ?? '-' ), ENT_QUOTES, 'UTF-8' ) ),
			'meta'    => YoastImporter::drop_empty_strings( $meta ),
		);

		if ( is_array( $social ) && array() !== $social ) {
			$settings['social'] = array(
				'facebook_app_id' => (string) ( $social['seopress_social_facebook_app_id'] ?? '' ),
				'profiles'        => array(
					'facebook'  => (string) ( $social['seopress_social_accounts_facebook'] ?? '' ),
					'x'         => (string) ( $social['seopress_social_accounts_twitter'] ?? '' ),
					'instagram' => (string) ( $social['seopress_social_accounts_instagram'] ?? '' ),
					'youtube'   => (string) ( $social['seopress_social_accounts_youtube'] ?? '' ),
					'linkedin'  => (string) ( $social['seopress_social_accounts_linkedin'] ?? '' ),
					'pinterest' => (string) ( $social['seopress_social_accounts_pinterest'] ?? '' ),
				),
			);

			$settings['schema'] = array_filter(
				array(
					'site_type' => 'person' === ( $social['seopress_social_knowledge_type'] ?? '' ) ? 'person' : 'organization',
					'name'      => (string) ( $social['seopress_social_knowledge_name'] ?? '' ),
				)
			);
		}

		return $settings;
	}

	/**
	 * Maps post or term meta (shared keys).
	 *
	 * @param array<string, string> $meta Source meta.
	 * @return array<string, mixed>
	 */
	public function map_object( array $meta ): array {
		$get    = static fn( string $key ): string => (string) ( $meta[ self::PREFIX . $key ] ?? '' );
		$fields = array(
			'title'               => VariableConverter::from_seopress( $get( 'titles_title' ) ),
			'description'         => VariableConverter::from_seopress( $get( 'titles_desc' ) ),
			'canonical'           => $get( 'robots_canonical' ),
			'og_title'            => VariableConverter::from_seopress( $get( 'social_fb_title' ) ),
			'og_description'      => VariableConverter::from_seopress( $get( 'social_fb_desc' ) ),
			'og_image'            => self::image_id( $get( 'social_fb_img_attachment_id' ), $get( 'social_fb_img' ) ),
			'twitter_title'       => VariableConverter::from_seopress( $get( 'social_twitter_title' ) ),
			'twitter_description' => VariableConverter::from_seopress( $get( 'social_twitter_desc' ) ),
			'twitter_image'       => self::image_id( $get( 'social_twitter_img_attachment_id' ), $get( 'social_twitter_img' ) ),
			'nofollow'            => 'yes' === $get( 'robots_follow' ),
		);

		// 'yes' means the negative directive.
		if ( 'yes' === $get( 'robots_index' ) ) {
			$fields['noindex'] = 'noindex';
		}

		$advanced = array();
		if ( 'yes' === $get( 'robots_snippet' ) ) {
			$advanced[] = 'nosnippet';
		}
		if ( 'yes' === $get( 'robots_imageindex' ) ) {
			$advanced[] = 'noimageindex';
		}
		if ( $advanced ) {
			$fields['robots_advanced'] = $advanced;
		}

		return $fields;
	}
}
