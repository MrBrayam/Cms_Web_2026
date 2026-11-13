<?php
/**
 * Local SEO module.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Modules\Local;

use WPInterface\SEO\Local\Business;
use WPInterface\SEO\Modules\Module;
use WPInterface\SEO\Presentation\Presentation;
use WPInterface\SEO\Support\Blocks;
use WPInterface\SEO\Support\Capabilities;
use WPInterface\SEO\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * One business location: LocalBusiness schema, plus an address/hours/map
 * block and `[wpiseo_local]` shortcode.
 */
final class LocalModule extends Module {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'business_type' => 'LocalBusiness',
			'name'          => '',
			'street'        => '',
			'locality'      => '',
			'region'        => '',
			'postal_code'   => '',
			'country'       => '',
			'latitude'      => '',
			'longitude'     => '',
			'phone'         => '',
			'email'         => '',
			'price_range'   => '',
			'open_24_7'     => false,
			'hours'         => Business::default_hours(),
			'map'           => 'link',
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
		$text  = static fn( string $key, int $max = 200 ): string => mb_substr( sanitize_text_field( (string) ( $values[ $key ] ?? '' ) ), 0, $max );
		$coord = static function ( string $key, float $limit ) use ( $values ): string {
			$value = trim( (string) ( $values[ $key ] ?? '' ) );
			return is_numeric( $value ) && abs( (float) $value ) <= $limit ? (string) round( (float) $value, 6 ) : '';
		};
		$time  = static fn( $value, string $fallback ): string => is_string( $value ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
		$hours = array();

		foreach ( Business::DAYS as $day ) {
			$day_values = is_array( $values['hours'][ $day ] ?? null ) ? $values['hours'][ $day ] : array();

			$hours[ $day ] = array(
				'open'   => $time( $day_values['open'] ?? null, $defaults['hours'][ $day ]['open'] ),
				'close'  => $time( $day_values['close'] ?? null, $defaults['hours'][ $day ]['close'] ),
				'closed' => Sanitize::to_bool( $day_values['closed'] ?? $defaults['hours'][ $day ]['closed'] ),
			);
		}

		$email = sanitize_email( (string) ( $values['email'] ?? '' ) );

		return array(
			'business_type' => in_array( $values['business_type'] ?? '', Business::TYPES, true ) ? $values['business_type'] : 'LocalBusiness',
			'name'          => $text( 'name' ),
			'street'        => $text( 'street' ),
			'locality'      => $text( 'locality', 100 ),
			'region'        => $text( 'region', 100 ),
			'postal_code'   => $text( 'postal_code', 20 ),
			'country'       => strtoupper( (string) preg_replace( '/[^a-zA-Z]/', '', $text( 'country', 2 ) ) ),
			'latitude'      => $coord( 'latitude', 90 ),
			'longitude'     => $coord( 'longitude', 180 ),
			'phone'         => $text( 'phone', 40 ),
			'email'         => is_email( $email ) ? $email : '',
			'price_range'   => $text( 'price_range', 99 ),
			'open_24_7'     => Sanitize::to_bool( $values['open_24_7'] ?? false ),
			'hours'         => $hours,
			'map'           => in_array( $values['map'] ?? '', array( 'none', 'link', 'embed' ), true ) ? $values['map'] : 'link',
		);
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_filter( 'wpiseo_schema_publisher', array( $this, 'schema' ), 10, 2 );
		add_shortcode( 'wpiseo_local', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );

		if ( is_admin() ) {
			add_filter( 'wpiseo_admin_pages', array( $this, 'admin_pages' ), 35 );
		}
	}

	/**
	 * Adds LocalBusiness type and details to the organization node.
	 *
	 * @param array<string, mixed>|null $node         Publisher node.
	 * @param Presentation              $presentation Page values.
	 * @return array<string, mixed>|null
	 */
	public function schema( $node, $presentation ) {
		unset( $presentation );

		if ( ! is_array( $node ) || 'person' === $this->plugin->options()->get( 'schema.site_type' ) ) {
			return $node;
		}

		$business   = new Business( $this->plugin->options() );
		$properties = $business->schema_properties();

		if ( array() === $properties ) {
			return $node;
		}

		$node['@type'] = array( 'Organization', (string) $this->plugin->options()->get( 'local.business_type', 'LocalBusiness' ) );

		if ( empty( $node['image'] ) && ! empty( $node['logo'] ) ) {
			$node['image'] = array( '@id' => $node['logo']['@id'] ?? '' );
		}

		return array_merge( $node, $properties );
	}

	/**
	 * `[wpiseo_local show="name,address,phone,hours,map"]`.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'show' => 'name,address,phone,hours' ), is_array( $atts ) ? $atts : array(), 'wpiseo_local' );
		$show = array_fill_keys( array_map( 'trim', explode( ',', (string) $atts['show'] ) ), true );

		return ( new Business( $this->plugin->options() ) )->html( $show );
	}

	/**
	 * Registers the Local Business block.
	 */
	public function register_block(): void {
		Blocks::register_script();

		register_block_type(
			WPISEO_PATH . 'blocks/local-business',
			array(
				'render_callback' => function ( $attributes ): string {
					$attributes = is_array( $attributes ) ? $attributes : array();
					$html       = ( new Business( $this->plugin->options() ) )->html(
						array(
							'name'    => ! empty( $attributes['showName'] ),
							'address' => ! empty( $attributes['showAddress'] ),
							'phone'   => ! empty( $attributes['showPhone'] ),
							'email'   => ! empty( $attributes['showEmail'] ),
							'hours'   => ! empty( $attributes['showHours'] ),
							'map'     => ! empty( $attributes['showMap'] ),
						)
					);

					return '' === $html ? '' : sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
				},
			)
		);
	}

	/**
	 * Adds the Local SEO page.
	 *
	 * @param array<string, array<string, mixed>> $pages Pages.
	 * @return array<string, array<string, mixed>>
	 */
	public function admin_pages( $pages ): array {
		$pages = is_array( $pages ) ? $pages : array();

		$pages['local'] = array(
			'title'      => __( 'Local SEO', 'wpinterface-seo' ),
			'menu_title' => __( 'Local SEO', 'wpinterface-seo' ),
			'capability' => Capabilities::MANAGE_SETTINGS,
		);

		return $pages;
	}
}
