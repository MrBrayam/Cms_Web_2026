/**
 * Schema tab: page/article type and a structured data form built from the
 * type definitions in Types::definitions() (config.schemaTypes).
 */
import {
	Button,
	Notice,
	PanelBody,
	SelectControl,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import config, { metaKey } from '../config';
import { getMeta } from '../source/context';
import { useSeoState } from '../state';
import { aiAvailable, useAiRequest } from './AiSuggest';
import ImagePicker from './ImagePicker';

const types = config.schemaTypes || {};

/**
 * Whether a stored value counts as filled in.
 *
 * @param {*} value Value.
 * @return {boolean} Filled.
 */
function isFilled( value ) {
	if ( Array.isArray( value ) ) {
		return value.some( ( item ) =>
			item && typeof item === 'object'
				? Object.values( item ).some( isFilled )
				: String( item ).trim() !== ''
		);
	}

	return value !== undefined && value !== null && value !== '' && value !== 0;
}

/**
 * The stored meta value as { type, data } (REST returns [] when empty).
 *
 * @param {*} value Raw meta value.
 * @return {Object} Normalised value.
 */
function normalise( value ) {
	if ( ! value || Array.isArray( value ) || typeof value !== 'object' ) {
		return { type: '', data: {} };
	}

	return {
		type: value.type || '',
		data: value.data && ! Array.isArray( value.data ) ? value.data : {},
	};
}

function Field( { name, field, value, onChange, postTitle } ) {
	const common = {
		__nextHasNoMarginBottom: true,
		label: field.required ? `${ field.label } *` : field.label,
		help: field.help,
	};
	const placeholder =
		field.placeholder === 'title' ? postTitle : field.placeholder;

	switch ( field.type ) {
		case 'textarea':
		case 'html':
			return (
				<TextareaControl
					{ ...common }
					rows={ 3 }
					value={ value || '' }
					onChange={ onChange }
				/>
			);
		case 'image':
			return (
				<ImagePicker
					label={ common.label }
					help={ field.help }
					value={ value || 0 }
					onChange={ onChange }
				/>
			);
		case 'boolean':
			return (
				<ToggleControl
					{ ...common }
					checked={ !! value }
					onChange={ onChange }
				/>
			);
		case 'select':
			return (
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					value={ value || '' }
					options={ [
						{ value: '', label: '—' },
						...Object.entries( field.options || {} ).map(
							( [ optionValue, label ] ) => ( {
								value: optionValue,
								label,
							} )
						),
					] }
					onChange={ onChange }
				/>
			);
		case 'list':
			return (
				<TextareaControl
					{ ...common }
					rows={ 4 }
					value={ ( Array.isArray( value ) ? value : [] ).join(
						'\n'
					) }
					onChange={ ( text ) => onChange( text.split( '\n' ) ) }
				/>
			);
		case 'repeater':
			return (
				<Repeater
					name={ name }
					field={ field }
					label={ common.label }
					rows={ Array.isArray( value ) ? value : [] }
					onChange={ onChange }
					postTitle={ postTitle }
				/>
			);
		default: {
			const inputTypes = {
				url: 'url',
				number: 'number',
				date: 'date',
				datetime: 'datetime-local',
			};

			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					type={ inputTypes[ field.type ] || 'text' }
					placeholder={
						field.type === 'duration'
							? __( 'e.g. 45 or 1:30', 'wpinterface-seo' )
							: placeholder
					}
					value={
						value === undefined || value === null
							? ''
							: String( value )
					}
					onChange={ onChange }
				/>
			);
		}
	}
}

function Repeater( { name, field, label, rows, onChange, postTitle } ) {
	const update = ( index, key, value ) =>
		onChange(
			rows.map( ( row, i ) =>
				i === index ? { ...row, [ key ]: value } : row
			)
		);

	const move = ( index, offset ) => {
		const next = [ ...rows ];
		const [ row ] = next.splice( index, 1 );
		next.splice( index + offset, 0, row );
		onChange( next );
	};

	return (
		<fieldset className="wpiseo-repeater">
			<legend>{ label }</legend>
			{ rows.map( ( row, index ) => (
				<div className="wpiseo-repeater__row" key={ index }>
					<div className="wpiseo-repeater__head">
						<strong>{ index + 1 }</strong>
						<Button
							size="small"
							icon="arrow-up-alt2"
							label={ __( 'Move up', 'wpinterface-seo' ) }
							disabled={ index === 0 }
							onClick={ () => move( index, -1 ) }
						/>
						<Button
							size="small"
							icon="arrow-down-alt2"
							label={ __( 'Move down', 'wpinterface-seo' ) }
							disabled={ index === rows.length - 1 }
							onClick={ () => move( index, 1 ) }
						/>
						<Button
							size="small"
							icon="trash"
							isDestructive
							label={ __( 'Remove', 'wpinterface-seo' ) }
							onClick={ () =>
								onChange(
									rows.filter( ( _, i ) => i !== index )
								)
							}
						/>
					</div>
					{ Object.entries( field.subfields || {} ).map(
						( [ key, subfield ] ) => (
							<Field
								key={ key }
								name={ `${ name }-${ index }-${ key }` }
								field={ subfield }
								value={ row?.[ key ] }
								onChange={ ( value ) =>
									update( index, key, value )
								}
								postTitle={ postTitle }
							/>
						)
					) }
				</div>
			) ) }
			<Button
				variant="secondary"
				size="compact"
				onClick={ () => onChange( [ ...rows, {} ] ) }
			>
				{ sprintf(
					/* translators: %s: repeater name, e.g. Steps. */
					__( 'Add to %s', 'wpinterface-seo' ),
					field.label.toLowerCase()
				) }
			</Button>
		</fieldset>
	);
}

function FaqDraft( { onAdd } ) {
	const { request, busy, error } = useAiRequest();

	if ( ! aiAvailable() ) {
		return null;
	}

	return (
		<div className="wpiseo-ai">
			<Button
				variant="secondary"
				size="compact"
				icon="admin-customizer"
				isBusy={ busy }
				disabled={ busy }
				onClick={ async () => {
					const result = await request( 'faq' );
					if ( result?.questions?.length ) {
						onAdd( result.questions );
					}
				} }
			>
				{ __( 'Draft questions with AI', 'wpinterface-seo' ) }
			</Button>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</div>
	);
}

function TypeSettings( { field, set } ) {
	const schema = config.schema || {};

	return (
		<PanelBody title={ __( 'Page & article type', 'wpinterface-seo' ) }>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Page type', 'wpinterface-seo' ) }
				value={ field( 'schema_page_type' ) }
				options={ [
					{
						value: '',
						label: sprintf(
							/* translators: %s: schema type. */
							__( 'Default (%s)', 'wpinterface-seo' ),
							schema.defaultPageType
						),
					},
					...( schema.pageTypes || [] ).map( ( type ) => ( {
						value: type,
						label: type,
					} ) ),
				] }
				onChange={ set( 'schema_page_type' ) }
			/>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Article type', 'wpinterface-seo' ) }
				value={ field( 'schema_article_type' ) }
				options={ [
					{
						value: '',
						label: sprintf(
							/* translators: %s: schema type. */
							__( 'Default (%s)', 'wpinterface-seo' ),
							schema.defaultArticleType
						),
					},
					...( schema.articleTypes || [] ).map( ( type ) => ( {
						value: type,
						label:
							type === 'none'
								? __( 'None', 'wpinterface-seo' )
								: type,
					} ) ),
				] }
				onChange={ set( 'schema_article_type' ) }
			/>
		</PanelBody>
	);
}

export default function SchemaTab() {
	const { source } = useSeoState();
	const { meta, setMeta, post } = source;

	const field = ( key, fallback = '' ) =>
		getMeta( meta, metaKey( key ), fallback );
	const set = ( key ) => ( value ) =>
		setMeta( { [ metaKey( key ) ]: value } );

	const stored = normalise( field( 'schema', null ) );
	const definition = types[ stored.type ];
	const save = ( next ) => set( 'schema' )( next.type ? next : {} );

	const missing = definition
		? Object.entries( definition.fields )
				.filter(
					( [ key, item ] ) =>
						item.required && ! isFilled( stored.data[ key ] )
				)
				.map( ( [ , item ] ) => item.label )
		: [];

	const typeOptions = Object.entries( types )
		.filter( ( [ key ] ) => ! ( config.storeProduct && key === 'Product' ) )
		.map( ( [ key, item ] ) => ( { value: key, label: item.label } ) );

	return (
		<>
			<PanelBody
				title={ __( 'Structured data', 'wpinterface-seo' ) }
				initialOpen
			>
				{ config.storeProduct && (
					<p className="wpiseo-help">
						{ __(
							'Product structured data is added automatically from the product details.',
							'wpinterface-seo'
						) }
					</p>
				) }
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'This page describes…', 'wpinterface-seo' ) }
					help={ definition?.description }
					value={ stored.type }
					options={ [
						{
							value: '',
							label: __(
								'Nothing specific (default)',
								'wpinterface-seo'
							),
						},
						...typeOptions,
					] }
					onChange={ ( type ) => save( { ...stored, type } ) }
				/>

				{ 'FAQPage' === stored.type && (
					<FaqDraft
						onAdd={ ( questions ) =>
							save( {
								...stored,
								data: {
									...stored.data,
									questions: [
										...( Array.isArray(
											stored.data.questions
										)
											? stored.data.questions
											: []
										).filter(
											( row ) =>
												row?.question || row?.answer
										),
										...questions,
									],
								},
							} )
						}
					/>
				) }

				{ definition && missing.length > 0 && (
					<Notice status="warning" isDismissible={ false }>
						{ sprintf(
							/* translators: %s: list of field names. */
							__(
								'Required for rich results: %s. Without them the markup is still added but Google will not show it.',
								'wpinterface-seo'
							),
							missing.join( ', ' )
						) }
					</Notice>
				) }

				{ definition && (
					<div className="wpiseo-schema-fields">
						{ Object.entries( definition.fields ).map(
							( [ key, item ] ) => (
								<Field
									key={ `${ stored.type }-${ key }` }
									name={ key }
									field={ item }
									value={ stored.data[ key ] }
									postTitle={ post.title }
									onChange={ ( value ) =>
										save( {
											...stored,
											data: {
												...stored.data,
												[ key ]: value,
											},
										} )
									}
								/>
							)
						) }
					</div>
				) }
			</PanelBody>

			<TypeSettings field={ field } set={ set } />
		</>
	);
}
