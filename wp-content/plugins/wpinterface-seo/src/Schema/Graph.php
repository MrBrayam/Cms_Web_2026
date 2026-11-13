<?php
/**
 * JSON-LD graph builder.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Schema;

use WP_Post;
use WP_User;
use WPInterface\SEO\Breadcrumbs\Trail;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Modules\Schema\SchemaModule;
use WPInterface\SEO\Options;
use WPInterface\SEO\Presentation\Presentation;
use WPInterface\SEO\Schema\Types;
use WPInterface\SEO\Support\Images;

defined( 'ABSPATH' ) || exit;

/**
 * Builds one connected schema.org graph per page: nodes reference each
 * other by `@id` (site → publisher → page → article → author).
 */
final class Graph {

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Resolved page values.
	 *
	 * @var Presentation
	 */
	private Presentation $presentation;

	/**
	 * Context.
	 *
	 * @var Context
	 */
	private Context $context;

	/**
	 * Home URL with trailing slash, the base of site-wide `@id`s.
	 *
	 * @var string
	 */
	private string $home;

	/**
	 * Memoised nodes that other nodes depend on.
	 *
	 * @var array<string, array<string, mixed>|null>
	 */
	private array $memo = array();

	/**
	 * Constructor.
	 *
	 * @param Options      $options      Settings.
	 * @param Presentation $presentation Resolved page values.
	 */
	public function __construct( Options $options, Presentation $presentation ) {
		$this->options      = $options;
		$this->presentation = $presentation;
		$this->context      = $presentation->context();
		$this->home         = trailingslashit( home_url( '/' ) );
	}

	/**
	 * The full JSON-LD document, or an empty array when there is nothing to print.
	 *
	 * @return array<string, mixed>
	 */
	public function build(): array {
		if ( $this->context->is( Context::OTHER ) ) {
			return array();
		}

		$nodes = array(
			'publisher'     => $this->publisher(),
			'website'       => $this->website(),
			'webpage'       => $this->webpage(),
			'primary_image' => $this->primary_image(),
			'breadcrumb'    => $this->breadcrumb(),
			'article'       => $this->article(),
			'author'        => $this->author(),
			'entity'        => $this->entity(),
		);

		$graph = array();

		foreach ( $nodes as $piece => $node ) {
			/**
			 * Filters one graph node before it is added. Return null to drop it.
			 *
			 * Dynamic part: publisher, website, webpage, primary_image, breadcrumb, article, author.
			 *
			 * @param array|null   $node         Node.
			 * @param Presentation $presentation Resolved page values.
			 */
			$node = apply_filters( "wpiseo_schema_{$piece}", $node, $this->presentation );

			if ( is_array( $node ) && array() !== $node ) {
				$graph = $this->add( $graph, $node );
			}
		}

		/**
		 * Filters the complete list of graph nodes.
		 *
		 * @param array        $graph        Nodes.
		 * @param Presentation $presentation Resolved page values.
		 */
		$graph = apply_filters( 'wpiseo_schema_graph', array_values( $graph ), $this->presentation );

		if ( ! is_array( $graph ) || array() === $graph ) {
			return array();
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);
	}

	/**
	 * Organization or Person that publishes the site.
	 *
	 * @return array<string, mixed>|null
	 */
	private function publisher(): ?array {
		if ( 'person' === $this->options->get( 'schema.site_type' ) ) {
			$user = get_userdata( (int) $this->options->get( 'schema.person', 0 ) );

			if ( ! $user instanceof WP_User ) {
				return null;
			}

			$person          = $this->person( $user );
			$person['@type'] = array( 'Person', 'Organization' );
			$person['logo']  = isset( $person['image']['@id'] ) ? array( '@id' => $person['image']['@id'] ) : null;

			return array_filter( $person );
		}

		$name = trim( (string) $this->options->get( 'schema.name', '' ) );
		$node = array(
			'@type'         => 'Organization',
			'@id'           => $this->publisher_id(),
			'name'          => '' !== $name ? $name : $this->site_name(),
			'alternateName' => trim( (string) $this->options->get( 'schema.alternate_name', '' ) ),
			'url'           => $this->home,
			'sameAs'        => $this->site_profiles(),
		);

		$logo = Images::attachment( (int) $this->options->get( 'schema.logo', 0 ) );

		if ( null !== $logo ) {
			$node['logo']  = $this->image_object( $logo, $this->home . '#logo', $node['name'] );
			$node['image'] = array( '@id' => $this->home . '#logo' );
		}

		return array_filter( $node );
	}

	/**
	 * WebSite node (no SearchAction: Google retired the sitelinks search box in 2024).
	 *
	 * @return array<string, mixed>
	 */
	private function website(): array {
		$alternate = trim( (string) $this->options->get( 'schema.alternate_name', '' ) );

		return array_filter(
			array(
				'@type'         => 'WebSite',
				'@id'           => $this->home . '#website',
				'url'           => $this->home,
				'name'          => $this->site_name(),
				'alternateName' => $alternate,
				'description'   => wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ),
				'publisher'     => $this->publisher_ref(),
				'inLanguage'    => $this->language(),
			)
		);
	}

	/**
	 * WebPage (or subtype) for the current URL.
	 *
	 * @return array<string, mixed>|null
	 */
	private function webpage(): ?array {
		$url = $this->presentation->url();

		if ( '' === $url || $this->context->is( Context::NOT_FOUND ) ) {
			return null;
		}

		$post  = $this->singular_post();
		$image = $this->presentation->primary_image();
		$node  = array(
			'@type'       => $this->page_type(),
			'@id'         => $url . '#webpage',
			'url'         => $url,
			'name'        => $this->presentation->title(),
			'isPartOf'    => array( '@id' => $this->home . '#website' ),
			'description' => $this->presentation->description(),
			'inLanguage'  => $this->language(),
		);

		if ( $image ) {
			$node['primaryImageOfPage'] = array( '@id' => $url . '#primaryimage' );
			$node['image']              = array( '@id' => $url . '#primaryimage' );
			$node['thumbnailUrl']       = $image['url'];
		}

		if ( $post ) {
			$node['datePublished'] = (string) get_post_time( 'c', true, $post );
			$node['dateModified']  = (string) get_post_modified_time( 'c', true, $post );
		}

		if ( $this->context->is( Context::FRONT_PAGE ) ) {
			$node['about'] = $this->publisher_ref();
		}

		// FAQ content makes the page itself an FAQPage with the questions as main entity.
		$stored = $post ? Meta::post( $post->ID, 'schema' ) : array();
		if ( is_array( $stored ) && 'FAQPage' === ( $stored['type'] ?? '' ) ) {
			$questions = Types::faq_questions( $stored );

			if ( $questions ) {
				$node['@type']      = 'FAQPage';
				$node['mainEntity'] = $questions;
			}
		}

		if ( $this->context->is( Context::AUTHOR ) && $this->context->user() ) {
			$node['mainEntity'] = array( '@id' => $this->person_id( (int) $this->context->user()->ID ) );
		}

		if ( null !== $this->breadcrumb() ) {
			$node['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
		}

		return array_filter( $node );
	}

	/**
	 * Primary image of a singular page.
	 *
	 * @return array<string, mixed>|null
	 */
	private function primary_image(): ?array {
		$image = $this->presentation->primary_image();
		$url   = $this->presentation->url();

		if ( null === $image || '' === $url || $this->context->is( Context::NOT_FOUND ) ) {
			return null;
		}

		return $this->image_object( $image, $url . '#primaryimage', '' );
	}

	/**
	 * BreadcrumbList.
	 *
	 * @return array<string, mixed>|null
	 */
	private function breadcrumb(): ?array {
		if ( ! array_key_exists( 'breadcrumb', $this->memo ) ) {
			$this->memo['breadcrumb'] = $this->build_breadcrumb();
		}

		return $this->memo['breadcrumb'];
	}

	/**
	 * Builds the BreadcrumbList node.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_breadcrumb(): ?array {
		$url = $this->presentation->url();

		if (
			'' === $url
			|| ! $this->options->get( 'schema.breadcrumbs', true )
			|| $this->context->is( Context::FRONT_PAGE, Context::NOT_FOUND, Context::SEARCH )
		) {
			return null;
		}

		$items = ( new Trail( $this->options, $this->context ) )->items();

		if ( count( $items ) < 2 ) {
			return null;
		}

		$elements = array();

		foreach ( $items as $index => $item ) {
			$element = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $item['label'],
			);

			if ( '' !== $item['url'] ) {
				$element['item'] = $item['url'];
			}

			$elements[] = $element;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#breadcrumb',
			'itemListElement' => $elements,
		);
	}

	/**
	 * Article (or subtype) for singular posts whose type has one.
	 *
	 * @return array<string, mixed>|null
	 */
	private function article(): ?array {
		if ( ! array_key_exists( 'article', $this->memo ) ) {
			$this->memo['article'] = $this->build_article();
		}

		return $this->memo['article'];
	}

	/**
	 * Builds the Article node.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_article(): ?array {
		$post = $this->singular_post();
		$type = $post ? $this->article_type( $post ) : 'none';

		if ( ! $post || 'none' === $type || post_password_required( $post ) || 'publish' !== $post->post_status ) {
			return null;
		}

		$url     = $this->presentation->url();
		$image   = $this->presentation->primary_image();
		$content = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $post->post_content ) ) );
		$node    = array(
			'@type'            => $type,
			'@id'              => $url . '#article',
			'isPartOf'         => array( '@id' => $url . '#webpage' ),
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			'headline'         => mb_substr( wp_strip_all_tags( get_the_title( $post ) ), 0, 110 ),
			'datePublished'    => (string) get_post_time( 'c', true, $post ),
			'dateModified'     => (string) get_post_modified_time( 'c', true, $post ),
			'publisher'        => $this->publisher_ref(),
			'wordCount'        => $this->word_count( $content ),
			'inLanguage'       => $this->language(),
		);

		if ( post_type_supports( $post->post_type, 'author' ) && get_userdata( (int) $post->post_author ) ) {
			$node['author'] = array( '@id' => $this->person_id( (int) $post->post_author ) );
		}

		if ( $image ) {
			$node['image']        = array( '@id' => $url . '#primaryimage' );
			$node['thumbnailUrl'] = $image['url'];
		}

		$keywords = get_the_terms( $post, 'post_tag' );
		if ( is_array( $keywords ) && $keywords ) {
			$node['keywords'] = array_values( wp_list_pluck( $keywords, 'name' ) );
		}

		$sections = get_the_terms( $post, 'category' );
		if ( is_array( $sections ) && $sections ) {
			$node['articleSection'] = array_values( wp_list_pluck( $sections, 'name' ) );
		}

		return array_filter( $node );
	}

	/**
	 * The extra entity described by a post (Recipe, Event, …).
	 *
	 * @return array<string, mixed>|null
	 */
	private function entity(): ?array {
		$post = $this->singular_post();

		if ( ! $post || post_password_required( $post ) ) {
			return null;
		}

		$stored = Meta::post( $post->ID, 'schema' );

		if ( ! is_array( $stored ) || empty( $stored['type'] ) ) {
			return null;
		}

		$url   = $this->presentation->url();
		$image = $this->presentation->primary_image();

		return Types::node(
			$post,
			$stored,
			array(
				'url'       => $url,
				'webpage'   => $url . '#webpage',
				'publisher' => $this->publisher_ref(),
				'author'    => post_type_supports( $post->post_type, 'author' ) && get_userdata( (int) $post->post_author ) ? array( '@id' => $this->person_id( (int) $post->post_author ) ) : null,
				'image'     => $image['url'] ?? '',
			)
		);
	}

	/**
	 * Person node for the article author or the author archive being viewed.
	 *
	 * @return array<string, mixed>|null
	 */
	private function author(): ?array {
		$user = null;

		if ( $this->context->is( Context::AUTHOR ) ) {
			$user = $this->context->user();
		} elseif ( null !== $this->article() || $this->entity_references_author() ) {
			$post = $this->singular_post();
			$user = $post ? get_userdata( (int) $post->post_author ) : null;
		}

		return $user instanceof WP_User ? $this->person( $user ) : null;
	}

	/**
	 * Whether the post's entity (Recipe, Product review) points at the author node.
	 */
	private function entity_references_author(): bool {
		$post = $this->singular_post();

		if ( ! $post ) {
			return false;
		}

		$stored = Meta::post( $post->ID, 'schema' );

		return is_array( $stored ) && in_array( $stored['type'] ?? '', array( 'Recipe', 'Product' ), true );
	}

	/**
	 * Person node for a user.
	 *
	 * @param WP_User $user User.
	 * @return array<string, mixed>
	 */
	private function person( WP_User $user ): array {
		$profiles = Meta::user( (int) $user->ID, 'social_profiles' );
		$same_as  = is_array( $profiles ) ? array_values( $profiles ) : array();

		if ( '' !== $user->user_url ) {
			array_unshift( $same_as, $user->user_url );
		}

		$node   = array(
			'@type'       => 'Person',
			'@id'         => $this->person_id( (int) $user->ID ),
			'name'        => $user->display_name,
			'url'         => get_author_posts_url( (int) $user->ID ),
			'description' => trim( wp_strip_all_tags( (string) get_the_author_meta( 'description', (int) $user->ID ) ) ),
			'sameAs'      => array_values( array_unique( $same_as ) ),
		);
		$avatar = get_option( 'show_avatars' ) ? get_avatar_url( $user->ID, array( 'size' => 256 ) ) : '';

		if ( is_string( $avatar ) && '' !== $avatar ) {
			$node['image'] = array(
				'@type'      => 'ImageObject',
				'@id'        => $this->home . '#/schema/person/image/' . $this->person_hash( (int) $user->ID ),
				'url'        => $avatar,
				'contentUrl' => $avatar,
				'caption'    => $user->display_name,
				'inLanguage' => $this->language(),
			);
		}

		return array_filter( $node );
	}

	/**
	 * ImageObject node.
	 *
	 * @param array<string, mixed> $image   Image data.
	 * @param string               $id      Node `@id`.
	 * @param string               $caption Fallback caption.
	 * @return array<string, mixed>
	 */
	private function image_object( array $image, string $id, string $caption ): array {
		$caption = '' !== (string) ( $image['caption'] ?? '' ) ? (string) $image['caption'] : ( '' !== (string) ( $image['alt'] ?? '' ) ? (string) $image['alt'] : $caption );

		return array_filter(
			array(
				'@type'      => 'ImageObject',
				'@id'        => $id,
				'url'        => $image['url'],
				'contentUrl' => $image['url'],
				'width'      => (int) ( $image['width'] ?? 0 ),
				'height'     => (int) ( $image['height'] ?? 0 ),
				'caption'    => $caption,
				'inLanguage' => $this->language(),
			)
		);
	}

	/**
	 * Schema type of the page.
	 *
	 * @return string
	 */
	private function page_type(): string {
		switch ( $this->context->type() ) {
			case Context::AUTHOR:
				return 'ProfilePage';
			case Context::SEARCH:
				return 'SearchResultsPage';
			case Context::TERM:
			case Context::POST_TYPE_ARCHIVE:
			case Context::DATE:
			case Context::HOME:
				return 'CollectionPage';
		}

		$post = $this->context->post();

		if ( $this->context->is( Context::FRONT_PAGE ) && ! $post ) {
			return 'CollectionPage';
		}

		if ( $post ) {
			$type = (string) Meta::post( $post->ID, 'schema_page_type' );

			if ( '' === $type ) {
				$type = (string) SchemaModule::post_type( $this->options, $post->post_type )['page_type'];
			}

			if ( in_array( $type, Fields::PAGE_TYPES, true ) ) {
				return $type;
			}
		}

		return 'WebPage';
	}

	/**
	 * Article type for a post (`none` when disabled).
	 *
	 * @param WP_Post $post Post.
	 */
	private function article_type( WP_Post $post ): string {
		$type = (string) Meta::post( $post->ID, 'schema_article_type' );

		if ( '' === $type ) {
			$type = (string) SchemaModule::post_type( $this->options, $post->post_type )['article_type'];
		}

		return in_array( $type, Fields::ARTICLE_TYPES, true ) ? $type : 'none';
	}

	/**
	 * The queried post on singular pages (including a static front page).
	 */
	private function singular_post(): ?WP_Post {
		return $this->context->is( Context::SINGULAR, Context::FRONT_PAGE ) ? $this->context->post() : null;
	}

	/**
	 * Reference to the publisher node.
	 *
	 * @return array<string, string>
	 */
	private function publisher_ref(): array {
		return array( '@id' => $this->publisher_id() );
	}

	/**
	 * `@id` of the publisher (organization or person).
	 */
	private function publisher_id(): string {
		if ( 'person' === $this->options->get( 'schema.site_type' ) ) {
			return $this->person_id( (int) $this->options->get( 'schema.person', 0 ) );
		}

		return $this->home . '#organization';
	}

	/**
	 * `@id` of a person.
	 *
	 * @param int $user_id User ID.
	 */
	private function person_id( int $user_id ): string {
		return $this->home . '#/schema/person/' . $this->person_hash( $user_id );
	}

	/**
	 * Stable, non-enumerable identifier for a user.
	 *
	 * @param int $user_id User ID.
	 */
	private function person_hash( int $user_id ): string {
		return substr( hash( 'sha256', 'wpiseo-person-' . $user_id . '-' . $this->home ), 0, 32 );
	}

	/**
	 * Site-wide social profile URLs.
	 *
	 * @return list<string>
	 */
	private function site_profiles(): array {
		$profiles = $this->options->get( 'social.profiles', array() );
		$urls     = array();

		if ( is_array( $profiles ) ) {
			foreach ( $profiles as $key => $value ) {
				if ( 'other' === $key && is_array( $value ) ) {
					$urls = array_merge( $urls, $value );
				} elseif ( is_string( $value ) && '' !== $value ) {
					$urls[] = $value;
				}
			}
		}

		return array_values( array_unique( array_filter( $urls, 'is_string' ) ) );
	}

	/**
	 * Site name.
	 */
	private function site_name(): string {
		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Content language, e.g. `en-US`.
	 */
	private function language(): string {
		return (string) get_bloginfo( 'language' );
	}

	/**
	 * Word count that also handles scripts without spaces reasonably.
	 *
	 * @param string $text Plain text.
	 */
	private function word_count( string $text ): int {
		$count = preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text );

		return false === $count ? 0 : $count;
	}

	/**
	 * Adds a node, merging with an existing node of the same `@id`.
	 *
	 * @param array<string, array<string, mixed>> $graph Graph keyed by `@id`.
	 * @param array<string, mixed>                $node  Node.
	 * @return array<string, array<string, mixed>>
	 */
	private function add( array $graph, array $node ): array {
		$id = isset( $node['@id'] ) && is_string( $node['@id'] ) ? $node['@id'] : 'node-' . count( $graph );

		$graph[ $id ] = isset( $graph[ $id ] ) ? array_merge( $node, $graph[ $id ] ) : $node;

		return $graph;
	}
}
