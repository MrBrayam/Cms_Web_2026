<?php
/**
 * Redirects & 404 log module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Redirects;

use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Redirects\AutoRedirects;
use WPInterface\SEO\Redirects\Handler;
use WPInterface\SEO\Redirects\NotFoundLog;
use WPInterface\SEO\Redirects\Repository;
use WPInterface\SEO\Rest\RedirectsController;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Redirect manager (301/302/307/308/410/451, exact and regex), automatic
 * redirects for changed URLs, and a privacy-friendly 404 log.
 */
final class RedirectsModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'auto_post'      => true,
			'auto_term'      => true,
			'on_trash'       => 'none',
			'log_404'        => true,
			'log_retention'  => 30,
			'log_max'        => 10000,
			'log_bots'       => false,
			'log_user_agent' => false,
			'ignore_assets'  => true,
		);
	}

	/**
	 * Sanitizes the section.
	 *
	 * @param array<string, mixed> $values   Values.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $values, array $defaults ): array {
		$bool = static fn( string $key ): bool => Sanitize::to_bool( $values[ $key ] ?? $defaults[ $key ] );

		return array(
			'auto_post'      => $bool( 'auto_post' ),
			'auto_term'      => $bool( 'auto_term' ),
			'on_trash'       => in_array( $values['on_trash'] ?? '', array( 'none', '410', 'redirect' ), true ) ? $values['on_trash'] : 'none',
			'log_404'        => $bool( 'log_404' ),
			'log_retention'  => max( 1, min( 365, absint( $values['log_retention'] ?? $defaults['log_retention'] ) ) ),
			'log_max'        => max( 100, min( 100000, absint( $values['log_max'] ?? $defaults['log_max'] ) ) ),
			'log_bots'       => $bool( 'log_bots' ),
			'log_user_agent' => $bool( 'log_user_agent' ),
			'ignore_assets'  => $bool( 'ignore_assets' ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		$options    = $this->plugin->options();
		$repository = new Repository();
		$log        = new NotFoundLog();

		// Redirects keep working while another SEO plugin is active: they do not duplicate output.
		( new Handler( $options, $repository, $log ) )->register();
		( new AutoRedirects( $options, $repository ) )->register();

		add_action( 'rest_api_init', static fn() => ( new RedirectsController( $repository, $log ) )->register_routes() );
		add_action( 'wpiseo_daily', array( $this, 'purge_log' ) );
		add_action( 'init', array( $this, 'schedule' ) );

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 45 );
		}
	}

	/**
	 * Schedules the daily maintenance event.
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( 'wpiseo_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpiseo_daily' );
		}
	}

	/**
	 * Applies 404 log retention.
	 */
	public function purge_log(): void {
		$options = $this->plugin->options();

		( new NotFoundLog() )->purge( (int) $options->get( 'redirects.log_retention', 30 ), (int) $options->get( 'redirects.log_max', 10000 ) );
	}

	/**
	 * Adds the Redirects and 404 Log pages.
	 *
	 * @param array<string, array<string, mixed>> $pages Pages.
	 * @return array<string, array<string, mixed>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['redirects'] = array(
			'title'      => __( 'Redirects', 'wpinterface-seo' ),
			'menu_title' => __( 'Redirects', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_REDIRECTS,
		);

		$pages['404-log'] = array(
			'title'      => __( '404 Log', 'wpinterface-seo' ),
			'menu_title' => __( '404 Log', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_REDIRECTS,
		);

		return $pages;
	}
}
