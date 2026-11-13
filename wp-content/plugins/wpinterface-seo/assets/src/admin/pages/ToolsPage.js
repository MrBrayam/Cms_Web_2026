/**
 * Tools: import from other SEO plugins, export/import settings.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, FormFileUpload, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import Importer, { useImportSources } from '../components/Importer';
import { REST_BASE } from '../components/list';
import { brandName, config, Section } from '../components/section';

function LinkIndex() {
	const [ state, setState ] = useState( null );

	const rebuild = () => {
		setState( 'busy' );
		apiFetch( { path: `${ REST_BASE }/links/rebuild`, method: 'POST' } )
			.then( () => setState( 'done' ) )
			.catch( () => setState( 'error' ) );
	};

	return (
		<Section
			title={ __( 'Internal link index', 'wpinterface-seo' ) }
			description={ __(
				'Link counts update whenever content is saved. Rebuild the index after importing content or changing permalinks.',
				'wpinterface-seo'
			) }
		>
			<Button
				variant="secondary"
				onClick={ rebuild }
				isBusy={ 'busy' === state }
				disabled={ 'busy' === state }
			>
				{ __( 'Rebuild link index', 'wpinterface-seo' ) }
			</Button>
			{ 'done' === state && (
				<Notice status="success" isDismissible={ false }>
					{ __(
						'Rebuilding in the background. Counts update over the next few minutes.',
						'wpinterface-seo'
					) }
				</Notice>
			) }
			{ 'error' === state && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'The rebuild could not be started.',
						'wpinterface-seo'
					) }
				</Notice>
			) }
		</Section>
	);
}

export default function ToolsPage( { settings, modules = [], save } ) {
	const setupUrl =
		( config.pages || [] ).find( ( page ) => page.id === 'setup' )?.url ||
		'';

	const [ sources, loadSources ] = useImportSources();
	const [ importError, setImportError ] = useState( null );

	useEffect( () => {
		loadSources();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const detected = ( sources || [] ).filter( ( source ) => source.detected );

	const exportSettings = () => {
		const blob = new window.Blob(
			[
				JSON.stringify(
					{
						plugin: 'wpinterface-seo',
						version: config.version,
						settings,
					},
					null,
					2
				),
			],
			{ type: 'application/json' }
		);
		const url = window.URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = 'wpinterface-seo-settings.json';
		link.click();
		window.URL.revokeObjectURL( url );
	};

	const importSettings = ( event ) => {
		const file = event.target.files?.[ 0 ];
		setImportError( null );

		if ( ! file ) {
			return;
		}

		file.text()
			.then( ( text ) => {
				const data = JSON.parse( text );
				if ( data?.plugin !== 'wpinterface-seo' || ! data.settings ) {
					throw new Error(
						sprintf(
							/* translators: %s: plugin name. */
							__(
								'This file is not a %s settings export.',
								'wpinterface-seo'
							),
							brandName()
						)
					);
				}
				return save( data.settings );
			} )
			.catch( ( error ) => setImportError( error.message ) );
	};

	return (
		<div className="wpiseo-page wpiseo-stack">
			<Section
				title={ __(
					'Import from another SEO plugin',
					'wpinterface-seo'
				) }
				description={ __(
					'Copies titles, descriptions, focus keywords, robots settings, canonical URLs, social fields, primary categories and settings. The other plugin’s data is left untouched.',
					'wpinterface-seo'
				) }
			>
				{ sources === null && <Spinner /> }
				{ sources !== null && ! detected.length && (
					<p className="wpiseo-muted">
						{ __(
							'No data from Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework or Slim SEO was found on this site.',
							'wpinterface-seo'
						) }
					</p>
				) }
				{ detected.map( ( source ) => (
					<Importer key={ source.id } source={ source } />
				) ) }
			</Section>

			<Section
				title={ __( 'Export / import settings', 'wpinterface-seo' ) }
				description={ sprintf(
					/* translators: %s: plugin name. */
					__(
						'Move %s settings between sites. Per-post SEO data is not included.',
						'wpinterface-seo'
					),
					brandName()
				) }
			>
				<div className="wpiseo-actions-row">
					<Button variant="secondary" onClick={ exportSettings }>
						{ __( 'Export settings', 'wpinterface-seo' ) }
					</Button>
					<FormFileUpload
						accept="application/json,.json"
						onChange={ importSettings }
						render={ ( { openFileDialog } ) => (
							<Button
								variant="secondary"
								onClick={ openFileDialog }
							>
								{ __(
									'Import settings file',
									'wpinterface-seo'
								) }
							</Button>
						) }
					/>
				</div>
				{ importError && (
					<Notice status="error" isDismissible={ false }>
						{ importError }
					</Notice>
				) }
			</Section>

			{ modules.some(
				( module ) => module.id === 'links' && module.active
			) && <LinkIndex /> }

			{ setupUrl && (
				<Section
					title={ __( 'Setup wizard', 'wpinterface-seo' ) }
					description={ __(
						'Go through the first-run settings again: site identity, social profiles, search appearance and imports.',
						'wpinterface-seo'
					) }
				>
					<div>
						<Button variant="secondary" href={ setupUrl }>
							{ __( 'Open the setup wizard', 'wpinterface-seo' ) }
						</Button>
					</div>
				</Section>
			) }
		</div>
	);
}
