<?php
/**
 * Rank Math importer.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Imports Rank Math post/term meta (`rank_math_*`), user profiles and the
 * `rank-math-options-titles` / `rank-math-options-general` options.
 *
 * Rank Math is not installed in the development environment, so this
 * mapping follows its documented meta keys; unknown keys are ignored.
 */
final class RankMathImporter extends Importer {

	private const PREFIX = 'rank_math_';

	/**
	 * Machine name.
	 */
	public function id(): string {
		return 'rankmath';
	}

	/**
	 * Human name.
	 */
	public function label(): string {
		return 'Rank Math';
	}

	/**
	 * Post meta prefix.
	 */
	protected function post_meta_prefix(): string {
		return self::PREFIX;
	}

	/**
	 * Maps one post's Rank Math meta.
	 *
	 * @param array<string, string> $meta Source meta.
	 * @return array<string, mixed>
	 */
	protected function map_post( array $meta ): array {
		return $this->map_object( $meta );
	}

	/**
	 * Term data from `rank_math_*` term meta.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function term_data(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off import query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( self::PREFIX ) . '%'
			)
		);

		$by_term = array();

		foreach ( (array) $rows as $row ) {
			$by_term[ (int) $row->term_id ][ (string) $row->meta_key ] = (string) $row->meta_value;
		}

		$data = array();

		foreach ( $by_term as $term_id => $meta ) {
			$data[ $term_id ] = array_diff_key( $this->map_object( $meta ), array_flip( array( 'focus_keywords', 'primary_terms', 'nofollow', 'robots_advanced' ) ) );
		}

		return $data;
	}

	/**
	 * User profiles.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function user_profiles(): array {
		return YoastImporter::profiles_from_user_meta( array( 'facebook', 'twitter' ), array( 'twitter' => 'x' ) );
	}

	/**
	 * Settings from Rank Math options.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		$titles  = get_option( 'rank-math-options-titles', array() );
		$general = get_option( 'rank-math-options-general', array() );

		if ( ! is_array( $titles ) || array() === $titles ) {
			return array();
		}

		$text = static fn( string $key ): string => VariableConverter::from_rank_math( (string) ( $titles[ $key ] ?? '' ) );
		$on   = static fn( string $key ): bool => 'on' === ( $titles[ $key ] ?? '' );
		$meta = array(
			'post_types'         => array(),
			'taxonomies'         => array(),
			'post_type_archives' => array(),
			'archives'           => array(
				'home'      => array(
					'title'       => $text( 'homepage_title' ),
					'description' => $text( 'homepage_description' ),
				),
				'author'    => array(
					'title'       => $text( 'author_archive_title' ),
					'description' => $text( 'author_archive_description' ),
					'noindex'     => $on( 'author_custom_robots' ) && self::robots_noindex( $titles['author_robots'] ?? array() ),
					'disabled'    => $on( 'disable_author_archives' ),
				),
				'date'      => array(
					'title'       => $text( 'date_archive_title' ),
					'description' => $text( 'date_archive_description' ),
					'disabled'    => $on( 'disable_date_archives' ),
				),
				'search'    => array( 'title' => $text( 'search_title' ) ),
				'not_found' => array( 'title' => $text( '404_title' ) ),
			),
		);

		$schema = array( 'post_types' => array() );

		foreach ( $titles as $key => $value ) {
			$key = (string) $key;

			if ( preg_match( '/^pt_(.+)_(title|description|archive_title)$/', $key, $m ) && post_type_exists( $m[1] ) ) {
				if ( 'archive_title' === $m[2] ) {
					$meta['post_type_archives'][ $m[1] ]['title'] = VariableConverter::from_rank_math( (string) $value );
				} else {
					$meta['post_types'][ $m[1] ][ $m[2] ] = VariableConverter::from_rank_math( (string) $value );
				}
			} elseif ( preg_match( '/^pt_(.+)_robots$/', $key, $m ) && post_type_exists( $m[1] ) && $on( 'pt_' . $m[1] . '_custom_robots' ) ) {
				$meta['post_types'][ $m[1] ]['noindex'] = self::robots_noindex( $value );
			} elseif ( preg_match( '/^tax_(.+)_(title|description)$/', $key, $m ) && taxonomy_exists( $m[1] ) ) {
				$meta['taxonomies'][ $m[1] ][ $m[2] ] = VariableConverter::from_rank_math( (string) $value );
			} elseif ( preg_match( '/^tax_(.+)_robots$/', $key, $m ) && taxonomy_exists( $m[1] ) && $on( 'tax_' . $m[1] . '_custom_robots' ) ) {
				$meta['taxonomies'][ $m[1] ]['noindex'] = self::robots_noindex( $value );
			} elseif ( preg_match( '/^pt_(.+)_default_article_type$/', $key, $m ) && in_array( $value, array( 'Article', 'BlogPosting', 'NewsArticle' ), true ) ) {
				$schema['post_types'][ $m[1] ]['article_type'] = (string) $value;
			}
		}

		$lines = preg_split( '/\R/', (string) ( $titles['social_additional_profiles'] ?? '' ) );
		$other = array_values( array_filter( array_map( 'trim', is_array( $lines ) ? $lines : array() ) ) );

		$settings = array(
			'general'     => array( 'separator' => (string) ( $titles['title_separator'] ?? '-' ) ),
			'meta'        => YoastImporter::drop_empty_strings( $meta ),
			'schema'      => array_merge(
				$schema,
				array(
					'site_type'      => 'person' === ( $titles['knowledgegraph_type'] ?? '' ) ? 'person' : 'organization',
					'name'           => (string) ( $titles['knowledgegraph_name'] ?? '' ),
					'alternate_name' => (string) ( $titles['website_alternate_name'] ?? '' ),
					'logo'           => (int) ( $titles['knowledgegraph_logo_id'] ?? 0 ),
				)
			),
			'social'      => array(
				'default_image' => (int) ( $titles['open_graph_image_id'] ?? 0 ),
				'profiles'      => array(
					'facebook' => (string) ( $titles['social_url_facebook'] ?? '' ),
					'x'        => (string) ( $titles['twitter_author_names'] ?? '' ),
					'other'    => $other,
				),
			),
			'breadcrumbs' => array(),
		);

		if ( is_array( $general ) ) {
			$settings['breadcrumbs'] = array_filter(
				array(
					'separator'  => html_entity_decode( (string) ( $general['breadcrumbs_separator'] ?? '' ), ENT_QUOTES, 'UTF-8' ),
					'home_label' => (string) ( $general['breadcrumbs_home_label'] ?? '' ),
					'prefix'     => (string) ( $general['breadcrumbs_prefix'] ?? '' ),
				)
			);
		}

		return $settings;
	}

	/**
	 * Maps post or term meta.
	 *
	 * @param array<string, string> $meta Source meta.
	 * @return array<string, mixed>
	 */
	private function map_object( array $meta ): array {
		$get    = static fn( string $key ): string => (string) ( $meta[ self::PREFIX . $key ] ?? '' );
		$fields = array(
			'title'            => VariableConverter::from_rank_math( $get( 'title' ) ),
			'description'      => VariableConverter::from_rank_math( $get( 'description' ) ),
			'focus_keywords'   => $get( 'focus_keyword' ),
			'canonical'        => $get( 'canonical_url' ),
			'breadcrumb_title' => $get( 'breadcrumb_title' ),
			'og_title'         => VariableConverter::from_rank_math( $get( 'facebook_title' ) ),
			'og_description'   => VariableConverter::from_rank_math( $get( 'facebook_description' ) ),
			'og_image'         => self::image_id( $get( 'facebook_image_id' ), $get( 'facebook_image' ) ),
		);

		if ( 'on' !== $get( 'twitter_use_facebook' ) ) {
			$fields['twitter_title']       = VariableConverter::from_rank_math( $get( 'twitter_title' ) );
			$fields['twitter_description'] = VariableConverter::from_rank_math( $get( 'twitter_description' ) );
			$fields['twitter_image']       = self::image_id( $get( 'twitter_image_id' ), $get( 'twitter_image' ) );
		}

		$robots = maybe_unserialize( $get( 'robots' ) );

		if ( is_array( $robots ) && array() !== $robots ) {
			$robots = array_map( 'strval', $robots );

			if ( in_array( 'noindex', $robots, true ) ) {
				$fields['noindex'] = 'noindex';
			} elseif ( in_array( 'index', $robots, true ) ) {
				$fields['noindex'] = 'index';
			}

			$fields['nofollow']        = in_array( 'nofollow', $robots, true );
			$fields['robots_advanced'] = array_values( array_intersect( array( 'noarchive', 'nosnippet', 'noimageindex' ), $robots ) );
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
	 * Whether a Rank Math robots array contains noindex.
	 *
	 * @param mixed $robots Robots value.
	 */
	private static function robots_noindex( $robots ): bool {
		return is_array( $robots ) && in_array( 'noindex', array_map( 'strval', $robots ), true );
	}
}
