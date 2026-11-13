/**
 * Data source for the block editor (core/editor + core-data stores).
 */
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useCallback, useMemo } from '@wordpress/element';

import config from '../config';
import { SourceContext } from './context';

const EMPTY = [];

// Taxonomies whose selected terms the panels need (primary term, %category%, %tag%).
const TAXONOMIES = [
	{ slug: 'category', restBase: 'categories' },
	{ slug: 'post_tag', restBase: 'tags' },
	...( config.taxonomies || [] ),
].filter(
	( taxonomy, index, list ) =>
		list.findIndex( ( item ) => item.slug === taxonomy.slug ) === index
);

export default function BlockSource( { children } ) {
	const post = useSelect( ( select ) => {
		const editor = select( editorStore );
		const core = select( coreStore );
		const authorId = editor.getEditedPostAttribute( 'author' );
		const mediaId = editor.getEditedPostAttribute( 'featured_media' );
		const author = authorId
			? core.getEntityRecord( 'root', 'user', authorId )
			: null;
		const media = mediaId ? core.getMedia( mediaId ) : null;

		return {
			id: editor.getCurrentPostId(),
			type: editor.getCurrentPostType(),
			title: editor.getEditedPostAttribute( 'title' ) || '',
			content: editor.getEditedPostContent() || '',
			excerpt: editor.getEditedPostAttribute( 'excerpt' ) || '',
			slug:
				editor.getEditedPostSlug?.() ||
				editor.getEditedPostAttribute( 'slug' ) ||
				'',
			permalink: editor.getPermalink() || '',
			date: editor.getEditedPostAttribute( 'date' ),
			modified: editor.getEditedPostAttribute( 'modified' ),
			authorName: author?.name || '',
			featuredImage: media?.source_url || '',
		};
	}, [] );

	const { meta, isDirty } = useSelect(
		( select ) => ( {
			meta: select( editorStore ).getEditedPostAttribute( 'meta' ),
			isDirty: select( editorStore ).isEditedPostDirty(),
		} ),
		[]
	);

	// One selector for all taxonomies; values are store-memoised arrays, so the
	// shallow comparison in useSelect keeps re-renders to real changes.
	const terms = useSelect( ( select ) => {
		const editor = select( editorStore );
		const core = select( coreStore );
		const result = {};

		TAXONOMIES.forEach( ( taxonomy ) => {
			const ids = editor.getEditedPostAttribute( taxonomy.restBase );
			result[ taxonomy.slug ] =
				Array.isArray( ids ) && ids.length
					? core.getEntityRecords( 'taxonomy', taxonomy.slug, {
							include: ids,
							per_page: 100,
							context: 'view',
					  } ) || EMPTY
					: EMPTY;
		} );

		return result;
	}, [] );

	const { editPost } = useDispatch( editorStore );

	const setMeta = useCallback(
		( partial ) => editPost( { meta: partial } ),
		[ editPost ]
	);

	const value = useMemo(
		() => ( {
			mode: 'block',
			post: {
				...post,
				terms,
				tags: ( terms.post_tag || EMPTY ).map( ( tag ) => tag.name ),
			},
			meta: meta || {},
			setMeta,
			isDirty,
		} ),
		[ post, terms, meta, setMeta, isDirty ]
	);

	return (
		<SourceContext.Provider value={ value }>
			{ children }
		</SourceContext.Provider>
	);
}
