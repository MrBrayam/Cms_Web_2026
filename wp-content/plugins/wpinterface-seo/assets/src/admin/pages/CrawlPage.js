/**
 * Robots & crawl: robots.txt rules, AI crawlers, head clean-up, feeds,
 * IndexNow and llms.txt.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	ExternalLink,
	Notice,
	TabPanel,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { REST_BASE } from '../components/list';
import { config, SaveBar, Section, useDraft } from '../components/section';

const CLEANUP = [
	{
		key: 'generator',
		label: __( 'WordPress version (generator tag)', 'wpinterface-seo' ),
	},
	{
		key: 'rsd',
		label: __( 'RSD link (used by old blogging apps)', 'wpinterface-seo' ),
	},
	{ key: 'shortlink', label: __( 'Shortlink (?p=123)', 'wpinterface-seo' ) },
	{
		key: 'rest_links',
		label: __( 'REST API discovery links', 'wpinterface-seo' ),
	},
	{
		key: 'oembed_links',
		label: __( 'oEmbed discovery links', 'wpinterface-seo' ),
	},
	{
		key: 'emoji',
		label: __( 'Emoji detection script and styles', 'wpinterface-seo' ),
	},
	{
		key: 'feed_links_extra',
		label: __(
			'Extra feed links (comments, categories, tags)',
			'wpinterface-seo'
		),
	},
];

function RobotsTab( { draft } ) {
	return (
		<Section
			title={ __( 'robots.txt', 'wpinterface-seo' ) }
			description={ __(
				'Tells crawlers which parts of the site to skip. It does not remove pages from search results: use noindex for that.',
				'wpinterface-seo'
			) }
		>
			{ config.physicalRobots && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'A robots.txt file exists in your site folder, so WordPress (and these settings) cannot change it. Delete or rename the file to use the rules below.',
						'wpinterface-seo'
					) }
				</Notice>
			) }
			<p>
				<ExternalLink href={ config.robotsUrl }>
					{ __( 'View your robots.txt', 'wpinterface-seo' ) }
				</ExternalLink>
			</p>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Extra rules', 'wpinterface-seo' ) }
				help={ __(
					'Added after the WordPress defaults and the sitemap line. Example: “User-agent: *” on one line and “Disallow: /private/” on the next.',
					'wpinterface-seo'
				) }
				rows={ 8 }
				className="wpiseo-code"
				value={ draft.get( 'robots_rules', '' ) }
				onChange={ ( value ) => draft.set( 'robots_rules', value ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Ask AI companies not to train on this site',
					'wpinterface-seo'
				) }
				help={ sprintf(
					/* translators: %s: list of crawler names. */
					__(
						'Blocks these crawlers: %s. Search engines and AI search answers are not affected. Crawlers can ignore robots.txt.',
						'wpinterface-seo'
					),
					( config.aiBots || [] ).join( ', ' )
				) }
				checked={ !! draft.get( 'block_ai', false ) }
				onChange={ ( value ) => draft.set( 'block_ai', value ) }
			/>
		</Section>
	);
}

function CleanupTab( { draft } ) {
	return (
		<>
			<Section
				title={ __( 'Head clean-up', 'wpinterface-seo' ) }
				description={ __(
					'Remove tags WordPress adds to every page. None of these affect rankings; removing them saves a few bytes and hides details from scanners. Keep REST and oEmbed links if other sites embed your posts.',
					'wpinterface-seo'
				) }
			>
				{ CLEANUP.map( ( item ) => (
					<ToggleControl
						__nextHasNoMarginBottom
						key={ item.key }
						label={ item.label }
						checked={
							!! draft.get( [ 'cleanup', item.key ], false )
						}
						onChange={ ( value ) =>
							draft.set( [ 'cleanup', item.key ], value )
						}
					/>
				) ) }
			</Section>

			<Section title={ __( 'RSS feeds', 'wpinterface-seo' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Keep feeds out of search results',
						'wpinterface-seo'
					) }
					help={ __(
						'Sends “noindex, follow” for feeds. Feed readers are not affected.',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'noindex_feeds', true ) }
					onChange={ ( value ) =>
						draft.set( 'noindex_feeds', value )
					}
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Before each feed item', 'wpinterface-seo' ) }
					rows={ 2 }
					value={ draft.get( 'rss_before', '' ) }
					onChange={ ( value ) => draft.set( 'rss_before', value ) }
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'After each feed item', 'wpinterface-seo' ) }
					help={ sprintf(
						/* translators: %s: list of variables. */
						__(
							'Credits you when sites copy your feed. Variables: %s.',
							'wpinterface-seo'
						),
						'%post_link%, %blog_link%, %post_title%, %site_name%'
					) }
					rows={ 2 }
					value={ draft.get( 'rss_after', '' ) }
					onChange={ ( value ) => draft.set( 'rss_after', value ) }
				/>
			</Section>
		</>
	);
}

function IndexNowTab( { draft, savedOn } ) {
	const [ info, setInfo ] = useState( null );
	const [ urls, setUrls ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );

	const load = () =>
		apiFetch( { path: `${ REST_BASE }/indexnow` } )
			.then( setInfo )
			.catch( () => setInfo( { history: [] } ) );

	useEffect( () => {
		load();
	}, [ savedOn ] );

	const submit = async () => {
		setBusy( true );
		setResult( null );

		try {
			const response = await apiFetch( {
				path: `${ REST_BASE }/indexnow`,
				method: 'POST',
				data: {
					urls: urls
						.split( /\s+/ )
						.map( ( url ) => url.trim() )
						.filter( Boolean ),
				},
			} );
			setResult( {
				status: [ 200, 202 ].includes( response.code )
					? 'success'
					: 'error',
				text: response.message,
			} );
			setUrls( '' );
			load();
		} catch ( caught ) {
			setResult( { status: 'error', text: caught.message } );
		} finally {
			setBusy( false );
		}
	};

	const enabled = !! draft.get( 'indexnow', false );

	return (
		<Section
			title={ __( 'IndexNow', 'wpinterface-seo' ) }
			description={ __(
				'Tells Bing, Yandex, Seznam, Naver and other IndexNow search engines right away when you publish, update or delete a post. Google does not use IndexNow.',
				'wpinterface-seo'
			) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Turn on IndexNow', 'wpinterface-seo' ) }
				help={ __(
					'Sends public URLs only, a few seconds after you save. Nothing is sent from local or staging sites.',
					'wpinterface-seo'
				) }
				checked={ enabled }
				onChange={ ( value ) => draft.set( 'indexnow', value ) }
			/>

			{ enabled && ! draft.get( 'indexnow_key', '' ) && (
				<p className="wpiseo-muted">
					{ __(
						'Save to create your IndexNow key.',
						'wpinterface-seo'
					) }
				</p>
			) }

			{ enabled && info?.keyUrl && (
				<>
					<p>
						{ __( 'Key file:', 'wpinterface-seo' ) }{ ' ' }
						<ExternalLink href={ info.keyUrl }>
							{ info.keyUrl }
						</ExternalLink>
					</p>
					{ ! info.isPublic && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This looks like a local or staging site, so automatic submissions are paused.',
								'wpinterface-seo'
							) }
						</Notice>
					) }
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Submit URLs now', 'wpinterface-seo' ) }
						help={ __(
							'One URL per line, up to 100. URLs must be on this site.',
							'wpinterface-seo'
						) }
						rows={ 3 }
						value={ urls }
						onChange={ setUrls }
					/>
					<Button
						variant="secondary"
						onClick={ submit }
						isBusy={ busy }
						disabled={ busy || ! urls.trim() || draft.dirty }
					>
						{ __( 'Submit', 'wpinterface-seo' ) }
					</Button>
					{ result && (
						<Notice
							status={ result.status }
							onRemove={ () => setResult( null ) }
						>
							{ result.text }
						</Notice>
					) }
				</>
			) }

			{ info?.history?.length > 0 && (
				<>
					<h3 className="wpiseo-subhead">
						{ __( 'Recent submissions', 'wpinterface-seo' ) }
					</h3>
					<table className="widefat striped wpiseo-table">
						<thead>
							<tr>
								<th>{ __( 'Time', 'wpinterface-seo' ) }</th>
								<th>{ __( 'URLs', 'wpinterface-seo' ) }</th>
								<th>{ __( 'Response', 'wpinterface-seo' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ info.history.map( ( entry, index ) => (
								<tr key={ index }>
									<td>
										{ new Date(
											entry.time * 1000
										).toLocaleString() }
									</td>
									<td>{ entry.urls }</td>
									<td>
										{ entry.code
											? `${ entry.code } ${
													entry.message || ''
											  }`
											: entry.message }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</>
			) }
		</Section>
	);
}

function LlmsTab( { draft, savedOn } ) {
	const [ preview, setPreview ] = useState( null );
	const enabled = !! draft.get( 'llms', false );
	const types = draft.get( 'llms_post_types', [ 'page', 'post' ] );

	useEffect( () => {
		if ( enabled ) {
			apiFetch( { path: `${ REST_BASE }/llms-txt` } )
				.then( setPreview )
				.catch( () => setPreview( null ) );
		}
	}, [ enabled, savedOn ] );

	return (
		<Section
			title={ __( 'llms.txt', 'wpinterface-seo' ) }
			description={ __(
				'A plain-text index of your key pages for AI assistants (llmstxt.org). It is a proposal: Google does not use it, and few AI tools read it yet.',
				'wpinterface-seo'
			) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Serve /llms.txt', 'wpinterface-seo' ) }
				checked={ enabled }
				onChange={ ( value ) => draft.set( 'llms', value ) }
			/>
			{ enabled && (
				<>
					<h3 className="wpiseo-subhead">
						{ __( 'Include', 'wpinterface-seo' ) }
					</h3>
					{ ( config.postTypes || [] )
						.filter( ( type ) => type.name !== 'attachment' )
						.map( ( type ) => (
							<CheckboxControl
								__nextHasNoMarginBottom
								key={ type.name }
								label={ type.label }
								checked={ types.includes( type.name ) }
								onChange={ ( checked ) =>
									draft.set(
										'llms_post_types',
										checked
											? [ ...types, type.name ]
											: types.filter(
													( name ) =>
														name !== type.name
											  )
									)
								}
							/>
						) ) }
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="number"
						min={ 1 }
						max={ 500 }
						label={ __(
							'Items per content type',
							'wpinterface-seo'
						) }
						value={ String( draft.get( 'llms_limit', 30 ) ) }
						onChange={ ( value ) =>
							draft.set(
								'llms_limit',
								parseInt( value, 10 ) || 30
							)
						}
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Introduction', 'wpinterface-seo' ) }
						help={ __(
							'A short description of the site, shown below the tagline.',
							'wpinterface-seo'
						) }
						rows={ 3 }
						value={ draft.get( 'llms_intro', '' ) }
						onChange={ ( value ) =>
							draft.set( 'llms_intro', value )
						}
					/>
					{ preview && (
						<>
							<h3 className="wpiseo-subhead">
								{ __( 'Preview', 'wpinterface-seo' ) }{ ' ' }
								<ExternalLink href={ preview.url }>
									{ preview.url }
								</ExternalLink>
							</h3>
							<pre className="wpiseo-preview">
								{ preview.content }
							</pre>
						</>
					) }
				</>
			) }
		</Section>
	);
}

export default function CrawlPage( { settings, save, saving } ) {
	const draft = useDraft( settings.crawl );
	const [ savedOn, setSavedOn ] = useState( 0 );

	const onSave = async () => {
		if ( await save( { crawl: draft.values } ) ) {
			draft.markSaved();
			setSavedOn( Date.now() );
		}
	};

	// Pick up the IndexNow key generated on save.
	useEffect( () => {
		if ( settings.crawl?.indexnow_key ) {
			draft.set( 'indexnow_key', settings.crawl.indexnow_key );
			draft.markSaved();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ settings.crawl?.indexnow_key ] );

	const tabs = [
		{ name: 'robots', title: __( 'robots.txt', 'wpinterface-seo' ) },
		{ name: 'cleanup', title: __( 'Clean-up & feeds', 'wpinterface-seo' ) },
		{ name: 'indexnow', title: __( 'IndexNow', 'wpinterface-seo' ) },
		{ name: 'llms', title: __( 'llms.txt', 'wpinterface-seo' ) },
	];

	return (
		<div className="wpiseo-page">
			<TabPanel className="wpiseo-page-tabs" tabs={ tabs }>
				{ ( tab ) => (
					<div className="wpiseo-stack">
						{ 'robots' === tab.name && (
							<RobotsTab draft={ draft } />
						) }
						{ 'cleanup' === tab.name && (
							<CleanupTab draft={ draft } />
						) }
						{ 'indexnow' === tab.name && (
							<IndexNowTab draft={ draft } savedOn={ savedOn } />
						) }
						{ 'llms' === tab.name && (
							<LlmsTab draft={ draft } savedOn={ savedOn } />
						) }
					</div>
				) }
			</TabPanel>
			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>
		</div>
	);
}
