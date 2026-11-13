/**
 * SEO editor app: block editor sidebar or classic editor metabox.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import config, { metaKey } from './config';
import ClassicSource from './source/ClassicSource';
import { getMeta } from './source/context';
import { SeoStateProvider, useSeoState } from './state';
import AiSuggest from './components/AiSuggest';
import ImagePicker from './components/ImagePicker';
import SeoPanel from './components/SeoPanel';
import VariableField from './components/VariableField';
import './editor.scss';

/*
 * Public API for add-ons (load after this script): editor state and shared
 * components, plus the `wpinterface-seo.editor.tabs` filter (wp.hooks).
 */
window.wpiseo = window.wpiseo || {};
window.wpiseo.editor = {
	config,
	metaKey,
	getMeta,
	useSeoState,
	components: { AiSuggest, ImagePicker, VariableField },
};

if ( config.mode === 'block' ) {
	// Loaded lazily so classic screens never evaluate block editor packages.
	import( /* webpackMode: "eager" */ './block' ).then( ( module ) =>
		module.default()
	);
} else {
	domReady( () => {
		const root = document.getElementById( 'wpiseo-metabox-root' );

		if ( ! root ) {
			return;
		}

		createRoot( root ).render(
			<ClassicSource>
				<SeoStateProvider>
					<SeoPanel />
				</SeoStateProvider>
			</ClassicSource>
		);
	} );
}
