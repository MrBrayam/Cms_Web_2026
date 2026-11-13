/**
 * Attachment picker storing an image ID.
 */
import { BaseControl, Button } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useInstanceId } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import { MediaUpload } from '@wordpress/media-utils';

export function useImageUrl( id ) {
	return useSelect(
		( select ) => {
			if ( ! id ) {
				return '';
			}
			const media = select( coreStore ).getMedia( id, {
				context: 'view',
			} );
			return (
				media?.media_details?.sizes?.large?.source_url ||
				media?.source_url ||
				''
			);
		},
		[ id ]
	);
}

export default function ImagePicker( { label, value, onChange, help } ) {
	const url = useImageUrl( value );
	const id = useInstanceId( ImagePicker, 'wpiseo-image-picker' );

	return (
		<BaseControl
			id={ id }
			label={ label }
			help={ help }
			__nextHasNoMarginBottom
		>
			<div className="wpiseo-image-picker">
				{ url && (
					<img
						src={ url }
						alt=""
						className="wpiseo-image-picker__preview"
					/>
				) }
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ value || undefined }
					onSelect={ ( media ) => onChange( media.id ) }
					render={ ( { open } ) => (
						<Button id={ id } variant="secondary" onClick={ open }>
							{ value
								? __( 'Replace image', 'wpinterface-seo' )
								: __( 'Select image', 'wpinterface-seo' ) }
						</Button>
					) }
				/>
				{ !! value && (
					<Button
						variant="link"
						isDestructive
						onClick={ () => onChange( 0 ) }
					>
						{ __( 'Remove', 'wpinterface-seo' ) }
					</Button>
				) }
			</div>
		</BaseControl>
	);
}
