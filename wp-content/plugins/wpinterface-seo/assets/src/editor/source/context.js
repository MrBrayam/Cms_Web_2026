/**
 * Editor data source shared by the block editor sidebar and classic metabox.
 *
 * Shape: {
 *   mode: 'block' | 'classic',
 *   post: { id, type, title, content, excerpt, slug, permalink, date, modified,
 *           authorName, featuredImage, terms: { [taxonomy]: [{ id, name }] }, tags: string[] },
 *   meta: { _wpiseo_*: value },
 *   setMeta( partial ),
 *   isDirty: boolean,
 * }
 */
import { createContext, useContext } from '@wordpress/element';

export const SourceContext = createContext( null );

export function useSource() {
	return useContext( SourceContext );
}

/**
 * Reads one meta field with a default.
 *
 * @param {Object} meta         Meta object.
 * @param {string} key          Prefixed key.
 * @param {*}      defaultValue Default.
 * @return {*} Value.
 */
export function getMeta( meta, key, defaultValue = '' ) {
	const value = meta ? meta[ key ] : undefined;
	return value === undefined || value === null ? defaultValue : value;
}
