<?php
/**
 * Resolved SEO values for one context.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Presentation;

use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Options;
use WPInterface\SEO\Support\Images;
use WPInterface\SEO\Support\Url;
use WPInterface\SEO\Templates\Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Computes title, description, robots, canonical and social values for a
 * Context by combining per-object meta, settings templates and fallbacks.
 *
 * Every getter is memoised; instances are cached per Context by the plugin.
 */
final class Presentation {

	/**
	 * Auto-generated descriptions are trimmed to this many characters.
	 */
	private const DESCRIPTION_MAX = 320;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Template parser.
	 *
	 * @var Parser
	 */
	private Parser $parser;

	/**
	 * Context.
	 *
	 * @var Context
	 */
	private Context $context;

	/**
	 * Memoised values.
	 *
	 * @var array<string, mixed>
	 */
	private array $memo = array();

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 * @param Parser  $parser  Template parser.
	 * @param Context $context Context.
	 */
	public function __construct( Options $options, Parser $parser, Context $context ) {
		$this->options = $options;
		$this->parser  = $parser;
		$this->context = $context;
	}

	/**
	 * The context.
	 */
	public function context(): Context {
		return $this->context;
	}

	/**
	 * Document title.
	 */
	public function title(): string {
		return $this->memo(
			'title',
			function (): string {
				$custom   = $this->object_field( 'title' );
				$template = '' !== $custom ? $custom : (string) ( $this->settings()['title'] ?? '' );
				$title    = '' !== trim( $template ) ? $this->parser->parse( $template, $this->context ) : '';

				if ( '' === $title && ! $this->context->is( Context::OTHER ) ) {
					$title = $this->parser->parse( '%title% %sep% %sitename%', $this->context );
				}

				/**
				 * Filters the SEO title.
				 *
				 * @param string  $title   Title.
				 * @param Context $context Context.
				 */
				return (string) apply_filters( 'wpiseo_title', $title, $this->context );
			}
		);
	}

	/**
	 * Meta description ('' when none).
	 */
	public function description(): string {
		return $this->memo(
			'description',
			function (): string {
				$custom = $this->object_field( 'description' );

				if ( '' !== $custom ) {
					$description = $this->parser->parse( $custom, $this->context );
				} else {
					$template    = (string) ( $this->settings()['description'] ?? '' );
					$description = '' !== trim( $template ) ? self::truncate( $this->parser->parse( $template, $this->context ) ) : '';
				}

				/**
				 * Filters the meta description.
				 *
				 * @param string  $description Description.
				 * @param Context $context     Context.
				 */
				return (string) apply_filters( 'wpiseo_description', $description, $this->context );
			}
		);
	}

	/**
	 * Robots directives: name => true (bare) or string value.
	 *
	 * @return array<string, true|string>
	 */
	public function robots(): array {
		return $this->memo(
			'robots',
			function (): array {
				$noindex  = $this->is_noindex();
				$advanced = $this->advanced_robots();
				$robots   = array(
					( $noindex ? 'noindex' : 'index' ) => true,
					( $this->is_nofollow() ? 'nofollow' : 'follow' ) => true,
				);

				foreach ( $advanced as $directive ) {
					$robots[ $directive ] = true;
				}

				if ( ! $noindex && $this->options->get( 'meta.max_snippet', true ) ) {
					if ( ! in_array( 'nosnippet', $advanced, true ) ) {
						$robots['max-snippet'] = '-1';
					}
					if ( ! in_array( 'noimageindex', $advanced, true ) ) {
						$robots['max-image-preview'] = 'large';
					}
					$robots['max-video-preview'] = '-1';
				}

				/**
				 * Filters robots directives.
				 *
				 * @param array   $robots  Directive => true|string.
				 * @param Context $context Context.
				 */
				$robots = apply_filters( 'wpiseo_robots', $robots, $this->context );

				return is_array( $robots ) ? $robots : array();
			}
		);
	}

	/**
	 * Whether the page may be indexed (site visibility and noindex settings).
	 */
	public function is_indexable(): bool {
		return (bool) get_option( 'blog_public' ) && ! isset( $this->robots()['noindex'] );
	}

	/**
	 * Canonical URL to print; '' for noindex pages, search and 404.
	 */
	public function canonical(): string {
		return $this->memo(
			'canonical',
			function (): string {
				$canonical = isset( $this->robots()['noindex'] ) ? '' : $this->url();

				/**
				 * Filters the canonical URL ('' prints none).
				 *
				 * @param string  $canonical Canonical URL.
				 * @param Context $context   Context.
				 */
				return (string) apply_filters( 'wpiseo_canonical', $canonical, $this->context );
			}
		);
	}

	/**
	 * Preferred URL of the page regardless of indexability (used for og:url and schema).
	 */
	public function url(): string {
		return $this->memo(
			'url',
			function (): string {
				$custom = $this->object_field( 'canonical' );

				return '' !== $custom ? $custom : $this->natural_url();
			}
		);
	}

	/**
	 * Open Graph title.
	 */
	public function og_title(): string {
		return $this->memo( 'og_title', fn(): string => $this->social_text( 'og_title' ) ?? $this->title() );
	}

	/**
	 * Open Graph description.
	 */
	public function og_description(): string {
		return $this->memo( 'og_description', fn(): string => $this->social_text( 'og_description' ) ?? $this->description() );
	}

	/**
	 * Open Graph image.
	 *
	 * @return array<string, mixed>|null
	 */
	public function og_image(): ?array {
		return $this->memo(
			'og_image',
			function (): ?array {
				$image = $this->social_image( 'og_image' )
					?? $this->primary_image()
					?? $this->term_image()
					?? Images::social( (int) $this->options->get( 'social.default_image', 0 ) );

				/**
				 * Filters the Open Graph image.
				 *
				 * @param array|null $image   Image data or null.
				 * @param Context    $context Context.
				 */
				$image = apply_filters( 'wpiseo_og_image', $image, $this->context );

				return is_array( $image ) && ! empty( $image['url'] ) ? $image : null;
			}
		);
	}

	/**
	 * X (Twitter) title.
	 */
	public function twitter_title(): string {
		return $this->memo( 'twitter_title', fn(): string => $this->social_text( 'twitter_title' ) ?? $this->og_title() );
	}

	/**
	 * X (Twitter) description.
	 */
	public function twitter_description(): string {
		return $this->memo( 'twitter_description', fn(): string => $this->social_text( 'twitter_description' ) ?? $this->og_description() );
	}

	/**
	 * X (Twitter) image.
	 *
	 * @return array<string, mixed>|null
	 */
	public function twitter_image(): ?array {
		return $this->memo( 'twitter_image', fn(): ?array => $this->social_image( 'twitter_image' ) ?? $this->og_image() );
	}

	/**
	 * Main image of a singular page: featured image, else first content image.
	 *
	 * @return array<string, mixed>|null
	 */
	public function primary_image(): ?array {
		return $this->memo(
			'primary_image',
			function (): ?array {
				$post = $this->context->post();

				if ( ! $post || post_password_required( $post ) ) {
					return null;
				}

				$thumbnail_id = (int) get_post_thumbnail_id( $post );

				return ( $thumbnail_id ? Images::social( $thumbnail_id ) : null ) ?? Images::first_in_content( $post );
			}
		);
	}

	/**
	 * Settings group (title/description/noindex templates) for the context.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		return $this->memo(
			'settings',
			function (): array {
				$context = $this->context;
				$post    = $context->post();

				switch ( $context->type() ) {
					case Context::FRONT_PAGE:
						return MetaModule::archive( $this->options, 'home' );

					case Context::SINGULAR:
						return $post ? MetaModule::post_type( $this->options, $post->post_type ) : array();

					case Context::HOME:
						return MetaModule::post_type( $this->options, 'page' );

					case Context::TERM:
						return MetaModule::taxonomy( $this->options, $context->subtype() );

					case Context::POST_TYPE_ARCHIVE:
						return MetaModule::post_type_archive( $this->options, $context->subtype() );

					case Context::AUTHOR:
						return MetaModule::archive( $this->options, 'author' );

					case Context::DATE:
						return MetaModule::archive( $this->options, 'date' );

					case Context::SEARCH:
						return MetaModule::archive( $this->options, 'search' );

					case Context::NOT_FOUND:
						return MetaModule::archive( $this->options, 'not_found' );
				}

				return array();
			}
		);
	}

	/**
	 * Whether the page is noindex (before filters).
	 */
	private function is_noindex(): bool {
		$context = $this->context;

		if ( $context->is( Context::SEARCH, Context::NOT_FOUND ) ) {
			return true;
		}

		if ( $context->is( Context::OTHER ) ) {
			return false;
		}

		$override = $this->object_field( 'noindex' );

		if ( 'noindex' === $override ) {
			return true;
		}

		$noindex = false;

		if ( 'index' !== $override ) {
			$noindex = (bool) ( $this->settings()['noindex'] ?? false );

			$post = $context->post();

			if ( $post && $context->is( Context::SINGULAR ) && 'publish' !== $post->post_status ) {
				$noindex = true;
			}

			if ( $context->is( Context::FRONT_PAGE ) ) {
				$noindex = false;
			}

			if ( $context->is( Context::AUTHOR ) && $context->user() && ! $this->author_has_posts( (int) $context->user()->ID ) ) {
				$noindex = true;
			}
		}

		if (
			! $noindex
			&& $context->is_paged()
			&& ! $context->is( Context::SINGULAR )
			&& $this->options->get( 'meta.noindex_paginated', false )
		) {
			$noindex = true;
		}

		return $noindex;
	}

	/**
	 * Whether links should not be followed.
	 */
	private function is_nofollow(): bool {
		$post = $this->context->post();

		return $post && $this->context->is( Context::SINGULAR, Context::FRONT_PAGE, Context::HOME ) && (bool) Meta::post( $post->ID, 'nofollow' );
	}

	/**
	 * Extra per-post directives (noarchive, nosnippet, noimageindex).
	 *
	 * @return list<string>
	 */
	private function advanced_robots(): array {
		$post = $this->context->post();

		if ( ! $post || ! $this->context->is( Context::SINGULAR, Context::FRONT_PAGE, Context::HOME ) ) {
			return array();
		}

		$advanced = Meta::post( $post->ID, 'robots_advanced' );

		return is_array( $advanced ) ? array_values( $advanced ) : array();
	}

	/**
	 * URL of the page as WordPress builds it, with pagination.
	 */
	private function natural_url(): string {
		$context = $this->context;
		$page    = $context->page();
		$url     = '';

		switch ( $context->type() ) {
			case Context::FRONT_PAGE:
				$url = Url::paged( home_url( '/' ), $page );
				break;

			case Context::SINGULAR:
				$post = $context->post();
				$link = $post ? get_permalink( $post ) : false;
				$url  = is_string( $link ) ? Url::post_page( $link, $page ) : '';
				break;

			case Context::HOME:
				$post = $context->post();
				$link = $post ? get_permalink( $post ) : home_url( '/' );
				$url  = is_string( $link ) ? Url::paged( $link, $page ) : '';
				break;

			case Context::TERM:
				$term = $context->term();
				$link = $term ? get_term_link( $term ) : '';
				$url  = is_string( $link ) ? Url::paged( $link, $page ) : '';
				break;

			case Context::POST_TYPE_ARCHIVE:
				$link = get_post_type_archive_link( $context->subtype() );
				$url  = is_string( $link ) ? Url::paged( $link, $page ) : '';
				break;

			case Context::AUTHOR:
				$user = $context->user();
				$url  = $user ? Url::paged( get_author_posts_url( (int) $user->ID ), $page ) : '';
				break;

			case Context::DATE:
				$date = $context->date();
				if ( $date['day'] ) {
					$link = get_day_link( $date['year'], $date['month'], $date['day'] );
				} elseif ( $date['month'] ) {
					$link = get_month_link( $date['year'], $date['month'] );
				} else {
					$link = get_year_link( $date['year'] );
				}
				$url = Url::paged( (string) $link, $page );
				break;
		}

		return $url;
	}

	/**
	 * A per-post or per-term field for singular and term contexts.
	 *
	 * @param string $key Field key.
	 */
	private function object_field( string $key ): string {
		$context = $this->context;
		$post    = $context->post();
		$value   = '';

		if ( $post && $context->is( Context::SINGULAR, Context::FRONT_PAGE, Context::HOME ) ) {
			$value = Meta::post( $post->ID, $key );
		} elseif ( $context->term() ) {
			$value = Meta::term( (int) $context->term()->term_id, $key );
		}

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Custom social text (parsed), or null when not set.
	 *
	 * @param string $key Field key.
	 */
	private function social_text( string $key ): ?string {
		$custom = $this->object_field( $key );

		return '' !== $custom ? $this->parser->parse( $custom, $this->context ) : null;
	}

	/**
	 * Custom social image, or null when not set.
	 *
	 * @param string $key Field key.
	 * @return array<string, mixed>|null
	 */
	private function social_image( string $key ): ?array {
		$context = $this->context;
		$post    = $context->post();
		$id      = 0;

		if ( $post && $context->is( Context::SINGULAR, Context::FRONT_PAGE, Context::HOME ) ) {
			$id = (int) Meta::post( $post->ID, $key );
		} elseif ( $context->term() ) {
			$id = (int) Meta::term( (int) $context->term()->term_id, $key );
		}

		return $id ? Images::social( $id ) : null;
	}

	/**
	 * Image stored by themes/plugins on a term (`thumbnail_id` term meta, used
	 * e.g. by WooCommerce product categories).
	 *
	 * @return array<string, mixed>|null
	 */
	private function term_image(): ?array {
		$term = $this->context->term();

		if ( ! $term ) {
			return null;
		}

		return Images::social( (int) get_term_meta( (int) $term->term_id, 'thumbnail_id', true ) );
	}

	/**
	 * Whether an author has published posts in public post types.
	 *
	 * @param int $user_id User ID.
	 */
	private function author_has_posts( int $user_id ): bool {
		$post_types = array_values( get_post_types( array( 'public' => true ) ) );

		return (int) count_user_posts( $user_id, $post_types, true ) > 0;
	}

	/**
	 * Truncates auto-generated text at a word boundary.
	 *
	 * @param string $text Text.
	 */
	private static function truncate( string $text ): string {
		if ( mb_strlen( $text ) <= self::DESCRIPTION_MAX ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, self::DESCRIPTION_MAX - 1 );
		$space = mb_strrpos( $cut, ' ' );

		return rtrim( false !== $space && $space > 200 ? mb_substr( $cut, 0, $space ) : $cut, " ,.;:-\u{2013}\u{2014}" ) . '…';
	}

	/**
	 * Memoises a computed value.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Producer.
	 * @return mixed
	 */
	private function memo( string $key, callable $callback ) {
		if ( ! array_key_exists( $key, $this->memo ) ) {
			$this->memo[ $key ] = $callback();
		}

		return $this->memo[ $key ];
	}
}
