<?php
/**
 * All in One SEO importer.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Import;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Reads AIOSEO's own table during a one-off import.

/**
 * Imports All in One SEO 4.x data from the `{prefix}aioseo_posts` table and
 * the `aioseo_options` / `aioseo_options_dynamic` JSON options.
 */
final class AioseoImporter extends Importer {

	/**
	 * Machine name.
	 */
	public function id(): string {
		return 'aioseo';
	}

	/**
	 * Human name.
	 */
	public function label(): string {
		return 'All in One SEO';
	}

	/**
	 * Unused: AIOSEO stores post data in its own table.
	 */
	protected function post_meta_prefix(): string {
		return '_aioseo_';
	}

	/**
	 * Number of posts with AIOSEO data.
	 */
	public function count_posts(): int {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return 0;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table() ) );
	}

	/**
	 * Imports one batch of rows.
	 *
	 * @param int  $offset    Offset.
	 * @param int  $limit     Batch size.
	 * @param bool $overwrite Whether to replace existing values.
	 * @return array{processed: int, updated: int, next: int|null}
	 */
	public function import_posts( int $offset, int $limit, bool $overwrite ): array {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return array(
				'processed' => 0,
				'updated'   => 0,
				'next'      => null,
			);
		}

		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC LIMIT %d OFFSET %d', $this->table(), $limit, $offset ), ARRAY_A );
		$updated = 0;

		foreach ( (array) $rows as $row ) {
			$post_id = (int) ( $row['post_id'] ?? 0 );

			if ( $post_id && get_post( $post_id ) && $this->write( 'post', $post_id, $this->map_post( $row ), $overwrite ) ) {
				++$updated;
			}
		}

		return array(
			'processed' => count( (array) $rows ),
			'updated'   => $updated,
			'next'      => count( (array) $rows ) < $limit ? null : $offset + $limit,
		);
	}

	/**
	 * Maps one `aioseo_posts` row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	protected function map_post( array $row ): array {
		$get    = static fn( string $key ): string => (string) ( $row[ $key ] ?? '' );
		$fields = array(
			'title'          => VariableConverter::from_aioseo( $get( 'title' ) ),
			'description'    => VariableConverter::from_aioseo( $get( 'description' ) ),
			'canonical'      => $get( 'canonical_url' ),
			'og_title'       => VariableConverter::from_aioseo( $get( 'og_title' ) ),
			'og_description' => VariableConverter::from_aioseo( $get( 'og_description' ) ),
		);

		if ( 'custom_image' === $get( 'og_image_type' ) ) {
			$fields['og_image'] = self::image_id( '', $get( 'og_image_custom_url' ) );
		}

		if ( '0' === $get( 'twitter_use_og' ) ) {
			$fields['twitter_title']       = VariableConverter::from_aioseo( $get( 'twitter_title' ) );
			$fields['twitter_description'] = VariableConverter::from_aioseo( $get( 'twitter_description' ) );

			if ( 'custom_image' === $get( 'twitter_image_type' ) ) {
				$fields['twitter_image'] = self::image_id( '', $get( 'twitter_image_custom_url' ) );
			}
		}

		// Per-post robots only apply when robots_default is off.
		if ( '0' === $get( 'robots_default' ) ) {
			if ( '1' === $get( 'robots_noindex' ) ) {
				$fields['noindex'] = 'noindex';
			}

			$fields['nofollow'] = '1' === $get( 'robots_nofollow' );
			$advanced           = array();

			foreach ( array( 'noarchive', 'nosnippet', 'noimageindex' ) as $directive ) {
				if ( '1' === $get( 'robots_' . $directive ) ) {
					$advanced[] = $directive;
				}
			}

			if ( $advanced ) {
				$fields['robots_advanced'] = $advanced;
			}
		}

		$keyphrases = json_decode( $get( 'keyphrases' ), true );
		if ( is_array( $keyphrases ) ) {
			$keywords = array( (string) ( $keyphrases['focus']['keyphrase'] ?? '' ) );

			foreach ( (array) ( $keyphrases['additional'] ?? array() ) as $additional ) {
				$keywords[] = (string) ( $additional['keyphrase'] ?? '' );
			}

			$fields['focus_keywords'] = array_values( array_filter( $keywords ) );
		}

		$primary = json_decode( $get( 'primary_term' ), true );
		if ( is_array( $primary ) ) {
			$fields['primary_terms'] = array_filter( array_map( 'intval', $primary ) );
		}

		return $fields;
	}

	/**
	 * Term data lives in the Pro-only `aioseo_terms` table; not imported.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function term_data(): array {
		return array();
	}

	/**
	 * AIOSEO profile URLs are stored in its own options per user; not imported.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function user_profiles(): array {
		return array();
	}

	/**
	 * Settings from the JSON options.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		$options = json_decode( (string) get_option( 'aioseo_options', '' ), true );
		$dynamic = json_decode( (string) get_option( 'aioseo_options_dynamic', '' ), true );

		if ( ! is_array( $options ) ) {
			return array();
		}

		$appearance = (array) ( $options['searchAppearance'] ?? array() );
		$text       = static fn( $value ): string => VariableConverter::from_aioseo( (string) $value );
		$meta       = array(
			'archives' => array(
				'home'   => array(
					'title'       => $text( $appearance['global']['siteTitle'] ?? '' ),
					'description' => $text( $appearance['global']['metaDescription'] ?? '' ),
				),
				'author' => array(
					'title'       => $text( $appearance['archives']['author']['title'] ?? '' ),
					'description' => $text( $appearance['archives']['author']['metaDescription'] ?? '' ),
				),
				'date'   => array(
					'title'       => $text( $appearance['archives']['date']['title'] ?? '' ),
					'description' => $text( $appearance['archives']['date']['metaDescription'] ?? '' ),
				),
				'search' => array( 'title' => $text( $appearance['archives']['search']['title'] ?? '' ) ),
			),
		);

		if ( is_array( $dynamic ) ) {
			$groups = array(
				'postTypes'  => 'post_types',
				'taxonomies' => 'taxonomies',
				'archives'   => 'post_type_archives',
			);

			foreach ( $groups as $source => $group ) {
				foreach ( (array) ( $dynamic['searchAppearance'][ $source ] ?? array() ) as $name => $values ) {
					if ( ! is_array( $values ) ) {
						continue;
					}

					$meta[ $group ][ (string) $name ] = array(
						'title'       => $text( $values['title'] ?? '' ),
						'description' => $text( $values['metaDescription'] ?? '' ),
					);

					$robots = (array) ( $values['advanced']['robotsMeta'] ?? array() );
					if ( isset( $robots['default'] ) && ! $robots['default'] ) {
						$meta[ $group ][ (string) $name ]['noindex'] = ! empty( $robots['noindex'] );
					}
				}
			}
		}

		$separator = html_entity_decode( (string) ( $appearance['global']['separator'] ?? '-' ), ENT_QUOTES, 'UTF-8' );

		return array(
			'general' => array( 'separator' => '' !== trim( $separator ) ? $separator : '-' ),
			'meta'    => YoastImporter::drop_empty_strings( $meta ),
		);
	}

	/**
	 * Table name.
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'aioseo_posts';
	}

	/**
	 * Whether AIOSEO's table exists.
	 */
	private function table_exists(): bool {
		global $wpdb;

		return $this->table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table() ) ) );
	}
}
