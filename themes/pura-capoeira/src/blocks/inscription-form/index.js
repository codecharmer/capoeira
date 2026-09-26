import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

const FIELDS = [
	[ 'modeNewTitle', 'Alumno nuevo — título' ],
	[ 'modeNewNote', 'Alumno nuevo — nota' ],
	[ 'modeMemberTitle', 'Alumno existente — título' ],
	[ 'modeMemberNote', 'Alumno existente — nota' ],
	[ 'groupAdultTitle', 'Adultos — título' ],
	[ 'groupAdultNote', 'Adultos — nota' ],
	[ 'groupKidsTitle', 'Niños — título' ],
	[ 'groupKidsNote', 'Niños — nota' ],
];

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Textos', 'pura' ) }>
					{ FIELDS.map( ( [ key, label ] ) => (
						<TextControl
							key={ key }
							label={ label }
							value={ attributes[ key ] }
							onChange={ ( value ) =>
								setAttributes( { [ key ]: value } )
							}
						/>
					) ) }
					<TextareaControl
						label={ __( 'Nota al pie', 'pura' ) }
						value={ attributes.footnote }
						onChange={ ( footnote ) =>
							setAttributes( { footnote } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
