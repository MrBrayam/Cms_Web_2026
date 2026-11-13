/**
 * Content analysis: SEO and readability checks.
 *
 * Runs entirely in the browser. Input is plain data so the engine can be
 * unit-tested and reused (editor sidebar, classic metabox, future bulk tools).
 */
import { __, _n, sprintf } from '@wordpress/i18n';

import * as de from './languages/de';
import * as en from './languages/en';
import * as es from './languages/es';
import * as fr from './languages/fr';
import * as hi from './languages/hi';
import * as it from './languages/it';
import * as ne from './languages/ne';
import * as nl from './languages/nl';
import * as pt from './languages/pt';
import {
	analyzeHtml,
	countPhrase,
	matchPhrase,
	normalize,
	sentences,
	syllables,
	words,
} from './text';

const POINTS = { good: 9, ok: 6, bad: 3 };

/**
 * Readability data per language. Each provides transitionWords; flesch
 * (formula weights) and syllables enable reading ease; passiveAuxiliaries,
 * irregularParticiples and nonParticiples enable the passive voice check.
 */
const LANGUAGES = { de, en, es, fr, hi, it, ne, nl, pt };

function result( id, status, message ) {
	return { id, status, message };
}

/**
 * Score 0–100 from check statuses.
 *
 * @param {Array} checks Checks.
 * @return {number} Score.
 */
export function score( checks ) {
	const scored = checks.filter( ( check ) => POINTS[ check.status ] );

	if ( ! scored.length ) {
		return 0;
	}

	const total = scored.reduce(
		( sum, check ) => sum + POINTS[ check.status ],
		0
	);

	return Math.round( ( total / ( scored.length * 9 ) ) * 100 );
}

/**
 * Score band used for colours: good ≥ 70, ok ≥ 40, bad otherwise.
 *
 * @param {number|null} value Score.
 * @return {string} Band.
 */
export function band( value ) {
	if ( value === null || value === undefined ) {
		return 'none';
	}
	if ( value >= 70 ) {
		return 'good';
	}
	return value >= 40 ? 'ok' : 'bad';
}

function keywordChecks( keyword, data, primary ) {
	const checks = [];
	const prefix = primary ? '' : 'extra-';
	const title = matchPhrase( data.title, keyword );

	if ( title === 'exact' ) {
		const atStart = normalize( data.title ).startsWith(
			normalize( keyword )
		);
		checks.push(
			result(
				`${ prefix }keyword-title`,
				'good',
				atStart
					? __(
							'The focus keyword appears at the start of the SEO title.',
							'wpinterface-seo'
					  )
					: __(
							'The SEO title contains the focus keyword. Moving it closer to the start can help.',
							'wpinterface-seo'
					  )
			)
		);
	} else {
		checks.push(
			result(
				`${ prefix }keyword-title`,
				title === 'words' ? 'ok' : 'bad',
				title === 'words'
					? __(
							'The SEO title contains all words of the focus keyword, but not as an exact phrase.',
							'wpinterface-seo'
					  )
					: __(
							'Add the focus keyword to the SEO title.',
							'wpinterface-seo'
					  )
			)
		);
	}

	if ( data.description ) {
		const description = matchPhrase( data.description, keyword );
		checks.push(
			result(
				`${ prefix }keyword-description`,
				description === 'exact' ? 'good' : 'ok',
				description === 'exact'
					? __(
							'The meta description contains the focus keyword.',
							'wpinterface-seo'
					  )
					: __(
							'Use the focus keyword in the meta description.',
							'wpinterface-seo'
					  )
			)
		);
	}

	const intro = data.html.paragraphs[ 0 ] || '';
	const introMatch = matchPhrase( intro, keyword );
	let introMessage = __(
		'Use the focus keyword in the first paragraph.',
		'wpinterface-seo'
	);
	if ( introMatch === 'exact' ) {
		introMessage = __(
			'The focus keyword appears in the first paragraph.',
			'wpinterface-seo'
		);
	} else if ( introMatch === 'words' ) {
		introMessage = __(
			'The first paragraph contains all words of the focus keyword, but not as an exact phrase.',
			'wpinterface-seo'
		);
	}
	checks.push(
		result(
			`${ prefix }keyword-intro`,
			{ exact: 'good', words: 'ok' }[ introMatch ] || 'bad',
			introMessage
		)
	);

	if ( ! primary ) {
		const occurrences = countPhrase( data.html.text, keyword );
		checks.push(
			result(
				`${ prefix }keyword-content`,
				occurrences > 0 ? 'good' : 'ok',
				occurrences > 0
					? __(
							'This keyword appears in the content.',
							'wpinterface-seo'
					  )
					: __(
							'This keyword does not appear in the content yet.',
							'wpinterface-seo'
					  )
			)
		);
		return checks;
	}

	const slugText = ( data.slug || '' ).replace( /[-_]+/g, ' ' );
	// Slugs have no hyphens left, so "pour-over coffee" must match "pour over coffee".
	const slug = matchPhrase( slugText, keyword.replace( /[-_]+/g, ' ' ) );
	checks.push(
		result(
			'keyword-slug',
			slug === 'none' ? 'ok' : 'good',
			slug === 'none'
				? __(
						'Consider using the focus keyword in the URL slug.',
						'wpinterface-seo'
				  )
				: __(
						'The URL slug contains the focus keyword.',
						'wpinterface-seo'
				  )
		)
	);

	const totalWords = data.wordCount;
	const occurrences = countPhrase( data.html.text, keyword );
	const keywordWords = Math.max( 1, words( keyword ).length );
	const density =
		totalWords > 0 ? ( occurrences * keywordWords * 100 ) / totalWords : 0;

	let densityStatus = 'good';
	if ( occurrences === 0 ) {
		densityStatus = 'bad';
	} else if ( density < 0.5 ) {
		densityStatus = 'ok';
	} else if ( density > 3 ) {
		densityStatus = 'bad';
	}

	checks.push(
		result(
			'keyword-density',
			densityStatus,
			sprintf(
				/* translators: 1: number of times, 2: density percentage. */
				_n(
					'The focus keyword appears %1$d time (density %2$s%%). Aim for 0.5–3%%.',
					'The focus keyword appears %1$d times (density %2$s%%). Aim for 0.5–3%%.',
					occurrences,
					'wpinterface-seo'
				),
				occurrences,
				density.toFixed( 1 )
			)
		)
	);

	const subheadings = data.html.headings.filter(
		( heading ) => heading.level === 2 || heading.level === 3
	);
	if ( subheadings.length ) {
		const withKeyword = subheadings.filter(
			( heading ) => matchPhrase( heading.text, keyword ) !== 'none'
		).length;
		checks.push(
			result(
				'keyword-subheadings',
				withKeyword > 0 ? 'good' : 'ok',
				withKeyword > 0
					? __(
							'The focus keyword is used in a subheading.',
							'wpinterface-seo'
					  )
					: __(
							'Use the focus keyword (or its words) in at least one H2 or H3 subheading.',
							'wpinterface-seo'
					  )
			)
		);
	}

	if ( data.html.images.length ) {
		const withAlt = data.html.images.some(
			( image ) => matchPhrase( image.alt, keyword ) !== 'none'
		);
		checks.push(
			result(
				'keyword-image-alt',
				withAlt ? 'good' : 'ok',
				withAlt
					? __(
							'An image alt text contains the focus keyword.',
							'wpinterface-seo'
					  )
					: __(
							'Add the focus keyword to the alt text of a relevant image.',
							'wpinterface-seo'
					  )
			)
		);
	}

	if ( keywordWords > 4 ) {
		checks.push(
			result(
				'keyword-length',
				'ok',
				__(
					'The focus keyword is long. Shorter keyphrases (up to 4 words) are easier to rank for.',
					'wpinterface-seo'
				)
			)
		);
	}

	return checks;
}

function generalChecks( data, measure ) {
	const checks = [];
	const count = data.wordCount;

	let lengthStatus = 'good';
	if ( count < 200 ) {
		lengthStatus = 'bad';
	} else if ( count < 300 ) {
		lengthStatus = 'ok';
	}
	checks.push(
		result(
			'content-length',
			lengthStatus,
			sprintf(
				/* translators: %d: number of words. */
				_n(
					'The content has %d word. At least 300 is recommended.',
					'The content has %d words. At least 300 is recommended.',
					count,
					'wpinterface-seo'
				),
				count
			)
		)
	);

	const width = measure( data.title );
	let titleStatus = 'good';
	let titleMessage = __(
		'The SEO title has a good length.',
		'wpinterface-seo'
	);
	if ( ! data.title ) {
		titleStatus = 'bad';
		titleMessage = __( 'The SEO title is empty.', 'wpinterface-seo' );
	} else if ( width > 600 ) {
		titleStatus = 'ok';
		titleMessage = __(
			'The SEO title is too wide and will be cut off in search results.',
			'wpinterface-seo'
		);
	} else if ( width < 200 ) {
		titleStatus = 'ok';
		titleMessage = __(
			'The SEO title is short. Use the space to describe the page.',
			'wpinterface-seo'
		);
	}
	checks.push( result( 'title-width', titleStatus, titleMessage ) );

	const descriptionLength = ( data.description || '' ).length;
	let descriptionStatus = 'good';
	let descriptionMessage = __(
		'The meta description has a good length.',
		'wpinterface-seo'
	);
	if ( ! descriptionLength ) {
		descriptionStatus = 'bad';
		descriptionMessage = __(
			'No meta description: search engines will pick text from the page instead.',
			'wpinterface-seo'
		);
	} else if ( descriptionLength < 120 ) {
		descriptionStatus = 'ok';
		descriptionMessage = __(
			'The meta description is short (under 120 characters).',
			'wpinterface-seo'
		);
	} else if ( descriptionLength > 160 ) {
		descriptionStatus = 'ok';
		descriptionMessage = __(
			'The meta description is over 160 characters and may be cut off.',
			'wpinterface-seo'
		);
	}
	checks.push(
		result( 'description-length', descriptionStatus, descriptionMessage )
	);

	const internal = data.html.links.filter( ( link ) => link.internal ).length;
	checks.push(
		result(
			'internal-links',
			internal > 0 ? 'good' : 'ok',
			internal > 0
				? __(
						'The content links to other pages of this site.',
						'wpinterface-seo'
				  )
				: __(
						'Add links to related pages on this site.',
						'wpinterface-seo'
				  )
		)
	);

	const external = data.html.links.filter( ( link ) => ! link.internal );
	checks.push(
		result(
			'external-links',
			external.length > 0 ? 'good' : 'ok',
			external.length > 0
				? __(
						'The content links to external sources.',
						'wpinterface-seo'
				  )
				: __(
						'Consider linking to a relevant, trustworthy external source.',
						'wpinterface-seo'
				  )
		)
	);

	const images = data.html.images;
	checks.push(
		result(
			'images',
			images.length > 0 ? 'good' : 'ok',
			images.length > 0
				? __( 'The content contains images.', 'wpinterface-seo' )
				: __(
						'Add at least one image to support the text.',
						'wpinterface-seo'
				  )
		)
	);

	const missingAlt = images.filter( ( image ) => ! image.alt ).length;
	if ( missingAlt ) {
		checks.push(
			result(
				'images-alt',
				'ok',
				sprintf(
					/* translators: %d: number of images. */
					_n(
						'%d image has no alt text.',
						'%d images have no alt text.',
						missingAlt,
						'wpinterface-seo'
					),
					missingAlt
				)
			)
		);
	}

	if ( data.html.headings.some( ( heading ) => heading.level === 1 ) ) {
		checks.push(
			result(
				'single-h1',
				'ok',
				__(
					'The content contains an H1 heading. The post title is usually the page H1, so use H2 for sections.',
					'wpinterface-seo'
				)
			)
		);
	}

	return checks;
}

function readabilityChecks( data, language ) {
	const checks = [];
	const allSentences = sentences( data.html.text );
	const sentenceCount = allSentences.length;

	if ( data.wordCount < 50 || sentenceCount < 3 ) {
		return [
			result(
				'readability-short',
				'ok',
				__(
					'Write at least 50 words to get readability feedback.',
					'wpinterface-seo'
				)
			),
		];
	}

	const sentenceWords = allSentences.map( ( sentence ) => words( sentence ) );

	if ( language?.flesch ) {
		const [ base, sentenceWeight, syllableWeight ] = language.flesch;
		const countSyllables = language.syllables || syllables;
		const totalSyllables = sentenceWords
			.flat()
			.reduce( ( sum, word ) => sum + countSyllables( word ), 0 );
		const flesch =
			base -
			sentenceWeight * ( data.wordCount / sentenceCount ) -
			syllableWeight * ( totalSyllables / Math.max( 1, data.wordCount ) );
		const rounded = Math.round( flesch );
		let status = 'good';
		if ( flesch < 50 ) {
			status = 'bad';
		} else if ( flesch < 60 ) {
			status = 'ok';
		}
		checks.push(
			result(
				'flesch',
				status,
				sprintf(
					/* translators: %d: Flesch reading ease score. */
					__(
						'Flesch reading ease: %d (60 or higher is easy to read).',
						'wpinterface-seo'
					),
					rounded
				)
			)
		);
	}

	const longSentences = sentenceWords.filter(
		( list ) => list.length > 20
	).length;
	const longShare = ( longSentences / sentenceCount ) * 100;
	let sentenceStatus = 'good';
	if ( longShare > 30 ) {
		sentenceStatus = 'bad';
	} else if ( longShare > 25 ) {
		sentenceStatus = 'ok';
	}
	checks.push(
		result(
			'sentence-length',
			sentenceStatus,
			sprintf(
				/* translators: %d: percentage of sentences. */
				__(
					'%d%% of sentences are longer than 20 words (recommended: at most 25%%).',
					'wpinterface-seo'
				),
				Math.round( longShare )
			)
		)
	);

	const longParagraphs = data.html.paragraphs.filter(
		( paragraph ) => words( paragraph ).length > 150
	).length;
	checks.push(
		result(
			'paragraph-length',
			longParagraphs ? 'ok' : 'good',
			longParagraphs
				? sprintf(
						/* translators: %d: number of paragraphs. */
						_n(
							'%d paragraph is longer than 150 words. Split it up.',
							'%d paragraphs are longer than 150 words. Split them up.',
							longParagraphs,
							'wpinterface-seo'
						),
						longParagraphs
				  )
				: __( 'Paragraphs have a good length.', 'wpinterface-seo' )
		)
	);

	if ( data.wordCount > 300 ) {
		let run = 0;
		let longest = 0;
		data.html.blocks.forEach( ( block ) => {
			if ( block.heading ) {
				run = 0;
			} else {
				run += words( block.text ).length;
				longest = Math.max( longest, run );
			}
		} );
		let status = 'good';
		if ( longest > 350 ) {
			status = 'bad';
		} else if ( longest > 300 ) {
			status = 'ok';
		}
		checks.push(
			result(
				'subheadings',
				status,
				status === 'good'
					? __(
							'Subheadings break up the text well.',
							'wpinterface-seo'
					  )
					: __(
							'Some sections run over 300 words without a subheading.',
							'wpinterface-seo'
					  )
			)
		);
	}

	if ( language?.passiveAuxiliaries ) {
		const auxiliaries = new Set( language.passiveAuxiliaries );
		const irregular = new Set( language.irregularParticiples );
		const notParticiple = new Set( language.nonParticiples );
		const passive = sentenceWords.filter( ( list ) =>
			list.some( ( word, index ) => {
				const next = ( list[ index + 1 ] || '' ).toLowerCase();
				return (
					auxiliaries.has( word.toLowerCase() ) &&
					( irregular.has( next ) ||
						( next.length > 4 &&
							next.endsWith( 'ed' ) &&
							! notParticiple.has( next ) ) )
				);
			} )
		).length;
		const passiveShare = ( passive / sentenceCount ) * 100;
		let passiveStatus = 'good';
		if ( passiveShare > 15 ) {
			passiveStatus = 'bad';
		} else if ( passiveShare > 10 ) {
			passiveStatus = 'ok';
		}
		checks.push(
			result(
				'passive-voice',
				passiveStatus,
				sprintf(
					/* translators: %d: percentage of sentences. */
					__(
						'%d%% of sentences use the passive voice (recommended: at most 10%%).',
						'wpinterface-seo'
					),
					Math.round( passiveShare )
				)
			)
		);
	}

	if ( language?.transitionWords ) {
		const transitions = language.transitionWords;
		const withTransition = allSentences.filter( ( sentence ) => {
			const text = ` ${ normalize( sentence ) } `;
			return transitions.some(
				( word ) =>
					text.includes( ` ${ word } ` ) ||
					text.includes( ` ${ word }, ` )
			);
		} ).length;
		const transitionShare = ( withTransition / sentenceCount ) * 100;
		let transitionStatus = 'good';
		if ( transitionShare < 20 ) {
			transitionStatus = 'bad';
		} else if ( transitionShare < 30 ) {
			transitionStatus = 'ok';
		}
		checks.push(
			result(
				'transition-words',
				transitionStatus,
				sprintf(
					/* translators: %d: percentage of sentences. */
					__(
						'%d%% of sentences contain transition words (recommended: at least 30%%).',
						'wpinterface-seo'
					),
					Math.round( transitionShare )
				)
			)
		);
	}

	let repeated = 0;
	for ( let i = 2; i < sentenceWords.length; i++ ) {
		const first = ( list ) => ( list[ 0 ] || '' ).toLowerCase();
		if (
			first( sentenceWords[ i ] ) &&
			first( sentenceWords[ i ] ) === first( sentenceWords[ i - 1 ] ) &&
			first( sentenceWords[ i ] ) === first( sentenceWords[ i - 2 ] )
		) {
			repeated++;
		}
	}
	checks.push(
		result(
			'sentence-beginnings',
			repeated ? 'ok' : 'good',
			repeated
				? __(
						'Three or more consecutive sentences start with the same word. Vary your sentence openings.',
						'wpinterface-seo'
				  )
				: __( 'Sentence openings are varied.', 'wpinterface-seo' )
		)
	);

	return checks;
}

/**
 * Approximate pixel width of a title in Google's desktop result (Arial 20px).
 *
 * @param {string} text Title.
 * @return {number} Width in pixels.
 */
export function measureTitle( text ) {
	if ( typeof document !== 'undefined' && document.createElement ) {
		const canvas =
			measureTitle.canvas ||
			( measureTitle.canvas = document.createElement( 'canvas' ) );
		const context = canvas.getContext && canvas.getContext( '2d' );
		if ( context ) {
			context.font = '20px Arial';
			return context.measureText( text || '' ).width;
		}
	}
	return ( text || '' ).length * 9.5;
}

/**
 * Runs the analysis.
 *
 * @param {Object}   input               Input.
 * @param {string}   input.title         Rendered SEO title.
 * @param {string}   input.description   Rendered meta description.
 * @param {string}   input.slug          URL slug.
 * @param {string}   input.content       Post content (HTML).
 * @param {string[]} input.keywords      Focus keywords; the first is primary.
 * @param {string}   input.locale        Content locale, e.g. en_US.
 * @param {string}   input.host          Site host.
 * @param {Function} [input.measure]     Title width function.
 * @param {boolean}  [input.readability] Whether to run readability checks.
 * @return {Object} { seo, readability, seoScore, readabilityScore, keywords }.
 */
export function analyze( input ) {
	const html = analyzeHtml( input.content || '', input.host || '' );
	const data = {
		title: input.title || '',
		description: input.description || '',
		slug: input.slug || '',
		html,
		wordCount: words( html.text ).length,
	};
	const language = LANGUAGES[ ( input.locale || '' ).slice( 0, 2 ) ] || null;
	const keywords = ( input.keywords || [] ).filter( Boolean );
	const measure = input.measure || measureTitle;

	const seo = keywords.length
		? keywordChecks( keywords[ 0 ], data, true )
		: [
				result(
					'keyword-missing',
					'bad',
					__(
						'Set a focus keyword to get keyword-specific checks.',
						'wpinterface-seo'
					)
				),
		  ];
	seo.push( ...generalChecks( data, measure ) );

	const extra = keywords.slice( 1 ).map( ( keyword ) => {
		const checks = keywordChecks( keyword, data, false );
		return { keyword, checks, score: score( checks ) };
	} );

	const readability =
		input.readability === false ? [] : readabilityChecks( data, language );

	if ( readability.length > 1 && ! language?.passiveAuxiliaries ) {
		let message = __(
			'The passive voice check is not available for this language yet.',
			'wpinterface-seo'
		);

		if ( ! language ) {
			message = __(
				'Reading ease, passive voice and transition word checks are not available for this language yet.',
				'wpinterface-seo'
			);
		} else if ( ! language.flesch ) {
			message = __(
				'Reading ease and passive voice checks are not available for this language yet.',
				'wpinterface-seo'
			);
		}

		readability.push( result( 'language-support', 'info', message ) );
	}

	return {
		seo,
		readability,
		seoScore: score( seo ),
		readabilityScore: readability.length ? score( readability ) : null,
		keywords: extra,
		wordCount: data.wordCount,
	};
}
