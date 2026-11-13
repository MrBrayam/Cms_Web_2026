<?php
/**
 * Content analysis module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Analysis;

use WP_Query;
use WPInterface\SEO\Editor\Editor;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * The analysis itself runs in the editor (JavaScript, no external calls);
 * this module adds score columns, sorting and filtering to post lists.
 */
final class AnalysisModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'readability' => true,
			'columns'     => true,
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
		return array(
			'readability' => Sanitize::to_bool( $values['readability'] ?? $defaults['readability'] ),
			'columns'     => Sanitize::to_bool( $values['columns'] ?? $defaults['columns'] ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		if ( is_admin() && $this->plugin->options()->get( 'analysis.columns', true ) ) {
			add_action( 'admin_init', array( $this, 'hook_list_tables' ) );
			add_action( 'pre_get_posts', array( $this, 'sort_and_filter' ) );
		}
	}

	/**
	 * Hooks columns for every post type with the SEO panel.
	 */
	public function hook_list_tables(): void {
		if ( ! current_user_can( Capabilities::EDIT_SEO ) ) {
			return;
		}

		foreach ( Editor::post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'columns' ) );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'sortable_columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}

		add_filter( 'default_hidden_columns', array( $this, 'default_hidden_columns' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filter_dropdown' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'column_styles' ) );
	}

	/**
	 * Adds the columns before the date column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();
		$ours    = array( 'wpiseo-score' => __( 'SEO', 'wpinterface-seo' ) );

		if ( $this->plugin->options()->get( 'analysis.readability', true ) ) {
			$ours['wpiseo-readability'] = __( 'Readability', 'wpinterface-seo' );
		}

		$position = array_search( 'date', array_keys( $columns ), true );

		if ( false === $position ) {
			return array_merge( $columns, $ours );
		}

		return array_merge( array_slice( $columns, 0, (int) $position, true ), $ours, array_slice( $columns, (int) $position, null, true ) );
	}

	/**
	 * Hides the readability column until the user enables it in Screen Options,
	 * keeping list tables usable on narrow screens.
	 *
	 * @param string[]   $hidden Hidden column IDs.
	 * @param \WP_Screen $screen Screen.
	 * @return string[]
	 */
	public function default_hidden_columns( $hidden, $screen = null ): array {
		$hidden = is_array( $hidden ) ? $hidden : array();

		if ( $screen instanceof \WP_Screen && 'edit' === $screen->base ) {
			$hidden[] = 'wpiseo-readability';
		}

		return $hidden;
	}

	/**
	 * Makes the columns sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();

		$columns['wpiseo-score']       = 'wpiseo-score';
		$columns['wpiseo-readability'] = 'wpiseo-readability';

		return $columns;
	}

	/**
	 * Prints a column cell.
	 *
	 * @param string $column  Column ID.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ): void {
		$key = array(
			'wpiseo-score'       => 'score',
			'wpiseo-readability' => 'readability_score',
		)[ $column ] ?? '';

		if ( '' === $key ) {
			return;
		}

		// Registered meta returns its default (0) when missing, so check existence.
		$exists  = metadata_exists( 'post', (int) $post_id, Fields::PREFIX . $key );
		$score   = $exists ? (int) get_post_meta( (int) $post_id, Fields::PREFIX . $key, true ) : null;
		$band    = self::band( $score );
		$noindex = 'wpiseo-score' === $column && 'noindex' === get_post_meta( (int) $post_id, Fields::PREFIX . 'noindex', true );

		printf(
			'<span class="wpiseo-dot wpiseo-dot--%1$s" title="%2$s"></span><span class="screen-reader-text">%2$s</span>%3$s',
			esc_attr( $band ),
			esc_attr( self::band_label( $band, $score ) ),
			$noindex ? ' <span class="wpiseo-noindex">' . esc_html__( 'noindex', 'wpinterface-seo' ) . '</span>' : ''
		);
	}

	/**
	 * Score band dropdown above the list.
	 *
	 * @param string $post_type Post type.
	 */
	public function filter_dropdown( $post_type ): void {
		if ( ! in_array( (string) $post_type, Editor::post_types(), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$current = isset( $_GET['wpiseo_band'] ) ? sanitize_key( wp_unslash( $_GET['wpiseo_band'] ) ) : '';
		$bands   = array(
			''     => __( 'All SEO scores', 'wpinterface-seo' ),
			'good' => __( 'SEO: good', 'wpinterface-seo' ),
			'ok'   => __( 'SEO: needs improvement', 'wpinterface-seo' ),
			'bad'  => __( 'SEO: poor', 'wpinterface-seo' ),
			'none' => __( 'SEO: not analyzed', 'wpinterface-seo' ),
		);

		echo '<label class="screen-reader-text" for="wpiseo-band">' . esc_html__( 'Filter by SEO score', 'wpinterface-seo' ) . '</label>';
		echo '<select name="wpiseo_band" id="wpiseo-band">';
		foreach ( $bands as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Applies sorting and band filtering on post list screens.
	 *
	 * @param WP_Query $query Query.
	 */
	public function sort_and_filter( $query ): void {
		if ( ! $query instanceof WP_Query || ! $query->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		$orderby = (string) $query->get( 'orderby' );

		if ( in_array( $orderby, array( 'wpiseo-score', 'wpiseo-readability' ), true ) ) {
			$key = Fields::PREFIX . ( 'wpiseo-score' === $orderby ? 'score' : 'readability_score' );

			// Named clauses keep posts without a score in the list.
			$query->set(
				'meta_query',
				array(
					'relation'     => 'OR',
					'wpiseo_score' => array(
						'key'  => $key,
						'type' => 'NUMERIC',
					),
					array(
						'key'     => $key,
						'compare' => 'NOT EXISTS',
					),
				)
			);
			$query->set( 'orderby', 'wpiseo_score' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$band = isset( $_GET['wpiseo_band'] ) ? sanitize_key( wp_unslash( $_GET['wpiseo_band'] ) ) : '';

		if ( '' === $band ) {
			return;
		}

		$key        = Fields::PREFIX . 'score';
		$meta_query = (array) $query->get( 'meta_query' );
		$ranges     = array(
			'good' => array( 70, 100 ),
			'ok'   => array( 40, 69 ),
			'bad'  => array( 0, 39 ),
		);

		if ( 'none' === $band ) {
			$meta_query[] = array(
				'key'     => $key,
				'compare' => 'NOT EXISTS',
			);
		} elseif ( isset( $ranges[ $band ] ) ) {
			$meta_query[] = array(
				'key'     => $key,
				'value'   => $ranges[ $band ],
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	/**
	 * Column styles (tiny; added only on post list screens).
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function column_styles( $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		wp_register_style( 'wpiseo-columns', false, array(), WPISEO_VERSION );
		wp_enqueue_style( 'wpiseo-columns' );
		wp_add_inline_style( 'wpiseo-columns', '.column-wpiseo-score,.column-wpiseo-readability{width:4.5em}.wpiseo-dot{display:inline-block;width:12px;height:12px;border-radius:50%;background:#c3c4c7;vertical-align:middle}.wpiseo-dot--good{background:#00a32a}.wpiseo-dot--ok{background:#dba617}.wpiseo-dot--bad{background:#d63638}.wpiseo-noindex{font-size:11px;color:#646970}' );
	}

	/**
	 * Score band.
	 *
	 * @param int|null $score Score or null when not analysed.
	 */
	public static function band( ?int $score ): string {
		if ( null === $score ) {
			return 'none';
		}

		if ( $score >= 70 ) {
			return 'good';
		}

		return $score >= 40 ? 'ok' : 'bad';
	}

	/**
	 * Accessible label for a band.
	 *
	 * @param string   $band  Band.
	 * @param int|null $score Score.
	 */
	private static function band_label( string $band, ?int $score ): string {
		$labels = array(
			'good' => __( 'Good', 'wpinterface-seo' ),
			'ok'   => __( 'Needs improvement', 'wpinterface-seo' ),
			'bad'  => __( 'Poor', 'wpinterface-seo' ),
			'none' => __( 'Not analyzed yet', 'wpinterface-seo' ),
		);

		/* translators: 1: band label, 2: score out of 100. */
		return null === $score ? $labels['none'] : sprintf( __( '%1$s (%2$d/100)', 'wpinterface-seo' ), $labels[ $band ], $score );
	}
}
