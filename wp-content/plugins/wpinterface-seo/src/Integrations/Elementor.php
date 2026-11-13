<?php
/**
 * Elementor integration.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Integrations;

use WP_Post;
use WPInterface\SEO\Editor\Editor;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an "SEO" section to Elementor's page settings (Settings tab) with the
 * most-used fields. Values are stored in the plugin's own post meta, never in
 * Elementor's page settings, so the WordPress editor and Elementor always
 * show the same data. The full panel stays available in the WordPress editor.
 */
final class Elementor {

	/**
	 * Control ID => SEO field.
	 */
	private const CONTROLS = array(
		'wpiseo_title'         => 'title',
		'wpiseo_description'   => 'description',
		'wpiseo_focus_keyword' => 'focus_keywords',
		'wpiseo_noindex'       => 'noindex',
	);

	/**
	 * Registers hooks (only does work when Elementor is active).
	 */
	public function register(): void {
		add_action( 'elementor/documents/register_controls', array( $this, 'controls' ) );
		add_filter( 'elementor/document/save/data', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Adds the SEO section.
	 *
	 * @param \Elementor\Core\Base\Document $document Elementor document.
	 */
	public function controls( $document ): void {
		$post = $this->post( $document );

		if ( null === $post || ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		$template = MetaModule::post_type( Plugin::instance()->options(), $post->post_type );
		$keywords = (array) Meta::post( $post->ID, 'focus_keywords' );

		$document->start_controls_section(
			'wpiseo_section',
			array(
				'label' => __( 'SEO', 'wpinterface-seo' ),
				'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
			)
		);

		$document->add_control(
			'wpiseo_title',
			array(
				'label'       => __( 'SEO title', 'wpinterface-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => (string) Meta::post( $post->ID, 'title' ),
				'placeholder' => $template['title'],
				'label_block' => true,
			)
		);

		$document->add_control(
			'wpiseo_description',
			array(
				'label'       => __( 'Meta description', 'wpinterface-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => (string) Meta::post( $post->ID, 'description' ),
				'placeholder' => $template['description'],
				'rows'        => 3,
			)
		);

		$document->add_control(
			'wpiseo_focus_keyword',
			array(
				'label'       => __( 'Focus keyword', 'wpinterface-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => (string) ( $keywords[0] ?? '' ),
				'label_block' => true,
			)
		);

		$document->add_control(
			'wpiseo_noindex',
			array(
				'label'   => __( 'Search engines', 'wpinterface-seo' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => (string) Meta::post( $post->ID, 'noindex' ),
				'options' => array(
					''        => __( 'Default for this type', 'wpinterface-seo' ),
					'index'   => __( 'Index', 'wpinterface-seo' ),
					'noindex' => __( 'Noindex', 'wpinterface-seo' ),
				),
			)
		);

		$document->add_control(
			'wpiseo_more',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => sprintf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) ),
					esc_html__( 'Content analysis, social and schema settings are in the WordPress editor.', 'wpinterface-seo' )
				),
			)
		);

		$document->end_controls_section();
	}

	/**
	 * Stores the SEO fields in post meta and keeps them out of Elementor's
	 * page settings.
	 *
	 * @param array<string, mixed>          $data     Data being saved.
	 * @param \Elementor\Core\Base\Document $document Elementor document.
	 * @return array<string, mixed>
	 */
	public function save( $data, $document ): array {
		$data = is_array( $data ) ? $data : array();
		$post = $this->post( $document );

		if ( null === $post || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return $data;
		}

		foreach ( self::CONTROLS as $control => $field ) {
			if ( ! array_key_exists( $control, $data['settings'] ) ) {
				continue;
			}

			$value = $data['settings'][ $control ];
			unset( $data['settings'][ $control ] );

			if ( 'focus_keywords' === $field ) {
				$current    = array_values( (array) Meta::post( $post->ID, 'focus_keywords' ) );
				$current[0] = (string) $value;
				$value      = array_values( array_filter( array_map( 'strval', $current ) ) );
			}

			Meta::write( 'post', $post->ID, $field, $value );
		}

		return $data;
	}

	/**
	 * The post behind a document, when the user may edit its SEO.
	 *
	 * @param mixed $document Elementor document.
	 */
	private function post( $document ): ?WP_Post {
		if ( ! is_object( $document ) || ! is_callable( array( $document, 'get_main_id' ) ) ) {
			return null;
		}

		$post = get_post( (int) $document->get_main_id() );

		if (
			! $post instanceof WP_Post
			|| ! in_array( $post->post_type, Editor::post_types(), true )
			|| ! current_user_can( Capabilities::EDIT_SEO )
			|| ! current_user_can( 'edit_post', $post->ID )
		) {
			return null;
		}

		return $post;
	}
}
