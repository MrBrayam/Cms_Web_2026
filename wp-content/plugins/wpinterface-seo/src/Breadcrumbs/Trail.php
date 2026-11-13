<?php
/**
 * Breadcrumb trail builder.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Breadcrumbs;

use WP_Post;
use WP_Term;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Options;
use WPInterface\SEO\Support\Url;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the list of crumbs for a Context. Used by the breadcrumb output
 * (block, shortcode, template tag) and by BreadcrumbList structured data.
 *
 * Items: list of { label: string, url: string }. The last item is the
 * current page.
 */
final class Trail {

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Context.
	 *
	 * @var Context
	 */
	private Context $context;

	/**
	 * Built items.
	 *
	 * @var list<array{label: string, url: string}>
	 */
	private array $items = array();

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 * @param Context $context Context.
	 */
	public function __construct( Options $options, Context $context ) {
		$this->options = $options;
		$this->context = $context;
	}

	/**
	 * Taxonomy used in the trail of a post type ('' for none).
	 *
	 * Defaults to the first public hierarchical taxonomy of the post type.
	 *
	 * @param Options $options   Settings.
	 * @param string  $post_type Post type.
	 */
	public static function taxonomy_for( Options $options, string $post_type ): string {
		$stored = $options->get( 'breadcrumbs.taxonomies', array() );

		if ( is_array( $stored ) && array_key_exists( $post_type, $stored ) ) {
			$taxonomy = (string) $stored[ $post_type ];

			return '' === $taxonomy || is_object_in_taxonomy( $post_type, $taxonomy ) ? $taxonomy : '';
		}

		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
			if ( $taxonomy->hierarchical && $taxonomy->public ) {
				return $taxonomy->name;
			}
		}

		return '';
	}

	/**
	 * A user-entered label in the current language (multilingual plugins).
	 *
	 * @param string $text Label.
	 */
	private static function translate( string $text ): string {
		/** This filter is documented in src/Templates/Parser.php */
		return '' === $text ? '' : (string) apply_filters( 'wpiseo_translate_string', $text, 'breadcrumbs' );
	}

	/**
	 * The crumbs.
	 *
	 * @return list<array{label: string, url: string}>
	 */
	public function items(): array {
		$this->items = array();
		$context     = $this->context;

		if ( $context->is( Context::OTHER ) ) {
			return array();
		}

		if ( $this->options->get( 'breadcrumbs.show_home', true ) || $context->is( Context::FRONT_PAGE ) ) {
			$home_label = trim( self::translate( (string) $this->options->get( 'breadcrumbs.home_label', '' ) ) );
			$this->add( '' !== $home_label ? $home_label : __( 'Home', 'wpinterface-seo' ), home_url( '/' ) );
		}

		switch ( $context->type() ) {
			case Context::HOME:
				if ( $context->post() ) {
					$this->add_post( $context->post() );
				}
				break;

			case Context::SINGULAR:
				$this->singular( $context->post() );
				break;

			case Context::TERM:
				$this->term( $context->term() );
				break;

			case Context::POST_TYPE_ARCHIVE:
				$object = $context->post_type_object();
				if ( $object ) {
					$link = get_post_type_archive_link( $object->name );
					$this->add( (string) $object->labels->name, is_string( $link ) ? $link : '' );
				}
				break;

			case Context::AUTHOR:
				$user = $context->user();
				if ( $user ) {
					$this->add( $user->display_name, get_author_posts_url( (int) $user->ID ) );
				}
				break;

			case Context::DATE:
				$this->date();
				break;

			case Context::SEARCH:
				$label = trim( self::translate( (string) $this->options->get( 'breadcrumbs.search_label', '' ) ) );
				/* translators: %s: search phrase. */
				$label = '' !== $label ? $label : __( 'Search results for "%s"', 'wpinterface-seo' );
				$this->add( sprintf( $label, $context->search_query() ), get_search_link( $context->search_query() ) );
				break;

			case Context::NOT_FOUND:
				$label = trim( self::translate( (string) $this->options->get( 'breadcrumbs.not_found_label', '' ) ) );
				$this->add( '' !== $label ? $label : __( 'Page not found', 'wpinterface-seo' ), '' );
				break;
		}

		if ( $context->is_paged() && ! $context->is( Context::SINGULAR, Context::NOT_FOUND ) ) {
			$last = end( $this->items );
			/* translators: %d: page number. */
			$this->add( sprintf( __( 'Page %d', 'wpinterface-seo' ), $context->page() ), $last ? Url::paged( $last['url'], $context->page() ) : '' );
		}

		/**
		 * Filters the breadcrumb items.
		 *
		 * @param list<array{label: string, url: string}> $items   Items; the last is the current page.
		 * @param Context                                 $context Context.
		 */
		$items = apply_filters( 'wpiseo_breadcrumb_items', $this->items, $context );

		return is_array( $items ) ? array_values( $items ) : array();
	}

	/**
	 * Crumbs for a single post, page or CPT entry.
	 *
	 * @param WP_Post|null $post Post.
	 */
	private function singular( ?WP_Post $post ): void {
		if ( ! $post ) {
			return;
		}

		if ( 'attachment' === $post->post_type && $post->post_parent ) {
			$parent = get_post( $post->post_parent );
			if ( $parent instanceof WP_Post ) {
				$this->singular_parents( $parent );
				$this->add_post( $parent );
			}
		} else {
			$this->singular_parents( $post );
		}

		$this->add_post( $post );
	}

	/**
	 * Crumbs before a post: blog page or post type archive, then term or ancestors.
	 *
	 * @param WP_Post $post Post.
	 */
	private function singular_parents( WP_Post $post ): void {
		if ( 'post' === $post->post_type ) {
			$this->add_blog_page();
		} else {
			$object = get_post_type_object( $post->post_type );
			$link   = $object && $object->has_archive ? get_post_type_archive_link( $post->post_type ) : false;

			if ( $object && is_string( $link ) ) {
				$this->add( (string) $object->labels->name, $link );
			}
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				$ancestor = get_post( $ancestor_id );
				if ( $ancestor instanceof WP_Post ) {
					$this->add_post( $ancestor );
				}
			}
			return;
		}

		$taxonomy = self::taxonomy_for( $this->options, $post->post_type );

		if ( '' === $taxonomy ) {
			return;
		}

		$term = Meta::primary_term( $post, $taxonomy );

		if ( $term ) {
			$this->add_term_with_ancestors( $term );
		}
	}

	/**
	 * Crumbs for a term archive.
	 *
	 * @param WP_Term|null $term Term.
	 */
	private function term( ?WP_Term $term ): void {
		if ( ! $term ) {
			return;
		}

		$taxonomy = get_taxonomy( $term->taxonomy );

		if ( $taxonomy && array( 'post' ) === array_values( (array) $taxonomy->object_type ) ) {
			$this->add_blog_page();
		}

		$this->add_term_with_ancestors( $term );
	}

	/**
	 * Crumbs for a date archive.
	 */
	private function date(): void {
		$date = $this->context->date();

		if ( ! $date['year'] ) {
			return;
		}

		$this->add( (string) $date['year'], (string) get_year_link( $date['year'] ) );

		if ( $date['month'] ) {
			$timestamp = gmmktime( 0, 0, 0, $date['month'], 1, $date['year'] );
			$this->add( (string) wp_date( 'F', $timestamp, new \DateTimeZone( 'UTC' ) ), (string) get_month_link( $date['year'], $date['month'] ) );
		}

		if ( $date['day'] && $date['month'] ) {
			$this->add( (string) $date['day'], (string) get_day_link( $date['year'], $date['month'], $date['day'] ) );
		}
	}

	/**
	 * Adds the posts page when the site uses a static front page.
	 */
	private function add_blog_page(): void {
		if ( ! $this->options->get( 'breadcrumbs.show_blog', true ) || 'page' !== get_option( 'show_on_front' ) ) {
			return;
		}

		$posts_page = get_post( (int) get_option( 'page_for_posts' ) );

		if ( $posts_page instanceof WP_Post && 'publish' === $posts_page->post_status ) {
			$this->add_post( $posts_page );
		}
	}

	/**
	 * Adds a term preceded by its ancestors.
	 *
	 * @param WP_Term $term Term.
	 */
	private function add_term_with_ancestors( WP_Term $term ): void {
		foreach ( array_reverse( get_ancestors( (int) $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( (int) $ancestor_id, $term->taxonomy );
			if ( $ancestor instanceof WP_Term ) {
				$this->add_term( $ancestor );
			}
		}

		$this->add_term( $term );
	}

	/**
	 * Adds a post crumb (breadcrumb title override, else post title).
	 *
	 * @param WP_Post $post Post.
	 */
	private function add_post( WP_Post $post ): void {
		$label = trim( (string) Meta::post( $post->ID, 'breadcrumb_title' ) );
		$link  = get_permalink( $post );

		$this->add( '' !== $label ? $label : wp_strip_all_tags( get_the_title( $post ) ), is_string( $link ) ? $link : '' );
	}

	/**
	 * Adds a term crumb.
	 *
	 * @param WP_Term $term Term.
	 */
	private function add_term( WP_Term $term ): void {
		$label = trim( (string) Meta::term( (int) $term->term_id, 'breadcrumb_title' ) );
		$link  = get_term_link( $term );

		$this->add( '' !== $label ? $label : $term->name, is_string( $link ) ? $link : '' );
	}

	/**
	 * Appends a crumb, skipping empty labels and consecutive duplicates.
	 *
	 * @param string $label Label (plain text).
	 * @param string $url   URL.
	 */
	private function add( string $label, string $url ): void {
		$label = trim( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) );

		if ( '' === $label ) {
			return;
		}

		$last = end( $this->items );

		if ( $last && '' !== $url && $last['url'] === $url ) {
			return;
		}

		$this->items[] = array(
			'label' => $label,
			'url'   => $url,
		);
	}
}
