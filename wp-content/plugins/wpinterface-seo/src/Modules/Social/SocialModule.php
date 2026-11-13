<?php
/**
 * Social module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Social;

use WP_User;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Presentation\Presentation;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Open Graph (Facebook, LinkedIn, WhatsApp, …) and X card tags.
 */
final class SocialModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		$profiles = array_fill_keys( Fields::NETWORKS, '' );

		$profiles['other'] = array();

		return array(
			'opengraph'       => true,
			'twitter'         => true,
			'twitter_card'    => 'summary_large_image',
			'default_image'   => 0,
			'facebook_app_id' => '',
			'profiles'        => $profiles,
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
		$raw      = is_array( $values['profiles'] ?? null ) ? $values['profiles'] : array();
		$profiles = array_merge( array_fill_keys( Fields::NETWORKS, '' ), Fields::sanitize_profiles( $raw ) );
		$other    = array();

		foreach ( is_array( $raw['other'] ?? null ) ? $raw['other'] : array() as $url ) {
			$url = is_string( $url ) ? Fields::sanitize_url( $url ) : '';
			if ( '' !== $url && ! in_array( $url, $other, true ) ) {
				$other[] = $url;
			}
		}

		$profiles['other'] = array_slice( $other, 0, 20 );

		return array(
			'opengraph'       => Sanitize::to_bool( $values['opengraph'] ?? $defaults['opengraph'] ),
			'twitter'         => Sanitize::to_bool( $values['twitter'] ?? $defaults['twitter'] ),
			'twitter_card'    => 'summary' === ( $values['twitter_card'] ?? '' ) ? 'summary' : 'summary_large_image',
			'default_image'   => absint( $values['default_image'] ?? 0 ),
			'facebook_app_id' => preg_replace( '/[^0-9]/', '', (string) ( $values['facebook_app_id'] ?? '' ) ),
			'profiles'        => $profiles,
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		if ( $this->plugin->output_enabled() ) {
			add_action( 'wpiseo_head', array( $this, 'render' ), 20 );
		}

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 20 );
		}
	}

	/**
	 * Prints the tags.
	 *
	 * @param Presentation $presentation Resolved page values.
	 */
	public function render( $presentation ): void {
		if ( ! $presentation instanceof Presentation ) {
			return;
		}

		$tags = $this->tags( $presentation );

		/**
		 * Filters social meta tags before output.
		 *
		 * @param list<array{0: string, 1: string, 2: string}> $tags         [ attribute, name, content ] triples.
		 * @param Presentation                                 $presentation Resolved page values.
		 */
		$tags = apply_filters( 'wpiseo_social_tags', $tags, $presentation );

		foreach ( (array) $tags as $tag ) {
			if ( is_array( $tag ) && 3 === count( $tag ) && '' !== (string) $tag[2] ) {
				printf( '<meta %s="%s" content="%s" />' . "\n", esc_attr( (string) $tag[0] ), esc_attr( (string) $tag[1] ), esc_attr( (string) $tag[2] ) );
			}
		}
	}

	/**
	 * Builds the tag list.
	 *
	 * @param Presentation $presentation Resolved page values.
	 * @return list<array{0: string, 1: string, 2: string}>
	 */
	public function tags( Presentation $presentation ): array {
		$options = $this->plugin->options();
		$context = $presentation->context();
		$tags    = array();

		if ( $options->get( 'social.opengraph', true ) ) {
			$type   = $this->og_type( $context );
			$locale = get_locale();
			$tags[] = array( 'property', 'og:locale', preg_match( '/^[a-z]{2,3}_[A-Z]{2}$/', $locale ) ? $locale : '' );
			$tags[] = array( 'property', 'og:type', $type );
			$tags[] = array( 'property', 'og:title', $presentation->og_title() );
			$tags[] = array( 'property', 'og:description', $presentation->og_description() );
			$tags[] = array( 'property', 'og:url', $presentation->url() );
			$tags[] = array( 'property', 'og:site_name', wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );

			if ( 'article' === $type ) {
				$post   = $context->post();
				$tags[] = array( 'property', 'article:publisher', (string) $options->get( 'social.profiles.facebook', '' ) );

				if ( $post ) {
					$tags[] = array( 'property', 'article:author', $this->author_profile( (int) $post->post_author, 'facebook' ) );
					$tags[] = array( 'property', 'article:published_time', (string) get_post_time( 'c', true, $post ) );
					$tags[] = array( 'property', 'article:modified_time', (string) get_post_modified_time( 'c', true, $post ) );
				}
			}

			$image = $presentation->og_image();

			if ( $image ) {
				$tags[] = array( 'property', 'og:image', (string) $image['url'] );
				$tags[] = array( 'property', 'og:image:width', $image['width'] ? (string) $image['width'] : '' );
				$tags[] = array( 'property', 'og:image:height', $image['height'] ? (string) $image['height'] : '' );
				$tags[] = array( 'property', 'og:image:alt', (string) ( $image['alt'] ?? '' ) );
				$tags[] = array( 'property', 'og:image:type', (string) ( $image['type'] ?? '' ) );
			}

			$tags[] = array( 'property', 'fb:app_id', (string) $options->get( 'social.facebook_app_id', '' ) );
		}

		if ( $options->get( 'social.twitter', true ) ) {
			$tags = array_merge( $tags, $this->twitter_tags( $presentation ) );
		}

		return $tags;
	}

	/**
	 * X card tags. Title, description and image are only repeated when they
	 * differ from Open Graph, because X falls back to `og:*`.
	 *
	 * @param Presentation $presentation Resolved page values.
	 * @return list<array{0: string, 1: string, 2: string}>
	 */
	private function twitter_tags( Presentation $presentation ): array {
		$options  = $this->plugin->options();
		$og       = (bool) $options->get( 'social.opengraph', true );
		$image    = $presentation->twitter_image();
		$og_image = $presentation->og_image();
		$card     = $image && 'summary' !== $options->get( 'social.twitter_card' ) ? 'summary_large_image' : 'summary';
		$tags     = array( array( 'name', 'twitter:card', $card ) );

		$title = $presentation->twitter_title();
		if ( ! $og || $title !== $presentation->og_title() ) {
			$tags[] = array( 'name', 'twitter:title', $title );
		}

		$description = $presentation->twitter_description();
		if ( ! $og || $description !== $presentation->og_description() ) {
			$tags[] = array( 'name', 'twitter:description', $description );
		}

		if ( $image && ( ! $og || ! $og_image || $image['url'] !== $og_image['url'] ) ) {
			$tags[] = array( 'name', 'twitter:image', (string) $image['url'] );
			$tags[] = array( 'name', 'twitter:image:alt', (string) ( $image['alt'] ?? '' ) );
		}

		$tags[] = array( 'name', 'twitter:site', self::x_handle( (string) $options->get( 'social.profiles.x', '' ) ) );

		$post = $presentation->context()->post();

		if ( $post && $presentation->context()->is( Context::SINGULAR ) && post_type_supports( $post->post_type, 'author' ) ) {
			$tags[] = array( 'name', 'twitter:creator', self::x_handle( $this->author_profile( (int) $post->post_author, 'x' ) ) );

			$author = get_userdata( (int) $post->post_author );

			if ( $author instanceof WP_User ) {
				$minutes = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ) / 200 ) );

				$tags[] = array( 'name', 'twitter:label1', __( 'Written by', 'wpinterface-seo' ) );
				$tags[] = array( 'name', 'twitter:data1', $author->display_name );
				$tags[] = array( 'name', 'twitter:label2', __( 'Est. reading time', 'wpinterface-seo' ) );
				/* translators: %d: number of minutes. */
				$tags[] = array( 'name', 'twitter:data2', sprintf( _n( '%d minute', '%d minutes', $minutes, 'wpinterface-seo' ), $minutes ) );
			}
		}

		return $tags;
	}

	/**
	 * Open Graph type for the context.
	 *
	 * @param Context $context Context.
	 */
	private function og_type( Context $context ): string {
		if ( $context->is( Context::AUTHOR ) ) {
			return 'profile';
		}

		return $context->is( Context::SINGULAR ) ? 'article' : 'website';
	}

	/**
	 * A user's profile URL for a network.
	 *
	 * @param int    $user_id User ID.
	 * @param string $network Network key.
	 */
	private function author_profile( int $user_id, string $network ): string {
		$profiles = Meta::user( $user_id, 'social_profiles' );

		return is_array( $profiles ) ? (string) ( $profiles[ $network ] ?? '' ) : '';
	}

	/**
	 * `@handle` from an X/Twitter profile URL.
	 *
	 * @param string $url Profile URL.
	 */
	public static function x_handle( string $url ): string {
		if ( preg_match( '#^https?://(?:www\.)?(?:x|twitter)\.com/@?([A-Za-z0-9_]{1,15})/?$#i', $url, $matches ) ) {
			return '@' . $matches[1];
		}

		return '';
	}

	/**
	 * Adds the Social settings page.
	 *
	 * @param array<string, array<string, string>> $pages Pages.
	 * @return array<string, array<string, string>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['social'] = array(
			'title'      => __( 'Social', 'wpinterface-seo' ),
			'menu_title' => __( 'Social', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}
}
