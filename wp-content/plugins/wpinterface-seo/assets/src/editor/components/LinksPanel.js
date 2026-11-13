/**
 * Internal links: counts, posts linking here, and link suggestions.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, PanelBody, Spinner } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { analyzeHtml } from '../../analysis/text';
import config, { metaKey } from '../config';
import { getMeta } from '../source/context';
import { useSeoState } from '../state';

const base = () => `/${ config.restNamespace || 'wpinterface-seo/v1' }/links`;

function Suggestion( { item } ) {
	const [ copied, setCopied ] = useState( false );

	const copy = () => {
		window.navigator.clipboard?.writeText( item.url ).then( () => {
			setCopied( true );
			window.setTimeout( () => setCopied( false ), 1500 );
		} );
	};

	return (
		<li className="wpiseo-link-suggestion">
			<a href={ item.url } target="_blank" rel="noreferrer">
				{ item.title || item.url }
			</a>
			{ item.anchor && (
				<span className="wpiseo-help">
					{ sprintf(
						/* translators: %s: suggested anchor text. */
						__( 'Anchor: “%s”', 'wpinterface-seo' ),
						item.anchor
					) }
				</span>
			) }
			<Button variant="link" size="small" onClick={ copy }>
				{ copied
					? __( 'Copied', 'wpinterface-seo' )
					: __( 'Copy link', 'wpinterface-seo' ) }
			</Button>
		</li>
	);
}

export default function LinksPanel() {
	const { source } = useSeoState();
	const { post, meta } = source;
	const [ data, setData ] = useState( null );
	const [ suggestions, setSuggestions ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const links = useMemo(
		() => analyzeHtml( post.content || '', config.site?.host || '' ).links,
		[ post.content ]
	);
	const internal = links.filter( ( link ) => link.internal ).length;
	const external = links.length - internal;

	useEffect( () => {
		if ( ! config.postId ) {
			return;
		}

		apiFetch( { path: `${ base() }/${ config.postId }` } )
			.then( setData )
			.catch( () => setData( null ) );
	}, [] );

	const suggest = async () => {
		setBusy( true );
		setError( null );

		try {
			setSuggestions(
				await apiFetch( {
					path: `${ base() }/suggest`,
					method: 'POST',
					data: {
						post_id: config.postId,
						content: post.content,
						keywords: getMeta(
							meta,
							metaKey( 'focus_keywords' ),
							[]
						),
					},
				} )
			);
		} catch ( caught ) {
			setError( caught.message );
		} finally {
			setBusy( false );
		}
	};

	return (
		<PanelBody
			title={ __( 'Internal links', 'wpinterface-seo' ) }
			initialOpen={ false }
		>
			<p>
				{ sprintf(
					/* translators: 1: internal links, 2: external links. */
					__(
						'This content has %1$d internal and %2$d external links.',
						'wpinterface-seo'
					),
					internal,
					external
				) }
			</p>

			{ data && (
				<>
					<p>
						{ sprintf(
							/* translators: %d: number of posts. */
							_n(
								'%d published post links here.',
								'%d published posts link here.',
								data.counts.inbound,
								'wpinterface-seo'
							),
							data.counts.inbound
						) }
					</p>
					{ data.inbound.length > 0 && (
						<ul className="wpiseo-link-list">
							{ data.inbound.map( ( item ) => (
								<li key={ item.id }>
									{ item.edit ? (
										<a href={ item.edit }>{ item.title }</a>
									) : (
										item.title
									) }
								</li>
							) ) }
						</ul>
					) }
					{ data.counts.inbound === 0 && (
						<p className="wpiseo-help">
							{ __(
								'Orphan content is hard for search engines to find. Link to it from related posts.',
								'wpinterface-seo'
							) }
						</p>
					) }
				</>
			) }

			<Button variant="secondary" onClick={ suggest } disabled={ busy }>
				{ __( 'Suggest links to add', 'wpinterface-seo' ) }
			</Button>
			{ busy && <Spinner /> }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ suggestions && (
				<ul className="wpiseo-link-list">
					{ suggestions.length === 0 && (
						<li className="wpiseo-help">
							{ __(
								'No suggestions yet. Add focus keywords to this and other posts to get better matches.',
								'wpinterface-seo'
							) }
						</li>
					) }
					{ suggestions.map( ( item ) => (
						<Suggestion key={ item.id } item={ item } />
					) ) }
				</ul>
			) }
		</PanelBody>
	);
}
