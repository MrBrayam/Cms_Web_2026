/**
 * Module toggles. Changes take effect on the next page load.
 */
import { Button, Card, CardBody, FormToggle } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { ProBadge, ProLink, useOffer } from '../components/pro';

// What WPInterface SEO Pro adds, listed while it is not installed.
const PRO_MODULES = [
	{
		label: __( 'Site Audit', 'wpinterface-seo' ),
		description: __(
			'Weekly check of every page for SEO problems, with a health score and a broken link checker.',
			'wpinterface-seo'
		),
	},
	{
		label: __( 'Link Tools', 'wpinterface-seo' ),
		description: __(
			'Link report, orphan pages, a visual link graph and automatic links from keywords.',
			'wpinterface-seo'
		),
	},
	{
		label: __( 'Bulk AI', 'wpinterface-seo' ),
		description: __(
			'Missing titles, descriptions and alt text written for hundreds of posts in one batch.',
			'wpinterface-seo'
		),
	},
	{
		label: __( 'Schema Templates', 'wpinterface-seo' ),
		description: __(
			'Structured data templates for whole post types, and custom JSON-LD.',
			'wpinterface-seo'
		),
	},
	{
		label: __( 'Multiple Locations', 'wpinterface-seo' ),
		description: __(
			'Any number of business locations with their own schema, and a store locator.',
			'wpinterface-seo'
		),
	},
	{
		label: __( 'SEO History', 'wpinterface-seo' ),
		description: __(
			'Every change to titles, descriptions and settings, with one-click restore.',
			'wpinterface-seo'
		),
	},
	{
		label: __( 'Client Reports', 'wpinterface-seo' ),
		description: __(
			'Weekly or monthly email reports with search performance and site health.',
			'wpinterface-seo'
		),
	},
	{
		label: __( 'White Label', 'wpinterface-seo' ),
		description: __(
			'Your agency name on the plugin, menu and reports.',
			'wpinterface-seo'
		),
	},
];

function ProModules() {
	const [ visible, hide ] = useOffer( 'modules' );

	if ( ! visible ) {
		return null;
	}

	return (
		<section
			className="wpiseo-pro-modules"
			aria-labelledby="wpiseo-pro-modules"
		>
			<div className="wpiseo-pro-modules__head">
				<h2 id="wpiseo-pro-modules">
					{ __( 'Available in Pro', 'wpinterface-seo' ) }
				</h2>
				<ProLink placement="modules">
					{ __( 'See what Pro adds', 'wpinterface-seo' ) }
				</ProLink>
				<Button variant="tertiary" size="small" onClick={ hide }>
					{ __( 'Hide', 'wpinterface-seo' ) }
				</Button>
			</div>
			<div className="wpiseo-modules">
				{ PRO_MODULES.map( ( module ) => (
					<Card key={ module.label } className="wpiseo-module is-pro">
						<CardBody>
							<div className="wpiseo-module__head">
								<span className="wpiseo-module__label">
									{ module.label }
								</span>
								<ProBadge />
							</div>
							<p className="wpiseo-module__description">
								{ module.description }
							</p>
						</CardBody>
					</Card>
				) ) }
			</div>
		</section>
	);
}

function statusLabel( module ) {
	if ( ! module.implemented ) {
		return sprintf(
			/* translators: %d: roadmap phase number. */
			__( 'Coming in phase %d', 'wpinterface-seo' ),
			module.phase
		);
	}
	if ( module.required ) {
		return __( 'Always on', 'wpinterface-seo' );
	}
	if ( module.enabled && ! module.available ) {
		return module.requires_label || __( 'Unavailable', 'wpinterface-seo' );
	}

	// The toggle already shows on/off.
	return '';
}

export default function ModulesPage( { modules, save, saving } ) {
	return (
		<>
			<p className="wpiseo-muted">
				{ __(
					'Turn off what you do not need. Disabled modules load no code.',
					'wpinterface-seo'
				) }
			</p>
			<div className="wpiseo-modules">
				{ modules.map( ( module ) => {
					const locked = module.required || ! module.implemented;
					const inputId = `wpiseo-module-${ module.id }`;

					return (
						<Card
							key={ module.id }
							className={ `wpiseo-module${
								module.implemented ? '' : ' is-planned'
							}` }
						>
							<CardBody>
								<div className="wpiseo-module__head">
									<label
										htmlFor={ inputId }
										className="wpiseo-module__label"
									>
										{ module.label }
									</label>
									<FormToggle
										id={ inputId }
										checked={ module.enabled }
										disabled={ locked || saving }
										onChange={ () =>
											save( {
												modules: {
													[ module.id ]:
														! module.enabled,
												},
											} )
										}
									/>
								</div>
								<p className="wpiseo-module__description">
									{ module.description }
								</p>
								{ statusLabel( module ) && (
									<span className="wpiseo-module__status">
										{ statusLabel( module ) }
									</span>
								) }
							</CardBody>
						</Card>
					);
				} ) }
			</div>
			<ProModules />
		</>
	);
}
