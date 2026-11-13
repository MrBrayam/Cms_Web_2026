<?php
/**
 * IndexNow client.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Crawl;

use WP_Post;
use WPInterface\SEO\Context\Context;
use WPInterface\SEO\Options;
use WPInterface\SEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Notifies Bing, Yandex, Seznam, Naver and other IndexNow engines when
 * content is published, updated or removed. URLs are queued and sent in
 * one batch shortly after, so bulk edits make one request.
 *
 * External service: https://api.indexnow.org/indexnow (opt-in).
 */
final class IndexNow {

	public const ENDPOINT      = 'https://api.indexnow.org/indexnow';
	public const CRON_HOOK     = 'wpiseo_indexnow_flush';
	private const QUEUE_OPTION = 'wpiseo_indexnow_queue';
	private const LOG_OPTION   = 'wpiseo_indexnow_log';
	private const MAX_LOG      = 30;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'transition_post_status', array( $this, 'post_status_changed' ), 20, 3 );
		add_action( self::CRON_HOOK, array( $this, 'flush' ) );
		add_action( 'template_redirect', array( $this, 'serve_key' ), 0 );
	}

	/**
	 * The API key (generated when IndexNow is enabled).
	 */
	public function key(): string {
		return (string) $this->options->get( 'crawl.indexnow_key', '' );
	}

	/**
	 * Serves `/{key}.txt` so engines can verify ownership (no file is written).
	 */
	public function serve_key(): void {
		$key = $this->key();

		if ( '' === $key || ! is_404() ) {
			return;
		}

		$path      = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
		$home_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

		if ( $home_path . '/' . $key . '.txt' !== $path ) {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex', true );
		echo esc_html( $key );
		exit;
	}

	/**
	 * Queues URLs of posts that were published, updated or unpublished.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public function post_status_changed( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || ! is_post_type_viewable( $post->post_type ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		if ( 'publish' === $new_status && ! Plugin::instance()->presentation( Context::for_post( $post ) )->is_indexable() ) {
			return;
		}

		$url = get_permalink( $post );

		if ( is_string( $url ) ) {
			$this->queue( array( $url ) );
		}
	}

	/**
	 * Adds URLs to the queue and schedules a send in 30 seconds.
	 *
	 * @param string[] $urls URLs.
	 */
	public function queue( array $urls ): void {
		$queue = get_option( self::QUEUE_OPTION, array() );
		$queue = array_values( array_unique( array_merge( is_array( $queue ) ? $queue : array(), $urls ) ) );

		update_option( self::QUEUE_OPTION, array_slice( $queue, -10000 ), false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_HOOK );
		}
	}

	/**
	 * Sends the queued URLs.
	 */
	public function flush(): void {
		$queue = get_option( self::QUEUE_OPTION, array() );
		delete_option( self::QUEUE_OPTION );

		if ( is_array( $queue ) && array() !== $queue ) {
			$this->submit( $queue );
		}
	}

	/**
	 * Submits URLs now.
	 *
	 * @param string[] $urls URLs on this site.
	 * @return array{code: int, message: string}
	 */
	public function submit( array $urls ): array {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$urls = array_values(
			array_filter(
				array_unique( array_map( 'esc_url_raw', $urls ) ),
				static fn( string $url ): bool => 0 === strcasecmp( (string) wp_parse_url( $url, PHP_URL_HOST ), $host )
			)
		);

		if ( array() === $urls ) {
			return $this->log( 0, 0, __( 'No URLs of this site to submit.', 'wpinterface-seo' ) );
		}

		if ( ! self::is_public_site( $host ) ) {
			return $this->log( count( $urls ), 0, __( 'Skipped: this looks like a local or development site.', 'wpinterface-seo' ) );
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => (string) wp_json_encode(
					array(
						'host'        => $host,
						'key'         => $this->key(),
						'keyLocation' => home_url( '/' . $this->key() . '.txt' ),
						'urlList'     => array_slice( $urls, 0, 10000 ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->log( count( $urls ), 0, $response->get_error_message() );
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$messages = array(
			200 => __( 'Submitted.', 'wpinterface-seo' ),
			202 => __( 'Accepted; the key is being verified.', 'wpinterface-seo' ),
			400 => __( 'Bad request.', 'wpinterface-seo' ),
			403 => __( 'The key was not accepted. Make sure the key file is reachable.', 'wpinterface-seo' ),
			422 => __( 'The URLs do not belong to this host.', 'wpinterface-seo' ),
			429 => __( 'Too many requests. Try again later.', 'wpinterface-seo' ),
		);

		return $this->log( count( $urls ), $code, $messages[ $code ] ?? __( 'Unexpected response.', 'wpinterface-seo' ) );
	}

	/**
	 * Recent submissions, newest first.
	 *
	 * @return list<array{time: int, urls: int, code: int, message: string}>
	 */
	public function history(): array {
		$log = get_option( self::LOG_OPTION, array() );

		return is_array( $log ) ? array_values( $log ) : array();
	}

	/**
	 * Whether a host can be reached by search engines.
	 *
	 * @param string $host Host name.
	 */
	public static function is_public_site( string $host ): bool {
		if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
			return false;
		}

		return ! preg_match( '/(^localhost$|\.local$|\.localhost$|\.test$|\.example$|\.invalid$|^127\.|^10\.|^192\.168\.)/i', $host );
	}

	/**
	 * Stores a log entry and returns the result.
	 *
	 * @param int    $urls    Number of URLs.
	 * @param int    $code    HTTP status (0 for none).
	 * @param string $message Message.
	 * @return array{code: int, message: string}
	 */
	private function log( int $urls, int $code, string $message ): array {
		$log = $this->history();

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'urls'    => $urls,
				'code'    => $code,
				'message' => $message,
			)
		);

		update_option( self::LOG_OPTION, array_slice( $log, 0, self::MAX_LOG ), false );

		return array(
			'code'    => $code,
			'message' => $message,
		);
	}
}
