<?php
/**
 * Polylang and WPML integration.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Integrations;

use WP_Post;
use WP_Term;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Options;
use WPInterface\SEO\Sitemaps\Router;

defined( 'ABSPATH' ) || exit;

/**
 * - Sitemaps list every language and link language versions with hreflang
 *   (both plugins print hreflang tags in `<head>` themselves, so this
 *   plugin does not add them again).
 * - Title templates and breadcrumb labels become translatable strings in
 *   Polylang (Languages → Translations) or WPML String Translation.
 */
final class Multilingual {

	public const CONTEXT = 'WPInterface SEO';

	private const WPML_HASH_OPTION = 'wpiseo_wpml_strings';

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
	 * Active multilingual plugin: polylang, wpml or ''.
	 */
	public static function provider(): string {
		if ( function_exists( 'pll_get_post_translations' ) && function_exists( 'pll_languages_list' ) ) {
			return 'polylang';
		}

		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return 'wpml';
		}

		return '';
	}

	/**
	 * Registers hooks once the multilingual plugin has loaded.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'boot' ), 1 );
	}

	/**
	 * Adds the integration hooks when Polylang or WPML is active.
	 */
	public function boot(): void {
		if ( '' === self::provider() ) {
			return;
		}

		add_filter( 'wpiseo_sitemap_post_query_args', array( $this, 'all_languages' ) );
		add_filter( 'wpiseo_sitemap_term_query_args', array( $this, 'all_languages' ) );
		add_action( 'template_redirect', array( $this, 'wpml_sitemap_language' ), -1 );
		add_filter( 'wpiseo_sitemap_post_entry', array( $this, 'post_alternates' ), 10, 2 );
		add_filter( 'wpiseo_sitemap_term_entry', array( $this, 'term_alternates' ), 10, 2 );
		add_filter( 'wpiseo_translate_string', array( $this, 'translate' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_strings' ) );
		}
	}

	/**
	 * Sitemap queries include every language (Polylang; WPML switches the
	 * request language to "all" instead, see wpml_sitemap_language()).
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	public function all_languages( $args ): array {
		$args = is_array( $args ) ? $args : array();

		if ( 'polylang' === self::provider() ) {
			$args['lang'] = '';
		}

		return $args;
	}

	/**
	 * WPML: sitemap requests read all languages.
	 */
	public function wpml_sitemap_language(): void {
		if ( 'wpml' === self::provider() && '' !== (string) get_query_var( Router::QUERY_VAR ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
			do_action( 'wpml_switch_language', 'all' );
		}
	}

	/**
	 * Adds hreflang alternates to a post entry.
	 *
	 * @param array<string, mixed>|null $entry Entry.
	 * @param WP_Post                   $post  Post.
	 * @return array<string, mixed>|null
	 */
	public function post_alternates( $entry, $post ) {
		if ( ! is_array( $entry ) || ! $post instanceof WP_Post ) {
			return $entry;
		}

		$versions = array();

		foreach ( $this->post_translations( $post ) as $language => $id ) {
			if ( 'publish' === get_post_status( $id ) && 'noindex' !== get_post_meta( $id, Fields::PREFIX . 'noindex', true ) ) {
				$url = $this->localize( (string) get_permalink( $id ), $language );

				if ( '' !== $url ) {
					$versions[ $language ] = $url;
				}
			}
		}

		return $this->with_alternates( $entry, $versions );
	}

	/**
	 * Adds hreflang alternates to a term entry.
	 *
	 * @param array<string, mixed>|null $entry Entry.
	 * @param WP_Term                   $term  Term.
	 * @return array<string, mixed>|null
	 */
	public function term_alternates( $entry, $term ) {
		if ( ! is_array( $entry ) || ! $term instanceof WP_Term ) {
			return $entry;
		}

		$versions = array();

		foreach ( $this->term_translations( $term ) as $language => $id ) {
			$link = get_term_link( $id, $term->taxonomy );

			if ( is_string( $link ) && 'noindex' !== get_term_meta( $id, Fields::PREFIX . 'noindex', true ) ) {
				$versions[ $language ] = $this->localize( $link, $language );
			}
		}

		return $this->with_alternates( $entry, $versions );
	}

	/**
	 * Translates a registered string into the current language.
	 *
	 * @param string $text Text.
	 */
	public function translate( $text ): string {
		$text = (string) $text;

		if ( '' === $text ) {
			return $text;
		}

		if ( 'polylang' === self::provider() && function_exists( 'pll__' ) ) {
			return (string) pll__( $text );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
		return (string) apply_filters( 'wpml_translate_single_string', $text, self::CONTEXT, self::name( $text ) );
	}

	/**
	 * Registers templates and labels for translation.
	 */
	public function register_strings(): void {
		$strings = $this->strings();

		if ( 'polylang' === self::provider() ) {
			if ( function_exists( 'pll_register_string' ) ) {
				foreach ( $strings as $label => $text ) {
					pll_register_string( $label, $text, self::CONTEXT );
				}
			}
			return;
		}

		// WPML stores registrations; only register again when the strings changed.
		$hash = md5( (string) wp_json_encode( $strings ) );

		if ( get_option( self::WPML_HASH_OPTION ) === $hash ) {
			return;
		}

		foreach ( $strings as $text ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
			do_action( 'wpml_register_single_string', self::CONTEXT, self::name( $text ), $text );
		}

		update_option( self::WPML_HASH_OPTION, $hash, false );
	}

	/**
	 * Translatable strings: label => text.
	 *
	 * @return array<string, string>
	 */
	public function strings(): array {
		$strings = array();
		$add     = static function ( string $label, $text ) use ( &$strings ): void {
			$text = trim( is_string( $text ) ? $text : '' );

			if ( '' !== $text && preg_match( '/\p{L}/u', (string) preg_replace( '/%[a-z_]+(\([^)]*\))?%/', '', $text ) ) ) {
				$strings[ $label ] = $text;
			}
		};

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$group = MetaModule::post_type( $this->options, $type->name );
			/* translators: %s: post type or taxonomy name. */
			$add( sprintf( __( 'Title template: %s', 'wpinterface-seo' ), $type->labels->name ), $group['title'] );
			/* translators: %s: post type or taxonomy name. */
			$add( sprintf( __( 'Description template: %s', 'wpinterface-seo' ), $type->labels->name ), $group['description'] );
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$group = MetaModule::taxonomy( $this->options, $taxonomy->name );
			/* translators: %s: post type or taxonomy name. */
			$add( sprintf( __( 'Title template: %s', 'wpinterface-seo' ), $taxonomy->labels->name ), $group['title'] );
			/* translators: %s: post type or taxonomy name. */
			$add( sprintf( __( 'Description template: %s', 'wpinterface-seo' ), $taxonomy->labels->name ), $group['description'] );
		}

		foreach ( (array) $this->options->get( 'meta.archives', array() ) as $archive => $group ) {
			/* translators: %s: archive type, e.g. author. */
			$add( sprintf( __( 'Title template: %s archive', 'wpinterface-seo' ), $archive ), $group['title'] ?? '' );
			/* translators: %s: archive type, e.g. author. */
			$add( sprintf( __( 'Description template: %s archive', 'wpinterface-seo' ), $archive ), $group['description'] ?? '' );
		}

		$add( __( 'Breadcrumbs: home label', 'wpinterface-seo' ), $this->options->get( 'breadcrumbs.home_label', '' ) );
		$add( __( 'Breadcrumbs: prefix', 'wpinterface-seo' ), $this->options->get( 'breadcrumbs.prefix', '' ) );
		$add( __( 'Breadcrumbs: search label', 'wpinterface-seo' ), $this->options->get( 'breadcrumbs.search_label', '' ) );
		$add( __( 'Breadcrumbs: 404 label', 'wpinterface-seo' ), $this->options->get( 'breadcrumbs.not_found_label', '' ) );

		return $strings;
	}

	/**
	 * Language => post ID of all versions of a post (including itself).
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, int>
	 */
	private function post_translations( WP_Post $post ): array {
		if ( 'polylang' === self::provider() ) {
			return array_map( 'intval', (array) pll_get_post_translations( $post->ID ) );
		}

		$versions = array();

		foreach ( $this->wpml_languages() as $code => $language ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
			$id = (int) apply_filters( 'wpml_object_id', $post->ID, $post->post_type, false, $code );

			if ( $id ) {
				$versions[ (string) $code ] = $id;
			}
		}

		return $versions;
	}

	/**
	 * Language => term ID of all versions of a term.
	 *
	 * @param WP_Term $term Term.
	 * @return array<string, int>
	 */
	private function term_translations( WP_Term $term ): array {
		if ( 'polylang' === self::provider() ) {
			return function_exists( 'pll_get_term_translations' ) ? array_map( 'intval', (array) pll_get_term_translations( $term->term_id ) ) : array();
		}

		$versions = array();

		foreach ( $this->wpml_languages() as $code => $language ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
			$id = (int) apply_filters( 'wpml_object_id', $term->term_id, $term->taxonomy, false, $code );

			if ( $id ) {
				$versions[ (string) $code ] = $id;
			}
		}

		return $versions;
	}

	/**
	 * Adds alternates (when there is more than one version) plus x-default.
	 *
	 * @param array<string, mixed>  $entry    Entry.
	 * @param array<string, string> $versions Language => URL.
	 * @return array<string, mixed>
	 */
	private function with_alternates( array $entry, array $versions ): array {
		$versions = array_filter( $versions );

		if ( count( $versions ) < 2 ) {
			return $entry;
		}

		$alternates = array();

		foreach ( $versions as $language => $url ) {
			$alternates[] = array(
				'hreflang' => $this->hreflang( $language ),
				'href'     => $url,
			);
		}

		$default = $this->default_language();

		if ( isset( $versions[ $default ] ) ) {
			$alternates[] = array(
				'hreflang' => 'x-default',
				'href'     => $versions[ $default ],
			);
		}

		$entry['alternates'] = $alternates;

		return $entry;
	}

	/**
	 * Hreflang code for a language slug (e.g. en-GB).
	 *
	 * @param string $language Language slug / code.
	 */
	private function hreflang( string $language ): string {
		if ( 'polylang' === self::provider() ) {
			// Same codes as Polylang prints in <head>: the country is only kept
			// when two languages share a language code (en-US / en-GB).
			$codes = array();

			foreach ( (array) pll_languages_list( array( 'fields' => '' ) ) as $object ) {
				if ( is_object( $object ) && isset( $object->slug ) ) {
					$codes[ (string) $object->slug ] = (string) ( $object->w3c ?? $object->slug );
				}
			}

			if ( ! isset( $codes[ $language ] ) ) {
				return $language;
			}

			$bases = array_count_values( array_map( static fn( string $code ): string => strtok( $code, '-' ), $codes ) );
			$base  = (string) strtok( $codes[ $language ], '-' );

			return $bases[ $base ] > 1 ? $codes[ $language ] : $base;
		}

		$languages = $this->wpml_languages();
		$tag       = $languages[ $language ]['tag'] ?? str_replace( '_', '-', (string) ( $languages[ $language ]['default_locale'] ?? $language ) );

		return strtolower( (string) $tag ) === $language ? $language : (string) $tag;
	}

	/**
	 * Default language slug.
	 */
	private function default_language(): string {
		if ( 'polylang' === self::provider() ) {
			return function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '';
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
		return (string) apply_filters( 'wpml_default_language', null );
	}

	/**
	 * WPML: the URL in a given language.
	 *
	 * @param string $url      URL.
	 * @param string $language Language code.
	 */
	private function localize( string $url, string $language ): string {
		if ( 'wpml' !== self::provider() || '' === $url ) {
			return $url;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
		return (string) apply_filters( 'wpml_permalink', $url, $language, true );
	}

	/**
	 * WPML active languages keyed by code.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function wpml_languages(): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

		return is_array( $languages ) ? $languages : array();
	}

	/**
	 * Stable WPML string name for a text.
	 *
	 * @param string $text Text.
	 */
	private static function name( string $text ): string {
		return 'wpiseo-' . md5( $text );
	}
}
