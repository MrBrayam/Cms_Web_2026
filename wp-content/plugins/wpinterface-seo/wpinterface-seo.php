<?php
/**
 * Plugin Name:       WPInterface SEO
 * Plugin URI:        https://wpinterface.com/plugins/wpinterface-seo/
 * Description:       Complete, lightweight SEO for any WordPress theme: titles, meta, schema, sitemaps, redirects and more.
 * Version:           1.0.2
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            WPInterface
 * Author URI:        https://wpinterface.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpinterface-seo
 * Domain Path:       /languages
 *
 * @package WPInterface\SEO
 */

defined( 'ABSPATH' ) || exit;

// This file must stay parseable by old PHP versions so the requirements notice can render.
define( 'WPISEO_VERSION', '1.0.2' );
define( 'WPISEO_DB_VERSION', 4 );
define( 'WPISEO_FILE', __FILE__ );
define( 'WPISEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPISEO_URL', plugin_dir_url( __FILE__ ) );
define( 'WPISEO_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPISEO_MIN_PHP', '7.4' );
define( 'WPISEO_MIN_WP', '6.6' );

/**
 * Whether the server meets the minimum PHP and WordPress versions.
 *
 * @return bool
 */
function wpiseo_requirements_met() {
	global $wp_version;

	return version_compare( PHP_VERSION, WPISEO_MIN_PHP, '>=' )
		&& version_compare( $wp_version, WPISEO_MIN_WP, '>=' );
}

/**
 * Admin notice shown when requirements are not met.
 *
 * @return void
 */
function wpiseo_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: required WordPress version. */
				__( 'WPInterface SEO requires PHP %1$s and WordPress %2$s or newer. The plugin is inactive until the server is updated.', 'wpinterface-seo' ),
				WPISEO_MIN_PHP,
				WPISEO_MIN_WP
			)
		)
	);
}

if ( ! wpiseo_requirements_met() ) {
	add_action( 'admin_notices', 'wpiseo_requirements_notice' );
	return;
}

require_once WPISEO_PATH . 'src/Autoloader.php';
\WPInterface\SEO\Autoloader::register();
require_once WPISEO_PATH . 'src/functions.php';

register_activation_hook( WPISEO_FILE, array( \WPInterface\SEO\Install\Activator::class, 'activate' ) );
register_deactivation_hook( WPISEO_FILE, array( \WPInterface\SEO\Install\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \WPInterface\SEO\Plugin::instance(), 'boot' ) );
