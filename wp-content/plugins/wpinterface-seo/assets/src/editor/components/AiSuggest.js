/**
 * "Suggest with AI" control: asks the site's AI provider for suggestions
 * and lets the user pick one. Nothing is applied without a click.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import config, { metaKey } from '../config';
import { getMeta } from '../source/context';
import { useSeoState } from '../state';

export const aiAvailable = () => !! config.ai?.available;

/**
 * Requests suggestions for a field from the current (unsaved) editor values.
 *
 * @return {Object} { request, busy, error, reset }.
 */
export function useAiRequest() {
	const { source } = useSeoState();
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const request = async ( field, extra = {} ) => {
		setBusy( true );
		setError( null );

		try {
			return await apiFetch( {
				path: `/${
					config.restNamespace || 'wpinterface-seo/v1'
				}/ai/generate`,
				method: 'POST',
				data: {
					field,
					post_id: config.postId,
					title: source.post.title,
					content: source.post.content,
					keywords: getMeta(
						source.meta,
						metaKey( 'focus_keywords' ),
						[]
					),
					...extra,
				},
			} );
		} catch ( caught ) {
			setError( caught.message );
			return null;
		} finally {
			setBusy( false );
		}
	};

	return { request, busy, error, reset: () => setError( null ) };
}

export default function AiSuggest( { field, onApply } ) {
	const { request, busy, error } = useAiRequest();
	const [ suggestions, setSuggestions ] = useState( null );

	if ( ! aiAvailable() ) {
		return null;
	}

	const run = async () => {
		const result = await request( field );

		if ( ! result ) {
			return;
		}

		setSuggestions(
			'social' === field
				? [ result ]
				: ( result.suggestions || [] ).map( ( text ) => text )
		);
	};

	const label = ( item ) =>
		'social' === field ? `${ item.title } — ${ item.description }` : item;

	return (
		<div className="wpiseo-ai">
			<Button
				variant="link"
				icon="admin-customizer"
				onClick={ run }
				disabled={ busy }
			>
				{ __( 'Suggest with AI', 'wpinterface-seo' ) }
			</Button>
			{ busy && <Spinner /> }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ suggestions && (
				<ul className="wpiseo-ai__list">
					{ suggestions.length === 0 && (
						<li className="wpiseo-help">
							{ __(
								'No suggestions. Add more content and try again.',
								'wpinterface-seo'
							) }
						</li>
					) }
					{ suggestions.map( ( item, index ) => (
						<li key={ index }>
							<button
								type="button"
								className="wpiseo-ai__option"
								onClick={ () => {
									onApply( item );
									setSuggestions( null );
								} }
							>
								{ label( item ) }
							</button>
						</li>
					) ) }
					<li>
						<Button
							variant="link"
							size="small"
							onClick={ () => setSuggestions( null ) }
						>
							{ __( 'Dismiss', 'wpinterface-seo' ) }
						</Button>
					</li>
				</ul>
			) }
		</div>
	);
}
