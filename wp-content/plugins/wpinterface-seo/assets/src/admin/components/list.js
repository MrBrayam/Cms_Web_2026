/**
 * Helpers for paginated REST lists (redirects, 404 log, bulk editor).
 */
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { config } from './section';

export const REST_BASE = `/${ config.restNamespace || 'wpinterface-seo/v1' }`;

/**
 * Loads a paginated collection; the total comes from the X-WP-Total header.
 *
 * @param {string} path    Route below the plugin namespace, e.g. '/redirects'.
 * @param {Object} query   Query args.
 * @param {number} refresh Change to force a reload.
 * @return {Object} { items, total, loading, error, reload, setItems }.
 */
export function useRemoteList( path, query, refresh = 0 ) {
	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const key = JSON.stringify( query );

	const reload = useCallback( () => {
		setLoading( true );
		setError( null );

		return apiFetch( {
			path: addQueryArgs( REST_BASE + path, JSON.parse( key ) ),
			parse: false,
		} )
			.then( async ( response ) => {
				setTotal(
					parseInt( response.headers.get( 'X-WP-Total' ), 10 ) || 0
				);
				setItems( await response.json() );
			} )
			.catch( async ( caught ) => {
				let message = caught?.message;

				if ( ! message && typeof caught?.json === 'function' ) {
					message = ( await caught.json().catch( () => ( {} ) ) )
						.message;
				}

				setError(
					message ||
						__( 'Could not load the list.', 'wpinterface-seo' )
				);
			} )
			.finally( () => setLoading( false ) );
	}, [ path, key ] );

	// Debounced so typing in a search box sends one request.
	useEffect( () => {
		const timer = setTimeout( reload, 250 );
		return () => clearTimeout( timer );
	}, [ reload, refresh ] );

	return { items, total, loading, error, reload, setItems };
}

export function Pagination( { page, perPage, total, onChange } ) {
	const pages = Math.max( 1, Math.ceil( total / perPage ) );

	return (
		<div className="wpiseo-pagination">
			<span className="wpiseo-muted">
				{ sprintf(
					/* translators: %d: number of items. */
					_n( '%d item', '%d items', total, 'wpinterface-seo' ),
					total
				) }
			</span>
			{ pages > 1 && (
				<>
					<Button
						size="compact"
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => onChange( page - 1 ) }
						label={ __( 'Previous page', 'wpinterface-seo' ) }
					>
						‹
					</Button>
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: number of pages. */
							__( '%1$d of %2$d', 'wpinterface-seo' ),
							page,
							pages
						) }
					</span>
					<Button
						size="compact"
						variant="secondary"
						disabled={ page >= pages }
						onClick={ () => onChange( page + 1 ) }
						label={ __( 'Next page', 'wpinterface-seo' ) }
					>
						›
					</Button>
				</>
			) }
		</div>
	);
}

/**
 * Selection of row IDs.
 *
 * @return {Object} { selected, toggle, toggleAll, clear, has }.
 */
export function useSelection() {
	const [ selected, setSelected ] = useState( [] );

	return {
		selected,
		has: ( id ) => selected.includes( id ),
		toggle: ( id ) =>
			setSelected( ( current ) =>
				current.includes( id )
					? current.filter( ( item ) => item !== id )
					: [ ...current, id ]
			),
		toggleAll: ( ids ) =>
			setSelected( ( current ) =>
				ids.every( ( id ) => current.includes( id ) ) ? [] : ids
			),
		clear: () => setSelected( [] ),
	};
}

/**
 * Starts a browser download of text content.
 *
 * @param {string} filename File name.
 * @param {string} content  Content.
 * @param {string} type     MIME type.
 */
export function download( filename, content, type ) {
	const url = window.URL.createObjectURL(
		new window.Blob( [ content ], { type } )
	);
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	link.click();
	window.URL.revokeObjectURL( url );
}

/**
 * Formats a GMT MySQL datetime in the browser's locale.
 *
 * @param {string} value 'Y-m-d H:i:s' in UTC.
 * @return {string} Local date and time, or an em dash.
 */
export function formatDate( value ) {
	if ( ! value || value.startsWith( '0000' ) ) {
		return '—';
	}

	const date = new Date( value.replace( ' ', 'T' ) + 'Z' );

	return Number.isNaN( date.getTime() ) ? value : date.toLocaleString();
}
