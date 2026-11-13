/**
 * Text utilities for the content analysis. Pure functions; no WordPress deps.
 */

// Letters, combining marks (Devanagari vowel signs, accents) and digits.
const WORD = /[\p{L}\p{M}\p{N}]+(?:['’-][\p{L}\p{M}\p{N}]+)*/gu;

/**
 * Parses HTML into a detached document (never touches the page DOM).
 *
 * @param {string} html HTML.
 * @return {Document} Document.
 */
export function parseHtml( html ) {
	return new window.DOMParser().parseFromString(
		`<!doctype html><body>${ html || '' }</body>`,
		'text/html'
	);
}

/**
 * Normalises text for matching: lowercase, unify apostrophes and spaces.
 *
 * @param {string} text Text.
 * @return {string} Normalised text.
 */
export function normalize( text ) {
	return ( text || '' )
		.toLocaleLowerCase()
		.replace( /[’‘`]/g, "'" )
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * Words in a text.
 *
 * @param {string} text Plain text.
 * @return {string[]} Words.
 */
export function words( text ) {
	return ( text || '' ).match( WORD ) || [];
}

/**
 * Splits plain text into sentences.
 *
 * @param {string} text Plain text.
 * @return {string[]} Sentences.
 */
export function sentences( text ) {
	// Blank lines (block boundaries) always end a sentence, e.g. after headings.
	return ( text || '' )
		.split( /\n\s*\n/ )
		.flatMap( ( block ) =>
			block
				.replace( /\s+/g, ' ' )
				// A sentence ends at . ! ? before a capital or a letter of a
				// script without case (e.g. Devanagari), and always at a danda.
				.split(
					/(?<=[.!?。！？])\s+(?=[\p{Lu}\p{Lo}\p{N}"“'(])|(?<=[।॥])\s*/u
				)
		)
		.map( ( sentence ) => sentence.trim() )
		.filter( ( sentence ) => words( sentence ).length > 0 );
}

function escapeRegExp( text ) {
	return text.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

/**
 * Number of whole-phrase occurrences of `phrase` in `text`.
 *
 * @param {string} text   Text.
 * @param {string} phrase Phrase.
 * @return {number} Count.
 */
export function countPhrase( text, phrase ) {
	const needle = normalize( phrase );

	if ( ! needle ) {
		return 0;
	}

	const pattern = new RegExp(
		`(?<![\\p{L}\\p{M}\\p{N}])${ escapeRegExp(
			needle
		) }(?![\\p{L}\\p{M}\\p{N}])`,
		'gu'
	);

	return ( normalize( text ).match( pattern ) || [] ).length;
}

/**
 * Whether every word of `phrase` appears somewhere in `text`.
 *
 * @param {string} text   Text.
 * @param {string} phrase Phrase.
 * @return {boolean} Result.
 */
export function containsAllWords( text, phrase ) {
	const haystack = new Set( words( normalize( text ) ) );
	const needles = words( normalize( phrase ) );

	return (
		needles.length > 0 && needles.every( ( word ) => haystack.has( word ) )
	);
}

/**
 * Match quality of a phrase in a text.
 *
 * @param {string} text   Text.
 * @param {string} phrase Phrase.
 * @return {'exact'|'words'|'none'} Match.
 */
export function matchPhrase( text, phrase ) {
	if ( countPhrase( text, phrase ) > 0 ) {
		return 'exact';
	}

	return containsAllWords( text, phrase ) ? 'words' : 'none';
}

/**
 * English syllable estimate (used for the Flesch reading ease score).
 *
 * @param {string} word Word.
 * @return {number} Syllables (at least 1).
 */
export function syllables( word ) {
	let clean = ( word || '' ).toLowerCase().replace( /[^a-z]/g, '' );

	if ( clean.length <= 3 ) {
		return 1;
	}

	clean = clean
		.replace( /(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '' )
		.replace( /^y/, '' );

	const groups = clean.match( /[aeiouy]{1,2}/g );

	return Math.max( 1, groups ? groups.length : 1 );
}

/**
 * Syllable estimate from vowel groups, for languages with regular spelling.
 *
 * @param {string}  word    Word.
 * @param {string}  vowels  Vowel letters of the language.
 * @param {boolean} silentE Whether a final -e/-es is silent (French).
 * @return {number} Syllables (at least 1).
 */
export function vowelGroups( word, vowels, silentE = false ) {
	const clean = ( word || '' ).toLocaleLowerCase();
	const groups = clean.match( new RegExp( `[${ vowels }]+`, 'gu' ) ) || [];
	let count = groups.length;

	if (
		silentE &&
		count > 1 &&
		new RegExp( `[^${ vowels }]es?$`, 'u' ).test( clean )
	) {
		count--;
	}

	return Math.max( 1, count );
}

/**
 * Structure of an HTML document relevant to SEO.
 *
 * @param {string} html HTML.
 * @param {string} host Site host, for internal/external link detection.
 * @return {Object} { text, paragraphs, headings, images, links, blocks }.
 */
export function analyzeHtml( html, host = '' ) {
	const doc = parseHtml( html );
	const body = doc.body;

	body.querySelectorAll( 'script,style,noscript' ).forEach( ( node ) =>
		node.remove()
	);

	const headings = Array.from(
		body.querySelectorAll( 'h1,h2,h3,h4,h5,h6' )
	).map( ( node ) => ( {
		level: Number( node.tagName.slice( 1 ) ),
		text: node.textContent.trim(),
	} ) );

	const images = Array.from( body.querySelectorAll( 'img' ) ).map(
		( node ) => ( {
			src: node.getAttribute( 'src' ) || '',
			alt: ( node.getAttribute( 'alt' ) || '' ).trim(),
		} )
	);

	const links = Array.from( body.querySelectorAll( 'a[href]' ) )
		.map( ( node ) => {
			const href = node.getAttribute( 'href' ) || '';
			const rel = ( node.getAttribute( 'rel' ) || '' ).toLowerCase();
			let internal = href.startsWith( '/' ) && ! href.startsWith( '//' );

			if ( ! internal && /^https?:\/\//i.test( href ) ) {
				try {
					internal = new URL( href ).host === host;
				} catch ( error ) {
					internal = false;
				}
			}

			return {
				href,
				internal,
				nofollow: rel.includes( 'nofollow' ),
				ignored:
					href.startsWith( '#' ) || /^(mailto|tel):/i.test( href ),
			};
		} )
		.filter( ( link ) => ! link.ignored );

	const blockSelector = 'p,li,blockquote,td,th,figcaption,dd,dt';
	const paragraphs = Array.from( body.querySelectorAll( blockSelector ) )
		.filter( ( node ) => ! node.querySelector( blockSelector ) )
		.map( ( node ) => node.textContent.replace( /\s+/g, ' ' ).trim() )
		.filter( Boolean );

	// Ordered text blocks (headings and paragraphs) for subheading distribution.
	const blocks = Array.from(
		body.querySelectorAll( `h1,h2,h3,h4,h5,h6,${ blockSelector }` )
	)
		.filter(
			( node ) =>
				/^H[1-6]$/.test( node.tagName ) ||
				! node.querySelector( blockSelector )
		)
		.map( ( node ) => ( {
			heading: /^H[1-6]$/.test( node.tagName ),
			text: node.textContent.replace( /\s+/g, ' ' ).trim(),
		} ) );

	// Text with block boundaries kept, so sentences do not run together.
	body.querySelectorAll( `h1,h2,h3,h4,h5,h6,${ blockSelector },br` ).forEach(
		( node ) => node.append( doc.createTextNode( '\n\n' ) )
	);

	const text = body.textContent.replace( /[ \t]+/g, ' ' ).trim();

	return { text, paragraphs, headings, images, links, blocks };
}
