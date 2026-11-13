/**
 * Content health: SEO scores per post type and content that needs work.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { StackedBar } from '../../components/charts';
import { formatNumber } from '../../components/format';
import { Section } from '../../components/section';
import { pageUrl, typeLabel } from './util';

const BANDS = [
	{ key: 'good', label: __( 'Good', 'wpinterface-seo' ) },
	{ key: 'ok', label: __( 'Needs work', 'wpinterface-seo' ) },
	{ key: 'bad', label: __( 'Poor', 'wpinterface-seo' ) },
	{ key: 'none', label: __( 'Not analyzed', 'wpinterface-seo' ) },
];

function Legend() {
	return (
		<span className="wpiseo-legend">
			{ BANDS.map( ( band ) => (
				<span key={ band.key }>
					<i
						className={ `wpiseo-swatch is-${ band.key }` }
						aria-hidden="true"
					/>
					{ band.label }
				</span>
			) ) }
		</span>
	);
}

function TypeRow( { type } ) {
	const label = typeLabel( type.name );
	const summary = BANDS.map( ( band ) =>
		sprintf( '%1$s %2$s', formatNumber( type[ band.key ] ), band.label )
	).join( ', ' );
	const analyzed = type.published - type.none;

	return (
		<li className="wpiseo-health__row">
			<div className="wpiseo-health__name">
				<span>{ label }</span>
				<span className="wpiseo-muted">
					{ sprintf(
						/* translators: %s: number of posts. */
						__( '%s published', 'wpinterface-seo' ),
						formatNumber( type.published )
					) }
				</span>
			</div>
			<StackedBar
				label={ `${ label }: ${ summary }` }
				parts={ BANDS.map( ( band ) => ( {
					tone: band.key,
					value: type[ band.key ],
				} ) ) }
			/>
			<span
				className={ `wpiseo-health__good${
					type.good > 0 ? ' is-up' : ' wpiseo-muted'
				}` }
			>
				{ analyzed > 0
					? sprintf(
							/* translators: %s: number of posts. */
							__( '%s good', 'wpinterface-seo' ),
							formatNumber( type.good )
					  )
					: sprintf(
							/* translators: %s: number of posts. */
							__( '%s not analyzed', 'wpinterface-seo' ),
							formatNumber( type.none )
					  ) }
			</span>
		</li>
	);
}

export default function ContentHealth( { data } ) {
	const { content } = data;
	const types = content.types.filter( ( type ) => type.published > 0 );
	const analyzed = content.published - content.none;
	const bulk = ( show ) => pageUrl( 'bulk-editor', { show } );
	const counts = [
		{
			show: 'no_description',
			value: content.noDescription,
			label: __(
				'without a written meta description',
				'wpinterface-seo'
			),
		},
		{
			show: 'no_keyword',
			value: content.noKeyword,
			label: __( 'without a focus keyword', 'wpinterface-seo' ),
		},
		{
			show: 'orphan',
			value: content.orphans,
			label: __(
				'with no internal links pointing to them',
				'wpinterface-seo'
			),
		},
		{
			show: 'noindex',
			value: content.noindex,
			label: __( 'hidden from search (noindex)', 'wpinterface-seo' ),
		},
	].filter( ( item ) => item.value !== null );

	if ( ! types.length ) {
		return (
			<Section
				id="wpiseo-health"
				title={ __( 'Content health', 'wpinterface-seo' ) }
			>
				<p className="wpiseo-muted">
					{ __(
						'Nothing is published yet. Scores and missing fields of your posts and pages appear here.',
						'wpinterface-seo'
					) }
				</p>
			</Section>
		);
	}

	return (
		<Section
			id="wpiseo-health"
			className="wpiseo-health"
			title={ __( 'Content health', 'wpinterface-seo' ) }
			actions={ analyzed > 0 && <Legend /> }
		>
			<p className="wpiseo-muted">
				{ analyzed > 0
					? __(
							'SEO analysis scores of published content. A score updates when its post is saved.',
							'wpinterface-seo'
					  )
					: __(
							'SEO analysis runs in the editor. Open a post and add a focus keyword to see its score here.',
							'wpinterface-seo'
					  ) }
			</p>
			<ul className="wpiseo-health__rows">
				{ types.map( ( type ) => (
					<TypeRow key={ type.name } type={ type } />
				) ) }
			</ul>
			{ analyzed === 0 && data.newest && (
				<div>
					<Button
						variant="secondary"
						size="small"
						href={ data.newest.edit }
					>
						{ __( 'Open your newest post', 'wpinterface-seo' ) }
					</Button>
				</div>
			) }
			<ul className="wpiseo-health__counts">
				{ counts.map( ( item ) => {
					const url = item.value > 0 ? bulk( item.show ) : '';
					const inner = (
						<>
							<span className="wpiseo-health__count">
								{ formatNumber( item.value ) }
							</span>
							<span className="wpiseo-muted">{ item.label }</span>
						</>
					);

					return (
						<li key={ item.show }>
							{ url ? <a href={ url }>{ inner }</a> : inner }
						</li>
					);
				} ) }
			</ul>
		</Section>
	);
}
