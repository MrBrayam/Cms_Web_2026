/**
 * Breadcrumb settings and usage instructions.
 */
import {
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { config, SaveBar, Section, useDraft } from '../components/section';

export default function BreadcrumbsPage( { settings, save, saving } ) {
	const draft = useDraft( settings.breadcrumbs );
	const effective = config.effective?.breadcrumbTaxes || {};

	const onSave = async () => {
		if ( await save( { breadcrumbs: draft.values } ) ) {
			draft.markSaved();
		}
	};

	const text = ( key, label, placeholder = '' ) => (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			placeholder={ placeholder }
			value={ draft.get( key, '' ) }
			onChange={ ( value ) => draft.set( key, value ) }
		/>
	);

	const toggle = ( key, label, fallback = true ) => (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ label }
			checked={ !! draft.get( key, fallback ) }
			onChange={ ( value ) => draft.set( key, value ) }
		/>
	);

	return (
		<div className="wpiseo-page wpiseo-stack">
			<Section
				title={ __(
					'Add breadcrumbs to your theme',
					'wpinterface-seo'
				) }
			>
				<ul className="wpiseo-usage">
					<li>
						{ __(
							'Block themes and the block editor: add the “SEO Breadcrumbs” block.',
							'wpinterface-seo'
						) }
					</li>
					<li>
						{ __( 'Shortcode:', 'wpinterface-seo' ) }{ ' ' }
						<code>[wpiseo_breadcrumbs]</code>
					</li>
					<li>
						{ __( 'Theme template (PHP):', 'wpinterface-seo' ) }{ ' ' }
						<code>
							{
								"<?php if ( function_exists( 'wpiseo_breadcrumbs' ) ) { wpiseo_breadcrumbs(); } ?>"
							}
						</code>
					</li>
				</ul>
				<p className="wpiseo-muted">
					{ __(
						'Breadcrumb structured data is added automatically by the Schema module, even if you do not display breadcrumbs.',
						'wpinterface-seo'
					) }
				</p>
			</Section>

			<Section title={ __( 'Appearance', 'wpinterface-seo' ) }>
				<div className="wpiseo-grid-2">
					{ text(
						'separator',
						__( 'Separator', 'wpinterface-seo' ),
						'»'
					) }
					{ text(
						'home_label',
						__( 'Home label', 'wpinterface-seo' ),
						__( 'Home', 'wpinterface-seo' )
					) }
					{ text(
						'prefix',
						__( 'Prefix (optional)', 'wpinterface-seo' ),
						__( 'You are here:', 'wpinterface-seo' )
					) }
					{ text(
						'search_label',
						__( 'Search results label', 'wpinterface-seo' ),
						/* translators: %s: search phrase. */
						__( 'Search results for "%s"', 'wpinterface-seo' )
					) }
					{ text(
						'not_found_label',
						__( '404 label', 'wpinterface-seo' ),
						__( 'Page not found', 'wpinterface-seo' )
					) }
				</div>
				{ toggle(
					'show_home',
					__( 'Show the home link', 'wpinterface-seo' )
				) }
				{ toggle(
					'show_current',
					__( 'Show the current page', 'wpinterface-seo' )
				) }
				{ toggle(
					'show_blog',
					__(
						'Show the blog page for posts (static front page sites)',
						'wpinterface-seo'
					)
				) }
				{ toggle(
					'core_block',
					__(
						'Use this trail in the WordPress Breadcrumbs block too (WordPress 7.0+)',
						'wpinterface-seo'
					)
				) }
				{ toggle(
					'css',
					__(
						'Load minimal layout CSS (turn off to style it yourself)',
						'wpinterface-seo'
					)
				) }
			</Section>

			<Section
				title={ __( 'Taxonomy in the trail', 'wpinterface-seo' ) }
				description={ __(
					'Which taxonomy appears between the home page and a single item.',
					'wpinterface-seo'
				) }
			>
				{ ( config.postTypes || [] )
					.filter( ( type ) => type.taxonomies.length > 0 )
					.map( ( type ) => (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							key={ type.name }
							label={ type.label }
							value={ draft.get(
								[ 'taxonomies', type.name ],
								effective[ type.name ] || ''
							) }
							options={ [
								{
									value: '',
									label: __( 'None', 'wpinterface-seo' ),
								},
								...type.taxonomies.map( ( taxonomy ) => ( {
									value: taxonomy.name,
									label: taxonomy.label,
								} ) ),
							] }
							onChange={ ( value ) =>
								draft.set( [ 'taxonomies', type.name ], value )
							}
						/>
					) ) }
			</Section>

			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>
		</div>
	);
}
