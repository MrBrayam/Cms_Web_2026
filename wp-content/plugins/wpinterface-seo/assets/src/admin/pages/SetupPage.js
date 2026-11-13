/**
 * First-run setup wizard. Skippable; never shown automatically.
 */
import { Button, TextControl, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import Importer, { useImportSources } from '../components/Importer';
import { brandName, config, Section, useDraft } from '../components/section';
import { SiteIdentityFields } from './SchemaPage';

export default function SetupPage( { settings, save, saving } ) {
	const [ step, setStep ] = useState( 0 );
	const schema = useDraft( settings.schema );
	const social = useDraft( settings.social );
	const meta = useDraft( settings.meta );
	const [ sources, loadSources ] = useImportSources();

	useEffect( () => {
		loadSources();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const detected = ( sources || [] ).filter( ( source ) => source.detected );
	const effective = config.effective || {};
	const dashboard = ( config.pages || [] ).find(
		( page ) => page.id === 'dashboard'
	);

	const steps = [
		{
			title: __( 'About your site', 'wpinterface-seo' ),
			content: <SiteIdentityFields draft={ schema } />,
		},
		{
			title: __( 'Social profiles', 'wpinterface-seo' ),
			content: (
				<div className="wpiseo-grid-2">
					{ [
						'facebook',
						'x',
						'instagram',
						'linkedin',
						'youtube',
					].map( ( network ) => (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							key={ network }
							type="url"
							label={
								config.networkLabels?.[ network ] || network
							}
							value={ social.get( [ 'profiles', network ], '' ) }
							onChange={ ( value ) =>
								social.set( [ 'profiles', network ], value )
							}
						/>
					) ) }
				</div>
			),
		},
		{
			title: __( 'What should appear in search?', 'wpinterface-seo' ),
			content: (
				<>
					{ ( config.postTypes || [] )
						.filter( ( type ) => type.name !== 'attachment' )
						.map( ( type ) => (
							<ToggleControl
								__nextHasNoMarginBottom
								key={ type.name }
								label={ type.label }
								checked={
									! meta.get(
										[ 'post_types', type.name, 'noindex' ],
										effective.postTypes?.[ type.name ]
											?.noindex
									)
								}
								onChange={ ( show ) =>
									meta.set(
										[ 'post_types', type.name, 'noindex' ],
										! show
									)
								}
							/>
						) ) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Author archives (turn off for single-author sites)',
							'wpinterface-seo'
						) }
						checked={
							! meta.get(
								[ 'archives', 'author', 'noindex' ],
								effective.archives?.author?.noindex
							)
						}
						onChange={ ( show ) =>
							meta.set(
								[ 'archives', 'author', 'noindex' ],
								! show
							)
						}
					/>
				</>
			),
		},
		{
			title: __( 'Import existing SEO data', 'wpinterface-seo' ),
			content: detected.length ? (
				detected.map( ( source ) => (
					<Importer key={ source.id } source={ source } />
				) )
			) : (
				<p>
					{ __(
						'No other SEO plugin data was found. Nothing to import.',
						'wpinterface-seo'
					) }
				</p>
			),
		},
	];

	const finish = async () => {
		// Only sections the user edited: an import in the last step may have
		// just saved newer settings that untouched drafts must not overwrite.
		const partial = { general: { setup_completed: true } };
		if ( schema.dirty ) {
			partial.schema = schema.values;
		}
		if ( social.dirty ) {
			partial.social = social.values;
		}
		if ( meta.dirty ) {
			partial.meta = meta.values;
		}

		const saved = await save( partial );

		if ( saved ) {
			setStep( steps.length );
		}
	};

	if ( step >= steps.length ) {
		return (
			<Section title={ __( 'All set!', 'wpinterface-seo' ) }>
				<p>
					{ sprintf(
						/* translators: %s: plugin name. */
						__(
							'%s is configured. Titles, descriptions, schema and sitemaps now work automatically. Fine-tune anything from the SEO menu, and use the SEO panel when editing posts.',
							'wpinterface-seo'
						),
						brandName()
					) }
				</p>
				{ dashboard && (
					<Button variant="primary" href={ dashboard.url }>
						{ __( 'Go to the SEO dashboard', 'wpinterface-seo' ) }
					</Button>
				) }
			</Section>
		);
	}

	const current = steps[ step ];
	const isLast = step === steps.length - 1;

	return (
		<div className="wpiseo-page wpiseo-setup">
			<p className="wpiseo-muted">
				{ sprintf(
					/* translators: 1: current step, 2: total steps. */
					__( 'Step %1$d of %2$d', 'wpinterface-seo' ),
					step + 1,
					steps.length
				) }
			</p>
			<Section title={ current.title }>{ current.content }</Section>
			<div className="wpiseo-savebar">
				{ step > 0 && (
					<Button
						variant="tertiary"
						onClick={ () => setStep( step - 1 ) }
					>
						{ __( 'Back', 'wpinterface-seo' ) }
					</Button>
				) }
				{ isLast ? (
					<Button
						variant="primary"
						onClick={ finish }
						isBusy={ saving }
						disabled={ saving }
					>
						{ __( 'Finish setup', 'wpinterface-seo' ) }
					</Button>
				) : (
					<Button
						variant="primary"
						onClick={ () => setStep( step + 1 ) }
					>
						{ __( 'Continue', 'wpinterface-seo' ) }
					</Button>
				) }
				{ dashboard && (
					<Button variant="link" href={ dashboard.url }>
						{ __( 'Skip setup', 'wpinterface-seo' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
