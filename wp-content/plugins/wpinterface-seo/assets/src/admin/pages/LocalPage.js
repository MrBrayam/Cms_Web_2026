/**
 * Local SEO: one business location (LocalBusiness schema, address block).
 */
import {
	CheckboxControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { config, SaveBar, Section, useDraft } from '../components/section';
import { ProHint } from '../components/pro';

const DAYS = [
	[ 'monday', __( 'Monday', 'wpinterface-seo' ) ],
	[ 'tuesday', __( 'Tuesday', 'wpinterface-seo' ) ],
	[ 'wednesday', __( 'Wednesday', 'wpinterface-seo' ) ],
	[ 'thursday', __( 'Thursday', 'wpinterface-seo' ) ],
	[ 'friday', __( 'Friday', 'wpinterface-seo' ) ],
	[ 'saturday', __( 'Saturday', 'wpinterface-seo' ) ],
	[ 'sunday', __( 'Sunday', 'wpinterface-seo' ) ],
];

/**
 * "AutoRepair" → "Auto repair".
 *
 * @param {string} type schema.org type.
 * @return {string} Label.
 */
function typeLabel( type ) {
	if ( 'LocalBusiness' === type ) {
		return __( 'Local business (general)', 'wpinterface-seo' );
	}

	const words = type.replace( /([a-z])([A-Z])/g, '$1 $2' ).toLowerCase();

	return words.charAt( 0 ).toUpperCase() + words.slice( 1 );
}

export default function LocalPage( { settings, save, saving } ) {
	const draft = useDraft( settings.local );

	const onSave = async () => {
		if ( await save( { local: draft.values } ) ) {
			draft.markSaved();
		}
	};

	const text = ( key, label, props = {} ) => (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			value={ draft.get( key, '' ) }
			onChange={ ( value ) => draft.set( key, value ) }
			{ ...props }
		/>
	);

	const open247 = !! draft.get( 'open_24_7', false );

	return (
		<div className="wpiseo-page wpiseo-stack">
			<Section
				title={ __( 'Business', 'wpinterface-seo' ) }
				description={ __(
					'Adds your address, phone and opening hours to the Organization in your structured data, which helps Google show them in search and Maps. Use the same details as your Google Business Profile.',
					'wpinterface-seo'
				) }
			>
				<div className="wpiseo-grid-2">
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Business type', 'wpinterface-seo' ) }
						value={ draft.get( 'business_type', 'LocalBusiness' ) }
						options={ ( config.businessTypes || [] )
							.map( ( type ) => ( {
								value: type,
								label: typeLabel( type ),
							} ) )
							.sort( ( a, b ) =>
								'LocalBusiness' === a.value
									? -1
									: a.label.localeCompare( b.label )
							) }
						onChange={ ( value ) =>
							draft.set( 'business_type', value )
						}
					/>
					{ text( 'name', __( 'Business name', 'wpinterface-seo' ), {
						help: __(
							'Leave empty to use the organization name from the Schema settings.',
							'wpinterface-seo'
						),
					} ) }
				</div>
			</Section>

			<Section title={ __( 'Address & contact', 'wpinterface-seo' ) }>
				{ text( 'street', __( 'Street address', 'wpinterface-seo' ) ) }
				<div className="wpiseo-grid-2">
					{ text( 'locality', __( 'City', 'wpinterface-seo' ) ) }
					{ text(
						'region',
						__( 'State / province / region', 'wpinterface-seo' )
					) }
					{ text(
						'postal_code',
						__( 'Postal code', 'wpinterface-seo' )
					) }
					{ text( 'country', __( 'Country', 'wpinterface-seo' ), {
						help: __(
							'Two-letter code such as US, GB or NP.',
							'wpinterface-seo'
						),
						maxLength: 2,
					} ) }
					{ text( 'latitude', __( 'Latitude', 'wpinterface-seo' ), {
						inputMode: 'decimal',
						placeholder: '27.7172',
					} ) }
					{ text( 'longitude', __( 'Longitude', 'wpinterface-seo' ), {
						inputMode: 'decimal',
						placeholder: '85.3240',
					} ) }
					{ text( 'phone', __( 'Phone', 'wpinterface-seo' ), {
						type: 'tel',
						help: __(
							'Include the country code, e.g. +977 1 4000000.',
							'wpinterface-seo'
						),
					} ) }
					{ text( 'email', __( 'Email', 'wpinterface-seo' ), {
						type: 'email',
					} ) }
					{ text(
						'price_range',
						__( 'Price range', 'wpinterface-seo' ),
						{
							placeholder: '$$',
						}
					) }
				</div>
				<p className="wpiseo-muted">
					{ __(
						'Tip: right-click your location in any map service to copy its coordinates.',
						'wpinterface-seo'
					) }
				</p>
			</Section>

			<Section title={ __( 'Opening hours', 'wpinterface-seo' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Open 24 hours, 7 days', 'wpinterface-seo' ) }
					checked={ open247 }
					onChange={ ( value ) => draft.set( 'open_24_7', value ) }
				/>
				{ ! open247 && (
					<table className="wpiseo-hours">
						<tbody>
							{ DAYS.map( ( [ day, label ] ) => {
								const closed = !! draft.get(
									[ 'hours', day, 'closed' ],
									false
								);

								return (
									<tr key={ day }>
										<th scope="row">{ label }</th>
										<td>
											<CheckboxControl
												__nextHasNoMarginBottom
												label={ __(
													'Closed',
													'wpinterface-seo'
												) }
												checked={ closed }
												onChange={ ( value ) =>
													draft.set(
														[
															'hours',
															day,
															'closed',
														],
														value
													)
												}
											/>
										</td>
										<td>
											<input
												type="time"
												aria-label={ `${ label } ${ __(
													'opens',
													'wpinterface-seo'
												) }` }
												disabled={ closed }
												value={ draft.get(
													[ 'hours', day, 'open' ],
													'09:00'
												) }
												onChange={ ( event ) =>
													draft.set(
														[
															'hours',
															day,
															'open',
														],
														event.target.value
													)
												}
											/>
										</td>
										<td aria-hidden="true">–</td>
										<td>
											<input
												type="time"
												aria-label={ `${ label } ${ __(
													'closes',
													'wpinterface-seo'
												) }` }
												disabled={ closed }
												value={ draft.get(
													[ 'hours', day, 'close' ],
													'17:00'
												) }
												onChange={ ( event ) =>
													draft.set(
														[
															'hours',
															day,
															'close',
														],
														event.target.value
													)
												}
											/>
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				) }
			</Section>

			<Section
				title={ __( 'Show on your site', 'wpinterface-seo' ) }
				description={ __(
					'Add the “Business Address & Hours” block, or use the shortcode [wpiseo_local show="name,address,phone,email,hours,map"].',
					'wpinterface-seo'
				) }
			>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Map', 'wpinterface-seo' ) }
					help={ __(
						'Embedded maps load from openstreetmap.org when the page is viewed. The link option loads nothing until clicked.',
						'wpinterface-seo'
					) }
					value={ draft.get( 'map', 'link' ) }
					options={ [
						{
							value: 'link',
							label: __(
								'Link to OpenStreetMap',
								'wpinterface-seo'
							),
						},
						{
							value: 'embed',
							label: __(
								'Embedded OpenStreetMap',
								'wpinterface-seo'
							),
						},
						{
							value: 'none',
							label: __( 'No map', 'wpinterface-seo' ),
						},
					] }
					onChange={ ( value ) => draft.set( 'map', value ) }
				/>
			</Section>

			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>

			<ProHint id="local">
				{ __(
					'Have more than one location? Pro adds any number of locations, each with its own schema, and a store locator.',
					'wpinterface-seo'
				) }
			</ProHint>
		</div>
	);
}
