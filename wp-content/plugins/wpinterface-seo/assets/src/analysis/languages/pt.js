/**
 * Portuguese data for readability checks.
 */
import { vowelGroups } from '../text';

// Martins et al. (1996): 248.835 − 1.015 × words/sentence − 84.6 × syllables/word.
export const flesch = [ 248.835, 1.015, 84.6 ];

export const syllables = ( word ) => vowelGroups( word, 'aeiouáàâãéêíóôõúü' );

export const transitionWords = [
	'ainda assim',
	'além de',
	'além disso',
	'apesar de',
	'assim',
	'assim como',
	'consequentemente',
	'contudo',
	'da mesma forma',
	'de fato',
	'de modo que',
	'depois',
	'dessa forma',
	'desse modo',
	'em conclusão',
	'em primeiro lugar',
	'em resumo',
	'em seguida',
	'embora',
	'enfim',
	'enquanto',
	'então',
	'entretanto',
	'finalmente',
	'igualmente',
	'isto é',
	'já que',
	'logo',
	'mas',
	'no entanto',
	'ou seja',
	'pois',
	'por conseguinte',
	'por exemplo',
	'por fim',
	'por isso',
	'por outro lado',
	'porém',
	'porque',
	'portanto',
	'primeiro',
	'sobretudo',
	'também',
	'todavia',
	'visto que',
];
