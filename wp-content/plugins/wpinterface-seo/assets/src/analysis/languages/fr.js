/**
 * French data for readability checks.
 */
import { vowelGroups } from '../text';

// Kandel & Moles (1958): 207 − 1.015 × words/sentence − 73.6 × syllables/word.
export const flesch = [ 207, 1.015, 73.6 ];

export const syllables = ( word ) =>
	vowelGroups( word, 'aeiouyàâäéèêëîïôöûùüÿœæ', true );

export const transitionWords = [
	'à cause de',
	'ainsi',
	'alors',
	'après',
	'au contraire',
	'aussi',
	'autrement dit',
	'bien que',
	'bref',
	'car',
	'cependant',
	"c'est-à-dire",
	"d'abord",
	"d'ailleurs",
	'de même',
	'de plus',
	'deuxièmement',
	'donc',
	'du coup',
	'également',
	'en conclusion',
	'en effet',
	'en outre',
	'en particulier',
	'en résumé',
	'en revanche',
	'en somme',
	'enfin',
	'ensuite',
	'finalement',
	'grâce à',
	'mais',
	'malgré',
	'néanmoins',
	'notamment',
	'par ailleurs',
	'par conséquent',
	'par contre',
	'par exemple',
	'parce que',
	'pour conclure',
	'pourtant',
	'premièrement',
	'puis',
	'puisque',
	'tandis que',
	'toutefois',
];
