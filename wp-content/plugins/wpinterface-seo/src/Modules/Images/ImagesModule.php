<?php
/**
 * Image SEO module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Images;

use WP_HTML_Tag_Processor;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Rest\SettingsController;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;
use WPInterface\SEO\Support\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * Alt text automation (at output time; the media library is only changed
 * by the explicit bulk tool), attachment page redirects and optional
 * filename clean-up on upload.
 */
final class ImagesModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'auto_alt'            => true,
			'fill_empty_alt'      => true,
			'alt_template'        => '%image_title%',
			'attachment_redirect' => 'file',
			'clean_filenames'     => false,
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
		$template = sanitize_text_field( (string) ( $values['alt_template'] ?? $defaults['alt_template'] ) );

		return array(
			'auto_alt'            => Sanitize::to_bool( $values['auto_alt'] ?? $defaults['auto_alt'] ),
			'fill_empty_alt'      => Sanitize::to_bool( $values['fill_empty_alt'] ?? $defaults['fill_empty_alt'] ),
			'alt_template'        => '' !== trim( $template ) ? $template : $defaults['alt_template'],
			'attachment_redirect' => in_array( $values['attachment_redirect'] ?? '', array( 'none', 'parent', 'file' ), true ) ? $values['attachment_redirect'] : 'file',
			'clean_filenames'     => Sanitize::to_bool( $values['clean_filenames'] ?? $defaults['clean_filenames'] ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		$options = $this->plugin->options();

		if ( $options->get( 'images.auto_alt', true ) ) {
			add_filter( 'wp_content_img_tag', array( $this, 'content_image' ), 20, 3 );
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'attachment_attributes' ), 20, 2 );
		}

		if ( 'none' !== $options->get( 'images.attachment_redirect', 'file' ) ) {
			add_action( 'template_redirect', array( $this, 'redirect_attachment' ), 2 );
		}

		// Only uploads and sideloads (e.g. "upload from URL"), not every sanitize_file_name() call.
		if ( $options->get( 'images.clean_filenames', false ) ) {
			add_filter( 'wp_handle_upload_prefilter', array( $this, 'clean_upload_name' ) );
			add_filter( 'wp_handle_sideload_prefilter', array( $this, 'clean_upload_name' ) );
		}

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 50 );
		}
	}

	/**
	 * Adds alt text to content images that lack it.
	 *
	 * @param string $html          Image tag.
	 * @param string $context       Filter context.
	 * @param int    $attachment_id Attachment ID (0 if unknown).
	 */
	public function content_image( $html, $context = '', $attachment_id = 0 ): string {
		$html = (string) $html;

		if ( false === stripos( $html, '<img' ) ) {
			return $html;
		}

		$processor = new WP_HTML_Tag_Processor( $html );

		if ( ! $processor->next_tag( array( 'tag_name' => 'img' ) ) || ! $this->needs_alt( $processor->get_attribute( 'alt' ) ) ) {
			return $html;
		}

		$src = $processor->get_attribute( 'src' );
		$alt = $this->alt_for( (int) $attachment_id, is_string( $src ) ? $src : '' );

		if ( '' === $alt ) {
			return $html;
		}

		$processor->set_attribute( 'alt', $alt );

		return $processor->get_updated_html();
	}

	/**
	 * Adds alt text to images printed with wp_get_attachment_image() (featured images, galleries).
	 *
	 * @param array<string, string> $attributes Attributes.
	 * @param WP_Post               $attachment Attachment.
	 * @return array<string, string>
	 */
	public function attachment_attributes( $attributes, $attachment ): array {
		$attributes = is_array( $attributes ) ? $attributes : array();

		if ( $attachment instanceof WP_Post && $this->needs_alt( $attributes['alt'] ?? null ) ) {
			$alt = $this->alt_for( (int) $attachment->ID, (string) ( $attributes['src'] ?? '' ) );

			if ( '' !== $alt ) {
				$attributes['alt'] = $alt;
			}
		}

		return $attributes;
	}

	/**
	 * Redirects attachment pages to the parent post or the file.
	 */
	public function redirect_attachment(): void {
		// WordPress 6.4+ can disable attachment pages itself.
		if ( ! is_attachment() || '0' === (string) get_option( 'wp_attachment_pages_enabled', '1' ) ) {
			return;
		}

		$attachment = get_queried_object();

		if ( ! $attachment instanceof WP_Post ) {
			return;
		}

		$target = '';
		$parent = $attachment->post_parent ? get_post( $attachment->post_parent ) : null;

		if ( 'parent' === $this->plugin->options()->get( 'images.attachment_redirect' ) && $parent instanceof WP_Post && 'publish' === $parent->post_status ) {
			$link   = get_permalink( $parent );
			$target = is_string( $link ) ? $link : '';
		}

		if ( '' === $target ) {
			$file   = wp_get_attachment_url( $attachment->ID );
			$target = is_string( $file ) ? $file : '';
		}

		// The target comes from WordPress itself; uploads may live on a CDN host, which wp_safe_redirect() would refuse.
		if ( '' !== $target && wp_redirect( $target, 301, Branding::redirect_by() ) ) { // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	/**
	 * Cleans the name of a file being uploaded.
	 *
	 * @param array<string, mixed> $file Upload: name, type, tmp_name, size.
	 * @return array<string, mixed>
	 */
	public function clean_upload_name( $file ): array {
		$file = is_array( $file ) ? $file : array();

		if ( isset( $file['name'] ) && is_string( $file['name'] ) ) {
			$file['name'] = $this->clean_filename( $file['name'] );
		}

		return $file;
	}

	/**
	 * Cleans uploaded file names: lowercase, ASCII, dashes.
	 *
	 * @param string $filename File name.
	 */
	public function clean_filename( string $filename ): string {
		$filename  = (string) $filename;
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		$name      = '' !== $extension ? substr( $filename, 0, -strlen( $extension ) - 1 ) : $filename;
		$name      = strtolower( remove_accents( $name ) );
		$name      = (string) preg_replace( '/[^a-z0-9]+/', '-', $name );
		$name      = trim( $name, '-' );

		if ( '' === $name ) {
			return $filename;
		}

		return '' !== $extension ? $name . '.' . strtolower( $extension ) : $name;
	}

	/**
	 * REST: bulk-fill missing alt text in the media library.
	 */
	public function register_routes(): void {
		register_rest_route(
			SettingsController::REST_NAMESPACE,
			'/images/fill-alt',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( array( 'missing' => $this->missing_alt_query( 1 )->found_posts ) ),
					'permission_callback' => static fn() => current_user_can( Capabilities::MANAGE_SETTINGS ) && current_user_can( 'upload_files' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'fill_alt_batch' ),
					'permission_callback' => static fn() => current_user_can( Capabilities::MANAGE_SETTINGS ) && current_user_can( 'upload_files' ),
				),
			)
		);
	}

	/**
	 * Fills one batch of images without alt text.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function fill_alt_batch( WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );

		$query   = $this->missing_alt_query( 50 );
		$updated = 0;

		foreach ( $query->posts as $attachment_id ) {
			$alt = $this->render_template( (int) $attachment_id, '' );

			// Fall back to the title, then the file name, so the image is never picked again.
			if ( '' === $alt ) {
				$alt = trim( (string) get_the_title( (int) $attachment_id ) );
			}

			if ( '' === $alt ) {
				$alt = (string) preg_replace( '/[-_]+/', ' ', pathinfo( (string) get_attached_file( (int) $attachment_id ), PATHINFO_FILENAME ) );
			}

			$alt = trim( $alt );

			update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $alt ? $alt : __( 'Image', 'wpinterface-seo' ) );
			++$updated;
		}

		return rest_ensure_response(
			array(
				'updated'   => $updated,
				'remaining' => max( 0, (int) $query->found_posts - $updated ),
			)
		);
	}

	/**
	 * Adds the Images settings page.
	 *
	 * @param array<string, array<string, mixed>> $pages Pages.
	 * @return array<string, array<string, mixed>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['images'] = array(
			'title'      => __( 'Image SEO', 'wpinterface-seo' ),
			'menu_title' => __( 'Images', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}

	/**
	 * Alt text for an image: the media library alt first, then the template.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $src           Image URL.
	 */
	public function alt_for( int $attachment_id, string $src ): string {
		if ( $attachment_id ) {
			$stored = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

			if ( '' !== $stored ) {
				return $stored;
			}
		}

		return $this->render_template( $attachment_id, $src );
	}

	/**
	 * Renders the alt template.
	 *
	 * Variables: %image_title%, %image_caption%, %filename%, %post_title%, %sitename%.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $src           Image URL (for %filename% when there is no attachment).
	 */
	private function render_template( int $attachment_id, string $src ): string {
		$template = (string) $this->plugin->options()->get( 'images.alt_template', '%image_title%' );
		$file     = $attachment_id ? (string) get_attached_file( $attachment_id ) : (string) wp_parse_url( $src, PHP_URL_PATH );
		$filename = (string) preg_replace( '/[-_]+/', ' ', pathinfo( $file, PATHINFO_FILENAME ) );
		$filename = (string) preg_replace( '/\s\d+x\d+$|\sscaled$/', '', $filename );
		$post     = get_post();

		$values = array(
			'%image_title%'   => $attachment_id ? (string) get_the_title( $attachment_id ) : '',
			'%image_caption%' => $attachment_id ? (string) wp_get_attachment_caption( $attachment_id ) : '',
			'%filename%'      => ucfirst( trim( $filename ) ),
			'%post_title%'    => $post instanceof WP_Post ? (string) get_the_title( $post ) : '',
			'%sitename%'      => (string) get_bloginfo( 'name' ),
		);

		$alt = trim( (string) preg_replace( '/\s+/', ' ', strtr( $template, $values ) ) );

		// An image title that is just the file name ("IMG_2041") is no better than nothing.
		if ( '' === $alt || preg_match( '/^(img|dsc|image|photo|screenshot)?[\s_-]*\d+$/i', $alt ) ) {
			$alt = $values['%post_title%'];
		}

		return wp_strip_all_tags( $alt );
	}

	/**
	 * Whether an alt attribute value should be filled.
	 *
	 * @param mixed $alt Current value (null when the attribute is missing).
	 */
	private function needs_alt( $alt ): bool {
		if ( null === $alt || false === $alt ) {
			return true;
		}

		return '' === trim( (string) $alt ) && $this->plugin->options()->get( 'images.fill_empty_alt', true );
	}

	/**
	 * Image attachments without alt text.
	 *
	 * @param int $limit Batch size.
	 */
	private function missing_alt_query( int $limit ): WP_Query {
		return new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only batch tool.
					'relation' => 'OR',
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => '_wp_attachment_image_alt',
						'value' => '',
					),
				),
			)
		);
	}
}
