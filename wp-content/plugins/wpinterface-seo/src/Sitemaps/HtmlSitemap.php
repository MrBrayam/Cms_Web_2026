<?php
/**
 * HTML sitemap (block and shortcode).
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Sitemaps;

use WP_Post;
use WP_Query;
use WP_Term;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Modules\Sitemaps\SitemapsModule;
use WPInterface\SEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the site's indexable content for visitors, following the same rules
 * as the XML sitemap (noindex and excluded items are left out).
 */
final class HtmlSitemap {

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Default arguments.
	 *
	 * @return array{post_types: list<string>, taxonomies: bool, dates: bool, limit: int, orderby: string}
	 */
	public static function defaults(): array {
		return array(
			'post_types' => array(),
			'taxonomies' => false,
			'dates'      => false,
			'limit'      => 100,
			'orderby'    => 'title',
		);
	}

	/**
	 * Renders the sitemap.
	 *
	 * @param array<string, mixed> $args post_types (empty = all in the XML sitemap), taxonomies, dates, limit, orderby (title|date).
	 */
	public function render( array $args ): string {
		$args       = array_merge( self::defaults(), $args );
		$post_types = array_values( array_filter( array_map( 'sanitize_key', (array) $args['post_types'] ) ) );

		if ( array() === $post_types ) {
			$post_types = $this->post_types();
		}

		$sections = array();

		foreach ( $post_types as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( ! $object || ! $object->public || 'attachment' === $post_type || ! SitemapsModule::includes_post_type( $this->options, $post_type ) ) {
				continue;
			}

			$posts = $this->posts( $post_type, max( 1, min( 1000, (int) $args['limit'] ) ), 'date' === $args['orderby'] ? 'date' : 'title' );

			if ( $posts ) {
				$sections[] = sprintf(
					'<section class="wpiseo-html-sitemap__section"><h2>%s</h2>%s</section>',
					esc_html( (string) $object->labels->name ),
					$this->post_list( $posts, (bool) $args['dates'], is_post_type_hierarchical( $post_type ) && 'title' === $args['orderby'] )
				);
			}
		}

		if ( ! empty( $args['taxonomies'] ) ) {
			foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
				if ( 'post_format' === $taxonomy->name || ! SitemapsModule::includes_taxonomy( $this->options, $taxonomy->name ) ) {
					continue;
				}

				$list = $this->term_list( $taxonomy->name, (int) $args['limit'] );

				if ( '' !== $list ) {
					$sections[] = sprintf(
						'<section class="wpiseo-html-sitemap__section"><h2>%s</h2>%s</section>',
						esc_html( (string) $taxonomy->labels->name ),
						$list
					);
				}
			}
		}

		return $sections ? '<div class="wpiseo-html-sitemap">' . implode( '', $sections ) . '</div>' : '';
	}

	/**
	 * Post types in the XML sitemap.
	 *
	 * @return list<string>
	 */
	private function post_types(): array {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			if ( SitemapsModule::includes_post_type( $this->options, $post_type ) ) {
				$types[] = $post_type;
			}
		}

		usort( $types, static fn( string $a, string $b ): int => ( 'page' === $b ) <=> ( 'page' === $a ) );

		return $types;
	}

	/**
	 * Indexable published posts.
	 *
	 * @param string $post_type Post type.
	 * @param int    $limit     Maximum.
	 * @param string $orderby   title|date.
	 * @return list<WP_Post>
	 */
	private function posts( string $post_type, int $limit, string $orderby ): array {
		$key        = Fields::PREFIX . 'noindex';
		$meta_query = MetaModule::post_type( $this->options, $post_type )['noindex']
			? array(
				array(
					'key'   => $key,
					'value' => 'index',
				),
			)
			: array(
				'relation' => 'OR',
				array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $key,
					'value'   => 'noindex',
					'compare' => '!=',
				),
			);

		$query = new WP_Query(
			array(
				'post_type'           => $post_type,
				'post_status'         => 'publish',
				'has_password'        => false,
				'posts_per_page'      => $limit,
				'orderby'             => 'title' === $orderby ? array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				) : 'date',
				'order'               => 'title' === $orderby ? 'ASC' : 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'meta_query'          => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Honours per-post noindex.
				'post__not_in'        => array_map( 'intval', (array) $this->options->get( 'sitemaps.exclude_posts', array() ) ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Short user-defined list.
			)
		);

		$posts = array_values( array_filter( $query->posts, static fn( $post ) => $post instanceof WP_Post ) );

		// Posts with their own canonical URL elsewhere are not listed.
		return array_values(
			array_filter(
				$posts,
				static function ( WP_Post $post ): bool {
					$canonical = (string) get_post_meta( $post->ID, Fields::PREFIX . 'canonical', true );
					return '' === $canonical || untrailingslashit( $canonical ) === untrailingslashit( (string) get_permalink( $post ) );
				}
			)
		);
	}

	/**
	 * Nested (hierarchical) or flat list of posts.
	 *
	 * @param WP_Post[] $posts  Posts.
	 * @param bool      $dates  Show publish dates.
	 * @param bool      $nested Nest children under parents.
	 */
	private function post_list( array $posts, bool $dates, bool $nested ): string {
		$children = array();
		$ids      = wp_list_pluck( $posts, 'ID' );

		foreach ( $posts as $post ) {
			$parent_id                = $nested && in_array( $post->post_parent, $ids, true ) ? $post->post_parent : 0;
			$children[ $parent_id ][] = $post;
		}

		return $this->branch( $children, 0, $dates );
	}

	/**
	 * One level of the list.
	 *
	 * @param array<int, list<WP_Post>> $children  Posts by parent.
	 * @param int                       $parent_id Parent ID.
	 * @param bool                      $dates     Show dates.
	 */
	private function branch( array $children, int $parent_id, bool $dates ): string {
		if ( empty( $children[ $parent_id ] ) ) {
			return '';
		}

		$items = '';

		foreach ( $children[ $parent_id ] as $post ) {
			$items .= sprintf(
				'<li><a href="%1$s">%2$s</a>%3$s%4$s</li>',
				esc_url( (string) get_permalink( $post ) ),
				esc_html( wp_strip_all_tags( get_the_title( $post ) ) ),
				$dates ? ' <time datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( (string) get_post_time( (string) get_option( 'date_format' ), false, $post, true ) ) . '</time>' : '',
				$this->branch( $children, $post->ID, $dates )
			);
		}

		return '<ul>' . $items . '</ul>';
	}

	/**
	 * Terms with posts.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $limit    Maximum.
	 */
	private function term_list( string $taxonomy, int $limit ): string {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => max( 1, min( 1000, $limit ) ),
				'orderby'    => 'name',
				'exclude'    => array_map( 'intval', (array) $this->options->get( 'sitemaps.exclude_terms', array() ) ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Short user-defined list.
			)
		);

		if ( ! is_array( $terms ) ) {
			return '';
		}

		$items = '';

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term || 'noindex' === get_term_meta( $term->term_id, Fields::PREFIX . 'noindex', true ) ) {
				continue;
			}

			$link = get_term_link( $term );

			if ( is_string( $link ) ) {
				$items .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( $link ), esc_html( $term->name ) );
			}
		}

		return '' === $items ? '' : '<ul>' . $items . '</ul>';
	}
}
