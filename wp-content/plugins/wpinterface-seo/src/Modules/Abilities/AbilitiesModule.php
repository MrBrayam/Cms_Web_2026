<?php
/**
 * Abilities API module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Abilities;

use WPInterface\SEO\Abilities\SeoAbilities;
use WPInterface\SEO\Modules\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers SEO abilities (WordPress 6.9+). Abilities marked public are
 * listed in the Abilities REST API and by the MCP Adapter plugin, so AI
 * agents can read and update SEO data within the user's permissions.
 */
final class AbilitiesModule extends Module {

	public const CATEGORY = 'seo';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers the "SEO" category unless another plugin already did.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) || ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'SEO', 'wpinterface-seo' ),
				'description' => __( 'Search engine optimization: titles, descriptions, analysis, redirects and indexing.', 'wpinterface-seo' ),
			)
		);
	}

	/**
	 * Registers the abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( ( new SeoAbilities( $this->plugin ) )->definitions() as $name => $args ) {
			if ( ! is_array( $args ) ) {
				continue;
			}

			$annotations = (array) ( $args['annotations'] ?? array() );
			unset( $args['annotations'] );

			$args['category'] = self::CATEGORY;
			$args['meta']     = array(
				'annotations'  => $annotations,
				'public'       => true,
				'show_in_rest' => true,
				// MCP Adapter releases before WordPress 7.1 read this flag.
				'mcp'          => array( 'public' => true ),
			);

			wp_register_ability( (string) $name, $args );
		}
	}
}
