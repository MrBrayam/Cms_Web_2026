/**
 * Data source for the classic editor: reads the edit form's DOM and keeps
 * SEO values in a hidden input that the metabox submits with the post.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';

import config, { PREFIX } from '../config';
import { SourceContext } from './context';

const $ = ( id ) => document.getElementById( id );

function editorContent() {
	const tinymce = window.tinymce;
	const editor = tinymce && tinymce.get && tinymce.get( 'content' );

	if ( editor && ! editor.isHidden() ) {
		return editor.getContent();
	}

	return $( 'content' )?.value || '';
}

function checkedTerms( taxonomy ) {
	const list = $( `${ taxonomy }checklist` );

	if ( ! list ) {
		return [];
	}

	return Array.from( list.querySelectorAll( 'input:checked' ) ).map(
		( input ) => ( {
			id: Number( input.value ),
			name: ( input.parentElement?.textContent || '' ).trim(),
		} )
	);
}

function readPost() {
	const terms = { category: checkedTerms( 'category' ) };
	( config.taxonomies || [] ).forEach( ( taxonomy ) => {
		terms[ taxonomy.slug ] = checkedTerms( taxonomy.slug );
	} );

	const tagsField = $( 'tax-input-post_tag' );
	const thumbnail = document.querySelector( '#postimagediv img' );

	return {
		id: config.postId,
		type: config.postType,
		title: $( 'title' )?.value || '',
		content: editorContent(),
		excerpt: $( 'excerpt' )?.value || '',
		slug:
			$( 'post_name' )?.value ||
			$( 'editable-post-name-full' )?.textContent ||
			'',
		permalink:
			document.querySelector( '#sample-permalink a' )?.href ||
			config.permalink ||
			'',
		date: config.date,
		modified: config.modified,
		authorName: config.authorName || '',
		featuredImage: thumbnail?.getAttribute( 'src' ) || '',
		terms,
		tags: ( tagsField?.value || '' )
			.split( ',' )
			.map( ( tag ) => tag.trim() )
			.filter( Boolean ),
	};
}

function writeHiddenInput( meta ) {
	const input = $( 'wpiseo-meta-input' );

	if ( ! input ) {
		return;
	}

	const unprefixed = {};
	Object.keys( meta ).forEach( ( key ) => {
		if ( key.startsWith( PREFIX ) ) {
			unprefixed[ key.slice( PREFIX.length ) ] = meta[ key ];
		}
	} );

	input.value = JSON.stringify( unprefixed );
}

export default function ClassicSource( { children } ) {
	const [ post, setPost ] = useState( readPost );
	const [ meta, setMetaState ] = useState( () => config.meta || {} );

	useEffect( () => {
		writeHiddenInput( meta );
	}, [ meta ] );

	useEffect( () => {
		let timer;
		const refresh = () => {
			window.clearTimeout( timer );
			timer = window.setTimeout( () => setPost( readPost() ), 400 );
		};

		const form = $( 'post' );
		form?.addEventListener( 'input', refresh );
		form?.addEventListener( 'change', refresh );

		const bindEditor = ( editor ) => {
			if ( editor?.id === 'content' ) {
				editor.on( 'input change keyup SetContent', refresh );
			}
		};

		const tinymce = window.tinymce;
		if ( tinymce ) {
			tinymce.editors?.forEach?.( bindEditor );
			tinymce.on?.( 'AddEditor', ( event ) =>
				bindEditor( event.editor )
			);
		}

		// Thumbnail and slug edits happen through AJAX without input events.
		const observer = new window.MutationObserver( refresh );
		[ 'postimagediv', 'edit-slug-box' ].forEach( ( id ) => {
			const node = $( id );
			if ( node ) {
				observer.observe( node, { childList: true, subtree: true } );
			}
		} );

		return () => {
			window.clearTimeout( timer );
			form?.removeEventListener( 'input', refresh );
			form?.removeEventListener( 'change', refresh );
			observer.disconnect();
		};
	}, [] );

	const setMeta = useCallback(
		( partial ) =>
			setMetaState( ( current ) => ( { ...current, ...partial } ) ),
		[]
	);

	const value = useMemo(
		() => ( { mode: 'classic', post, meta, setMeta, isDirty: true } ),
		[ post, meta, setMeta ]
	);

	return (
		<SourceContext.Provider value={ value }>
			{ children }
		</SourceContext.Provider>
	);
}
