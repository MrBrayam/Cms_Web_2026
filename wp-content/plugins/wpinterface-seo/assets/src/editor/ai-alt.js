/**
 * "Write alt text with AI" for the Image block (block editor only).
 */
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls } from '@wordpress/block-editor';
import { Button, Notice, PanelBody } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import config from './config';

function AltButton( { attachmentId, onAlt } ) {
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const run = async () => {
		setBusy( true );
		setError( null );

		try {
			const result = await apiFetch( {
				path: `/${
					config.restNamespace || 'wpinterface-seo/v1'
				}/ai/generate`,
				method: 'POST',
				data: {
					field: 'alt',
					post_id: config.postId,
					attachment_id: attachmentId,
				},
			} );

			if ( result?.alt ) {
				onAlt( result.alt );
			}
		} catch ( caught ) {
			setError( caught.message );
		} finally {
			setBusy( false );
		}
	};

	return (
		<>
			<Button
				variant="secondary"
				icon="admin-customizer"
				onClick={ run }
				isBusy={ busy }
				disabled={ busy || ! attachmentId }
			>
				{ __( 'Write alt text with AI', 'wpinterface-seo' ) }
			</Button>
			{ ! attachmentId && (
				<p className="wpiseo-help">
					{ __(
						'Only images from the media library are supported.',
						'wpinterface-seo'
					) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</>
	);
}

const withAiAlt = createHigherOrderComponent(
	( BlockEdit ) =>
		function WithAiAlt( props ) {
			if ( props.name !== 'core/image' || ! props.isSelected ) {
				return <BlockEdit { ...props } />;
			}

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<PanelBody
							title={ __( 'AI alt text', 'wpinterface-seo' ) }
						>
							<AltButton
								attachmentId={ props.attributes.id }
								onAlt={ ( alt ) =>
									props.setAttributes( { alt } )
								}
							/>
						</PanelBody>
					</InspectorControls>
				</>
			);
		},
	'withWpiseoAiAlt'
);

export default function registerAiAlt() {
	if ( config.ai?.available ) {
		addFilter( 'editor.BlockEdit', 'wpinterface-seo/ai-alt', withAiAlt );
	}
}
