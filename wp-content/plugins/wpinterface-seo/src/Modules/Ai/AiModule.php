<?php
/**
 * AI Assist module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Ai;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WPInterface\SEO\Ai\Client;
use WPInterface\SEO\Ai\Generator;
use WPInterface\SEO\Ai\Usage;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Rest\SettingsController;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Suggests titles, descriptions, social text, alt text and FAQ drafts through
 * the WordPress AI Client, with the provider the site owner set up in
 * Settings → Connectors. Every request is started by a user, counted against
 * a daily per-user limit, and reviewed before anything is saved.
 */
final class AiModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'daily_limit' => 50,
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
		return array(
			'daily_limit' => max( 1, min( 1000, absint( $values['daily_limit'] ?? $defaults['daily_limit'] ) ) ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'wpiseo_editor_config', array( $this, 'editor_config' ) );

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 85 );
		}
	}

	/**
	 * Tells the editor whether AI buttons can be shown.
	 *
	 * @param array<string, mixed> $config Editor config.
	 * @return array<string, mixed>
	 */
	public function editor_config( $config ): array {
		$config       = is_array( $config ) ? $config : array();
		$config['ai'] = array( 'available' => Client::available() );

		return $config;
	}

	/**
	 * REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/ai/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => static fn() => current_user_can( Capabilities::EDIT_SEO ),
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/ai/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( $this, 'can_generate' ),
				'args'                => array(
					'field'         => array(
						'type'     => 'string',
						'enum'     => Generator::FIELDS,
						'required' => true,
					),
					'post_id'       => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'attachment_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'title'         => array( 'type' => 'string' ),
					'content'       => array( 'type' => 'string' ),
					'keywords'      => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	/**
	 * Permission: SEO rights on the post (and the image for alt text).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function can_generate( WP_REST_Request $request ): bool {
		if ( ! current_user_can( Capabilities::EDIT_SEO ) ) {
			return false;
		}

		$post_id       = (int) $request['post_id'];
		$attachment_id = (int) $request['attachment_id'];

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		if ( 'alt' === $request['field'] ) {
			return $attachment_id > 0 && current_user_can( 'edit_post', $attachment_id );
		}

		return $post_id > 0;
	}

	/**
	 * Availability and today's usage.
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		return rest_ensure_response(
			array(
				'available'     => Client::available(),
				'wpAi'          => function_exists( 'wp_ai_client_prompt' ),
				'usage'         => Usage::today( get_current_user_id() ),
				'limit'         => (int) $this->plugin->options()->get( 'ai.daily_limit', 50 ),
				'connectorsUrl' => current_user_can( 'manage_options' ) ? admin_url( 'options-connectors.php' ) : '',
			)
		);
	}

	/**
	 * Generates suggestions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function generate( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$limit   = (int) $this->plugin->options()->get( 'ai.daily_limit', 50 );

		if ( Usage::today( $user_id ) >= $limit ) {
			return new WP_Error(
				'wpiseo_ai_limit',
				sprintf(
					/* translators: %d: daily limit. */
					__( 'You reached today’s limit of %d AI requests. It resets tomorrow; site managers can change it in SEO → AI Assist.', 'wpinterface-seo' ),
					$limit
				),
				array( 'status' => 429 )
			);
		}

		$input = array(
			'post_id'       => (int) $request['post_id'],
			'attachment_id' => (int) $request['attachment_id'],
		);

		foreach ( array( 'title', 'content', 'keywords' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$input[ $key ] = $request->get_param( $key );
			}
		}

		Usage::record( $user_id );

		$result = ( new Generator( new Client() ) )->generate( (string) $request['field'], $input );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Adds the AI Assist page.
	 *
	 * @param array<string, array<string, mixed>> $pages Pages.
	 * @return array<string, array<string, mixed>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['ai'] = array(
			'title'      => __( 'AI Assist', 'wpinterface-seo' ),
			'menu_title' => __( 'AI Assist', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}
}
