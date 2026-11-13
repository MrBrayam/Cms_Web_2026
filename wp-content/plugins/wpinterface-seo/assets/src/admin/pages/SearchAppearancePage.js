/**
 * Search Appearance: title/description templates and indexing per content type.
 */
import { TabPanel, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import VariableField from '../../editor/components/VariableField';
import {
	config,
	Group,
	SaveBar,
	Section,
	useDraft,
} from '../components/section';
import StoreSettings from '../components/StoreSettings';

const effective = config.effective || {};

function TemplateGroup( { draft, path, values, withNoindex = true, extra } ) {
	const variables = config.variables || [];
	const key = ( field ) => [ ...path, field ];

	return (
		<>
			<VariableField
				label={ __( 'SEO title', 'wpinterface-seo' ) }
				value={ draft.get( key( 'title' ), values.title ) }
				onChange={ ( value ) => draft.set( key( 'title' ), value ) }
				variables={ variables }
			/>
			{ values.description !== undefined && (
				<VariableField
					multiline
					label={ __( 'Meta description', 'wpinterface-seo' ) }
					value={ draft.get(
						key( 'description' ),
						values.description
					) }
					onChange={ ( value ) =>
						draft.set( key( 'description' ), value )
					}
					variables={ variables }
				/>
			) }
			{ withNoindex && (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show in search results', 'wpinterface-seo' ) }
					help={ __(
						'Off adds noindex. Individual items can override this.',
						'wpinterface-seo'
					) }
					checked={ ! draft.get( key( 'noindex' ), values.noindex ) }
					onChange={ ( show ) =>
						draft.set( key( 'noindex' ), ! show )
					}
				/>
			) }
			{ extra }
		</>
	);
}

export default function SearchAppearancePage( {
	settings,
	modules = [],
	save,
	saving,
} ) {
	const draft = useDraft( settings.meta );
	const [ currentTab, setCurrentTab ] = useState( 'types' );
	const hasStore = modules.some(
		( module ) =>
			[ 'woocommerce', 'edd' ].includes( module.id ) && module.active
	);

	const onSave = async () => {
		if ( await save( { meta: draft.values } ) ) {
			draft.markSaved();
		}
	};

	const tabs = [
		{ name: 'types', title: __( 'Content types', 'wpinterface-seo' ) },
		{ name: 'taxonomies', title: __( 'Taxonomies', 'wpinterface-seo' ) },
		{ name: 'archives', title: __( 'Archives', 'wpinterface-seo' ) },
		{ name: 'advanced', title: __( 'Advanced', 'wpinterface-seo' ) },
	];

	if ( hasStore ) {
		tabs.push( {
			name: 'store',
			title: __( 'Online store', 'wpinterface-seo' ),
		} );
	}

	return (
		<div className="wpiseo-page">
			<TabPanel
				className="wpiseo-page-tabs"
				tabs={ tabs }
				onSelect={ setCurrentTab }
			>
				{ ( tab ) => (
					<div className="wpiseo-stack">
						{ tab.name === 'types' && (
							<Section>
								{ ( config.postTypes || [] ).map( ( type ) => (
									<Group
										key={ type.name }
										title={ type.label }
									>
										<TemplateGroup
											draft={ draft }
											path={ [ 'post_types', type.name ] }
											values={
												effective.postTypes?.[
													type.name
												] || {}
											}
										/>
										{ type.hasArchive && (
											<details className="wpiseo-details">
												<summary>
													{ sprintf(
														/* translators: %s: post type name. */
														__(
															'%s archive page',
															'wpinterface-seo'
														),
														type.label
													) }
												</summary>
												<TemplateGroup
													draft={ draft }
													path={ [
														'post_type_archives',
														type.name,
													] }
													values={
														effective
															.postTypeArchives?.[
															type.name
														] || {}
													}
												/>
											</details>
										) }
									</Group>
								) ) }
							</Section>
						) }

						{ tab.name === 'taxonomies' && (
							<Section>
								{ ( config.taxonomies || [] ).map(
									( taxonomy ) => (
										<Group
											key={ taxonomy.name }
											title={ taxonomy.label }
										>
											<TemplateGroup
												draft={ draft }
												path={ [
													'taxonomies',
													taxonomy.name,
												] }
												values={
													effective.taxonomies?.[
														taxonomy.name
													] || {}
												}
											/>
										</Group>
									)
								) }
							</Section>
						) }

						{ tab.name === 'archives' && (
							<Section>
								<Group
									title={ __(
										'Home page',
										'wpinterface-seo'
									) }
									description={ __(
										'Used for the front page. A static front page can override this in its own SEO panel.',
										'wpinterface-seo'
									) }
								>
									<TemplateGroup
										draft={ draft }
										path={ [ 'archives', 'home' ] }
										values={
											effective.archives?.home || {}
										}
										withNoindex={ false }
									/>
								</Group>
								{ [ 'author', 'date' ].map( ( archive ) => (
									<Group
										key={ archive }
										title={
											archive === 'author'
												? __(
														'Author archives',
														'wpinterface-seo'
												  )
												: __(
														'Date archives',
														'wpinterface-seo'
												  )
										}
									>
										<TemplateGroup
											draft={ draft }
											path={ [ 'archives', archive ] }
											values={
												effective.archives?.[
													archive
												] || {}
											}
											extra={
												<ToggleControl
													__nextHasNoMarginBottom
													label={ __(
														'Disable these archives (redirect to the home page)',
														'wpinterface-seo'
													) }
													checked={
														!! draft.get(
															[
																'archives',
																archive,
																'disabled',
															],
															effective
																.archives?.[
																archive
															]?.disabled
														)
													}
													onChange={ ( value ) =>
														draft.set(
															[
																'archives',
																archive,
																'disabled',
															],
															value
														)
													}
												/>
											}
										/>
									</Group>
								) ) }
								<Group
									title={ __(
										'Search results',
										'wpinterface-seo'
									) }
								>
									<TemplateGroup
										draft={ draft }
										path={ [ 'archives', 'search' ] }
										values={
											effective.archives?.search || {}
										}
										withNoindex={ false }
									/>
								</Group>
								<Group
									title={ __(
										'404 (page not found)',
										'wpinterface-seo'
									) }
								>
									<TemplateGroup
										draft={ draft }
										path={ [ 'archives', 'not_found' ] }
										values={
											effective.archives?.not_found || {}
										}
										withNoindex={ false }
									/>
								</Group>
							</Section>
						) }

						{ tab.name === 'advanced' && (
							<Section
								title={ __(
									'Indexing rules',
									'wpinterface-seo'
								) }
							>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Noindex page 2 and later of archives',
										'wpinterface-seo'
									) }
									help={ __(
										'Usually best left off: Google treats paginated pages as normal pages.',
										'wpinterface-seo'
									) }
									checked={
										!! draft.get(
											'noindex_paginated',
											false
										)
									}
									onChange={ ( value ) =>
										draft.set( 'noindex_paginated', value )
									}
								/>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Allow large previews in search (max-snippet, max-image-preview:large, max-video-preview)',
										'wpinterface-seo'
									) }
									help={ __(
										'Recommended. Large image previews are needed for Google Discover.',
										'wpinterface-seo'
									) }
									checked={
										!! draft.get( 'max_snippet', true )
									}
									onChange={ ( value ) =>
										draft.set( 'max_snippet', value )
									}
								/>
							</Section>
						) }
						{ tab.name === 'store' && (
							<StoreSettings
								settings={ settings }
								modules={ modules }
								save={ save }
								saving={ saving }
							/>
						) }
					</div>
				) }
			</TabPanel>
			{ 'store' !== currentTab && (
				<SaveBar
					onSave={ onSave }
					saving={ saving }
					dirty={ draft.dirty }
				/>
			) }
		</div>
	);
}
