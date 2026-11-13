/**
 * Block editor integration: sidebar, document panel summary, pre-publish check.
 */
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import {
	PluginDocumentSettingPanel,
	PluginPrePublishPanel,
	PluginSidebar,
	PluginSidebarMoreMenuItem,
} from '@wordpress/editor';
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

import registerAiAlt from './ai-alt';
import config, { metaKey } from './config';
import { ScoreBadge } from './components/AnalysisList';
import SeoPanel from './components/SeoPanel';
import BlockSource from './source/BlockSource';
import { getMeta } from './source/context';
import { SeoStateProvider, useSeoState } from './state';

const SIDEBAR = 'wpiseo-sidebar';
const SIDEBAR_ID = `wpinterface-seo/${ SIDEBAR }`;

const icon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		aria-hidden="true"
	>
		<path d="M10.5 3a7.5 7.5 0 0 1 5.96 12.06l4.74 4.73-1.41 1.42-4.74-4.74A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM7.5 11h1.6v2.6H7.5V11Zm2.2-2.2h1.6v4.8H9.7V8.8Zm2.2 1.2h1.6v3.6h-1.6V10Z" />
	</svg>
);

/**
 * Slot/Fill content is rendered inside the editor's own slots, outside this
 * plugin's React tree, so every fill gets its own data providers. They all
 * read the same editor store, so they always agree.
 *
 * @param {Object}  props          Props.
 * @param {Element} props.children Children.
 */
function Providers( { children } ) {
	return (
		<BlockSource>
			<SeoStateProvider>{ children }</SeoStateProvider>
		</BlockSource>
	);
}

function isNoindex( meta ) {
	const noindex = getMeta( meta, metaKey( 'noindex' ) );
	return noindex === 'noindex' || ( noindex === '' && config.noindexDefault );
}

function Summary() {
	const { analysis, source } = useSeoState();
	const { enableComplementaryArea } = useDispatch( 'core/interface' );

	return (
		<div className="wpiseo-summary">
			{ config.modules?.analysis && analysis && (
				<>
					<ScoreBadge
						score={ analysis.seoScore }
						label={ __( 'SEO', 'wpinterface-seo' ) }
					/>
					{ analysis.readabilityScore !== null && (
						<ScoreBadge
							score={ analysis.readabilityScore }
							label={ __( 'Readability', 'wpinterface-seo' ) }
						/>
					) }
				</>
			) }
			{ isNoindex( source.meta ) && (
				<p className="wpiseo-warning">
					{ __(
						'This page is hidden from search engines (noindex).',
						'wpinterface-seo'
					) }
				</p>
			) }
			<Button
				variant="secondary"
				onClick={ () => enableComplementaryArea( 'core', SIDEBAR_ID ) }
			>
				{ __( 'Open SEO settings', 'wpinterface-seo' ) }
			</Button>
		</div>
	);
}

function PrePublishContent() {
	const { analysis, description, source } = useSeoState();
	const notes = [];

	if ( isNoindex( source.meta ) ) {
		notes.push( __( 'This page is set to noindex.', 'wpinterface-seo' ) );
	}
	if ( ! description ) {
		notes.push( __( 'There is no meta description.', 'wpinterface-seo' ) );
	}
	if ( ! getMeta( source.meta, metaKey( 'focus_keywords' ), [] ).length ) {
		notes.push( __( 'No focus keyword is set.', 'wpinterface-seo' ) );
	}

	return (
		<>
			{ config.modules?.analysis && analysis && (
				<ScoreBadge
					score={ analysis.seoScore }
					label={ __( 'SEO', 'wpinterface-seo' ) }
				/>
			) }
			{ notes.length ? (
				<ul className="wpiseo-prepublish">
					{ notes.map( ( note ) => (
						<li key={ note }>{ note }</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'Looks good.', 'wpinterface-seo' ) }</p>
			) }
		</>
	);
}

function Plugin() {
	return (
		<>
			<PluginSidebarMoreMenuItem target={ SIDEBAR } icon={ icon }>
				{ __( 'SEO', 'wpinterface-seo' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={ SIDEBAR }
				title={ __( 'SEO', 'wpinterface-seo' ) }
				icon={ icon }
			>
				<Providers>
					<SeoPanel />
				</Providers>
			</PluginSidebar>
			<PluginDocumentSettingPanel
				name="wpiseo-summary"
				title={ __( 'SEO', 'wpinterface-seo' ) }
				icon={ icon }
			>
				<Providers>
					<Summary />
				</Providers>
			</PluginDocumentSettingPanel>
			<PluginPrePublishPanel
				title={ __( 'SEO check', 'wpinterface-seo' ) }
				icon={ icon }
			>
				<Providers>
					<PrePublishContent />
				</Providers>
			</PluginPrePublishPanel>
		</>
	);
}

export default function register() {
	registerPlugin( 'wpinterface-seo', { render: Plugin, icon } );
	registerAiAlt();

	// Optional metabox below the content (Settings → General → Editor). It
	// edits the same editor store as the sidebar; its hidden classic input
	// stays empty, so the metabox form submit changes nothing.
	domReady( () => {
		const root = document.getElementById( 'wpiseo-metabox-root' );

		if ( root ) {
			createRoot( root ).render(
				<Providers>
					<SeoPanel />
				</Providers>
			);
		}
	} );
}
