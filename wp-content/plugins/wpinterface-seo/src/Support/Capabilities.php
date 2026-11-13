<?php
/**
 * Custom capabilities.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin capabilities and their default role mapping.
 *
 * Users with `manage_options` always receive every plugin capability, so a
 * site can never lock its administrators out of SEO settings.
 */
final class Capabilities {

	public const MANAGE_SETTINGS  = 'wpiseo_manage_settings';
	public const EDIT_SEO         = 'wpiseo_edit_seo';
	public const MANAGE_REDIRECTS = 'wpiseo_manage_redirects';
	public const VIEW_ANALYTICS   = 'wpiseo_view_analytics';

	/**
	 * All plugin capabilities.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::MANAGE_SETTINGS,
			self::EDIT_SEO,
			self::MANAGE_REDIRECTS,
			self::VIEW_ANALYTICS,
		);
	}

	/**
	 * Capabilities granted to each role on install.
	 *
	 * @return array<string, list<string>>
	 */
	public static function default_role_map(): array {
		/**
		 * Filters which roles receive plugin capabilities on install.
		 *
		 * @param array $map Role name => list of capabilities.
		 */
		return (array) apply_filters(
			'wpiseo_default_role_capabilities',
			array(
				'administrator' => self::all(),
				'editor'        => array( self::EDIT_SEO, self::VIEW_ANALYTICS ),
				'author'        => array( self::EDIT_SEO ),
				'contributor'   => array( self::EDIT_SEO ),
			)
		);
	}

	/**
	 * Adds capabilities to roles (activation / upgrade).
	 */
	public static function add_to_roles(): void {
		foreach ( self::default_role_map() as $role_name => $caps ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( (array) $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Removes plugin capabilities from every role (uninstall).
	 */
	public static function remove_from_roles(): void {
		$roles = wp_roles();

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = $roles->get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Registers the administrator safety net.
	 */
	public function register(): void {
		add_filter( 'user_has_cap', array( $this, 'grant_to_site_managers' ), 10, 1 );
	}

	/**
	 * Grants every plugin capability to users who can `manage_options`.
	 *
	 * @param array<string, bool> $allcaps User capabilities.
	 * @return array<string, bool>
	 */
	public function grant_to_site_managers( $allcaps ): array {
		$allcaps = is_array( $allcaps ) ? $allcaps : array();

		if ( ! empty( $allcaps['manage_options'] ) ) {
			foreach ( self::all() as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}

		return $allcaps;
	}
}
