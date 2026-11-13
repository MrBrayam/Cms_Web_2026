<?php
/**
 * Base importer.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Import;

use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Imports SEO data from another plugin's post meta, term meta, user meta
 * and settings. Posts are imported in batches; nothing is deleted from the
 * source plugin, so imports can be re-run safely.
 */
abstract class Importer {

	/**
	 * Machine name.
	 */
	abstract public function id(): string;

	/**
	 * Human name.
	 */
	abstract public function label(): string;

	/**
	 * Prefix shared by the source plugin's post meta keys (used with LIKE).
	 */
	abstract protected function post_meta_prefix(): string;

	/**
	 * Meta key prefixes that identify posts with source data.
	 *
	 * @return string[]
	 */
	protected function id_prefixes(): array {
		return array( $this->post_meta_prefix() );
	}

	/**
	 * Meta key prefixes read for those posts (may be broader than id_prefixes()).
	 *
	 * @return string[]
	 */
	protected function meta_prefixes(): array {
		return $this->id_prefixes();
	}

	/**
	 * LIKE patterns matching meta keys that start with any of the prefixes.
	 *
	 * @param string[] $prefixes Meta key prefixes.
	 * @return list<string>
	 */
	protected static function like_patterns( array $prefixes ): array {
		global $wpdb;

		return array_values( array_map( static fn( string $prefix ): string => $wpdb->esc_like( $prefix ) . '%', $prefixes ) );
	}

	/**
	 * Maps one post's source meta (key => value) to our fields (key => raw value).
	 *
	 * @param array<string, string> $meta Source meta.
	 * @return array<string, mixed>
	 */
	abstract protected function map_post( array $meta ): array;

	/**
	 * Term data: term ID => our fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract protected function term_data(): array;

	/**
	 * User social profiles: user ID => network => URL.
	 *
	 * @return array<int, array<string, string>>
	 */
	abstract protected function user_profiles(): array;

	/**
	 * Partial plugin settings converted from the source plugin.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function settings(): array;

	/**
	 * Whether the source plugin left any data behind.
	 */
	public function detect(): bool {
		return $this->count_posts() > 0 || array() !== $this->settings();
	}

	/**
	 * Number of posts with source meta.
	 */
	public function count_posts(): int {
		global $wpdb;

		$patterns = self::like_patterns( $this->id_prefixes() );

		// One "meta_key LIKE %s" per prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- One-off import query.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT post_id) FROM %i WHERE (' . implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_key LIKE %s' ) ) . ')', array_merge( array( $wpdb->postmeta ), $patterns ) ) );
	}

	/**
	 * Imports one batch of posts.
	 *
	 * @param int  $offset    Offset into the list of posts with source meta.
	 * @param int  $limit     Batch size.
	 * @param bool $overwrite Whether to replace values already set in this plugin.
	 * @return array{processed: int, updated: int, next: int|null}
	 */
	public function import_posts( int $offset, int $limit, bool $overwrite ): array {
		global $wpdb;

		$patterns = self::like_patterns( $this->id_prefixes() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- One-off import query.
		$post_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT post_id FROM %i WHERE (' . implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_key LIKE %s' ) ) . ') ORDER BY post_id ASC LIMIT %d OFFSET %d', array_merge( array( $wpdb->postmeta ), $patterns, array( $limit, $offset ) ) ) );

		$post_ids = array_map( 'intval', (array) $post_ids );

		if ( array() === $post_ids ) {
			return array(
				'processed' => 0,
				'updated'   => 0,
				'next'      => null,
			);
		}

		$patterns = self::like_patterns( $this->meta_prefixes() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- One-off import query.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, meta_key, meta_value FROM %i WHERE (' . implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_key LIKE %s' ) ) . ') AND post_id IN (' . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ')', array_merge( array( $wpdb->postmeta ), $patterns, $post_ids ) ) );

		$by_post = array();

		foreach ( (array) $rows as $row ) {
			$by_post[ (int) $row->post_id ][ (string) $row->meta_key ] = (string) $row->meta_value;
		}

		$updated = 0;

		foreach ( $by_post as $post_id => $meta ) {
			if ( ! get_post( $post_id ) ) {
				continue;
			}

			if ( $this->write( 'post', $post_id, $this->map_post( $meta ), $overwrite ) ) {
				++$updated;
			}
		}

		return array(
			'processed' => count( $post_ids ),
			'updated'   => $updated,
			'next'      => count( $post_ids ) < $limit ? null : $offset + $limit,
		);
	}

	/**
	 * Imports all term data.
	 *
	 * @param bool $overwrite Whether to replace existing values.
	 * @return array{updated: int}
	 */
	public function import_terms( bool $overwrite ): array {
		$updated = 0;

		foreach ( $this->term_data() as $term_id => $fields ) {
			if ( get_term( $term_id ) && $this->write( 'term', $term_id, $fields, $overwrite ) ) {
				++$updated;
			}
		}

		return array( 'updated' => $updated );
	}

	/**
	 * Imports user social profiles.
	 *
	 * @param bool $overwrite Whether to replace existing values.
	 * @return array{updated: int}
	 */
	public function import_users( bool $overwrite ): array {
		$updated = 0;

		foreach ( $this->user_profiles() as $user_id => $profiles ) {
			if ( ! get_userdata( $user_id ) ) {
				continue;
			}

			$current  = Meta::user( $user_id, 'social_profiles' );
			$current  = is_array( $current ) ? $current : array();
			$profiles = $overwrite ? array_merge( $current, $profiles ) : array_merge( $profiles, $current );

			if ( $profiles !== $current ) {
				Meta::write( 'user', $user_id, 'social_profiles', $profiles );
				++$updated;
			}
		}

		return array( 'updated' => $updated );
	}

	/**
	 * Writes mapped fields; returns whether anything changed.
	 *
	 * @param string               $object_type Object type.
	 * @param int                  $object_id   Object ID.
	 * @param array<string, mixed> $fields      Our field => raw value.
	 * @param bool                 $overwrite   Whether to replace existing values.
	 */
	protected function write( string $object_type, int $object_id, array $fields, bool $overwrite ): bool {
		$specs   = Fields::for_type( $object_type );
		$changed = false;

		foreach ( $fields as $key => $value ) {
			if ( ! isset( $specs[ $key ] ) ) {
				continue;
			}

			$clean   = Fields::sanitize( $specs[ $key ], $value );
			$current = 'post' === $object_type ? Meta::post( $object_id, $key ) : Meta::term( $object_id, $key );

			if ( Fields::is_default( $specs[ $key ], $clean ) || $clean === $current ) {
				continue;
			}

			if ( ! $overwrite && ! Fields::is_default( $specs[ $key ], $current ) ) {
				continue;
			}

			Meta::write( $object_type, $object_id, $key, $clean );
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Attachment ID from an ID or URL value.
	 *
	 * @param string $id  Attachment ID (may be empty).
	 * @param string $url Image URL fallback.
	 */
	protected static function image_id( string $id, string $url ): int {
		if ( is_numeric( $id ) && (int) $id > 0 ) {
			return (int) $id;
		}

		return '' !== $url ? (int) attachment_url_to_postid( $url ) : 0;
	}
}
