<?php
/**
 * Template variables REST endpoints.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * GET  /wpinterface-seo/v1/variables          List variables for template editors.
 * POST /wpinterface-seo/v1/variables/preview  Render a template for a post, term or the site.
 */
final class VariablesController {

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
			SettingsController::REST_NAMESPACE,
			'/variables',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_variables' ),
				'permission_callback' => array( $this, 'can_edit_seo' ),
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/variables/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_preview' ),
				'args'                => array(
					'template' => array(
						'type'     => 'string',
						'required' => true,
					),
					'post_id'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'term_id'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission check for listing.
	 */
	public function can_edit_seo(): bool {
		return current_user_can( Capabilities::EDIT_SEO ) || current_user_can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Permission check for previews: the user must be able to edit the object.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function can_preview( WP_REST_Request $request ): bool {
		if ( ! $this->can_edit_seo() ) {
			return false;
		}

		$post_id = (int) $request->get_param( 'post_id' );
		$term_id = (int) $request->get_param( 'term_id' );

		if ( $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		}

		if ( $term_id ) {
			return current_user_can( 'edit_term', $term_id );
		}

		return true;
	}

	/**
	 * Lists variables.
	 */
	public function list_variables(): WP_REST_Response {
		$out = array();

		foreach ( $this->plugin->variables()->all() as $name => $variable ) {
			$out[] = array(
				'name'             => $name,
				'label'            => $variable['label'],
				'description'      => $variable['description'],
				'example'          => $variable['example'],
				'accepts_argument' => $variable['accepts_argument'],
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Renders a template.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview( WP_REST_Request $request ) {
		$template = (string) $request->get_param( 'template' );

		if ( strlen( $template ) > 1000 ) {
			return new WP_Error( 'wpiseo_template_too_long', __( 'Template is too long.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		$post_id = (int) $request->get_param( 'post_id' );
		$term_id = (int) $request->get_param( 'term_id' );

		if ( $post_id ) {
			$context = Context::for_post( $post_id );
		} elseif ( $term_id ) {
			$context = Context::for_term( $term_id );
		} else {
			$context = Context::for_front_page_posts();
		}

		return rest_ensure_response(
			array(
				'template' => $template,
				'output'   => $this->plugin->parser()->parse( $template, $context ),
			)
		);
	}
}
