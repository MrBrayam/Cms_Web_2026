<?php
/**
 * Slim SEO importer.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Imports Slim SEO post/term meta (`slim_seo` array) and the `slim_seo` option.
 */
final class SlimSeoImporter extends Importer {

	private const KEY = 'slim_seo';

	/**
	 * Machine name.
	 */
	public function id(): string {
		return 'slimseo';
	}

	/**
	 * Human name.
	 */
	public function label(): string {
		return 'Slim SEO';
	}

	/**
	 * Meta key (exact; LIKE 'slim_seo%' only matches this key and `slim_seo_*`).
	 */
	protected function post_meta_prefix(): string {
		return self::KEY;
	}

	/**
	 * Maps one post's meta.
	 *
	 * @param array<string, string> $meta Source meta.
	 * @return array<string, mixed>
	 */
	protected function map_post( array $meta ): array {
		$data = maybe_unserialize( (string) ( $meta[ self::KEY ] ?? '' ) );

		return is_array( $data ) ? self::map_data( $data ) : array();
	}

	/**
	 * Term data.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function term_data(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off import query.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s", self::KEY ) );
		$data = array();

		foreach ( (array) $rows as $row ) {
			$values = maybe_unserialize( (string) $row->meta_value );

			if ( is_array( $values ) ) {
				$data[ (int) $row->term_id ] = self::map_data( $values );
			}
		}

		return $data;
	}

	/**
	 * Slim SEO has no per-user profile fields.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function user_profiles(): array {
		return array();
	}

	/**
	 * Settings from the `slim_seo` option.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		$option = get_option( self::KEY, array() );

		if ( ! is_array( $option ) || array() === $option ) {
			return array();
		}

		$text = static fn( $value ): string => VariableConverter::from_slim_seo( (string) $value );
		$meta = array();

		foreach ( $option as $key => $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}

			$key   = (string) $key;
			$group = array_filter(
				array(
					'title'       => $text( $values['title'] ?? '' ),
					'description' => $text( $values['description'] ?? '' ),
				)
			);

			if ( isset( $values['noindex'] ) ) {
				$group['noindex'] = ! empty( $values['noindex'] );
			}

			if ( 'home' === $key ) {
				$meta['archives']['home'] = array_diff_key( $group, array( 'noindex' => true ) );
			} elseif ( 'author' === $key ) {
				$meta['archives']['author'] = $group;
			} elseif ( '_archive' === substr( $key, -8 ) && post_type_exists( substr( $key, 0, -8 ) ) ) {
				$meta['post_type_archives'][ substr( $key, 0, -8 ) ] = $group;
			} elseif ( post_type_exists( $key ) ) {
				$meta['post_types'][ $key ] = $group;
			} elseif ( taxonomy_exists( $key ) ) {
				$meta['taxonomies'][ $key ] = $group;
			}
		}

		$settings = array( 'meta' => $meta );

		if ( ! empty( $option['facebook_app_id'] ) || ! empty( $option['twitter_site'] ) ) {
			$settings['social'] = array(
				'facebook_app_id' => (string) ( $option['facebook_app_id'] ?? '' ),
				'profiles'        => array( 'x' => (string) ( $option['twitter_site'] ?? '' ) ),
			);
		}

		return $settings;
	}

	/**
	 * Maps a `slim_seo` array.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<string, mixed>
	 */
	private static function map_data( array $data ): array {
		$fields = array(
			'title'         => VariableConverter::from_slim_seo( (string) ( $data['title'] ?? '' ) ),
			'description'   => VariableConverter::from_slim_seo( (string) ( $data['description'] ?? '' ) ),
			'canonical'     => (string) ( $data['canonical'] ?? '' ),
			'og_image'      => self::image_id( '', (string) ( $data['facebook_image'] ?? '' ) ),
			'twitter_image' => self::image_id( '', (string) ( $data['twitter_image'] ?? '' ) ),
		);

		if ( ! empty( $data['noindex'] ) ) {
			$fields['noindex'] = 'noindex';
		}

		return $fields;
	}
}
