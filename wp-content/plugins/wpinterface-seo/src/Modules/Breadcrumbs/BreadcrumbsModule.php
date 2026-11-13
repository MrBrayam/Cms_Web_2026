<?php
/**
 * Breadcrumbs module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Breadcrumbs;

use WP_Block;
use WP_Post;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Breadcrumbs\Trail;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Support\Blocks;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumb output for any theme: `wpiseo_breadcrumbs()`, the
 * `[wpiseo_breadcrumbs]` shortcode and the "SEO Breadcrumbs" block.
 *
 * Markup is a plain `<nav><ol>` list; structured data comes from the
 * schema module (JSON-LD), not microdata.
 */
final class BreadcrumbsModule extends Module {

	private const STYLE_HANDLE = 'wpiseo-breadcrumbs';

	/**
	 * Attributes and post context of the core Breadcrumbs block being rendered.
	 *
	 * @var array<string, mixed>
	 */
	private array $core_block = array();

	/**
	 * Default settings. Empty labels are translated at render time.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'separator'       => '»',
			'home_label'      => '',
			'prefix'          => '',
			'show_home'       => true,
			'show_current'    => true,
			'show_blog'       => true,
			'search_label'    => '',
			'not_found_label' => '',
			'taxonomies'      => array(),
			'css'             => true,
			'core_block'      => true,
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
		$taxonomies = array();

		if ( is_array( $values['taxonomies'] ?? null ) ) {
			foreach ( $values['taxonomies'] as $post_type => $taxonomy ) {
				$post_type = sanitize_key( (string) $post_type );
				if ( '' !== $post_type ) {
					$taxonomies[ $post_type ] = sanitize_key( (string) $taxonomy );
				}
			}
		}

		$text = static fn( string $key ): string => mb_substr( sanitize_text_field( (string) ( $values[ $key ] ?? $defaults[ $key ] ) ), 0, 200 );

		return array(
			'separator'       => mb_substr( sanitize_text_field( (string) ( $values['separator'] ?? $defaults['separator'] ) ), 0, 10 ),
			'home_label'      => $text( 'home_label' ),
			'prefix'          => $text( 'prefix' ),
			'show_home'       => Sanitize::to_bool( $values['show_home'] ?? $defaults['show_home'] ),
			'show_current'    => Sanitize::to_bool( $values['show_current'] ?? $defaults['show_current'] ),
			'show_blog'       => Sanitize::to_bool( $values['show_blog'] ?? $defaults['show_blog'] ),
			'search_label'    => $text( 'search_label' ),
			'not_found_label' => $text( 'not_found_label' ),
			'taxonomies'      => $taxonomies,
			'css'             => Sanitize::to_bool( $values['css'] ?? $defaults['css'] ),
			'core_block'      => Sanitize::to_bool( $values['core_block'] ?? $defaults['core_block'] ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( 'wpiseo_breadcrumbs', array( $this, 'shortcode' ) );
		add_action( 'wpiseo_breadcrumbs', array( $this, 'action_output' ) );

		// WordPress 7.0+ core Breadcrumbs block: use the primary term, and optionally the whole trail.
		add_filter( 'block_core_breadcrumbs_post_type_settings', array( $this, 'core_post_type_settings' ), 10, 3 );

		if ( $this->plugin->options()->get( 'breadcrumbs.core_block', true ) ) {
			add_filter( 'render_block_context', array( $this, 'capture_core_block' ), 10, 2 );
			add_filter( 'block_core_breadcrumbs_items', array( $this, 'core_items' ) );
		}

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 30 );
		}
	}

	/**
	 * Tells the core block which taxonomy and term to use (primary category).
	 *
	 * @param array<string, string> $settings  Settings: taxonomy, term.
	 * @param string                $post_type Post type.
	 * @param int                   $post_id   Post ID.
	 * @return array<string, string>
	 */
	public function core_post_type_settings( $settings, $post_type, $post_id ): array {
		$settings = is_array( $settings ) ? $settings : array();
		$taxonomy = Trail::taxonomy_for( $this->plugin->options(), (string) $post_type );
		$post     = get_post( (int) $post_id );

		if ( '' === $taxonomy || ! $post instanceof WP_Post ) {
			return $settings;
		}

		$settings['taxonomy'] = $taxonomy;
		$term                 = Meta::primary_term( $post, $taxonomy );

		if ( $term ) {
			$settings['term'] = $term->slug;
		}

		return $settings;
	}

	/**
	 * Remembers the attributes and post of the core Breadcrumbs block about to render.
	 *
	 * @param array<string, mixed> $context      Block context.
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @return array<string, mixed>
	 */
	public function capture_core_block( $context, $parsed_block ): array {
		$context = is_array( $context ) ? $context : array();

		if ( 'core/breadcrumbs' === ( $parsed_block['blockName'] ?? '' ) ) {
			$this->core_block = array(
				'attrs'   => (array) ( $parsed_block['attrs'] ?? array() ),
				'post_id' => (int) ( $context['postId'] ?? 0 ),
			);
		}

		return $context;
	}

	/**
	 * Replaces the core block's items with this plugin's trail (same trail as
	 * the BreadcrumbList schema). Blocks inside a query loop keep core's items.
	 *
	 * @param array<int, array<string, mixed>> $items Core items.
	 * @return array<int, array<string, mixed>>
	 */
	public function core_items( $items ): array {
		$items   = is_array( $items ) ? $items : array();
		$block   = $this->core_block;
		$context = $this->plugin->context();

		$this->core_block = array();

		$block_post = (int) ( $block['post_id'] ?? 0 );
		$queried    = (int) get_queried_object_id();

		if ( is_singular() && $block_post && $queried !== $block_post ) {
			return $items;
		}

		$trail = ( new Trail( $this->plugin->options(), $context ) )->items();

		if ( array() === $trail ) {
			return $items;
		}

		$attrs = array_merge(
			array(
				'showHomeItem'    => true,
				'showCurrentItem' => true,
			),
			(array) ( $block['attrs'] ?? array() )
		);

		if ( ! $attrs['showHomeItem'] && count( $trail ) > 1 && untrailingslashit( $trail[0]['url'] ) === untrailingslashit( home_url( '/' ) ) ) {
			array_shift( $trail );
		}

		$crumbs = array_map(
			static fn( array $item ): array => array(
				'label' => $item['label'],
				'url'   => $item['url'],
			),
			$trail
		);

		$last = count( $crumbs ) - 1;

		if ( ! $attrs['showCurrentItem'] ) {
			array_pop( $crumbs );
		} elseif ( $last >= 0 ) {
			// The current page is plain text (core marks it aria-current).
			unset( $crumbs[ $last ]['url'] );
		}

		return array_values( $crumbs );
	}

	/**
	 * Registers the block and its style.
	 */
	public function register_block(): void {
		wp_register_style( self::STYLE_HANDLE, false, array(), WPISEO_VERSION );
		wp_add_inline_style( self::STYLE_HANDLE, self::css() );

		Blocks::register_script();

		register_block_type(
			WPISEO_PATH . 'blocks/breadcrumbs',
			array( 'render_callback' => array( $this, 'render_block' ) )
		);
	}

	/**
	 * Breadcrumb HTML for a context.
	 *
	 * @param Context|null         $context Context; defaults to the current request.
	 * @param array<string, mixed> $args    Overrides: class, separator, prefix, show_current, wrapper_attributes.
	 */
	public function html( ?Context $context = null, array $args = array() ): string {
		$context = $context ?? $this->plugin->context();
		$options = $this->plugin->options();
		$items   = ( new Trail( $options, $context ) )->items();

		if ( count( $items ) < 1 || ( $context->is( Context::FRONT_PAGE ) && empty( $args['show_on_front'] ) ) ) {
			return '';
		}

		$separator = (string) ( $args['separator'] ?? $options->get( 'breadcrumbs.separator', '»' ) );
		/** This filter is documented in src/Templates/Parser.php */
		$prefix       = (string) ( $args['prefix'] ?? apply_filters( 'wpiseo_translate_string', (string) $options->get( 'breadcrumbs.prefix', '' ), 'breadcrumbs' ) );
		$show_current = (bool) ( $args['show_current'] ?? $options->get( 'breadcrumbs.show_current', true ) );

		if ( ! $show_current && count( $items ) > 1 ) {
			array_pop( $items );
			$show_current = false;
		}

		$count = count( $items );
		$parts = array();

		foreach ( $items as $index => $item ) {
			$is_last = $index === $count - 1;
			$current = $is_last && $show_current;

			$label = $current || '' === $item['url']
				? sprintf( '<span class="wpiseo-breadcrumbs__label"%s>%s</span>', $current ? ' aria-current="page"' : '', esc_html( $item['label'] ) )
				: sprintf( '<a class="wpiseo-breadcrumbs__link" href="%s">%s</a>', esc_url( $item['url'] ), esc_html( $item['label'] ) );

			$sep = ! $is_last && '' !== $separator
				? sprintf( ' <span class="wpiseo-breadcrumbs__separator" aria-hidden="true">%s</span> ', esc_html( $separator ) )
				: '';

			$parts[] = sprintf( '<li class="wpiseo-breadcrumbs__item%s">%s%s</li>', $current ? ' wpiseo-breadcrumbs__item--current' : '', $label, $sep );
		}

		if ( $options->get( 'breadcrumbs.css', true ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}

		$attributes = (string) ( $args['wrapper_attributes'] ?? sprintf( 'class="%s"', esc_attr( trim( 'wpiseo-breadcrumbs ' . ( $args['class'] ?? '' ) ) ) ) );

		$html = sprintf(
			'<nav %1$s aria-label="%2$s">%3$s<ol class="wpiseo-breadcrumbs__list">%4$s</ol></nav>',
			$attributes,
			esc_attr__( 'Breadcrumb', 'wpinterface-seo' ),
			'' !== $prefix ? '<span class="wpiseo-breadcrumbs__prefix">' . esc_html( $prefix ) . '</span> ' : '',
			implode( '', $parts )
		);

		/**
		 * Filters the breadcrumb HTML. The result is passed through
		 * wp_kses() with allowed_html().
		 *
		 * @param string  $html    HTML.
		 * @param array   $items   Items.
		 * @param Context $context Context.
		 */
		$html = (string) apply_filters( 'wpiseo_breadcrumbs_html', $html, $items, $context );

		return wp_kses( $html, self::allowed_html() );
	}

	/**
	 * Tags and attributes breadcrumb markup may contain (the block wrapper
	 * adds classes, an ID, inline styles and data attributes).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		$common = array(
			'class' => true,
			'id'    => true,
		);

		return array(
			'nav'  => $common + array(
				'style'      => true,
				'aria-label' => true,
				'data-*'     => true,
			),
			'ol'   => $common,
			'li'   => $common,
			'a'    => $common + array(
				'href' => true,
				'rel'  => true,
			),
			'span' => $common + array(
				'aria-current' => true,
				'aria-hidden'  => true,
			),
		);
	}

	/**
	 * `[wpiseo_breadcrumbs]` shortcode.
	 *
	 * @param array<string, string>|string $atts Attributes: class, separator.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'class'     => '',
				'separator' => null,
			),
			is_array( $atts ) ? $atts : array(),
			'wpiseo_breadcrumbs'
		);

		$args = array( 'class' => sanitize_html_class( (string) $atts['class'] ) );

		if ( null !== $atts['separator'] ) {
			$args['separator'] = (string) $atts['separator'];
		}

		return $this->html( null, $args );
	}

	/**
	 * `do_action( 'wpiseo_breadcrumbs' )` for themes.
	 *
	 * @param array<string, mixed> $args Arguments for html().
	 */
	public function action_output( $args = array() ): void {
		echo wp_kses( $this->html( null, is_array( $args ) ? $args : array() ), self::allowed_html() );
	}

	/**
	 * Block render callback.
	 *
	 * In the editor (REST block renderer) the trail is built for the post in
	 * the block context, since there is no front-end query.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner content.
	 * @param WP_Block|null        $block      Block instance.
	 */
	public function render_block( $attributes, $content = '', $block = null ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$context    = null;

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$post_id = $block instanceof WP_Block ? (int) ( $block->context['postId'] ?? 0 ) : 0;

			// The block renderer endpoint passes `post_id` and sets up global post data.
			if ( ! $post_id ) {
				$post_id = (int) get_the_ID();
			}

			$context = $post_id ? Context::for_post( $post_id ) : Context::for_front_page_posts();
		}

		$args = array(
			'wrapper_attributes' => get_block_wrapper_attributes( array( 'class' => 'wpiseo-breadcrumbs' ) ),
			'show_on_front'      => (bool) ( $attributes['showOnFront'] ?? false ),
		);

		if ( isset( $attributes['separator'] ) && '' !== $attributes['separator'] ) {
			$args['separator'] = (string) $attributes['separator'];
		}

		return $this->html( $context, $args );
	}

	/**
	 * Adds the Breadcrumbs settings page.
	 *
	 * @param array<string, array<string, string>> $pages Pages.
	 * @return array<string, array<string, string>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['breadcrumbs'] = array(
			'title'      => __( 'Breadcrumbs', 'wpinterface-seo' ),
			'menu_title' => __( 'Breadcrumbs', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}

	/**
	 * Minimal layout CSS (no colours or fonts; inherits the theme).
	 */
	private static function css(): string {
		return '.wpiseo-breadcrumbs__list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;align-items:baseline}'
			. '.wpiseo-breadcrumbs__item{margin:0;padding:0}'
			. '.wpiseo-breadcrumbs__separator{margin:0 .4em;opacity:.6}'
			. '.wpiseo-breadcrumbs__prefix{margin-right:.4em}';
	}
}
