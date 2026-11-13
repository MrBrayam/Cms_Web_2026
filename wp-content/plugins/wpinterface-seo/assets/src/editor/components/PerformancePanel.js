/**
 * Google Search performance of the post (synced Search Console data) and
 * on-demand URL Inspection.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ExternalLink,
	Notice,
	PanelBody,
	Spinner,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import config from '../config';

const base = () => `/${ config.restNamespace || 'wpinterface-seo/v1' }/gsc`;

/**
 * Percent change label, e.g. "+12%".
 *
 * @param {number} current  Current value.
 * @param {number} previous Previous value.
 * @return {string} Label or ''.
 */
export function change( current, previous ) {
	if ( ! previous ) {
		return '';
	}

	const percent = Math.round( ( ( current - previous ) / previous ) * 100 );

	return `${ percent > 0 ? '+' : '' }${ percent }%`;
}

function Metric( { label, value, previous, invert = false } ) {
	const delta = change( value, previous );
	const better = invert ? value < previous : value > previous;

	return (
		<div className="wpiseo-metric">
			<span className="wpiseo-metric__label">{ label }</span>
			<strong className="wpiseo-metric__value">{ value }</strong>
			{ delta && previous !== value && (
				<span
					className={ `wpiseo-metric__change is-${
						better ? 'up' : 'down'
					}` }
				>
					{ delta }
				</span>
			) }
		</div>
	);
}

function Inspection( { result } ) {
	const rows = [
		[ __( 'Verdict', 'wpinterface-seo' ), result.verdict ],
		[ __( 'Coverage', 'wpinterface-seo' ), result.coverage ],
		[ __( 'Last crawl', 'wpinterface-seo' ), result.lastCrawl ],
		[ __( 'Google canonical', 'wpinterface-seo' ), result.googleCanonical ],
		[ __( 'Rich results', 'wpinterface-seo' ), result.richResults ],
	].filter( ( [ , value ] ) => value );

	return (
		<div className="wpiseo-inspection">
			<dl>
				{ rows.map( ( [ label, value ] ) => (
					<div key={ label }>
						<dt>{ label }</dt>
						<dd>{ value }</dd>
					</div>
				) ) }
			</dl>
			{ result.time && (
				<p className="wpiseo-help">
					{ sprintf(
						/* translators: %s: date and time. */
						__( 'Checked %s', 'wpinterface-seo' ),
						new Date( result.time * 1000 ).toLocaleString()
					) }
				</p>
			) }
			{ result.link && (
				<ExternalLink href={ result.link }>
					{ __( 'Open in Search Console', 'wpinterface-seo' ) }
				</ExternalLink>
			) }
		</div>
	);
}

export default function PerformancePanel() {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ inspecting, setInspecting ] = useState( false );
	const [ error, setError ] = useState( null );

	const load = () => {
		if ( data || loading || ! config.postId ) {
			return;
		}

		setLoading( true );
		apiFetch( { path: `${ base() }/post/${ config.postId }?days=28` } )
			.then( setData )
			.catch( ( caught ) => setError( caught.message ) )
			.finally( () => setLoading( false ) );
	};

	const inspect = async () => {
		setInspecting( true );
		setError( null );

		try {
			const inspection = await apiFetch( {
				path: `${ base() }/inspect`,
				method: 'POST',
				data: { post_id: config.postId },
			} );
			setData( ( current ) => ( { ...current, inspection } ) );
		} catch ( caught ) {
			setError( caught.message );
		} finally {
			setInspecting( false );
		}
	};

	return (
		<PanelBody
			title={ __( 'Google Search (28 days)', 'wpinterface-seo' ) }
			initialOpen={ false }
			onToggle={ ( open ) => open && load() }
		>
			{ loading && <Spinner /> }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ data && (
				<>
					<div className="wpiseo-metrics">
						<Metric
							label={ __( 'Clicks', 'wpinterface-seo' ) }
							value={ data.totals.clicks }
							previous={ data.previous.clicks }
						/>
						<Metric
							label={ __( 'Impressions', 'wpinterface-seo' ) }
							value={ data.totals.impressions }
							previous={ data.previous.impressions }
						/>
						<Metric
							label={ __( 'CTR', 'wpinterface-seo' ) }
							value={ `${ data.totals.ctr }%` }
						/>
						<Metric
							label={ __( 'Position', 'wpinterface-seo' ) }
							value={ data.totals.position || '—' }
							previous={ data.previous.position }
							invert
						/>
					</div>
					{ data.totals.impressions === 0 && (
						<p className="wpiseo-help">
							{ __(
								'No search data for this URL yet. New or recently changed content can take a few days to appear.',
								'wpinterface-seo'
							) }
						</p>
					) }
					{ data.queries.length > 0 && (
						<>
							<h3 className="wpiseo-subtitle">
								{ __( 'Top queries', 'wpinterface-seo' ) }
							</h3>
							<ul className="wpiseo-query-list">
								{ data.queries.map( ( query ) => (
									<li key={ query.query }>
										<span>{ query.query }</span>
										<span className="wpiseo-help">
											{ sprintf(
												/* translators: 1: clicks, 2: average position. */
												__(
													'%1$d clicks · pos. %2$s',
													'wpinterface-seo'
												),
												query.clicks,
												query.position
											) }
										</span>
									</li>
								) ) }
							</ul>
						</>
					) }

					<h3 className="wpiseo-subtitle">
						{ __( 'Index status', 'wpinterface-seo' ) }
					</h3>
					{ data.inspection && (
						<Inspection result={ data.inspection } />
					) }
					<Button
						variant="secondary"
						onClick={ inspect }
						isBusy={ inspecting }
						disabled={ inspecting }
					>
						{ data.inspection
							? __( 'Check again', 'wpinterface-seo' )
							: __( 'Check index status', 'wpinterface-seo' ) }
					</Button>
				</>
			) }
		</PanelBody>
	);
}
