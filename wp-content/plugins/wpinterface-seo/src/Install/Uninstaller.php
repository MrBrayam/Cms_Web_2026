<?php
/**
 * Uninstall routine.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Install;

use WPInterface\SEO\Options;
use WPInterface\SEO\SearchConsole\Auth;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes plugin data only when "Delete data on uninstall" is enabled on that site.
 */
final class Uninstaller {

	/**
	 * Runs for every site on multisite, otherwise for the current site.
	 */
	public static function run(): void {
		if ( ! is_multisite() ) {
			self::uninstall_site();
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::uninstall_site();
			restore_current_blog();
		}
	}

	/**
	 * Cleans up the current site.
	 */
	private static function uninstall_site(): void {
		foreach ( Deactivator::cron_hooks() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		$settings = get_option( Options::OPTION, array() );

		if ( empty( $settings['general']['delete_data_on_uninstall'] ) ) {
			return;
		}

		global $wpdb;

		// Revoke the Search Console grant with Google before its tokens are deleted.
		( new Auth() )->disconnect();

		Schema::drop();
		Capabilities::remove_from_roles();

		$option_like    = $wpdb->esc_like( 'wpiseo_' ) . '%';
		$transient_like = $wpdb->esc_like( '_transient_wpiseo_' ) . '%';
		$timeout_like   = $wpdb->esc_like( '_transient_timeout_wpiseo_' ) . '%';
		$meta_like      = $wpdb->esc_like( '_wpiseo_' ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Bulk cleanup on uninstall; no API exists for prefix deletes.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $option_like, $transient_like, $timeout_like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $meta_like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $meta_like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $meta_like ) );
		// phpcs:enable

		wp_cache_flush();
	}
}
