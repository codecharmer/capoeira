import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Textos', 'pura' ) }>
					<TextareaControl
						label={ __( 'Introducción (sin sesión)', 'pura' ) }
						value={ attributes.introLoggedOut }
						onChange={ ( introLoggedOut ) =>
							setAttributes( { introLoggedOut } )
						}
					/>
					<TextareaControl
						label={ __( 'Cuenta sin ficha de alumno', 'pura' ) }
						value={ attributes.noStudentText }
						onChange={ ( noStudentText ) =>
							setAttributes( { noStudentText } )
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
