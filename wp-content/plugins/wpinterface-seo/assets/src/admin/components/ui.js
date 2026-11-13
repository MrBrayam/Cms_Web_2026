/**
 * Small shared pieces: stat tiles, change labels, status icons, a segmented
 * control and the page header's action slot.
 */
import { createPortal } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Renders children into the page header (right side, before Help).
 *
 * @param {Object}  props          Props.
 * @param {Element} props.children Buttons, links or text.
 * @return {Element|null} Portal.
 */
export function HeaderActions( { children } ) {
	const slot = document.getElementById( 'wpiseo-header-actions' );

	return slot ? createPortal( children, slot ) : null;
}

/**
 * Row of stat tiles.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.label    Accessible name of the group.
 * @param {Element} props.children StatTile elements.
 */
export function StatTiles( { label, children } ) {
	return (
		<section className="wpiseo-tiles" aria-label={ label }>
			{ children }
		</section>
	);
}

/**
 * One figure: label, value (with optional unit) and a footer line.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.label    What is measured.
 * @param {string}  props.value    Formatted value.
 * @param {string}  props.unit     Text after the value, e.g. "/100".
 * @param {Element} props.children Footer: change, note, sparkline or meter.
 */
export function StatTile( { label, value, unit, children } ) {
	return (
		<div className="wpiseo-tile">
			<span className="wpiseo-tile__label">{ label }</span>
			<span className="wpiseo-tile__value">
				{ value }
				{ unit && <span className="wpiseo-tile__unit">{ unit }</span> }
			</span>
			{ children && (
				<div className="wpiseo-tile__foot">{ children }</div>
			) }
		</div>
	);
}

/**
 * A change, coloured by whether it is good or bad, followed by context.
 *
 * @param {Object} props         Props.
 * @param {string} props.text    The change, e.g. "+17%".
 * @param {string} props.tone    up (good), down (bad) or empty.
 * @param {string} props.context e.g. "vs previous 28 days".
 */
export function Delta( { text, tone = '', context = '' } ) {
	return (
		<span className="wpiseo-delta">
			<span className={ tone ? `is-${ tone }` : 'wpiseo-muted' }>
				{ text }
			</span>
			{ context && <span className="wpiseo-muted"> { context }</span> }
		</span>
	);
}

const ICONS = {
	critical: (
		<>
			<circle cx="10" cy="10" r="9" fill="#d63638" />
			<path
				d="M10 5.5v5.5M10 13.8v.4"
				stroke="#fff"
				strokeWidth="2"
				strokeLinecap="round"
			/>
		</>
	),
	warning: (
		<>
			<path d="M10 1.8 19 17.5H1L10 1.8Z" fill="#dba617" />
			<path
				d="M10 7.5v4.5M10 14.6v.3"
				stroke="#1d2327"
				strokeWidth="1.8"
				strokeLinecap="round"
			/>
		</>
	),
	notice: (
		<>
			<circle
				cx="10"
				cy="10"
				r="8.2"
				fill="none"
				stroke="#3858e9"
				strokeWidth="1.6"
			/>
			<path
				d="M10 9v5M10 6.2v.3"
				stroke="#3858e9"
				strokeWidth="1.8"
				strokeLinecap="round"
			/>
		</>
	),
	ok: (
		<>
			<circle cx="10" cy="10" r="9" fill="#00a32a" />
			<path
				d="m6 10.2 2.7 2.7L14 7.6"
				fill="none"
				stroke="#fff"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</>
	),
	todo: (
		<circle
			cx="10"
			cy="10"
			r="8.2"
			fill="none"
			stroke="#8c8f94"
			strokeWidth="1.6"
		/>
	),
};

/**
 * Status icon with an accessible name (never colour alone).
 *
 * @param {Object} props      Props.
 * @param {string} props.type critical, warning, notice, ok or todo.
 */
export function StatusIcon( { type } ) {
	const labels = {
		critical: __( 'Critical', 'wpinterface-seo' ),
		warning: __( 'Should fix', 'wpinterface-seo' ),
		notice: __( 'Suggestion', 'wpinterface-seo' ),
		ok: __( 'Done', 'wpinterface-seo' ),
		todo: __( 'To do', 'wpinterface-seo' ),
	};

	return (
		<svg
			className="wpiseo-status-icon"
			width="18"
			height="18"
			viewBox="0 0 20 20"
			role="img"
			aria-label={ labels[ type ] || labels.notice }
		>
			{ ICONS[ type ] || ICONS.notice }
		</svg>
	);
}

/**
 * Segmented control: a few mutually exclusive options.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Accessible name.
 * @param {*}        props.value    Selected value.
 * @param {Object[]} props.options  { value, label }.
 * @param {Function} props.onChange Called with the new value.
 */
export function Segmented( { label, value, options, onChange } ) {
	return (
		<div className="wpiseo-segmented" role="group" aria-label={ label }>
			{ options.map( ( option ) => (
				<button
					key={ option.value }
					type="button"
					aria-pressed={ option.value === value }
					onClick={ () => onChange( option.value ) }
				>
					{ option.label }
				</button>
			) ) }
		</div>
	);
}
