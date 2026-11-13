<?php
/**
 * Shared block editor assets.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

use WPInterface\SEO\Admin\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the script that holds the editor code of every plugin block.
 */
final class Blocks {

	public const SCRIPT = 'wpiseo-blocks';

	/**
	 * Registers the script once (call on `init` before register_block_type()).
	 */
	public static function register_script(): void {
		if ( wp_script_is( self::SCRIPT, 'registered' ) ) {
			return;
		}

		$asset_file = WPISEO_PATH . 'assets/build/blocks.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script( self::SCRIPT, WPISEO_URL . 'assets/build/blocks.js', $asset['dependencies'] ?? array(), $asset['version'] ?? WPISEO_VERSION, array( 'in_footer' => true ) );
		wp_set_script_translations( self::SCRIPT, 'wpinterface-seo', WPISEO_PATH . 'languages' );
		// Data is added when the editor loads, after all post types are registered.
		add_action( 'enqueue_block_editor_assets', array( self::class, 'add_data' ) );
	}

	/**
	 * Adds `window.wpiseoBlocks` for the block editor scripts.
	 */
	public static function add_data(): void {
		wp_add_inline_script(
			self::SCRIPT,
			'window.wpiseoBlocks = ' . wp_json_encode(
				array(
					'breadcrumbs' => function_exists( 'wpiseo_module_enabled' ) && wpiseo_module_enabled( 'breadcrumbs' ),
					'local'       => function_exists( 'wpiseo_module_enabled' ) && wpiseo_module_enabled( 'local' ),
					'localUrl'    => admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '-local' ),
					'htmlSitemap' => function_exists( 'wpiseo_module_enabled' ) && wpiseo_module_enabled( 'sitemaps' ),
					'postTypes'   => array_values(
						array_map(
							static fn( $type ) => array(
								'value' => $type->name,
								'label' => $type->labels->name,
							),
							array_filter( get_post_types( array( 'public' => true ), 'objects' ), static fn( $type ) => 'attachment' !== $type->name )
						)
					),
				)
			) . ';',
			'before'
		);
	}
}
