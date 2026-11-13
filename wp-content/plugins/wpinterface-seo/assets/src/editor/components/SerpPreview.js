/**
 * Google-style search result preview.
 */
import { __ } from '@wordpress/i18n';

import { measureTitle } from '../../analysis';
import config from '../config';
import { truncateChars, truncateToWidth } from '../util';

function breadcrumbUrl( url ) {
	try {
		const parsed = new URL( url );
		const parts = parsed.pathname.split( '/' ).filter( Boolean );
		return [ parsed.host, ...parts ].join( ' › ' );
	} catch ( error ) {
		return url || '';
	}
}

export default function SerpPreview( { title, description, url, mobile } ) {
	const shownTitle = truncateToWidth(
		title,
		mobile ? 500 : 600,
		measureTitle
	);
	const shownDescription = truncateChars( description, mobile ? 120 : 158 );

	return (
		<div
			className={ `wpiseo-serp${ mobile ? ' is-mobile' : '' }` }
			aria-label={ __( 'Search result preview', 'wpinterface-seo' ) }
		>
			<div className="wpiseo-serp__site">
				<span className="wpiseo-serp__favicon" aria-hidden="true">
					{ ( config.site?.name || '?' ).charAt( 0 ) }
				</span>
				<span>
					<span className="wpiseo-serp__name">
						{ config.site?.name }
					</span>
					<span className="wpiseo-serp__url">
						{ breadcrumbUrl( url ) }
					</span>
				</span>
			</div>
			<div className="wpiseo-serp__title">{ shownTitle }</div>
			<div className="wpiseo-serp__description">
				{ shownDescription || (
					<em>
						{ __(
							'No description: the search engine will show text from the page.',
							'wpinterface-seo'
						) }
					</em>
				) }
			</div>
		</div>
	);
}
