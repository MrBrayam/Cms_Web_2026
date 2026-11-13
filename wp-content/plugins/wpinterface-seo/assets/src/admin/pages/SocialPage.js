/**
 * Social settings: Open Graph / X, default image, profiles.
 */
import {
	SelectControl,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import ImagePicker from '../../editor/components/ImagePicker';
import { config, SaveBar, Section, useDraft } from '../components/section';

export default function SocialPage( { settings, save, saving } ) {
	const draft = useDraft( settings.social );
	const labels = config.networkLabels || {};

	const onSave = async () => {
		if ( await save( { social: draft.values } ) ) {
			draft.markSaved();
		}
	};

	return (
		<div className="wpiseo-page wpiseo-stack">
			<Section
				title={ __( 'Sharing tags', 'wpinterface-seo' ) }
				description={ __(
					'Control how links to your site look when shared on Facebook, LinkedIn, WhatsApp, X and others.',
					'wpinterface-seo'
				) }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Add Open Graph tags (Facebook, LinkedIn, WhatsApp, …)',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'opengraph', true ) }
					onChange={ ( value ) => draft.set( 'opengraph', value ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Add X (Twitter) card tags',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'twitter', true ) }
					onChange={ ( value ) => draft.set( 'twitter', value ) }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'X card style', 'wpinterface-seo' ) }
					value={ draft.get( 'twitter_card', 'summary_large_image' ) }
					options={ [
						{
							value: 'summary_large_image',
							label: __( 'Large image', 'wpinterface-seo' ),
						},
						{
							value: 'summary',
							label: __( 'Small image', 'wpinterface-seo' ),
						},
					] }
					onChange={ ( value ) => draft.set( 'twitter_card', value ) }
				/>
				<ImagePicker
					label={ __( 'Default image', 'wpinterface-seo' ) }
					help={ __(
						'Used when a page has no featured or custom social image. 1200 × 630 pixels works everywhere.',
						'wpinterface-seo'
					) }
					value={ draft.get( 'default_image', 0 ) }
					onChange={ ( value ) =>
						draft.set( 'default_image', value )
					}
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __(
						'Facebook App ID (optional)',
						'wpinterface-seo'
					) }
					value={ draft.get( 'facebook_app_id', '' ) }
					onChange={ ( value ) =>
						draft.set( 'facebook_app_id', value )
					}
				/>
			</Section>

			<Section
				title={ __( 'Your profiles', 'wpinterface-seo' ) }
				description={ __(
					'Profiles of your organization or brand. Search engines use them to connect your site with your social accounts.',
					'wpinterface-seo'
				) }
			>
				<div className="wpiseo-grid-2">
					{ ( config.networks || [] ).map( ( network ) => (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							key={ network }
							type="url"
							label={ labels[ network ] || network }
							placeholder={
								network === 'x'
									? 'https://x.com/yourname'
									: 'https://'
							}
							value={ draft.get( [ 'profiles', network ], '' ) }
							onChange={ ( value ) =>
								draft.set( [ 'profiles', network ], value )
							}
						/>
					) ) }
				</div>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Other profile URLs', 'wpinterface-seo' ) }
					help={ __( 'One URL per line.', 'wpinterface-seo' ) }
					value={ (
						draft.get( [ 'profiles', 'other' ], [] ) || []
					).join( '\n' ) }
					onChange={ ( value ) =>
						draft.set(
							[ 'profiles', 'other' ],
							value.split( '\n' ).map( ( line ) => line.trim() )
						)
					}
				/>
			</Section>

			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ draft.dirty }
			/>
		</div>
	);
}
