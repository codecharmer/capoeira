import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Calendly', 'pura' ) }>
					<SelectControl
						label={ __( 'Grupo', 'pura' ) }
						value={ attributes.group }
						options={ [
							{ value: 'adult', label: __( 'Adultos', 'pura' ) },
							{ value: 'kids', label: __( 'Niños', 'pura' ) },
						] }
						onChange={ ( group ) => setAttributes( { group } ) }
					/>
					<RangeControl
						label={ __( 'Alto (px)', 'pura' ) }
						value={ attributes.height }
						min={ 400 }
						max={ 1200 }
						step={ 50 }
						onChange={ ( height ) => setAttributes( { height } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<Placeholder
					icon="clock"
					label={ __( 'Agenda Calendly', 'pura' ) }
					instructions={
						attributes.group === 'kids'
							? __(
									'Calendario de clase de prueba para niños (la URL viene de Ajustes).',
									'pura'
							  )
							: __(
									'Calendario de clase de prueba para adultos (la URL viene de Ajustes).',
									'pura'
							  )
					}
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
