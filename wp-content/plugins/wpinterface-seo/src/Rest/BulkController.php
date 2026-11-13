<?php
/**
 * Bulk editor and keyword lookup REST endpoints.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Rest;

use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Editor\Editor;
use WPInterface\SEO\Links\Indexer;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /wpinterface-seo/v1/bulk           List and save SEO titles/descriptions.
 * GET      /wpinterface-seo/v1/keywords/used  Other posts using a focus keyword.
 */
final class BulkController {

	/**
	 * Filters for published posts that need work (the dashboard links here).
	 */
	public const FILTERS = array( 'no_description', 'no_keyword', 'orphan', 'noindex', 'poor', 'unscored' );

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
	 * Registers routes.
	 */
	public function register_routes(): void {
		$permission = static fn() => current_user_can( Capabilities::EDIT_SEO ) && current_user_can( 'edit_posts' );

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $permission,
					'args'                => array(
						'post_type' => array(
							'description' => 'Post type, or empty for all types with the SEO panel.',
							'type'        => 'string',
							'default'     => '',
						),
						'show'      => array(
							'type'    => 'string',
							'enum'    => array_merge( array( '' ), self::FILTERS ),
							'default' => '',
						),
						'page'      => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'search'    => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_items' ),
					'permission_callback' => $permission,
					'args'                => array(
						'items' => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'object' ),
						),
					),
				),
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/keywords/used',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'keyword_used' ),
				'permission_callback' => static fn() => current_user_can( Capabilities::EDIT_SEO ),
				'args'                => array(
					'keyword' => array(
						'type'     => 'string',
						'required' => true,
					),
					'post_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Posts with their SEO fields and rendered values.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$post_type  = sanitize_key( (string) $request['post_type'] );
		$post_types = '' === $post_type ? Editor::post_types() : array_intersect( array( $post_type ), Editor::post_types() );
		$show       = (string) $request['show'];

		if ( array() === $post_types ) {
			return rest_ensure_response( array() );
		}

		$args = array(
			'post_type'      => array_values( $post_types ),
			'post_status'    => '' === $show ? array( 'publish', 'draft', 'pending', 'future', 'private' ) : 'publish',
			'posts_per_page' => (int) $request['per_page'],
			'paged'          => (int) $request['page'],
			's'              => (string) $request['search'],
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Authors and contributors only see their own posts, so totals and paging stay right.
		foreach ( $post_types as $type ) {
			$object = get_post_type_object( $type );

			if ( $object && ! current_user_can( $object->cap->edit_others_posts ) ) {
				$args['author'] = get_current_user_id();
				break;
			}
		}

		$where = '' === $show ? '' : $this->filter( $show, $args );

		if ( '' !== $where ) {
			$args['suppress_filters'] = false;
			$add_where                = static fn( string $sql ): string => $sql . $where;
			add_filter( 'posts_where', $add_where );
		}

		$query = new WP_Query( $args );

		if ( isset( $add_where ) ) {
			remove_filter( 'posts_where', $add_where );
		}

		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$presentation = $this->plugin->presentation( Context::for_post( $post ) );

			$items[] = array(
				'id'                   => $post->ID,
				'type'                 => $post->post_type,
				'title'                => wp_strip_all_tags( get_the_title( $post ) ),
				'status'               => $post->post_status,
				'link'                 => (string) get_permalink( $post ),
				'edit'                 => (string) get_edit_post_link( $post->ID, 'raw' ),
				'seo_title'            => (string) Meta::post( $post->ID, 'title' ),
				'description'          => (string) Meta::post( $post->ID, 'description' ),
				'noindex'              => (string) Meta::post( $post->ID, 'noindex' ),
				'rendered_title'       => $presentation->title(),
				'rendered_description' => $presentation->description(),
			);
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	/**
	 * Narrows the query to posts matching a filter: adds a meta query to
	 * $args, or returns SQL to append to the WHERE clause.
	 *
	 * @param string               $show Filter.
	 * @param array<string, mixed> $args Query arguments.
	 */
	private function filter( string $show, array &$args ): string {
		global $wpdb;

		$prefix = Fields::PREFIX;
		$meta   = array(
			'no_description' => array(
				'key'     => $prefix . 'description',
				'compare' => 'NOT EXISTS',
			),
			'no_keyword'     => array(
				'key'     => $prefix . 'focus_keywords',
				'compare' => 'NOT EXISTS',
			),
			'unscored'       => array(
				'key'     => $prefix . 'score',
				'compare' => 'NOT EXISTS',
			),
			'poor'           => array(
				'key'     => $prefix . 'score',
				'value'   => 40,
				'compare' => '<',
				'type'    => 'NUMERIC',
			),
		);

		if ( isset( $meta[ $show ] ) ) {
			$args['meta_query'] = array( $meta[ $show ] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			return '';
		}

		if ( 'orphan' === $show ) {
			return $wpdb->prepare(
				" AND {$wpdb->posts}.ID <> %d AND NOT EXISTS (SELECT 1 FROM %i l INNER JOIN {$wpdb->posts} src ON src.ID = l.post_id AND src.post_status = 'publish' WHERE l.target_post_id = {$wpdb->posts}.ID AND l.post_id <> {$wpdb->posts}.ID)",
				(int) get_option( 'page_on_front' ),
				Indexer::table()
			);
		}

		// Noindex: an explicit "noindex", or the type's default when not overridden with "index".
		$options = $this->plugin->options();
		$hidden  = array_values( array_filter( (array) $args['post_type'], static fn( string $type ): bool => (bool) MetaModule::post_type( $options, $type )['noindex'] ) );
		$by_type = array() === $hidden ? '0 = 1' : $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders built here.
			"{$wpdb->posts}.post_type IN (" . implode( ',', array_fill( 0, count( $hidden ), '%s' ) ) . ") AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = {$wpdb->posts}.ID AND m.meta_key = %s AND m.meta_value = 'index')",
			array_merge( $hidden, array( $prefix . 'noindex' ) )
		);

		$explicit = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = {$wpdb->posts}.ID AND m.meta_key = %s AND m.meta_value = 'noindex')", $prefix . 'noindex' );

		return " AND ({$by_type} OR {$explicit})";
	}

	/**
	 * Saves edited rows.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function save_items( WP_REST_Request $request ): WP_REST_Response {
		$saved   = 0;
		$skipped = 0;

		foreach ( array_slice( (array) $request['items'], 0, 100 ) as $item ) {
			$post_id = is_array( $item ) ? absint( $item['id'] ?? 0 ) : 0;

			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				++$skipped;
				continue;
			}

			$fields = array(
				'seo_title'   => 'title',
				'description' => 'description',
				'noindex'     => 'noindex',
			);

			foreach ( $fields as $input => $key ) {
				if ( array_key_exists( $input, $item ) ) {
					Meta::write( 'post', $post_id, $key, $item[ $input ] );
				}
			}

			++$saved;
		}

		return rest_ensure_response(
			array(
				'saved'   => $saved,
				'skipped' => $skipped,
			)
		);
	}

	/**
	 * Other posts that use a focus keyword.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function keyword_used( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$keyword = trim( sanitize_text_field( (string) $request['keyword'] ) );

		if ( '' === $keyword ) {
			return rest_ensure_response( array() );
		}

		// Focus keywords are stored as a serialized array: match the quoted value.
		$like = '%' . $wpdb->esc_like( '"' . $keyword . '"' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lookup across all posts; case-insensitive via the table collation.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND pm.meta_value LIKE %s AND pm.post_id <> %d AND p.post_status IN ('publish', 'future', 'draft', 'pending', 'private')
				ORDER BY p.post_date DESC LIMIT 5",
				Fields::PREFIX . 'focus_keywords',
				$like,
				(int) $request['post_id']
			)
		);

		$posts = array();

		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			if ( current_user_can( 'read_post', $id ) ) {
				$posts[] = array(
					'id'    => $id,
					'title' => wp_strip_all_tags( get_the_title( $id ) ),
					'edit'  => (string) get_edit_post_link( $id, 'raw' ),
				);
			}
		}

		return rest_ensure_response( $posts );
	}
}
