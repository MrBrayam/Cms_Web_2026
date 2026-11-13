<?php
/**
 * SEO abilities for the WordPress Abilities API.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Abilities;

use WP_Error;
use WP_Post;
use WPInterface\SEO\Analysis\ServerAnalysis;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Crawl\IndexNow;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Redirects\Repository;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Ability definitions. Each one checks capabilities itself, so exposing them
 * to REST or MCP clients never grants more than the user's role allows.
 */
final class SeoAbilities {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Ability name => arguments (without category and meta, added on registration).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function definitions(): array {
		$post_input = array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'Post, page or other content ID.', 'wpinterface-seo' ),
					'minimum'     => 1,
				),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);

		$definitions = array(
			'wpinterface-seo/get-seo-data'    => array(
				'label'               => __( 'Get SEO data', 'wpinterface-seo' ),
				'description'         => __( 'Returns the SEO title, meta description, focus keywords, robots directives and canonical URL of a post as they appear in search results, plus the raw values set by the editor.', 'wpinterface-seo' ),
				'input_schema'        => $post_input,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'get_seo_data' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'annotations'         => self::readonly(),
			),
			'wpinterface-seo/update-seo-data' => array(
				'label'               => __( 'Update SEO data', 'wpinterface-seo' ),
				'description'         => __( 'Sets the SEO title, meta description, focus keywords, canonical URL or indexing of a post. Omitted fields are left unchanged; an empty string resets a field to its default template.', 'wpinterface-seo' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'        => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'          => array(
							'type'        => 'string',
							'description' => sprintf(
								/* translators: 1: %sep% variable, 2: %sitename% variable. */
								__( 'SEO title. Template variables such as %1$s and %2$s are allowed.', 'wpinterface-seo' ),
								'%sep%',
								'%sitename%'
							),
						),
						'description'    => array(
							'type'        => 'string',
							'description' => __( 'Meta description, ideally 120–160 characters.', 'wpinterface-seo' ),
						),
						'focus_keywords' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'canonical'      => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'noindex'        => array(
							'type' => 'string',
							'enum' => array( '', 'index', 'noindex' ),
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'update_seo_data' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'wpinterface-seo/analyze-post'    => array(
				'label'               => __( 'Analyze post SEO', 'wpinterface-seo' ),
				'description'         => __( 'Runs basic SEO checks on a post (title and description length, focus keyword placement, content length, image alt text, internal links, indexing) and returns a 0–100 score with recommendations.', 'wpinterface-seo' ),
				'input_schema'        => $post_input,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'analyze_post' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'annotations'         => self::readonly(),
			),
		);

		if ( $this->plugin->modules()->get( 'redirects' ) ) {
			$definitions['wpinterface-seo/list-redirects']  = array(
				'label'               => __( 'List redirects', 'wpinterface-seo' ),
				'description'         => __( 'Lists redirect rules, optionally filtered by a search term.', 'wpinterface-seo' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'   => array( 'type' => 'string' ),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static fn( $input = array() ) => ( new Repository() )->query( is_array( $input ) ? $input : array() ),
				'permission_callback' => static fn() => current_user_can( Capabilities::MANAGE_REDIRECTS ),
				'annotations'         => self::readonly(),
			);
			$definitions['wpinterface-seo/create-redirect'] = array(
				'label'               => __( 'Create redirect', 'wpinterface-seo' ),
				'description'         => __( 'Creates a redirect from an old URL of this site to a new URL (301 by default). Use status 410 for content that was removed for good.', 'wpinterface-seo' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source' => array( 'type' => 'string' ),
						'target' => array( 'type' => 'string' ),
						'status' => array(
							'type'    => 'integer',
							'enum'    => Repository::STATUSES,
							'default' => 301,
						),
					),
					'required'             => array( 'source' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'create_redirect' ),
				'permission_callback' => static fn() => current_user_can( Capabilities::MANAGE_REDIRECTS ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			);
		}

		if ( $this->plugin->options()->get( 'crawl.indexnow', false ) && $this->plugin->modules()->get( 'crawl' ) ) {
			$definitions['wpinterface-seo/submit-indexnow'] = array(
				'label'               => __( 'Submit URLs to IndexNow', 'wpinterface-seo' ),
				'description'         => __( 'Tells IndexNow search engines (Bing, Yandex and others) that pages of this site changed. Up to 100 URLs of this site.', 'wpinterface-seo' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'urls' => array(
							'type'     => 'array',
							'items'    => array(
								'type'   => 'string',
								'format' => 'uri',
							),
							'maxItems' => 100,
						),
					),
					'required'             => array( 'urls' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => fn( $input ) => ( new IndexNow( $this->plugin->options() ) )->submit( array_map( 'strval', (array) ( $input['urls'] ?? array() ) ) ),
				'permission_callback' => static fn() => current_user_can( Capabilities::MANAGE_SETTINGS ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			);
		}

		/**
		 * Filters the plugin's abilities before registration.
		 *
		 * @param array<string, array> $definitions Ability name => arguments (label, description, input_schema,
		 *                                          output_schema, execute_callback, permission_callback, annotations).
		 */
		return (array) apply_filters( 'wpiseo_abilities', $definitions );
	}

	/**
	 * Permission: SEO editing rights on the post.
	 *
	 * @param mixed $input Input.
	 */
	public function can_edit_post( $input = null ): bool {
		$post_id = is_array( $input ) ? (int) ( $input['post_id'] ?? 0 ) : 0;

		return $post_id > 0 && current_user_can( Capabilities::EDIT_SEO ) && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * SEO data of a post.
	 *
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_seo_data( $input ) {
		$post = $this->post( $input );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$presentation = $this->plugin->presentation( Context::for_post( $post ) );
		$robots       = array();

		foreach ( $presentation->robots() as $name => $value ) {
			$robots[] = true === $value ? (string) $name : $name . ':' . $value;
		}

		return array(
			'post_id'        => $post->ID,
			'url'            => (string) get_permalink( $post ),
			'title'          => $presentation->title(),
			'description'    => $presentation->description(),
			'canonical'      => $presentation->canonical(),
			'robots'         => implode( ', ', $robots ),
			'indexable'      => $presentation->is_indexable(),
			'focus_keywords' => array_values( (array) Meta::post( $post->ID, 'focus_keywords' ) ),
			'og_title'       => $presentation->og_title(),
			'og_image'       => (string) ( $presentation->og_image()['url'] ?? '' ),
			'raw'            => array(
				'title'       => (string) Meta::post( $post->ID, 'title' ),
				'description' => (string) Meta::post( $post->ID, 'description' ),
				'noindex'     => (string) Meta::post( $post->ID, 'noindex' ),
				'canonical'   => (string) Meta::post( $post->ID, 'canonical' ),
			),
		);
	}

	/**
	 * Updates SEO fields of a post.
	 *
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_seo_data( $input ) {
		$post = $this->post( $input );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		foreach ( array( 'title', 'description', 'focus_keywords', 'canonical', 'noindex' ) as $key ) {
			if ( is_array( $input ) && array_key_exists( $key, $input ) ) {
				Meta::write( 'post', $post->ID, $key, $input[ $key ] );
			}
		}

		return $this->get_seo_data( array( 'post_id' => $post->ID ) );
	}

	/**
	 * Basic analysis.
	 *
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function analyze_post( $input ) {
		$post = $this->post( $input );

		return is_wp_error( $post ) ? $post : ServerAnalysis::analyze( $post );
	}

	/**
	 * Creates a redirect.
	 *
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_redirect( $input ) {
		$input      = is_array( $input ) ? $input : array();
		$repository = new Repository();
		$id         = $repository->create(
			array(
				'source' => (string) ( $input['source'] ?? '' ),
				'target' => (string) ( $input['target'] ?? '' ),
				'status' => (int) ( $input['status'] ?? 301 ),
				'origin' => 'manual',
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return (array) $repository->get( $id );
	}

	/**
	 * Post from input.
	 *
	 * @param mixed $input Input.
	 * @return WP_Post|WP_Error
	 */
	private function post( $input ) {
		$post = get_post( is_array( $input ) ? (int) ( $input['post_id'] ?? 0 ) : 0 );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'wpiseo_post_not_found', __( 'Post not found.', 'wpinterface-seo' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Read-only annotations.
	 *
	 * @return array<string, bool>
	 */
	private static function readonly(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}
