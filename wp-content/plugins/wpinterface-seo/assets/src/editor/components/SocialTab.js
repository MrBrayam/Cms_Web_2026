/**
 * Social tab: Facebook/LinkedIn (Open Graph) and X previews and overrides.
 */
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import config, { metaKey } from '../config';
import { getMeta } from '../source/context';
import { useSeoState } from '../state';
import { useRenderedTemplate } from '../template';
import { truncateChars } from '../util';
import AiSuggest from './AiSuggest';
import ImagePicker, { useImageUrl } from './ImagePicker';
import VariableField from './VariableField';

function host( url ) {
	try {
		return new URL( url ).host;
	} catch ( error ) {
		return config.site?.host || '';
	}
}

function SocialCard( { network, image, title, description, url } ) {
	return (
		<div className={ `wpiseo-social-card is-${ network }` }>
			<div className="wpiseo-social-card__image">
				{ image ? (
					<img src={ image } alt="" />
				) : (
					<span>{ __( 'No image', 'wpinterface-seo' ) }</span>
				) }
			</div>
			<div className="wpiseo-social-card__body">
				{ network === 'facebook' && (
					<div className="wpiseo-social-card__host">
						{ host( url ).toUpperCase() }
					</div>
				) }
				<div className="wpiseo-social-card__title">
					{ truncateChars( title, 90 ) }
				</div>
				{ network === 'facebook' && (
					<div className="wpiseo-social-card__description">
						{ truncateChars( description, 110 ) }
					</div>
				) }
				{ network === 'x' && (
					<div className="wpiseo-social-card__host">
						{ host( url ) }
					</div>
				) }
			</div>
		</div>
	);
}

export default function SocialTab() {
	const { source, title, description } = useSeoState();
	const { meta, setMeta, post } = source;

	const field = ( key ) => getMeta( meta, metaKey( key ) );
	const set = ( key ) => ( value ) =>
		setMeta( { [ metaKey( key ) ]: value } );

	const ogTitle =
		useRenderedTemplate( field( 'og_title' ), post, meta ) || title;
	const ogDescription =
		useRenderedTemplate( field( 'og_description' ), post, meta ) ||
		description;
	const xTitle =
		useRenderedTemplate( field( 'twitter_title' ), post, meta ) || ogTitle;
	const xDescription =
		useRenderedTemplate( field( 'twitter_description' ), post, meta ) ||
		ogDescription;

	const ogImageUrl = useImageUrl( field( 'og_image' ) );
	const xImageUrl = useImageUrl( field( 'twitter_image' ) );
	const fallbackImage =
		post.featuredImage || config.social?.defaultImage || '';
	const ogImage = ogImageUrl || fallbackImage;
	const xImage = xImageUrl || ogImage;

	return (
		<>
			<PanelBody
				title={ __( 'Facebook, LinkedIn & others', 'wpinterface-seo' ) }
				initialOpen
			>
				<SocialCard
					network="facebook"
					image={ ogImage }
					title={ ogTitle }
					description={ ogDescription }
					url={ post.permalink }
				/>
				<ImagePicker
					label={ __( 'Image', 'wpinterface-seo' ) }
					value={ field( 'og_image' ) }
					onChange={ set( 'og_image' ) }
					help={ __(
						'Defaults to the featured image. Recommended: 1200 × 630 pixels.',
						'wpinterface-seo'
					) }
				/>
				<VariableField
					label={ __( 'Title', 'wpinterface-seo' ) }
					value={ field( 'og_title' ) }
					placeholder={ title }
					onChange={ set( 'og_title' ) }
				/>
				<VariableField
					multiline
					label={ __( 'Description', 'wpinterface-seo' ) }
					value={ field( 'og_description' ) }
					placeholder={ description }
					onChange={ set( 'og_description' ) }
				/>
				<AiSuggest
					field="social"
					onApply={ ( value ) =>
						setMeta( {
							[ metaKey( 'og_title' ) ]: value.title,
							[ metaKey( 'og_description' ) ]: value.description,
						} )
					}
				/>
			</PanelBody>

			<PanelBody title={ __( 'X (Twitter)', 'wpinterface-seo' ) }>
				<SocialCard
					network="x"
					image={ xImage }
					title={ xTitle }
					description={ xDescription }
					url={ post.permalink }
				/>
				<ImagePicker
					label={ __( 'Image', 'wpinterface-seo' ) }
					value={ field( 'twitter_image' ) }
					onChange={ set( 'twitter_image' ) }
					help={ __(
						'Defaults to the Facebook image.',
						'wpinterface-seo'
					) }
				/>
				<VariableField
					label={ __( 'Title', 'wpinterface-seo' ) }
					value={ field( 'twitter_title' ) }
					placeholder={ ogTitle }
					onChange={ set( 'twitter_title' ) }
				/>
				<VariableField
					multiline
					label={ __( 'Description', 'wpinterface-seo' ) }
					value={ field( 'twitter_description' ) }
					placeholder={ ogDescription }
					onChange={ set( 'twitter_description' ) }
				/>
			</PanelBody>
		</>
	);
}
