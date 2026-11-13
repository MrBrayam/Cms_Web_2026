<?php
/**
 * Titles & Meta module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Meta;

use WPInterface\SEO\Admin\TermFields;
use WPInterface\SEO\Admin\UserFields;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Editor\Editor;
use WPInterface\SEO\Integrations\BlockBindings;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Options;
use WPInterface\SEO\Presentation\Presentation;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;
use WPInterface\SEO\Support\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * SEO title, meta description, robots and canonical output, plus the
 * per-post, per-term and per-user editing UI.
 */
final class MetaModule extends Module {

	/**
	 * Verification services and their meta tag names (printed on the front page).
	 */
	public const VERIFICATION = array(
		'google'    => 'google-site-verification',
		'bing'      => 'msvalidate.01',
		'yandex'    => 'yandex-verification',
		'baidu'     => 'baidu-site-verification',
		'pinterest' => 'p:domain_verify',
	);

	/**
	 * Default settings.
	 *
	 * Post type / taxonomy maps start empty: post types register on `init`,
	 * after settings defaults are built, so their defaults are resolved lazily
	 * in post_type() / taxonomy().
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'post_types'         => array(),
			'taxonomies'         => array(),
			'post_type_archives' => array(),
			'archives'           => array(
				'home'      => array(
					'title'       => '%sitename% %page% %sep% %tagline%',
					'description' => '%tagline%',
				),
				'author'    => array(
					'title'       => '%author% %page% %sep% %sitename%',
					'description' => '',
					'noindex'     => false,
					'disabled'    => false,
				),
				'date'      => array(
					'title'       => '%title% %page% %sep% %sitename%',
					'description' => '',
					'noindex'     => true,
					'disabled'    => false,
				),
				'search'    => array(
					'title' => '%title% %page% %sep% %sitename%',
				),
				'not_found' => array(
					'title' => '%title% %sep% %sitename%',
				),
			),
			'noindex_paginated'  => false,
			'max_snippet'        => true,
			'verification'       => array_fill_keys( array_keys( self::VERIFICATION ), '' ),
		);
	}

	/**
	 * Defaults for one post type.
	 *
	 * @param string $post_type Post type.
	 * @return array{title: string, description: string, noindex: bool}
	 */
	public static function post_type_defaults( string $post_type ): array {
		return array(
			'title'       => '%title% %page% %sep% %sitename%',
			'description' => '%excerpt%',
			'noindex'     => 'attachment' === $post_type,
		);
	}

	/**
	 * Defaults for one taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array{title: string, description: string, noindex: bool}
	 */
	public static function taxonomy_defaults( string $taxonomy ): array {
		return array(
			'title'       => '%term_title% %page% %sep% %sitename%',
			'description' => '%term_description%',
			'noindex'     => 'post_format' === $taxonomy,
		);
	}

	/**
	 * Defaults for a post type archive.
	 *
	 * @return array{title: string, description: string, noindex: bool}
	 */
	public static function post_type_archive_defaults(): array {
		return array(
			'title'       => '%title% %page% %sep% %sitename%',
			'description' => '',
			'noindex'     => false,
		);
	}

	/**
	 * Effective settings for a post type.
	 *
	 * @param Options $options   Settings.
	 * @param string  $post_type Post type.
	 * @return array<string, mixed>
	 */
	public static function post_type( Options $options, string $post_type ): array {
		$stored = $options->get( 'meta.post_types.' . $post_type, array() );

		return array_merge( self::post_type_defaults( $post_type ), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Effective settings for a taxonomy.
	 *
	 * @param Options $options  Settings.
	 * @param string  $taxonomy Taxonomy.
	 * @return array<string, mixed>
	 */
	public static function taxonomy( Options $options, string $taxonomy ): array {
		$stored = $options->get( 'meta.taxonomies.' . $taxonomy, array() );

		return array_merge( self::taxonomy_defaults( $taxonomy ), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Effective settings for a post type archive.
	 *
	 * @param Options $options   Settings.
	 * @param string  $post_type Post type.
	 * @return array<string, mixed>
	 */
	public static function post_type_archive( Options $options, string $post_type ): array {
		$stored = $options->get( 'meta.post_type_archives.' . $post_type, array() );

		return array_merge( self::post_type_archive_defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Effective settings for a special archive (home, author, date, search, not_found).
	 *
	 * @param Options $options Settings.
	 * @param string  $archive Archive key.
	 * @return array<string, mixed>
	 */
	public static function archive( Options $options, string $archive ): array {
		$defaults = self::default_settings()['archives'][ $archive ] ?? array();
		$stored   = $options->get( 'meta.archives.' . $archive, array() );

		return array_merge( $defaults, is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Sanitizes the `meta` settings section.
	 *
	 * @param array<string, mixed> $values   Section values.
	 * @param array<string, mixed> $defaults Section defaults.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $values, array $defaults ): array {
		$archives = is_array( $values['archives'] ?? null ) ? $values['archives'] : array();
		$clean    = array(
			'post_types'         => self::sanitize_group_map( $values['post_types'] ?? array() ),
			'taxonomies'         => self::sanitize_group_map( $values['taxonomies'] ?? array() ),
			'post_type_archives' => self::sanitize_group_map( $values['post_type_archives'] ?? array() ),
			'archives'           => array(),
			'noindex_paginated'  => Sanitize::to_bool( $values['noindex_paginated'] ?? $defaults['noindex_paginated'] ),
			'max_snippet'        => Sanitize::to_bool( $values['max_snippet'] ?? $defaults['max_snippet'] ),
			'verification'       => array(),
		);

		foreach ( array_keys( self::VERIFICATION ) as $service ) {
			$clean['verification'][ $service ] = self::verification_code( $values['verification'][ $service ] ?? '' );
		}

		foreach ( $defaults['archives'] as $archive => $archive_defaults ) {
			$clean['archives'][ $archive ] = self::sanitize_group(
				array_merge( $archive_defaults, is_array( $archives[ $archive ] ?? null ) ? $archives[ $archive ] : array() ),
				array_keys( $archive_defaults )
			);
		}

		return $clean;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		if ( $this->plugin->output_enabled() ) {
			add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 15 );
			add_filter( 'wp_robots', array( $this, 'filter_wp_robots' ), 20 );
			add_action( 'wp_head', array( $this, 'render_head' ), 1 );
			add_action( 'template_redirect', array( $this, 'redirect_disabled_archives' ), 1 );
			remove_action( 'wp_head', 'rel_canonical' );
		}

		( new Editor( $this->plugin ) )->register();
		( new BlockBindings() )->register();

		if ( is_admin() ) {
			( new TermFields() )->register();
			( new UserFields() )->register();
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 5 );
		}
	}

	/**
	 * Site verification code from a pasted code or a whole meta tag.
	 *
	 * @param mixed $value Input.
	 */
	public static function verification_code( $value ): string {
		$value = trim( is_scalar( $value ) ? (string) $value : '' );

		if ( preg_match( '/content\s*=\s*["\']([^"\']*)["\']/i', $value, $match ) ) {
			$value = $match[1];
		} else {
			$value = wp_strip_all_tags( $value );
		}

		return mb_substr( (string) preg_replace( '/[^A-Za-z0-9_\-=.:]/', '', $value ), 0, 200 );
	}

	/**
	 * Replaces the document title.
	 *
	 * @param string $title Title from other filters.
	 */
	public function filter_document_title( $title ): string {
		$presentation = $this->current();

		if ( null === $presentation ) {
			return is_string( $title ) ? $title : '';
		}

		$seo_title = $presentation->title();

		return '' !== $seo_title ? esc_html( $seo_title ) : ( is_string( $title ) ? $title : '' );
	}

	/**
	 * Applies our directives to the core robots meta tag.
	 *
	 * @param array<string, mixed> $robots Directives from core and other filters.
	 * @return array<string, mixed>
	 */
	public function filter_wp_robots( $robots ): array {
		$robots       = is_array( $robots ) ? $robots : array();
		$presentation = $this->current();

		// Core already prints noindex,nofollow for "Discourage search engines".
		if ( null === $presentation || ! get_option( 'blog_public' ) ) {
			return $robots;
		}

		$ours = $presentation->robots();

		foreach ( array( array( 'index', 'noindex' ), array( 'follow', 'nofollow' ) ) as $pair ) {
			if ( isset( $ours[ $pair[1] ] ) || ! empty( $robots[ $pair[1] ] ) ) {
				unset( $robots[ $pair[0] ] );
				$robots[ $pair[1] ] = true;
			} else {
				$robots[ $pair[0] ] = true;
			}
		}

		if ( ! empty( $robots['noindex'] ) ) {
			unset( $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );
			return $robots;
		}

		foreach ( $ours as $directive => $value ) {
			if ( ! in_array( $directive, array( 'index', 'noindex', 'follow', 'nofollow' ), true ) ) {
				$robots[ $directive ] = $value;
			}
		}

		return $robots;
	}

	/**
	 * Prints description, canonical and fires `wpiseo_head` for other modules.
	 */
	public function render_head(): void {
		$presentation = $this->current();

		if ( null === $presentation || is_feed() ) {
			return;
		}

		$credit = Branding::credit();

		if ( $credit ) {
			echo "\n<!-- " . esc_html( Branding::name() ) . " -->\n";
		}

		if ( is_front_page() ) {
			foreach ( self::VERIFICATION as $service => $name ) {
				$code = (string) $this->plugin->options()->get( 'meta.verification.' . $service, '' );

				if ( '' !== $code ) {
					printf( '<meta name="%s" content="%s" />' . "\n", esc_attr( $name ), esc_attr( $code ) );
				}
			}
		}

		$description = $presentation->description();

		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		$canonical = $presentation->canonical();

		if ( '' !== $canonical && ! is_preview() ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
		}

		/**
		 * Fires inside the plugin's `<head>` block. Social and schema output hook here.
		 *
		 * @param Presentation $presentation Resolved values for the current page.
		 */
		do_action( 'wpiseo_head', $presentation );

		if ( $credit ) {
			echo '<!-- / ' . esc_html( Branding::name() ) . " -->\n\n";
		}
	}

	/**
	 * Sends disabled author and date archives to the home page.
	 */
	public function redirect_disabled_archives(): void {
		$disabled = ( is_author() && self::archive( $this->plugin->options(), 'author' )['disabled'] )
			|| ( is_date() && self::archive( $this->plugin->options(), 'date' )['disabled'] );

		if ( $disabled && wp_safe_redirect( home_url( '/' ), 301, Branding::redirect_by() ) ) {
			exit;
		}
	}

	/**
	 * Adds the Search Appearance page.
	 *
	 * @param array<string, array<string, string>> $pages Pages.
	 * @return array<string, array<string, string>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['search-appearance'] = array(
			'title'      => __( 'Search Appearance', 'wpinterface-seo' ),
			'menu_title' => __( 'Search Appearance', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}

	/**
	 * Presentation for the main query, or null outside public pages.
	 */
	private function current(): ?Presentation {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return null;
		}

		$context = $this->plugin->context();

		return $context->is( Context::OTHER ) ? null : $this->plugin->presentation( $context );
	}

	/**
	 * Sanitizes a map of name => { title, description, noindex }.
	 *
	 * @param mixed $map Raw map.
	 * @return array<string, array<string, mixed>>
	 */
	private static function sanitize_group_map( $map ): array {
		if ( ! is_array( $map ) ) {
			return array();
		}

		$clean = array();

		foreach ( array_slice( $map, 0, 200, true ) as $name => $group ) {
			$name = sanitize_key( (string) $name );

			if ( '' !== $name && is_array( $group ) ) {
				$clean[ $name ] = self::sanitize_group( $group, array( 'title', 'description', 'noindex' ) );
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes one settings group, keeping only the given keys present in input.
	 *
	 * @param array<string, mixed> $group Raw group.
	 * @param string[]             $keys  Allowed keys.
	 * @return array<string, mixed>
	 */
	private static function sanitize_group( array $group, array $keys ): array {
		$clean = array();

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $group ) ) {
				continue;
			}

			switch ( $key ) {
				case 'title':
					$clean[ $key ] = sanitize_text_field( (string) $group[ $key ] );
					break;
				case 'description':
					$clean[ $key ] = sanitize_textarea_field( (string) $group[ $key ] );
					break;
				default:
					$clean[ $key ] = Sanitize::to_bool( $group[ $key ] );
			}
		}

		return $clean;
	}
}
