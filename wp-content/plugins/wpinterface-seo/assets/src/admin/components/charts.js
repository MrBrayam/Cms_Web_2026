/**
 * Small dependency-free SVG charts: a trend line with a previous-period
 * comparison on one shared axis, sparklines, meters and stacked bars.
 */
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { formatDay } from './format';

const HEIGHT = 220;
const TOP = 10;
const BOTTOM = 26;
const RIGHT = 8;

/**
 * Text anchor of an x-axis label: the first and last stay inside the plot.
 *
 * @param {number} position Label index.
 * @param {number} total    Number of labels.
 * @return {string} SVG text-anchor.
 */
function anchor( position, total ) {
	if ( position === 0 ) {
		return 'start';
	}

	return position === total - 1 ? 'end' : 'middle';
}

/**
 * A "nice" tick step (1, 2, 2.5 or 5 × 10^n) for a range.
 *
 * @param {number} range Data range.
 * @param {number} count Wanted number of intervals.
 * @return {number} Step.
 */
function niceStep( range, count ) {
	const raw = range / count || 1;
	const power = 10 ** Math.floor( Math.log10( raw ) );
	const step = [ 1, 2, 2.5, 5, 10 ].find(
		( factor ) => factor * power >= raw
	);

	return Math.max( step * power, Number.EPSILON );
}

/**
 * Axis domain and ticks. Counts start at zero; positions span their range.
 *
 * @param {number[]} values Values (nulls removed).
 * @param {boolean}  invert Lower is better (positions).
 * @return {Object} { min, max, ticks }.
 */
function scale( values, invert ) {
	const high = Math.max( ...values, 0 );
	const low = invert ? Math.min( ...values ) : 0;
	const step = niceStep( Math.max( high - low, invert ? 1 : 1 ), 4 );
	const min = invert ? Math.max( 0, Math.floor( low / step ) * step ) : 0;
	const max = Math.max( Math.ceil( high / step ) * step, min + step );
	const ticks = [];

	for ( let tick = min; tick <= max + step / 2; tick += step ) {
		ticks.push( Math.round( tick * 100 ) / 100 );
	}

	return { min, max, ticks };
}

/**
 * SVG path through the points, broken where a value is missing.
 *
 * @param {Array}    values Values (null = gap).
 * @param {Function} x      Index to x.
 * @param {Function} y      Value to y.
 * @return {string} Path data.
 */
function linePath( values, x, y ) {
	let path = '';
	let open = false;

	values.forEach( ( value, index ) => {
		if ( value === null || value === undefined ) {
			open = false;
			return;
		}

		path += `${ open ? 'L' : 'M' }${ x( index ).toFixed( 1 ) },${ y(
			value
		).toFixed( 1 ) }`;
		open = true;
	} );

	return path;
}

/**
 * Filled areas under each unbroken run of values, down to the baseline.
 *
 * @param {Array}    values   Values (null = gap).
 * @param {Function} x        Index to x.
 * @param {Function} y        Value to y.
 * @param {number}   baseline Baseline y.
 * @return {string} Path data.
 */
function areaPath( values, x, y, baseline ) {
	const base = baseline.toFixed( 1 );
	let path = '';
	let run = [];

	const close = () => {
		if ( run.length > 1 ) {
			path += `M${ x( run[ 0 ] ).toFixed( 1 ) },${ base }`;
			run.forEach( ( index ) => {
				path += `L${ x( index ).toFixed( 1 ) },${ y(
					values[ index ]
				).toFixed( 1 ) }`;
			} );
			path += `L${ x( run[ run.length - 1 ] ).toFixed( 1 ) },${ base }Z`;
		}
		run = [];
	};

	values.forEach( ( value, index ) => {
		if ( value === null || value === undefined ) {
			close();
		} else {
			run.push( index );
		}
	} );
	close();

	return path;
}

/**
 * Width of an element, kept current as it resizes.
 *
 * @param {number} initial Width before the first measurement.
 * @return {Array} [ ref, width ].
 */
function useWidth( initial ) {
	const ref = useRef();
	const [ width, setWidth ] = useState( initial );

	useLayoutEffect( () => {
		const node = ref.current;

		if ( ! node || typeof window.ResizeObserver !== 'function' ) {
			return;
		}

		setWidth( node.clientWidth || initial );

		const observer = new window.ResizeObserver( ( [ entry ] ) =>
			setWidth( Math.round( entry.contentRect.width ) || initial )
		);
		observer.observe( node );

		return () => observer.disconnect();
	}, [ initial ] );

	return [ ref, width ];
}

/**
 * Daily trend for one measure, with the previous period drawn in grey on
 * the same axis. Hover or use the arrow keys to read single days.
 *
 * @param {Object}   props               Props.
 * @param {string[]} props.dates         Dates (Y-m-d) of the current period.
 * @param {Array}    props.current       Values, null for days without data.
 * @param {Array}    props.previous      Values of the previous period (same length).
 * @param {Function} props.format        Value formatter.
 * @param {string}   props.unit          Unit after a value in the tooltip, e.g. "clicks".
 * @param {boolean}  props.invert        Lower values are better (drawn higher).
 * @param {string}   props.label         Accessible description of the chart.
 * @param {string}   props.currentLabel  Legend text of the current period.
 * @param {string}   props.previousLabel Legend text of the previous period.
 */
export function TrendChart( {
	dates,
	current,
	previous = [],
	format,
	unit,
	invert = false,
	label,
	currentLabel,
	previousLabel,
} ) {
	const [ ref, width ] = useWidth( 720 );
	const [ active, setActive ] = useState( null );
	const values = [ ...current, ...previous ].filter(
		( value ) => value !== null && value !== undefined
	);

	// Changing the measure or period resets the hovered day.
	useEffect( () => setActive( null ), [ dates, current ] );

	const count = dates.length;
	const { min, max, ticks } = scale( values.length ? values : [ 0 ], invert );
	const left =
		Math.max( ...ticks.map( ( tick ) => format( tick ).length ) ) * 7 + 10;
	const plotWidth = Math.max( 1, width - left - RIGHT );
	const plotHeight = HEIGHT - TOP - BOTTOM;
	const x = ( index ) =>
		left +
		( count > 1 ? ( index / ( count - 1 ) ) * plotWidth : plotWidth / 2 );
	const y = ( value ) => {
		const ratio = max > min ? ( value - min ) / ( max - min ) : 0;
		return TOP + ( invert ? ratio : 1 - ratio ) * plotHeight;
	};

	const pick = useCallback(
		( clientX ) => {
			const box = ref.current?.getBoundingClientRect();

			if ( ! box || count < 1 ) {
				return;
			}

			const ratio = ( clientX - box.left - left ) / plotWidth;
			setActive(
				Math.min(
					count - 1,
					Math.max( 0, Math.round( ratio * ( count - 1 ) ) )
				)
			);
		},
		[ ref, count, left, plotWidth ]
	);

	const onKeyDown = ( event ) => {
		const moves = {
			ArrowLeft: -1,
			ArrowRight: 1,
			Home: -count,
			End: count,
		};

		if ( event.key === 'Escape' ) {
			setActive( null );
			return;
		}

		if ( moves[ event.key ] !== undefined ) {
			event.preventDefault();
			setActive( ( index ) =>
				Math.min(
					count - 1,
					Math.max( 0, ( index ?? count - 1 ) + moves[ event.key ] )
				)
			);
		}
	};

	if ( ! count ) {
		return (
			<p className="wpiseo-muted">
				{ __( 'No data for this period yet.', 'wpinterface-seo' ) }
			</p>
		);
	}

	const tickCount = width < 480 ? 3 : 5;
	const xTicks = [
		...new Set(
			Array.from( { length: tickCount }, ( item, index ) =>
				Math.round( ( index / ( tickCount - 1 ) ) * ( count - 1 ) )
			)
		),
	];
	const baseline = y( invert ? max : min );
	const tipLeft = active === null ? 0 : x( active );
	const known = ( value ) => value !== null && value !== undefined;
	const describe = ( index ) =>
		[
			formatDay( dates[ index ], 'long' ),
			known( current[ index ] )
				? `${ format( current[ index ] ) } ${ unit }`
				: __( 'no data', 'wpinterface-seo' ),
			previous.length && known( previous[ index ] )
				? sprintf(
						/* translators: %s: value in the previous period. */
						__( '%s in the previous period', 'wpinterface-seo' ),
						format( previous[ index ] )
				  )
				: '',
		]
			.filter( Boolean )
			.join( ', ' );

	return (
		<figure className="wpiseo-trend">
			<div
				ref={ ref }
				className="wpiseo-trend__plot"
				tabIndex={ 0 }
				onKeyDown={ onKeyDown }
				onMouseMove={ ( event ) => pick( event.clientX ) }
				onMouseLeave={ () => setActive( null ) }
				onBlur={ () => setActive( null ) }
				role="slider"
				aria-label={ label }
				aria-valuemin={ 0 }
				aria-valuemax={ count - 1 }
				aria-valuenow={ active ?? count - 1 }
				aria-valuetext={ describe( active ?? count - 1 ) }
			>
				<svg
					width={ width }
					height={ HEIGHT }
					role="img"
					aria-label={ label }
				>
					{ ticks.map( ( tick ) => (
						<g key={ tick }>
							<line
								x1={ left }
								x2={ width - RIGHT }
								y1={ y( tick ) }
								y2={ y( tick ) }
								className={
									tick === ( invert ? max : min )
										? 'wpiseo-trend__base'
										: 'wpiseo-trend__grid'
								}
							/>
							<text
								x={ left - 8 }
								y={ y( tick ) + 4 }
								textAnchor="end"
								className="wpiseo-trend__tick"
							>
								{ format( tick ) }
							</text>
						</g>
					) ) }
					{ xTicks.map( ( index, position ) => (
						<text
							key={ index }
							x={ x( index ) }
							y={ HEIGHT - 6 }
							textAnchor={ anchor( position, xTicks.length ) }
							className="wpiseo-trend__tick"
						>
							{ formatDay( dates[ index ] ) }
						</text>
					) ) }
					{ ! invert && (
						<path
							d={ areaPath( current, x, y, baseline ) }
							className="wpiseo-trend__area"
						/>
					) }
					{ previous.length > 0 && (
						<path
							d={ linePath( previous, x, y ) }
							className="wpiseo-trend__line wpiseo-trend__line--previous"
						/>
					) }
					<path
						d={ linePath( current, x, y ) }
						className="wpiseo-trend__line"
					/>
					{ active !== null && (
						<g>
							<line
								x1={ tipLeft }
								x2={ tipLeft }
								y1={ TOP }
								y2={ HEIGHT - BOTTOM }
								className="wpiseo-trend__cursor"
							/>
							{ previous[ active ] !== null &&
								previous[ active ] !== undefined && (
									<circle
										cx={ tipLeft }
										cy={ y( previous[ active ] ) }
										r="4"
										className="wpiseo-trend__dot wpiseo-trend__dot--previous"
									/>
								) }
							{ current[ active ] !== null &&
								current[ active ] !== undefined && (
									<circle
										cx={ tipLeft }
										cy={ y( current[ active ] ) }
										r="4"
										className="wpiseo-trend__dot"
									/>
								) }
						</g>
					) }
				</svg>

				{ active !== null && (
					<div
						className="wpiseo-trend__tip"
						style={ {
							left: Math.min(
								Math.max( tipLeft, 90 ),
								Math.max( 90, width - 90 )
							),
						} }
						aria-live="polite"
					>
						<div className="wpiseo-muted">
							{ formatDay( dates[ active ], 'long' ) }
						</div>
						<div>
							<i className="wpiseo-key" aria-hidden="true" />
							<strong>
								{ current[ active ] === null
									? '–'
									: format( current[ active ] ) }
							</strong>{ ' ' }
							<span className="wpiseo-muted">{ unit }</span>
						</div>
						{ previous.length > 0 && (
							<div>
								<i
									className="wpiseo-key wpiseo-key--previous"
									aria-hidden="true"
								/>
								<strong>
									{ previous[ active ] === null ||
									previous[ active ] === undefined
										? '–'
										: format( previous[ active ] ) }
								</strong>{ ' ' }
								<span className="wpiseo-muted">
									{ __(
										'previous period',
										'wpinterface-seo'
									) }
								</span>
							</div>
						) }
					</div>
				) }
			</div>

			<figcaption className="wpiseo-legend">
				<span>
					<i className="wpiseo-key" aria-hidden="true" />
					{ currentLabel }
				</span>
				{ previous.length > 0 && (
					<span>
						<i
							className="wpiseo-key wpiseo-key--previous"
							aria-hidden="true"
						/>
						{ previousLabel }
					</span>
				) }
			</figcaption>
		</figure>
	);
}

/**
 * Sparkline for a stat tile: grey line, the latest value marked.
 *
 * @param {Object}  props        Props.
 * @param {Array}   props.values Values (null = no data).
 * @param {boolean} props.invert Lower is better (drawn higher).
 */
export function Sparkline( { values, invert = false } ) {
	const known = values.filter(
		( value ) => value !== null && value !== undefined
	);

	if ( known.length < 2 ) {
		return null;
	}

	const high = Math.max( ...known );
	const low = Math.min( ...known );
	const x = ( index ) => 2 + ( index / ( values.length - 1 ) ) * 76;
	const y = ( value ) => {
		const ratio = high > low ? ( value - low ) / ( high - low ) : 0.5;
		return 3 + ( invert ? ratio : 1 - ratio ) * 18;
	};
	const lastIndex =
		values.length -
		1 -
		[ ...values ]
			.reverse()
			.findIndex( ( value ) => value !== null && value !== undefined );

	return (
		<svg
			className="wpiseo-sparkline"
			width="80"
			height="24"
			viewBox="0 0 80 24"
			aria-hidden="true"
		>
			<path d={ linePath( values, x, y ) } />
			<circle
				cx={ x( lastIndex ) }
				cy={ y( values[ lastIndex ] ) }
				r="2.5"
			/>
		</svg>
	);
}

/**
 * Horizontal meter (decorative; the number next to it carries the value).
 *
 * @param {Object} props       Props.
 * @param {number} props.value Value.
 * @param {number} props.max   Maximum.
 */
export function Meter( { value, max = 100 } ) {
	const percent =
		max > 0 ? Math.min( 100, Math.max( 0, ( value / max ) * 100 ) ) : 0;

	return (
		<span className="wpiseo-meter" aria-hidden="true">
			<span style={ { width: `${ percent }%` } } />
		</span>
	);
}

/**
 * Stacked horizontal bar of parts of a whole.
 *
 * @param {Object}   props       Props.
 * @param {Object[]} props.parts { value, tone } in display order.
 * @param {string}   props.label Accessible summary.
 */
export function StackedBar( { parts, label } ) {
	const shown = parts.filter( ( part ) => part.value > 0 );

	return (
		<span className="wpiseo-stack-bar" role="img" aria-label={ label }>
			{ shown.map( ( part ) => (
				<span
					key={ part.tone }
					className={ `wpiseo-stack-bar__part is-${ part.tone }` }
					style={ { flexGrow: part.value } }
				/>
			) ) }
		</span>
	);
}
