<?php
/**
 * The SEO Framework importer.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Imports The SEO Framework post meta (`_genesis_*`, `_open_graph_*`, …),
 * `autodescription-term-settings` and `autodescription-site-settings`.
 *
 * TSF has no title templates: custom titles are shown as written plus the
 * site name, so imported titles get "%sep% %sitename%" appended unless the
 * post opted out of the site name.
 */
final class SeoFrameworkImporter extends Importer {

	/**
	 * Separator keys and the characters they render.
	 */
	private const SEPARATORS = array(
		'hyphen' => '-',
		'pipe'   => '|',
		'ndash'  => '–',
		'mdash'  => '—',
		'bull'   => '•',
		'middot' => '·',
		'lsaquo' => '‹',
		'rsaquo' => '›',
		'frasl'  => '⁄',
		'laquo'  => '«',
		'raquo'  => '»',
		'le'     => '≤',
		'ge'     => '≥',
		'lt'     => '<',
		'gt'     => '>',
	);

	/**
	 * Machine name.
	 */
	public function id(): string {
		return 'tsf';
	}

	/**
	 * Human name.
	 */
	public function label(): string {
		return 'The SEO Framework';
	}

	/**
	 * Primary prefix (detection).
	 */
	protected function post_meta_prefix(): string {
		return '_genesis_';
	}

	/**
	 * Prefixes identifying posts with TSF data.
	 *
	 * @return string[]
	 */
	protected function id_prefixes(): array {
		return array( '_genesis_', '_open_graph_', '_twitter_title', '_twitter_description', '_social_image_' );
	}

	/**
	 * Prefixes read for those posts (`redirect` is generic, so it is only
	 * read for posts that already have TSF data).
	 *
	 * @return string[]
	 */
	protected function meta_prefixes(): array {
		return array_merge( $this->id_prefixes(), array( '_tsf_', '_primary_term_', 'redirect' ) );
	}

	/**
	 * Maps one post's meta.
	 *
	 * @param array<string, string> $meta Source meta.
	 * @return array<string, mixed>
	 */
	protected function map_post( array $meta ): array {
		$get   = static fn( string $key ): string => (string) ( $meta[ $key ] ?? '' );
		$title = $get( '_genesis_title' );

		if ( '' !== $title && '1' !== $get( '_tsf_title_no_blogname' ) ) {
			$title .= ' %sep% %sitename%';
		}

		$fields = array(
			'title'               => $title,
			'description'         => $get( '_genesis_description' ),
			'canonical'           => $get( '_genesis_canonical_uri' ),
			'og_title'            => $get( '_open_graph_title' ),
			'og_description'      => $get( '_open_graph_description' ),
			'og_image'            => self::image_id( $get( '_social_image_id' ), $get( '_social_image_url' ) ),
			'twitter_title'       => $get( '_twitter_title' ),
			'twitter_description' => $get( '_twitter_description' ),
			'nofollow'            => '1' === $get( '_genesis_nofollow' ),
		);

		// 1 forces noindex, -1 forces index, absent = default.
		if ( '1' === $get( '_genesis_noindex' ) ) {
			$fields['noindex'] = 'noindex';
		} elseif ( '-1' === $get( '_genesis_noindex' ) ) {
			$fields['noindex'] = 'index';
		}

		if ( '1' === $get( '_genesis_noarchive' ) ) {
			$fields['robots_advanced'] = array( 'noarchive' );
		}

		$primary = array();
		foreach ( $meta as $key => $value ) {
			if ( 0 === strpos( $key, '_primary_term_' ) && is_numeric( $value ) && (int) $value > 0 ) {
				$primary[ substr( $key, 14 ) ] = (int) $value;
			}
		}
		if ( $primary ) {
			$fields['primary_terms'] = $primary;
		}

		return $fields;
	}

	/**
	 * Term data from `autodescription-term-settings`.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function term_data(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off import query.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s", 'autodescription-term-settings' ) );
		$data = array();

		foreach ( (array) $rows as $row ) {
			$settings = maybe_unserialize( (string) $row->meta_value );

			if ( ! is_array( $settings ) ) {
				continue;
			}

			$get   = static fn( string $key ): string => (string) ( $settings[ $key ] ?? '' );
			$title = $get( 'doctitle' );

			if ( '' !== $title && '1' !== $get( 'title_no_blog_name' ) ) {
				$title .= ' %sep% %sitename%';
			}

			$fields = array(
				'title'               => $title,
				'description'         => $get( 'description' ),
				'canonical'           => $get( 'canonical' ),
				'og_title'            => $get( 'og_title' ),
				'og_description'      => $get( 'og_description' ),
				'og_image'            => self::image_id( $get( 'social_image_id' ), $get( 'social_image_url' ) ),
				'twitter_title'       => $get( 'tw_title' ),
				'twitter_description' => $get( 'tw_description' ),
			);

			if ( '1' === $get( 'noindex' ) ) {
				$fields['noindex'] = 'noindex';
			} elseif ( '-1' === $get( 'noindex' ) ) {
				$fields['noindex'] = 'index';
			}

			$data[ (int) $row->term_id ] = $fields;
		}

		return $data;
	}

	/**
	 * TSF stores no extra social profiles per user that map to ours.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function user_profiles(): array {
		return array();
	}

	/**
	 * Settings from `autodescription-site-settings`.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		$site = get_option( 'autodescription-site-settings', array() );

		if ( ! is_array( $site ) || array() === $site ) {
			return array();
		}

		$meta = array(
			'archives' => array(
				'home'   => array_filter(
					array(
						'title'       => (string) ( $site['homepage_title'] ?? '' ),
						'description' => (string) ( $site['homepage_description'] ?? '' ),
					)
				),
				'author' => array( 'noindex' => ! empty( $site['author_noindex'] ) ),
				'date'   => array( 'noindex' => ! empty( $site['date_noindex'] ) ),
			),
		);

		foreach ( (array) ( $site['noindex_post_types'] ?? array() ) as $post_type => $value ) {
			$meta['post_types'][ (string) $post_type ]['noindex'] = ! empty( $value );
		}

		foreach ( (array) ( $site['noindex_taxonomies'] ?? array() ) as $taxonomy => $value ) {
			$meta['taxonomies'][ (string) $taxonomy ]['noindex'] = ! empty( $value );
		}

		return array(
			'general' => array( 'separator' => self::SEPARATORS[ (string) ( $site['title_separator'] ?? 'hyphen' ) ] ?? '-' ),
			'meta'    => $meta,
		);
	}
}
