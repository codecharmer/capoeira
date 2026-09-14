import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

const KEYS = [
	{ value: 'month', label: 'Mensualidad' },
	{ value: 'inscription', label: 'Inscripción / uniforme' },
	{ value: 'trial', label: 'Clase de prueba' },
	{ value: 'inscription_month', label: 'Inscripción + primer mes' },
	{ value: 'quarter', label: 'Paquete trimestral' },
	{ value: 'year', label: 'Paquete anual' },
];

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Precio', 'pura' ) }>
					<SelectControl
						label={ __( 'Concepto', 'pura' ) }
						value={ attributes.key }
						options={ KEYS }
						onChange={ ( key ) => setAttributes( { key } ) }
					/>
					<SelectControl
						label={ __( 'Grupo', 'pura' ) }
						value={ attributes.group }
						options={ [
							{ value: 'adult', label: __( 'Adultos', 'pura' ) },
							{ value: 'kids', label: __( 'Niños', 'pura' ) },
						] }
						onChange={ ( group ) => setAttributes( { group } ) }
					/>
					<TextControl
						label={ __( 'Sufijo', 'pura' ) }
						value={ attributes.suffix }
						onChange={ ( suffix ) => setAttributes( { suffix } ) }
					/>
					<SelectControl
						label={ __( 'Etiqueta HTML', 'pura' ) }
						value={ attributes.tagName }
						options={ [
							{ value: 'div', label: 'div' },
							{ value: 'span', label: 'span' },
							{ value: 'p', label: 'p' },
						] }
						onChange={ ( tagName ) => setAttributes( { tagName } ) }
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
