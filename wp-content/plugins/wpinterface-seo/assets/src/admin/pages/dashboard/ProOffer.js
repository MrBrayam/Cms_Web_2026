/**
 * "Site audit" teaser for sites without Pro, using the site's own numbers.
 */
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

import { formatNumber } from '../../components/format';
import { ProBadge, ProLink, useOffer } from '../../components/pro';
import { Section } from '../../components/section';

export default function AuditOffer( { content } ) {
	const [ visible, hide ] = useOffer( 'dashboard' );

	if ( ! visible ) {
		return null;
	}

	const pages = sprintf(
		/* translators: %s: number of published posts and pages. */
		_n(
			'your %s published page',
			'all %s published pages',
			content.published,
			'wpinterface-seo'
		),
		formatNumber( content.published )
	);
	const linkTotal = content.links
		? content.links.internal + content.links.external
		: 0;
	const links =
		linkTotal > 0
			? sprintf(
					/* translators: %s: number of links. */
					_n(
						'%s link',
						'all %s links',
						linkTotal,
						'wpinterface-seo'
					),
					formatNumber( linkTotal )
			  )
			: __( 'every link', 'wpinterface-seo' );

	return (
		<Section
			id="wpiseo-audit-offer"
			className="wpiseo-pro-offer"
			title={ __( 'Site audit', 'wpinterface-seo' ) }
			actions={ <ProBadge /> }
		>
			<p>
				{ __(
					'Broken links and duplicate titles cost visitors without any warning.',
					'wpinterface-seo'
				) }{ ' ' }
				{ sprintf(
					/* translators: 1: e.g. "all 42 published pages", 2: e.g. "all 380 links". */
					__(
						'Pro checks %1$s and tests %2$s every week, on your own server, then lists what to fix.',
						'wpinterface-seo'
					),
					pages,
					links
				) }
			</p>
			<ul className="wpiseo-pro-offer__list">
				<li>
					{ __( 'Broken and redirected links', 'wpinterface-seo' ) }
				</li>
				<li>
					{ __(
						'Duplicate, missing or too long titles and descriptions',
						'wpinterface-seo'
					) }
				</li>
				<li>
					{ __(
						'Orphan pages, thin content and images without alt text',
						'wpinterface-seo'
					) }
				</li>
			</ul>
			<div className="wpiseo-pro-offer__actions">
				<ProLink placement="dashboard-audit">
					{ __( 'See what Pro adds', 'wpinterface-seo' ) }
				</ProLink>
				<Button variant="tertiary" size="small" onClick={ hide }>
					{ __( 'Hide', 'wpinterface-seo' ) }
				</Button>
			</div>
		</Section>
	);
}
