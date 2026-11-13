/**
 * Advanced tab: indexing, canonical, breadcrumbs, primary terms.
 */
import {
	CheckboxControl,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import config, { metaKey } from '../config';
import { getMeta } from '../source/context';
import { useSeoState } from '../state';

const ADVANCED = [
	{
		value: 'noarchive',
		label: __( 'No archive (noarchive)', 'wpinterface-seo' ),
	},
	{
		value: 'nosnippet',
		label: __( 'No snippet (nosnippet)', 'wpinterface-seo' ),
	},
	{
		value: 'noimageindex',
		label: __( 'Do not index images (noimageindex)', 'wpinterface-seo' ),
	},
];

export default function AdvancedTab() {
	const { source } = useSeoState();
	const { meta, setMeta, post } = source;

	const field = ( key, fallback = '' ) =>
		getMeta( meta, metaKey( key ), fallback );
	const set = ( key ) => ( value ) =>
		setMeta( { [ metaKey( key ) ]: value } );

	const advanced = field( 'robots_advanced', [] );
	const primary = field( 'primary_terms', {} );

	const defaultIndex = config.noindexDefault
		? __( 'Default for this type (noindex)', 'wpinterface-seo' )
		: __( 'Default for this type (index)', 'wpinterface-seo' );

	const primaryTaxonomies = ( config.taxonomies || [] ).filter(
		( taxonomy ) => ( post.terms?.[ taxonomy.slug ] || [] ).length > 1
	);

	return (
		<>
			<PanelBody
				title={ __( 'Search engine visibility', 'wpinterface-seo' ) }
				initialOpen
			>
				{ ! config.site?.public && (
					<p className="wpiseo-warning">
						{ __(
							'The whole site is set to discourage search engines (Settings → Reading).',
							'wpinterface-seo'
						) }
					</p>
				) }
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __(
						'Allow search engines to index this page?',
						'wpinterface-seo'
					) }
					value={ field( 'noindex' ) }
					options={ [
						{ value: '', label: defaultIndex },
						{
							value: 'index',
							label: __( 'Yes (index)', 'wpinterface-seo' ),
						},
						{
							value: 'noindex',
							label: __( 'No (noindex)', 'wpinterface-seo' ),
						},
					] }
					onChange={ set( 'noindex' ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Do not follow links on this page (nofollow)',
						'wpinterface-seo'
					) }
					checked={ !! field( 'nofollow', false ) }
					onChange={ set( 'nofollow' ) }
				/>
				{ ADVANCED.map( ( option ) => (
					<CheckboxControl
						__nextHasNoMarginBottom
						key={ option.value }
						label={ option.label }
						checked={ advanced.includes( option.value ) }
						onChange={ ( checked ) =>
							set( 'robots_advanced' )(
								checked
									? [ ...advanced, option.value ]
									: advanced.filter(
											( value ) => value !== option.value
									  )
							)
						}
					/>
				) ) }
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="url"
					label={ __( 'Canonical URL', 'wpinterface-seo' ) }
					help={ __(
						'Only set this when the same content lives at another URL that should rank instead.',
						'wpinterface-seo'
					) }
					placeholder={ post.permalink }
					value={ field( 'canonical' ) }
					onChange={ set( 'canonical' ) }
				/>
			</PanelBody>

			{ ( config.modules?.breadcrumbs ||
				primaryTaxonomies.length > 0 ) && (
				<PanelBody
					title={ __(
						'Breadcrumbs & categories',
						'wpinterface-seo'
					) }
				>
					{ config.modules?.breadcrumbs && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Breadcrumb title',
								'wpinterface-seo'
							) }
							placeholder={ post.title }
							value={ field( 'breadcrumb_title' ) }
							onChange={ set( 'breadcrumb_title' ) }
						/>
					) }
					{ primaryTaxonomies.map( ( taxonomy ) => (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							key={ taxonomy.slug }
							label={ sprintf(
								/* translators: %s: taxonomy name, e.g. Category. */
								__( 'Primary %s', 'wpinterface-seo' ),
								taxonomy.label.toLowerCase()
							) }
							help={ sprintf(
								/* translators: %s: the %primary_category% template variable. */
								__(
									'Used in breadcrumbs, the %s variable and schema.',
									'wpinterface-seo'
								),
								'%primary_category%'
							) }
							value={ String( primary[ taxonomy.slug ] || '' ) }
							options={ [
								{
									value: '',
									label: __(
										'First selected',
										'wpinterface-seo'
									),
								},
								...post.terms[ taxonomy.slug ].map(
									( term ) => ( {
										value: String( term.id ),
										label: term.name,
									} )
								),
							] }
							onChange={ ( value ) => {
								const next = { ...primary };
								if ( value ) {
									next[ taxonomy.slug ] = Number( value );
								} else {
									delete next[ taxonomy.slug ];
								}
								set( 'primary_terms' )( next );
							} }
						/>
					) ) }
				</PanelBody>
			) }
		</>
	);
}
