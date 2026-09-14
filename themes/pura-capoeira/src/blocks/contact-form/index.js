import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Formulario', 'pura' ) }>
					<TextControl
						label={ __( 'Primera línea del mensaje', 'pura' ) }
						value={ attributes.intro }
						onChange={ ( intro ) => setAttributes( { intro } ) }
					/>
					<TextControl
						label={ __( 'Texto del botón', 'pura' ) }
						value={ attributes.submitLabel }
						onChange={ ( submitLabel ) => setAttributes( { submitLabel } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
