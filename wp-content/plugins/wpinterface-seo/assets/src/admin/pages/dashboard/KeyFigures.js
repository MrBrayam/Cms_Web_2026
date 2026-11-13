/**
 * Row of key figures at the top of the dashboard.
 */
import { __, _n, sprintf } from '@wordpress/i18n';

import { Meter, Sparkline } from '../../components/charts';
import {
	formatCompact,
	formatDecimal,
	formatNumber,
} from '../../components/format';
import { ChangeDelta, PositionDelta } from '../../components/searchTrend';
import { StatTile, StatTiles } from '../../components/ui';
import { averageScore, pageUrl, typeLabel } from './util';

function IndexableTile( { content } ) {
	return (
		<StatTile
			label={ __( 'Pages search engines can index', 'wpinterface-seo' ) }
			value={ formatNumber( content.indexable ) }
			unit={ sprintf(
				/* translators: %s: number of published posts and pages. */
				__( 'of %s', 'wpinterface-seo' ),
				formatNumber( content.published )
			) }
		>
			<Meter value={ content.indexable } max={ content.published } />
			<span className="wpiseo-muted">
				{ content.noindex > 0
					? sprintf(
							/* translators: %s: number of posts. */
							_n(
								'%s set to noindex',
								'%s set to noindex',
								content.noindex,
								'wpinterface-seo'
							),
							formatNumber( content.noindex )
					  )
					: __( 'Nothing is hidden from search', 'wpinterface-seo' ) }
			</span>
		</StatTile>
	);
}

function ScoreTile( { content } ) {
	const score = averageScore( content );
	let note = __( 'All published content is analyzed', 'wpinterface-seo' );

	if ( score === null ) {
		note = __( 'Scores appear as you edit posts', 'wpinterface-seo' );
	} else if ( content.none > 0 ) {
		note = sprintf(
			/* translators: %s: number of posts. */
			_n(
				'%s post not analyzed yet',
				'%s posts not analyzed yet',
				content.none,
				'wpinterface-seo'
			),
			formatNumber( content.none )
		);
	}

	return (
		<StatTile
			label={ __( 'Average SEO score', 'wpinterface-seo' ) }
			value={
				score === null
					? __( 'No score yet', 'wpinterface-seo' )
					: formatNumber( score )
			}
			unit={ score === null ? '' : '/100' }
		>
			{ score !== null && <Meter value={ score } /> }
			<span className="wpiseo-muted">{ note }</span>
		</StatTile>
	);
}

export default function KeyFigures( { data } ) {
	const { content, search, notFound } = data;

	if ( search ) {
		const { totals, previous, series } = search;

		return (
			<StatTiles label={ __( 'Key figures', 'wpinterface-seo' ) }>
				<StatTile
					label={ __( 'Clicks from Google', 'wpinterface-seo' ) }
					value={ formatCompact( totals.clicks ) }
				>
					<ChangeDelta
						current={ totals.clicks }
						previous={ previous.clicks }
						days={ 28 }
					/>
					<Sparkline values={ series.clicks } />
				</StatTile>
				<StatTile
					label={ __( 'Impressions', 'wpinterface-seo' ) }
					value={ formatCompact( totals.impressions ) }
				>
					<ChangeDelta
						current={ totals.impressions }
						previous={ previous.impressions }
						days={ 28 }
					/>
					<Sparkline values={ series.impressions } />
				</StatTile>
				<StatTile
					label={ __( 'Average position', 'wpinterface-seo' ) }
					value={
						totals.position ? formatDecimal( totals.position ) : '–'
					}
				>
					<PositionDelta
						current={ totals.position }
						previous={ previous.position }
						days={ 28 }
					/>
					<Sparkline values={ series.position } invert />
				</StatTile>
				<IndexableTile content={ content } />
				<ScoreTile content={ content } />
			</StatTiles>
		);
	}

	const notFoundUrl = pageUrl( '404-log' );

	return (
		<StatTiles label={ __( 'Key figures', 'wpinterface-seo' ) }>
			<StatTile
				label={ __( 'Published content', 'wpinterface-seo' ) }
				value={ formatNumber( content.published ) }
			>
				<span className="wpiseo-muted">
					{ content.types
						.slice( 0, 3 )
						.map(
							( type ) =>
								`${ typeLabel( type.name ) }: ${ formatNumber(
									type.published
								) }`
						)
						.join( ', ' ) }
				</span>
			</StatTile>
			<IndexableTile content={ content } />
			<ScoreTile content={ content } />
			{ notFound && (
				<StatTile
					label={ __( 'Missing pages this week', 'wpinterface-seo' ) }
					value={ formatNumber( notFound.seen ) }
				>
					{ notFound.seen > 0 && notFoundUrl ? (
						<a href={ notFoundUrl }>
							{ __( 'Open the 404 log', 'wpinterface-seo' ) }
						</a>
					) : (
						<span className="wpiseo-muted">
							{ __( 'Nothing to redirect', 'wpinterface-seo' ) }
						</span>
					) }
				</StatTile>
			) }
		</StatTiles>
	);
}
