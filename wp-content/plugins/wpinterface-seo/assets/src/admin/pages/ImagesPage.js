/**
 * Image SEO: automatic alt text, attachment pages, file names, bulk alt fill.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { REST_BASE } from '../components/list';
import { SaveBar, Section, useDraft } from '../components/section';

const ALT_VARIABLES = [
	'%image_title%',
	'%image_caption%',
	'%filename%',
	'%post_title%',
	'%sitename%',
];

function BulkAlt( { dirty } ) {
	const [ missing, setMissing ] = useState( null );
	const [ running, setRunning ] = useState( false );
	const [ updated, setUpdated ] = useState( 0 );
	const [ error, setError ] = useState( null );

	const count = () =>
		apiFetch( { path: `${ REST_BASE }/images/fill-alt` } )
			.then( ( response ) => setMissing( response.missing ) )
			.catch( ( caught ) => setError( caught.message ) );

	useEffect( () => {
		count();
	}, [] );

	const run = async () => {
		setRunning( true );
		setError( null );
		setUpdated( 0 );

		try {
			let remaining = missing;
			let total = 0;

			while ( remaining > 0 ) {
				// eslint-disable-next-line no-await-in-loop -- Batches must run in sequence.
				const response = await apiFetch( {
					path: `${ REST_BASE }/images/fill-alt`,
					method: 'POST',
				} );

				// Stop if a batch makes no progress (never loop forever).
				if ( ! response.updated || response.remaining >= remaining ) {
					setMissing( response.remaining );
					break;
				}

				total += response.updated;
				remaining = response.remaining;
				setUpdated( total );
				setMissing( remaining );
			}
		} catch ( caught ) {
			setError( caught.message );
		} finally {
			setRunning( false );
			count();
		}
	};

	return (
		<>
			<p>
				{ missing === null
					? __( 'Counting images…', 'wpinterface-seo' )
					: sprintf(
							/* translators: %d: number of images. */
							_n(
								'%d image in the media library has no alt text.',
								'%d images in the media library have no alt text.',
								missing,
								'wpinterface-seo'
							),
							missing
					  ) }
			</p>
			<Button
				variant="secondary"
				onClick={ run }
				isBusy={ running }
				disabled={ running || ! missing || dirty }
			>
				{ __( 'Fill missing alt text now', 'wpinterface-seo' ) }
			</Button>
			{ dirty && (
				<p className="wpiseo-muted">
					{ __(
						'Save your changes first: the saved template is used.',
						'wpinterface-seo'
					) }
				</p>
			) }
			{ running && (
				<p className="wpiseo-muted" aria-live="polite">
					{ sprintf(
						/* translators: %d: number of images updated so far. */
						_n(
							'%d image updated…',
							'%d images updated…',
							updated,
							'wpinterface-seo'
						),
						updated
					) }
				</p>
			) }
			{ ! running && updated > 0 && (
				<Notice status="success" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: number of images. */
						_n(
							'Alt text saved on %d image.',
							'Alt text saved on %d images.',
							updated,
							'wpinterface-seo'
						),
						updated
					) }
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</>
	);
}

export default function ImagesPage( { settings, save, saving } ) {
	const draft = useDraft( settings.images );

	const onSave = async () => {
		if ( await save( { images: draft.values } ) ) {
			draft.markSaved();
		}
	};

	return (
		<div className="wpiseo-page wpiseo-stack">
			<Section
				title={ __( 'Alt text', 'wpinterface-seo' ) }
				description={ __(
					'Alt text describes an image for screen readers and image search. Alt text you write in the media library always wins.',
					'wpinterface-seo'
				) }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Add alt text to images that have none when the page is shown',
						'wpinterface-seo'
					) }
					help={ __(
						'Nothing is saved: the alt text is added to the page output only.',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'auto_alt', true ) }
					onChange={ ( value ) => draft.set( 'auto_alt', value ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Also fill empty alt attributes (alt="")',
						'wpinterface-seo'
					) }
					help={ __(
						'An empty alt marks a decorative image. Turn this off if your theme uses empty alt on purpose.',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'fill_empty_alt', true ) }
					onChange={ ( value ) =>
						draft.set( 'fill_empty_alt', value )
					}
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Alt text template', 'wpinterface-seo' ) }
					help={ sprintf(
						/* translators: %s: list of variables. */
						__( 'Variables: %s', 'wpinterface-seo' ),
						ALT_VARIABLES.join( ', ' )
					) }
					value={ draft.get( 'alt_template', '%image_title%' ) }
					onChange={ ( value ) => draft.set( 'alt_template', value ) }
				/>
			</Section>

			<Section
				title={ __( 'Bulk fill alt text', 'wpinterface-seo' ) }
				description={ __(
					'Saves alt text from the template above into the media library for every image that has none. Existing alt text is not changed.',
					'wpinterface-seo'
				) }
			>
				<BulkAlt dirty={ draft.dirty } />
			</Section>

			<Section title={ __( 'Media pages and files', 'wpinterface-seo' ) }>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Attachment pages', 'wpinterface-seo' ) }
					help={ __(
						'WordPress creates a thin page for every upload. Redirecting avoids low-quality pages in search results.',
						'wpinterface-seo'
					) }
					value={ draft.get( 'attachment_redirect', 'file' ) }
					options={ [
						{
							value: 'file',
							label: __(
								'Redirect to the file itself',
								'wpinterface-seo'
							),
						},
						{
							value: 'parent',
							label: __(
								'Redirect to the post the file is attached to',
								'wpinterface-seo'
							),
						},
						{
							value: 'none',
							label: __(
								'Keep attachment pages',
								'wpinterface-seo'
							),
						},
					] }
					onChange={ ( value ) =>
						draft.set( 'attachment_redirect', value )
					}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Clean up file names on upload',
						'wpinterface-seo'
					) }
					help={ __(
						'“IMG_2041 Final (1).JPG” becomes “img-2041-final-1.jpg”: lowercase, dashes, no accents.',
						'wpinterface-seo'
					) }
					checked={ !! draft.get( 'clean_filenames', false ) }
					onChange={ ( value ) =>
						draft.set( 'clean_filenames', value )
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
