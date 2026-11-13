/**
 * Number and date formatting in the user's admin language (Intl, no moment.js).
 */
import { __, _n, sprintf } from '@wordpress/i18n';

import { config } from './section';

const locale = (
	config.locale ||
	document.documentElement.lang ||
	'en'
).replace( '_', '-' );

const cache = {};

function formatter( key, options ) {
	if ( ! cache[ key ] ) {
		try {
			cache[ key ] = new Intl.NumberFormat( locale, options );
		} catch ( error ) {
			cache[ key ] = new Intl.NumberFormat( 'en', options );
		}
	}

	return cache[ key ];
}

/**
 * Whole number with grouping, e.g. 1,350.
 *
 * @param {number} value Value.
 * @return {string} Formatted.
 */
export const formatNumber = ( value ) =>
	formatter( 'int', { maximumFractionDigits: 0 } ).format( value || 0 );

/**
 * Number with one decimal, e.g. 14.6.
 *
 * @param {number} value Value.
 * @return {string} Formatted.
 */
export const formatDecimal = ( value ) =>
	formatter( 'dec', {
		minimumFractionDigits: 1,
		maximumFractionDigits: 1,
	} ).format( value || 0 );

/**
 * Short number for tiles: 1,350 stays, 48,210 becomes 48.2K.
 *
 * @param {number} value Value.
 * @return {string} Formatted.
 */
export const formatCompact = ( value ) =>
	Math.abs( value ) < 10000
		? formatNumber( value )
		: formatter( 'compact', {
				notation: 'compact',
				maximumFractionDigits: 1,
		  } ).format( value );

/**
 * Percentage from a 0–100 value, e.g. 2.8%.
 *
 * @param {number} value Value.
 * @return {string} Formatted.
 */
export const formatPercent = ( value ) =>
	formatter( 'pct', { style: 'percent', maximumFractionDigits: 1 } ).format(
		( value || 0 ) / 100
	);

/**
 * Date from Y-m-d.
 *
 * @param {string} ymd   Date.
 * @param {string} style short ("Oct 5") or long ("Sunday, October 5").
 * @return {string} Formatted.
 */
export function formatDay( ymd, style = 'short' ) {
	const date = new Date( `${ ymd }T00:00:00Z` );
	const options =
		style === 'long'
			? {
					weekday: 'long',
					month: 'long',
					day: 'numeric',
					timeZone: 'UTC',
			  }
			: { month: 'short', day: 'numeric', timeZone: 'UTC' };

	try {
		return date.toLocaleDateString( locale, options );
	} catch ( error ) {
		return ymd;
	}
}

/**
 * "10 minutes ago" for a Unix timestamp.
 *
 * @param {number} timestamp Seconds.
 * @return {string} Text.
 */
export function timeAgo( timestamp ) {
	const minutes = Math.max(
		0,
		Math.round( ( Date.now() / 1000 - timestamp ) / 60 )
	);

	if ( minutes < 1 ) {
		return __( 'just now', 'wpinterface-seo' );
	}

	if ( minutes < 60 ) {
		return sprintf(
			/* translators: %d: number of minutes. */
			_n( '%d minute ago', '%d minutes ago', minutes, 'wpinterface-seo' ),
			minutes
		);
	}

	const hours = Math.round( minutes / 60 );

	if ( hours < 24 ) {
		return sprintf(
			/* translators: %d: number of hours. */
			_n( '%d hour ago', '%d hours ago', hours, 'wpinterface-seo' ),
			hours
		);
	}

	const days = Math.round( hours / 24 );

	return sprintf(
		/* translators: %d: number of days. */
		_n( '%d day ago', '%d days ago', days, 'wpinterface-seo' ),
		days
	);
}

/**
 * Percentage change, or null when there is nothing to compare with.
 *
 * @param {number} current  Current value.
 * @param {number} previous Previous value.
 * @return {number|null} Rounded change in percent.
 */
export const percentChange = ( current, previous ) =>
	previous > 0
		? Math.round( ( ( current - previous ) / previous ) * 100 )
		: null;

/**
 * Change of an average position, e.g. "1.2 places higher" (lower is better).
 *
 * @param {number} gain Previous minus current position.
 * @return {string} Text.
 */
export function placesText( gain ) {
	const places = formatDecimal( Math.abs( gain ) );

	if ( gain > 0 ) {
		return sprintf(
			/* translators: %s: number of places, e.g. 1.2. */
			__( '%s places higher', 'wpinterface-seo' ),
			places
		);
	}

	return sprintf(
		/* translators: %s: number of places, e.g. 1.2. */
		__( '%s places lower', 'wpinterface-seo' ),
		places
	);
}
