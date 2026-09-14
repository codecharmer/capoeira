import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Galería', 'pura' ) }>
					<ToggleControl
						label={ __( 'Mostrar filtros por categoría', 'pura' ) }
						checked={ attributes.showFilters }
						onChange={ ( showFilters ) => setAttributes( { showFilters } ) }
					/>
					<RangeControl
						label={ __( 'Máximo de videos', 'pura' ) }
						value={ attributes.limit }
						min={ 4 }
						max={ 120 }
						onChange={ ( limit ) => setAttributes( { limit } ) }
					/>
					<TextControl
						label={ __( 'Enlace al álbum completo', 'pura' ) }
						value={ attributes.fallbackUrl }
						onChange={ ( fallbackUrl ) => setAttributes( { fallbackUrl } ) }
					/>
					<TextControl
						label={ __( 'Texto del botón del álbum', 'pura' ) }
						value={ attributes.fallbackText }
						onChange={ ( fallbackText ) => setAttributes( { fallbackText } ) }
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
