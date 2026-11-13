<?php
/**
 * Import REST endpoints.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPInterface\SEO\Import\AioseoImporter;
use WPInterface\SEO\Import\Importer;
use WPInterface\SEO\Import\RankMathImporter;
use WPInterface\SEO\Import\SeoFrameworkImporter;
use WPInterface\SEO\Import\SeoPressImporter;
use WPInterface\SEO\Import\SlimSeoImporter;
use WPInterface\SEO\Import\YoastImporter;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * GET  /wpinterface-seo/v1/import        Detected sources.
 * POST /wpinterface-seo/v1/import/{id}   Run one step: settings | posts | terms | users.
 *
 * The admin UI calls `posts` repeatedly with the returned `next` offset so
 * large sites import in small requests.
 */
final class ImportController {

	private const BATCH = 100;

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
	 * Available importers keyed by ID.
	 *
	 * @return array<string, Importer>
	 */
	public static function importers(): array {
		$importers = array();

		foreach ( array( new YoastImporter(), new RankMathImporter(), new AioseoImporter(), new SeoPressImporter(), new SeoFrameworkImporter(), new SlimSeoImporter() ) as $importer ) {
			$importers[ $importer->id() ] = $importer;
		}

		/**
		 * Filters importers (ID => Importer).
		 *
		 * @param array<string, Importer> $importers Importers.
		 */
		$importers = apply_filters( 'wpiseo_importers', $importers );

		return array_filter( (array) $importers, static fn( $importer ): bool => $importer instanceof Importer );
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_sources' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/import/(?P<id>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'step'      => array(
						'type'     => 'string',
						'enum'     => array( 'settings', 'posts', 'terms', 'users' ),
						'required' => true,
					),
					'offset'    => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
					'overwrite' => array(
						'type'    => 'boolean',
						'default' => false,
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
	 * Lists sources with what was found.
	 */
	public function list_sources(): WP_REST_Response {
		$out = array();

		foreach ( self::importers() as $id => $importer ) {
			$posts = $importer->count_posts();

			$out[] = array(
				'id'       => $id,
				'label'    => $importer->label(),
				'posts'    => $posts,
				'settings' => array() !== $importer->settings(),
				'detected' => $posts > 0 || array() !== $importer->settings(),
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Runs one import step.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		$importer = self::importers()[ (string) $request['id'] ] ?? null;

		if ( null === $importer ) {
			return new WP_Error( 'wpiseo_unknown_importer', __( 'Unknown import source.', 'wpinterface-seo' ), array( 'status' => 404 ) );
		}

		$overwrite = (bool) $request['overwrite'];

		switch ( (string) $request['step'] ) {
			case 'settings':
				$settings = $importer->settings();
				if ( array() !== $settings ) {
					$this->plugin->options()->update( $settings );
				}
				return rest_ensure_response( array( 'imported' => array_keys( $settings ) ) );

			case 'terms':
				return rest_ensure_response( $importer->import_terms( $overwrite ) );

			case 'users':
				return rest_ensure_response( $importer->import_users( $overwrite ) );

			default:
				return rest_ensure_response(
					array_merge(
						$importer->import_posts( (int) $request['offset'], self::BATCH, $overwrite ),
						array( 'total' => $importer->count_posts() )
					)
				);
		}
	}
}
