/**
 * SEO tab: snippet editor, preview, focus keywords and checks.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ButtonGroup,
	FormTokenField,
	PanelBody,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { measureTitle } from '../../analysis';
import config, { metaKey } from '../config';
import { getMeta } from '../source/context';
import { useSeoState } from '../state';
import AiSuggest from './AiSuggest';
import AnalysisList, { ScoreBadge } from './AnalysisList';
import LinksPanel from './LinksPanel';
import PerformancePanel from './PerformancePanel';
import SerpPreview from './SerpPreview';
import VariableField from './VariableField';

function LengthMeter( { value, max, good } ) {
	const percent = Math.min( 100, Math.round( ( value / max ) * 100 ) );
	let status = 'good';
	if ( value === 0 ) {
		status = 'bad';
	} else if ( value < good[ 0 ] || value > good[ 1 ] ) {
		status = 'ok';
	}

	return (
		<div className={ `wpiseo-meter is-${ status }` } aria-hidden="true">
			<span style={ { width: `${ percent }%` } } />
		</div>
	);
}

/**
 * Other posts that already target a keyword (cannibalisation check).
 *
 * @param {string} keyword Main focus keyword.
 * @param {number} postId  Current post (excluded).
 * @return {Array} Posts: { id, title, edit }.
 */
function useKeywordUsage( keyword, postId ) {
	const [ posts, setPosts ] = useState( [] );

	useEffect( () => {
		if ( ! keyword ) {
			setPosts( [] );
			return;
		}

		let cancelled = false;
		const timer = setTimeout( () => {
			apiFetch( {
				path: addQueryArgs(
					`/${
						config.restNamespace || 'wpinterface-seo/v1'
					}/keywords/used`,
					{ keyword, post_id: postId }
				),
			} )
				.then( ( result ) => ! cancelled && setPosts( result ) )
				.catch( () => ! cancelled && setPosts( [] ) );
		}, 600 );

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ keyword, postId ] );

	return posts;
}

function KeywordUsage( { keyword, posts } ) {
	if ( ! posts.length ) {
		return null;
	}

	return (
		<div className="wpiseo-keyword-used">
			<p>
				{ sprintf(
					/* translators: %s: focus keyword. */
					__(
						'You already target “%s” on other content. Pages competing for the same keyword can hold each other back:',
						'wpinterface-seo'
					),
					keyword
				) }
			</p>
			<ul>
				{ posts.map( ( item ) => (
					<li key={ item.id }>
						{ item.edit ? (
							<a
								href={ item.edit }
								target="_blank"
								rel="noreferrer"
							>
								{ item.title ||
									__( '(no title)', 'wpinterface-seo' ) }
							</a>
						) : (
							item.title
						) }
					</li>
				) ) }
			</ul>
		</div>
	);
}

export default function SeoTab() {
	const { source, title, description, analysis } = useSeoState();
	const { meta, setMeta, post } = source;
	const [ mobile, setMobile ] = useState( false );

	const keywords = getMeta( meta, metaKey( 'focus_keywords' ), [] );
	const mainKeyword = Array.isArray( keywords ) ? keywords[ 0 ] || '' : '';
	const usedOn = useKeywordUsage( mainKeyword, config.postId );
	const titleWidth = Math.round( measureTitle( title ) );
	const descriptionPreview =
		description || ( post.excerpt || '' ).slice( 0, 160 );

	return (
		<>
			<PanelBody
				title={ __( 'Search result', 'wpinterface-seo' ) }
				initialOpen
			>
				<ButtonGroup
					className="wpiseo-device-toggle"
					aria-label={ __( 'Preview device', 'wpinterface-seo' ) }
				>
					<Button
						size="small"
						variant="secondary"
						isPressed={ ! mobile }
						onClick={ () => setMobile( false ) }
					>
						{ __( 'Desktop', 'wpinterface-seo' ) }
					</Button>
					<Button
						size="small"
						variant="secondary"
						isPressed={ mobile }
						onClick={ () => setMobile( true ) }
					>
						{ __( 'Mobile', 'wpinterface-seo' ) }
					</Button>
				</ButtonGroup>

				<SerpPreview
					title={ title }
					description={ descriptionPreview }
					url={ post.permalink }
					mobile={ mobile }
				/>

				<VariableField
					label={ __( 'SEO title', 'wpinterface-seo' ) }
					value={ getMeta( meta, metaKey( 'title' ) ) }
					placeholder={ config.templates?.title }
					onChange={ ( value ) =>
						setMeta( { [ metaKey( 'title' ) ]: value } )
					}
					help={ sprintf(
						/* translators: %d: width in pixels. */
						__(
							'%dpx of about 600px. Leave empty to use the default template.',
							'wpinterface-seo'
						),
						titleWidth
					) }
				/>
				<LengthMeter
					value={ titleWidth }
					max={ 650 }
					good={ [ 200, 600 ] }
				/>
				<AiSuggest
					field="title"
					onApply={ ( value ) =>
						setMeta( { [ metaKey( 'title' ) ]: value } )
					}
				/>

				<VariableField
					multiline
					label={ __( 'Meta description', 'wpinterface-seo' ) }
					value={ getMeta( meta, metaKey( 'description' ) ) }
					placeholder={ config.templates?.description }
					onChange={ ( value ) =>
						setMeta( { [ metaKey( 'description' ) ]: value } )
					}
					help={ sprintf(
						/* translators: %d: number of characters. */
						__(
							'%d characters. Aim for 120–160.',
							'wpinterface-seo'
						),
						description.length
					) }
				/>
				<LengthMeter
					value={ description.length }
					max={ 180 }
					good={ [ 120, 160 ] }
				/>
				<AiSuggest
					field="description"
					onApply={ ( value ) =>
						setMeta( { [ metaKey( 'description' ) ]: value } )
					}
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Focus keywords', 'wpinterface-seo' ) }
				initialOpen
			>
				<FormTokenField
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __(
						'Keywords this content should rank for',
						'wpinterface-seo'
					) }
					value={ Array.isArray( keywords ) ? keywords : [] }
					maxLength={ config.maxKeywords || 5 }
					onChange={ ( tokens ) =>
						setMeta( {
							[ metaKey( 'focus_keywords' ) ]: tokens
								.map( ( token ) =>
									typeof token === 'string'
										? token
										: token.value
								)
								.map( ( token ) => token.trim() )
								.filter( Boolean ),
						} )
					}
					__experimentalShowHowTo={ false }
				/>
				<p className="wpiseo-help">
					{ sprintf(
						/* translators: %d: maximum number of keywords. */
						__(
							'Press Enter after each keyword. Up to %d; the first one is the main keyword.',
							'wpinterface-seo'
						),
						config.maxKeywords || 5
					) }
				</p>
				<KeywordUsage keyword={ mainKeyword } posts={ usedOn } />
			</PanelBody>

			{ config.modules?.analysis && analysis && (
				<PanelBody
					title={ __( 'SEO analysis', 'wpinterface-seo' ) }
					initialOpen
				>
					<ScoreBadge
						score={ analysis.seoScore }
						label={ __( 'SEO score', 'wpinterface-seo' ) }
					/>
					<AnalysisList checks={ analysis.seo } />
					{ analysis.keywords.map( ( item ) => (
						<details
							key={ item.keyword }
							className="wpiseo-extra-keyword"
						>
							<summary>
								<ScoreBadge
									score={ item.score }
									label={ item.keyword }
								/>
							</summary>
							<AnalysisList checks={ item.checks } />
						</details>
					) ) }
				</PanelBody>
			) }
			{ config.modules?.links && <LinksPanel /> }
			{ config.gsc?.connected && <PerformancePanel /> }
		</>
	);
}
