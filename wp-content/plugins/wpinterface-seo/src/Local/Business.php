<?php
/**
 * Local business data.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Local;

use WPInterface\SEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Builds LocalBusiness schema properties and the address/hours/map HTML
 * from the `local` settings section.
 */
final class Business {

	public const DAYS = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

	/**
	 * Common schema.org LocalBusiness subtypes.
	 */
	public const TYPES = array( 'LocalBusiness', 'AccountingService', 'AnimalShelter', 'Attorney', 'AutoDealer', 'AutoRepair', 'AutomotiveBusiness', 'Bakery', 'BarOrPub', 'BeautySalon', 'BedAndBreakfast', 'BookStore', 'CafeOrCoffeeShop', 'ChildCare', 'ClothingStore', 'DaySpa', 'Dentist', 'DryCleaningOrLaundry', 'Electrician', 'ElectronicsStore', 'EmergencyService', 'EmploymentAgency', 'EntertainmentBusiness', 'ExerciseGym', 'FinancialService', 'FoodEstablishment', 'FurnitureStore', 'GeneralContractor', 'GroceryStore', 'HairSalon', 'HardwareStore', 'HealthAndBeautyBusiness', 'HomeAndConstructionBusiness', 'Hotel', 'HousePainter', 'InsuranceAgency', 'JewelryStore', 'LegalService', 'Library', 'Locksmith', 'LodgingBusiness', 'MedicalBusiness', 'MovingCompany', 'NailSalon', 'Notary', 'Optician', 'PetStore', 'Pharmacy', 'Physician', 'Plumber', 'ProfessionalService', 'RealEstateAgent', 'Restaurant', 'RoofingContractor', 'SelfStorage', 'ShoeStore', 'ShoppingCenter', 'SportsActivityLocation', 'Store', 'TouristInformationCenter', 'TravelAgency', 'VeterinaryCare' );

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Location values; null reads the Local SEO settings (`local.*`).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data;

	/**
	 * Constructor.
	 *
	 * @param Options                   $options Settings.
	 * @param array<string, mixed>|null $data    Values for another location (same keys as the
	 *                                           `local` settings section); null for the site's own.
	 */
	public function __construct( Options $options, ?array $data = null ) {
		$this->options = $options;
		$this->data    = $data;
	}

	/**
	 * Default opening hours (Mon–Fri 9–17).
	 *
	 * @return array<string, array{open: string, close: string, closed: bool}>
	 */
	public static function default_hours(): array {
		$hours = array();

		foreach ( self::DAYS as $day ) {
			$hours[ $day ] = array(
				'open'   => '09:00',
				'close'  => '17:00',
				'closed' => in_array( $day, array( 'saturday', 'sunday' ), true ),
			);
		}

		return $hours;
	}

	/**
	 * Whether enough data exists for LocalBusiness markup (an address is required).
	 */
	public function is_complete(): bool {
		return '' !== $this->get( 'street' ) && '' !== $this->get( 'locality' );
	}

	/**
	 * Schema properties to merge into the publisher node.
	 *
	 * @return array<string, mixed>
	 */
	public function schema_properties(): array {
		if ( ! $this->is_complete() ) {
			return array();
		}

		$properties = array(
			'address'    => array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $this->get( 'street' ),
					'addressLocality' => $this->get( 'locality' ),
					'addressRegion'   => $this->get( 'region' ),
					'postalCode'      => $this->get( 'postal_code' ),
					'addressCountry'  => strtoupper( $this->get( 'country' ) ),
				)
			),
			'telephone'  => $this->get( 'phone' ),
			'email'      => $this->get( 'email' ),
			'priceRange' => $this->get( 'price_range' ),
		);

		$lat = $this->get( 'latitude' );
		$lng = $this->get( 'longitude' );

		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			$properties['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => round( (float) $lat, 5 ),
				'longitude' => round( (float) $lng, 5 ),
			);

			$properties['hasMap'] = $this->map_link();
		}

		$hours = $this->opening_hours();

		if ( $hours ) {
			$properties['openingHoursSpecification'] = $hours;
		}

		return array_filter( $properties );
	}

	/**
	 * OpeningHoursSpecification entries, grouping days with the same hours.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function opening_hours(): array {
		if ( $this->value( 'open_24_7', false ) ) {
			return array(
				array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array_map( 'ucfirst', self::DAYS ),
					'opens'     => '00:00',
					'closes'    => '23:59',
				),
			);
		}

		$groups = array();

		foreach ( $this->hours() as $day => $hours ) {
			if ( $hours['closed'] ) {
				continue;
			}

			$key = $hours['open'] . '-' . $hours['close'];

			$groups[ $key ]['days'][] = ucfirst( $day );
			$groups[ $key ]['open']   = $hours['open'];
			$groups[ $key ]['close']  = $hours['close'];
		}

		$specs = array();

		foreach ( $groups as $group ) {
			$specs[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $group['days'],
				'opens'     => $group['open'],
				'closes'    => $group['close'],
			);
		}

		return $specs;
	}

	/**
	 * Address / hours / phone / map HTML.
	 *
	 * @param array<string, bool> $show Parts: name, address, hours, phone, email, map.
	 */
	public function html( array $show ): string {
		$parts = array();

		if ( ! empty( $show['name'] ) ) {
			$parts[] = '<p class="wpiseo-local__name"><strong>' . esc_html( $this->name() ) . '</strong></p>';
		}

		if ( ! empty( $show['address'] ) && $this->is_complete() ) {
			$line2   = trim( implode( ' ', array_filter( array( $this->get( 'postal_code' ), $this->get( 'locality' ) ) ) ) );
			$lines   = array_filter( array( $this->get( 'street' ), $line2, $this->get( 'region' ), $this->country_name() ) );
			$parts[] = '<address class="wpiseo-local__address">' . implode( '<br />', array_map( 'esc_html', $lines ) ) . '</address>';
		}

		if ( ! empty( $show['phone'] ) && '' !== $this->get( 'phone' ) ) {
			$parts[] = sprintf( '<p class="wpiseo-local__phone"><a href="tel:%s">%s</a></p>', esc_attr( (string) preg_replace( '/[^0-9+]/', '', $this->get( 'phone' ) ) ), esc_html( $this->get( 'phone' ) ) );
		}

		if ( ! empty( $show['email'] ) && is_email( $this->get( 'email' ) ) ) {
			$parts[] = sprintf( '<p class="wpiseo-local__email"><a href="mailto:%1$s">%1$s</a></p>', esc_html( antispambot( $this->get( 'email' ) ) ) );
		}

		if ( ! empty( $show['hours'] ) ) {
			$parts[] = $this->hours_html();
		}

		if ( ! empty( $show['map'] ) ) {
			$parts[] = $this->map_html();
		}

		$parts = array_filter( $parts );

		return $parts ? '<div class="wpiseo-local">' . implode( '', $parts ) . '</div>' : '';
	}

	/**
	 * Business name (falls back to the schema organization name, then the site title).
	 */
	public function name(): string {
		foreach ( array( $this->get( 'name' ), (string) $this->options->get( 'schema.name', '' ) ) as $name ) {
			if ( '' !== trim( $name ) ) {
				return trim( $name );
			}
		}

		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Link to the location on OpenStreetMap.
	 */
	public function map_link(): string {
		$lat = $this->get( 'latitude' );
		$lng = $this->get( 'longitude' );

		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=17/%1$s/%2$s', rawurlencode( $lat ), rawurlencode( $lng ) );
		}

		$query = implode( ', ', array_filter( array( $this->get( 'street' ), $this->get( 'locality' ), $this->get( 'country' ) ) ) );

		return '' !== $query ? 'https://www.openstreetmap.org/search?query=' . rawurlencode( $query ) : '';
	}

	/**
	 * Opening hours table.
	 */
	private function hours_html(): string {
		$labels = array(
			'monday'    => __( 'Monday', 'wpinterface-seo' ),
			'tuesday'   => __( 'Tuesday', 'wpinterface-seo' ),
			'wednesday' => __( 'Wednesday', 'wpinterface-seo' ),
			'thursday'  => __( 'Thursday', 'wpinterface-seo' ),
			'friday'    => __( 'Friday', 'wpinterface-seo' ),
			'saturday'  => __( 'Saturday', 'wpinterface-seo' ),
			'sunday'    => __( 'Sunday', 'wpinterface-seo' ),
		);

		if ( $this->value( 'open_24_7', false ) ) {
			return '<p class="wpiseo-local__hours">' . esc_html__( 'Open 24 hours, 7 days a week', 'wpinterface-seo' ) . '</p>';
		}

		$format = (string) get_option( 'time_format', 'H:i' );
		$rows   = '';

		foreach ( $this->hours() as $day => $hours ) {
			$time = $hours['closed']
				? esc_html__( 'Closed', 'wpinterface-seo' )
				: esc_html( wp_date( $format, (int) strtotime( '2000-01-01 ' . $hours['open'] . ' UTC' ), new \DateTimeZone( 'UTC' ) ) . ' – ' . wp_date( $format, (int) strtotime( '2000-01-01 ' . $hours['close'] . ' UTC' ), new \DateTimeZone( 'UTC' ) ) );

			$rows .= sprintf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $labels[ $day ] ), $time );
		}

		return '<table class="wpiseo-local__hours"><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Map link, or an embedded OpenStreetMap when enabled.
	 */
	private function map_html(): string {
		$mode = (string) $this->value( 'map', 'link' );
		$lat  = $this->get( 'latitude' );
		$lng  = $this->get( 'longitude' );
		$link = $this->map_link();

		if ( 'none' === $mode || '' === $link ) {
			return '';
		}

		if ( 'embed' === $mode && is_numeric( $lat ) && is_numeric( $lng ) ) {
			$bbox = implode( ',', array( (float) $lng - 0.006, (float) $lat - 0.003, (float) $lng + 0.006, (float) $lat + 0.003 ) );

			return sprintf(
				'<iframe class="wpiseo-local__map" title="%1$s" loading="lazy" referrerpolicy="no-referrer" src="%2$s" style="width:100%%;height:300px;border:0"></iframe><p><a href="%3$s">%4$s</a></p>',
				esc_attr__( 'Map', 'wpinterface-seo' ),
				esc_url( 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( $bbox ) . '&layer=mapnik&marker=' . rawurlencode( $lat . ',' . $lng ) ),
				esc_url( $link ),
				esc_html__( 'View larger map', 'wpinterface-seo' )
			);
		}

		return sprintf( '<p class="wpiseo-local__map-link"><a href="%s">%s</a></p>', esc_url( $link ), esc_html__( 'View on map', 'wpinterface-seo' ) );
	}

	/**
	 * Effective hours per day.
	 *
	 * @return array<string, array{open: string, close: string, closed: bool}>
	 */
	private function hours(): array {
		$stored = $this->value( 'hours', array() );
		$hours  = self::default_hours();

		foreach ( self::DAYS as $day ) {
			if ( is_array( $stored ) && isset( $stored[ $day ] ) && is_array( $stored[ $day ] ) ) {
				$hours[ $day ] = array_merge( $hours[ $day ], $stored[ $day ] );
			}
		}

		return $hours;
	}

	/**
	 * Country name for display (WordPress has no country list, so the code is shown).
	 */
	private function country_name(): string {
		return strtoupper( $this->get( 'country' ) );
	}

	/**
	 * A `local.*` setting as a trimmed string.
	 *
	 * @param string $key Key.
	 */
	private function get( string $key ): string {
		$value = $this->value( $key, '' );

		return trim( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Raw value from the location data or the Local SEO settings.
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	private function value( string $key, $default_value ) {
		if ( null !== $this->data ) {
			return $this->data[ $key ] ?? $default_value;
		}

		return $this->options->get( 'local.' . $key, $default_value );
	}
}
