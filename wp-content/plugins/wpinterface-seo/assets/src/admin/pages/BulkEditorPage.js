/**
 * Bulk editor: SEO titles, descriptions and indexing for many posts at once.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { Pagination, REST_BASE, useRemoteList } from '../components/list';
import { ProHint } from '../components/pro';
import { config } from '../components/section';

const PER_PAGE = 20;

const NOINDEX_OPTIONS = [
	{ value: '', label: __( 'Default', 'wpinterface-seo' ) },
	{ value: 'index', label: __( 'Index', 'wpinterface-seo' ) },
	{ value: 'noindex', label: __( 'Noindex', 'wpinterface-seo' ) },
];

// "No links in" needs the link index of the Internal Links module.
const SHOW_OPTIONS = [
	{ value: '', label: __( 'All posts', 'wpinterface-seo' ) },
	{
		value: 'no_description',
		label: __( 'Without a meta description', 'wpinterface-seo' ),
	},
	{
		value: 'no_keyword',
		label: __( 'Without a focus keyword', 'wpinterface-seo' ),
	},
	{
		value: 'orphan',
		label: __(
			'With no internal links pointing to them',
			'wpinterface-seo'
		),
	},
	{
		value: 'noindex',
		label: __( 'Hidden from search (noindex)', 'wpinterface-seo' ),
	},
	{ value: 'poor', label: __( 'Poor SEO score', 'wpinterface-seo' ) },
	{ value: 'unscored', label: __( 'Not analyzed yet', 'wpinterface-seo' ) },
].filter( ( option ) => ! option.hidden );

// Filters can be preset from the URL, e.g. links on the dashboard.
const query = new URLSearchParams( window.location.search );

function Counter( { value, max } ) {
	// Templates with variables are only measurable once rendered (after saving).
	if ( value.includes( '%' ) ) {
		return null;
	}

	const length = value.length;

	return (
		<span
			className={ `wpiseo-counter${
				length > max ? ' wpiseo-counter--over' : ''
			}` }
		>
			{ length }/{ max }
		</span>
	);
}

export default function BulkEditorPage() {
	const postTypes = ( config.postTypes || [] ).filter(
		( type ) => type.name !== 'attachment'
	);
	const [ postType, setPostType ] = useState( () => {
		const requested = query.get( 'post_type' ) || '';
		return postTypes.some( ( type ) => type.name === requested )
			? requested
			: '';
	} );
	const [ show, setShow ] = useState( () =>
		SHOW_OPTIONS.some( ( option ) => option.value === query.get( 'show' ) )
			? query.get( 'show' )
			: ''
	);
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ changes, setChanges ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ refresh, setRefresh ] = useState( 0 );
	const list = useRemoteList(
		'/bulk',
		{ post_type: postType, show, page, per_page: PER_PAGE, search },
		refresh
	);
	const dirtyCount = Object.keys( changes ).length;

	// Warn before leaving with unsaved edits.
	useEffect( () => {
		if ( ! dirtyCount ) {
			return;
		}

		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirtyCount ] );

	const confirmDiscard = () =>
		! dirtyCount ||
		// eslint-disable-next-line no-alert -- Simple confirmation before losing edits.
		window.confirm(
			__(
				'You have unsaved changes on this page. Discard them?',
				'wpinterface-seo'
			)
		);

	const navigate = ( apply ) => {
		if ( confirmDiscard() ) {
			setChanges( {} );
			apply();
		}
	};

	const value = ( item, key ) => changes[ item.id ]?.[ key ] ?? item[ key ];

	const edit = ( item, key, newValue ) =>
		setChanges( ( current ) => ( {
			...current,
			[ item.id ]: { ...current[ item.id ], [ key ]: newValue },
		} ) );

	const saveAll = async () => {
		setSaving( true );
		setNotice( null );

		try {
			const response = await apiFetch( {
				path: `${ REST_BASE }/bulk`,
				method: 'POST',
				data: {
					items: Object.entries( changes ).map(
						( [ id, fields ] ) => ( {
							id: Number( id ),
							...fields,
						} )
					),
				},
			} );
			setChanges( {} );
			setRefresh( ( count ) => count + 1 );
			setNotice( {
				status: response.skipped ? 'warning' : 'success',
				text: response.skipped
					? sprintf(
							/* translators: 1: saved count, 2: skipped count. */
							__(
								'Saved: %1$d. Skipped (no permission): %2$d.',
								'wpinterface-seo'
							),
							response.saved,
							response.skipped
					  )
					: sprintf(
							/* translators: %d: number of posts. */
							_n(
								'%d post saved.',
								'%d posts saved.',
								response.saved,
								'wpinterface-seo'
							),
							response.saved
					  ),
			} );
		} catch ( caught ) {
			setNotice( { status: 'error', text: caught.message } );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="wpiseo-page wpiseo-page--wide wpiseo-stack">
			<p className="wpiseo-muted">
				{ sprintf(
					/* translators: 1: %sep% variable, 2: %sitename% variable. */
					__(
						'Edit SEO titles and meta descriptions without opening each post. Leave a field empty to use the template from Search Appearance (shown in grey). Variables such as %1$s and %2$s work here too.',
						'wpinterface-seo'
					),
					'%sep%',
					'%sitename%'
				) }
			</p>

			<div className="wpiseo-toolbar">
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Content type', 'wpinterface-seo' ) }
					hideLabelFromVision
					value={ postType }
					options={ [
						{
							value: '',
							label: __( 'All content types', 'wpinterface-seo' ),
						},
						...postTypes.map( ( type ) => ( {
							value: type.name,
							label: type.label,
						} ) ),
					] }
					onChange={ ( next ) =>
						navigate( () => {
							setPostType( next );
							setPage( 1 );
						} )
					}
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Show', 'wpinterface-seo' ) }
					hideLabelFromVision
					value={ show }
					options={ SHOW_OPTIONS }
					onChange={ ( next ) =>
						navigate( () => {
							setShow( next );
							setPage( 1 );
						} )
					}
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="search"
					label={ __( 'Search', 'wpinterface-seo' ) }
					hideLabelFromVision
					placeholder={ __( 'Search titles…', 'wpinterface-seo' ) }
					value={ search }
					onChange={ ( next ) =>
						navigate( () => {
							setSearch( next );
							setPage( 1 );
						} )
					}
				/>
				<Button
					variant="primary"
					onClick={ saveAll }
					isBusy={ saving }
					disabled={ saving || ! dirtyCount }
				>
					{ dirtyCount
						? sprintf(
								/* translators: %d: number of edited posts. */
								__( 'Save %d changed', 'wpinterface-seo' ),
								dirtyCount
						  )
						: __( 'Save changes', 'wpinterface-seo' ) }
				</Button>
			</div>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }
				</Notice>
			) }

			<div className="wpiseo-table-wrap">
				<table className="wp-list-table widefat striped wpiseo-table wpiseo-bulk">
					<thead>
						<tr>
							<th className="wpiseo-bulk__post">
								{ __( 'Post', 'wpinterface-seo' ) }
							</th>
							<th>{ __( 'SEO title', 'wpinterface-seo' ) }</th>
							<th>
								{ __( 'Meta description', 'wpinterface-seo' ) }
							</th>
							<th className="wpiseo-bulk__robots">
								{ __( 'Indexing', 'wpinterface-seo' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ list.loading && ! list.items.length && (
							<tr>
								<td colSpan="4">
									<Spinner />
								</td>
							</tr>
						) }
						{ ! list.loading && ! list.items.length && (
							<tr>
								<td colSpan="4" className="wpiseo-muted">
									{ show
										? __(
												'No posts match this filter.',
												'wpinterface-seo'
										  )
										: __(
												'Nothing found.',
												'wpinterface-seo'
										  ) }
								</td>
							</tr>
						) }
						{ list.items.map( ( item ) => (
							<tr
								key={ item.id }
								className={
									changes[ item.id ] ? 'is-changed' : ''
								}
							>
								<td>
									<strong>
										<a href={ item.edit }>
											{ item.title ||
												__(
													'(no title)',
													'wpinterface-seo'
												) }
										</a>
									</strong>
									{ ! postType && (
										<span className="wpiseo-tag">
											{ postTypes.find(
												( type ) =>
													type.name === item.type
											)?.singular || item.type }
										</span>
									) }
									{ 'publish' !== item.status && (
										<span className="wpiseo-tag">
											{ item.status }
										</span>
									) }
									<div className="row-actions visible">
										<a
											href={ item.link }
											target="_blank"
											rel="noreferrer"
										>
											{ __( 'View', 'wpinterface-seo' ) }
										</a>
									</div>
								</td>
								<td>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'SEO title',
											'wpinterface-seo'
										) }
										hideLabelFromVision
										placeholder={ item.rendered_title }
										value={ value( item, 'seo_title' ) }
										onChange={ ( next ) =>
											edit( item, 'seo_title', next )
										}
									/>
									<Counter
										value={
											value( item, 'seo_title' ) ||
											item.rendered_title
										}
										max={ 60 }
									/>
								</td>
								<td>
									<TextareaControl
										__nextHasNoMarginBottom
										label={ __(
											'Meta description',
											'wpinterface-seo'
										) }
										hideLabelFromVision
										rows={ 2 }
										placeholder={
											item.rendered_description
										}
										value={ value( item, 'description' ) }
										onChange={ ( next ) =>
											edit( item, 'description', next )
										}
									/>
									<Counter
										value={
											value( item, 'description' ) ||
											item.rendered_description
										}
										max={ 160 }
									/>
								</td>
								<td>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Indexing',
											'wpinterface-seo'
										) }
										hideLabelFromVision
										value={ value( item, 'noindex' ) }
										options={ NOINDEX_OPTIONS }
										onChange={ ( next ) =>
											edit( item, 'noindex', next )
										}
									/>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<Pagination
				page={ page }
				perPage={ PER_PAGE }
				total={ list.total }
				onChange={ ( next ) => navigate( () => setPage( next ) ) }
			/>

			<ProHint id="bulk-editor">
				{ __(
					'Pro’s Bulk AI writes missing titles, descriptions and alt text for hundreds of posts in one batch, for you to review before anything changes.',
					'wpinterface-seo'
				) }
			</ProHint>
		</div>
	);
}
