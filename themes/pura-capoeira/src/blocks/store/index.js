import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, TextareaControl, TextControl } from '@wordpress/components';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Textos', 'pura' ) }>
					<TextControl
						label={ __( 'Nota del catálogo', 'pura' ) }
						value={ attributes.note }
						onChange={ ( note ) => setAttributes( { note } ) }
					/>
					<TextareaControl
						label={ __( 'Nota al pie del carrito', 'pura' ) }
						value={ attributes.footnote }
						onChange={ ( footnote ) => setAttributes( { footnote } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<Placeholder
					icon="cart"
					label={ __( 'Tienda', 'pura' ) }
					instructions={ __( 'El catálogo, el carrito y el pago se cargan en el sitio. Configura Printful y Stripe en Pura Capoeira → Ajustes.', 'pura' ) }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
