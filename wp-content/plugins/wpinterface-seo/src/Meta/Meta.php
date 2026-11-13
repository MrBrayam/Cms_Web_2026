<?php
/**
 * SEO meta accessors.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Meta;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Typed reads and writes of `_wpiseo_*` post, term and user meta.
 */
final class Meta {

	/**
	 * Reads a post field (normalised to its spec type).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key without prefix.
	 * @return mixed
	 */
	public static function post( int $post_id, string $key ) {
		return self::read( 'post', $post_id, $key );
	}

	/**
	 * Reads a term field.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Field key without prefix.
	 * @return mixed
	 */
	public static function term( int $term_id, string $key ) {
		return self::read( 'term', $term_id, $key );
	}

	/**
	 * Reads a user field.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Field key without prefix.
	 * @return mixed
	 */
	public static function user( int $user_id, string $key ) {
		return self::read( 'user', $user_id, $key );
	}

	/**
	 * Sanitizes and saves a field; deletes the row when the value is the default.
	 *
	 * @param string $object_type `post`, `term` or `user`.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Field key without prefix.
	 * @param mixed  $value       Raw value.
	 * @return bool Whether the field exists.
	 */
	public static function write( string $object_type, int $object_id, string $key, $value ): bool {
		$fields = Fields::for_type( $object_type );

		if ( ! isset( $fields[ $key ] ) ) {
			return false;
		}

		$clean = Fields::sanitize( $fields[ $key ], $value );

		if ( Fields::is_default( $fields[ $key ], $clean ) ) {
			delete_metadata( $object_type, $object_id, Fields::PREFIX . $key );
		} else {
			update_metadata( $object_type, $object_id, Fields::PREFIX . $key, $clean );
		}

		return true;
	}

	/**
	 * The primary term of a post in a taxonomy: the chosen one if still
	 * assigned, otherwise the first assigned term.
	 *
	 * @param WP_Post $post     Post.
	 * @param string  $taxonomy Taxonomy.
	 */
	public static function primary_term( WP_Post $post, string $taxonomy ): ?WP_Term {
		if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
			return null;
		}

		$terms = get_the_terms( $post, $taxonomy );

		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}

		$primary = self::post( $post->ID, 'primary_terms' );
		$chosen  = is_array( $primary ) ? (int) ( $primary[ $taxonomy ] ?? 0 ) : 0;

		if ( $chosen ) {
			foreach ( $terms as $term ) {
				if ( (int) $term->term_id === $chosen ) {
					return $term;
				}
			}
		}

		/**
		 * Filters the fallback primary term when none was chosen.
		 *
		 * @param WP_Term   $term     First assigned term.
		 * @param WP_Post   $post     Post.
		 * @param string    $taxonomy Taxonomy.
		 * @param WP_Term[] $terms    All assigned terms.
		 */
		$term = apply_filters( 'wpiseo_primary_term_fallback', $terms[0], $post, $taxonomy, $terms );

		return $term instanceof WP_Term ? $term : $terms[0];
	}

	/**
	 * Reads and normalises a field.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Field key without prefix.
	 * @return mixed
	 */
	private static function read( string $object_type, int $object_id, string $key ) {
		$fields = Fields::for_type( $object_type );

		if ( ! isset( $fields[ $key ] ) ) {
			return null;
		}

		$spec  = $fields[ $key ];
		$value = $object_id ? get_metadata( $object_type, $object_id, Fields::PREFIX . $key, true ) : '';

		if ( '' === $value || null === $value || false === $value ) {
			return Fields::default_value( $spec );
		}

		switch ( $spec['type'] ) {
			case 'boolean':
				return (bool) $value;
			case 'integer':
				return (int) $value;
			case 'array':
			case 'object':
				return is_array( $value ) ? $value : Fields::default_value( $spec );
			default:
				return is_scalar( $value ) ? (string) $value : '';
		}
	}
}
