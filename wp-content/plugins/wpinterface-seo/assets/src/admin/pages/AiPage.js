/**
 * AI Assist: status of the WordPress AI connectors, and limits.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	ExternalLink,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { REST_BASE } from '../components/list';
import { SaveBar, Section, useDraft } from '../components/section';

function StatusNotice( { status } ) {
	if ( status.available ) {
		return (
			<Notice status="success" isDismissible={ false }>
				{ __(
					'Ready. Suggestions use the AI provider set up in Settings → Connectors.',
					'wpinterface-seo'
				) }
			</Notice>
		);
	}

	return (
		<Notice status="warning" isDismissible={ false }>
			{ status.wpAi
				? __(
						'No AI provider is set up yet. Install a provider plugin and add its key in Settings → Connectors; WordPress then shares it with every plugin that uses AI.',
						'wpinterface-seo'
				  )
				: __(
						'AI Assist uses the AI Client built into WordPress 7.0 and later. Update WordPress to use it.',
						'wpinterface-seo'
				  ) }{ ' ' }
			{ status.wpAi && status.connectorsUrl && (
				<ExternalLink href={ status.connectorsUrl }>
					{ __( 'Open Connectors', 'wpinterface-seo' ) }
				</ExternalLink>
			) }
		</Notice>
	);
}

export default function AiPage( { settings, save, saving } ) {
	const draft = useDraft( settings.ai );
	const [ status, setStatus ] = useState( null );

	useEffect( () => {
		apiFetch( { path: `${ REST_BASE }/ai/status` } )
			.then( setStatus )
			.catch( () => setStatus( {} ) );
	}, [] );

	const onSave = async () => {
		if ( await save( { ai: draft.values } ) ) {
			draft.markSaved();
		}
	};

	if ( ! status ) {
		return <Spinner />;
	}

	return (
		<div className="wpiseo-page wpiseo-stack">
			<Section
				title={ __( 'Status', 'wpinterface-seo' ) }
				description={ __(
					'Suggests SEO titles, meta descriptions, social text, image alt text and FAQ drafts. Suggestions appear in the editor and are only saved when you pick one.',
					'wpinterface-seo'
				) }
			>
				<StatusNotice status={ status } />
				<p className="wpiseo-muted">
					{ __(
						'Privacy: when someone clicks a “Suggest with AI” button, the post’s title, focus keywords and text (or the image, for alt text) are sent to the provider set up in Settings → Connectors. Nothing is sent automatically, and nothing goes to WPInterface.',
						'wpinterface-seo'
					) }
				</p>
			</Section>

			<Section title={ __( 'Limits', 'wpinterface-seo' ) }>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					min={ 1 }
					max={ 1000 }
					label={ __(
						'Requests per user per day',
						'wpinterface-seo'
					) }
					help={ sprintf(
						/* translators: %d: requests used today. */
						__(
							'Protects your provider budget. You used %d today.',
							'wpinterface-seo'
						),
						status.usage || 0
					) }
					value={ String( draft.get( 'daily_limit', 50 ) ) }
					onChange={ ( value ) =>
						draft.set( 'daily_limit', parseInt( value, 10 ) || 50 )
					}
				/>
			</Section>

			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>
		</div>
	);
}
