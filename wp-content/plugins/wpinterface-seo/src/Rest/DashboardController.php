<?php
/**
 * Dashboard REST endpoints.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPInterface\SEO\Dashboard\Report;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Upsell;

defined( 'ABSPATH' ) || exit;

/**
 * /wpinterface-seo/v1/dashboard and /offers. Reads local data only.
 */
final class DashboardController {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		$permission = static fn() => current_user_can( Capabilities::MANAGE_SETTINGS );

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'report' ),
				'permission_callback' => $permission,
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/offers/hide',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'hide_offer' ),
				'permission_callback' => $permission,
				'args'                => array(
					'id' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9_-]{1,40}$',
					),
				),
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/dashboard/setup',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'hide_setup' ),
				'permission_callback' => $permission,
				'args'                => array(
					'hidden' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Hides a Pro offer for the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function hide_offer( WP_REST_Request $request ): WP_REST_Response {
		Upsell::hide( sanitize_key( (string) $request['id'] ) );

		return rest_ensure_response( array( 'hidden' => Upsell::hidden() ) );
	}

	/**
	 * Dashboard data.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function report( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( ( new Report( $this->plugin ) )->build( (bool) $request['refresh'] ) );
	}

	/**
	 * Hides or shows the setup checklist for the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function hide_setup( WP_REST_Request $request ): WP_REST_Response {
		if ( $request['hidden'] ) {
			update_user_meta( get_current_user_id(), Report::HIDE_SETUP_META, 1 );
		} else {
			delete_user_meta( get_current_user_id(), Report::HIDE_SETUP_META );
		}

		return rest_ensure_response( array( 'hidden' => (bool) $request['hidden'] ) );
	}
}
