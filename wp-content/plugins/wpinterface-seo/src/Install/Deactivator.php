<?php
/**
 * Deactivation.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Removes scheduled events and rewrite rules. Never deletes data.
 */
final class Deactivator {

	/**
	 * Deactivation hook.
	 */
	public static function deactivate(): void {
		foreach ( self::cron_hooks() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		delete_option( 'wpiseo_flush_rewrite_rules' );
		flush_rewrite_rules( false );
	}

	/**
	 * Cron hooks registered by the plugin.
	 *
	 * @return list<string>
	 */
	public static function cron_hooks(): array {
		/**
		 * Filters the plugin's cron hooks, cleared on deactivation and uninstall.
		 *
		 * @param list<string> $hooks Hook names.
		 */
		return array_values( (array) apply_filters( 'wpiseo_cron_hooks', array( 'wpiseo_daily', 'wpiseo_hourly', 'wpiseo_batch', 'wpiseo_indexnow_flush', 'wpiseo_links_rebuild', 'wpiseo_gsc_sync' ) ) );
	}
}
