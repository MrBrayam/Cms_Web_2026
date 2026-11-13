<?php
/**
 * Post editor integration.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Editor;

use WP_Post;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Modules\Schema\SchemaModule;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Rest\SettingsController;
use WPInterface\SEO\Schema\Types;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Images;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the SEO panel: a sidebar in the block editor and a metabox in the
 * classic editor. Both render the same React app (assets/src/editor).
 */
final class Editor {

	public const HANDLE = 'wpiseo-editor';
	private const NONCE = 'wpiseo_metabox';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_classic' ) );
	}

	/**
	 * Post types that get the SEO panel.
	 *
	 * @return list<string>
	 */
	public static function post_types(): array {
		$post_types = array_values(
			array_filter(
				get_post_types(
					array(
						'public'  => true,
						'show_ui' => true,
					),
					'names'
				),
				static fn( string $post_type ): bool => 'attachment' !== $post_type
			)
		);

		/**
		 * Filters the post types that show the SEO panel.
		 *
		 * @param list<string> $post_types Post type names.
		 */
		return array_values( (array) apply_filters( 'wpiseo_editor_post_types', $post_types ) );
	}

	/**
	 * Block editor: enqueues the sidebar for supported post types.
	 */
	public function enqueue_block_editor(): void {
		$post = get_post();

		if ( ! $post instanceof WP_Post || ! $this->can_use( $post ) ) {
			return;
		}

		$this->enqueue( $post, 'block' );
	}

	/**
	 * Classic editor: adds the metabox.
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post.
	 */
	public function add_meta_box( $post_type, $post = null ): void {
		if ( ! $post instanceof WP_Post || ! $this->can_use( $post ) ) {
			return;
		}

		$block_editor = use_block_editor_for_post( $post );

		if ( $block_editor && ! $this->plugin->options()->get( 'general.block_editor_metabox', false ) ) {
			return;
		}

		add_meta_box(
			'wpiseo-metabox',
			__( 'SEO', 'wpinterface-seo' ),
			array( $this, 'render_meta_box' ),
			(string) $post_type,
			'normal',
			'high'
		);

		// In the block editor the sidebar script is already loaded and also mounts the metabox.
		if ( ! $block_editor ) {
			$this->enqueue( $post, 'classic' );
		}
	}

	/**
	 * Renders the metabox root and the hidden field the app writes to.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_meta_box( $post ): void {
		unset( $post ); // The app reads values from the localized config.

		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		echo '<input type="hidden" id="wpiseo-meta-input" name="wpiseo_meta" value="" />';
		echo '<div id="wpiseo-metabox-root"><p>' . esc_html__( 'Loading…', 'wpinterface-seo' ) . '</p></div>';
	}

	/**
	 * Saves metabox values (classic editor only; the block editor saves via REST).
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_classic( $post_id ): void {
		if (
			! isset( $_POST[ self::NONCE . '_nonce' ], $_POST['wpiseo_meta'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE )
			|| wp_is_post_autosave( (int) $post_id )
			|| wp_is_post_revision( (int) $post_id )
			|| ! current_user_can( 'edit_post', (int) $post_id )
			|| ! current_user_can( Capabilities::EDIT_SEO )
		) {
			return;
		}

		// JSON from the metabox app, sanitized field by field right after decoding.
		$data = self::sanitize_meta_input( json_decode( wp_unslash( $_POST['wpiseo_meta'] ), true ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_meta_input().

		foreach ( array_keys( Fields::post() ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				Meta::write( 'post', (int) $post_id, $key, $data[ $key ] );
			}
		}

		/**
		 * Fires after the classic editor metabox saved its fields. Add-ons
		 * save their own `_wpiseo_*` meta from `$data`, whose keys have the
		 * `_wpiseo_` prefix removed. Known fields are sanitized by their field
		 * spec, other strings with sanitize_textarea_field(); validate them
		 * further for your own format.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $data    Sanitized values.
		 */
		do_action( 'wpiseo_classic_meta_saved', (int) $post_id, $data );
	}

	/**
	 * Sanitizes the decoded metabox values: known fields by their field spec,
	 * other values (add-on fields) keep their type with text cleaned.
	 *
	 * @param mixed $input Decoded JSON.
	 * @return array<string, mixed>
	 */
	public static function sanitize_meta_input( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$fields = Fields::post();
		$clean  = array();

		foreach ( $input as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			$clean[ $key ] = isset( $fields[ $key ] ) ? Fields::sanitize( $fields[ $key ], $value ) : self::sanitize_value( $value );
		}

		return $clean;
	}

	/**
	 * Cleans a value of unknown purpose while keeping its type.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( self::class, 'sanitize_value' ), $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Whether the current user gets the SEO panel for this post.
	 *
	 * @param WP_Post $post Post.
	 */
	private function can_use( WP_Post $post ): bool {
		return in_array( $post->post_type, self::post_types(), true )
			&& current_user_can( Capabilities::EDIT_SEO )
			&& current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Enqueues the editor app.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $mode `block` or `classic`.
	 */
	private function enqueue( WP_Post $post, string $mode ): void {
		$asset_file = WPISEO_PATH . 'assets/build/editor.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset        = require $asset_file;
		$dependencies = $asset['dependencies'] ?? array();

		if ( 'classic' === $mode ) {
			// The editor-only packages are not loaded on classic screens.
			$dependencies = array_values( array_diff( $dependencies, array( 'wp-editor', 'wp-edit-post', 'wp-block-editor', 'wp-plugins' ) ) );
			wp_enqueue_media();
		}

		wp_enqueue_script( self::HANDLE, WPISEO_URL . 'assets/build/editor.js', $dependencies, $asset['version'] ?? WPISEO_VERSION, array( 'in_footer' => true ) );

		if ( is_readable( WPISEO_PATH . 'assets/build/editor.css' ) ) {
			wp_enqueue_style( self::HANDLE, WPISEO_URL . 'assets/build/editor.css', array( 'wp-components' ), $asset['version'] ?? WPISEO_VERSION );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}

		wp_set_script_translations( self::HANDLE, 'wpinterface-seo', WPISEO_PATH . 'languages' );

		wp_add_inline_script(
			self::HANDLE,
			'window.wpiseoEditor = ' . wp_json_encode( $this->config( $post, $mode ), JSON_HEX_TAG ) . ';',
			'before'
		);

		/**
		 * Fires after the SEO editor app is enqueued. Add-ons enqueue editor
		 * scripts here (depend on `wpiseo-editor`).
		 *
		 * @param WP_Post $post Post being edited.
		 * @param string  $mode block|classic.
		 */
		do_action( 'wpiseo_editor_enqueue_scripts', $post, $mode );
	}

	/**
	 * Configuration passed to the editor app.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $mode Mode.
	 * @return array<string, mixed>
	 */
	private function config( WP_Post $post, string $mode ): array {
		$options    = $this->plugin->options();
		$modules    = $this->plugin->modules();
		$type       = MetaModule::post_type( $options, $post->post_type );
		$schema_pt  = SchemaModule::post_type( $options, $post->post_type );
		$default_og = Images::social( (int) $options->get( 'social.default_image', 0 ) );
		$taxonomies = array();

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( $taxonomy->hierarchical && $taxonomy->public && $taxonomy->show_ui ) {
				$taxonomies[] = array(
					'slug'     => $taxonomy->name,
					'label'    => $taxonomy->labels->singular_name,
					'restBase' => $taxonomy->rest_base ? $taxonomy->rest_base : $taxonomy->name,
				);
			}
		}

		$variables = array();
		foreach ( $this->plugin->variables()->all() as $name => $variable ) {
			$variables[] = array(
				'name'    => $name,
				'label'   => $variable['label'],
				'example' => $variable['example'],
			);
		}

		$config = array(
			'mode'           => $mode,
			'postId'         => $post->ID,
			'postType'       => $post->post_type,
			'restNamespace'  => SettingsController::REST_NAMESPACE,
			'modules'        => array(
				'analysis'    => $modules->is_enabled( 'analysis' ) && $modules->is_implemented( 'analysis' ),
				'social'      => $modules->is_enabled( 'social' ) && $modules->is_implemented( 'social' ),
				'schema'      => $modules->is_enabled( 'schema' ) && $modules->is_implemented( 'schema' ),
				'breadcrumbs' => $modules->is_enabled( 'breadcrumbs' ) && $modules->is_implemented( 'breadcrumbs' ),
				'links'       => null !== $modules->get( 'links' ),
			),
			'site'           => array(
				'name'       => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
				'tagline'    => wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ),
				'url'        => home_url( '/' ),
				'host'       => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'locale'     => get_locale(),
				'dateFormat' => (string) get_option( 'date_format' ),
				'public'     => (bool) get_option( 'blog_public' ),
			),
			'separator'      => (string) $options->get( 'general.separator', '-' ),
			'templates'      => array(
				'title'       => (string) $type['title'],
				'description' => (string) $type['description'],
			),
			'noindexDefault' => (bool) $type['noindex'],
			'readability'    => (bool) $options->get( 'analysis.readability', true ),
			'postTypeLabel'  => (string) ( get_post_type_object( $post->post_type )->labels->singular_name ?? '' ),
			'maxKeywords'    => (int) apply_filters( 'wpiseo_max_focus_keywords', Fields::MAX_KEYWORDS ),
			'schema'         => array(
				'pageTypes'          => Fields::PAGE_TYPES,
				'articleTypes'       => Fields::ARTICLE_TYPES,
				'defaultPageType'    => $schema_pt['page_type'],
				'defaultArticleType' => $schema_pt['article_type'],
			),
			'social'         => array(
				'defaultImage' => $default_og['url'] ?? '',
			),
			'taxonomies'     => $taxonomies,
			'schemaTypes'    => Types::definitions(),
			// Store products get Product schema from the WooCommerce/EDD module.
			'storeProduct'   => ( 'product' === $post->post_type && $modules->get( 'woocommerce' ) && $options->get( 'woocommerce.product_schema', true ) )
				|| ( 'download' === $post->post_type && $modules->get( 'edd' ) && $options->get( 'edd.product_schema', true ) ),
			'variables'      => $variables,
			'outputEnabled'  => $this->plugin->output_enabled(),
		);

		if ( 'classic' === $mode ) {
			$author               = get_userdata( (int) $post->post_author );
			$permalink            = get_permalink( $post );
			$config['authorName'] = $author ? $author->display_name : '';
			$config['permalink']  = is_string( $permalink ) ? $permalink : '';
			$config['date']       = (string) get_post_time( 'c', true, $post );
			$config['modified']   = (string) get_post_modified_time( 'c', true, $post );

			$meta = array();
			foreach ( array_keys( Fields::post() ) as $key ) {
				$meta[ Fields::PREFIX . $key ] = Meta::post( $post->ID, $key );
			}
			$config['meta'] = $meta;
		}

		/**
		 * Filters the editor app configuration.
		 *
		 * @param array   $config Configuration.
		 * @param WP_Post $post   Post being edited.
		 */
		return (array) apply_filters( 'wpiseo_editor_config', $config, $post );
	}
}
