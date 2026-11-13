<?php
/**
 * WordPress Dashboard widget.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Dashboard;

use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Svg;

defined( 'ABSPATH' ) || exit;

/**
 * "SEO overview" on the WordPress Dashboard: a few figures and the top next
 * steps. Rendered in PHP from the cached dashboard figures; no scripts.
 */
final class Widget {

	public const ID = 'wpiseo_overview';

	private const STEPS = 3;

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
	}

	/**
	 * Adds the widget for users who can see the SEO dashboard.
	 */
	public function add(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return;
		}

		wp_add_dashboard_widget( self::ID, __( 'SEO overview', 'wpinterface-seo' ), array( $this, 'render' ) );
		wp_add_inline_style( 'dashboard', self::css() );
	}

	/**
	 * Prints the widget.
	 */
	public function render(): void {
		$report   = new Report( $this->plugin );
		$stats    = ( new Stats( $this->plugin ) )->get();
		$insights = $report->insights( $stats );
		$tiles    = $this->tiles( $stats );
		$more     = count( $insights ) - self::STEPS;

		echo '<div class="wpiseo-widget">';

		if ( array() !== $tiles ) {
			printf( '<div class="wpiseo-widget__tiles" style="grid-template-columns:repeat(%d,minmax(0,1fr))">', count( $tiles ) );

			foreach ( $tiles as $tile ) {
				printf(
					'<div class="wpiseo-widget__tile"><span class="wpiseo-widget__label">%1$s</span><span class="wpiseo-widget__value">%2$s%3$s</span><span class="wpiseo-widget__note%4$s">%5$s</span></div>',
					esc_html( $tile['label'] ),
					esc_html( $tile['value'] ),
					'' !== $tile['unit'] ? '<span class="wpiseo-widget__unit">' . esc_html( $tile['unit'] ) . '</span>' : '',
					'' !== $tile['tone'] ? ' is-' . esc_attr( $tile['tone'] ) : '',
					esc_html( $tile['note'] )
				);
			}

			echo '</div>';
		}

		printf( '<h3 class="wpiseo-widget__heading">%s</h3>', esc_html__( 'Next steps', 'wpinterface-seo' ) );

		if ( array() === $insights ) {
			printf( '<p class="wpiseo-widget__empty">%s</p>', esc_html__( 'Nothing needs your attention right now.', 'wpinterface-seo' ) );
		} else {
			echo '<ul class="wpiseo-widget__steps">';

			foreach ( array_slice( $insights, 0, self::STEPS ) as $insight ) {
				printf(
					'<li>%1$s<span class="wpiseo-widget__step">%2$s</span>%3$s</li>',
					wp_kses( self::icon( $insight['severity'] ), Svg::allowed() ),
					esc_html( $insight['title'] ),
					$insight['action'] ? sprintf( '<a href="%s">%s</a>', esc_url( $insight['action']['url'] ), esc_html( $insight['action']['label'] ) ) : ''
				);
			}

			echo '</ul>';
		}

		echo '</div>';

		printf(
			'<div class="wpiseo-widget__footer"><a href="%1$s">%2$s</a>%3$s</div>',
			esc_url( Report::page_url( 'dashboard' ) ),
			esc_html__( 'Open SEO dashboard', 'wpinterface-seo' ),
			$more > 0 ? '<span>' . esc_html(
				sprintf(
					/* translators: %s: number of further next steps. */
					_n( '%s more step', '%s more steps', $more, 'wpinterface-seo' ),
					number_format_i18n( $more )
				)
			) . '</span>' : ''
		);
	}

	/**
	 * Figures shown above the next steps.
	 *
	 * @param array<string, mixed> $stats Dashboard figures.
	 * @return list<array{label: string, value: string, unit: string, note: string, tone: string}>
	 */
	private function tiles( array $stats ): array {
		$tiles   = array();
		$search  = $stats['search'] ?? null;
		$content = $stats['content'];

		if ( is_array( $search ) ) {
			$change  = self::change( (float) $search['totals']['clicks'], (float) $search['previous']['clicks'] );
			$tiles[] = array(
				'label' => __( 'Clicks, 28 days', 'wpinterface-seo' ),
				'value' => number_format_i18n( $search['totals']['clicks'] ),
				'unit'  => '',
				'note'  => null === $change ? __( 'No earlier data', 'wpinterface-seo' ) : sprintf( '%+d%%', $change ),
				'tone'  => null === $change || 0 === $change ? '' : ( $change > 0 ? 'up' : 'down' ),
			);
		} else {
			$tiles[] = array(
				'label' => __( 'Indexable', 'wpinterface-seo' ),
				'value' => number_format_i18n( $content['indexable'] ),
				'unit'  => '',
				'note'  => sprintf(
					/* translators: %s: number of published posts. */
					__( 'of %s published', 'wpinterface-seo' ),
					number_format_i18n( $content['published'] )
				),
				'tone'  => '',
			);
		}

		$scored  = $content['published'] - $content['none'];
		$tiles[] = array(
			'label' => __( 'Average SEO score', 'wpinterface-seo' ),
			'value' => $scored > 0 ? number_format_i18n( (int) round( $content['scoreSum'] / $scored ) ) : '–',
			'unit'  => $scored > 0 ? '/100' : '',
			'note'  => $content['none'] > 0
				? sprintf(
					/* translators: %s: number of posts. */
					__( '%s not analyzed', 'wpinterface-seo' ),
					number_format_i18n( $content['none'] )
				)
				: __( 'All analyzed', 'wpinterface-seo' ),
			'tone'  => '',
		);

		/**
		 * Filters the figures in the "SEO overview" Dashboard widget.
		 *
		 * @param array                $tiles Tiles: label, value, unit, note, tone (''|up|down).
		 * @param array<string, mixed> $stats Dashboard figures.
		 */
		$tiles = (array) apply_filters( 'wpiseo_dashboard_widget_tiles', $tiles, $stats );

		return array_values(
			array_map(
				static fn( array $tile ): array => array(
					'label' => (string) ( $tile['label'] ?? '' ),
					'value' => (string) ( $tile['value'] ?? '' ),
					'unit'  => (string) ( $tile['unit'] ?? '' ),
					'note'  => (string) ( $tile['note'] ?? '' ),
					'tone'  => in_array( $tile['tone'] ?? '', array( 'up', 'down' ), true ) ? (string) $tile['tone'] : '',
				),
				array_filter( $tiles, 'is_array' )
			)
		);
	}

	/**
	 * Percentage change, or null without a previous value.
	 *
	 * @param float $current  Current value.
	 * @param float $previous Previous value.
	 */
	private static function change( float $current, float $previous ): ?int {
		return $previous > 0 ? (int) round( ( $current - $previous ) / $previous * 100 ) : null;
	}

	/**
	 * Severity icon with an accessible name.
	 *
	 * @param string $severity critical|warning|notice.
	 */
	private static function icon( string $severity ): string {
		$icons = array(
			'critical' => array( __( 'Critical', 'wpinterface-seo' ), '<circle cx="10" cy="10" r="9" fill="#d63638"/><path d="M10 5.5v5.5M10 13.8v.4" stroke="#fff" stroke-width="2" stroke-linecap="round"/>' ),
			'warning'  => array( __( 'Should fix', 'wpinterface-seo' ), '<path d="M10 1.8 19 17.5H1L10 1.8Z" fill="#dba617"/><path d="M10 7.5v4.5M10 14.6v.3" stroke="#1d2327" stroke-width="1.8" stroke-linecap="round"/>' ),
			'notice'   => array( __( 'Suggestion', 'wpinterface-seo' ), '<circle cx="10" cy="10" r="8.2" fill="none" stroke="#3858e9" stroke-width="1.6"/><path d="M10 9v5M10 6.2v.3" stroke="#3858e9" stroke-width="1.8" stroke-linecap="round"/>' ),
		);
		$icon  = $icons[ $severity ] ?? $icons['notice'];

		return sprintf( '<svg class="wpiseo-widget__icon" width="18" height="18" viewBox="0 0 20 20" role="img" aria-label="%1$s">%2$s</svg>', esc_attr( $icon[0] ), $icon[1] );
	}

	/**
	 * Widget styles (added to the Dashboard's own stylesheet).
	 */
	private static function css(): string {
		return '#wpiseo_overview .inside{margin:0;padding:0}'
			. '.wpiseo-widget{padding:12px}'
			. '.wpiseo-widget__tiles{display:grid;gap:8px}'
			. '.wpiseo-widget__tile{display:flex;flex-direction:column;min-width:0;background:#f6f7f7;border:1px solid #f0f0f1;border-radius:2px;padding:8px 10px}'
			. '.wpiseo-widget__label,.wpiseo-widget__note{font-size:12px;color:#646970}'
			. '.wpiseo-widget__value{font-size:18px;font-weight:600;line-height:1.4;color:#1d2327}'
			. '.wpiseo-widget__unit{font-size:12px;font-weight:400;color:#646970}'
			. '.wpiseo-widget__note.is-up{color:#007017}.wpiseo-widget__note.is-down{color:#b32d2e}'
			. '.wpiseo-widget__heading{margin:16px 0 4px;font-size:13px;font-weight:600}'
			. '.wpiseo-widget__steps{margin:0}'
			. '.wpiseo-widget__steps li{display:flex;gap:8px;align-items:flex-start;margin:0;padding:8px 0;border-bottom:1px solid #f0f0f1}'
			. '.wpiseo-widget__steps li:last-child{border-bottom:0}'
			. '.wpiseo-widget__icon{flex:0 0 18px;margin-top:1px}'
			. '.wpiseo-widget__step{flex:1 1 auto}'
			. '.wpiseo-widget__empty{margin:4px 0 0;color:#646970}'
			. '.wpiseo-widget__footer{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:10px 12px;border-top:1px solid #f0f0f1;background:#f6f7f7;font-size:12px;color:#646970}'
			. '.wpiseo-widget__footer a{font-size:13px}';
	}
}
