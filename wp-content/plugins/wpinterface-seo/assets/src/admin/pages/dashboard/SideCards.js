/**
 * Cards in the dashboard's narrow column.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { formatNumber } from '../../components/format';
import { Section } from '../../components/section';
import { StatusIcon } from '../../components/ui';
import { pageUrl } from './util';

/**
 * Icon for a site check: blocking problems are critical.
 *
 * @param {Object} check Check.
 * @return {string} StatusIcon type.
 */
function checkIcon( check ) {
	if ( check.ok ) {
		return 'ok';
	}

	return check.id === 'indexing' ? 'critical' : 'warning';
}

export function SiteChecks( { checks } ) {
	const passed = checks.filter( ( check ) => check.ok ).length;

	return (
		<Section
			id="wpiseo-checks"
			title={ __( 'Site checks', 'wpinterface-seo' ) }
			actions={
				<span className="wpiseo-muted">
					{ sprintf(
						/* translators: 1: passed checks, 2: all checks. */
						__( '%1$d of %2$d passed', 'wpinterface-seo' ),
						passed,
						checks.length
					) }
				</span>
			}
		>
			<ul className="wpiseo-checklist">
				{ checks.map( ( check ) => (
					<li key={ check.id }>
						<StatusIcon type={ checkIcon( check ) } />
						{ check.ok ? (
							<span>{ check.label }</span>
						) : (
							<span>
								<strong>{ check.label }</strong>{ ' ' }
								{ check.help && (
									<span className="wpiseo-muted">
										{ check.help }
									</span>
								) }{ ' ' }
								{ check.fix && (
									<a href={ check.fix.url }>
										{ check.fix.label }
									</a>
								) }
							</span>
						) }
					</li>
				) ) }
			</ul>
		</Section>
	);
}

export function MissingPages( { notFound, redirects } ) {
	const logUrl = pageUrl( '404-log' );

	return (
		<Section
			id="wpiseo-missing"
			title={ __( 'Missing pages', 'wpinterface-seo' ) }
			actions={
				logUrl && (
					<a href={ logUrl }>
						{ __( '404 log', 'wpinterface-seo' ) }
					</a>
				)
			}
		>
			<p>
				{ notFound.new > 0 ? (
					<strong>
						{ sprintf(
							/* translators: %s: number of URLs. */
							_n(
								'%s new this week.',
								'%s new this week.',
								notFound.new,
								'wpinterface-seo'
							),
							formatNumber( notFound.new )
						) }
					</strong>
				) : (
					__( 'No new missing pages this week.', 'wpinterface-seo' )
				) }{ ' ' }
				{ redirects &&
					sprintf(
						/* translators: %s: number of redirects. */
						_n(
							'%s redirect is active.',
							'%s redirects are active.',
							redirects.active,
							'wpinterface-seo'
						),
						formatNumber( redirects.active )
					) }
			</p>
			{ notFound.top.length > 0 && (
				<table className="wpiseo-mini-table">
					<thead>
						<tr>
							<th scope="col">
								{ __( 'URL', 'wpinterface-seo' ) }
							</th>
							<th scope="col" className="is-num">
								{ __( 'Visits', 'wpinterface-seo' ) }
							</th>
							{ logUrl && (
								<th scope="col" className="is-num">
									<span className="screen-reader-text">
										{ __( 'Action', 'wpinterface-seo' ) }
									</span>
								</th>
							) }
						</tr>
					</thead>
					<tbody>
						{ notFound.top.map( ( row ) => (
							<tr key={ row.url }>
								<td className="wpiseo-url">{ row.url }</td>
								<td className="is-num">
									{ formatNumber( row.hits ) }
								</td>
								{ logUrl && (
									<td className="is-num">
										<a href={ logUrl }>
											{ __(
												'Redirect',
												'wpinterface-seo'
											) }
										</a>
									</td>
								) }
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</Section>
	);
}

export function Sitemap( { sitemap, connected } ) {
	const [ copied, setCopied ] = useState( false );

	const copy = () => {
		window.navigator.clipboard
			?.writeText( sitemap.url )
			.then( () => {
				setCopied( true );
				setTimeout( () => setCopied( false ), 2000 );
			} )
			.catch( () => {} );
	};

	return (
		<Section
			id="wpiseo-sitemap"
			title={ __( 'Sitemap', 'wpinterface-seo' ) }
			actions={
				<a href={ sitemap.url } target="_blank" rel="noreferrer">
					{ __( 'View', 'wpinterface-seo' ) }
					<span className="screen-reader-text">
						{ __( '(opens in a new tab)', 'wpinterface-seo' ) }
					</span>
				</a>
			}
		>
			<div className="wpiseo-copy">
				<code>{ sitemap.url }</code>
				<Button variant="secondary" size="small" onClick={ copy }>
					{ copied
						? __( 'Copied', 'wpinterface-seo' )
						: __( 'Copy', 'wpinterface-seo' ) }
				</Button>
			</div>
			<p className="wpiseo-muted">
				{ sprintf(
					/* translators: %s: number of sitemap files. */
					_n(
						'%s sitemap file.',
						'%s sitemap files.',
						sitemap.files,
						'wpinterface-seo'
					),
					formatNumber( sitemap.files )
				) }{ ' ' }
				{ connected
					? __(
							'Search engines find it through robots.txt.',
							'wpinterface-seo'
					  )
					: __(
							'Submit it in Search Console after connecting.',
							'wpinterface-seo'
					  ) }
			</p>
		</Section>
	);
}

export function MoreTools() {
	const links = [
		{
			url: pageUrl( 'tools' ),
			label: __( 'Import from another SEO plugin', 'wpinterface-seo' ),
		},
		{
			url: pageUrl( 'redirects' ),
			label: __( 'Set up redirects', 'wpinterface-seo' ),
		},
		{
			url: pageUrl( 'modules' ),
			label: __( 'Turn modules on or off', 'wpinterface-seo' ),
		},
	].filter( ( link ) => link.url );

	if ( ! links.length ) {
		return null;
	}

	return (
		<Section
			id="wpiseo-tools"
			title={ __( 'More tools', 'wpinterface-seo' ) }
		>
			<ul className="wpiseo-links">
				{ links.map( ( link ) => (
					<li key={ link.url }>
						<a href={ link.url }>{ link.label }</a>
					</li>
				) ) }
			</ul>
		</Section>
	);
}
