<?php
/**
 * WooCommerce module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\WooCommerce;

use WC_Product;
use WP_Post;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Presentation\Presentation;
use WPInterface\SEO\Support\Images;
use WPInterface\SEO\Support\Sanitize;
use WPInterface\SEO\Templates\VariableRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Product schema (replacing WooCommerce's own markup so there is one
 * graph), store page indexing rules, product social tags and fields.
 */
final class WooCommerceModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'product_schema'   => true,
			'noindex_pages'    => true,
			'noindex_hidden'   => true,
			'remove_generator' => true,
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
		$clean = array();

		foreach ( $defaults as $key => $default ) {
			$clean[ $key ] = Sanitize::to_bool( $values[ $key ] ?? $default );
		}

		return $clean;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		$options = $this->plugin->options();

		add_action( 'wpiseo_register_variables', array( $this, 'register_variables' ) );
		add_action( 'woocommerce_product_options_sku', array( $this, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );

		if ( ! $this->plugin->output_enabled() ) {
			return;
		}

		if ( $options->get( 'woocommerce.product_schema', true ) ) {
			add_filter( 'woocommerce_structured_data_product', '__return_empty_array' );
			add_filter( 'woocommerce_structured_data_website', '__return_empty_array' );
			add_filter( 'wpiseo_schema_graph', array( $this, 'product_schema' ), 10, 2 );
			add_filter( 'wpiseo_schema_webpage', array( $this, 'product_page_type' ), 10, 2 );
		}

		if ( $options->get( 'schema.breadcrumbs', true ) ) {
			add_filter( 'woocommerce_structured_data_breadcrumblist', '__return_empty_array' );
		}

		add_filter( 'wpiseo_robots', array( $this, 'robots' ), 10, 2 );
		add_filter( 'wpiseo_sitemap_post_entry', array( $this, 'sitemap_entry' ), 10, 2 );
		add_filter( 'wpiseo_social_tags', array( $this, 'social_tags' ), 10, 2 );
		add_filter( 'wpiseo_title', array( $this, 'shop_page_value' ), 5, 2 );
		add_filter( 'wpiseo_description', array( $this, 'shop_page_value' ), 5, 2 );
		add_filter( 'wpiseo_breadcrumb_items', array( $this, 'breadcrumb_shop_label' ), 10, 2 );

		if ( $options->get( 'woocommerce.remove_generator', true ) ) {
			remove_filter( 'get_the_generator_html', 'wc_generator_tag', 10 );
			remove_filter( 'get_the_generator_xhtml', 'wc_generator_tag', 10 );
		}
	}

	/**
	 * Adds the Product node on single product pages.
	 *
	 * @param array<int, array<string, mixed>> $graph        Nodes.
	 * @param Presentation                     $presentation Page values.
	 * @return array<int, array<string, mixed>>
	 */
	public function product_schema( $graph, $presentation ): array {
		$graph   = is_array( $graph ) ? $graph : array();
		$post    = $presentation instanceof Presentation ? $presentation->context()->post() : null;
		$product = $post && 'product' === $post->post_type ? wc_get_product( $post ) : null;

		if ( ! $product instanceof WC_Product || ! $presentation->context()->is( Context::SINGULAR ) ) {
			return $graph;
		}

		$url  = $presentation->url();
		$node = array(
			'@type'            => 'Product',
			'@id'              => $url . '#product',
			'name'             => wp_strip_all_tags( $product->get_name() ),
			'url'              => $url,
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			'description'      => mb_substr( wp_strip_all_tags( do_shortcode( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ) ), 0, 5000 ),
			'sku'              => $product->get_sku(),
			'image'            => $this->images( $product ),
			'brand'            => $this->brand( $product ),
			'mpn'              => (string) get_post_meta( $product->get_id(), '_wpiseo_mpn', true ),
			'offers'           => $this->offers( $product, $url ),
		);

		// get_global_unique_id() exists since WooCommerce 9.2.
		if ( is_callable( array( $product, 'get_global_unique_id' ) ) && '' !== (string) $product->get_global_unique_id() ) {
			$node['gtin'] = (string) $product->get_global_unique_id();
		}

		if ( wc_review_ratings_enabled() && $product->get_review_count() > 0 ) {
			$node['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (float) $product->get_average_rating(),
				'reviewCount' => (int) $product->get_review_count(),
				'bestRating'  => 5,
			);
			$node['review']          = $this->reviews( $product );
		}

		$graph[] = array_filter( $node, static fn( $value ) => '' !== $value && null !== $value && array() !== $value );

		return $graph;
	}

	/**
	 * Product pages are ItemPages.
	 *
	 * @param array<string, mixed>|null $node         WebPage node.
	 * @param Presentation              $presentation Page values.
	 * @return array<string, mixed>|null
	 */
	public function product_page_type( $node, $presentation ) {
		$post = $presentation instanceof Presentation ? $presentation->context()->post() : null;

		if ( is_array( $node ) && $post && 'product' === $post->post_type && 'WebPage' === ( $node['@type'] ?? '' ) ) {
			$node['@type'] = 'ItemPage';
		}

		return $node;
	}

	/**
	 * Noindex for cart, checkout, account and hidden products.
	 *
	 * @param array<string, mixed> $robots  Directives.
	 * @param Context              $context Context.
	 * @return array<string, mixed>
	 */
	public function robots( $robots, $context ): array {
		$robots = is_array( $robots ) ? $robots : array();
		$post   = $context instanceof Context ? $context->post() : null;

		if ( ! $post ) {
			return $robots;
		}

		$noindex = false;

		if ( $this->plugin->options()->get( 'woocommerce.noindex_pages', true ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
				if ( (int) wc_get_page_id( $page ) === (int) $post->ID ) {
					$noindex = true;
				}
			}
		}

		if ( 'product' === $post->post_type && $this->plugin->options()->get( 'woocommerce.noindex_hidden', true ) && $this->is_hidden( $post ) ) {
			$noindex = true;
		}

		if ( $noindex ) {
			unset( $robots['index'], $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );
			$robots['noindex'] = true;
		}

		return $robots;
	}

	/**
	 * Leaves hidden products and store pages out of the sitemap.
	 *
	 * @param array<string, mixed>|null $entry Entry.
	 * @param WP_Post                   $post  Post.
	 * @return array<string, mixed>|null
	 */
	public function sitemap_entry( $entry, $post ) {
		if ( ! is_array( $entry ) || ! $post instanceof WP_Post ) {
			return $entry;
		}

		if ( 'product' === $post->post_type && $this->is_hidden( $post ) ) {
			return null;
		}

		if ( 'page' === $post->post_type && in_array( (int) $post->ID, array( (int) wc_get_page_id( 'cart' ), (int) wc_get_page_id( 'checkout' ), (int) wc_get_page_id( 'myaccount' ) ), true ) ) {
			return null;
		}

		return $entry;
	}

	/**
	 * Product price and availability tags for social networks.
	 *
	 * @param list<array{0: string, 1: string, 2: string}> $tags         Tags.
	 * @param Presentation                                 $presentation Page values.
	 * @return list<array{0: string, 1: string, 2: string}>
	 */
	public function social_tags( $tags, $presentation ): array {
		$tags    = is_array( $tags ) ? $tags : array();
		$post    = $presentation instanceof Presentation ? $presentation->context()->post() : null;
		$product = $post && 'product' === $post->post_type ? wc_get_product( $post ) : null;

		if ( ! $product instanceof WC_Product ) {
			return $tags;
		}

		foreach ( $tags as $index => $tag ) {
			if ( 'og:type' === $tag[1] ) {
				$tags[ $index ][2] = 'product';
			}
		}

		$tags[] = array( 'property', 'product:price:amount', wc_format_decimal( (string) $product->get_price(), wc_get_price_decimals() ) );
		$tags[] = array( 'property', 'product:price:currency', get_woocommerce_currency() );
		$tags[] = array( 'property', 'product:availability', $product->is_in_stock() ? 'instock' : 'out of stock' );

		return $tags;
	}

	/**
	 * Uses the shop page's own SEO title/description on the product archive.
	 *
	 * @param string  $value   Value.
	 * @param Context $context Context.
	 */
	public function shop_page_value( $value, $context ): string {
		$value = (string) $value;

		if ( ! $context instanceof Context || ! $context->is( Context::POST_TYPE_ARCHIVE ) || 'product' !== $context->subtype() ) {
			return $value;
		}

		$shop_id = (int) wc_get_page_id( 'shop' );
		$key     = 'wpiseo_title' === current_filter() ? 'title' : 'description';
		$custom  = $shop_id > 0 ? trim( (string) Meta::post( $shop_id, $key ) ) : '';

		return '' !== $custom ? wpiseo_parse_template( $custom, $context ) : $value;
	}

	/**
	 * Uses the shop page title for the product archive crumb.
	 *
	 * @param list<array{label: string, url: string}> $items   Items.
	 * @param Context                                 $context Context.
	 * @return list<array{label: string, url: string}>
	 */
	public function breadcrumb_shop_label( $items, $context ): array {
		$items   = is_array( $items ) ? $items : array();
		$shop_id = (int) wc_get_page_id( 'shop' );
		$archive = get_post_type_archive_link( 'product' );

		if ( $shop_id <= 0 || ! is_string( $archive ) ) {
			return $items;
		}

		foreach ( $items as $index => $item ) {
			if ( untrailingslashit( $item['url'] ) === untrailingslashit( $archive ) ) {
				$items[ $index ]['label'] = wp_strip_all_tags( get_the_title( $shop_id ) );
			}
		}

		unset( $context );

		return $items;
	}

	/**
	 * Product template variables.
	 *
	 * @param VariableRegistry $registry Registry.
	 */
	public function register_variables( $registry ): void {
		if ( ! $registry instanceof VariableRegistry ) {
			return;
		}

		$product = static function ( Context $context ): ?WC_Product {
			$post    = $context->post();
			$product = $post && 'product' === $post->post_type ? wc_get_product( $post ) : null;

			return $product instanceof WC_Product ? $product : null;
		};

		$registry->register(
			'wc_price',
			array(
				'label'       => __( 'Product price', 'wpinterface-seo' ),
				'description' => __( 'Current price with currency.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => $product( $c ) ? html_entity_decode( wp_strip_all_tags( wc_price( (float) wc_get_price_to_display( $product( $c ) ) ) ) ) : '',
			)
		);
		$registry->register(
			'wc_sku',
			array(
				'label'       => __( 'Product SKU', 'wpinterface-seo' ),
				'description' => __( 'Stock keeping unit.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => $product( $c ) ? $product( $c )->get_sku() : '',
			)
		);
		$registry->register(
			'wc_brand',
			array(
				'label'       => __( 'Product brand', 'wpinterface-seo' ),
				'description' => __( 'First brand of the product.', 'wpinterface-seo' ),
				'callback'    => fn( Context $c ) => $product( $c ) ? (string) ( $this->brand( $product( $c ) )['name'] ?? '' ) : '',
			)
		);
		$registry->register(
			'wc_short_desc',
			array(
				'label'       => __( 'Product short description', 'wpinterface-seo' ),
				'description' => __( 'The product short description.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => $product( $c ) ? wp_trim_words( wp_strip_all_tags( $product( $c )->get_short_description() ), 40 ) : '',
			)
		);
	}

	/**
	 * MPN field (GTIN and brand use WooCommerce's own fields).
	 */
	public function product_fields(): void {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
			return;
		}

		woocommerce_wp_text_input(
			array(
				'id'          => '_wpiseo_mpn',
				'label'       => __( 'MPN', 'wpinterface-seo' ),
				'desc_tip'    => true,
				'description' => __( 'Manufacturer part number, used in product structured data.', 'wpinterface-seo' ),
			)
		);
	}

	/**
	 * Saves the MPN field.
	 *
	 * @param WC_Product $product Product being saved (nonce checked by WooCommerce).
	 */
	public function save_product_fields( $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product form nonce before this action.
		if ( $product instanceof WC_Product && isset( $_POST['_wpiseo_mpn'] ) ) {
			$product->update_meta_data( '_wpiseo_mpn', sanitize_text_field( wp_unslash( $_POST['_wpiseo_mpn'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Offer or AggregateOffer.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $url     Product URL.
	 * @return array<string, mixed>
	 */
	private function offers( WC_Product $product, string $url ): array {
		$currency     = get_woocommerce_currency();
		$decimals     = wc_get_price_decimals();
		$availability = array(
			'instock'     => 'https://schema.org/InStock',
			'outofstock'  => 'https://schema.org/OutOfStock',
			'onbackorder' => 'https://schema.org/BackOrder',
		)[ $product->get_stock_status() ] ?? 'https://schema.org/InStock';

		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_prices' ) ) {
			$prices = $product->get_variation_prices( true );
			$values = array_map( 'floatval', (array) ( $prices['price'] ?? array() ) );

			if ( $values ) {
				return array(
					'@type'         => 'AggregateOffer',
					'lowPrice'      => wc_format_decimal( (string) min( $values ), $decimals ),
					'highPrice'     => wc_format_decimal( (string) max( $values ), $decimals ),
					'offerCount'    => count( $values ),
					'priceCurrency' => $currency,
					'availability'  => $availability,
					'url'           => $url,
					'seller'        => array( '@id' => trailingslashit( home_url( '/' ) ) . '#organization' ),
				);
			}
		}

		if ( '' === (string) $product->get_price() ) {
			return array();
		}

		$sale_end = $product->get_date_on_sale_to();

		return array(
			'@type'           => 'Offer',
			'price'           => wc_format_decimal( (string) wc_get_price_to_display( $product ), $decimals ),
			'priceCurrency'   => $currency,
			'priceValidUntil' => $sale_end ? $sale_end->date( 'Y-m-d' ) : gmdate( 'Y-12-31', time() + YEAR_IN_SECONDS ),
			'availability'    => $availability,
			'itemCondition'   => 'https://schema.org/NewCondition',
			'url'             => $url,
			'seller'          => array( '@id' => trailingslashit( home_url( '/' ) ) . '#organization' ),
		);
	}

	/**
	 * Main and gallery image URLs.
	 *
	 * @param WC_Product $product Product.
	 * @return list<string>
	 */
	private function images( WC_Product $product ): array {
		$urls = array();

		foreach ( array_merge( array( (int) $product->get_image_id() ), array_slice( array_map( 'intval', $product->get_gallery_image_ids() ), 0, 5 ) ) as $id ) {
			$image = $id ? Images::attachment( $id ) : null;

			if ( $image ) {
				$urls[] = (string) $image['url'];
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Brand from WooCommerce Brands.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string, string>|null
	 */
	private function brand( WC_Product $product ): ?array {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			return null;
		}

		$terms = get_the_terms( $product->get_id(), 'product_brand' );

		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}

		return array(
			'@type' => 'Brand',
			'name'  => $terms[0]->name,
		);
	}

	/**
	 * Up to five recent reviews.
	 *
	 * @param WC_Product $product Product.
	 * @return list<array<string, mixed>>
	 */
	private function reviews( WC_Product $product ): array {
		$comments = get_comments(
			array(
				'post_id' => $product->get_id(),
				'status'  => 'approve',
				'type'    => 'review',
				'number'  => 5,
			)
		);
		$reviews  = array();

		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( (int) $comment->comment_ID, 'rating', true );

			if ( $rating < 1 ) {
				continue;
			}

			$reviews[] = array(
				'@type'         => 'Review',
				'author'        => array(
					'@type' => 'Person',
					'name'  => $comment->comment_author,
				),
				'datePublished' => mysql2date( 'c', $comment->comment_date_gmt, false ),
				'reviewBody'    => wp_strip_all_tags( $comment->comment_content ),
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => $rating,
					'bestRating'  => 5,
				),
			);
		}

		return $reviews;
	}

	/**
	 * Whether a product is hidden from both catalog and search.
	 *
	 * @param WP_Post $post Product post.
	 */
	private function is_hidden( WP_Post $post ): bool {
		$product = wc_get_product( $post );

		return $product instanceof WC_Product && 'hidden' === $product->get_catalog_visibility();
	}
}
