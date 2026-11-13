<?php
/**
 * Image picker for classic admin forms.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders an attachment-ID field with a media-library picker.
 */
final class MediaField {

	private const HANDLE = 'wpiseo-media-field';

	/**
	 * Enqueues the media modal and picker script.
	 */
	public static function enqueue(): void {
		wp_enqueue_media();

		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script( self::HANDLE, false, array( 'jquery', 'media-editor' ), WPISEO_VERSION, array( 'in_footer' => true ) );
			wp_add_inline_script( self::HANDLE, self::script() );
		}

		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Prints the field.
	 *
	 * @param string $name          Input name.
	 * @param int    $attachment_id Current attachment ID.
	 */
	public static function render( string $name, int $attachment_id ): void {
		$preview = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';

		printf(
			'<div class="wpiseo-media-field"><input type="hidden" name="%1$s" value="%2$s" />'
			. '<img src="%3$s" alt="" style="max-width:240px;height:auto;display:%4$s;margin-bottom:8px" /><br />'
			. '<button type="button" class="button wpiseo-media-select" data-title="%5$s">%6$s</button> '
			. '<button type="button" class="button-link wpiseo-media-remove" style="display:%4$s">%7$s</button></div>',
			esc_attr( $name ),
			esc_attr( $attachment_id ? (string) $attachment_id : '' ),
			esc_url( (string) $preview ),
			$attachment_id ? 'inline-block' : 'none',
			esc_attr__( 'Select image', 'wpinterface-seo' ),
			esc_html__( 'Select image', 'wpinterface-seo' ),
			esc_html__( 'Remove', 'wpinterface-seo' )
		);
	}

	/**
	 * Picker behaviour (jQuery, matches the classic admin screens it runs on).
	 */
	private static function script(): string {
		return <<<'JS'
jQuery( function ( $ ) {
	$( document ).on( 'click', '.wpiseo-media-select', function ( event ) {
		event.preventDefault();
		var field = $( this ).closest( '.wpiseo-media-field' );
		var frame = wp.media( { title: $( this ).data( 'title' ), library: { type: 'image' }, multiple: false } );
		frame.on( 'select', function () {
			var image = frame.state().get( 'selection' ).first().toJSON();
			var url = image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url;
			field.find( 'input' ).val( image.id );
			field.find( 'img' ).attr( 'src', url ).show();
			field.find( '.wpiseo-media-remove' ).show();
		} );
		frame.open();
	} );
	$( document ).on( 'click', '.wpiseo-media-remove', function ( event ) {
		event.preventDefault();
		var field = $( this ).closest( '.wpiseo-media-field' );
		field.find( 'input' ).val( '' );
		field.find( 'img' ).attr( 'src', '' ).hide();
		$( this ).hide();
	} );
} );
JS;
	}
}
