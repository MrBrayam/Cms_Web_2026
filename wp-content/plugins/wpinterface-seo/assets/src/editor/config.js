/**
 * Server-provided editor configuration (see Editor::config()).
 */
const config = window.wpiseoEditor || {};

export const PREFIX = '_wpiseo_';

export const metaKey = ( key ) => `${ PREFIX }${ key }`;

export default config;
