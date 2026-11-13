<?php
/**
 * Settings REST endpoint.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /wpinterface-seo/v1/settings.
 *
 * A dedicated route (instead of /wp/v2/settings) so access follows the
 * plugin's `wpiseo_manage_settings` capability rather than `manage_options`.
 */
final class SettingsController {

	public const REST_NAMESPACE = 'wpinterface-seo/v1';

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
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'settings' => array(
							'type'        => 'object',
							'required'    => true,
							'description' => __( 'Partial settings keyed by section. Omitted keys are left unchanged.', 'wpinterface-seo' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check.
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Returns settings and module states.
	 */
	public function get_settings(): WP_REST_Response {
		return rest_ensure_response( $this->payload() );
	}

	/**
	 * Saves a partial settings object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );

		if ( ! is_array( $settings ) ) {
			return new WP_Error( 'wpiseo_invalid_settings', __( 'Settings must be an object.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		$this->plugin->options()->update( $settings );

		return rest_ensure_response( $this->payload() );
	}

	/**
	 * Response body.
	 *
	 * @return array<string, mixed>
	 */
	private function payload(): array {
		return array(
			'settings' => $this->plugin->options()->all(),
			'modules'  => $this->plugin->modules()->describe(),
		);
	}
}
