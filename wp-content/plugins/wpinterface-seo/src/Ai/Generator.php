<?php
/**
 * AI suggestions for SEO fields.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Ai;

use WP_Error;
use WP_Post;
use WPInterface\SEO\Meta\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Builds prompts for one field at a time and returns suggestions for the
 * user to review. Nothing is saved automatically.
 */
final class Generator {

	public const FIELDS = array( 'title', 'description', 'social', 'alt', 'faq' );

	/**
	 * Maximum content characters sent to the provider.
	 */
	private const MAX_CONTENT = 12000;

	/**
	 * Provider client.
	 *
	 * @var JsonClient
	 */
	private JsonClient $client;

	/**
	 * Constructor.
	 *
	 * @param JsonClient $client AI client.
	 */
	public function __construct( JsonClient $client ) {
		$this->client = $client;
	}

	/**
	 * Suggestions for a field.
	 *
	 * @param string               $field   title|description|social|alt|faq.
	 * @param array<string, mixed> $input   post_id, title, content, keywords (unsaved editor values win), attachment_id (alt).
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate( string $field, array $input ) {
		if ( ! in_array( $field, self::FIELDS, true ) ) {
			return new WP_Error( 'wpiseo_ai_field', __( 'Unknown field.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		$post     = get_post( (int) ( $input['post_id'] ?? 0 ) );
		$title    = (string) ( $input['title'] ?? ( $post instanceof WP_Post ? $post->post_title : '' ) );
		$content  = (string) ( $input['content'] ?? ( $post instanceof WP_Post ? $post->post_content : '' ) );
		$keywords = array_values( array_filter( array_map( 'strval', (array) ( $input['keywords'] ?? ( $post instanceof WP_Post ? Meta::post( $post->ID, 'focus_keywords' ) : array() ) ) ) ) );
		$text     = mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $content ) ) ) ), 0, self::MAX_CONTENT );

		$context = sprintf(
			"%s: %s\n%s: %s\n%s: %s\n\n%s:\n%s",
			__( 'Website', 'wpinterface-seo' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			__( 'Page title', 'wpinterface-seo' ),
			$title,
			__( 'Focus keywords', 'wpinterface-seo' ),
			$keywords ? implode( ', ', $keywords ) : __( '(none)', 'wpinterface-seo' ),
			__( 'Page content', 'wpinterface-seo' ),
			'' !== $text ? $text : __( '(empty)', 'wpinterface-seo' )
		);

		$system = __( 'You are an SEO copywriter. Write accurate, specific text based only on the page content. Use the same language as the page content. Never invent facts, prices or claims. Do not use quotation marks around the text.', 'wpinterface-seo' );

		switch ( $field ) {
			case 'title':
				$schema = self::list_schema( 'suggestions', 3 );
				$prompt = __( 'Write 3 different SEO titles for this page. Each must be under 60 characters, describe the page, and include the main focus keyword naturally when one is given. Do not add the website name.', 'wpinterface-seo' );
				break;
			case 'description':
				$schema = self::list_schema( 'suggestions', 3 );
				$prompt = __( 'Write 3 different meta descriptions for this page. Each must be 120–155 characters, summarise what the reader gets, include the main focus keyword naturally when one is given, and end with a reason to click.', 'wpinterface-seo' );
				break;
			case 'social':
				$schema = array(
					'type'                 => 'object',
					'properties'           => array(
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
					),
					'required'             => array( 'title', 'description' ),
					'additionalProperties' => false,
				);
				$prompt = __( 'Write a title (under 70 characters) and a description (under 200 characters) for sharing this page on social media. Make them engaging but accurate.', 'wpinterface-seo' );
				break;
			case 'faq':
				$schema = array(
					'type'                 => 'object',
					'properties'           => array(
						'questions' => array(
							'type'     => 'array',
							'maxItems' => 6,
							'items'    => array(
								'type'                 => 'object',
								'properties'           => array(
									'question' => array( 'type' => 'string' ),
									'answer'   => array( 'type' => 'string' ),
								),
								'required'             => array( 'question', 'answer' ),
								'additionalProperties' => false,
							),
						),
					),
					'required'             => array( 'questions' ),
					'additionalProperties' => false,
				);
				$prompt = __( 'Write up to 6 frequently asked questions that this page answers, each with a short answer taken from the page content. Only include questions the content really answers.', 'wpinterface-seo' );
				break;
			default:
				return $this->alt( (int) ( $input['attachment_id'] ?? 0 ), $title, $text, $system );
		}

		$result = $this->client->json( $system, $prompt . "\n\n" . $context, $schema );

		return is_wp_error( $result ) ? $result : self::clean( $field, $result );
	}

	/**
	 * Alt text for an image, written from the image itself.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $title         Page title.
	 * @param string $text          Page text (context).
	 * @param string $system        System instruction.
	 * @return array<string, mixed>|WP_Error
	 */
	private function alt( int $attachment_id, string $title, string $text, string $system ) {
		$image = self::image_data( $attachment_id );

		if ( '' === $image ) {
			return new WP_Error( 'wpiseo_ai_image', __( 'The image file could not be read.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		$schema = array(
			'type'                 => 'object',
			'properties'           => array( 'alt' => array( 'type' => 'string' ) ),
			'required'             => array( 'alt' ),
			'additionalProperties' => false,
		);
		$prompt = sprintf(
			"%s\n\n%s: %s\n%s: %s",
			__( 'Write alt text for this image: one sentence under 125 characters that describes what it shows for someone who cannot see it. Do not start with "Image of" or "Picture of".', 'wpinterface-seo' ),
			__( 'Page title', 'wpinterface-seo' ),
			$title,
			__( 'Page excerpt', 'wpinterface-seo' ),
			mb_substr( $text, 0, 1000 )
		);

		$result = $this->client->json( $system, $prompt, $schema, $image );

		return is_wp_error( $result ) ? $result : self::clean( 'alt', $result );
	}

	/**
	 * Medium-size image as a data URI (at most 4 MB).
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function image_data( int $attachment_id ): string {
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return '';
		}

		$file   = (string) get_attached_file( $attachment_id );
		$medium = image_get_intermediate_size( $attachment_id, 'medium_large' );

		if ( is_array( $medium ) && ! empty( $medium['path'] ) ) {
			$path = trailingslashit( (string) wp_get_upload_dir()['basedir'] ) . $medium['path'];
			$file = is_readable( $path ) ? $path : $file;
		}

		$mime = (string) wp_check_filetype( $file )['type'];

		if ( ! is_readable( $file ) || filesize( $file ) > 4 * MB_IN_BYTES || ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
			return '';
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload file.

		return false === $contents ? '' : 'data:' . $mime . ';base64,' . base64_encode( $contents ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Image upload to the AI provider.
	}

	/**
	 * Schema for {key: [string…]}.
	 *
	 * @param string $key   Property.
	 * @param int    $count Items.
	 * @return array<string, mixed>
	 */
	private static function list_schema( string $key, int $count ): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				$key => array(
					'type'     => 'array',
					'items'    => array( 'type' => 'string' ),
					'minItems' => 1,
					'maxItems' => $count,
				),
			),
			'required'             => array( $key ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Plain-text, trimmed suggestions.
	 *
	 * @param string               $field Field.
	 * @param array<string, mixed> $data  Decoded answer.
	 * @return array<string, mixed>
	 */
	private static function clean( string $field, array $data ): array {
		$text = static fn( $value ): string => trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), " \t\"'“”" );

		switch ( $field ) {
			case 'title':
			case 'description':
				return array( 'suggestions' => array_values( array_filter( array_map( $text, array_slice( (array) ( $data['suggestions'] ?? array() ), 0, 3 ) ) ) ) );
			case 'social':
				return array(
					'title'       => $text( $data['title'] ?? '' ),
					'description' => $text( $data['description'] ?? '' ),
				);
			case 'alt':
				return array( 'alt' => $text( $data['alt'] ?? '' ) );
			default:
				$questions = array();

				foreach ( array_slice( (array) ( $data['questions'] ?? array() ), 0, 6 ) as $row ) {
					if ( is_array( $row ) && '' !== $text( $row['question'] ?? '' ) && '' !== $text( $row['answer'] ?? '' ) ) {
						$questions[] = array(
							'question' => $text( $row['question'] ),
							'answer'   => sanitize_textarea_field( (string) $row['answer'] ),
						);
					}
				}

				return array( 'questions' => $questions );
		}
	}
}
