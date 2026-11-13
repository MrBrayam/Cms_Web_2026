/**
 * Settings app shell: loads settings once, renders the requested page.
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import AiPage from './pages/AiPage';
import BreadcrumbsPage from './pages/BreadcrumbsPage';
import BulkEditorPage from './pages/BulkEditorPage';
import CrawlPage from './pages/CrawlPage';
import DashboardPage from './pages/dashboard';
import GeneralPage from './pages/GeneralPage';
import ImagesPage from './pages/ImagesPage';
import LocalPage from './pages/LocalPage';
import ModulesPage from './pages/ModulesPage';
import NotFoundLogPage from './pages/NotFoundLogPage';
import RedirectsPage from './pages/RedirectsPage';
import SchemaPage from './pages/SchemaPage';
import SearchConsolePage from './pages/SearchConsolePage';
import SearchAppearancePage from './pages/SearchAppearancePage';
import SetupPage from './pages/SetupPage';
import SitemapsPage from './pages/SitemapsPage';
import SocialPage from './pages/SocialPage';
import ToolsPage from './pages/ToolsPage';

const config = window.wpiseoAdmin || {};
const SETTINGS_PATH = `/${
	config.restNamespace || 'wpinterface-seo/v1'
}/settings`;

const PAGES = {
	dashboard: DashboardPage,
	general: GeneralPage,
	modules: ModulesPage,
	'search-appearance': SearchAppearancePage,
	social: SocialPage,
	schema: SchemaPage,
	sitemaps: SitemapsPage,
	breadcrumbs: BreadcrumbsPage,
	redirects: RedirectsPage,
	'404-log': NotFoundLogPage,
	images: ImagesPage,
	crawl: CrawlPage,
	local: LocalPage,
	'bulk-editor': BulkEditorPage,
	'search-console': SearchConsolePage,
	ai: AiPage,
	tools: ToolsPage,
	setup: SetupPage,
};

export default function App( { page } ) {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		// Users who can only edit SEO or manage redirects cannot read settings;
		// their pages load their own data.
		if ( ! config.can?.manageSettings ) {
			setData( { settings: {}, modules: [] } );
			return;
		}

		apiFetch( { path: SETTINGS_PATH } )
			.then( setData )
			.catch( ( error ) => setLoadError( error.message ) );
	}, [] );

	/**
	 * Saves a partial settings object, e.g. { general: { separator: '|' } }.
	 * Resolves to true on success.
	 */
	const save = useCallback( async ( partial ) => {
		setSaving( true );
		setNotice( null );

		try {
			const response = await apiFetch( {
				path: SETTINGS_PATH,
				method: 'POST',
				data: { settings: partial },
			} );
			setData( response );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'wpinterface-seo' ),
			} );
			return true;
		} catch ( error ) {
			setNotice( { status: 'error', message: error.message } );
			return false;
		} finally {
			setSaving( false );
		}
	}, [] );

	/**
	 * Filters the admin pages (page ID => component). Add-ons add pages that
	 * they registered with the `wpiseo_admin_pages` PHP filter.
	 *
	 * @param {Object} pages Page components.
	 */
	const pages = applyFilters( 'wpinterface-seo.admin.pages', PAGES );
	const Page = pages[ page ] || DashboardPage;

	return (
		<div className="wpiseo-admin">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					className="wpiseo-admin__notice"
				>
					{ notice.message }
				</Notice>
			) }

			{ loadError && (
				<Notice status="error" isDismissible={ false }>
					{ loadError }
				</Notice>
			) }

			{ ! data && ! loadError && <Spinner /> }

			{ data && (
				<Page
					settings={ data.settings }
					modules={ data.modules }
					config={ config }
					save={ save }
					saving={ saving }
				/>
			) }
		</div>
	);
}
