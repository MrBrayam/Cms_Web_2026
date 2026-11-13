/**
 * XML sitemap settings.
 */
import {
	CheckboxControl,
	ExternalLink,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { config, SaveBar, Section, useDraft } from '../components/section';

function idsToText( value ) {
	return ( Array.isArray( value ) ? value : [] ).join( ', ' );
}

function textToIds( text ) {
	return text
		.split( ',' )
		.map( ( part ) => parseInt( part.trim(), 10 ) )
		.filter( ( id ) => id > 0 );
}

export default function SitemapsPage( { settings, save, saving } ) {
	const draft = useDraft( settings.sitemaps );
	const effective = config.effective || {};

	const onSave = async () => {
		if ( await save( { sitemaps: draft.values } ) ) {
			draft.markSaved();
		}
	};

	return (
		<div className="wpiseo-page wpiseo-stack">
			<Section
				title={ __( 'Your sitemap', 'wpinterface-seo' ) }
				description={ __(
					'Submit this URL in Google Search Console and Bing Webmaster Tools. It replaces the WordPress core sitemap (/wp-sitemap.xml redirects here).',
					'wpinterface-seo'
				) }
			>
				{ config.sitemapUrl ? (
					<ExternalLink href={ config.sitemapUrl }>
						{ config.sitemapUrl }
					</ExternalLink>
				) : (
					<p className="wpiseo-muted">
						{ __(
							'The sitemap is not active (the module is off, or another SEO plugin is active).',
							'wpinterface-seo'
						) }
					</p>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Add the sitemap to robots.txt',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'robots_txt', true ) }
					onChange={ ( value ) => draft.set( 'robots_txt', value ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Include images', 'wpinterface-seo' ) }
					checked={ !! draft.get( 'images', true ) }
					onChange={ ( value ) => draft.set( 'images', value ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					min={ 10 }
					max={ 50000 }
					label={ __( 'URLs per sitemap file', 'wpinterface-seo' ) }
					help={ __(
						'Lower this if large sitemaps time out on your server.',
						'wpinterface-seo'
					) }
					value={ String( draft.get( 'per_page', 1000 ) ) }
					onChange={ ( value ) =>
						draft.set( 'per_page', parseInt( value, 10 ) || 1000 )
					}
				/>
			</Section>

			<Section title={ __( 'What to include', 'wpinterface-seo' ) }>
				<h3 className="wpiseo-subhead">
					{ __( 'Content types', 'wpinterface-seo' ) }
				</h3>
				{ ( config.postTypes || [] ).map( ( type ) => (
					<ToggleControl
						__nextHasNoMarginBottom
						key={ type.name }
						label={ type.label }
						checked={
							!! draft.get(
								[ 'post_types', type.name ],
								effective.sitemapTypes?.[ type.name ]
							)
						}
						onChange={ ( value ) =>
							draft.set( [ 'post_types', type.name ], value )
						}
					/>
				) ) }
				<h3 className="wpiseo-subhead">
					{ __( 'Taxonomies', 'wpinterface-seo' ) }
				</h3>
				{ ( config.taxonomies || [] ).map( ( taxonomy ) => (
					<ToggleControl
						__nextHasNoMarginBottom
						key={ taxonomy.name }
						label={ taxonomy.label }
						checked={
							!! draft.get(
								[ 'taxonomies', taxonomy.name ],
								effective.sitemapTaxes?.[ taxonomy.name ]
							)
						}
						onChange={ ( value ) =>
							draft.set( [ 'taxonomies', taxonomy.name ], value )
						}
					/>
				) ) }
				<h3 className="wpiseo-subhead">
					{ __( 'Other', 'wpinterface-seo' ) }
				</h3>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Author archives', 'wpinterface-seo' ) }
					help={ __(
						'Skipped automatically when author archives are noindex or disabled.',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'authors', true ) }
					onChange={ ( value ) => draft.set( 'authors', value ) }
				/>
			</Section>

			<Section
				title={ __( 'News & video', 'wpinterface-seo' ) }
				description={ __(
					'Extra sitemaps for specialised search features. They are listed in the sitemap index when turned on.',
					'wpinterface-seo'
				) }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Google News sitemap', 'wpinterface-seo' ) }
					help={ __(
						'Only for sites that publish news. Lists articles from the last two days, as Google News requires.',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'news', false ) }
					onChange={ ( value ) => draft.set( 'news', value ) }
				/>
				{ !! draft.get( 'news', false ) && (
					<div className="wpiseo-indent">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Publication name',
								'wpinterface-seo'
							) }
							help={ __(
								'Must match the name used in Google Publisher Center. Defaults to the site title.',
								'wpinterface-seo'
							) }
							placeholder={ config.siteName }
							value={ draft.get( 'news_name', '' ) }
							onChange={ ( value ) =>
								draft.set( 'news_name', value )
							}
						/>
						{ ( config.postTypes || [] )
							.filter( ( type ) => type.name !== 'attachment' )
							.map( ( type ) => {
								const types = draft.get( 'news_types', [
									'post',
								] );

								return (
									<CheckboxControl
										__nextHasNoMarginBottom
										key={ type.name }
										label={ type.label }
										checked={ types.includes( type.name ) }
										onChange={ ( checked ) =>
											draft.set(
												'news_types',
												checked
													? [ ...types, type.name ]
													: types.filter(
															( name ) =>
																name !==
																type.name
													  )
											)
										}
									/>
								);
							} ) }
					</div>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Video sitemap', 'wpinterface-seo' ) }
					help={ __(
						'Lists pages with YouTube embeds, video blocks or Video structured data, so Google can show them in video results.',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'video', false ) }
					onChange={ ( value ) => draft.set( 'video', value ) }
				/>
			</Section>

			<Section
				title={ __( 'Exclusions', 'wpinterface-seo' ) }
				description={ __(
					'Noindex items are left out automatically. Use these only for indexable items you still want out of the sitemap.',
					'wpinterface-seo'
				) }
			>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Exclude post IDs', 'wpinterface-seo' ) }
					help={ __(
						'Comma-separated, e.g. 12, 45',
						'wpinterface-seo'
					) }
					value={ idsToText( draft.get( 'exclude_posts', [] ) ) }
					onChange={ ( value ) =>
						draft.set( 'exclude_posts', textToIds( value ) )
					}
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Exclude term IDs', 'wpinterface-seo' ) }
					value={ idsToText( draft.get( 'exclude_terms', [] ) ) }
					onChange={ ( value ) =>
						draft.set( 'exclude_terms', textToIds( value ) )
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
