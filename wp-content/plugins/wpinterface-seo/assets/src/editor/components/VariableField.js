/**
 * Text field for templates, with an "Insert variable" menu.
 */
import {
	BaseControl,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	useBaseControlProps,
} from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import config from '../config';
import './variable-field.scss';

const COMMON = [
	'title',
	'sitename',
	'sep',
	'excerpt',
	'primary_category',
	'author',
	'date',
	'currentyear',
];

export default function VariableField( {
	label,
	value,
	onChange,
	placeholder,
	help,
	multiline = false,
	variables = config.variables || [],
} ) {
	const ref = useRef();
	const { baseControlProps, controlProps } = useBaseControlProps( {
		label,
		help,
	} );

	const insert = ( name ) => {
		const token = `%${ name }%`;
		const input = ref.current;
		const current = value || '';

		if ( input && typeof input.selectionStart === 'number' ) {
			const start = input.selectionStart;
			const end = input.selectionEnd;
			const before = current.slice( 0, start );
			const after = current.slice( end );
			const spaced = `${
				before && ! before.endsWith( ' ' ) ? ' ' : ''
			}${ token }${ after.startsWith( ' ' ) ? '' : ' ' }`;
			onChange(
				`${ before }${ spaced }${ after }`.replace( /\s+$/, '' )
			);
		} else {
			onChange( `${ current } ${ token }`.trim() );
		}
	};

	const sorted = [
		...variables.filter( ( item ) => COMMON.includes( item.name ) ),
		...variables.filter( ( item ) => ! COMMON.includes( item.name ) ),
	];

	const Tag = multiline ? 'textarea' : 'input';

	return (
		<BaseControl { ...baseControlProps } __nextHasNoMarginBottom>
			<div className="wpiseo-variable-field">
				<Tag
					{ ...controlProps }
					ref={ ref }
					className={ `components-text-control__input wpiseo-variable-field__input${
						multiline ? ' is-multiline' : ''
					}` }
					type={ multiline ? undefined : 'text' }
					rows={ multiline ? 3 : undefined }
					value={ value || '' }
					placeholder={ placeholder }
					onChange={ ( event ) => onChange( event.target.value ) }
				/>
				<DropdownMenu
					icon="plus-alt2"
					label={ __( 'Insert variable', 'wpinterface-seo' ) }
					className="wpiseo-variable-field__menu"
					popoverProps={ { placement: 'bottom-end' } }
				>
					{ ( { onClose } ) => (
						<MenuGroup
							label={ __( 'Variables', 'wpinterface-seo' ) }
							className="wpiseo-variable-menu"
						>
							{ sorted.map( ( variable ) => (
								<MenuItem
									key={ variable.name }
									info={ variable.example }
									onClick={ () => {
										insert( variable.name );
										onClose();
									} }
								>
									{ variable.label }
								</MenuItem>
							) ) }
						</MenuGroup>
					) }
				</DropdownMenu>
			</div>
		</BaseControl>
	);
}
