/**
 * SEO dashboard: key figures, next steps, search performance, content health
 * and site status at a glance.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { Fragment, useCallback, useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';

import { timeAgo } from '../../components/format';
import { REST_BASE } from '../../components/list';
import { HeaderActions } from '../../components/ui';
import ContentHealth from './ContentHealth';
import KeyFigures from './KeyFigures';
import NextSteps from './NextSteps';
import AuditOffer from './ProOffer';
import SearchPerformance from './SearchPerformance';
import Setup from './Setup';
import { MissingPages, MoreTools, SiteChecks, Sitemap } from './SideCards';

/**
 * Built-in cards. `area` is main (wide column) or side (narrow column).
 *
 * @param {Object}  data      Dashboard data.
 * @param {boolean} showSetup Whether the setup checklist is shown.
 * @return {Object[]} Cards: { id, area, render( data ) => element }.
 */
function defaultCards( data, showSetup ) {
	const cards = [];

	if ( data.insights.length || ! showSetup ) {
		cards.push( {
			id: 'next-steps',
			area: 'main',
			render: () => <NextSteps insights={ data.insights } />,
		} );
	}

	if ( data.site.gscModule ) {
		cards.push( {
			id: 'search',
			area: 'main',
			render: () => <SearchPerformance data={ data } />,
		} );
	}

	cards.push(
		{
			id: 'content-health',
			area: 'main',
			render: () => <ContentHealth data={ data } />,
		},
		{
			id: 'site-checks',
			area: 'side',
			render: () => <SiteChecks checks={ data.checks } />,
		},
		{
			id: 'pro-audit',
			area: 'side',
			render: () => <AuditOffer content={ data.content } />,
		}
	);

	if ( data.notFound ) {
		cards.push( {
			id: 'missing-pages',
			area: 'side',
			render: () => (
				<MissingPages
					notFound={ data.notFound }
					redirects={ data.redirects }
				/>
			),
		} );
	}

	if ( data.sitemap ) {
		cards.push( {
			id: 'sitemap',
			area: 'side',
			render: () => (
				<Sitemap
					sitemap={ data.sitemap }
					connected={ data.site.gscConnected }
				/>
			),
		} );
	}

	if ( showSetup ) {
		cards.push( {
			id: 'more-tools',
			area: 'side',
			render: () => <MoreTools />,
		} );
	}

	return cards;
}

export default function DashboardPage() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ , setTick ] = useState( 0 );

	const load = useCallback( ( refresh = false ) => {
		setRefreshing( refresh );
		setError( null );

		return apiFetch( {
			path: `${ REST_BASE }/dashboard${ refresh ? '?refresh=1' : '' }`,
		} )
			.then( setData )
			.catch( ( caught ) => setError( caught.message ) )
			.finally( () => setRefreshing( false ) );
	}, [] );

	useEffect( () => {
		load();

		// Keeps "Updated … ago" current.
		const timer = setInterval(
			() => setTick( ( tick ) => tick + 1 ),
			60000
		);
		return () => clearInterval( timer );
	}, [ load ] );

	const hideSetup = () => {
		setData( ( current ) => ( {
			...current,
			setup: { ...current.setup, hidden: true },
		} ) );
		apiFetch( {
			path: `${ REST_BASE }/dashboard/setup`,
			method: 'POST',
			data: { hidden: true },
		} ).catch( ( caught ) => setError( caught.message ) );
	};

	if ( ! data ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const showSetup =
		! data.setup.hidden && data.setup.tasks.some( ( task ) => ! task.done );

	/**
	 * Filters the dashboard cards. Add-ons insert their own, e.g. after
	 * `site-checks`. Each card: { id, area: 'main'|'side', render( data ) },
	 * where render returns an element.
	 *
	 * @param {Object[]} cards Cards in display order.
	 * @param {Object}   data  Dashboard data (GET /dashboard).
	 */
	const cards = applyFilters(
		'wpinterface-seo.dashboard.cards',
		defaultCards( data, showSetup ),
		data
	);
	const column = ( area ) =>
		cards
			.filter( ( card ) => card.area === area )
			.map( ( card ) => (
				<Fragment key={ card.id }>{ card.render( data ) }</Fragment>
			) );

	return (
		<div className="wpiseo-dashboard">
			<HeaderActions>
				<span className="wpiseo-muted">
					{ sprintf(
						/* translators: %s: time, e.g. "10 minutes ago". */
						__( 'Updated %s', 'wpinterface-seo' ),
						timeAgo( data.generated )
					) }
				</span>
				<Button
					variant="secondary"
					size="small"
					onClick={ () => load( true ) }
					isBusy={ refreshing }
					disabled={ refreshing }
				>
					{ __( 'Refresh', 'wpinterface-seo' ) }
				</Button>
			</HeaderActions>

			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			{ showSetup && (
				<Setup tasks={ data.setup.tasks } onHide={ hideSetup } />
			) }

			<KeyFigures data={ data } />

			<div className="wpiseo-dashboard__columns">
				<div className="wpiseo-dashboard__main">
					{ column( 'main' ) }
				</div>
				<aside
					className="wpiseo-dashboard__side"
					aria-label={ __( 'At a glance', 'wpinterface-seo' ) }
				>
					{ column( 'side' ) }
				</aside>
			</div>
		</div>
	);
}
