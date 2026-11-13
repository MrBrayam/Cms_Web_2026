<?php
/**
 * Front-end SEO inspector in the admin bar.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Admin;

use WP_Admin_Bar;
use WP_Post;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Shows site managers what the plugin printed for the current page: title,
 * description, robots, canonical, social image and schema types. Values are
 * captured while the page renders, so they match the actual output.
 */
final class HeadInspector {

	/**
	 * Final robots directives (from the `wp_robots` filter).
	 *
	 * @var array<string, mixed>
	 */
	private array $robots = array();

	/**
	 * Schema node types printed on the page.
	 *
	 * @var list<string>
	 */
	private array $schema = array();

	/**
	 * Registers hooks (front end only).
	 */
	public function register(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		add_filter( 'wp_robots', array( $this, 'capture_robots' ), PHP_INT_MAX );
		add_filter( 'wpiseo_schema_graph', array( $this, 'capture_schema' ), PHP_INT_MAX );
		add_action( 'admin_bar_menu', array( $this, 'add_nodes' ), 95 );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Keeps long values on one line (the admin bar renders in the footer, after styles print).
	 */
	public function styles(): void {
		if ( is_admin_bar_showing() && current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_add_inline_style( 'admin-bar', '#wp-admin-bar-wpiseo-inspector .ab-submenu .ab-item{max-width:560px;overflow:hidden;text-overflow:ellipsis}#wp-admin-bar-wpiseo-inspector .ab-icon:before{top:2px}' );
		}
	}

	/**
	 * Remembers the final robots directives.
	 *
	 * @param array<string, mixed> $robots Directives.
	 * @return array<string, mixed>
	 */
	public function capture_robots( $robots ): array {
		$this->robots = is_array( $robots ) ? $robots : array();

		return $this->robots;
	}

	/**
	 * Remembers the schema node types.
	 *
	 * @param array<int, array<string, mixed>> $graph Nodes.
	 * @return array<int, array<string, mixed>>
	 */
	public function capture_schema( $graph ): array {
		$graph        = is_array( $graph ) ? $graph : array();
		$this->schema = array();

		foreach ( $graph as $node ) {
			$type = $node['@type'] ?? '';
			$type = is_array( $type ) ? implode( ' + ', array_map( 'strval', $type ) ) : (string) $type;

			if ( '' !== $type ) {
				$this->schema[] = $type;
			}
		}

		return $graph;
	}

	/**
	 * Adds the "SEO" menu.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public function add_nodes( $bar ): void {
		if ( ! $bar instanceof WP_Admin_Bar || is_admin() || ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return;
		}

		$plugin = Plugin::instance();

		$bar->add_node(
			array(
				'id'    => 'wpiseo-inspector',
				'title' => '<span class="ab-icon dashicons dashicons-search" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'SEO', 'wpinterface-seo' ) . '</span>',
				'href'  => admin_url( 'admin.php?page=' . Admin::MENU_SLUG ),
			)
		);

		if ( ! $plugin->output_enabled() ) {
			$this->item( $bar, 'paused', __( 'Output is paused because another SEO plugin is active.', 'wpinterface-seo' ) );
			return;
		}

		$presentation = $plugin->presentation();
		$robots       = array();

		foreach ( $this->robots as $name => $value ) {
			if ( false !== $value ) {
				$robots[] = true === $value ? (string) $name : $name . ':' . $value;
			}
		}

		$rows = array(
			'title'       => array( __( 'Title', 'wpinterface-seo' ), wp_get_document_title() ),
			'description' => array( __( 'Description', 'wpinterface-seo' ), $presentation->description() ),
			'robots'      => array( __( 'Robots', 'wpinterface-seo' ), implode( ', ', $robots ) ),
			'canonical'   => array( __( 'Canonical', 'wpinterface-seo' ), $presentation->canonical() ),
			'og-image'    => array( __( 'Social image', 'wpinterface-seo' ), (string) ( $presentation->og_image()['url'] ?? '' ) ),
			'schema'      => array( __( 'Schema', 'wpinterface-seo' ), implode( ', ', $this->schema ) ),
		);

		foreach ( $rows as $id => $row ) {
			$value = '' !== $row[1] ? $row[1] : __( '(none)', 'wpinterface-seo' );

			$this->item(
				$bar,
				$id,
				sprintf( '<strong>%s:</strong> %s', esc_html( $row[0] ), esc_html( mb_strimwidth( $value, 0, 110, '…' ) ) ),
				esc_attr( $value )
			);
		}

		$object = get_queried_object();

		if ( $object instanceof WP_Post && current_user_can( 'edit_post', $object->ID ) ) {
			$bar->add_node(
				array(
					'parent' => 'wpiseo-inspector',
					'id'     => 'wpiseo-inspector-edit',
					'title'  => esc_html__( 'Edit SEO settings', 'wpinterface-seo' ),
					'href'   => (string) get_edit_post_link( $object->ID ),
				)
			);
		}

		$url = $presentation->canonical();

		if ( '' !== $url ) {
			$bar->add_node(
				array(
					'parent' => 'wpiseo-inspector',
					'id'     => 'wpiseo-inspector-rich-results',
					'title'  => esc_html__( 'Test in Google Rich Results Test', 'wpinterface-seo' ),
					'href'   => 'https://search.google.com/test/rich-results?url=' . rawurlencode( $url ),
					'meta'   => array( 'target' => '_blank' ),
				)
			);
		}
	}

	/**
	 * Adds a text row.
	 *
	 * @param WP_Admin_Bar $bar   Admin bar.
	 * @param string       $id    Row ID.
	 * @param string       $html  Escaped HTML.
	 * @param string       $title Escaped tooltip.
	 */
	private function item( WP_Admin_Bar $bar, string $id, string $html, string $title = '' ): void {
		$bar->add_node(
			array(
				'parent' => 'wpiseo-inspector',
				'id'     => 'wpiseo-inspector-' . $id,
				'title'  => $html,
				'meta'   => array( 'title' => $title ),
			)
		);
	}
}
