/**
 * Italian data for readability checks.
 */
import { vowelGroups } from '../text';

// Franchina & Vacca (1986): 217 − 1.3 × words/sentence − 60 × syllables/word.
export const flesch = [ 217, 1.3, 60 ];

export const syllables = ( word ) => vowelGroups( word, 'aeiouàèéìíòóùú' );

export const transitionWords = [
	'ad esempio',
	'allo stesso modo',
	'allora',
	'anche',
	'anzi',
	'benché',
	'cioè',
	'comunque',
	'così',
	"d'altra parte",
	'da un lato',
	"dall'altro lato",
	'di conseguenza',
	'dopo',
	'dunque',
	'finalmente',
	'in altre parole',
	'in conclusione',
	'in particolare',
	'in primo luogo',
	'in realtà',
	'in sintesi',
	'infatti',
	'infine',
	'innanzitutto',
	'inoltre',
	'insomma',
	'invece',
	'ma',
	'mentre',
	'nonostante',
	'oltre a',
	'ovvero',
	'per esempio',
	'perché',
	'perciò',
	'però',
	'pertanto',
	'poi',
	'prima',
	'quindi',
	'sebbene',
	'soprattutto',
	'successivamente',
	'tuttavia',
];
