<?php
/**
 * Meta registration.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Meta;

use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers SEO meta for all post types, taxonomies and users so the block
 * editor can read and write it through the REST API.
 */
final class Registrar {

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'init', array( $this, 'add_custom_fields_support' ), 999 );
	}

	/**
	 * Registers every field.
	 */
	public function register_meta(): void {
		foreach ( Fields::post() as $key => $spec ) {
			register_post_meta( '', Fields::PREFIX . $key, $this->args( 'post', $spec ) );
		}

		foreach ( Fields::term() as $key => $spec ) {
			register_term_meta( '', Fields::PREFIX . $key, $this->args( 'term', $spec ) );
		}

		foreach ( Fields::user() as $key => $spec ) {
			register_meta( 'user', Fields::PREFIX . $key, $this->args( 'user', $spec ) );
		}
	}

	/**
	 * Block editor post types need `custom-fields` support for meta to be
	 * included in REST responses. The core "Custom Fields" panel stays off by
	 * default in the block editor, so this has no visible side effect.
	 */
	public function add_custom_fields_support(): void {
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			if (
				'attachment' !== $post_type
				&& post_type_supports( $post_type, 'editor' )
				&& ! post_type_supports( $post_type, 'custom-fields' )
				&& use_block_editor_for_post_type( $post_type )
			) {
				add_post_type_support( $post_type, 'custom-fields' );
			}
		}
	}

	/**
	 * Arguments for register_meta().
	 *
	 * @param string               $object_type Object type.
	 * @param array<string, mixed> $spec        Field spec.
	 * @return array<string, mixed>
	 */
	private function args( string $object_type, array $spec ): array {
		return array(
			'type'              => $spec['type'],
			'single'            => true,
			'default'           => Fields::default_value( $spec ),
			'show_in_rest'      => array( 'schema' => Fields::rest_schema( $spec ) ),
			'sanitize_callback' => static fn( $value ) => Fields::sanitize( $spec, $value ),
			'auth_callback'     => static function ( $allowed, $meta_key, $object_id ) use ( $object_type, $spec ): bool {
				$object_id = (int) $object_id;

				switch ( $object_type ) {
					case 'term':
						$can_edit = current_user_can( 'edit_term', $object_id );
						break;
					case 'user':
						$can_edit = current_user_can( 'edit_user', $object_id );
						break;
					default:
						$can_edit = current_user_can( 'edit_post', $object_id );
				}

				// Scores are written by the analysis for anyone who can edit the post.
				if ( 'edit_post' === ( $spec['auth'] ?? '' ) ) {
					return $can_edit;
				}

				return $can_edit && current_user_can( Capabilities::EDIT_SEO );
			},
		);
	}
}
