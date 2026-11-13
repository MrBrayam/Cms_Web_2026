<?php
/**
 * Activation.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Install;

use WP_Site;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Handles single-site, network-wide and new-site activation.
 */
final class Activator {

	/**
	 * Activation hook.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 */
	public static function activate( $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				restore_current_blog();
			}

			return;
		}

		self::activate_site();
	}

	/**
	 * Installs on the current site.
	 */
	public static function activate_site(): void {
		Upgrader::maybe_upgrade();
		Capabilities::add_to_roles();
		update_option( 'wpiseo_flush_rewrite_rules', 1, true );
	}

	/**
	 * Installs on a site created after network activation.
	 *
	 * @param WP_Site $site New site.
	 */
	public static function on_new_site( $site ): void {
		if ( ! $site instanceof WP_Site ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( WPISEO_BASENAME ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		self::activate_site();
		restore_current_blog();
	}
}
