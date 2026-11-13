/**
 * Search Performance: Google Search Console connection, dashboard, tracked
 * keywords, content decay, sitemaps.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	ExternalLink,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { Sparkline } from '../components/charts';
import {
	formatCompact,
	formatDay,
	formatDecimal,
	formatNumber,
	formatPercent,
	percentChange,
	timeAgo,
} from '../components/format';
import { REST_BASE } from '../components/list';
import {
	ChangeDelta,
	MEASURE_OPTIONS,
	PERIODS,
	PositionDelta,
	SearchTrend,
} from '../components/searchTrend';
import { config, SaveBar, Section, useDraft } from '../components/section';
import {
	Delta,
	HeaderActions,
	Segmented,
	StatTile,
	StatTiles,
} from '../components/ui';

const GSC = `${ REST_BASE }/gsc`;

/**
 * Coloured percentage change, or nothing when there is none.
 *
 * @param {number}  current  Current value.
 * @param {number}  previous Previous value.
 * @param {boolean} invert   Lower is better.
 * @return {Element|null} Change.
 */
function delta( current, previous, invert = false ) {
	const change = percentChange( current, previous );

	if ( ! change ) {
		return null;
	}

	const better = invert ? change < 0 : change > 0;

	return (
		<Delta
			text={ `${ change > 0 ? '+' : '' }${ change }%` }
			tone={ better ? 'up' : 'down' }
		/>
	);
}

function PageTitle( { page } ) {
	return (
		<>
			{ page.title ? (
				<strong>
					{ page.edit ? (
						<a href={ page.edit }>{ page.title }</a>
					) : (
						page.title
					) }
				</strong>
			) : null }
			<div className="wpiseo-url wpiseo-small">
				<a href={ page.url } target="_blank" rel="noreferrer">
					{ page.url }
				</a>
			</div>
		</>
	);
}

function Connect( { status, onError } ) {
	const [ write, setWrite ] = useState( false );
	const [ own, setOwn ] = useState( false );
	const [ clientId, setClientId ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const connect = async () => {
		setBusy( true );

		try {
			const response = await apiFetch( {
				path: `${ GSC }/connect`,
				method: 'POST',
				data: {
					mode: own ? 'own' : 'broker',
					write,
					client_id: clientId,
					client_secret: clientSecret,
				},
			} );
			window.location.assign( response.url );
		} catch ( caught ) {
			onError( caught.message );
			setBusy( false );
		}
	};

	return (
		<Section
			title={ __( 'Connect Google Search Console', 'wpinterface-seo' ) }
			description={ __(
				'See clicks, impressions, average position and index status for your content without leaving WordPress. Data is copied to your site once a day; nothing is shared with WPInterface.',
				'wpinterface-seo'
			) }
		>
			<ul className="wpiseo-usage">
				<li>
					{ __(
						'You sign in with Google and choose which Search Console property to use.',
						'wpinterface-seo'
					) }
				</li>
				<li>
					{ __(
						'Read-only access is requested unless you allow sitemap submission.',
						'wpinterface-seo'
					) }
				</li>
				<li>
					{ __(
						'You can disconnect at any time; access is then revoked with Google.',
						'wpinterface-seo'
					) }
				</li>
			</ul>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Allow sitemap submission', 'wpinterface-seo' ) }
				help={ __(
					'Asks for read and write access so the plugin can submit your sitemap to Google.',
					'wpinterface-seo'
				) }
				checked={ write }
				onChange={ setWrite }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Advanced: use my own Google Cloud project',
					'wpinterface-seo'
				) }
				help={ __(
					'For agencies and strict privacy needs. Sign-in then goes directly to Google without the WPInterface connection service.',
					'wpinterface-seo'
				) }
				checked={ own }
				onChange={ setOwn }
			/>
			{ own && (
				<div className="wpiseo-indent">
					<p className="wpiseo-muted">
						{ __(
							'In Google Cloud, enable the Search Console API and create an OAuth client of type “Web application” with this authorized redirect URI:',
							'wpinterface-seo'
						) }
					</p>
					<code className="wpiseo-url">{ status.callbackUrl }</code>
					<div className="wpiseo-grid-2 wpiseo-fields">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Client ID', 'wpinterface-seo' ) }
							value={ clientId }
							onChange={ setClientId }
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="password"
							label={ __( 'Client secret', 'wpinterface-seo' ) }
							value={ clientSecret }
							onChange={ setClientSecret }
						/>
					</div>
				</div>
			) }
			<Button
				variant="primary"
				onClick={ connect }
				isBusy={ busy }
				disabled={ busy || ( own && ( ! clientId || ! clientSecret ) ) }
			>
				{ __( 'Connect with Google', 'wpinterface-seo' ) }
			</Button>
		</Section>
	);
}

function PropertyPicker( { current, onSaved, onError } ) {
	const [ properties, setProperties ] = useState( null );
	const [ value, setValue ] = useState( current || '' );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		apiFetch( { path: `${ GSC }/properties` } )
			.then( ( sites ) => {
				setProperties( sites );
				if ( ! current && sites.length ) {
					setValue( sites[ 0 ].url );
				}
			} )
			.catch( ( caught ) => {
				setProperties( [] );
				onError( caught.message );
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const save = async () => {
		setBusy( true );

		try {
			onSaved(
				await apiFetch( {
					path: `${ GSC }/properties`,
					method: 'POST',
					data: { property: value },
				} )
			);
		} catch ( caught ) {
			onError( caught.message );
		} finally {
			setBusy( false );
		}
	};

	return (
		<Section
			title={ __( 'Choose a property', 'wpinterface-seo' ) }
			description={ __(
				'Pick the Search Console property for this site. A domain property (sc-domain:) covers all subdomains and protocols.',
				'wpinterface-seo'
			) }
		>
			{ properties === null && <Spinner /> }
			{ properties?.length === 0 && (
				<p>
					{ __(
						'This Google account has no Search Console properties. Add and verify your site in Search Console first (the site verification setting in SEO → General can help), then reload this page.',
						'wpinterface-seo'
					) }{ ' ' }
					<ExternalLink href="https://search.google.com/search-console">
						{ __( 'Open Search Console', 'wpinterface-seo' ) }
					</ExternalLink>
				</p>
			) }
			{ properties?.length > 0 && (
				<>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Property', 'wpinterface-seo' ) }
						value={ value }
						options={ properties.map( ( site ) => ( {
							value: site.url,
							label: site.match
								? sprintf(
										/* translators: %s: property URL. */
										__(
											'%s (this site)',
											'wpinterface-seo'
										),
										site.url
								  )
								: site.url,
						} ) ) }
						onChange={ setValue }
					/>
					<Button
						variant="primary"
						onClick={ save }
						isBusy={ busy }
						disabled={ busy || ! value }
					>
						{ __( 'Use this property', 'wpinterface-seo' ) }
					</Button>
				</>
			) }
		</Section>
	);
}

function Keywords( { settings, save, saving, report } ) {
	const [ keyword, setKeyword ] = useState( '' );
	const keywords = settings.search_console?.keywords || [];
	const rows = report?.keywords || [];

	const update = ( list ) => save( { search_console: { keywords: list } } );

	return (
		<Section
			title={ __( 'Tracked keywords', 'wpinterface-seo' ) }
			description={ __(
				'Average Google position for searches you care about, from your Search Console data. No paid rank tracker is involved, so positions only exist for searches where your site appeared.',
				'wpinterface-seo'
			) }
		>
			<form
				className="wpiseo-inline-form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					const value = keyword.trim().toLowerCase();
					if ( value && ! keywords.includes( value ) ) {
						update( [ ...keywords, value ] );
					}
					setKeyword( '' );
				} }
			>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Keyword', 'wpinterface-seo' ) }
					hideLabelFromVision
					placeholder={ __( 'Add a keyword…', 'wpinterface-seo' ) }
					value={ keyword }
					onChange={ setKeyword }
				/>
				<Button
					variant="secondary"
					type="submit"
					disabled={ saving || ! keyword.trim() }
				>
					{ __( 'Track', 'wpinterface-seo' ) }
				</Button>
			</form>
			{ keywords.length > 0 && (
				<table className="widefat striped wpiseo-table">
					<thead>
						<tr>
							<th>{ __( 'Keyword', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Position', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Trend', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Clicks', 'wpinterface-seo' ) }</th>
							<th>
								<span className="screen-reader-text">
									{ __( 'Actions', 'wpinterface-seo' ) }
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{ keywords.map( ( item ) => {
							const row = rows.find(
								( entry ) => entry.keyword === item
							);

							return (
								<tr key={ item }>
									<td>{ item }</td>
									<td>
										{ row?.current.position || '—' }{ ' ' }
										{ row &&
											delta(
												row.current.position,
												row.previous.position,
												true
											) }
									</td>
									<td>
										<Sparkline
											values={ ( row?.series || [] ).map(
												( point ) => point.position
											) }
											invert
										/>
									</td>
									<td>
										{ formatNumber( row?.current.clicks ) }
									</td>
									<td>
										<Button
											variant="link"
											isDestructive
											onClick={ () =>
												update(
													keywords.filter(
														( value ) =>
															value !== item
													)
												)
											}
										>
											{ __(
												'Remove',
												'wpinterface-seo'
											) }
										</Button>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			) }
		</Section>
	);
}

function Sitemaps( { status, onError } ) {
	const [ sitemaps, setSitemaps ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		apiFetch( { path: `${ GSC }/sitemaps` } )
			.then( setSitemaps )
			.catch( ( caught ) => setLoadError( caught.message ) );
	}, [] );

	const submit = async () => {
		setBusy( true );

		try {
			setSitemaps(
				await apiFetch( { path: `${ GSC }/sitemaps`, method: 'POST' } )
			);
		} catch ( caught ) {
			onError( caught.message );
		} finally {
			setBusy( false );
		}
	};

	const ours = ( sitemaps || [] ).some(
		( sitemap ) => sitemap.path === status.sitemapUrl
	);

	return (
		<Section title={ __( 'Sitemaps in Google', 'wpinterface-seo' ) }>
			{ loadError && (
				<Notice status="warning" isDismissible={ false }>
					{ loadError }
				</Notice>
			) }
			{ sitemaps === null && ! loadError && <Spinner /> }
			{ sitemaps?.length === 0 && (
				<p className="wpiseo-muted">
					{ __(
						'No sitemaps have been submitted for this property.',
						'wpinterface-seo'
					) }
				</p>
			) }
			{ sitemaps?.length > 0 && (
				<table className="widefat striped wpiseo-table">
					<thead>
						<tr>
							<th>{ __( 'Sitemap', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Last read', 'wpinterface-seo' ) }</th>
							<th>{ __( 'URLs', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Issues', 'wpinterface-seo' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ sitemaps.map( ( sitemap ) => (
							<tr key={ sitemap.path }>
								<td className="wpiseo-url">{ sitemap.path }</td>
								<td>
									{ sitemap.lastDownloaded
										? new Date(
												sitemap.lastDownloaded
										  ).toLocaleDateString()
										: __( 'Pending', 'wpinterface-seo' ) }
								</td>
								<td>{ formatNumber( sitemap.submitted ) }</td>
								<td>
									{ sitemap.errors || sitemap.warnings
										? sprintf(
												/* translators: 1: errors, 2: warnings. */
												__(
													'%1$d errors, %2$d warnings',
													'wpinterface-seo'
												),
												sitemap.errors,
												sitemap.warnings
										  )
										: __( 'None', 'wpinterface-seo' ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			{ status.sitemapUrl &&
				( status.canWrite ? (
					<Button
						variant="secondary"
						onClick={ submit }
						isBusy={ busy }
						disabled={ busy }
					>
						{ ours
							? __( 'Resubmit sitemap', 'wpinterface-seo' )
							: __( 'Submit sitemap', 'wpinterface-seo' ) }
					</Button>
				) : (
					! ours && (
						<p className="wpiseo-muted">
							{ __(
								'To submit your sitemap from here, disconnect and connect again with “Allow sitemap submission” turned on — or add it in Search Console:',
								'wpinterface-seo'
							) }{ ' ' }
							<code className="wpiseo-url">
								{ status.sitemapUrl }
							</code>
						</p>
					)
				) ) }
		</Section>
	);
}

function Dashboard( { status, setStatus, settings, save, saving, onError } ) {
	const [ days, setDays ] = useState( 28 );
	const [ measureId, setMeasureId ] = useState( 'clicks' );
	const [ report, setReport ] = useState( null );
	const [ syncing, setSyncing ] = useState( false );

	const load = () =>
		apiFetch( { path: `${ GSC }/report?days=${ days }` } )
			.then( setReport )
			.catch( ( caught ) => onError( caught.message ) );

	useEffect( () => {
		load();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ days, settings.search_console?.keywords?.length ] );

	const sync = async () => {
		setSyncing( true );

		try {
			setStatus(
				await apiFetch( { path: `${ GSC }/sync`, method: 'POST' } )
			);
			load();
		} catch ( caught ) {
			onError( caught.message );
		} finally {
			setSyncing( false );
		}
	};

	const syncState = status.sync || {};

	return (
		<>
			<HeaderActions>
				<span className="wpiseo-muted">
					{ syncState.last_success
						? sprintf(
								/* translators: %s: time, e.g. "10 minutes ago". */
								__( 'Synced %s', 'wpinterface-seo' ),
								timeAgo( syncState.last_success )
						  )
						: __( 'Not synced yet', 'wpinterface-seo' ) }
				</span>
				{ config.can?.manageSettings && (
					<Button
						variant="secondary"
						size="small"
						onClick={ sync }
						isBusy={ syncing }
						disabled={ syncing }
					>
						{ __( 'Sync now', 'wpinterface-seo' ) }
					</Button>
				) }
			</HeaderActions>

			{ syncState.error && (
				<Notice status="warning" isDismissible={ false }>
					{ syncState.error }
				</Notice>
			) }
			{ ( syncState.cursor || ! status.hasData ) && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Importing your Search Console history in the background. This can take a few minutes; reload the page to see progress.',
						'wpinterface-seo'
					) }
				</Notice>
			) }

			{ ! report && <Spinner /> }
			{ report && (
				<>
					<StatTiles label={ __( 'Totals', 'wpinterface-seo' ) }>
						<StatTile
							label={ __( 'Clicks', 'wpinterface-seo' ) }
							value={ formatCompact( report.totals.clicks ) }
						>
							<ChangeDelta
								current={ report.totals.clicks }
								previous={ report.previous.clicks }
								days={ report.range.days }
							/>
							<Sparkline
								values={ report.series.map(
									( point ) => point.clicks
								) }
							/>
						</StatTile>
						<StatTile
							label={ __( 'Impressions', 'wpinterface-seo' ) }
							value={ formatCompact( report.totals.impressions ) }
						>
							<ChangeDelta
								current={ report.totals.impressions }
								previous={ report.previous.impressions }
								days={ report.range.days }
							/>
							<Sparkline
								values={ report.series.map(
									( point ) => point.impressions
								) }
							/>
						</StatTile>
						<StatTile
							label={ __( 'Average CTR', 'wpinterface-seo' ) }
							value={ formatPercent( report.totals.ctr ) }
						>
							<ChangeDelta
								current={ report.totals.ctr }
								previous={ report.previous.ctr }
								days={ report.range.days }
							/>
						</StatTile>
						<StatTile
							label={ __(
								'Average position',
								'wpinterface-seo'
							) }
							value={
								report.totals.position
									? formatDecimal( report.totals.position )
									: '–'
							}
						>
							<PositionDelta
								current={ report.totals.position }
								previous={ report.previous.position }
								days={ report.range.days }
							/>
							<Sparkline
								values={ report.series.map(
									( point ) => point.position
								) }
								invert
							/>
						</StatTile>
					</StatTiles>

					<Section
						id="wpiseo-gsc-trend"
						title={ __( 'Performance', 'wpinterface-seo' ) }
						description={ status.property }
						actions={
							<>
								<Segmented
									label={ __( 'Period', 'wpinterface-seo' ) }
									value={ days }
									options={ PERIODS }
									onChange={ setDays }
								/>
								<Segmented
									label={ __( 'Measure', 'wpinterface-seo' ) }
									value={ measureId }
									options={ MEASURE_OPTIONS }
									onChange={ setMeasureId }
								/>
							</>
						}
					>
						<SearchTrend
							report={ report }
							measureId={ measureId }
						/>
						<p className="wpiseo-muted">
							{ sprintf(
								/* translators: 1: start date, 2: end date. */
								__(
									'%1$s – %2$s, compared with the period before. Google’s data is about two days behind.',
									'wpinterface-seo'
								),
								formatDay( report.range.start, 'long' ),
								formatDay( report.range.end, 'long' )
							) }
						</p>
					</Section>

					<div className="wpiseo-columns">
						<Section title={ __( 'Top pages', 'wpinterface-seo' ) }>
							<table className="widefat striped wpiseo-table">
								<thead>
									<tr>
										<th>
											{ __( 'Page', 'wpinterface-seo' ) }
										</th>
										<th>
											{ __(
												'Clicks',
												'wpinterface-seo'
											) }
										</th>
										<th>
											{ __(
												'Position',
												'wpinterface-seo'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ report.pages.length === 0 && (
										<tr>
											<td
												colSpan="3"
												className="wpiseo-muted"
											>
												{ __(
													'No data yet.',
													'wpinterface-seo'
												) }
											</td>
										</tr>
									) }
									{ report.pages.map( ( page ) => (
										<tr key={ page.url }>
											<td>
												<PageTitle page={ page } />
											</td>
											<td>
												{ formatNumber( page.clicks ) }{ ' ' }
												{ delta(
													page.clicks,
													page.previous_clicks
												) }
											</td>
											<td>{ page.position || '—' }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</Section>

						<Section
							title={ __(
								'Top searches (28 days)',
								'wpinterface-seo'
							) }
						>
							<table className="widefat striped wpiseo-table">
								<thead>
									<tr>
										<th>
											{ __( 'Query', 'wpinterface-seo' ) }
										</th>
										<th>
											{ __(
												'Clicks',
												'wpinterface-seo'
											) }
										</th>
										<th>
											{ __(
												'Position',
												'wpinterface-seo'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ report.queries.length === 0 && (
										<tr>
											<td
												colSpan="3"
												className="wpiseo-muted"
											>
												{ __(
													'No data yet.',
													'wpinterface-seo'
												) }
											</td>
										</tr>
									) }
									{ report.queries.map( ( query ) => (
										<tr key={ query.query }>
											<td>{ query.query }</td>
											<td>
												{ formatNumber( query.clicks ) }
											</td>
											<td>{ query.position }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</Section>
					</div>

					<Section
						title={ __( 'Losing traffic', 'wpinterface-seo' ) }
						description={ sprintf(
							/* translators: 1: percent, 2: minimum clicks. */
							__(
								'Pages whose clicks dropped by %1$d%% or more compared with the previous period (at least %2$d clicks before). Updating them is often the quickest win.',
								'wpinterface-seo'
							),
							settings.search_console?.decay_threshold ?? 30,
							settings.search_console?.decay_min_clicks ?? 10
						) }
					>
						{ report.decay.length === 0 ? (
							<p className="wpiseo-muted">
								{ __(
									'No pages are losing significant traffic.',
									'wpinterface-seo'
								) }
							</p>
						) : (
							<table className="widefat striped wpiseo-table">
								<tbody>
									{ report.decay.map( ( page ) => (
										<tr key={ page.url }>
											<td>
												<PageTitle page={ page } />
											</td>
											<td>
												{ sprintf(
													/* translators: 1: previous clicks, 2: current clicks. */
													__(
														'%1$s → %2$s clicks',
														'wpinterface-seo'
													),
													formatNumber(
														page.previous_clicks
													),
													formatNumber( page.clicks )
												) }
											</td>
											<td>
												<Delta
													text={ `${ page.change }%` }
													tone="down"
												/>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						) }
					</Section>

					{ config.can?.manageSettings && (
						<Keywords
							settings={ settings }
							save={ save }
							saving={ saving }
							report={ report }
						/>
					) }
				</>
			) }
		</>
	);
}

function Options( { settings, save, saving, status, setStatus, onError } ) {
	const draft = useDraft( settings.search_console );
	const [ deleteData, setDeleteData ] = useState( false );
	const [ busy, setBusy ] = useState( false );

	const onSave = async () => {
		if ( await save( { search_console: draft.values } ) ) {
			draft.markSaved();
		}
	};

	const disconnect = async () => {
		if (
			// eslint-disable-next-line no-alert -- Simple confirmation before disconnecting.
			! window.confirm(
				__( 'Disconnect Search Console?', 'wpinterface-seo' )
			)
		) {
			return;
		}

		setBusy( true );

		try {
			setStatus(
				await apiFetch( {
					path: `${ GSC }/disconnect`,
					method: 'POST',
					data: { delete_data: deleteData },
				} )
			);
		} catch ( caught ) {
			onError( caught.message );
		} finally {
			setBusy( false );
		}
	};

	const numberField = ( key, label, help, min, max ) => (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			type="number"
			min={ min }
			max={ max }
			label={ label }
			help={ help }
			value={ String( draft.get( key, '' ) ) }
			onChange={ ( value ) =>
				draft.set( key, parseInt( value, 10 ) || min )
			}
		/>
	);

	return (
		<Section title={ __( 'Settings', 'wpinterface-seo' ) }>
			<div className="wpiseo-grid-2">
				{ numberField(
					'decay_threshold',
					__( 'Traffic drop alert (%)', 'wpinterface-seo' ),
					null,
					5,
					95
				) }
				{ numberField(
					'decay_min_clicks',
					__( 'Minimum clicks for alerts', 'wpinterface-seo' ),
					null,
					1,
					100000
				) }
				{ numberField(
					'backfill_days',
					__( 'History to import (days)', 'wpinterface-seo' ),
					__(
						'Applies to the first sync. Google keeps 16 months.',
						'wpinterface-seo'
					),
					30,
					480
				) }
			</div>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Show a “Clicks (28 days)” column in post lists',
					'wpinterface-seo'
				) }
				checked={ !! draft.get( 'columns', true ) }
				onChange={ ( value ) => draft.set( 'columns', value ) }
			/>
			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>

			<h3 className="wpiseo-subhead">
				{ __( 'Connection', 'wpinterface-seo' ) }
			</h3>
			<p className="wpiseo-muted">
				{ 'own' === status.mode
					? __(
							'Connected with your own Google Cloud project.',
							'wpinterface-seo'
					  )
					: __(
							'Connected through the WPInterface connection service (it only exchanges sign-in tokens; your data goes directly from Google to this site).',
							'wpinterface-seo'
					  ) }
			</p>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __(
					'Also delete the Search Console data stored on this site',
					'wpinterface-seo'
				) }
				checked={ deleteData }
				onChange={ setDeleteData }
			/>
			<Button
				variant="secondary"
				isDestructive
				onClick={ disconnect }
				isBusy={ busy }
				disabled={ busy }
			>
				{ __( 'Disconnect', 'wpinterface-seo' ) }
			</Button>
		</Section>
	);
}

export default function SearchConsolePage( { settings, save, saving } ) {
	const [ status, setStatus ] = useState( null );
	const [ notice, setNotice ] = useState( config.gscNotice || null );

	useEffect( () => {
		apiFetch( { path: `${ GSC }/status` } )
			.then( setStatus )
			.catch( ( caught ) =>
				setNotice( { status: 'error', message: caught.message } )
			);
	}, [] );

	const onError = ( message ) => setNotice( { status: 'error', message } );
	const manage = !! config.can?.manageSettings;
	const ready = status?.connected && status?.property;

	return (
		<div className="wpiseo-page wpiseo-page--wide wpiseo-stack">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ ! status && <Spinner /> }

			{ status?.unreadable && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The saved Google connection can no longer be read (the site’s security keys changed). Please connect again.',
						'wpinterface-seo'
					) }
				</Notice>
			) }
			{ status?.needsReauth && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Google access expired or was revoked. Disconnect and connect again to resume syncing; your stored data is kept.',
						'wpinterface-seo'
					) }
				</Notice>
			) }

			{ status && ! status.connected && manage && (
				<Connect status={ status } onError={ onError } />
			) }
			{ status && ! status.connected && ! manage && (
				<Section>
					<p>
						{ __(
							'Search Console is not connected yet. Ask a site administrator to connect it.',
							'wpinterface-seo'
						) }
					</p>
				</Section>
			) }

			{ status?.connected && ! status.property && manage && (
				<PropertyPicker
					current={ status.property }
					onSaved={ setStatus }
					onError={ onError }
				/>
			) }

			{ ready && (
				<Dashboard
					status={ status }
					setStatus={ setStatus }
					settings={ settings }
					save={ save }
					saving={ saving }
					onError={ onError }
				/>
			) }

			{ ready && manage && (
				<Sitemaps status={ status } onError={ onError } />
			) }

			{ status?.connected && manage && (
				<Options
					settings={ settings }
					save={ save }
					saving={ saving }
					status={ status }
					setStatus={ setStatus }
					onError={ onError }
				/>
			) }
		</div>
	);
}
