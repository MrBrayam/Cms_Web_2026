/**
 * Search Console trend: measures, the summary line and the chart, shared by
 * the dashboard and the Search Performance page.
 */
import { createInterpolateElement, useMemo } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { TrendChart } from './charts';
import {
	formatDecimal,
	formatNumber,
	formatPercent,
	percentChange,
	placesText,
} from './format';
import { Delta } from './ui';

export const PERIODS = [
	{ value: 7, label: __( '7 days', 'wpinterface-seo' ) },
	{ value: 28, label: __( '28 days', 'wpinterface-seo' ) },
	{ value: 90, label: __( '3 months', 'wpinterface-seo' ) },
];

const ctr = ( row ) =>
	row.impressions > 0
		? Math.round( ( row.clicks / row.impressions ) * 1000 ) / 10
		: null;

export const MEASURES = {
	clicks: {
		label: __( 'Clicks', 'wpinterface-seo' ),
		unit: __( 'clicks', 'wpinterface-seo' ),
		value: ( row ) => row.clicks,
		format: formatNumber,
		summary: ( value ) =>
			sprintf(
				/* translators: %s: number of clicks. */
				_n(
					'<b>%s</b> click from Google',
					'<b>%s</b> clicks from Google',
					value,
					'wpinterface-seo'
				),
				formatNumber( value )
			),
	},
	impressions: {
		label: __( 'Impressions', 'wpinterface-seo' ),
		unit: __( 'impressions', 'wpinterface-seo' ),
		value: ( row ) => row.impressions,
		format: formatNumber,
		summary: ( value ) =>
			sprintf(
				/* translators: %s: number of impressions. */
				_n(
					'<b>%s</b> impression in Google Search',
					'<b>%s</b> impressions in Google Search',
					value,
					'wpinterface-seo'
				),
				formatNumber( value )
			),
	},
	position: {
		label: __( 'Position', 'wpinterface-seo' ),
		unit: __( 'average position', 'wpinterface-seo' ),
		value: ( row ) => row.position,
		format: formatDecimal,
		invert: true,
		summary: ( value ) =>
			sprintf(
				/* translators: %s: average position, e.g. 14.6. */
				__( 'Average position <b>%s</b>', 'wpinterface-seo' ),
				formatDecimal( value )
			),
	},
	ctr: {
		label: __( 'CTR', 'wpinterface-seo' ),
		unit: __( 'click-through rate', 'wpinterface-seo' ),
		value: ctr,
		format: formatPercent,
		summary: ( value ) =>
			sprintf(
				/* translators: %s: click-through rate, e.g. 2.8%. */
				__(
					'<b>%s</b> of searchers clicked through',
					'wpinterface-seo'
				),
				formatPercent( value )
			),
	},
};

const sinceLabel = ( days ) =>
	sprintf(
		/* translators: %d: number of days. */
		__( 'vs previous %d days', 'wpinterface-seo' ),
		days
	);

/**
 * Percentage change against the previous period.
 *
 * @param {Object} props          Props.
 * @param {number} props.current  Current value.
 * @param {number} props.previous Previous value.
 * @param {number} props.days     Period length.
 */
export function ChangeDelta( { current, previous, days } ) {
	const change = percentChange( current, previous );

	if ( change === null ) {
		return (
			<Delta
				text={ __( 'No earlier data to compare', 'wpinterface-seo' ) }
			/>
		);
	}

	let tone = '';

	if ( change !== 0 ) {
		tone = change > 0 ? 'up' : 'down';
	}

	return (
		<Delta
			text={ `${ change > 0 ? '+' : '' }${ change }%` }
			tone={ tone }
			context={ sinceLabel( days ) }
		/>
	);
}

/**
 * Change of an average position (lower is better).
 *
 * @param {Object} props          Props.
 * @param {number} props.current  Current position.
 * @param {number} props.previous Previous position.
 * @param {number} props.days     Period length.
 */
export function PositionDelta( { current, previous, days } ) {
	if ( ! previous || ! current ) {
		return (
			<Delta
				text={ __( 'No earlier data to compare', 'wpinterface-seo' ) }
			/>
		);
	}

	const gain = Math.round( ( previous - current ) * 10 ) / 10;

	if ( gain === 0 ) {
		return (
			<Delta
				text={ __( 'No change', 'wpinterface-seo' ) }
				context={ sinceLabel( days ) }
			/>
		);
	}

	return (
		<Delta
			tone={ gain > 0 ? 'up' : 'down' }
			text={ placesText( gain ) }
			context={ sinceLabel( days ) }
		/>
	);
}

function Summary( { measure, id, report } ) {
	const current = report.totals[ id ];
	const previous = report.previous[ id ];
	const Change = id === 'position' ? PositionDelta : ChangeDelta;

	return (
		<p className="wpiseo-search__summary">
			<span>
				{ createInterpolateElement( measure.summary( current ), {
					b: <strong />,
				} ) }
			</span>
			<Change
				current={ current }
				previous={ previous }
				days={ report.range.days }
			/>
		</p>
	);
}

/**
 * Options for a measure switcher.
 */
export const MEASURE_OPTIONS = Object.entries( MEASURES ).map(
	( [ value, item ] ) => ( { value, label: item.label } )
);

/**
 * Summary line and daily chart of one measure.
 *
 * @param {Object} props           Props.
 * @param {Object} props.report    GET /gsc/report response.
 * @param {string} props.measureId clicks, impressions, position or ctr.
 */
export function SearchTrend( { report, measureId } ) {
	const measure = MEASURES[ measureId ];
	const chart = useMemo(
		() => ( {
			dates: report.series.map( ( row ) => row.date ),
			current: report.series.map( measure.value ),
			previous: ( report.previousSeries || [] ).map( measure.value ),
		} ),
		[ report, measure ]
	);
	const days = report.range.days;

	return (
		<>
			<Summary measure={ measure } id={ measureId } report={ report } />
			<TrendChart
				dates={ chart.dates }
				current={ chart.current }
				previous={ chart.previous }
				format={ measure.format }
				unit={ measure.unit }
				invert={ !! measure.invert }
				label={ sprintf(
					/* translators: 1: measure, e.g. Clicks, 2: number of days. */
					__(
						'%1$s per day over the last %2$d days, compared with the %2$d days before',
						'wpinterface-seo'
					),
					measure.label,
					days
				) }
				currentLabel={ sprintf(
					/* translators: %d: number of days. */
					__( 'Last %d days', 'wpinterface-seo' ),
					days
				) }
				previousLabel={ sprintf(
					/* translators: %d: number of days. */
					__( 'Previous %d days', 'wpinterface-seo' ),
					days
				) }
			/>
		</>
	);
}
