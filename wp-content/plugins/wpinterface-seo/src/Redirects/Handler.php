<?php
/**
 * Front-end redirect and 404 handling.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Redirects;

use WPInterface\SEO\Options;
use WPInterface\SEO\Support\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * Applies redirect rules on `template_redirect` (before WordPress' own
 * canonical redirects) and logs remaining 404s.
 */
final class Handler {

	private const ASSET_PATTERN = '/\.(?:css|js|map|mjs|json|xml|txt|ico|png|jpe?g|gif|webp|avif|svg|woff2?|ttf|eot|otf|mp4|webm|mp3|pdf|zip)$/i';
	private const BOT_PATTERN   = '/bot|crawl|spider|slurp|archiver|facebookexternalhit|embedly|preview|monitor|curl|wget|python|httpclient|headless/i';

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Rules.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * 404 log.
	 *
	 * @var NotFoundLog
	 */
	private NotFoundLog $log;

	/**
	 * Constructor.
	 *
	 * @param Options     $options    Settings.
	 * @param Repository  $repository Rules.
	 * @param NotFoundLog $log        404 log.
	 */
	public function __construct( Options $options, Repository $repository, NotFoundLog $log ) {
		$this->options    = $options;
		$this->repository = $repository;
		$this->log        = $log;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'redirect' ), 0 );
		add_action( 'template_redirect', array( $this, 'log_404' ), 999 );
	}

	/**
	 * Redirects (or answers 410/451) when a rule matches.
	 */
	public function redirect(): void {
		if ( is_admin() || is_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$request = Normalizer::from_request();

		/**
		 * Filters the matched rule (null for none).
		 *
		 * @param array|null $rule    Matched rule.
		 * @param array      $request Parsed request: path, query.
		 */
		$rule = apply_filters( 'wpiseo_redirect_match', $this->repository->match( $request ), $request );

		if ( ! is_array( $rule ) ) {
			return;
		}

		if ( ! self::is_prefetch() ) {
			$this->repository->hit( (int) $rule['id'] );
		}

		$status = (int) $rule['status'];

		if ( $status >= 400 ) {
			global $wp_query;

			$wp_query->set_404();
			status_header( $status );
			nocache_headers();
			return;
		}

		$target = Normalizer::target_url( (string) ( $rule['resolved_target'] ?? $rule['target'] ) );

		// Keep the original query string when the rule ignores it and the target has none.
		if ( $rule['ignore_query'] && '' !== $request['query'] && false === strpos( $target, '?' ) && 'exact' === $rule['match_type'] ) {
			$target .= '?' . $request['query'];
		}

		if ( '' === $target ) {
			return;
		}

		$current = Normalizer::full( $request );
		if ( Normalizer::is_internal( $target ) && Normalizer::full( Normalizer::parse( $target ) ) === $current ) {
			return;
		}

		wp_redirect( $target, $status, Branding::redirect_by() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External targets are intentional and set by site managers.
		exit;
	}

	/**
	 * Logs the request if it ended as a 404.
	 */
	public function log_404(): void {
		if ( ! is_404() || ! $this->options->get( 'redirects.log_404', true ) || self::is_prefetch() ) {
			return;
		}

		$request    = Normalizer::from_request();
		$url        = Normalizer::full( $request );
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( $this->options->get( 'redirects.ignore_assets', true ) && preg_match( self::ASSET_PATTERN, $request['path'] ) ) {
			return;
		}

		if ( ! $this->options->get( 'redirects.log_bots', false ) && ( '' === $user_agent || preg_match( self::BOT_PATTERN, $user_agent ) ) ) {
			return;
		}

		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		/**
		 * Filters whether a 404 is logged.
		 *
		 * @param bool   $log Whether to log.
		 * @param string $url Path and query.
		 */
		if ( ! apply_filters( 'wpiseo_log_404', true, $url ) ) {
			return;
		}

		$this->log->record( $url, $referrer, $this->options->get( 'redirects.log_user_agent', false ) ? $user_agent : '' );
	}

	/**
	 * Whether the browser is prefetching/prerendering (speculative loading).
	 */
	private static function is_prefetch(): bool {
		$purpose = isset( $_SERVER['HTTP_SEC_PURPOSE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_PURPOSE'] ) ) : '';
		$legacy  = isset( $_SERVER['HTTP_PURPOSE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PURPOSE'] ) ) : '';

		return false !== stripos( $purpose . $legacy, 'prefetch' ) || false !== stripos( $purpose, 'prerender' );
	}
}
