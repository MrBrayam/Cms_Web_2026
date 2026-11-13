<?php
/**
 * Google Search Console module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\SearchConsole;

use WP_Post;
use WPInterface\SEO\Admin\Admin;
use WPInterface\SEO\Editor\Editor;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Rest\SearchConsoleController;
use WPInterface\SEO\SearchConsole\Api;
use WPInterface\SEO\SearchConsole\Auth;
use WPInterface\SEO\SearchConsole\Store;
use WPInterface\SEO\SearchConsole\Sync;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Search performance inside WordPress. Nothing connects to Google until a
 * site manager clicks "Connect".
 */
final class SearchConsoleModule extends Module {

	public const DAILY_HOOK = 'wpiseo_gsc_daily';

	private const NOTICE_TRANSIENT = 'wpiseo_gsc_notice_';

	/**
	 * OAuth.
	 *
	 * @var Auth
	 */
	private Auth $auth;

	/**
	 * Clicks for the posts on the current list screen, keyed by URL hash.
	 *
	 * @var array<string, array{clicks: int, impressions: int}>|null
	 */
	private ?array $clicks = null;

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'keywords'         => array(),
			'backfill_days'    => 180,
			'decay_threshold'  => 30,
			'decay_min_clicks' => 10,
			'columns'          => true,
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
		$keywords = array();

		foreach ( (array) ( $values['keywords'] ?? array() ) as $keyword ) {
			$keyword = mb_strtolower( trim( sanitize_text_field( is_scalar( $keyword ) ? (string) $keyword : '' ) ) );

			if ( '' !== $keyword && ! in_array( $keyword, $keywords, true ) ) {
				$keywords[] = mb_substr( $keyword, 0, 100 );
			}
		}

		return array(
			'keywords'         => array_slice( $keywords, 0, 100 ),
			'backfill_days'    => max( 30, min( 480, absint( $values['backfill_days'] ?? $defaults['backfill_days'] ) ) ),
			'decay_threshold'  => max( 5, min( 95, absint( $values['decay_threshold'] ?? $defaults['decay_threshold'] ) ) ),
			'decay_min_clicks' => max( 1, min( 100000, absint( $values['decay_min_clicks'] ?? $defaults['decay_min_clicks'] ) ) ),
			'columns'          => Sanitize::to_bool( $values['columns'] ?? $defaults['columns'] ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		$this->auth = new Auth();

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( Sync::HOOK, array( $this, 'run_sync' ) );
		add_action( self::DAILY_HOOK, array( Sync::class, 'schedule' ) );
		add_filter( 'wpiseo_editor_config', array( $this, 'editor_config' ) );
		add_filter( 'wpiseo_abilities', array( $this, 'abilities' ) );
		add_action( 'wpiseo_settings_updated', array( $this, 'keywords_changed' ), 10, 2 );

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 15 );
			add_action( 'admin_init', array( $this, 'handle_callback' ) );
			add_action( 'admin_init', array( $this, 'schedule_daily' ) );
			add_action( 'admin_init', array( $this, 'hook_list_tables' ) );
		}
	}

	/**
	 * REST routes.
	 */
	public function register_routes(): void {
		( new SearchConsoleController( $this->plugin->options(), $this->auth, new Store() ) )->register_routes();
	}

	/**
	 * Runs one sync step (WP-Cron).
	 */
	public function run_sync(): void {
		( new Sync( $this->plugin->options(), $this->auth, new Api( $this->auth ), new Store() ) )->run();
	}

	/**
	 * Keeps the daily sync scheduled while connected.
	 */
	public function schedule_daily(): void {
		$ready = $this->auth->is_connected() && '' !== (string) ( $this->auth->data()['property'] ?? '' );

		if ( $ready && ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK );
		} elseif ( ! $ready && wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_clear_scheduled_hook( self::DAILY_HOOK );
		}
	}

	/**
	 * Re-syncs when the tracked keyword list was saved.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 * @param array<string, mixed> $input    Partial input that was saved.
	 */
	public function keywords_changed( $settings, $input = array() ): void {
		if ( is_array( $input ) && isset( $input['search_console']['keywords'] ) && $this->auth->is_connected() ) {
			Sync::schedule( 1 );
		}
	}

	/**
	 * Completes the OAuth flow when Google or the broker sends the user back.
	 */
	public function handle_callback(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth callback; protected by the state parameter.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( Admin::MENU_SLUG . '-search-console' !== $page || ! isset( $_GET['state'] ) || ( ! isset( $_GET['code'] ) && ! isset( $_GET['error'] ) ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return;
		}

		if ( isset( $_GET['error'] ) ) {
			$result = new \WP_Error( 'wpiseo_gsc_denied', __( 'The connection was cancelled in Google.', 'wpinterface-seo' ) );
		} else {
			$result = $this->auth->finish( sanitize_text_field( wp_unslash( $_GET['code'] ) ), sanitize_text_field( wp_unslash( $_GET['state'] ) ) );
		}
		// phpcs:enable

		set_transient(
			self::NOTICE_TRANSIENT . get_current_user_id(),
			is_wp_error( $result ) ? array(
				'status'  => 'error',
				'message' => $result->get_error_message(),
			) : array(
				'status'  => 'success',
				'message' => __( 'Connected to Google Search Console. Choose your property below.', 'wpinterface-seo' ),
			),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( Auth::callback_url() );
		exit;
	}

	/**
	 * One-time notice after the OAuth flow (read by the admin app).
	 *
	 * @return array<string, string>|null
	 */
	public static function pop_notice(): ?array {
		$key    = self::NOTICE_TRANSIENT . get_current_user_id();
		$notice = get_transient( $key );

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Adds the "Clicks" column.
	 */
	public function hook_list_tables(): void {
		if ( ! $this->setting( 'search_console', 'columns', true ) || ! $this->auth->is_connected() || ! current_user_can( Capabilities::VIEW_ANALYTICS ) ) {
			return;
		}

		foreach ( Editor::post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'columns' ), 30 );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Adds the column before the date.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns  = is_array( $columns ) ? $columns : array();
		$ours     = array( 'wpiseo-gsc' => __( 'Clicks (28 days)', 'wpinterface-seo' ) );
		$position = array_search( 'date', array_keys( $columns ), true );

		if ( false === $position ) {
			return array_merge( $columns, $ours );
		}

		return array_merge( array_slice( $columns, 0, (int) $position, true ), $ours, array_slice( $columns, (int) $position, null, true ) );
	}

	/**
	 * Prints clicks for a post.
	 *
	 * @param string $column  Column ID.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ): void {
		if ( 'wpiseo-gsc' !== $column ) {
			return;
		}

		if ( null === $this->clicks ) {
			global $wp_query;

			$urls  = array();
			$posts = isset( $wp_query->posts ) ? (array) $wp_query->posts : array();

			foreach ( $posts as $post ) {
				if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
					$urls[] = (string) get_permalink( $post );
				}
			}

			$store        = new Store();
			$end          = $store->last_date( Store::SITE );
			$end          = '' !== $end ? $end : gmdate( 'Y-m-d' );
			$this->clicks = $store->clicks_for( $urls, gmdate( 'Y-m-d', strtotime( $end . ' -27 days' ) ), $end );
		}

		$url  = 'publish' === get_post_status( (int) $post_id ) ? (string) get_permalink( (int) $post_id ) : '';
		$data = '' !== $url ? ( $this->clicks[ Store::hash( $url ) ] ?? null ) : null;

		if ( null === $data ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}

		printf(
			'<span title="%1$s">%2$s</span>',
			/* translators: %s: number of impressions. */
			esc_attr( sprintf( __( '%s impressions', 'wpinterface-seo' ), number_format_i18n( $data['impressions'] ) ) ),
			esc_html( number_format_i18n( $data['clicks'] ) )
		);
	}

	/**
	 * Editor config: whether the performance panel can load data.
	 *
	 * @param array<string, mixed> $config Config.
	 * @return array<string, mixed>
	 */
	public function editor_config( $config ): array {
		$config        = is_array( $config ) ? $config : array();
		$config['gsc'] = array(
			'connected' => $this->auth->is_connected() && '' !== (string) ( $this->auth->data()['property'] ?? '' ),
			'pageUrl'   => current_user_can( Capabilities::VIEW_ANALYTICS ) ? Auth::callback_url() : '',
		);

		return $config;
	}

	/**
	 * Adds a read-only search performance ability.
	 *
	 * @param array<string, array<string, mixed>> $abilities Abilities.
	 * @return array<string, array<string, mixed>>
	 */
	public function abilities( $abilities ): array {
		$abilities = is_array( $abilities ) ? $abilities : array();

		$abilities['wpinterface-seo/get-search-performance'] = array(
			'label'               => __( 'Get search performance', 'wpinterface-seo' ),
			'description'         => __( 'Returns Google Search clicks, impressions, CTR and average position for the site or one post over the last 7, 28 or 90 days, from data synced from Search Console, with the previous period for comparison.', 'wpinterface-seo' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Optional. Omit for the whole site.', 'wpinterface-seo' ),
					),
					'days'    => array(
						'type'    => 'integer',
						'enum'    => array( 7, 28, 90 ),
						'default' => 28,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => array( $this, 'performance_ability' ),
			'permission_callback' => static function ( $input = array() ): bool {
				$post_id = is_array( $input ) ? (int) ( $input['post_id'] ?? 0 ) : 0;

				return $post_id ? current_user_can( 'edit_post', $post_id ) && current_user_can( Capabilities::EDIT_SEO ) : current_user_can( Capabilities::VIEW_ANALYTICS );
			},
			'annotations'         => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);

		return $abilities;
	}

	/**
	 * Executes the performance ability.
	 *
	 * @param mixed $input Input.
	 * @return array<string, mixed>
	 */
	public function performance_ability( $input = array() ): array {
		$input   = is_array( $input ) ? $input : array();
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'days', (int) ( $input['days'] ?? 28 ) );

		$controller = new SearchConsoleController( $this->plugin->options(), $this->auth, new Store() );

		if ( ! empty( $input['post_id'] ) ) {
			$request->set_param( 'id', (int) $input['post_id'] );
			$response = $controller->post_report( $request );
		} else {
			$response = $controller->report( $request );
		}

		return is_wp_error( $response ) ? array( 'error' => $response->get_error_message() ) : (array) $response->get_data();
	}

	/**
	 * Adds the Search Performance page.
	 *
	 * @param array<string, array<string, mixed>> $pages Pages.
	 * @return array<string, array<string, mixed>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['search-console'] = array(
			'title'      => __( 'Search Performance', 'wpinterface-seo' ),
			'menu_title' => __( 'Search Performance', 'wpinterface-seo' ),
			'capability' => Capabilities::VIEW_ANALYTICS,
		);

		return $pages;
	}
}
