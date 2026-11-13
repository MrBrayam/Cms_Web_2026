<?php
/**
 * Uninstall handler.
 *
 * Data is removed only if "Delete all data when the plugin is deleted" is
 * enabled in SEO → Settings. Otherwise settings and post meta are kept so a
 * reinstall picks up where the site left off.
 *
 * @package WPInterface\SEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Autoloader.php';
\WPInterface\SEO\Autoloader::register();

\WPInterface\SEO\Install\Uninstaller::run();
