/**
 * "Finish setting up" checklist for new sites.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { Meter } from '../../components/charts';
import { Section } from '../../components/section';
import { StatusIcon } from '../../components/ui';

export default function Setup( { tasks, onHide } ) {
	const done = tasks.filter( ( task ) => task.done ).length;
	// The first open step is the one to do now.
	const next = tasks.find( ( task ) => ! task.done );

	return (
		<Section
			id="wpiseo-setup"
			className="wpiseo-setup-card"
			title={ __( 'Finish setting up', 'wpinterface-seo' ) }
			description={ __(
				'A few steps get your site ready for search. Each takes a few minutes.',
				'wpinterface-seo'
			) }
			actions={
				<>
					<span className="wpiseo-setup-card__progress">
						<Meter value={ done } max={ tasks.length } />
						<span className="wpiseo-muted">
							{ sprintf(
								/* translators: 1: finished steps, 2: all steps. */
								__( '%1$d of %2$d done', 'wpinterface-seo' ),
								done,
								tasks.length
							) }
						</span>
					</span>
					<Button variant="tertiary" size="small" onClick={ onHide }>
						{ __( 'Hide', 'wpinterface-seo' ) }
					</Button>
				</>
			}
		>
			<ol className="wpiseo-rows">
				{ tasks.map( ( task ) => (
					<li
						key={ task.id }
						className={ `wpiseo-row${
							task.done ? ' is-done' : ''
						}` }
					>
						<StatusIcon type={ task.done ? 'ok' : 'todo' } />
						<div className="wpiseo-row__text">
							<strong>{ task.label }</strong>
							<span className="wpiseo-muted">{ task.help }</span>
						</div>
						{ ! task.done && task.action && (
							<Button
								variant={
									task === next ? 'primary' : 'secondary'
								}
								size="small"
								href={ task.action.url }
							>
								{ task.action.label }
							</Button>
						) }
					</li>
				) ) }
			</ol>
		</Section>
	);
}
