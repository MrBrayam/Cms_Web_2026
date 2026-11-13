/**
 * Schema settings: site identity and default types per post type.
 */
import {
	RadioControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import ImagePicker from '../../editor/components/ImagePicker';
import { config, SaveBar, Section, useDraft } from '../components/section';
import { ProHint } from '../components/pro';

export function SiteIdentityFields( { draft } ) {
	const isPerson = draft.get( 'site_type', 'organization' ) === 'person';

	return (
		<>
			<RadioControl
				label={ __( 'This site represents', 'wpinterface-seo' ) }
				selected={ isPerson ? 'person' : 'organization' }
				options={ [
					{
						value: 'organization',
						label: __(
							'An organization or business',
							'wpinterface-seo'
						),
					},
					{
						value: 'person',
						label: __( 'A person', 'wpinterface-seo' ),
					},
				] }
				onChange={ ( value ) => draft.set( 'site_type', value ) }
			/>
			{ isPerson ? (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Person', 'wpinterface-seo' ) }
					value={ String( draft.get( 'person', 0 ) || '' ) }
					options={ [
						{
							value: '',
							label: __( 'Select a user', 'wpinterface-seo' ),
						},
						...( config.users || [] ).map( ( user ) => ( {
							value: String( user.id ),
							label: user.name,
						} ) ),
					] }
					onChange={ ( value ) =>
						draft.set( 'person', Number( value ) )
					}
				/>
			) : (
				<>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Organization name', 'wpinterface-seo' ) }
						placeholder={ config.siteName }
						value={ draft.get( 'name', '' ) }
						onChange={ ( value ) => draft.set( 'name', value ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Alternate name (optional)',
							'wpinterface-seo'
						) }
						value={ draft.get( 'alternate_name', '' ) }
						onChange={ ( value ) =>
							draft.set( 'alternate_name', value )
						}
					/>
					<ImagePicker
						label={ __( 'Logo', 'wpinterface-seo' ) }
						help={ __(
							'At least 112 × 112 pixels. Shown by Google next to your site name.',
							'wpinterface-seo'
						) }
						value={ draft.get( 'logo', 0 ) }
						onChange={ ( value ) => draft.set( 'logo', value ) }
					/>
				</>
			) }
		</>
	);
}

export default function SchemaPage( { settings, save, saving } ) {
	const draft = useDraft( settings.schema );
	const effective = config.effective?.schema || {};

	const onSave = async () => {
		if ( await save( { schema: draft.values } ) ) {
			draft.markSaved();
		}
	};

	return (
		<div className="wpiseo-page wpiseo-stack">
			<Section
				title={ __( 'Site identity', 'wpinterface-seo' ) }
				description={ __(
					'Used as the publisher in structured data for every page.',
					'wpinterface-seo'
				) }
			>
				<SiteIdentityFields draft={ draft } />
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Add breadcrumb structured data (BreadcrumbList)',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'breadcrumbs', true ) }
					onChange={ ( value ) => draft.set( 'breadcrumbs', value ) }
				/>
			</Section>

			<Section
				title={ __( 'Default types', 'wpinterface-seo' ) }
				description={ __(
					'The schema.org type used for each content type. Individual items can override it.',
					'wpinterface-seo'
				) }
			>
				{ ( config.postTypes || [] )
					.filter( ( type ) => type.name !== 'attachment' )
					.map( ( type ) => (
						<div className="wpiseo-grid-2" key={ type.name }>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ `${ type.label }: ${ __(
									'page type',
									'wpinterface-seo'
								) }` }
								value={ draft.get(
									[ 'post_types', type.name, 'page_type' ],
									effective[ type.name ]?.page_type
								) }
								options={ ( config.pageTypes || [] ).map(
									( value ) => ( { value, label: value } )
								) }
								onChange={ ( value ) =>
									draft.set(
										[
											'post_types',
											type.name,
											'page_type',
										],
										value
									)
								}
							/>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ `${ type.label }: ${ __(
									'article type',
									'wpinterface-seo'
								) }` }
								value={ draft.get(
									[ 'post_types', type.name, 'article_type' ],
									effective[ type.name ]?.article_type
								) }
								options={ ( config.articleTypes || [] ).map(
									( value ) => ( {
										value,
										label:
											value === 'none'
												? __(
														'None',
														'wpinterface-seo'
												  )
												: value,
									} )
								) }
								onChange={ ( value ) =>
									draft.set(
										[
											'post_types',
											type.name,
											'article_type',
										],
										value
									)
								}
							/>
						</div>
					) ) }
			</Section>

			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>

			<ProHint id="schema">
				{ __(
					'Pro applies schema templates to whole post types and lets you add custom JSON-LD.',
					'wpinterface-seo'
				) }
			</ProHint>
		</div>
	);
}
