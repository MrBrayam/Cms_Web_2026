<?php
/**
 * Tools module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Tools;

use WPInterface\SEO\Admin\Admin;
use WPInterface\SEO\Admin\HeadInspector;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Rest\ImportController;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Conflicts;
use WPInterface\SEO\Support\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * Importers, setup wizard and the "another SEO plugin is active" notice.
 */
final class ToolsModule extends Module {

	private const DISMISS_ACTION = 'wpiseo_dismiss_conflicts';
	private const DISMISS_META   = '_wpiseo_dismissed_conflicts';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		( new HeadInspector() )->register();

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 90 );
			add_action( 'admin_notices', array( $this, 'conflict_notice' ) );
			add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'dismiss_conflicts' ) );
		}
	}

	/**
	 * Registers REST routes.
	 */
	public function register_routes(): void {
		( new ImportController( $this->plugin ) )->register_routes();
	}

	/**
	 * Adds the Tools page, and the Setup page until setup is completed.
	 *
	 * @param array<string, array<string, string>> $pages Pages.
	 * @return array<string, array<string, string>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['bulk-editor'] = array(
			'title'      => __( 'Bulk Editor', 'wpinterface-seo' ),
			'menu_title' => __( 'Bulk Editor', 'wpinterface-seo' ),
			'capability' => Capabilities::EDIT_SEO,
		);

		$pages['tools'] = array(
			'title'      => __( 'Tools', 'wpinterface-seo' ),
			'menu_title' => __( 'Tools', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		$pages['setup'] = array(
			'title'      => __( 'Setup Wizard', 'wpinterface-seo' ),
			'menu_title' => __( 'Setup Wizard', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
			'hidden'     => (bool) $this->plugin->options()->get( 'general.setup_completed', false ),
		);

		return $pages;
	}

	/**
	 * Explains why output is paused while another SEO plugin is active.
	 *
	 * Shown on the Dashboard, Plugins and SEO screens; dismissible per user
	 * (until the set of active SEO plugins changes).
	 */
	public function conflict_notice(): void {
		if ( $this->plugin->output_enabled() || ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ( ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) && false === strpos( $screen->id, Admin::MENU_SLUG ) ) ) {
			return;
		}

		$conflicts = Conflicts::active();
		$signature = md5( implode( ',', array_keys( $conflicts ) ) );

		if ( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) === $signature ) {
			return;
		}

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a> <a class="button" href="%5$s">%6$s</a> <a href="%7$s">%8$s</a></p></div>',
			/* translators: %s: plugin name. */
			esc_html( sprintf( __( '%s is paused.', 'wpinterface-seo' ), Branding::name() ) ),
			esc_html(
				sprintf(
					/* translators: 1: list of plugin names, 2: this plugin's name. */
					__( '%1$s is active, so %2$s is not printing titles, meta tags, schema or sitemaps (to avoid duplicates). Import your data, then deactivate the other plugin.', 'wpinterface-seo' ),
					implode( ', ', $conflicts ),
					Branding::name()
				)
			),
			esc_url( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '-tools' ) ),
			esc_html__( 'Import data', 'wpinterface-seo' ),
			esc_url( admin_url( 'plugins.php' ) ),
			esc_html__( 'Go to Plugins', 'wpinterface-seo' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'wpinterface-seo' )
		);
	}

	/**
	 * Stores the dismissal and returns to the previous screen.
	 */
	public function dismiss_conflicts(): void {
		check_admin_referer( self::DISMISS_ACTION );

		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wpinterface-seo' ), 403 );
		}

		update_user_meta( get_current_user_id(), self::DISMISS_META, md5( implode( ',', array_keys( Conflicts::active() ) ) ) );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
