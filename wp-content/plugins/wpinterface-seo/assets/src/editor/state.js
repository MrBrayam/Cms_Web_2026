/**
 * Derived SEO state (rendered title/description, analysis) computed once
 * and shared by every panel through context.
 */
import {
	createContext,
	useContext,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';

import { analyze } from '../analysis';
import config, { metaKey } from './config';
import { getMeta, useSource } from './source/context';
import { useRenderedTemplate } from './template';

const StateContext = createContext( null );

export function useSeoState() {
	return useContext( StateContext );
}

export function SeoStateProvider( { children } ) {
	const source = useSource();
	const { post, meta, setMeta, isDirty } = source;

	const titleTemplate =
		getMeta( meta, metaKey( 'title' ) ) || config.templates?.title || '';
	const descriptionTemplate =
		getMeta( meta, metaKey( 'description' ) ) ||
		config.templates?.description ||
		'';

	const title = useRenderedTemplate( titleTemplate, post, meta );
	const description = useRenderedTemplate( descriptionTemplate, post, meta );
	const keywords = getMeta( meta, metaKey( 'focus_keywords' ), [] );
	const keywordKey = ( Array.isArray( keywords ) ? keywords : [] ).join(
		'|'
	);

	const [ analysis, setAnalysis ] = useState( null );

	useEffect( () => {
		if ( ! config.modules?.analysis ) {
			return undefined;
		}

		const timer = window.setTimeout( () => {
			setAnalysis(
				analyze( {
					title,
					description,
					slug: post.slug,
					content: post.content,
					keywords: keywordKey ? keywordKey.split( '|' ) : [],
					locale: config.site?.locale,
					host: config.site?.host,
					readability: config.readability !== false,
				} )
			);
		}, 600 );

		return () => window.clearTimeout( timer );
	}, [ title, description, post.slug, post.content, keywordKey ] );

	// Store scores for the post list columns. Only while the post has unsaved
	// changes, so opening a post never marks it as modified.
	useEffect( () => {
		if ( ! analysis || ! isDirty ) {
			return;
		}

		const seoScore = analysis.seoScore;
		const readabilityScore = analysis.readabilityScore ?? 0;

		if (
			getMeta( meta, metaKey( 'score' ), 0 ) !== seoScore ||
			getMeta( meta, metaKey( 'readability_score' ), 0 ) !==
				readabilityScore
		) {
			setMeta( {
				[ metaKey( 'score' ) ]: seoScore,
				[ metaKey( 'readability_score' ) ]: readabilityScore,
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ analysis, isDirty ] );

	const value = useMemo(
		() => ( {
			source,
			title,
			description,
			titleTemplate,
			descriptionTemplate,
			analysis,
		} ),
		[
			source,
			title,
			description,
			titleTemplate,
			descriptionTemplate,
			analysis,
		]
	);

	return (
		<StateContext.Provider value={ value }>
			{ children }
		</StateContext.Provider>
	);
}
