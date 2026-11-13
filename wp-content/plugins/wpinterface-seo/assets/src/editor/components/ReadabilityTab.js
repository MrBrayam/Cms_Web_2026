/**
 * Readability tab.
 */
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { useSeoState } from '../state';
import AnalysisList, { ScoreBadge } from './AnalysisList';

export default function ReadabilityTab() {
	const { analysis } = useSeoState();

	if ( ! analysis ) {
		return (
			<PanelBody>
				<p>{ __( 'Analysing…', 'wpinterface-seo' ) }</p>
			</PanelBody>
		);
	}

	return (
		<PanelBody
			title={ __( 'Readability analysis', 'wpinterface-seo' ) }
			initialOpen
		>
			<ScoreBadge
				score={ analysis.readabilityScore }
				label={ __( 'Readability', 'wpinterface-seo' ) }
			/>
			<AnalysisList checks={ analysis.readability } />
		</PanelBody>
	);
}
