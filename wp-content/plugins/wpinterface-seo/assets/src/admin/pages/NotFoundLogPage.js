/**
 * 404 log: most-hit missing URLs, one-click redirects, retention settings.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	formatDate,
	Pagination,
	REST_BASE,
	useRemoteList,
	useSelection,
} from '../components/list';
import { config, SaveBar, Section, useDraft } from '../components/section';
import { hasTarget, STATUS_OPTIONS } from './RedirectsPage';

const PER_PAGE = 50;

function RedirectRow( { entry, onDone, onCancel } ) {
	const [ target, setTarget ] = useState( '' );
	const [ status, setStatus ] = useState( 301 );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const submit = async ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( null );

		try {
			await apiFetch( {
				path: `${ REST_BASE }/404/${ entry.id }/redirect`,
				method: 'POST',
				data: { target, status },
			} );
			onDone();
		} catch ( caught ) {
			setError( caught.message );
			setBusy( false );
		}
	};

	return (
		<form className="wpiseo-inline-form" onSubmit={ submit }>
			<code className="wpiseo-url">{ entry.url }</code>
			<span aria-hidden="true">→</span>
			{ hasTarget( status ) && (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'New URL', 'wpinterface-seo' ) }
					hideLabelFromVision
					placeholder={ __( '/new-page/', 'wpinterface-seo' ) }
					value={ target }
					onChange={ setTarget }
					required
				/>
			) }
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Type', 'wpinterface-seo' ) }
				hideLabelFromVision
				value={ String( status ) }
				options={ STATUS_OPTIONS.map( ( option ) => ( {
					value: String( option.value ),
					label: option.label,
				} ) ) }
				onChange={ ( value ) => setStatus( Number( value ) ) }
			/>
			<Button
				variant="primary"
				type="submit"
				isBusy={ busy }
				disabled={ busy }
			>
				{ __( 'Save', 'wpinterface-seo' ) }
			</Button>
			<Button variant="tertiary" onClick={ onCancel }>
				{ __( 'Cancel', 'wpinterface-seo' ) }
			</Button>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</form>
	);
}

function LogTable( { showUserAgent } ) {
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ orderby, setOrderby ] = useState( 'hits' );
	const [ redirecting, setRedirecting ] = useState( null );
	const [ error, setError ] = useState( null );
	const selection = useSelection();
	const list = useRemoteList( '/404', {
		page,
		per_page: PER_PAGE,
		search,
		orderby,
		order: 'url' === orderby ? 'asc' : 'desc',
	} );
	const ids = list.items.map( ( item ) => item.id );

	const remove = async ( data ) => {
		setError( null );

		try {
			await apiFetch( {
				path: `${ REST_BASE }/404`,
				method: 'DELETE',
				data,
			} );
			selection.clear();
			list.reload();
		} catch ( caught ) {
			setError( caught.message );
		}
	};

	const clearAll = () => {
		if (
			// eslint-disable-next-line no-alert -- Simple confirmation before a destructive action.
			window.confirm(
				__( 'Delete every entry in the 404 log?', 'wpinterface-seo' )
			)
		) {
			remove( { all: true } );
		}
	};

	return (
		<>
			<div className="wpiseo-toolbar">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="search"
					label={ __( 'Search', 'wpinterface-seo' ) }
					hideLabelFromVision
					placeholder={ __( 'Search URLs…', 'wpinterface-seo' ) }
					value={ search }
					onChange={ ( value ) => {
						setSearch( value );
						setPage( 1 );
					} }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Sort by', 'wpinterface-seo' ) }
					hideLabelFromVision
					value={ orderby }
					options={ [
						{
							value: 'hits',
							label: __( 'Most hits', 'wpinterface-seo' ),
						},
						{
							value: 'last_seen',
							label: __( 'Most recent', 'wpinterface-seo' ),
						},
						{
							value: 'first_seen',
							label: __( 'Newest', 'wpinterface-seo' ),
						},
						{ value: 'url', label: __( 'URL', 'wpinterface-seo' ) },
					] }
					onChange={ ( value ) => {
						setOrderby( value );
						setPage( 1 );
					} }
				/>
				<Button
					variant="tertiary"
					isDestructive
					onClick={ clearAll }
					disabled={ ! list.total }
				>
					{ __( 'Clear log', 'wpinterface-seo' ) }
				</Button>
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
						isDestructive
						onClick={ () => remove( { ids: selection.selected } ) }
					>
						{ __( 'Delete', 'wpinterface-seo' ) }
					</Button>
				</div>
			) }

			{ ( error || list.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ error || list.error }
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
							<th>{ __( 'URL', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Hits', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Last seen', 'wpinterface-seo' ) }</th>
							<th>{ __( 'Referrer', 'wpinterface-seo' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ list.loading && ! list.items.length && (
							<tr>
								<td colSpan="5">
									<Spinner />
								</td>
							</tr>
						) }
						{ ! list.loading && ! list.items.length && (
							<tr>
								<td colSpan="5" className="wpiseo-muted">
									{ __(
										'No 404 errors logged. Nice!',
										'wpinterface-seo'
									) }
								</td>
							</tr>
						) }
						{ list.items.map( ( entry ) =>
							redirecting === entry.id ? (
								<tr key={ entry.id }>
									<td colSpan="5">
										<RedirectRow
											entry={ entry }
											onCancel={ () =>
												setRedirecting( null )
											}
											onDone={ () => {
												setRedirecting( null );
												list.reload();
											} }
										/>
									</td>
								</tr>
							) : (
								<tr key={ entry.id }>
									<th className="check-column" scope="row">
										<CheckboxControl
											__nextHasNoMarginBottom
											aria-label={ sprintf(
												/* translators: %s: URL. */
												__(
													'Select %s',
													'wpinterface-seo'
												),
												entry.url
											) }
											checked={ selection.has(
												entry.id
											) }
											onChange={ () =>
												selection.toggle( entry.id )
											}
										/>
									</th>
									<td>
										<code className="wpiseo-url">
											{ entry.url }
										</code>
										<div className="row-actions visible">
											<Button
												variant="link"
												onClick={ () =>
													setRedirecting( entry.id )
												}
											>
												{ __(
													'Redirect',
													'wpinterface-seo'
												) }
											</Button>
											{ ' | ' }
											<Button
												variant="link"
												isDestructive
												onClick={ () =>
													remove( {
														ids: [ entry.id ],
													} )
												}
											>
												{ __(
													'Delete',
													'wpinterface-seo'
												) }
											</Button>
										</div>
										{ showUserAgent && entry.user_agent && (
											<div className="wpiseo-muted wpiseo-small">
												{ entry.user_agent }
											</div>
										) }
									</td>
									<td>{ entry.hits }</td>
									<td>{ formatDate( entry.last_seen ) }</td>
									<td className="wpiseo-url">
										{ entry.referrer || '—' }
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

function LogSettings( { settings, save, saving } ) {
	const draft = useDraft( settings.redirects );

	const onSave = async () => {
		if ( await save( { redirects: draft.values } ) ) {
			draft.markSaved();
		}
	};

	const toggle = ( key, label, help, fallback = false ) => (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ label }
			help={ help }
			checked={ !! draft.get( key, fallback ) }
			onChange={ ( value ) => draft.set( key, value ) }
		/>
	);

	return (
		<Section
			title={ __( 'Log settings', 'wpinterface-seo' ) }
			description={ __(
				'The log stores the URL, referrer and hit count. Visitor IP addresses are never stored.',
				'wpinterface-seo'
			) }
		>
			{ toggle(
				'log_404',
				__( 'Log 404 errors', 'wpinterface-seo' ),
				null,
				true
			) }
			{ toggle(
				'ignore_assets',
				__(
					'Ignore missing images, scripts and styles',
					'wpinterface-seo'
				),
				__(
					'Missing files are usually theme or plugin bugs, not broken links.',
					'wpinterface-seo'
				),
				true
			) }
			{ toggle(
				'log_bots',
				__( 'Also log hits from bots and crawlers', 'wpinterface-seo' )
			) }
			{ toggle(
				'log_user_agent',
				__( 'Store the browser user agent', 'wpinterface-seo' )
			) }
			<div className="wpiseo-grid-2">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					min={ 1 }
					max={ 365 }
					label={ __( 'Keep entries for (days)', 'wpinterface-seo' ) }
					value={ String( draft.get( 'log_retention', 30 ) ) }
					onChange={ ( value ) =>
						draft.set(
							'log_retention',
							parseInt( value, 10 ) || 30
						)
					}
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					min={ 100 }
					max={ 100000 }
					label={ __( 'Maximum entries', 'wpinterface-seo' ) }
					value={ String( draft.get( 'log_max', 10000 ) ) }
					onChange={ ( value ) =>
						draft.set( 'log_max', parseInt( value, 10 ) || 10000 )
					}
				/>
			</div>
			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>
		</Section>
	);
}

export default function NotFoundLogPage( { settings, save, saving } ) {
	return (
		<div className="wpiseo-page wpiseo-page--wide wpiseo-stack">
			<Section
				title={ __( 'Missing pages', 'wpinterface-seo' ) }
				description={ __(
					'Pages visitors tried to open that do not exist. Redirect the ones that matter to a working page; the entry is removed once redirected.',
					'wpinterface-seo'
				) }
			>
				<LogTable
					showUserAgent={ !! settings.redirects?.log_user_agent }
				/>
			</Section>

			{ config.can?.manageSettings && settings.redirects && (
				<LogSettings
					settings={ settings }
					save={ save }
					saving={ saving }
				/>
			) }
		</div>
	);
}
