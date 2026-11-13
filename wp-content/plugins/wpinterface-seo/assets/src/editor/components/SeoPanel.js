/**
 * Tabbed SEO panel used in both the block editor sidebar and the classic metabox.
 */
import { TabPanel } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import config from '../config';
import AdvancedTab from './AdvancedTab';
import ReadabilityTab from './ReadabilityTab';
import SchemaTab from './SchemaTab';
import SeoTab from './SeoTab';
import SocialTab from './SocialTab';

export default function SeoPanel() {
	const builtIn = [
		{
			name: 'seo',
			title: __( 'SEO', 'wpinterface-seo' ),
			component: SeoTab,
		},
		config.modules?.analysis &&
			config.readability !== false && {
				name: 'readability',
				title: __( 'Readability', 'wpinterface-seo' ),
				component: ReadabilityTab,
			},
		config.modules?.social && {
			name: 'social',
			title: __( 'Social', 'wpinterface-seo' ),
			component: SocialTab,
		},
		config.modules?.schema && {
			name: 'schema',
			title: __( 'Schema', 'wpinterface-seo' ),
			component: SchemaTab,
		},
		{
			name: 'advanced',
			title: __( 'Advanced', 'wpinterface-seo' ),
			component: AdvancedTab,
		},
	].filter( Boolean );

	/**
	 * Filters the SEO panel tabs: { name, title, component }. Components
	 * can read the editor state with window.wpiseo.editor.useSeoState().
	 *
	 * @param {Object[]} tabs Tabs.
	 */
	const tabs = applyFilters( 'wpinterface-seo.editor.tabs', builtIn ).filter(
		( tab ) => tab && tab.name && tab.component
	);

	return (
		<div className={ `wpiseo-panel is-${ config.mode }` }>
			{ ! config.outputEnabled && (
				<p className="wpiseo-warning">
					{ __(
						'Another SEO plugin is active, so these settings are saved but not printed on the site yet.',
						'wpinterface-seo'
					) }
				</p>
			) }
			<TabPanel className="wpiseo-tabs" tabs={ tabs }>
				{ ( tab ) => {
					const Tab = tabs.find(
						( item ) => item.name === tab.name
					).component;
					return <Tab />;
				} }
			</TabPanel>
		</div>
	);
}
