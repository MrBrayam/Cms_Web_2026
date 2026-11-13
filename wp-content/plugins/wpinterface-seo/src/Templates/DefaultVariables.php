<?php
/**
 * Built-in template variables.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Templates;

use WP_Post;
use WP_Term;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the core `%variables%`.
 *
 * Dates are read with get_post_time()/get_post_modified_time() rather than
 * the_date()/get_the_modified_date(), because some themes and plugins filter
 * the latter into relative strings ("3 days ago") regardless of format.
 */
final class DefaultVariables {

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
	 * Registers all built-in variables.
	 *
	 * @param VariableRegistry $registry Registry.
	 */
	public function register( VariableRegistry $registry ): void {
		$variables = array(
			'title'            => array(
				'label'       => __( 'Title', 'wpinterface-seo' ),
				'description' => __( 'Title of the post, term, author, archive or search.', 'wpinterface-seo' ),
				'callback'    => array( $this, 'title' ),
			),
			'sitename'         => array(
				'label'       => __( 'Site title', 'wpinterface-seo' ),
				'description' => __( 'The site title from Settings → General.', 'wpinterface-seo' ),
				'callback'    => static fn() => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			),
			'tagline'          => array(
				'label'       => __( 'Tagline', 'wpinterface-seo' ),
				'description' => __( 'The site tagline from Settings → General.', 'wpinterface-seo' ),
				'callback'    => static fn() => wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ),
			),
			'sep'              => array(
				'label'       => __( 'Separator', 'wpinterface-seo' ),
				'description' => __( 'The title separator chosen in settings.', 'wpinterface-seo' ),
				'callback'    => fn() => (string) $this->options->get( 'general.separator', '-' ),
			),
			'excerpt'          => array(
				'label'            => __( 'Excerpt', 'wpinterface-seo' ),
				'description'      => sprintf(
					/* translators: %s: example variable. */
					__( 'Post excerpt, or the start of the content. Optional word limit, e.g. %s.', 'wpinterface-seo' ),
					'%excerpt(20)%'
				),
				'callback'         => array( $this, 'excerpt' ),
				'example'          => '%excerpt(30)%',
				'accepts_argument' => true,
			),
			'category'         => array(
				'label'       => __( 'Categories', 'wpinterface-seo' ),
				'description' => __( 'Comma-separated categories of the post.', 'wpinterface-seo' ),
				'callback'    => fn( Context $c ) => $this->term_names( $c, 'category' ),
			),
			'primary_category' => array(
				'label'       => __( 'Primary category', 'wpinterface-seo' ),
				'description' => __( 'The primary category, or the first category.', 'wpinterface-seo' ),
				'callback'    => array( $this, 'primary_category' ),
			),
			'tag'              => array(
				'label'       => __( 'Tags', 'wpinterface-seo' ),
				'description' => __( 'Comma-separated tags of the post.', 'wpinterface-seo' ),
				'callback'    => fn( Context $c ) => $this->term_names( $c, 'post_tag' ),
			),
			'term'             => array(
				'label'            => __( 'Terms', 'wpinterface-seo' ),
				'description'      => sprintf(
					/* translators: %s: example variable. */
					__( 'Comma-separated terms of the post in a taxonomy, e.g. %s.', 'wpinterface-seo' ),
					'%term(genre)%'
				),
				'callback'         => fn( Context $c, string $taxonomy ) => '' === $taxonomy ? '' : $this->term_names( $c, sanitize_key( $taxonomy ) ),
				'example'          => '%term(product_cat)%',
				'accepts_argument' => true,
			),
			'term_title'       => array(
				'label'       => __( 'Term title', 'wpinterface-seo' ),
				'description' => __( 'Name of the category, tag or term being viewed.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => $c->term() ? $c->term()->name : '',
			),
			'term_description' => array(
				'label'       => __( 'Term description', 'wpinterface-seo' ),
				'description' => __( 'Description of the category, tag or term being viewed.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => $c->term() ? $c->term()->description : '',
			),
			'author'           => array(
				'label'       => __( 'Author', 'wpinterface-seo' ),
				'description' => __( 'Display name of the post author or the author being viewed.', 'wpinterface-seo' ),
				'callback'    => array( $this, 'author' ),
			),
			'date'             => array(
				'label'            => __( 'Publish date', 'wpinterface-seo' ),
				'description'      => sprintf(
					/* translators: %s: example variable. */
					__( 'Post publish date. Optional PHP date format, e.g. %s.', 'wpinterface-seo' ),
					'%date(Y)%'
				),
				'callback'         => fn( Context $c, string $format ) => $this->post_date( $c, $format, false ),
				'example'          => '%date(F j, Y)%',
				'accepts_argument' => true,
			),
			'modified'         => array(
				'label'            => __( 'Modified date', 'wpinterface-seo' ),
				'description'      => __( 'Post last-modified date. Optional PHP date format.', 'wpinterface-seo' ),
				'callback'         => fn( Context $c, string $format ) => $this->post_date( $c, $format, true ),
				'example'          => '%modified(Y-m-d)%',
				'accepts_argument' => true,
			),
			'page'             => array(
				'label'       => __( 'Page number', 'wpinterface-seo' ),
				'description' => __( '"Page 2 of 5" on paginated pages; empty on the first page.', 'wpinterface-seo' ),
				'callback'    => array( $this, 'page' ),
			),
			'pagenumber'       => array(
				'label'       => __( 'Current page', 'wpinterface-seo' ),
				'description' => __( 'Current page number.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => (string) $c->page(),
			),
			'pagetotal'        => array(
				'label'       => __( 'Total pages', 'wpinterface-seo' ),
				'description' => __( 'Total number of pages.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => (string) $c->max_pages(),
			),
			'search_query'     => array(
				'label'       => __( 'Search phrase', 'wpinterface-seo' ),
				'description' => __( 'The phrase searched for.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => $c->search_query(),
			),
			'post_type_name'   => array(
				'label'       => __( 'Post type name', 'wpinterface-seo' ),
				'description' => __( 'Singular name on single entries, plural name on archives.', 'wpinterface-seo' ),
				'callback'    => array( $this, 'post_type_name' ),
			),
			'id'               => array(
				'label'       => __( 'ID', 'wpinterface-seo' ),
				'description' => __( 'ID of the post, term or user.', 'wpinterface-seo' ),
				'callback'    => static fn( Context $c ) => $c->object_id() ? (string) $c->object_id() : '',
			),
			'currentyear'      => array(
				'label'       => __( 'Current year', 'wpinterface-seo' ),
				'description' => __( 'The current year.', 'wpinterface-seo' ),
				'callback'    => static fn() => (string) wp_date( 'Y' ),
			),
			'currentmonth'     => array(
				'label'       => __( 'Current month', 'wpinterface-seo' ),
				'description' => __( 'The current month name.', 'wpinterface-seo' ),
				'callback'    => static fn() => (string) wp_date( 'F' ),
			),
			'currentdate'      => array(
				'label'       => __( 'Current date', 'wpinterface-seo' ),
				'description' => __( 'Today\'s date in the site date format.', 'wpinterface-seo' ),
				'callback'    => static fn() => (string) wp_date( (string) get_option( 'date_format' ) ),
			),
			'custom_field'     => array(
				'label'            => __( 'Custom field', 'wpinterface-seo' ),
				'description'      => sprintf(
					/* translators: %s: example variable. */
					__( 'Value of a post or term meta field, e.g. %s.', 'wpinterface-seo' ),
					'%custom_field(price)%'
				),
				'callback'         => array( $this, 'custom_field' ),
				'example'          => '%custom_field(price)%',
				'accepts_argument' => true,
			),
		);

		foreach ( $variables as $name => $args ) {
			$registry->register( $name, $args );
		}
	}

	/**
	 * `%title%`.
	 *
	 * @param Context $context Context.
	 */
	public function title( Context $context ): string {
		$post = $context->post();

		if ( $post ) {
			return $post->post_title;
		}

		switch ( $context->type() ) {
			case Context::TERM:
				return $context->term() ? $context->term()->name : '';

			case Context::AUTHOR:
				return $context->user() ? $context->user()->display_name : '';

			case Context::POST_TYPE_ARCHIVE:
				return $context->post_type_object() ? (string) $context->post_type_object()->labels->name : '';

			case Context::DATE:
				return $this->date_archive_title( $context );

			case Context::SEARCH:
				/* translators: %s: search phrase. */
				return sprintf( __( 'Search results for "%s"', 'wpinterface-seo' ), $context->search_query() );

			case Context::NOT_FOUND:
				return __( 'Page not found', 'wpinterface-seo' );

			case Context::FRONT_PAGE:
			case Context::HOME:
				return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		}

		return '';
	}

	/**
	 * `%excerpt%` / `%excerpt(n)%`.
	 *
	 * Uses the manual excerpt when present; otherwise trims the content
	 * without running `the_content` filters (avoids shortcode side effects).
	 *
	 * @param Context $context Context.
	 * @param string  $words   Optional word limit.
	 */
	public function excerpt( Context $context, string $words = '' ): string {
		$post = $context->post();

		if ( ! $post ) {
			return $context->term() ? $context->term()->description : '';
		}

		$limit = absint( $words );

		if ( '' !== trim( $post->post_excerpt ) ) {
			$text = $post->post_excerpt;
		} elseif ( post_password_required( $post ) ) {
			return '';
		} else {
			$text = strip_shortcodes( excerpt_remove_blocks( $post->post_content ) );
		}

		return $limit ? wp_trim_words( $text, $limit, '…' ) : self::snippet( wp_trim_words( $text, 30, '…' ) );
	}

	/**
	 * Shortens text to the length search engines show (about 160 characters),
	 * breaking between words.
	 *
	 * @param string $text Text.
	 */
	private static function snippet( string $text ): string {
		if ( mb_strlen( $text ) <= 160 ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, 156 );
		$space = mb_strrpos( $cut, ' ' );

		return rtrim( false === $space ? $cut : mb_substr( $cut, 0, $space ), " \t\n\r\0\x0B,.;:-–—" ) . '…';
	}

	/**
	 * `%primary_category%`.
	 *
	 * @param Context $context Context.
	 */
	public function primary_category( Context $context ): string {
		$post = $context->post();

		if ( ! $post ) {
			return $context->term() && 'category' === $context->term()->taxonomy ? $context->term()->name : '';
		}

		$term = Meta::primary_term( $post, 'category' );

		return $term instanceof WP_Term ? $term->name : '';
	}

	/**
	 * `%author%`.
	 *
	 * @param Context $context Context.
	 */
	public function author( Context $context ): string {
		if ( $context->user() ) {
			return $context->user()->display_name;
		}

		$post = $context->post();

		if ( ! $post || ! post_type_supports( $post->post_type, 'author' ) ) {
			return '';
		}

		$user = get_userdata( (int) $post->post_author );

		return $user ? $user->display_name : '';
	}

	/**
	 * `%page%`: "Page 2 of 5", empty on page 1.
	 *
	 * @param Context $context Context.
	 */
	public function page( Context $context ): string {
		if ( ! $context->is_paged() ) {
			return '';
		}

		/* translators: 1: current page number, 2: total number of pages. */
		return sprintf( __( 'Page %1$d of %2$d', 'wpinterface-seo' ), $context->page(), $context->max_pages() );
	}

	/**
	 * `%post_type_name%`.
	 *
	 * @param Context $context Context.
	 */
	public function post_type_name( Context $context ): string {
		if ( $context->post_type_object() ) {
			return (string) $context->post_type_object()->labels->name;
		}

		$post = $context->post();

		if ( ! $post ) {
			return '';
		}

		$object = get_post_type_object( $post->post_type );

		return $object ? (string) $object->labels->singular_name : '';
	}

	/**
	 * `%custom_field(key)%` for posts and terms. Only scalar values are used.
	 *
	 * @param Context $context Context.
	 * @param string  $key     Meta key.
	 */
	public function custom_field( Context $context, string $key ): string {
		/**
		 * Filters whether a meta key may be printed via %custom_field()%.
		 * Protected (underscore-prefixed) keys are refused by default because
		 * the output is public.
		 *
		 * @param bool    $allowed Whether the key is allowed.
		 * @param string  $key     Meta key.
		 * @param Context $context Context.
		 */
		$allowed = (bool) apply_filters( 'wpiseo_custom_field_allowed', '' !== $key && ! is_protected_meta( $key, 'post' ), $key, $context );

		if ( ! $allowed ) {
			return '';
		}

		$value = '';

		if ( $context->post() ) {
			$value = get_post_meta( $context->post()->ID, $key, true );
		} elseif ( $context->term() ) {
			$value = get_term_meta( $context->term()->term_id, $key, true );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Comma-separated term names of the post (or the term being viewed).
	 *
	 * @param Context $context  Context.
	 * @param string  $taxonomy Taxonomy.
	 */
	private function term_names( Context $context, string $taxonomy ): string {
		$post = $context->post();

		if ( ! $post ) {
			$term = $context->term();

			return $term && $term->taxonomy === $taxonomy ? $term->name : '';
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		$terms = get_the_terms( $post, $taxonomy );

		if ( ! is_array( $terms ) ) {
			return '';
		}

		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * Formatted publish or modified date of the post.
	 *
	 * @param Context $context  Context.
	 * @param string  $format   PHP date format; site format when empty.
	 * @param bool    $modified Whether to use the modified date.
	 */
	private function post_date( Context $context, string $format, bool $modified ): string {
		$post = $context->post();

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$format = '' !== $format ? $format : (string) get_option( 'date_format' );
		$value  = $modified
			? get_post_modified_time( $format, false, $post, true )
			: get_post_time( $format, false, $post, true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Title of a date archive, e.g. "March 2026".
	 *
	 * @param Context $context Context.
	 */
	private function date_archive_title( Context $context ): string {
		$date = $context->date();

		if ( ! $date['year'] ) {
			return '';
		}

		if ( $date['day'] && $date['month'] ) {
			$timestamp = gmmktime( 0, 0, 0, $date['month'], $date['day'], $date['year'] );

			return (string) wp_date( (string) get_option( 'date_format' ), $timestamp, new \DateTimeZone( 'UTC' ) );
		}

		if ( $date['month'] ) {
			$timestamp = gmmktime( 0, 0, 0, $date['month'], 1, $date['year'] );

			return (string) wp_date( _x( 'F Y', 'monthly archives date format', 'wpinterface-seo' ), $timestamp, new \DateTimeZone( 'UTC' ) );
		}

		return (string) $date['year'];
	}
}
