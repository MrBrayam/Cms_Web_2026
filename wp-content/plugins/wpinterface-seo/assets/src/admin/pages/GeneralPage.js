/**
 * General settings: title separator and uninstall behaviour.
 */
import { Button, TextControl, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { SaveBar, Section } from '../components/section';

const VERIFICATION = [
	[ 'google', 'Google Search Console' ],
	[ 'bing', 'Bing Webmaster Tools' ],
	[ 'yandex', 'Yandex Webmaster' ],
	[ 'baidu', 'Baidu Webmaster Tools' ],
	[ 'pinterest', 'Pinterest' ],
];

const SEPARATORS = [ '-', '–', '—', '|', '·', '•', '»', '/', '~', ':' ];

export default function GeneralPage( { settings, config, save, saving } ) {
	const [ general, setGeneral ] = useState( settings.general );
	const [ verification, setVerification ] = useState(
		settings.meta?.verification || {}
	);
	const [ dirty, setDirty ] = useState( false );
	const isPreset = SEPARATORS.includes( general.separator );
	const [ custom, setCustom ] = useState( ! isPreset );

	const update = ( key, value ) => {
		setGeneral( ( current ) => ( { ...current, [ key ]: value } ) );
		setDirty( true );
	};

	const onSave = async () => {
		if ( await save( { general, meta: { verification } } ) ) {
			setDirty( false );
		}
	};

	const preview = `${ __( 'Example Post Title', 'wpinterface-seo' ) } ${
		general.separator
	} ${ config.siteName || '' }`;

	return (
		<form
			className="wpiseo-page wpiseo-stack"
			onSubmit={ ( event ) => {
				event.preventDefault();
				onSave();
			} }
		>
			<Section
				title={ __( 'Title separator', 'wpinterface-seo' ) }
				description={ sprintf(
					/* translators: %s: the %sep% template variable. */
					__(
						'Used by the %s variable in title templates.',
						'wpinterface-seo'
					),
					'%sep%'
				) }
			>
				<div
					className="wpiseo-separators"
					role="radiogroup"
					aria-label={ __( 'Title separator', 'wpinterface-seo' ) }
				>
					{ SEPARATORS.map( ( separator ) => (
						<Button
							key={ separator }
							role="radio"
							aria-checked={
								! custom && general.separator === separator
							}
							variant="secondary"
							size="compact"
							isPressed={
								! custom && general.separator === separator
							}
							onClick={ () => {
								setCustom( false );
								update( 'separator', separator );
							} }
						>
							{ separator }
						</Button>
					) ) }
					<Button
						role="radio"
						aria-checked={ custom }
						variant="secondary"
						size="compact"
						isPressed={ custom }
						onClick={ () => setCustom( true ) }
					>
						{ __( 'Custom', 'wpinterface-seo' ) }
					</Button>
				</div>
				{ custom && (
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Custom separator', 'wpinterface-seo' ) }
						value={ general.separator }
						maxLength={ 10 }
						onChange={ ( value ) => update( 'separator', value ) }
					/>
				) }
				<div className="wpiseo-serp-preview" aria-live="polite">
					<span className="wpiseo-serp-preview__label">
						{ __( 'Preview', 'wpinterface-seo' ) }
					</span>
					<span className="wpiseo-serp-preview__title">
						{ preview }
					</span>
				</div>
			</Section>

			<Section
				title={ __( 'Site verification', 'wpinterface-seo' ) }
				description={ __(
					'Paste the verification code (or the whole meta tag) each service gives you. The tags are added to your home page.',
					'wpinterface-seo'
				) }
			>
				<div className="wpiseo-grid-2">
					{ VERIFICATION.map( ( [ service, label ] ) => (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							key={ service }
							label={ label }
							value={ verification[ service ] || '' }
							onChange={ ( value ) => {
								setVerification( ( current ) => ( {
									...current,
									[ service ]: value,
								} ) );
								setDirty( true );
							} }
						/>
					) ) }
				</div>
			</Section>

			<Section title={ __( 'Advanced', 'wpinterface-seo' ) }>
				{ ( config.conflicts || [] ).length > 0 && (
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Print SEO tags even though another SEO plugin is active',
							'wpinterface-seo'
						) }
						help={ __(
							'Not recommended: pages will get duplicate titles, meta tags and schema. Deactivate the other plugin instead.',
							'wpinterface-seo'
						) }
						checked={ !! general.force_output }
						onChange={ ( value ) =>
							update( 'force_output', value )
						}
					/>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Also show the SEO box below the content in the block editor',
						'wpinterface-seo'
					) }
					help={ __(
						'The SEO sidebar is always available from the editor toolbar. Turn this on if your team prefers a box under the content, as in the classic editor.',
						'wpinterface-seo'
					) }
					checked={ !! general.block_editor_metabox }
					onChange={ ( value ) =>
						update( 'block_editor_metabox', value )
					}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Delete all data when the plugin is deleted',
						'wpinterface-seo'
					) }
					help={ __(
						'Removes settings, SEO titles and descriptions, redirects and logs. Leave off if you might reinstall.',
						'wpinterface-seo'
					) }
					checked={ !! general.delete_data_on_uninstall }
					onChange={ ( value ) =>
						update( 'delete_data_on_uninstall', value )
					}
				/>
			</Section>

			<SaveBar onSave={ onSave } saving={ saving } dirty={ dirty } />
		</form>
	);
}
