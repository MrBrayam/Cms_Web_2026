/**
 * Next steps: what to fix, most important first.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { ProLink, useOffer } from '../../components/pro';
import { Section } from '../../components/section';
import { StatusIcon } from '../../components/ui';

const VISIBLE = 6;

// What Pro does about some next steps.
const PRO_HINTS = {
	no_description: {
		link: 'steps-bulk-ai',
		text: __(
			'With Pro, Bulk AI writes them all in one batch.',
			'wpinterface-seo'
		),
	},
	orphans: {
		link: 'steps-auto-links',
		text: __(
			'With Pro, auto-links add links to them from keywords you choose.',
			'wpinterface-seo'
		),
	},
};

// Hidden together with the dashboard's Pro card.
function ProStepHint( { link, text } ) {
	const [ visible ] = useOffer( 'dashboard' );

	if ( ! visible ) {
		return null;
	}

	return (
		<span className="wpiseo-pro-step">
			{ text }{ ' ' }
			<ProLink placement={ link }>
				{ __( 'Learn more', 'wpinterface-seo' ) }
			</ProLink>
		</span>
	);
}

export default function NextSteps( { insights } ) {
	const [ expanded, setExpanded ] = useState( false );
	const shown = expanded ? insights : insights.slice( 0, VISIBLE );
	const hidden = insights.length - VISIBLE;

	return (
		<Section
			id="wpiseo-next-steps"
			className="wpiseo-steps"
			title={ __( 'Next steps', 'wpinterface-seo' ) }
			actions={
				insights.length > 1 && (
					<span className="wpiseo-muted">
						{ __( 'Most important first', 'wpinterface-seo' ) }
					</span>
				)
			}
		>
			{ insights.length === 0 ? (
				<p className="wpiseo-muted">
					{ __(
						'Nothing needs your attention right now. New tasks appear here when something changes.',
						'wpinterface-seo'
					) }
				</p>
			) : (
				<ul className="wpiseo-rows">
					{ shown.map( ( insight ) => (
						<li key={ insight.id } className="wpiseo-row">
							<StatusIcon type={ insight.severity } />
							<div className="wpiseo-row__text">
								<strong>{ insight.title }</strong>
								{ insight.help && (
									<span className="wpiseo-muted">
										{ insight.help }
									</span>
								) }
								{ PRO_HINTS[ insight.id ] && (
									<ProStepHint
										{ ...PRO_HINTS[ insight.id ] }
									/>
								) }
							</div>
							{ insight.action && (
								<Button
									variant="secondary"
									size="small"
									href={ insight.action.url }
								>
									{ insight.action.label }
								</Button>
							) }
						</li>
					) ) }
				</ul>
			) }
			{ hidden > 0 && (
				<Button
					variant="link"
					onClick={ () => setExpanded( ! expanded ) }
					aria-expanded={ expanded }
				>
					{ expanded
						? __( 'Show fewer', 'wpinterface-seo' )
						: sprintf(
								/* translators: %d: number of hidden next steps. */
								_n(
									'Show %d more',
									'Show %d more',
									hidden,
									'wpinterface-seo'
								),
								hidden
						  ) }
				</Button>
			) }
		</Section>
	);
}
