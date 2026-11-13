<?php
/**
 * Plugin branding (white-label support).
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Name and credits shown in the admin and in front-end output. Add-ons can
 * rebrand the plugin with the `wpiseo_branding` filter.
 */
final class Branding {

	/**
	 * Branding values.
	 *
	 * @return array{name: string, menu_title: string, credit: bool, show_version: bool, help_url: string}
	 */
	public static function all(): array {
		$defaults = array(
			'name'         => 'WPInterface SEO',
			'menu_title'   => __( 'SEO', 'wpinterface-seo' ),
			'credit'       => true,
			'show_version' => true,
			'help_url'     => 'https://wpinterface.com/plugins/wpinterface-seo/',
		);

		/**
		 * Filters the plugin's branding.
		 *
		 * @param array $branding name (plugin name in the admin), menu_title (admin menu),
		 *                        credit (name the plugin in page source, redirect headers and
		 *                        the sitemap stylesheet), show_version (version in the admin header),
		 *                        help_url (the header's Help link; empty hides it).
		 */
		$branding = apply_filters( 'wpiseo_branding', $defaults );
		$branding = is_array( $branding ) ? array_merge( $defaults, $branding ) : $defaults;

		return array(
			'name'         => '' !== trim( (string) $branding['name'] ) ? (string) $branding['name'] : $defaults['name'],
			'menu_title'   => '' !== trim( (string) $branding['menu_title'] ) ? (string) $branding['menu_title'] : $defaults['menu_title'],
			'credit'       => (bool) $branding['credit'],
			'show_version' => (bool) $branding['show_version'],
			'help_url'     => esc_url_raw( (string) $branding['help_url'] ),
		);
	}

	/**
	 * Plugin name for the admin.
	 */
	public static function name(): string {
		return self::all()['name'];
	}

	/**
	 * `X-Redirect-By` value, or false to send none.
	 *
	 * @return string|false
	 */
	public static function redirect_by() {
		$branding = self::all();

		return $branding['credit'] ? $branding['name'] : false;
	}

	/**
	 * Whether front-end output may name the plugin.
	 */
	public static function credit(): bool {
		return self::all()['credit'];
	}
}
