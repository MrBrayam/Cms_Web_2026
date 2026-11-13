/**
 * SEO admin app entry.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import App from './App';
import { Meter, Sparkline, StackedBar, TrendChart } from './components/charts';
import {
	formatCompact,
	formatDay,
	formatDecimal,
	formatNumber,
	formatPercent,
	percentChange,
	timeAgo,
} from './components/format';
import {
	download,
	formatDate,
	Pagination,
	REST_BASE,
	useRemoteList,
	useSelection,
} from './components/list';
import {
	brandName,
	getIn,
	Group,
	SaveBar,
	Section,
	setIn,
	useDraft,
} from './components/section';
import {
	Delta,
	HeaderActions,
	Segmented,
	StatTile,
	StatTiles,
	StatusIcon,
} from './components/ui';
import VariableField from '../editor/components/VariableField';
import './admin.scss';

/*
 * Public API for add-ons (loaded after this script): shared components, and
 * the `wpinterface-seo.admin.pages` and `wpinterface-seo.dashboard.cards`
 * filters (wp.hooks) to add pages and dashboard cards:
 *
 *     wp.hooks.addFilter( 'wpinterface-seo.admin.pages', 'my-addon', ( pages ) => ( { ...pages, 'my-page': MyPage } ) );
 */
window.wpiseo = window.wpiseo || {};
window.wpiseo.admin = {
	components: {
		Delta,
		Group,
		HeaderActions,
		Meter,
		Pagination,
		SaveBar,
		Section,
		Segmented,
		Sparkline,
		StackedBar,
		StatTile,
		StatTiles,
		StatusIcon,
		TrendChart,
		VariableField,
	},
	hooks: { useDraft, useRemoteList, useSelection },
	utils: {
		brandName,
		download,
		formatCompact,
		formatDate,
		formatDay,
		formatDecimal,
		formatNumber,
		formatPercent,
		getIn,
		percentChange,
		REST_BASE,
		setIn,
		timeAgo,
	},
};

domReady( () => {
	const root = document.getElementById( 'wpiseo-admin-root' );

	if ( ! root ) {
		return;
	}

	createRoot( root ).render( <App page={ root.dataset.page } /> );
} );
