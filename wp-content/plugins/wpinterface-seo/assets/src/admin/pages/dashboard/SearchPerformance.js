/**
 * Search performance: daily trend against the previous period, top pages
 * and top searches. Reads the local Search Console tables.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	formatDecimal,
	formatNumber,
	percentChange,
} from '../../components/format';
import { REST_BASE } from '../../components/list';
import {
	MEASURE_OPTIONS,
	PERIODS,
	SearchTrend,
} from '../../components/searchTrend';
import { Section } from '../../components/section';
import { Segmented } from '../../components/ui';
import { pageUrl } from './util';

function TopTables( { report } ) {
	const pages = report.pages.slice( 0, 5 );
	const queries = report.queries.slice( 0, 5 );

	if ( ! pages.length && ! queries.length ) {
		return null;
	}

	return (
		<div className="wpiseo-split">
			<div>
				<h3 className="wpiseo-subhead">
					{ __( 'Top pages', 'wpinterface-seo' ) }
				</h3>
				<table className="wpiseo-mini-table">
					<thead>
						<tr>
							<th scope="col">
								{ __( 'Page', 'wpinterface-seo' ) }
							</th>
							<th scope="col" className="is-num">
								{ __( 'Clicks', 'wpinterface-seo' ) }
							</th>
							<th scope="col" className="is-num">
								{ __( 'Change', 'wpinterface-seo' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ pages.map( ( page ) => {
							const change = percentChange(
								page.clicks,
								page.previous_clicks
							);
							let tone = '';

							if ( change ) {
								tone = change > 0 ? 'is-up' : 'is-down';
							}

							return (
								<tr key={ page.url }>
									<td>
										<a href={ page.url }>
											{ page.title ||
												page.url.replace(
													/^https?:\/\/[^/]+/,
													''
												) ||
												'/' }
										</a>
									</td>
									<td className="is-num">
										{ formatNumber( page.clicks ) }
									</td>
									<td className={ `is-num ${ tone }` }>
										{ change === null
											? '–'
											: `${
													change > 0 ? '+' : ''
											  }${ change }%` }
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</div>
			<div>
				<h3 className="wpiseo-subhead">
					{ __( 'Top searches', 'wpinterface-seo' ) }
				</h3>
				<table className="wpiseo-mini-table">
					<thead>
						<tr>
							<th scope="col">
								{ __( 'Search', 'wpinterface-seo' ) }
							</th>
							<th scope="col" className="is-num">
								{ __( 'Clicks', 'wpinterface-seo' ) }
							</th>
							<th scope="col" className="is-num">
								{ __( 'Position', 'wpinterface-seo' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ queries.map( ( query ) => (
							<tr key={ query.query }>
								<td>{ query.query }</td>
								<td className="is-num">
									{ formatNumber( query.clicks ) }
								</td>
								<td className="is-num">
									{ formatDecimal( query.position ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</div>
	);
}

function Invitation( { connectUrl } ) {
	return (
		<div className="wpiseo-invite">
			<svg
				width="220"
				height="96"
				viewBox="0 0 220 96"
				aria-hidden="true"
				className="wpiseo-invite__art"
			>
				<line x1="0" x2="220" y1="88" y2="88" stroke="#dcdcde" />
				<line x1="0" x2="220" y1="58" y2="58" stroke="#f0f0f1" />
				<line x1="0" x2="220" y1="28" y2="28" stroke="#f0f0f1" />
				<polyline
					points="4,80 30,74 56,76 82,62 108,66 134,50 160,46 186,34 214,22"
					fill="none"
					stroke="#c3c4c7"
					strokeWidth="2"
					strokeLinejoin="round"
					strokeLinecap="round"
				/>
			</svg>
			<div>
				<h3>
					{ __(
						'See how people find you on Google',
						'wpinterface-seo'
					) }
				</h3>
				<p className="wpiseo-muted">
					{ __(
						'Connect Search Console to show clicks, impressions, top searches and pages that are losing traffic here. Data is read from Google once a day and stays on your site.',
						'wpinterface-seo'
					) }
				</p>
				{ connectUrl && (
					<Button variant="primary" href={ connectUrl }>
						{ __( 'Connect Search Console', 'wpinterface-seo' ) }
					</Button>
				) }
			</div>
		</div>
	);
}

export default function SearchPerformance( { data } ) {
	const [ days, setDays ] = useState( 28 );
	const [ measureId, setMeasureId ] = useState( 'clicks' );
	const [ report, setReport ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const consoleUrl = pageUrl( 'search-console' );
	const ready = !! data.search;

	useEffect( () => {
		if ( ! ready ) {
			return;
		}

		setLoading( true );
		setError( null );
		apiFetch( { path: `${ REST_BASE }/gsc/report?days=${ days }` } )
			.then( setReport )
			.catch( ( caught ) => setError( caught.message ) )
			.finally( () => setLoading( false ) );
	}, [ ready, days ] );

	if ( ! data.site.gscConnected ) {
		return (
			<Section
				id="wpiseo-search"
				title={ __( 'Search performance', 'wpinterface-seo' ) }
			>
				<Invitation connectUrl={ consoleUrl } />
			</Section>
		);
	}

	if ( ! ready ) {
		return (
			<Section
				id="wpiseo-search"
				title={ __( 'Search performance', 'wpinterface-seo' ) }
			>
				<p className="wpiseo-muted">
					{ __(
						'Search Console is connected. Figures appear here after the first sync, usually within an hour.',
						'wpinterface-seo'
					) }
				</p>
			</Section>
		);
	}

	return (
		<Section
			id="wpiseo-search"
			className="wpiseo-search"
			title={ __( 'Search performance', 'wpinterface-seo' ) }
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
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ report && (
				<div
					className={ `wpiseo-search__body${
						loading ? ' is-loading' : ''
					}` }
					aria-busy={ loading }
				>
					<SearchTrend report={ report } measureId={ measureId } />
					<TopTables report={ report } />
				</div>
			) }
			{ ! report && loading && (
				<p className="wpiseo-muted">
					{ __( 'Loading…', 'wpinterface-seo' ) }
				</p>
			) }
			{ consoleUrl && (
				<p>
					<a href={ consoleUrl }>
						{ __( 'Open Search Performance', 'wpinterface-seo' ) }
					</a>
				</p>
			) }
		</Section>
	);
}
