<?php
/**
 * Easy Digital Downloads module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Edd;

use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Presentation\Presentation;
use WPInterface\SEO\Support\Images;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Product schema for downloads (replacing EDD's own markup) and noindex
 * for checkout, receipt and purchase history pages.
 */
final class EddModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'product_schema' => true,
			'noindex_pages'  => true,
		);
	}

	/**
	 * Sanitizes the section.
	 *
	 * @param array<string, mixed> $values   Values.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $values, array $defaults ): array {
		return array(
			'product_schema' => Sanitize::to_bool( $values['product_schema'] ?? $defaults['product_schema'] ),
			'noindex_pages'  => Sanitize::to_bool( $values['noindex_pages'] ?? $defaults['noindex_pages'] ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		if ( ! $this->plugin->output_enabled() ) {
			return;
		}

		if ( $this->plugin->options()->get( 'edd.product_schema', true ) ) {
			add_filter( 'edd_generate_download_structured_data', '__return_empty_array' );
			add_filter( 'wpiseo_schema_graph', array( $this, 'product_schema' ), 10, 2 );
		}

		if ( $this->plugin->options()->get( 'edd.noindex_pages', true ) ) {
			add_filter( 'wpiseo_robots', array( $this, 'robots' ), 10, 2 );
		}
	}

	/**
	 * Adds the Product node on download pages.
	 *
	 * @param array<int, array<string, mixed>> $graph        Nodes.
	 * @param Presentation                     $presentation Page values.
	 * @return array<int, array<string, mixed>>
	 */
	public function product_schema( $graph, $presentation ): array {
		$graph = is_array( $graph ) ? $graph : array();
		$post  = $presentation instanceof Presentation ? $presentation->context()->post() : null;

		if ( ! $post || 'download' !== $post->post_type || ! $presentation->context()->is( Context::SINGULAR ) || ! function_exists( 'edd_get_download_price' ) ) {
			return $graph;
		}

		$url      = $presentation->url();
		$currency = function_exists( 'edd_get_currency' ) ? (string) edd_get_currency() : 'USD';
		$image    = Images::attachment( (int) get_post_thumbnail_id( $post ) );
		$node     = array(
			'@type'            => 'Product',
			'@id'              => $url . '#product',
			'name'             => wp_strip_all_tags( get_the_title( $post ) ),
			'url'              => $url,
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			'description'      => wp_trim_words( wp_strip_all_tags( '' !== $post->post_excerpt ? $post->post_excerpt : strip_shortcodes( $post->post_content ) ), 60 ),
			'image'            => $image ? $image['url'] : '',
			'sku'              => function_exists( 'edd_use_skus' ) && edd_use_skus() ? (string) get_post_meta( $post->ID, 'edd_sku', true ) : '',
			'brand'            => array( '@id' => trailingslashit( home_url( '/' ) ) . '#organization' ),
		);

		if ( function_exists( 'edd_has_variable_prices' ) && edd_has_variable_prices( $post->ID ) && function_exists( 'edd_get_variable_prices' ) ) {
			$prices = array_map( static fn( $price ) => (float) ( $price['amount'] ?? 0 ), (array) edd_get_variable_prices( $post->ID ) );

			if ( $prices ) {
				$node['offers'] = array(
					'@type'         => 'AggregateOffer',
					'lowPrice'      => min( $prices ),
					'highPrice'     => max( $prices ),
					'offerCount'    => count( $prices ),
					'priceCurrency' => $currency,
					'availability'  => 'https://schema.org/InStock',
					'url'           => $url,
				);
			}
		} else {
			$node['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (float) edd_get_download_price( $post->ID ),
				'priceCurrency' => $currency,
				'availability'  => 'https://schema.org/InStock',
				'url'           => $url,
				'seller'        => array( '@id' => trailingslashit( home_url( '/' ) ) . '#organization' ),
			);
		}

		$graph[] = array_filter( $node, static fn( $value ) => '' !== $value && null !== $value );

		return $graph;
	}

	/**
	 * Noindex for checkout, receipt, failed transaction and purchase history pages.
	 *
	 * @param array<string, mixed> $robots  Directives.
	 * @param Context              $context Context.
	 * @return array<string, mixed>
	 */
	public function robots( $robots, $context ): array {
		$robots = is_array( $robots ) ? $robots : array();

		if ( ! $context instanceof Context || ! $context->is( Context::SINGULAR ) ) {
			return $robots;
		}

		$checks = array( 'edd_is_checkout', 'edd_is_success_page', 'edd_is_failed_transaction_page', 'edd_is_purchase_history_page' );

		foreach ( $checks as $check ) {
			if ( function_exists( $check ) && $check() ) {
				unset( $robots['index'], $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );
				$robots['noindex'] = true;
				break;
			}
		}

		return $robots;
	}
}
