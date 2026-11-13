/**
 * List of analysis results, worst first.
 */
import { __ } from '@wordpress/i18n';

import { band } from '../../analysis';

const ORDER = { bad: 0, ok: 1, info: 2, good: 3 };
const LABELS = {
	bad: __( 'Problem', 'wpinterface-seo' ),
	ok: __( 'Improvement', 'wpinterface-seo' ),
	good: __( 'Good', 'wpinterface-seo' ),
	info: __( 'Note', 'wpinterface-seo' ),
};

export function ScoreBadge( { score, label } ) {
	const scoreBand = band( score );

	return (
		<span className={ `wpiseo-score wpiseo-score--${ scoreBand }` }>
			<span className="wpiseo-score__dot" aria-hidden="true" />
			<span className="wpiseo-score__label">{ label }</span>
			<span className="wpiseo-score__value">
				{ score === null || score === undefined
					? '–'
					: `${ score }/100` }
			</span>
		</span>
	);
}

export default function AnalysisList( { checks } ) {
	if ( ! checks || ! checks.length ) {
		return null;
	}

	const sorted = [ ...checks ].sort(
		( a, b ) => ORDER[ a.status ] - ORDER[ b.status ]
	);

	return (
		<ul className="wpiseo-checks">
			{ sorted.map( ( check ) => (
				<li
					key={ check.id }
					className={ `wpiseo-checks__item is-${ check.status }` }
				>
					<span className="wpiseo-checks__dot" aria-hidden="true" />
					<span className="screen-reader-text">
						{ LABELS[ check.status ] }:{ ' ' }
					</span>
					{ check.message }
				</li>
			) ) }
		</ul>
	);
}
