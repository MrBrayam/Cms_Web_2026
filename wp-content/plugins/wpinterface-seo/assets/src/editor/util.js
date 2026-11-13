/**
 * Small editor helpers.
 */

/**
 * Plain text from HTML without touching the page DOM.
 *
 * @param {string} html HTML.
 * @return {string} Text.
 */
export function htmlToPlainText( html ) {
	if ( ! html || ! /[<&]/.test( html ) ) {
		return html || '';
	}

	const doc = new window.DOMParser().parseFromString(
		`<!doctype html><body>${ html }</body>`,
		'text/html'
	);

	doc.body
		.querySelectorAll( 'p,li,h1,h2,h3,h4,h5,h6,br' )
		.forEach( ( node ) => node.append( doc.createTextNode( ' ' ) ) );

	return doc.body.textContent.replace( /\s+/g, ' ' ).trim();
}

/**
 * Truncates text so that measure( text ) <= max, adding an ellipsis.
 *
 * @param {string}   text    Text.
 * @param {number}   max     Maximum width.
 * @param {Function} measure Width function.
 * @return {string} Text.
 */
export function truncateToWidth( text, max, measure ) {
	if ( ! text || measure( text ) <= max ) {
		return text || '';
	}

	let low = 0;
	let high = text.length;

	while ( low < high ) {
		const mid = Math.ceil( ( low + high ) / 2 );
		if ( measure( `${ text.slice( 0, mid ) }…` ) <= max ) {
			low = mid;
		} else {
			high = mid - 1;
		}
	}

	return `${ text.slice( 0, low ).trimEnd() } …`;
}

/**
 * Truncates text to a character count at a word boundary.
 *
 * @param {string} text  Text.
 * @param {number} limit Characters.
 * @return {string} Text.
 */
export function truncateChars( text, limit ) {
	if ( ! text || text.length <= limit ) {
		return text || '';
	}

	const cut = text.slice( 0, limit );
	const space = cut.lastIndexOf( ' ' );

	return `${ ( space > limit * 0.6
		? cut.slice( 0, space )
		: cut
	).trimEnd() } …`;
}
