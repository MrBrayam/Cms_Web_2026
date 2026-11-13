<?php
/**
 * Redirects and 404 log REST endpoints.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Redirects\Csv;
use WPInterface\SEO\Redirects\Importer;
use WPInterface\SEO\Redirects\Normalizer;
use WPInterface\SEO\Redirects\NotFoundLog;
use WPInterface\SEO\Redirects\Repository;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * /wpinterface-seo/v1/redirects[/(id)|/bulk|/import|/export], /404[/(id)/redirect].
 */
final class RedirectsController {

	/**
	 * Rules.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * 404 log.
	 *
	 * @var NotFoundLog
	 */
	private NotFoundLog $log;

	/**
	 * Constructor.
	 *
	 * @param Repository  $repository Rules.
	 * @param NotFoundLog $log        404 log.
	 */
	public function __construct( Repository $repository, NotFoundLog $log ) {
		$this->repository = $repository;
		$this->log        = $log;
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		$ns         = SettingsController::REST_NAMESPACE;
		$permission = array( $this, 'can_manage' );
		$rule_args  = array(
			'source'       => array( 'type' => 'string' ),
			'target'       => array( 'type' => 'string' ),
			'status'       => array(
				'type' => 'integer',
				'enum' => Repository::STATUSES,
			),
			'match_type'   => array(
				'type' => 'string',
				'enum' => Repository::MATCH_TYPES,
			),
			'ignore_query' => array( 'type' => 'boolean' ),
			'enabled'      => array( 'type' => 'boolean' ),
		);

		register_rest_route(
			$ns,
			'/redirects',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_rules' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_rule' ),
					'permission_callback' => $permission,
					'args'                => $rule_args,
				),
			)
		);

		register_rest_route(
			$ns,
			'/redirects/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_rule' ),
					'permission_callback' => $permission,
					'args'                => $rule_args,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_rule' ),
					'permission_callback' => $permission,
				),
			)
		);

		register_rest_route(
			$ns,
			'/redirects/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk' ),
				'permission_callback' => $permission,
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'enum'     => array( 'enable', 'disable', 'delete' ),
						'required' => true,
					),
					'ids'    => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/redirects/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_csv' ),
				'permission_callback' => $permission,
				'args'                => array(
					'csv' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/redirects/sources',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( ( new Importer( $this->repository ) )->sources() ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => fn( WP_REST_Request $request ) => rest_ensure_response( ( new Importer( $this->repository ) )->import( (string) $request['source'] ) ),
					'permission_callback' => $permission,
					'args'                => array(
						'source' => array(
							'type'     => 'string',
							'enum'     => array( 'redirection', 'rankmath', 'yoast', 'slimseo', 'seopress' ),
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/redirects/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'stats' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/redirects/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_csv' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$ns,
			'/404',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_404' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_404' ),
					'permission_callback' => $permission,
					'args'                => array(
						'ids' => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'integer' ),
							'default' => array(),
						),
						'all' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/404/(?P<id>\d+)/redirect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'redirect_404' ),
				'permission_callback' => $permission,
				'args'                => array(
					'target' => array( 'type' => 'string' ),
					'status' => array(
						'type'    => 'integer',
						'enum'    => Repository::STATUSES,
						'default' => 301,
					),
				),
			)
		);
	}

	/**
	 * Permission check.
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE_REDIRECTS );
	}

	/**
	 * Lists rules.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function list_rules( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->repository->query( $request->get_params() );

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );

		return $response;
	}

	/**
	 * Creates a rule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_rule( WP_REST_Request $request ) {
		$id = $this->repository->create( array_merge( $this->rule_data( $request ), array( 'origin' => 'manual' ) ) );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return rest_ensure_response( $this->repository->get( $id ) );
	}

	/**
	 * Updates a rule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_rule( WP_REST_Request $request ) {
		$result = $this->repository->update( (int) $request['id'], $this->rule_data( $request ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $this->repository->get( (int) $request['id'] ) );
	}

	/**
	 * Deletes a rule.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function delete_rule( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( array( 'deleted' => $this->repository->delete( array( (int) $request['id'] ) ) ) );
	}

	/**
	 * Bulk enable/disable/delete.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function bulk( WP_REST_Request $request ): WP_REST_Response {
		$ids = array_map( 'intval', (array) $request['ids'] );

		switch ( (string) $request['action'] ) {
			case 'delete':
				$count = $this->repository->delete( $ids );
				break;
			default:
				$count = $this->repository->set_enabled( $ids, 'enable' === $request['action'] );
		}

		return rest_ensure_response( array( 'updated' => $count ) );
	}

	/**
	 * Imports CSV text.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function import_csv( WP_REST_Request $request ): WP_REST_Response {
		$created = 0;
		$errors  = array();

		foreach ( Csv::parse( (string) $request['csv'] ) as $rule ) {
			$result = $this->repository->create( $rule );

			if ( is_wp_error( $result ) ) {
				if ( count( $errors ) < 20 ) {
					$errors[] = sprintf( '%s: %s', $rule['source'], $result->get_error_message() );
				}
				continue;
			}

			++$created;
		}

		return rest_ensure_response(
			array(
				'created' => $created,
				'errors'  => $errors,
			)
		);
	}

	/**
	 * Figures for the Redirects page: active rules, redirected visits and
	 * missing pages of the last seven days.
	 */
	public function stats(): WP_REST_Response {
		$counts = $this->repository->counts();

		return rest_ensure_response(
			array(
				'active'   => $counts['exact'] + $counts['regex'],
				'hits'     => $this->repository->hits(),
				'notFound' => Plugin::instance()->options()->get( 'redirects.log_404', true ) ? $this->log->recent( time() - WEEK_IN_SECONDS, 0 ) : null,
			)
		);
	}

	/**
	 * Exports all rules as CSV text.
	 */
	public function export_csv(): WP_REST_Response {
		return rest_ensure_response( array( 'csv' => Csv::export( $this->repository->all() ) ) );
	}

	/**
	 * Lists 404s.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function list_404( WP_REST_Request $request ): WP_REST_Response {
		$result   = $this->log->query( $request->get_params() );
		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );

		return $response;
	}

	/**
	 * Deletes 404 entries, or all of them with `all: true`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_404( WP_REST_Request $request ) {
		$ids = array_values( array_filter( array_map( 'absint', (array) $request['ids'] ) ) );

		// Clearing the whole log must be asked for explicitly.
		if ( array() === $ids && ! $request['all'] ) {
			return new WP_Error( 'wpiseo_404_no_ids', __( 'No log entries selected.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'deleted' => $this->log->delete( $ids ) ) );
	}

	/**
	 * Creates a redirect for a logged 404 and removes it from the log.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function redirect_404( WP_REST_Request $request ) {
		$entry = $this->log->get( (int) $request['id'] );

		if ( null === $entry ) {
			return new WP_Error( 'wpiseo_404_not_found', __( 'Log entry not found.', 'wpinterface-seo' ), array( 'status' => 404 ) );
		}

		$id = $this->repository->create(
			array(
				'source'       => (string) $entry['url'],
				'target'       => (string) $request['target'],
				'status'       => (int) $request['status'],
				'ignore_query' => false === strpos( (string) $entry['url'], '?' ),
				'origin'       => '404',
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$this->log->delete_url( Normalizer::full( Normalizer::parse( (string) $entry['url'] ) ) );
		$this->log->delete( array( (int) $entry['id'] ) );

		return rest_ensure_response( $this->repository->get( $id ) );
	}

	/**
	 * Rule fields present in a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function rule_data( WP_REST_Request $request ): array {
		$data = array();

		foreach ( array( 'source', 'target', 'status', 'match_type', 'ignore_query', 'enabled' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$data[ $key ] = $request->get_param( $key );
			}
		}

		return $data;
	}
}
