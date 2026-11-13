<?php
/**
 * Internal linking module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Links;

use WP_Post;
use WP_REST_Request;
use WP_REST_Server;
use WPInterface\SEO\Editor\Editor;
use WPInterface\SEO\Links\Indexer;
use WPInterface\SEO\Links\Suggestions;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Rest\SettingsController;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the link index current, shows link counts in post lists and serves
 * link suggestions to the editor.
 */
final class LinksModule extends Module {

	public const REBUILD_OPTION = 'wpiseo_links_rebuild';
	public const REBUILD_HOOK   = 'wpiseo_links_rebuild';

	/**
	 * Link index.
	 *
	 * @var Indexer
	 */
	private Indexer $indexer;

	/**
	 * Counts for the posts on the current list screen.
	 *
	 * @var array<int, array{internal: int, external: int, inbound: int}>|null
	 */
	private ?array $counts = null;

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		$this->indexer = new Indexer();

		add_action( 'save_post', array( $this, 'post_saved' ), 20, 2 );
		add_action( 'deleted_post', array( $this->indexer, 'forget' ) );
		add_action( self::REBUILD_HOOK, array( $this, 'rebuild_step' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'hook_list_tables' ) );
			add_action( 'admin_init', array( $this, 'maybe_schedule_rebuild' ) );
		}
	}

	/**
	 * Re-indexes a post when it is saved (also removes links of unpublished posts).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function post_saved( $post_id, $post ): void {
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$this->indexer->index( $post );
	}

	/**
	 * Schedules the background rebuild while one is pending.
	 */
	public function maybe_schedule_rebuild(): void {
		if ( false !== get_option( self::REBUILD_OPTION ) && ! wp_next_scheduled( self::REBUILD_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::REBUILD_HOOK );
		}
	}

	/**
	 * Processes one batch of the background rebuild.
	 */
	public function rebuild_step(): void {
		$state = get_option( self::REBUILD_OPTION );

		if ( ! is_array( $state ) ) {
			return;
		}

		$last = $this->indexer->rebuild_batch( (int) ( $state['last_id'] ?? 0 ) );

		if ( null === $last ) {
			delete_option( self::REBUILD_OPTION );
			return;
		}

		update_option( self::REBUILD_OPTION, array( 'last_id' => $last ), false );
		wp_schedule_single_event( time() + 15, self::REBUILD_HOOK );
	}

	/**
	 * Adds the list-table column for SEO-enabled post types.
	 */
	public function hook_list_tables(): void {
		foreach ( Editor::post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'columns' ), 20 );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Adds the "Links" column after the SEO columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns  = is_array( $columns ) ? $columns : array();
		$ours     = array( 'wpiseo-links' => __( 'Links', 'wpinterface-seo' ) );
		$position = array_search( 'date', array_keys( $columns ), true );

		if ( false === $position ) {
			return array_merge( $columns, $ours );
		}

		return array_merge( array_slice( $columns, 0, (int) $position, true ), $ours, array_slice( $columns, (int) $position, null, true ) );
	}

	/**
	 * Prints "internal out / inbound" counts.
	 *
	 * @param string $column  Column ID.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ): void {
		if ( 'wpiseo-links' !== $column ) {
			return;
		}

		if ( null === $this->counts ) {
			global $wp_query;

			$ids          = isset( $wp_query->posts ) ? wp_list_pluck( (array) $wp_query->posts, 'ID' ) : array();
			$this->counts = $this->indexer->counts( array_map( 'intval', $ids ) );
		}

		$counts = $this->counts[ (int) $post_id ] ?? $this->indexer->counts( array( (int) $post_id ) )[ (int) $post_id ];

		printf(
			'<span title="%1$s">%2$d ↗</span> <span title="%3$s">%4$d ↙</span>',
			esc_attr__( 'Internal links in this post', 'wpinterface-seo' ),
			(int) $counts['internal'],
			esc_attr__( 'Posts linking here', 'wpinterface-seo' ),
			(int) $counts['inbound']
		);
	}

	/**
	 * REST: link data for a post, suggestions, rebuild.
	 */
	public function register_routes(): void {
		$can_edit = static fn( WP_REST_Request $request ): bool => current_user_can( Capabilities::EDIT_SEO ) && current_user_can( 'edit_post', (int) $request['post_id'] );

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/links/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'post_links' ),
				'permission_callback' => $can_edit,
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/links/suggest',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					return rest_ensure_response(
						( new Suggestions( $this->indexer ) )->for_post(
							(int) $request['post_id'],
							(string) $request['content'],
							array_map( 'strval', (array) $request['keywords'] )
						)
					);
				},
				'permission_callback' => $can_edit,
				'args'                => array(
					'post_id'  => array(
						'type'     => 'integer',
						'required' => true,
					),
					'content'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'keywords' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/links/rebuild',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function () {
					update_option( self::REBUILD_OPTION, array( 'last_id' => 0 ), false );
					wp_clear_scheduled_hook( self::REBUILD_HOOK );
					wp_schedule_single_event( time(), self::REBUILD_HOOK );

					return rest_ensure_response( array( 'scheduled' => true ) );
				},
				'permission_callback' => static fn() => current_user_can( Capabilities::MANAGE_SETTINGS ),
			)
		);
	}

	/**
	 * Counts and posts linking to a post.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function post_links( WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['post_id'];
		$inbound = array();

		foreach ( $this->indexer->inbound( $post_id ) as $id ) {
			if ( current_user_can( 'read_post', $id ) ) {
				$inbound[] = array(
					'id'    => $id,
					'title' => wp_strip_all_tags( get_the_title( $id ) ),
					'edit'  => (string) get_edit_post_link( $id, 'raw' ),
				);
			}
		}

		return rest_ensure_response(
			array(
				'counts'     => $this->indexer->counts( array( $post_id ) )[ $post_id ],
				'inbound'    => $inbound,
				'rebuilding' => false !== get_option( self::REBUILD_OPTION ),
			)
		);
	}
}
