/**
 * Helpers for editing one settings section with a local draft.
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useCallback, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export const config = window.wpiseoAdmin || {};

/**
 * Plugin name as shown in the admin (can be white-labelled).
 *
 * @return {string} Name.
 */
export const brandName = () => config.brand?.name || 'WPInterface SEO';

/**
 * Immutable set by path, e.g. setIn( obj, [ 'post_types', 'post', 'title' ], 'x' ).
 *
 * @param {Object}   object Source.
 * @param {string[]} path   Path.
 * @param {*}        value  Value.
 * @return {Object} Copy.
 */
export function setIn( object, path, value ) {
	const [ key, ...rest ] = path;
	const base = object && typeof object === 'object' ? object : {};

	return {
		...base,
		[ key ]: rest.length ? setIn( base[ key ], rest, value ) : value,
	};
}

/**
 * Reads by path.
 *
 * @param {Object}   object       Source.
 * @param {string[]} path         Path.
 * @param {*}        defaultValue Default.
 * @return {*} Value.
 */
export function getIn( object, path, defaultValue ) {
	const value = path.reduce(
		( current, key ) =>
			current && typeof current === 'object' ? current[ key ] : undefined,
		object
	);

	return value === undefined ? defaultValue : value;
}

/**
 * Draft state for a settings section.
 *
 * @param {Object} initial Initial section values.
 * @return {Object} { values, get, set, dirty, reset }.
 */
export function useDraft( initial ) {
	const [ values, setValues ] = useState( initial || {} );
	const [ dirty, setDirty ] = useState( false );

	const set = useCallback( ( path, value ) => {
		setValues( ( current ) =>
			setIn( current, Array.isArray( path ) ? path : [ path ], value )
		);
		setDirty( true );
	}, [] );

	const get = useCallback(
		( path, defaultValue ) =>
			getIn(
				values,
				Array.isArray( path ) ? path : [ path ],
				defaultValue
			),
		[ values ]
	);

	return useMemo(
		() => ( {
			values,
			get,
			set,
			dirty,
			markSaved: () => setDirty( false ),
		} ),
		[ values, get, set, dirty ]
	);
}

/**
 * A card with an optional heading, description and header actions.
 *
 * @param {Object}  props             Props.
 * @param {string}  props.title       Heading.
 * @param {string}  props.description Help below the heading.
 * @param {Element} props.actions     Right side of the header (a link, a note, controls).
 * @param {string}  props.className   Extra class.
 * @param {string}  props.id          Heading ID, labels the section.
 * @param {Element} props.children    Content.
 * @return {Element} Card.
 */
export function Section( {
	title,
	description,
	actions,
	className = '',
	id,
	children,
} ) {
	return (
		<Card
			className={ `wpiseo-section ${ className }`.trim() }
			aria-labelledby={ title && id ? id : undefined }
		>
			{ ( title || description || actions ) && (
				<CardHeader isBorderless>
					<div>
						{ title && <h2 id={ id }>{ title }</h2> }
						{ description && (
							<p className="wpiseo-muted">{ description }</p>
						) }
					</div>
					{ actions && (
						<div className="wpiseo-section__actions">
							{ actions }
						</div>
					) }
				</CardHeader>
			) }
			<CardBody>{ children }</CardBody>
		</Card>
	);
}

/**
 * A titled block inside a Section, separated from its siblings by a rule.
 * Use it instead of one Section per item (e.g. one per post type).
 *
 * @param {Object}  props             Props.
 * @param {string}  props.title       Heading.
 * @param {string}  props.description Optional help below the heading.
 * @param {Element} props.children    Content.
 * @return {Element} Group.
 */
export function Group( { title, description, children } ) {
	return (
		<div className="wpiseo-group">
			<h3 className="wpiseo-group__title">{ title }</h3>
			{ description && <p className="wpiseo-muted">{ description }</p> }
			{ children }
		</div>
	);
}

export function SaveBar( { onSave, saving, dirty } ) {
	return (
		<div className="wpiseo-savebar">
			<Button
				variant="primary"
				onClick={ onSave }
				isBusy={ saving }
				disabled={ saving || ! dirty }
			>
				{ __( 'Save changes', 'wpinterface-seo' ) }
			</Button>
			{ dirty && ! saving && (
				<span className="wpiseo-muted">
					{ __( 'You have unsaved changes.', 'wpinterface-seo' ) }
				</span>
			) }
		</div>
	);
}
