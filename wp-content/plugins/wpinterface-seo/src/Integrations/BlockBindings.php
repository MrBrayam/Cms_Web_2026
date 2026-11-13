<?php
/**
 * Block Bindings source for SEO values.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Integrations;

use WP_Block;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `wpinterface-seo/field` source so block themes can bind a
 * paragraph, heading, button or image to the resolved SEO title, meta
 * description or social image of the current post (WordPress 6.5+).
 *
 * Example: `<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"wpinterface-seo/field","args":{"key":"description"}}}}} -->`.
 */
final class BlockBindings {

	public const SOURCE = 'wpinterface-seo/field';

	/**
	 * Bindable keys.
	 */
	public const KEYS = array( 'title', 'description', 'og_title', 'og_description', 'og_image', 'canonical' );

	/**
	 * Registers the source on `init`.
	 */
	public function register(): void {
		if ( function_exists( 'register_block_bindings_source' ) ) {
			add_action( 'init', array( $this, 'register_source' ) );
		}
	}

	/**
	 * Registers the source.
	 */
	public function register_source(): void {
		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'SEO', 'wpinterface-seo' ),
				'get_value_callback' => array( $this, 'value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Resolved value for a binding.
	 *
	 * @param array<string, mixed> $source_args    Binding args: key.
	 * @param WP_Block             $block_instance Block.
	 * @param string               $attribute_name Bound attribute.
	 * @return string|null Null leaves the attribute unchanged.
	 */
	public function value( $source_args, $block_instance, $attribute_name ) {
		unset( $attribute_name );

		$key = (string) ( $source_args['key'] ?? '' );

		if ( ! in_array( $key, self::KEYS, true ) ) {
			return null;
		}

		$plugin  = Plugin::instance();
		$post_id = $block_instance instanceof WP_Block ? (int) ( $block_instance->context['postId'] ?? 0 ) : 0;

		if ( $post_id && post_password_required( $post_id ) ) {
			return null;
		}

		$presentation = $plugin->presentation( $post_id ? Context::for_post( $post_id ) : null );

		switch ( $key ) {
			case 'title':
				return $presentation->title();
			case 'description':
				return $presentation->description();
			case 'og_title':
				return $presentation->og_title();
			case 'og_description':
				return $presentation->og_description();
			case 'og_image':
				return (string) ( $presentation->og_image()['url'] ?? '' );
			default:
				return $presentation->canonical();
		}
	}
}
