/**
 * Pointers to WPInterface SEO Pro: shown only while Pro is not installed,
 * only to people who manage SEO settings, and each one can be hidden.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { REST_BASE } from './list';
import { config } from './section';

const pro = config.pro || null;
const hidden = new Set( pro?.hidden || [] );
const listeners = new Set();

/**
 * Pro page URL for one placement.
 *
 * @param {string} placement Where the link is shown.
 * @return {string} URL.
 */
export const proUrl = ( placement ) =>
	pro ? addQueryArgs( pro.url, { utm_content: placement } ) : '';

/**
 * Visibility of one offer, and a function that hides it for good.
 *
 * @param {string} id Offer ID.
 * @return {Array} [ visible, hide ].
 */
export function useOffer( id ) {
	const [ visible, setVisible ] = useState( !! pro && ! hidden.has( id ) );

	// Every place that shows the same offer hides together.
	useEffect( () => {
		const update = () => setVisible( !! pro && ! hidden.has( id ) );
		listeners.add( update );
		return () => listeners.delete( update );
	}, [ id ] );

	const hide = () => {
		hidden.add( id );
		listeners.forEach( ( update ) => update() );
		apiFetch( {
			path: `${ REST_BASE }/offers/hide`,
			method: 'POST',
			data: { id },
		} ).catch( () => {} );
	};

	return [ visible, hide ];
}

export function ProBadge() {
	return (
		<span className="wpiseo-pro-badge">
			{ __( 'Pro', 'wpinterface-seo' ) }
		</span>
	);
}

/**
 * Link to the Pro page that opens in a new tab.
 *
 * @param {Object}  props           Props.
 * @param {string}  props.placement Where the link is shown.
 * @param {Element} props.children  Link text.
 */
export function ProLink( { placement, children } ) {
	return (
		<a
			href={ proUrl( placement ) }
			target="_blank"
			rel="noopener noreferrer"
		>
			{ children }
			<span className="screen-reader-text">
				{ __( '(opens in a new tab)', 'wpinterface-seo' ) }
			</span>
		</a>
	);
}

/**
 * One-line hint at the end of a page, e.g. under the Local SEO settings.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.id       Offer ID (also the link placement).
 * @param {Element} props.children Text.
 */
export function ProHint( { id, children } ) {
	const [ visible, hide ] = useOffer( id );

	if ( ! visible ) {
		return null;
	}

	return (
		<p className="wpiseo-pro-hint">
			<ProBadge />
			<span>
				{ children }{ ' ' }
				<ProLink placement={ id }>
					{ __( 'See what Pro adds', 'wpinterface-seo' ) }
				</ProLink>
			</span>
			<Button
				className="wpiseo-pro-hint__hide"
				variant="tertiary"
				size="small"
				onClick={ hide }
			>
				{ __( 'Hide', 'wpinterface-seo' ) }
			</Button>
		</p>
	);
}
