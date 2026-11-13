/**
 * Runs an import from another SEO plugin in batches with progress.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { brandName, config } from './section';

const BASE = `/${ config.restNamespace || 'wpinterface-seo/v1' }/import`;

export default function Importer( { source, onDone } ) {
	const [ overwrite, setOverwrite ] = useState( false );
	const [ running, setRunning ] = useState( false );
	const [ progress, setProgress ] = useState( null );
	const [ error, setError ] = useState( null );

	const run = async () => {
		setRunning( true );
		setError( null );

		const step = ( name, extra = {} ) =>
			apiFetch( {
				path: `${ BASE }/${ source.id }`,
				method: 'POST',
				data: { step: name, overwrite, ...extra },
			} );

		try {
			setProgress( { stage: __( 'Settings', 'wpinterface-seo' ) } );
			await step( 'settings' );

			let offset = 0;
			let updated = 0;
			let processed = 0;

			while ( offset !== null ) {
				// eslint-disable-next-line no-await-in-loop -- Batches must run in sequence.
				const response = await step( 'posts', { offset } );
				processed += response.processed;
				updated += response.updated;
				setProgress( {
					stage: __( 'Posts', 'wpinterface-seo' ),
					processed,
					total: response.total,
				} );
				offset = response.next;
			}

			setProgress( {
				stage: __( 'Categories & tags', 'wpinterface-seo' ),
			} );
			const terms = await step( 'terms' );

			setProgress( { stage: __( 'Authors', 'wpinterface-seo' ) } );
			const users = await step( 'users' );

			setProgress( {
				done: true,
				posts: updated,
				terms: terms.updated,
				users: users.updated,
			} );

			if ( onDone ) {
				onDone();
			}
		} catch ( caught ) {
			setError( caught.message );
		} finally {
			setRunning( false );
		}
	};

	return (
		<div className="wpiseo-importer">
			<p>
				<strong>{ source.label }</strong>{ ' ' }
				{ sprintf(
					/* translators: %d: number of posts. */
					__( '— SEO data found on %d items.', 'wpinterface-seo' ),
					source.posts
				) }
			</p>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ sprintf(
					/* translators: %s: plugin name. */
					__(
						'Overwrite values already set in %s',
						'wpinterface-seo'
					),
					brandName()
				) }
				checked={ overwrite }
				onChange={ setOverwrite }
				disabled={ running }
			/>
			<Button
				variant="primary"
				onClick={ run }
				isBusy={ running }
				disabled={ running }
			>
				{ sprintf(
					/* translators: %s: plugin name. */
					__( 'Import from %s', 'wpinterface-seo' ),
					source.label
				) }
			</Button>

			{ progress && ! progress.done && (
				<p className="wpiseo-muted" aria-live="polite">
					{ progress.total
						? sprintf(
								/* translators: 1: stage, 2: done, 3: total. */
								__( '%1$s: %2$d of %3$d…', 'wpinterface-seo' ),
								progress.stage,
								progress.processed,
								progress.total
						  )
						: sprintf(
								/* translators: %s: stage. */
								__( '%s…', 'wpinterface-seo' ),
								progress.stage
						  ) }
				</p>
			) }

			{ progress?.done && (
				<Notice status="success" isDismissible={ false }>
					{ sprintf(
						/* translators: 1: posts, 2: terms, 3: users. */
						__(
							'Import finished: %1$d posts, %2$d terms and %3$d author profiles updated. Settings were imported too. You can now deactivate the other plugin.',
							'wpinterface-seo'
						),
						progress.posts,
						progress.terms,
						progress.users
					) }
				</Notice>
			) }

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</div>
	);
}

export function useImportSources() {
	const [ sources, setSources ] = useState( null );

	const load = () =>
		apiFetch( { path: BASE } )
			.then( setSources )
			.catch( () => setSources( [] ) );

	return [ sources, load ];
}
