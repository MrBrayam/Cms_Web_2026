/**
 * Redirect rules: list, add/edit, bulk actions, CSV and plugin import, settings.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	FormFileUpload,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
	download,
	formatDate,
	Pagination,
	REST_BASE,
	useRemoteList,
	useSelection,
} from '../components/list';
import { formatCompact, formatNumber } from '../components/format';
import { config, SaveBar, Section, useDraft } from '../components/section';
import { HeaderActions, StatTile, StatTiles } from '../components/ui';

const PER_PAGE = 50;

export const STATUS_OPTIONS = [
	{ value: 301, label: __( '301 Moved permanently', 'wpinterface-seo' ) },
	{ value: 302, label: __( '302 Found (temporary)', 'wpinterface-seo' ) },
	{ value: 307, label: __( '307 Temporary redirect', 'wpinterface-seo' ) },
	{ value: 308, label: __( '308 Permanent redirect', 'wpinterface-seo' ) },
	{ value: 410, label: __( '410 Content deleted', 'wpinterface-seo' ) },
	{
		value: 451,
		label: __( '451 Unavailable for legal reasons', 'wpinterface-seo' ),
	},
];

const ORIGIN_LABELS = {
	manual: __( 'Manual', 'wpinterface-seo' ),
	slug: __( 'URL change', 'wpinterface-seo' ),
	term: __( 'Term URL change', 'wpinterface-seo' ),
	trash: __( 'Trashed post', 'wpinterface-seo' ),
	import: __( 'Imported', 'wpinterface-seo' ),
	404: __( '404 log', 'wpinterface-seo' ),
};

const EMPTY_RULE = {
	source: '',
	target: '',
	status: 301,
	match_type: 'exact',
	ignore_query: true,
	enabled: true,
};

export const hasTarget = ( status ) => status < 400;

function RuleForm( { rule, onSaved, onCancel } ) {
	const [ values, setValues ] = useState( rule || EMPTY_RULE );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const set = ( key, value ) =>
		setValues( ( current ) => ( { ...current, [ key ]: value } ) );
	const isEdit = !! rule?.id;
	const formRef = useRef();

	// The form opens from a button elsewhere; move focus to its first field.
	useEffect( () => {
		formRef.current?.querySelector( 'input' )?.focus();
	}, [] );

	const submit = async ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( null );

		try {
			const saved = await apiFetch( {
				path: `${ REST_BASE }/redirects${
					isEdit ? `/${ rule.id }` : ''
				}`,
				method: isEdit ? 'PUT' : 'POST',
				data: {
					source: values.source,
					target: hasTarget( values.status ) ? values.target : '',
					status: Number( values.status ),
					match_type: values.match_type,
					ignore_query: values.ignore_query,
					enabled: values.enabled,
				},
			} );
			onSaved( saved, isEdit );
		} catch ( caught ) {
			setError( caught.message );
		} finally {
			setBusy( false );
		}
	};

	const isRegex = 'regex' === values.match_type;

	return (
		<form className="wpiseo-rule-form" onSubmit={ submit } ref={ formRef }>
			<div className="wpiseo-grid-2">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Old URL (source)', 'wpinterface-seo' ) }
					help={
						isRegex
							? __(
									'A regular expression matched against the path, e.g. ^/blog/(\\d+)/(.*)$',
									'wpinterface-seo'
							  )
							: __(
									'A path such as /old-page/ or a full URL on this site.',
									'wpinterface-seo'
							  )
					}
					value={ values.source }
					onChange={ ( value ) => set( 'source', value ) }
					required
				/>
				{ hasTarget( Number( values.status ) ) && (
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'New URL (target)', 'wpinterface-seo' ) }
						help={
							isRegex
								? __(
										'Use $1, $2 … for captured groups.',
										'wpinterface-seo'
								  )
								: __(
										'A path on this site or any full URL.',
										'wpinterface-seo'
								  )
						}
						value={ values.target }
						onChange={ ( value ) => set( 'target', value ) }
						required
					/>
				) }
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Type', 'wpinterface-seo' ) }
					value={ String( values.status ) }
					options={ STATUS_OPTIONS.map( ( option ) => ( {
						value: String( option.value ),
						label: option.label,
					} ) ) }
					onChange={ ( value ) => set( 'status', Number( value ) ) }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Match', 'wpinterface-seo' ) }
					value={ values.match_type }
					options={ [
						{
							value: 'exact',
							label: __( 'Exact URL', 'wpinterface-seo' ),
						},
						{
							value: 'regex',
							label: __(
								'Regular expression',
								'wpinterface-seo'
							),
						},
					] }
					onChange={ ( value ) => set( 'match_type', value ) }
				/>
			</div>
			{ ! isRegex && (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Ignore query strings (?utm_source=… still redirects)',
						'wpinterface-seo'
					) }
					checked={ !! values.ignore_query }
					onChange={ ( value ) => set( 'ignore_query', value ) }
				/>
			) }
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Active', 'wpinterface-seo' ) }
				checked={ !! values.enabled }
				onChange={ ( value ) => set( 'enabled', value ) }
			/>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="wpiseo-actions-row">
				<Button
					variant="primary"
					type="submit"
					isBusy={ busy }
					disabled={ busy }
				>
					{ isEdit
						? __( 'Update redirect', 'wpinterface-seo' )
						: __( 'Add redirect', 'wpinterface-seo' ) }
				</Button>
				{ onCancel && (
					<Button variant="tertiary" onClick={ onCancel }>
						{ __( 'Cancel', 'wpinterface-seo' ) }
					</Button>
				) }
			</div>
		</form>
	);
}

function RulesTable( { reloadKey } ) {
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ origin, setOrigin ] = useState( '' );
	const [ editing, setEditing ] = useState( null );
	const [ message, setMessage ] = useState( null );
	const selection = useSelection();
	const list = useRemoteList(
		'/redirects',
		{
			page,
			per_page: PER_PAGE,
			search,
			status,
			origin,
		},
		reloadKey
	);

	const bulk = async ( action ) => {
		if (
			'delete' === action &&
			// eslint-disable-next-line no-alert -- Simple confirmation before a destructive action.
			! window.confirm(
				__( 'Delete the selected redirects?', 'wpinterface-seo' )
			)
		) {
			return;
		}

		try {
			await apiFetch( {
				path: `${ REST_BASE }/redirects/bulk`,
				method: 'POST',
				data: { action, ids: selection.selected },
			} );
			selection.clear();
			list.reload();
		} catch ( caught ) {
			setMessage( { status: 'error', text: caught.message } );
		}
	};

	const toggleEnabled = async ( rule ) => {
		try {
			const saved = await apiFetch( {
				path: `${ REST_BASE }/redirects/${ rule.id }`,
				method: 'PUT',
				data: { enabled: ! rule.enabled },
			} );
			list.setItems( ( items ) =>
				items.map( ( item ) => ( item.id === saved.id ? saved : item ) )
			);
		} catch ( caught ) {
			setMessage( { status: 'error', text: caught.message } );
		}
	};

	const remove = async ( rule ) => {
		if (
			// eslint-disable-next-line no-alert -- Simple confirmation before a destructive action.
			! window.confirm( __( 'Delete this redirect?', 'wpinterface-seo' ) )
		) {
			return;
		}

		await apiFetch( {
			path: `${ REST_BASE }/redirects/${ rule.id }`,
			method: 'DELETE',
		} );
		list.reload();
	};

	const ids = list.items.map( ( item ) => item.id );

	return (
		<>
			<div className="wpiseo-toolbar">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="search"
					placeholder={ __( 'Search URLs…', 'wpinterface-seo' ) }
					label={ __( 'Search', 'wpinterface-seo' ) }
					hideLabelFromVision
					value={ search }
					onChange={ ( value ) => {
						setSearch( value );
						setPage( 1 );
					} }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Type', 'wpinterface-seo' ) }
					hideLabelFromVision
					value={ status }
					options={ [
						{
							value: '',
							label: __( 'All types', 'wpinterface-seo' ),
						},
						...STATUS_OPTIONS.map( ( option ) => ( {
							value: String( option.value ),
							label: String( option.value ),
						} ) ),
					] }
					onChange={ ( value ) => {
						setStatus( value );
						setPage( 1 );
					} }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Created by', 'wpinterface-seo' ) }
					hideLabelFromVision
					value={ origin }
					options={ [
						{
							value: '',
							label: __( 'All sources', 'wpinterface-seo' ),
						},
						...Object.entries( ORIGIN_LABELS ).map(
							( [ value, label ] ) => ( { value, label } )
						),
					] }
					onChange={ ( value ) => {
						setOrigin( value );
						setPage( 1 );
					} }
				/>
			</div>

			{ selection.selected.length > 0 && (
				<div className="wpiseo-actions-row wpiseo-bulkbar">
					<span>
						{ sprintf(
							/* translators: %d: number of selected rows. */
							__( '%d selected', 'wpinterface-seo' ),
							selection.selected.length
						) }
					</span>
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => bulk( 'enable' ) }
					>
						{ __( 'Activate', 'wpinterface-seo' ) }
					</Button>
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => bulk( 'disable' ) }
					>
						{ __( 'Deactivate', 'wpinterface-seo' ) }
					</Button>
					<Button
						variant="secondary"
						size="compact"
						isDestructive
						onClick={ () => bulk( 'delete' ) }
					>
						{ __( 'Delete', 'wpinterface-seo' ) }
					</Button>
				</div>
			) }

			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }
				</Notice>
			) }

			<div className="wpiseo-table-wrap">
				<table className="wp-list-table widefat striped wpiseo-table">
					<thead>
						<tr>
							<td className="check-column">
								<CheckboxControl
									__nextHasNoMarginBottom
									aria-label={ __(
										'Select all',
										'wpinterface-seo'
									) }
									checked={
										ids.length > 0 &&
										ids.every( selection.has )
									}
									onChange={ () =>
										selection.toggleAll( ids )
									}
								/>
							</td>
							<th>{ __( 'Old URL', 'wpinterface-seo' ) }</th>
							<th>{ __( 'New URL', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Type', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Hits', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Last hit', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Active', 'wpinterface-seo' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ list.loading && ! list.items.length && (
							<tr>
								<td colSpan="7">
									<Spinner />
								</td>
							</tr>
						) }
						{ ! list.loading && ! list.items.length && (
							<tr>
								<td colSpan="7" className="wpiseo-muted">
									{ __(
										'No redirects yet.',
										'wpinterface-seo'
									) }
								</td>
							</tr>
						) }
						{ list.items.map( ( rule ) =>
							editing === rule.id ? (
								<tr key={ rule.id }>
									<td colSpan="7">
										<RuleForm
											rule={ rule }
											onCancel={ () =>
												setEditing( null )
											}
											onSaved={ () => {
												setEditing( null );
												list.reload();
											} }
										/>
									</td>
								</tr>
							) : (
								<tr
									key={ rule.id }
									className={
										rule.enabled ? '' : 'is-disabled'
									}
								>
									<th className="check-column" scope="row">
										<CheckboxControl
											__nextHasNoMarginBottom
											aria-label={ sprintf(
												/* translators: %s: URL. */
												__(
													'Select %s',
													'wpinterface-seo'
												),
												rule.source
											) }
											checked={ selection.has( rule.id ) }
											onChange={ () =>
												selection.toggle( rule.id )
											}
										/>
									</th>
									<td>
										<code className="wpiseo-url">
											{ rule.source }
										</code>
										{ 'regex' === rule.match_type && (
											<span className="wpiseo-tag">
												{ __(
													'regex',
													'wpinterface-seo'
												) }
											</span>
										) }
										<div className="row-actions visible">
											<Button
												variant="link"
												onClick={ () =>
													setEditing( rule.id )
												}
											>
												{ __(
													'Edit',
													'wpinterface-seo'
												) }
											</Button>
											{ ' | ' }
											<Button
												variant="link"
												isDestructive
												onClick={ () => remove( rule ) }
											>
												{ __(
													'Delete',
													'wpinterface-seo'
												) }
											</Button>
											<span className="wpiseo-muted">
												{ ' · ' }
												{ ORIGIN_LABELS[
													rule.origin
												] || rule.origin }
											</span>
										</div>
									</td>
									<td>
										{ hasTarget( rule.status ) ? (
											<code className="wpiseo-url">
												{ rule.target }
											</code>
										) : (
											<span className="wpiseo-muted">
												—
											</span>
										) }
									</td>
									<td>{ rule.status }</td>
									<td>{ rule.hits }</td>
									<td>{ formatDate( rule.last_hit ) }</td>
									<td>
										<ToggleControl
											__nextHasNoMarginBottom
											label={
												<span className="screen-reader-text">
													{ __(
														'Active',
														'wpinterface-seo'
													) }
												</span>
											}
											checked={ !! rule.enabled }
											onChange={ () =>
												toggleEnabled( rule )
											}
										/>
									</td>
								</tr>
							)
						) }
					</tbody>
				</table>
			</div>

			<Pagination
				page={ page }
				perPage={ PER_PAGE }
				total={ list.total }
				onChange={ setPage }
			/>
		</>
	);
}

function ImportExport( { onImported } ) {
	const [ sources, setSources ] = useState( null );
	const [ busy, setBusy ] = useState( null );
	const [ result, setResult ] = useState( null );

	useEffect( () => {
		apiFetch( { path: `${ REST_BASE }/redirects/sources` } )
			.then( setSources )
			.catch( () => setSources( [] ) );
	}, [] );

	const importCsv = async ( event ) => {
		const file = event.target.files?.[ 0 ];

		if ( ! file ) {
			return;
		}

		setBusy( 'csv' );
		setResult( null );

		try {
			const response = await apiFetch( {
				path: `${ REST_BASE }/redirects/import`,
				method: 'POST',
				data: { csv: await file.text() },
			} );
			setResult( {
				status: response.errors.length ? 'warning' : 'success',
				text: sprintf(
					/* translators: %d: number of redirects. */
					_n(
						'%d redirect imported.',
						'%d redirects imported.',
						response.created,
						'wpinterface-seo'
					),
					response.created
				),
				errors: response.errors,
			} );
			onImported();
		} catch ( caught ) {
			setResult( { status: 'error', text: caught.message } );
		} finally {
			setBusy( null );
		}
	};

	const exportCsv = async () => {
		setBusy( 'export' );

		try {
			const response = await apiFetch( {
				path: `${ REST_BASE }/redirects/export`,
			} );
			download( 'redirects.csv', response.csv, 'text/csv' );
		} catch ( caught ) {
			setResult( { status: 'error', text: caught.message } );
		} finally {
			setBusy( null );
		}
	};

	const importSource = async ( source ) => {
		setBusy( source.id );
		setResult( null );

		try {
			const response = await apiFetch( {
				path: `${ REST_BASE }/redirects/sources`,
				method: 'POST',
				data: { source: source.id },
			} );
			setResult( {
				status: 'success',
				text: sprintf(
					/* translators: 1: number imported, 2: number skipped, 3: plugin name. */
					__(
						'%1$d redirects imported from %3$s, %2$d skipped (duplicates or invalid).',
						'wpinterface-seo'
					),
					response.created,
					response.skipped,
					source.label
				),
			} );
			onImported();
		} catch ( caught ) {
			setResult( { status: 'error', text: caught.message } );
		} finally {
			setBusy( null );
		}
	};

	return (
		<>
			<div className="wpiseo-actions-row">
				<FormFileUpload
					accept=".csv,text/csv"
					onChange={ importCsv }
					render={ ( { openFileDialog } ) => (
						<Button
							variant="secondary"
							onClick={ openFileDialog }
							isBusy={ 'csv' === busy }
							disabled={ !! busy }
						>
							{ __( 'Import CSV', 'wpinterface-seo' ) }
						</Button>
					) }
				/>
				<Button
					variant="secondary"
					onClick={ exportCsv }
					isBusy={ 'export' === busy }
					disabled={ !! busy }
				>
					{ __( 'Export CSV', 'wpinterface-seo' ) }
				</Button>
			</div>
			<p className="wpiseo-muted">
				{ __(
					'CSV columns: source, target, status, match (exact or regex). The first row may be a header.',
					'wpinterface-seo'
				) }
			</p>

			{ sources === null && <Spinner /> }
			{ sources?.length > 0 && (
				<>
					<h3 className="wpiseo-subhead">
						{ __( 'Found in other plugins', 'wpinterface-seo' ) }
					</h3>
					<div className="wpiseo-actions-row">
						{ sources.map( ( source ) => (
							<Button
								key={ source.id }
								variant="secondary"
								onClick={ () => importSource( source ) }
								isBusy={ source.id === busy }
								disabled={ !! busy }
							>
								{ sprintf(
									/* translators: 1: plugin name, 2: number of redirects. */
									__(
										'Import %2$d from %1$s',
										'wpinterface-seo'
									),
									source.label,
									source.count
								) }
							</Button>
						) ) }
					</div>
				</>
			) }

			{ result && (
				<Notice
					status={ result.status }
					onRemove={ () => setResult( null ) }
				>
					<p>{ result.text }</p>
					{ result.errors?.length > 0 && (
						<ul>
							{ result.errors.map( ( line ) => (
								<li key={ line }>{ line }</li>
							) ) }
						</ul>
					) }
				</Notice>
			) }
		</>
	);
}

function RedirectSettings( { settings, save, saving } ) {
	const draft = useDraft( settings.redirects );

	const onSave = async () => {
		if ( await save( { redirects: draft.values } ) ) {
			draft.markSaved();
		}
	};

	return (
		<Section
			title={ __( 'Automatic redirects', 'wpinterface-seo' ) }
			description={ __(
				'Keep old links working when URLs change.',
				'wpinterface-seo'
			) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Redirect the old URL when a published post’s URL changes',
					'wpinterface-seo'
				) }
				checked={ !! draft.get( 'auto_post', true ) }
				onChange={ ( value ) => draft.set( 'auto_post', value ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Redirect the old URL when a category or tag URL changes',
					'wpinterface-seo'
				) }
				checked={ !! draft.get( 'auto_term', true ) }
				onChange={ ( value ) => draft.set( 'auto_term', value ) }
			/>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __(
					'When a published post is trashed',
					'wpinterface-seo'
				) }
				value={ draft.get( 'on_trash', 'none' ) }
				options={ [
					{
						value: 'none',
						label: __(
							'Do nothing (visitors get a 404)',
							'wpinterface-seo'
						),
					},
					{
						value: '410',
						label: __(
							'Return 410 Content deleted',
							'wpinterface-seo'
						),
					},
					{
						value: 'redirect',
						label: __(
							'Redirect to the parent page or post type archive',
							'wpinterface-seo'
						),
					},
				] }
				help={ __(
					'Restoring the post from the trash removes the rule again.',
					'wpinterface-seo'
				) }
				onChange={ ( value ) => draft.set( 'on_trash', value ) }
			/>
			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>
		</Section>
	);
}

function Figures( { reloadKey } ) {
	const [ stats, setStats ] = useState( null );
	const logUrl = ( config.pages || [] ).find(
		( page ) => page.id === '404-log'
	)?.url;

	useEffect( () => {
		apiFetch( { path: `${ REST_BASE }/redirects/stats` } )
			.then( setStats )
			.catch( () => setStats( null ) );
	}, [ reloadKey ] );

	if ( ! stats ) {
		return null;
	}

	return (
		<StatTiles label={ __( 'Redirect figures', 'wpinterface-seo' ) }>
			<StatTile
				label={ __( 'Active redirects', 'wpinterface-seo' ) }
				value={ formatNumber( stats.active ) }
			/>
			<StatTile
				label={ __( 'Redirected visits', 'wpinterface-seo' ) }
				value={ formatCompact( stats.hits ) }
			>
				<span className="wpiseo-muted">
					{ __( 'Since each redirect was added', 'wpinterface-seo' ) }
				</span>
			</StatTile>
			{ stats.notFound && (
				<StatTile
					label={ __(
						'New missing pages this week',
						'wpinterface-seo'
					) }
					value={ formatNumber( stats.notFound.new ) }
				>
					{ logUrl && (
						<a href={ logUrl }>
							{ __( 'Open the 404 log', 'wpinterface-seo' ) }
						</a>
					) }
				</StatTile>
			) }
		</StatTiles>
	);
}

export default function RedirectsPage( { settings, save, saving } ) {
	const [ reloadKey, setReloadKey ] = useState( 0 );
	const [ adding, setAdding ] = useState( false );
	const reload = () => setReloadKey( ( key ) => key + 1 );

	return (
		<div className="wpiseo-page wpiseo-page--wide wpiseo-stack">
			<HeaderActions>
				<Button
					variant="primary"
					size="compact"
					onClick={ () => setAdding( true ) }
					disabled={ adding }
				>
					{ __( 'Add redirect', 'wpinterface-seo' ) }
				</Button>
			</HeaderActions>

			<Figures reloadKey={ reloadKey } />

			<Section
				description={ __(
					'Send visitors and search engines from old URLs to new ones. Redirects keep working even if another SEO plugin is active.',
					'wpinterface-seo'
				) }
			>
				{ adding && (
					<RuleForm
						onCancel={ () => setAdding( false ) }
						onSaved={ () => {
							setAdding( false );
							reload();
						} }
					/>
				) }
				<RulesTable reloadKey={ reloadKey } />
			</Section>

			<div className="wpiseo-pair">
				{ config.can?.manageSettings && settings.redirects && (
					<RedirectSettings
						settings={ settings }
						save={ save }
						saving={ saving }
					/>
				) }
				<Section title={ __( 'Import and export', 'wpinterface-seo' ) }>
					<ImportExport onImported={ reload } />
				</Section>
			</div>
		</div>
	);
}
