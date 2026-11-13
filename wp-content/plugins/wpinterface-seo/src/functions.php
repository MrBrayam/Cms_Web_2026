<?php
/**
 * Public template/helper functions.
 *
 * These are the stable, theme-facing API. Keep signatures backward compatible.
 *
 * @package WPInterface\SEO
 */

use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin instance.
 */
function wpiseo(): Plugin {
	return Plugin::instance();
}

/**
 * Reads a setting by dot path, e.g. `wpiseo_get_option( 'general.separator' )`.
 *
 * @param string $path          Dot-separated path.
 * @param mixed  $default_value Value returned when the path does not exist.
 * @return mixed
 */
function wpiseo_get_option( string $path, $default_value = null ) {
	return Plugin::instance()->options()->get( $path, $default_value );
}

/**
 * Replaces %variables% in a template for the given (or current) context.
 *
 * @param string       $template Template such as `%title% %sep% %sitename%`.
 * @param Context|null $context  Context to render for; defaults to the current request.
 */
function wpiseo_parse_template( string $template, ?Context $context = null ): string {
	$plugin = Plugin::instance();

	return $plugin->parser()->parse( $template, $context ?? $plugin->context() );
}

/**
 * Whether a module is enabled.
 *
 * @param string $module_id Module ID, e.g. `sitemaps`.
 */
function wpiseo_module_enabled( string $module_id ): bool {
	return Plugin::instance()->modules()->is_enabled( $module_id );
}

/**
 * Prints (or returns) breadcrumbs for the current page.
 *
 * Usage in a theme: `<?php if ( function_exists( 'wpiseo_breadcrumbs' ) ) { wpiseo_breadcrumbs(); } ?>`
 *
 * @param array<string, mixed> $args    Optional: class, separator, prefix, show_current.
 * @param bool                 $display Whether to echo (default) or return.
 */
function wpiseo_breadcrumbs( array $args = array(), bool $display = true ): string {
	$module = Plugin::instance()->modules()->get( 'breadcrumbs' );

	$html = $module instanceof \WPInterface\SEO\Modules\Breadcrumbs\BreadcrumbsModule ? $module->html( null, $args ) : '';

	if ( $display ) {
		echo wp_kses( $html, \WPInterface\SEO\Modules\Breadcrumbs\BreadcrumbsModule::allowed_html() );
	}

	return $html;
}
