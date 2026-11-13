<?php
/**
 * Per-post schema types.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Schema;

use WP_Post;
use WPInterface\SEO\Support\Images;

defined( 'ABSPATH' ) || exit;

/**
 * Definitions, sanitization and JSON-LD building for the extra entity a post
 * can describe (stored in `_wpiseo_schema` as { type, data }).
 *
 * Field types: text, textarea, html, url, number, date, datetime, duration,
 * image, select, boolean, list (array of strings), repeater (array of rows).
 */
final class Types {

	/**
	 * Type keys in display order.
	 */
	public const KEYS = array( 'FAQPage', 'HowTo', 'Recipe', 'Event', 'VideoObject', 'JobPosting', 'Course', 'SoftwareApplication', 'Book', 'Product' );

	/**
	 * Definitions with translated labels. Call after `init`.
	 *
	 * @return array<string, array{label: string, description: string, fields: array<string, array<string, mixed>>}>
	 */
	public static function definitions(): array {
		$currency = array(
			'type'  => 'text',
			'label' => __( 'Currency (ISO code, e.g. USD)', 'wpinterface-seo' ),
		);

		$address = array(
			'street'      => array(
				'type'  => 'text',
				'label' => __( 'Street address', 'wpinterface-seo' ),
			),
			'locality'    => array(
				'type'  => 'text',
				'label' => __( 'City', 'wpinterface-seo' ),
			),
			'region'      => array(
				'type'  => 'text',
				'label' => __( 'State / region', 'wpinterface-seo' ),
			),
			'postal_code' => array(
				'type'  => 'text',
				'label' => __( 'Postal code', 'wpinterface-seo' ),
			),
			'country'     => array(
				'type'  => 'text',
				'label' => __( 'Country code (e.g. US, NP)', 'wpinterface-seo' ),
			),
		);

		return array(
			'FAQPage'             => array(
				'label'       => __( 'FAQ', 'wpinterface-seo' ),
				'description' => __( 'Questions and answers shown on this page. Google no longer shows FAQ rich results, but the markup still helps search engines and AI tools understand the content.', 'wpinterface-seo' ),
				'fields'      => array(
					'questions' => array(
						'type'      => 'repeater',
						'label'     => __( 'Questions', 'wpinterface-seo' ),
						'required'  => true,
						'subfields' => array(
							'question' => array(
								'type'  => 'text',
								'label' => __( 'Question', 'wpinterface-seo' ),
							),
							'answer'   => array(
								'type'  => 'textarea',
								'label' => __( 'Answer', 'wpinterface-seo' ),
							),
						),
					),
				),
			),
			'HowTo'               => array(
				'label'       => __( 'How-to', 'wpinterface-seo' ),
				'description' => __( 'Step-by-step instructions.', 'wpinterface-seo' ),
				'fields'      => array(
					'name'        => array(
						'type'        => 'text',
						'label'       => __( 'Name', 'wpinterface-seo' ),
						'placeholder' => 'title',
					),
					'description' => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'wpinterface-seo' ),
					),
					'total_time'  => array(
						'type'  => 'duration',
						'label' => __( 'Total time (minutes or h:mm)', 'wpinterface-seo' ),
					),
					'supplies'    => array(
						'type'  => 'list',
						'label' => __( 'Supplies (one per line)', 'wpinterface-seo' ),
					),
					'tools'       => array(
						'type'  => 'list',
						'label' => __( 'Tools (one per line)', 'wpinterface-seo' ),
					),
					'steps'       => array(
						'type'      => 'repeater',
						'label'     => __( 'Steps', 'wpinterface-seo' ),
						'required'  => true,
						'subfields' => array(
							'name'  => array(
								'type'  => 'text',
								'label' => __( 'Step title', 'wpinterface-seo' ),
							),
							'text'  => array(
								'type'  => 'textarea',
								'label' => __( 'Instructions', 'wpinterface-seo' ),
							),
							'image' => array(
								'type'  => 'image',
								'label' => __( 'Image', 'wpinterface-seo' ),
							),
						),
					),
				),
			),
			'Recipe'              => array(
				'label'       => __( 'Recipe', 'wpinterface-seo' ),
				'description' => __( 'Eligible for recipe rich results in Google.', 'wpinterface-seo' ),
				'fields'      => array(
					'name'         => array(
						'type'        => 'text',
						'label'       => __( 'Recipe name', 'wpinterface-seo' ),
						'placeholder' => 'title',
					),
					'description'  => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'wpinterface-seo' ),
					),
					'image'        => array(
						'type'  => 'image',
						'label' => __( 'Image (defaults to the featured image)', 'wpinterface-seo' ),
					),
					'category'     => array(
						'type'  => 'text',
						'label' => __( 'Category (e.g. Dessert)', 'wpinterface-seo' ),
					),
					'cuisine'      => array(
						'type'  => 'text',
						'label' => __( 'Cuisine (e.g. Nepali)', 'wpinterface-seo' ),
					),
					'keywords'     => array(
						'type'  => 'text',
						'label' => __( 'Keywords (comma separated)', 'wpinterface-seo' ),
					),
					'prep_time'    => array(
						'type'  => 'duration',
						'label' => __( 'Prep time (minutes)', 'wpinterface-seo' ),
					),
					'cook_time'    => array(
						'type'  => 'duration',
						'label' => __( 'Cook time (minutes)', 'wpinterface-seo' ),
					),
					'yield'        => array(
						'type'  => 'text',
						'label' => __( 'Servings / yield (e.g. 4 servings)', 'wpinterface-seo' ),
					),
					'calories'     => array(
						'type'  => 'text',
						'label' => __( 'Calories per serving (e.g. 270 calories)', 'wpinterface-seo' ),
					),
					'ingredients'  => array(
						'type'     => 'list',
						'label'    => __( 'Ingredients (one per line)', 'wpinterface-seo' ),
						'required' => true,
					),
					'instructions' => array(
						'type'      => 'repeater',
						'label'     => __( 'Instructions', 'wpinterface-seo' ),
						'required'  => true,
						'subfields' => array(
							'text' => array(
								'type'  => 'textarea',
								'label' => __( 'Step', 'wpinterface-seo' ),
							),
						),
					),
				),
			),
			'Event'               => array(
				'label'       => __( 'Event', 'wpinterface-seo' ),
				'description' => __( 'One event per page. Eligible for event rich results.', 'wpinterface-seo' ),
				'fields'      => array_merge(
					array(
						'name'          => array(
							'type'        => 'text',
							'label'       => __( 'Event name', 'wpinterface-seo' ),
							'placeholder' => 'title',
						),
						'description'   => array(
							'type'  => 'textarea',
							'label' => __( 'Description', 'wpinterface-seo' ),
						),
						'image'         => array(
							'type'  => 'image',
							'label' => __( 'Image', 'wpinterface-seo' ),
						),
						'start'         => array(
							'type'     => 'datetime',
							'label'    => __( 'Starts', 'wpinterface-seo' ),
							'required' => true,
						),
						'end'           => array(
							'type'  => 'datetime',
							'label' => __( 'Ends', 'wpinterface-seo' ),
						),
						'status'        => array(
							'type'    => 'select',
							'label'   => __( 'Status', 'wpinterface-seo' ),
							'options' => array(
								'EventScheduled'   => __( 'Scheduled', 'wpinterface-seo' ),
								'EventPostponed'   => __( 'Postponed', 'wpinterface-seo' ),
								'EventRescheduled' => __( 'Rescheduled', 'wpinterface-seo' ),
								'EventMovedOnline' => __( 'Moved online', 'wpinterface-seo' ),
								'EventCancelled'   => __( 'Cancelled', 'wpinterface-seo' ),
							),
						),
						'attendance'    => array(
							'type'    => 'select',
							'label'   => __( 'Attendance', 'wpinterface-seo' ),
							'options' => array(
								'OfflineEventAttendanceMode' => __( 'In person', 'wpinterface-seo' ),
								'OnlineEventAttendanceMode' => __( 'Online', 'wpinterface-seo' ),
								'MixedEventAttendanceMode' => __( 'In person and online', 'wpinterface-seo' ),
							),
						),
						'location_name' => array(
							'type'  => 'text',
							'label' => __( 'Venue name', 'wpinterface-seo' ),
						),
					),
					$address,
					array(
						'online_url'    => array(
							'type'  => 'url',
							'label' => __( 'Online event URL', 'wpinterface-seo' ),
						),
						'price'         => array(
							'type'  => 'number',
							'label' => __( 'Ticket price', 'wpinterface-seo' ),
						),
						'currency'      => $currency,
						'availability'  => array(
							'type'    => 'select',
							'label'   => __( 'Ticket availability', 'wpinterface-seo' ),
							'options' => array(
								'InStock'  => __( 'On sale', 'wpinterface-seo' ),
								'SoldOut'  => __( 'Sold out', 'wpinterface-seo' ),
								'PreOrder' => __( 'Pre-sale', 'wpinterface-seo' ),
							),
						),
						'ticket_url'    => array(
							'type'  => 'url',
							'label' => __( 'Ticket URL', 'wpinterface-seo' ),
						),
						'organizer'     => array(
							'type'  => 'text',
							'label' => __( 'Organizer', 'wpinterface-seo' ),
						),
						'organizer_url' => array(
							'type'  => 'url',
							'label' => __( 'Organizer URL', 'wpinterface-seo' ),
						),
						'performer'     => array(
							'type'  => 'text',
							'label' => __( 'Performer', 'wpinterface-seo' ),
						),
					)
				),
			),
			'VideoObject'         => array(
				'label'       => __( 'Video', 'wpinterface-seo' ),
				'description' => __( 'Describes the main video on this page (also used in the video sitemap).', 'wpinterface-seo' ),
				'fields'      => array(
					'name'        => array(
						'type'        => 'text',
						'label'       => __( 'Video title', 'wpinterface-seo' ),
						'placeholder' => 'title',
					),
					'description' => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'wpinterface-seo' ),
					),
					'thumbnail'   => array(
						'type'     => 'image',
						'label'    => __( 'Thumbnail', 'wpinterface-seo' ),
						'required' => true,
					),
					'upload_date' => array(
						'type'     => 'date',
						'label'    => __( 'Upload date', 'wpinterface-seo' ),
						'required' => true,
					),
					'duration'    => array(
						'type'  => 'duration',
						'label' => __( 'Duration (minutes or h:mm)', 'wpinterface-seo' ),
					),
					'content_url' => array(
						'type'  => 'url',
						'label' => __( 'Video file URL', 'wpinterface-seo' ),
					),
					'embed_url'   => array(
						'type'  => 'url',
						'label' => __( 'Player / embed URL', 'wpinterface-seo' ),
					),
				),
			),
			'JobPosting'          => array(
				'label'       => __( 'Job posting', 'wpinterface-seo' ),
				'description' => __( 'Eligible for Google job listings. Remove the markup or set "valid through" when the job is filled.', 'wpinterface-seo' ),
				'fields'      => array_merge(
					array(
						'title'           => array(
							'type'        => 'text',
							'label'       => __( 'Job title', 'wpinterface-seo' ),
							'placeholder' => 'title',
						),
						'description'     => array(
							'type'     => 'html',
							'label'    => __( 'Job description (HTML allowed)', 'wpinterface-seo' ),
							'required' => true,
						),
						'date_posted'     => array(
							'type'     => 'date',
							'label'    => __( 'Date posted', 'wpinterface-seo' ),
							'required' => true,
						),
						'valid_through'   => array(
							'type'  => 'datetime',
							'label' => __( 'Valid through', 'wpinterface-seo' ),
						),
						'employment_type' => array(
							'type'    => 'select',
							'label'   => __( 'Employment type', 'wpinterface-seo' ),
							'options' => array(
								'FULL_TIME'  => __( 'Full time', 'wpinterface-seo' ),
								'PART_TIME'  => __( 'Part time', 'wpinterface-seo' ),
								'CONTRACTOR' => __( 'Contractor', 'wpinterface-seo' ),
								'TEMPORARY'  => __( 'Temporary', 'wpinterface-seo' ),
								'INTERN'     => __( 'Intern', 'wpinterface-seo' ),
								'VOLUNTEER'  => __( 'Volunteer', 'wpinterface-seo' ),
								'PER_DIEM'   => __( 'Per diem', 'wpinterface-seo' ),
								'OTHER'      => __( 'Other', 'wpinterface-seo' ),
							),
						),
						'company'         => array(
							'type'  => 'text',
							'label' => __( 'Hiring organization (defaults to your organization)', 'wpinterface-seo' ),
						),
						'company_url'     => array(
							'type'  => 'url',
							'label' => __( 'Organization website', 'wpinterface-seo' ),
						),
						'remote'          => array(
							'type'  => 'boolean',
							'label' => __( 'Fully remote job', 'wpinterface-seo' ),
						),
						'remote_country'  => array(
							'type'  => 'text',
							'label' => __( 'Remote: allowed country (code)', 'wpinterface-seo' ),
						),
					),
					$address,
					array(
						'salary_min'      => array(
							'type'  => 'number',
							'label' => __( 'Salary from', 'wpinterface-seo' ),
						),
						'salary_max'      => array(
							'type'  => 'number',
							'label' => __( 'Salary to', 'wpinterface-seo' ),
						),
						'salary_currency' => $currency,
						'salary_unit'     => array(
							'type'    => 'select',
							'label'   => __( 'Salary per', 'wpinterface-seo' ),
							'options' => array(
								'HOUR'  => __( 'Hour', 'wpinterface-seo' ),
								'DAY'   => __( 'Day', 'wpinterface-seo' ),
								'WEEK'  => __( 'Week', 'wpinterface-seo' ),
								'MONTH' => __( 'Month', 'wpinterface-seo' ),
								'YEAR'  => __( 'Year', 'wpinterface-seo' ),
							),
						),
						'direct_apply'    => array(
							'type'  => 'boolean',
							'label' => __( 'Applications can be submitted on this page', 'wpinterface-seo' ),
						),
					)
				),
			),
			'Course'              => array(
				'label'       => __( 'Course', 'wpinterface-seo' ),
				'description' => __( 'An educational course.', 'wpinterface-seo' ),
				'fields'      => array(
					'name'         => array(
						'type'        => 'text',
						'label'       => __( 'Course name', 'wpinterface-seo' ),
						'placeholder' => 'title',
					),
					'description'  => array(
						'type'     => 'textarea',
						'label'    => __( 'Description', 'wpinterface-seo' ),
						'required' => true,
					),
					'provider'     => array(
						'type'  => 'text',
						'label' => __( 'Provider (defaults to your organization)', 'wpinterface-seo' ),
					),
					'provider_url' => array(
						'type'  => 'url',
						'label' => __( 'Provider website', 'wpinterface-seo' ),
					),
				),
			),
			'SoftwareApplication' => array(
				'label'       => __( 'Software / app', 'wpinterface-seo' ),
				'description' => __( 'An app, plugin, theme or game.', 'wpinterface-seo' ),
				'fields'      => array(
					'name'             => array(
						'type'        => 'text',
						'label'       => __( 'Name', 'wpinterface-seo' ),
						'placeholder' => 'title',
					),
					'description'      => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'wpinterface-seo' ),
					),
					'operating_system' => array(
						'type'  => 'text',
						'label' => __( 'Operating system (e.g. Windows, iOS, Web)', 'wpinterface-seo' ),
					),
					'category'         => array(
						'type'    => 'select',
						'label'   => __( 'Category', 'wpinterface-seo' ),
						'options' => array(
							'BusinessApplication'    => __( 'Business', 'wpinterface-seo' ),
							'DeveloperApplication'   => __( 'Developer', 'wpinterface-seo' ),
							'DesignApplication'      => __( 'Design', 'wpinterface-seo' ),
							'EducationalApplication' => __( 'Education', 'wpinterface-seo' ),
							'GameApplication'        => __( 'Game', 'wpinterface-seo' ),
							'MultimediaApplication'  => __( 'Multimedia', 'wpinterface-seo' ),
							'UtilitiesApplication'   => __( 'Utilities', 'wpinterface-seo' ),
							'SecurityApplication'    => __( 'Security', 'wpinterface-seo' ),
							'LifestyleApplication'   => __( 'Lifestyle', 'wpinterface-seo' ),
							'FinanceApplication'     => __( 'Finance', 'wpinterface-seo' ),
						),
					),
					'price'            => array(
						'type'  => 'number',
						'label' => __( 'Price (0 for free)', 'wpinterface-seo' ),
					),
					'currency'         => $currency,
					'rating'           => array(
						'type'  => 'number',
						'label' => __( 'Average user rating (1–5, from real user reviews only)', 'wpinterface-seo' ),
					),
					'rating_count'     => array(
						'type'  => 'number',
						'label' => __( 'Number of user ratings', 'wpinterface-seo' ),
					),
				),
			),
			'Book'                => array(
				'label'       => __( 'Book', 'wpinterface-seo' ),
				'description' => __( 'A book.', 'wpinterface-seo' ),
				'fields'      => array(
					'name'      => array(
						'type'        => 'text',
						'label'       => __( 'Title', 'wpinterface-seo' ),
						'placeholder' => 'title',
					),
					'author'    => array(
						'type'     => 'text',
						'label'    => __( 'Author', 'wpinterface-seo' ),
						'required' => true,
					),
					'isbn'      => array(
						'type'  => 'text',
						'label' => __( 'ISBN', 'wpinterface-seo' ),
					),
					'format'    => array(
						'type'    => 'select',
						'label'   => __( 'Format', 'wpinterface-seo' ),
						'options' => array(
							'Hardcover'       => __( 'Hardcover', 'wpinterface-seo' ),
							'Paperback'       => __( 'Paperback', 'wpinterface-seo' ),
							'EBook'           => __( 'E-book', 'wpinterface-seo' ),
							'AudiobookFormat' => __( 'Audiobook', 'wpinterface-seo' ),
						),
					),
					'pages'     => array(
						'type'  => 'number',
						'label' => __( 'Number of pages', 'wpinterface-seo' ),
					),
					'publisher' => array(
						'type'  => 'text',
						'label' => __( 'Publisher', 'wpinterface-seo' ),
					),
					'published' => array(
						'type'  => 'date',
						'label' => __( 'Publication date', 'wpinterface-seo' ),
					),
					'image'     => array(
						'type'  => 'image',
						'label' => __( 'Cover image', 'wpinterface-seo' ),
					),
				),
			),
			'Product'             => array(
				'label'       => __( 'Product / review', 'wpinterface-seo' ),
				'description' => __( 'A product, optionally with your review of it. Not for products sold with WooCommerce or Easy Digital Downloads (handled automatically).', 'wpinterface-seo' ),
				'fields'      => array(
					'name'         => array(
						'type'        => 'text',
						'label'       => __( 'Product name', 'wpinterface-seo' ),
						'placeholder' => 'title',
					),
					'description'  => array(
						'type'  => 'textarea',
						'label' => __( 'Description', 'wpinterface-seo' ),
					),
					'image'        => array(
						'type'  => 'image',
						'label' => __( 'Image (defaults to the featured image)', 'wpinterface-seo' ),
					),
					'brand'        => array(
						'type'  => 'text',
						'label' => __( 'Brand', 'wpinterface-seo' ),
					),
					'sku'          => array(
						'type'  => 'text',
						'label' => __( 'SKU', 'wpinterface-seo' ),
					),
					'gtin'         => array(
						'type'  => 'text',
						'label' => __( 'GTIN / EAN / UPC', 'wpinterface-seo' ),
					),
					'price'        => array(
						'type'  => 'number',
						'label' => __( 'Price', 'wpinterface-seo' ),
					),
					'currency'     => $currency,
					'availability' => array(
						'type'    => 'select',
						'label'   => __( 'Availability', 'wpinterface-seo' ),
						'options' => array(
							'InStock'      => __( 'In stock', 'wpinterface-seo' ),
							'OutOfStock'   => __( 'Out of stock', 'wpinterface-seo' ),
							'PreOrder'     => __( 'Pre-order', 'wpinterface-seo' ),
							'Discontinued' => __( 'Discontinued', 'wpinterface-seo' ),
						),
					),
					'offer_url'    => array(
						'type'  => 'url',
						'label' => __( 'Where to buy (URL)', 'wpinterface-seo' ),
					),
					'rating'       => array(
						'type'  => 'number',
						'label' => __( 'Your rating (1–5)', 'wpinterface-seo' ),
					),
					'pros'         => array(
						'type'  => 'list',
						'label' => __( 'Pros (one per line)', 'wpinterface-seo' ),
					),
					'cons'         => array(
						'type'  => 'list',
						'label' => __( 'Cons (one per line)', 'wpinterface-seo' ),
					),
				),
			),
		);
	}

	/**
	 * Sanitizes stored schema data { type, data }.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) || ! in_array( $value['type'] ?? '', self::KEYS, true ) ) {
			return array();
		}

		$definitions = self::definitions();
		$fields      = $definitions[ $value['type'] ]['fields'];
		$data        = is_array( $value['data'] ?? null ) ? $value['data'] : array();
		$clean       = array();

		foreach ( $fields as $key => $field ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$sanitized = self::sanitize_field( $field, $data[ $key ] );

			if ( '' !== $sanitized && array() !== $sanitized && null !== $sanitized && false !== $sanitized ) {
				$clean[ $key ] = $sanitized;
			}
		}

		return array(
			'type' => (string) $value['type'],
			'data' => $clean,
		);
	}

	/**
	 * JSON-LD node for a post's entity (null when it has none).
	 *
	 * @param WP_Post              $post   Post.
	 * @param array<string, mixed> $stored Stored { type, data }.
	 * @param array<string, mixed> $refs   References: url, webpage, publisher, author, image.
	 * @return array<string, mixed>|null
	 */
	public static function node( WP_Post $post, array $stored, array $refs ): ?array {
		$type = (string) ( $stored['type'] ?? '' );
		$data = is_array( $stored['data'] ?? null ) ? $stored['data'] : array();

		if ( ! in_array( $type, self::KEYS, true ) || 'FAQPage' === $type ) {
			return null;
		}

		$get   = static fn( string $key, $fallback = '' ) => $data[ $key ] ?? $fallback;
		$title = wp_strip_all_tags( get_the_title( $post ) );
		$node  = array(
			'@type'            => $type,
			'@id'              => $refs['url'] . '#/schema/' . strtolower( $type ),
			'mainEntityOfPage' => array( '@id' => $refs['webpage'] ),
		);

		switch ( $type ) {
			case 'HowTo':
				$node['name']        = $get( 'name', $title );
				$node['description'] = $get( 'description' );
				$node['totalTime']   = self::iso_duration( (string) $get( 'total_time' ) );
				$node['supply']      = self::things( (array) $get( 'supplies', array() ), 'HowToSupply' );
				$node['tool']        = self::things( (array) $get( 'tools', array() ), 'HowToTool' );
				$node['step']        = array_values(
					array_map(
						static fn( $step, $index ) => array_filter(
							array(
								'@type'    => 'HowToStep',
								'position' => $index + 1,
								'name'     => $step['name'] ?? '',
								'text'     => $step['text'] ?? '',
								'image'    => self::image_url( (int) ( $step['image'] ?? 0 ) ),
							)
						),
						array_values( (array) $get( 'steps', array() ) ),
						array_keys( array_values( (array) $get( 'steps', array() ) ) )
					)
				);
				break;

			case 'Recipe':
				$prep  = self::iso_duration( (string) $get( 'prep_time' ) );
				$cook  = self::iso_duration( (string) $get( 'cook_time' ) );
				$total = self::minutes( (string) $get( 'prep_time' ) ) + self::minutes( (string) $get( 'cook_time' ) );

				$node['name']               = $get( 'name', $title );
				$node['description']        = $get( 'description' );
				$node['image']              = self::image_url( (int) $get( 'image', 0 ) ) ?: ( $refs['image'] ?? '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				$node['author']             = $refs['author'] ?? null;
				$node['datePublished']      = (string) get_post_time( 'c', true, $post );
				$node['recipeCategory']     = $get( 'category' );
				$node['recipeCuisine']      = $get( 'cuisine' );
				$node['keywords']           = $get( 'keywords' );
				$node['prepTime']           = $prep;
				$node['cookTime']           = $cook;
				$node['totalTime']          = $total > 0 ? 'PT' . $total . 'M' : '';
				$node['recipeYield']        = $get( 'yield' );
				$node['recipeIngredient']   = array_values( (array) $get( 'ingredients', array() ) );
				$node['recipeInstructions'] = array_values(
					array_map(
						static fn( $step ) => array(
							'@type' => 'HowToStep',
							'text'  => $step['text'] ?? '',
						),
						array_filter( (array) $get( 'instructions', array() ), static fn( $step ) => ! empty( $step['text'] ) )
					)
				);

				if ( '' !== $get( 'calories' ) ) {
					$node['nutrition'] = array(
						'@type'    => 'NutritionInformation',
						'calories' => $get( 'calories' ),
					);
				}
				break;

			case 'Event':
				$mode     = (string) $get( 'attendance', 'OfflineEventAttendanceMode' );
				$physical = array_filter(
					array(
						'@type'   => 'Place',
						'name'    => $get( 'location_name' ),
						'address' => self::address( $data ),
					)
				);
				$virtual  = '' !== $get( 'online_url' ) ? array(
					'@type' => 'VirtualLocation',
					'url'   => $get( 'online_url' ),
				) : array();
				$location = array();

				if ( 'OnlineEventAttendanceMode' !== $mode && count( $physical ) > 1 ) {
					$location[] = $physical;
				}
				if ( 'OfflineEventAttendanceMode' !== $mode && $virtual ) {
					$location[] = $virtual;
				}

				$node['name']                = $get( 'name', $title );
				$node['description']         = $get( 'description' );
				$node['image']               = self::image_url( (int) $get( 'image', 0 ) ) ?: ( $refs['image'] ?? '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				$node['startDate']           = $get( 'start' );
				$node['endDate']             = $get( 'end' );
				$node['eventStatus']         = 'https://schema.org/' . $get( 'status', 'EventScheduled' );
				$node['eventAttendanceMode'] = 'https://schema.org/' . $mode;
				$node['location']            = 1 === count( $location ) ? $location[0] : $location;
				$node['organizer']           = '' !== $get( 'organizer' ) ? array_filter(
					array(
						'@type' => 'Organization',
						'name'  => $get( 'organizer' ),
						'url'   => $get( 'organizer_url' ),
					)
				) : ( $refs['publisher'] ?? null );
				$node['performer']           = '' !== $get( 'performer' ) ? array(
					'@type' => 'PerformingGroup',
					'name'  => $get( 'performer' ),
				) : null;

				if ( '' !== (string) $get( 'price' ) ) {
					$node['offers'] = array_filter(
						array(
							'@type'         => 'Offer',
							'price'         => $get( 'price' ),
							'priceCurrency' => strtoupper( (string) $get( 'currency', 'USD' ) ),
							'availability'  => 'https://schema.org/' . $get( 'availability', 'InStock' ),
							'url'           => $get( 'ticket_url', $refs['url'] ),
						),
						static fn( $value ) => '' !== $value && null !== $value
					);
				}
				break;

			case 'VideoObject':
				$node['name']         = $get( 'name', $title );
				$node['description']  = $get( 'description', wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ) );
				$node['thumbnailUrl'] = self::image_url( (int) $get( 'thumbnail', 0 ) ) ?: ( $refs['image'] ?? '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				$node['uploadDate']   = $get( 'upload_date', (string) get_post_time( 'c', true, $post ) );
				$node['duration']     = self::iso_duration( (string) $get( 'duration' ) );
				$node['contentUrl']   = $get( 'content_url' );
				$node['embedUrl']     = $get( 'embed_url' );
				break;

			case 'JobPosting':
				$node['title']              = $get( 'title', $title );
				$node['description']        = $get( 'description' );
				$node['datePosted']         = $get( 'date_posted', (string) get_post_time( 'Y-m-d', true, $post ) );
				$node['validThrough']       = $get( 'valid_through' );
				$node['employmentType']     = $get( 'employment_type' );
				$node['directApply']        = (bool) $get( 'direct_apply', false );
				$node['hiringOrganization'] = '' !== $get( 'company' ) ? array_filter(
					array(
						'@type'  => 'Organization',
						'name'   => $get( 'company' ),
						'sameAs' => $get( 'company_url' ),
					)
				) : ( $refs['publisher'] ?? null );

				if ( $get( 'remote', false ) ) {
					$node['jobLocationType']               = 'TELECOMMUTE';
					$node['applicantLocationRequirements'] = array(
						'@type' => 'Country',
						'name'  => strtoupper( (string) $get( 'remote_country', $get( 'country' ) ) ),
					);
				} else {
					$node['jobLocation'] = array(
						'@type'   => 'Place',
						'address' => self::address( $data ),
					);
				}

				if ( '' !== (string) $get( 'salary_min' ) || '' !== (string) $get( 'salary_max' ) ) {
					$value = '' !== (string) $get( 'salary_max' ) && '' !== (string) $get( 'salary_min' )
						? array(
							'@type'    => 'QuantitativeValue',
							'minValue' => $get( 'salary_min' ),
							'maxValue' => $get( 'salary_max' ),
							'unitText' => $get( 'salary_unit', 'YEAR' ),
						)
						: array(
							'@type'    => 'QuantitativeValue',
							'value'    => '' !== (string) $get( 'salary_min' ) ? $get( 'salary_min' ) : $get( 'salary_max' ),
							'unitText' => $get( 'salary_unit', 'YEAR' ),
						);

					$node['baseSalary'] = array(
						'@type'    => 'MonetaryAmount',
						'currency' => strtoupper( (string) $get( 'salary_currency', 'USD' ) ),
						'value'    => $value,
					);
				}
				break;

			case 'Course':
				$node['name']        = $get( 'name', $title );
				$node['description'] = $get( 'description' );
				$node['provider']    = '' !== $get( 'provider' ) ? array_filter(
					array(
						'@type'  => 'Organization',
						'name'   => $get( 'provider' ),
						'sameAs' => $get( 'provider_url' ),
					)
				) : ( $refs['publisher'] ?? null );
				break;

			case 'SoftwareApplication':
				$node['name']                = $get( 'name', $title );
				$node['description']         = $get( 'description' );
				$node['operatingSystem']     = $get( 'operating_system' );
				$node['applicationCategory'] = $get( 'category' );

				if ( '' !== (string) $get( 'price' ) ) {
					$node['offers'] = array(
						'@type'         => 'Offer',
						'price'         => $get( 'price' ),
						'priceCurrency' => strtoupper( (string) $get( 'currency', 'USD' ) ),
					);
				}

				if ( '' !== (string) $get( 'rating' ) && (int) $get( 'rating_count', 0 ) > 0 ) {
					$node['aggregateRating'] = array(
						'@type'       => 'AggregateRating',
						'ratingValue' => $get( 'rating' ),
						'ratingCount' => (int) $get( 'rating_count' ),
						'bestRating'  => 5,
					);
				}
				break;

			case 'Book':
				$node['name']          = $get( 'name', $title );
				$node['author']        = array(
					'@type' => 'Person',
					'name'  => $get( 'author' ),
				);
				$node['isbn']          = $get( 'isbn' );
				$node['bookFormat']    = '' !== $get( 'format' ) ? 'https://schema.org/' . $get( 'format' ) : '';
				$node['numberOfPages'] = $get( 'pages' );
				$node['publisher']     = '' !== $get( 'publisher' ) ? array(
					'@type' => 'Organization',
					'name'  => $get( 'publisher' ),
				) : null;
				$node['datePublished'] = $get( 'published' );
				$node['image']         = self::image_url( (int) $get( 'image', 0 ) ) ?: ( $refs['image'] ?? '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				break;

			case 'Product':
				$node['name']        = $get( 'name', $title );
				$node['description'] = $get( 'description' );
				$node['image']       = self::image_url( (int) $get( 'image', 0 ) ) ?: ( $refs['image'] ?? '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				$node['sku']         = $get( 'sku' );
				$node['gtin']        = $get( 'gtin' );
				$node['brand']       = '' !== $get( 'brand' ) ? array(
					'@type' => 'Brand',
					'name'  => $get( 'brand' ),
				) : null;

				if ( '' !== (string) $get( 'price' ) ) {
					$node['offers'] = array_filter(
						array(
							'@type'         => 'Offer',
							'price'         => $get( 'price' ),
							'priceCurrency' => strtoupper( (string) $get( 'currency', 'USD' ) ),
							'availability'  => 'https://schema.org/' . $get( 'availability', 'InStock' ),
							'url'           => $get( 'offer_url', $refs['url'] ),
						)
					);
				}

				if ( '' !== (string) $get( 'rating' ) ) {
					$node['review'] = array_filter(
						array(
							'@type'         => 'Review',
							'reviewRating'  => array(
								'@type'       => 'Rating',
								'ratingValue' => $get( 'rating' ),
								'bestRating'  => 5,
							),
							'author'        => $refs['author'] ?? null,
							'positiveNotes' => self::item_list( (array) $get( 'pros', array() ) ),
							'negativeNotes' => self::item_list( (array) $get( 'cons', array() ) ),
						)
					);
				}
				break;
		}

		return self::prune( $node );
	}

	/**
	 * FAQ entries as schema Question nodes.
	 *
	 * @param array<string, mixed> $stored Stored { type, data }.
	 * @return list<array<string, mixed>>
	 */
	public static function faq_questions( array $stored ): array {
		$questions = array();

		foreach ( (array) ( $stored['data']['questions'] ?? array() ) as $row ) {
			if ( empty( $row['question'] ) || empty( $row['answer'] ) ) {
				continue;
			}

			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $row['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $row['answer'],
				),
			);
		}

		return $questions;
	}

	/**
	 * Required fields that are empty, for editor warnings.
	 *
	 * @param array<string, mixed> $stored Stored { type, data }.
	 * @return list<string> Field labels.
	 */
	public static function missing_required( array $stored ): array {
		$definitions = self::definitions();
		$type        = (string) ( $stored['type'] ?? '' );

		if ( ! isset( $definitions[ $type ] ) ) {
			return array();
		}

		$missing = array();

		foreach ( $definitions[ $type ]['fields'] as $key => $field ) {
			if ( ! empty( $field['required'] ) && empty( $stored['data'][ $key ] ) ) {
				$missing[] = $field['label'];
			}
		}

		return $missing;
	}

	/**
	 * ISO 8601 duration from minutes ("90"), "h:mm" ("1:30") or "h:mm:ss".
	 *
	 * @param string $value Input.
	 */
	public static function iso_duration( string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^P/i', $value ) ) {
			return strtoupper( $value );
		}

		$parts = array_map( 'intval', explode( ':', $value ) );

		if ( 1 === count( $parts ) ) {
			$hours   = intdiv( $parts[0], 60 );
			$minutes = $parts[0] % 60;
			$seconds = 0;
		} elseif ( 2 === count( $parts ) ) {
			list( $hours, $minutes ) = $parts;
			$seconds                 = 0;
		} else {
			list( $hours, $minutes, $seconds ) = array_slice( $parts, 0, 3 );
		}

		$iso = 'PT' . ( $hours ? $hours . 'H' : '' ) . ( $minutes ? $minutes . 'M' : '' ) . ( $seconds ? $seconds . 'S' : '' );

		return 'PT' === $iso ? '' : $iso;
	}

	/**
	 * Minutes from a duration input.
	 *
	 * @param string $value Input.
	 */
	private static function minutes( string $value ): int {
		$value = trim( $value );

		if ( '' === $value || preg_match( '/^P/i', $value ) ) {
			return 0;
		}

		$parts = array_map( 'intval', explode( ':', $value ) );

		return 1 === count( $parts ) ? $parts[0] : $parts[0] * 60 + $parts[1];
	}

	/**
	 * Sanitizes one field value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_field( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'textarea':
				return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
			case 'html':
				return is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';
			case 'url':
				return is_string( $value ) ? (string) esc_url_raw( trim( $value ), array( 'http', 'https' ) ) : '';
			case 'number':
				return is_numeric( $value ) ? $value + 0 : '';
			case 'date':
				return is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
			case 'datetime':
				return is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)?)?$/', $value ) ? $value : '';
			case 'duration':
				return is_scalar( $value ) && preg_match( '/^(\d+(:\d{1,2}){0,2}|P[0-9A-Z]+)$/i', trim( (string) $value ) ) ? trim( (string) $value ) : '';
			case 'image':
				return is_numeric( $value ) ? absint( $value ) : 0;
			case 'boolean':
				return (bool) $value;
			case 'select':
				return is_string( $value ) && isset( $field['options'][ $value ] ) ? $value : '';
			case 'list':
				if ( is_string( $value ) ) {
					$value = preg_split( '/\R/', $value );
				}
				return array_values( array_filter( array_map( static fn( $item ) => is_scalar( $item ) ? sanitize_text_field( (string) $item ) : '', array_slice( (array) $value, 0, 100 ) ) ) );
			case 'repeater':
				$rows = array();
				foreach ( array_slice( is_array( $value ) ? $value : array(), 0, 100 ) as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$clean = array();
					foreach ( $field['subfields'] as $key => $subfield ) {
						if ( isset( $row[ $key ] ) ) {
							$clean[ $key ] = self::sanitize_field( $subfield, $row[ $key ] );
						}
					}
					if ( array_filter( $clean ) ) {
						$rows[] = $clean;
					}
				}
				return $rows;
			default:
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}
	}

	/**
	 * PostalAddress from address fields.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<string, string>
	 */
	private static function address( array $data ): array {
		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => (string) ( $data['street'] ?? '' ),
				'addressLocality' => (string) ( $data['locality'] ?? '' ),
				'addressRegion'   => (string) ( $data['region'] ?? '' ),
				'postalCode'      => (string) ( $data['postal_code'] ?? '' ),
				'addressCountry'  => strtoupper( (string) ( $data['country'] ?? '' ) ),
			)
		);

		return count( $address ) > 1 ? $address : array();
	}

	/**
	 * Things (supplies, tools) from names.
	 *
	 * @param string[] $names Names.
	 * @param string   $type  Schema type.
	 * @return list<array<string, string>>
	 */
	private static function things( array $names, string $type ): array {
		return array_values(
			array_map(
				static fn( $name ) => array(
					'@type' => $type,
					'name'  => (string) $name,
				),
				array_filter( $names )
			)
		);
	}

	/**
	 * ItemList for review pros/cons.
	 *
	 * @param string[] $items Items.
	 * @return array<string, mixed>|null
	 */
	private static function item_list( array $items ): ?array {
		$items = array_values( array_filter( $items ) );

		if ( array() === $items ) {
			return null;
		}

		return array(
			'@type'           => 'ItemList',
			'itemListElement' => array_map(
				static fn( $item, $index ) => array(
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'name'     => (string) $item,
				),
				$items,
				array_keys( $items )
			),
		);
	}

	/**
	 * Full-size image URL of an attachment.
	 *
	 * @param int $id Attachment ID.
	 */
	private static function image_url( int $id ): string {
		$image = $id ? Images::attachment( $id ) : null;

		return $image ? (string) $image['url'] : '';
	}

	/**
	 * Removes empty values recursively (keeps 0 and false where meaningful).
	 *
	 * @param array<string, mixed> $node Node.
	 * @return array<string, mixed>
	 */
	private static function prune( array $node ): array {
		foreach ( $node as $key => $value ) {
			if ( is_array( $value ) ) {
				$value        = self::prune( $value );
				$node[ $key ] = $value;
			}

			if ( null === $value || '' === $value || array() === $value ) {
				unset( $node[ $key ] );
			}
		}

		return $node;
	}
}
