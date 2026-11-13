/**
 * Block editor scripts for the plugin's blocks. Each block is registered only
 * when its module is on (window.wpiseoBlocks, see Support\Blocks).
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import * as blocks from '@wordpress/blocks';
import {
	CheckboxControl,
	ExternalLink,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import breadcrumbs from '../../../blocks/breadcrumbs/block.json';
import htmlSitemap from '../../../blocks/html-sitemap/block.json';
import localBusiness from '../../../blocks/local-business/block.json';

const settings = window.wpiseoBlocks || {};

const breadcrumbsIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		aria-hidden="true"
	>
		<path d="M4 11h4v2H4v-2Zm6.6-3.4L14.2 12l-3.6 4.4-1.5-1.3 2.5-3.1-2.5-3.1 1.5-1.3ZM16 11h4v2h-4v-2Z" />
	</svg>
);

const localIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		aria-hidden="true"
	>
		<path d="M12 2a7 7 0 0 1 7 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 0 1 7-7Zm0 2a5 5 0 0 0-5 5c0 3.2 3.4 8 5 10 1.6-2 5-6.8 5-10a5 5 0 0 0-5-5Zm0 2.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Z" />
	</svg>
);

if ( settings.breadcrumbs !== false ) {
	blocks.registerBlockType( breadcrumbs.name, {
		icon: breadcrumbsIcon,
		edit: function Edit( { attributes, setAttributes, context } ) {
			const blockProps = useBlockProps();

			return (
				<>
					<InspectorControls>
						<PanelBody
							title={ __( 'Breadcrumbs', 'wpinterface-seo' ) }
						>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Separator', 'wpinterface-seo' ) }
								help={ __(
									'Leave empty to use the separator from SEO → Breadcrumbs.',
									'wpinterface-seo'
								) }
								value={ attributes.separator }
								onChange={ ( separator ) =>
									setAttributes( { separator } )
								}
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Show on the front page',
									'wpinterface-seo'
								) }
								checked={ attributes.showOnFront }
								onChange={ ( showOnFront ) =>
									setAttributes( { showOnFront } )
								}
							/>
						</PanelBody>
					</InspectorControls>
					<div { ...blockProps }>
						<ServerSideRender
							block={ breadcrumbs.name }
							attributes={ attributes }
							urlQueryArgs={ { post_id: context.postId } }
							skipBlockSupportAttributes
							EmptyResponsePlaceholder={ () => (
								<p className="wpiseo-breadcrumbs-placeholder">
									{ __(
										'Home » Category » Current page (breadcrumbs appear on the site)',
										'wpinterface-seo'
									) }
								</p>
							) }
						/>
					</div>
				</>
			);
		},
	} );
}

const LOCAL_PARTS = [
	[ 'showName', __( 'Business name', 'wpinterface-seo' ) ],
	[ 'showAddress', __( 'Address', 'wpinterface-seo' ) ],
	[ 'showPhone', __( 'Phone', 'wpinterface-seo' ) ],
	[ 'showEmail', __( 'Email', 'wpinterface-seo' ) ],
	[ 'showHours', __( 'Opening hours', 'wpinterface-seo' ) ],
	[ 'showMap', __( 'Map', 'wpinterface-seo' ) ],
];

if ( settings.local ) {
	blocks.registerBlockType( localBusiness.name, {
		icon: localIcon,
		edit: function Edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();

			return (
				<>
					<InspectorControls>
						<PanelBody title={ __( 'Show', 'wpinterface-seo' ) }>
							{ LOCAL_PARTS.map( ( [ key, label ] ) => (
								<ToggleControl
									__nextHasNoMarginBottom
									key={ key }
									label={ label }
									checked={ !! attributes[ key ] }
									onChange={ ( value ) =>
										setAttributes( { [ key ]: value } )
									}
								/>
							) ) }
							{ settings.localUrl && (
								<ExternalLink href={ settings.localUrl }>
									{ __(
										'Edit business details',
										'wpinterface-seo'
									) }
								</ExternalLink>
							) }
						</PanelBody>
					</InspectorControls>
					<div { ...blockProps }>
						<ServerSideRender
							block={ localBusiness.name }
							attributes={ attributes }
							skipBlockSupportAttributes
							EmptyResponsePlaceholder={ () => (
								<p className="wpiseo-breadcrumbs-placeholder">
									{ __(
										'Add your address and opening hours in SEO → Local SEO.',
										'wpinterface-seo'
									) }
								</p>
							) }
						/>
					</div>
				</>
			);
		},
	} );
}

const sitemapIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		aria-hidden="true"
	>
		<path d="M10 3h4v4h-1v3h6v4h1v4h-4v-4h1v-2h-5v2h1v4h-4v-4h1v-2H5v2h1v4H2v-4h1v-4h8V7h-1V3Z" />
	</svg>
);

if ( settings.htmlSitemap ) {
	blocks.registerBlockType( htmlSitemap.name, {
		icon: sitemapIcon,
		edit: function Edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();
			const selected = attributes.postTypes || [];

			return (
				<>
					<InspectorControls>
						<PanelBody title={ __( 'Content', 'wpinterface-seo' ) }>
							<p className="components-base-control__help">
								{ __(
									'Leave all unchecked to list every type in your XML sitemap.',
									'wpinterface-seo'
								) }
							</p>
							{ ( settings.postTypes || [] ).map( ( type ) => (
								<CheckboxControl
									__nextHasNoMarginBottom
									key={ type.value }
									label={ type.label }
									checked={ selected.includes( type.value ) }
									onChange={ ( checked ) =>
										setAttributes( {
											postTypes: checked
												? [ ...selected, type.value ]
												: selected.filter(
														( name ) =>
															name !== type.value
												  ),
										} )
									}
								/>
							) ) }
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'List categories and tags',
									'wpinterface-seo'
								) }
								checked={ !! attributes.showTaxonomies }
								onChange={ ( showTaxonomies ) =>
									setAttributes( { showTaxonomies } )
								}
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Show dates', 'wpinterface-seo' ) }
								checked={ !! attributes.showDates }
								onChange={ ( showDates ) =>
									setAttributes( { showDates } )
								}
							/>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Order', 'wpinterface-seo' ) }
								value={ attributes.orderBy }
								options={ [
									{
										value: 'title',
										label: __(
											'Alphabetical',
											'wpinterface-seo'
										),
									},
									{
										value: 'date',
										label: __(
											'Newest first',
											'wpinterface-seo'
										),
									},
								] }
								onChange={ ( orderBy ) =>
									setAttributes( { orderBy } )
								}
							/>
							<RangeControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Items per content type',
									'wpinterface-seo'
								) }
								min={ 5 }
								max={ 500 }
								value={ attributes.limit }
								onChange={ ( limit ) =>
									setAttributes( { limit } )
								}
							/>
						</PanelBody>
					</InspectorControls>
					<div { ...blockProps }>
						<ServerSideRender
							block={ htmlSitemap.name }
							attributes={ attributes }
							skipBlockSupportAttributes
							EmptyResponsePlaceholder={ () => (
								<p className="wpiseo-breadcrumbs-placeholder">
									{ __(
										'No published content to list yet.',
										'wpinterface-seo'
									) }
								</p>
							) }
						/>
					</div>
				</>
			);
		},
	} );
}

/*
 * Block Bindings source (WordPress 6.7+): bind a paragraph, heading, button
 * or image to the SEO title, description or social image. Values resolve on
 * the server; the editor shows the stored value or a label.
 */
const BINDING_FIELDS = {
	title: {
		label: __( 'SEO title', 'wpinterface-seo' ),
		meta: '_wpiseo_title',
	},
	description: {
		label: __( 'Meta description', 'wpinterface-seo' ),
		meta: '_wpiseo_description',
	},
	og_title: {
		label: __( 'Social title', 'wpinterface-seo' ),
		meta: '_wpiseo_og_title',
	},
	og_description: {
		label: __( 'Social description', 'wpinterface-seo' ),
		meta: '_wpiseo_og_description',
	},
	og_image: {
		label: __( 'Social image URL', 'wpinterface-seo' ),
		meta: '',
	},
	canonical: {
		label: __( 'Canonical URL', 'wpinterface-seo' ),
		meta: '_wpiseo_canonical',
	},
};

if ( typeof blocks.registerBlockBindingsSource === 'function' ) {
	blocks.registerBlockBindingsSource( {
		name: 'wpinterface-seo/field',
		label: __( 'SEO', 'wpinterface-seo' ),
		usesContext: [ 'postId', 'postType' ],
		getValues( { select, context, bindings } ) {
			const record =
				context?.postId && context?.postType
					? select( 'core' ).getEditedEntityRecord(
							'postType',
							context.postType,
							context.postId
					  )
					: null;
			const values = {};

			Object.entries( bindings ).forEach( ( [ attribute, binding ] ) => {
				const field = BINDING_FIELDS[ binding?.args?.key ];

				// The image URL is only known on the server; keep the block's own value.
				if ( ! field || ! field.meta ) {
					return;
				}

				const stored = record?.meta?.[ field.meta ];

				values[ attribute ] = stored || field.label;
			} );

			return values;
		},
		canUserEditValue: () => false,
		getFieldsList: () =>
			Object.entries( BINDING_FIELDS ).map( ( [ key, field ] ) => ( {
				label: field.label,
				type: 'string',
				args: { key },
			} ) ),
	} );
}
