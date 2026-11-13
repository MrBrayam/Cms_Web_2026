/**
 * Client-side rendering of %variable% templates for live previews.
 *
 * Mirrors Templates\DefaultVariables (PHP). Variables that need server data
 * (e.g. %custom_field(x)%) are rendered through the REST preview endpoint
 * and cached; they reflect the last saved post.
 */
import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';
import { useEffect, useMemo, useState } from '@wordpress/element';

import { htmlToPlainText } from './util';
import config, { metaKey } from './config';

const PATTERN = /%([a-z0-9_]+)(?:\(([^()%]*)\))?%/g;
const serverCache = new Map();

function trimWords( text, limit ) {
	const list = ( text || '' ).split( /\s+/ ).filter( Boolean );
	return list.length > limit
		? `${ list.slice( 0, limit ).join( ' ' ) }…`
		: list.join( ' ' );
}

function primaryTerm( post, meta, taxonomy ) {
	const terms = post.terms?.[ taxonomy ] || [];
	const chosen = ( meta?.[ metaKey( 'primary_terms' ) ] || {} )[ taxonomy ];
	const term = terms.find( ( item ) => item.id === chosen ) || terms[ 0 ];
	return term ? term.name : '';
}

/**
 * Local value of a variable, or undefined when it needs the server.
 *
 * @param {string} name     Variable.
 * @param {string} argument Argument.
 * @param {Object} post     Post data from the source.
 * @param {Object} meta     Meta.
 * @return {string|undefined} Value.
 */
function localValue( name, argument, post, meta ) {
	const site = config.site || {};

	switch ( name ) {
		case 'title':
			return post.title;
		case 'sitename':
			return site.name;
		case 'tagline':
			return site.tagline;
		case 'sep':
			return config.separator || '-';
		case 'excerpt': {
			const limit = Number( argument ) || 30;
			return trimWords(
				post.excerpt || htmlToPlainText( post.content ),
				limit
			);
		}
		case 'category':
			return ( post.terms?.category || [] )
				.map( ( term ) => term.name )
				.join( ', ' );
		case 'primary_category':
			return primaryTerm( post, meta, 'category' );
		case 'tag':
			return ( post.tags || [] ).join( ', ' );
		case 'term':
			return post.terms?.[ argument ]
				? post.terms[ argument ]
						.map( ( term ) => term.name )
						.join( ', ' )
				: undefined;
		case 'term_title':
		case 'term_description':
		case 'search_query':
		case 'page':
			return '';
		case 'pagenumber':
		case 'pagetotal':
			return '1';
		case 'author':
			return post.authorName;
		case 'date':
			return post.date
				? dateI18n( argument || site.dateFormat, post.date )
				: '';
		case 'modified':
			return post.modified
				? dateI18n( argument || site.dateFormat, post.modified )
				: '';
		case 'post_type_name':
			return config.postTypeLabel || '';
		case 'id':
			return String( post.id || '' );
		case 'currentyear':
			return dateI18n( 'Y', new Date() );
		case 'currentmonth':
			return dateI18n( 'F', new Date() );
		case 'currentdate':
			return dateI18n( site.dateFormat, new Date() );
		default:
			return undefined;
	}
}

/**
 * Collapses whitespace and stray separators (same rules as Parser::cleanup()).
 *
 * @param {string} text Text.
 * @return {string} Clean text.
 */
export function cleanup( text ) {
	const separator = ( config.separator || '-' ).trim();
	let result = ( text || '' ).replace( /\s+/g, ' ' ).trim();

	if ( ! separator || ! result ) {
		return result;
	}

	const sep = separator.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	result = result.replace(
		new RegExp( `(?:\\s*${ sep }\\s*){2,}`, 'g' ),
		` ${ separator } `
	);
	result = result.replace(
		new RegExp( `^\\s*${ sep }\\s*|\\s*${ sep }\\s*$`, 'g' ),
		''
	);

	return result.trim();
}

/**
 * Renders a template.
 *
 * @param {string} template Template.
 * @param {Object} post     Post data.
 * @param {Object} meta     Meta.
 * @param {Object} server   Server-rendered tokens: token => value.
 * @return {string} Text.
 */
export function renderTemplate( template, post, meta, server = {} ) {
	if ( ! template ) {
		return '';
	}

	const output = template.replace(
		PATTERN,
		( token, name, argument = '' ) => {
			const value = localValue( name, argument.trim(), post, meta );
			if ( value !== undefined ) {
				return htmlToPlainText( String( value ) );
			}
			if ( server[ token ] !== undefined ) {
				return server[ token ];
			}
			const known = ( config.variables || [] ).some(
				( variable ) => variable.name === name
			);
			return known ? '' : token;
		}
	);

	return cleanup( output );
}

/**
 * Tokens in a template that need server rendering.
 *
 * @param {string} template Template.
 * @param {Object} post     Post.
 * @param {Object} meta     Meta.
 * @return {string[]} Tokens.
 */
function serverTokens( template, post, meta ) {
	const tokens = [];
	( template || '' ).replace( PATTERN, ( token, name, argument = '' ) => {
		const known = ( config.variables || [] ).some(
			( variable ) => variable.name === name
		);
		if (
			known &&
			localValue( name, argument.trim(), post, meta ) === undefined
		) {
			tokens.push( token );
		}
		return token;
	} );
	return tokens;
}

/**
 * Renders a template, fetching server-only variables once per token.
 *
 * @param {string} template Template.
 * @param {Object} post     Post.
 * @param {Object} meta     Meta.
 * @return {string} Text.
 */
export function useRenderedTemplate( template, post, meta ) {
	const [ server, setServer ] = useState( {} );
	const tokens = serverTokens( template, post, meta );
	const tokenKey = tokens.join( '|' );

	useEffect( () => {
		let active = true;

		tokens.forEach( ( token ) => {
			const cacheKey = `${ post.id }:${ token }`;

			if ( serverCache.has( cacheKey ) ) {
				setServer( ( current ) => ( {
					...current,
					[ token ]: serverCache.get( cacheKey ),
				} ) );
				return;
			}

			apiFetch( {
				path: `/${ config.restNamespace }/variables/preview`,
				method: 'POST',
				data: { template: token, post_id: post.id },
			} )
				.then( ( response ) => {
					serverCache.set( cacheKey, response.output || '' );
					if ( active ) {
						setServer( ( current ) => ( {
							...current,
							[ token ]: response.output || '',
						} ) );
					}
				} )
				.catch( () => serverCache.set( cacheKey, '' ) );
		} );

		return () => {
			active = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ tokenKey, post.id ] );

	return useMemo(
		() => renderTemplate( template, post, meta, server ),
		[ template, post, meta, server ]
	);
}
