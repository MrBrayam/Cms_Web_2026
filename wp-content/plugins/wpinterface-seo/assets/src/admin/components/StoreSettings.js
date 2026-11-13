/**
 * WooCommerce and Easy Digital Downloads settings (Search Appearance → Online store).
 */
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { SaveBar, Section, useDraft } from './section';

export default function StoreSettings( { settings, modules, save, saving } ) {
	const active = ( id ) =>
		modules.some( ( module ) => module.id === id && module.active );
	const woo = useDraft( settings.woocommerce );
	const edd = useDraft( settings.edd );

	const onSave = async () => {
		const partial = {};

		if ( woo.dirty ) {
			partial.woocommerce = woo.values;
		}
		if ( edd.dirty ) {
			partial.edd = edd.values;
		}

		if ( await save( partial ) ) {
			woo.markSaved();
			edd.markSaved();
		}
	};

	const toggle = ( draft, key, label, help ) => (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ label }
			help={ help }
			checked={ !! draft.get( key, true ) }
			onChange={ ( value ) => draft.set( key, value ) }
		/>
	);

	return (
		<>
			{ active( 'woocommerce' ) && (
				<Section
					title={ __( 'WooCommerce', 'wpinterface-seo' ) }
					description={ __(
						'Product pages use the SEO title and description templates from the Products content type.',
						'wpinterface-seo'
					) }
				>
					{ toggle(
						woo,
						'product_schema',
						__( 'Product structured data', 'wpinterface-seo' ),
						__(
							'Replaces WooCommerce’s own markup with a Product linked to your site’s schema graph: price, stock, GTIN, brand, reviews and ratings. Add GTINs in the product’s Inventory tab.',
							'wpinterface-seo'
						)
					) }
					{ toggle(
						woo,
						'noindex_pages',
						__(
							'Keep cart, checkout and account pages out of search results',
							'wpinterface-seo'
						)
					) }
					{ toggle(
						woo,
						'noindex_hidden',
						__(
							'Noindex products hidden from the catalog and search',
							'wpinterface-seo'
						),
						__(
							'They are also left out of the sitemap.',
							'wpinterface-seo'
						)
					) }
					{ toggle(
						woo,
						'remove_generator',
						__(
							'Remove the WooCommerce version tag',
							'wpinterface-seo'
						)
					) }
				</Section>
			) }

			{ active( 'edd' ) && (
				<Section
					title={ __( 'Easy Digital Downloads', 'wpinterface-seo' ) }
				>
					{ toggle(
						edd,
						'product_schema',
						__( 'Product structured data', 'wpinterface-seo' ),
						__(
							'Replaces the markup Easy Digital Downloads adds with a Product linked to your schema graph.',
							'wpinterface-seo'
						)
					) }
					{ toggle(
						edd,
						'noindex_pages',
						__(
							'Keep checkout, confirmation and purchase history pages out of search results',
							'wpinterface-seo'
						)
					) }
				</Section>
			) }

			<SaveBar
				onSave={ onSave }
				saving={ saving }
				dirty={ woo.dirty || edd.dirty }
			/>
		</>
	);
}
