/**
 * Dutch data for readability checks.
 */
import { vowelGroups } from '../text';

// Douma (1960): 206.835 − 0.93 × words/sentence − 77 × syllables/word.
export const flesch = [ 206.835, 0.93, 77 ];

export const syllables = ( word ) => vowelGroups( word, 'aeiouyáéèëïóöú' );

export const transitionWords = [
	'aangezien',
	'al met al',
	'bijvoorbeeld',
	'bovendien',
	'daardoor',
	'daarentegen',
	'daarna',
	'daarnaast',
	'daarom',
	'dan',
	'dus',
	'echter',
	'eerst',
	'evenals',
	'eveneens',
	'evenzo',
	'hierdoor',
	'hoewel',
	'immers',
	'in tegenstelling tot',
	'integendeel',
	'kortom',
	'maar',
	'met andere woorden',
	'met name',
	'nadat',
	'namelijk',
	'niettemin',
	'omdat',
	'ondanks',
	'ook',
	'samenvattend',
	'ten eerste',
	'ten slotte',
	'ten tweede',
	'tenslotte',
	'terwijl',
	'tevens',
	'toch',
	'tot slot',
	'uiteindelijk',
	'vervolgens',
	'want',
	'zoals',
	'zodat',
];
