/**
 * Helpers shared by the dashboard cards.
 */
import { addQueryArgs } from '@wordpress/url';

import { config } from '../../components/section';

/**
 * URL of one of the plugin's admin pages, or '' when the user cannot open it
 * (or its module is off).
 *
 * @param {string} id   Page ID.
 * @param {Object} args Extra query args.
 * @return {string} URL.
 */
export function pageUrl( id, args = {} ) {
	const url = ( config.pages || [] ).find( ( page ) => page.id === id )?.url;

	return url ? addQueryArgs( url, args ) : '';
}

/**
 * Plural label of a post type.
 *
 * @param {string} name Post type.
 * @return {string} Label.
 */
export const typeLabel = ( name ) =>
	( config.postTypes || [] ).find( ( type ) => type.name === name )?.label ||
	name;

/**
 * Average SEO score of published content, or null before any analysis.
 *
 * @param {Object} content Content figures.
 * @return {number|null} Score.
 */
export function averageScore( content ) {
	const scored = content.published - content.none;

	return scored > 0 ? Math.round( content.scoreSum / scored ) : null;
}
