<?php
/**
 * Search Console REST endpoints.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Rest;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPInterface\SEO\Options;
use WPInterface\SEO\SearchConsole\Api;
use WPInterface\SEO\SearchConsole\Auth;
use WPInterface\SEO\SearchConsole\Store;
use WPInterface\SEO\SearchConsole\Sync;
use WPInterface\SEO\Sitemaps\Router;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * /wpinterface-seo/v1/gsc/*. Reports read the local tables only; connecting,
 * syncing, inspecting and sitemap actions call Google.
 */
final class SearchConsoleController {

	public const INSPECTION_META   = '_wpiseo_gsc_inspection';
	public const INSPECTION_OPTION = 'wpiseo_gsc_inspections';

	/**
	 * Google's daily URL Inspection quota per property.
	 */
	private const INSPECTION_LIMIT = 2000;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * OAuth.
	 *
	 * @var Auth
	 */
	private Auth $auth;

	/**
	 * Storage.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 * @param Auth    $auth    OAuth.
	 * @param Store   $store   Storage.
	 */
	public function __construct( Options $options, Auth $auth, Store $store ) {
		$this->options = $options;
		$this->auth    = $auth;
		$this->store   = $store;
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		$ns     = SettingsController::REST_NAMESPACE;
		$manage = static fn() => current_user_can( Capabilities::MANAGE_SETTINGS );
		$view   = static fn() => current_user_can( Capabilities::VIEW_ANALYTICS ) || current_user_can( Capabilities::MANAGE_SETTINGS );
		$days   = array(
			'type'    => 'integer',
			'enum'    => array( 7, 28, 90 ),
			'default' => 28,
		);

		register_rest_route(
			$ns,
			'/gsc/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $view,
			)
		);

		register_rest_route(
			$ns,
			'/gsc/connect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'connect' ),
				'permission_callback' => $manage,
				'args'                => array(
					'mode'          => array(
						'type'    => 'string',
						'enum'    => array( 'broker', 'own' ),
						'default' => 'broker',
					),
					'write'         => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'client_id'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'client_secret' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/gsc/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => $manage,
				'args'                => array(
					'delete_data' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/gsc/properties',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'properties' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_property' ),
					'permission_callback' => $manage,
					'args'                => array(
						'property' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/gsc/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/gsc/report',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'report' ),
				'permission_callback' => $view,
				'args'                => array( 'days' => $days ),
			)
		);

		register_rest_route(
			$ns,
			'/gsc/post/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'post_report' ),
				'permission_callback' => static fn( WP_REST_Request $request ) => current_user_can( Capabilities::EDIT_SEO ) && current_user_can( 'edit_post', (int) $request['id'] ),
				'args'                => array( 'days' => $days ),
			)
		);

		register_rest_route(
			$ns,
			'/gsc/inspect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'inspect' ),
				'permission_callback' => static fn( WP_REST_Request $request ) => current_user_can( Capabilities::EDIT_SEO ) && current_user_can( 'edit_post', (int) $request['post_id'] ),
				'args'                => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/gsc/sitemaps',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'sitemaps' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_sitemap' ),
					'permission_callback' => $manage,
				),
			)
		);
	}

	/**
	 * Connection and sync status.
	 */
	public function status(): WP_REST_Response {
		$data = $this->auth->data();

		return rest_ensure_response(
			array(
				'connected'   => $this->auth->is_connected(),
				'unreadable'  => $this->auth->is_unreadable(),
				'needsReauth' => 'reconnect' === ( $data['error'] ?? '' ),
				'mode'        => (string) ( $data['mode'] ?? '' ),
				'property'    => (string) ( $data['property'] ?? '' ),
				'canWrite'    => $this->auth->can_write(),
				'sync'        => Sync::state(),
				'hasData'     => '' !== $this->store->last_date( Store::SITE ),
				'brokerUrl'   => Auth::broker_url(),
				'callbackUrl' => Auth::callback_url(),
				'inspections' => $this->inspections_today(),
				'sitemapUrl'  => Router::index_url(),
			)
		);
	}

	/**
	 * Starts the OAuth flow.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function connect( WP_REST_Request $request ) {
		$url = $this->auth->start( (string) $request['mode'], (bool) $request['write'], (string) $request['client_id'], (string) $request['client_secret'] );

		return is_wp_error( $url ) ? $url : rest_ensure_response( array( 'url' => $url ) );
	}

	/**
	 * Revokes access and optionally deletes the synced data.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$this->auth->disconnect();
		wp_clear_scheduled_hook( Sync::HOOK );
		delete_option( Sync::STATE_OPTION );

		if ( $request['delete_data'] ) {
			$this->store->delete_all();
		}

		return $this->status();
	}

	/**
	 * Properties of the connected account, best match first.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function properties() {
		$sites = ( new Api( $this->auth ) )->sites();

		if ( is_wp_error( $sites ) ) {
			return $sites;
		}

		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$bare = preg_replace( '/^www\./', '', $host );

		foreach ( $sites as &$site ) {
			$site_host     = strtolower( 0 === strpos( $site['url'], 'sc-domain:' ) ? substr( $site['url'], 10 ) : (string) wp_parse_url( $site['url'], PHP_URL_HOST ) );
			$site['match'] = in_array( $site_host, array( $host, $bare, 'www.' . $bare ), true );
		}
		unset( $site );

		usort( $sites, static fn( array $a, array $b ): int => (int) $b['match'] <=> (int) $a['match'] );

		return rest_ensure_response( $sites );
	}

	/**
	 * Chooses the property and starts the first sync.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_property( WP_REST_Request $request ) {
		$property = (string) $request['property'];
		$sites    = ( new Api( $this->auth ) )->sites();

		if ( is_wp_error( $sites ) ) {
			return $sites;
		}

		if ( ! in_array( $property, wp_list_pluck( $sites, 'url' ), true ) ) {
			return new WP_Error( 'wpiseo_gsc_property', __( 'That property is not available for the connected account.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		$current = (string) ( $this->auth->data()['property'] ?? '' );

		if ( $current !== $property ) {
			$this->store->delete_all();
			delete_option( Sync::STATE_OPTION );
		}

		$this->auth->update( array( 'property' => $property ) );
		Sync::schedule( 1 );

		return $this->status();
	}

	/**
	 * Runs a sync step now (continues in the background if needed).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync() {
		$result = ( new Sync( $this->options, $this->auth, new Api( $this->auth ), $this->store ) )->run();

		return is_wp_error( $result ) ? $result : $this->status();
	}

	/**
	 * Site report.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function report( WP_REST_Request $request ): WP_REST_Response {
		$range     = $this->store->range( (int) $request['days'] );
		$threshold = (int) $this->options->get( 'search_console.decay_threshold', 30 );
		$minimum   = (int) $this->options->get( 'search_console.decay_min_clicks', 10 );
		$keywords  = array();

		foreach ( Sync::keywords( $this->options ) as $keyword ) {
			$current  = $this->store->totals( Store::KEYWORD, $keyword, $range['start'], $range['end'] );
			$previous = $this->store->totals( Store::KEYWORD, $keyword, $range['prev_start'], $range['prev_end'] );

			$keywords[] = array(
				'keyword'  => $keyword,
				'current'  => $current,
				'previous' => $previous,
				'series'   => $this->store->series( Store::KEYWORD, $keyword, $range['start'], $range['end'] ),
			);
		}

		return rest_ensure_response(
			array(
				'range'          => $range,
				'totals'         => $this->store->totals( Store::SITE, '', $range['start'], $range['end'] ),
				'previous'       => $this->store->totals( Store::SITE, '', $range['prev_start'], $range['prev_end'] ),
				'series'         => $this->store->series( Store::SITE, '', $range['start'], $range['end'] ),
				'previousSeries' => $this->store->series( Store::SITE, '', $range['prev_start'], $range['prev_end'] ),
				'pages'          => $this->with_titles( $this->store->pages( $range['start'], $range['end'], $range['prev_start'], $range['prev_end'], 10 ) ),
				'queries'        => $this->store->queries( '', 10 ),
				'decay'          => $this->with_titles( array_slice( $this->store->decay( $range['start'], $range['end'], $range['prev_start'], $range['prev_end'], $threshold, $minimum ), 0, 10 ) ),
				'keywords'       => $keywords,
			)
		);
	}

	/**
	 * One post's report.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function post_report( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'wpiseo_post_not_found', __( 'Post not found.', 'wpinterface-seo' ), array( 'status' => 404 ) );
		}

		$url   = (string) get_permalink( $post );
		$range = $this->store->range( (int) $request['days'] );

		return rest_ensure_response(
			array(
				'url'        => $url,
				'range'      => $range,
				'totals'     => $this->store->totals( Store::PAGE, $url, $range['start'], $range['end'] ),
				'previous'   => $this->store->totals( Store::PAGE, $url, $range['prev_start'], $range['prev_end'] ),
				'series'     => $this->store->series( Store::PAGE, $url, $range['start'], $range['end'] ),
				'queries'    => $this->store->queries( $url, 10 ),
				'inspection' => get_post_meta( $post->ID, self::INSPECTION_META, true ) ?: null, // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'connected'  => $this->auth->is_connected() && '' !== (string) ( $this->auth->data()['property'] ?? '' ),
			)
		);
	}

	/**
	 * URL Inspection for a post (on demand, quota-limited).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function inspect( WP_REST_Request $request ) {
		$post     = get_post( (int) $request['post_id'] );
		$property = (string) ( $this->auth->data()['property'] ?? '' );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'wpiseo_gsc_inspect_post', __( 'Only published content can be inspected.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		if ( '' === $property ) {
			return new WP_Error( 'wpiseo_gsc_not_ready', __( 'Connect Search Console and choose a property first.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		if ( $this->inspections_today() >= self::INSPECTION_LIMIT ) {
			return new WP_Error( 'wpiseo_gsc_quota', __( 'Google’s daily URL Inspection limit for this site was reached. Try again tomorrow.', 'wpinterface-seo' ), array( 'status' => 429 ) );
		}

		$result = ( new Api( $this->auth ) )->inspect( $property, (string) get_permalink( $post ) );

		$this->count_inspection();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$index   = (array) ( $result['indexStatusResult'] ?? array() );
		$summary = array(
			'time'            => time(),
			'verdict'         => (string) ( $index['verdict'] ?? '' ),
			'coverage'        => (string) ( $index['coverageState'] ?? '' ),
			'indexing'        => (string) ( $index['indexingState'] ?? '' ),
			'robots'          => (string) ( $index['robotsTxtState'] ?? '' ),
			'fetch'           => (string) ( $index['pageFetchState'] ?? '' ),
			'lastCrawl'       => (string) ( $index['lastCrawlTime'] ?? '' ),
			'googleCanonical' => (string) ( $index['googleCanonical'] ?? '' ),
			'userCanonical'   => (string) ( $index['userCanonical'] ?? '' ),
			'richResults'     => (string) ( $result['richResultsResult']['verdict'] ?? '' ),
			'link'            => (string) ( $result['inspectionResultLink'] ?? '' ),
		);

		update_post_meta( $post->ID, self::INSPECTION_META, $summary );

		return rest_ensure_response( $summary );
	}

	/**
	 * Sitemaps known to Search Console.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function sitemaps() {
		$property = (string) ( $this->auth->data()['property'] ?? '' );
		$sitemaps = '' === $property ? new WP_Error( 'wpiseo_gsc_not_ready', __( 'Choose a property first.', 'wpinterface-seo' ), array( 'status' => 400 ) ) : ( new Api( $this->auth ) )->sitemaps( $property );

		if ( is_wp_error( $sitemaps ) ) {
			return $sitemaps;
		}

		return rest_ensure_response(
			array_map(
				static fn( array $sitemap ): array => array(
					'path'           => (string) ( $sitemap['path'] ?? '' ),
					'lastSubmitted'  => (string) ( $sitemap['lastSubmitted'] ?? '' ),
					'lastDownloaded' => (string) ( $sitemap['lastDownloaded'] ?? '' ),
					'isPending'      => ! empty( $sitemap['isPending'] ),
					'errors'         => (int) ( $sitemap['errors'] ?? 0 ),
					'warnings'       => (int) ( $sitemap['warnings'] ?? 0 ),
					'submitted'      => array_sum( array_map( static fn( $content ) => (int) ( $content['submitted'] ?? 0 ), (array) ( $sitemap['contents'] ?? array() ) ) ),
				),
				$sitemaps
			)
		);
	}

	/**
	 * Submits the plugin's sitemap index.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_sitemap() {
		$property = (string) ( $this->auth->data()['property'] ?? '' );

		if ( '' === $property ) {
			return new WP_Error( 'wpiseo_gsc_not_ready', __( 'Choose a property first.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		if ( ! $this->auth->can_write() ) {
			return new WP_Error( 'wpiseo_gsc_scope', __( 'Reconnect with “Allow sitemap submission” turned on to submit sitemaps.', 'wpinterface-seo' ), array( 'status' => 403 ) );
		}

		$result = ( new Api( $this->auth ) )->submit_sitemap( $property, Router::index_url() );

		return is_wp_error( $result ) ? $result : $this->sitemaps();
	}

	/**
	 * Adds post IDs, titles and edit links to page rows.
	 *
	 * @param list<array<string, mixed>> $pages Rows.
	 * @return list<array<string, mixed>>
	 */
	private function with_titles( array $pages ): array {
		foreach ( $pages as &$page ) {
			$post_id = url_to_postid( (string) $page['url'] ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid -- At most 20 admin lookups.

			$page['post_id'] = $post_id;
			$page['title']   = $post_id ? wp_strip_all_tags( get_the_title( $post_id ) ) : '';
			$page['edit']    = $post_id && current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : '';
		}
		unset( $page );

		return $pages;
	}

	/**
	 * Inspections made today (UTC, like Google's quota).
	 */
	private function inspections_today(): int {
		$counter = get_option( self::INSPECTION_OPTION, array() );

		return is_array( $counter ) && ( $counter['date'] ?? '' ) === gmdate( 'Y-m-d' ) ? (int) ( $counter['count'] ?? 0 ) : 0;
	}

	/**
	 * Counts one inspection.
	 */
	private function count_inspection(): void {
		update_option(
			self::INSPECTION_OPTION,
			array(
				'date'  => gmdate( 'Y-m-d' ),
				'count' => $this->inspections_today() + 1,
			),
			false
		);
	}
}
