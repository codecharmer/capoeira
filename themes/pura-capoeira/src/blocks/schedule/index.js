import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Grupo', 'pura' ) }>
					<SelectControl
						label={ __( 'Grupo', 'pura' ) }
						value={ attributes.group }
						options={ [
							{ value: 'adult', label: __( 'Adultos', 'pura' ) },
							{ value: 'kids', label: __( 'Niños', 'pura' ) },
						] }
						onChange={ ( group ) => setAttributes( { group } ) }
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
