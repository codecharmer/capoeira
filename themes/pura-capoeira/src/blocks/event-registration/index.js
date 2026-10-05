import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { eventSlug, eventName, days, askShirtSize, footnote } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Evento', 'pura' ) }>
					<TextControl
						label={ __( 'Nombre del evento', 'pura' ) }
						value={ eventName }
						onChange={ ( value ) =>
							setAttributes( { eventName: value } )
						}
					/>
					<TextControl
						label={ __( 'Identificador (sin espacios)', 'pura' ) }
						help={ __(
							'Agrupa los registros en el panel. Solo minúsculas, números y guiones.',
							'pura'
						) }
						value={ eventSlug }
						onChange={ ( value ) =>
							setAttributes( {
								eventSlug: value
									.toLowerCase()
									.replace( /[^a-z0-9-]+/g, '-' ),
							} )
						}
					/>
					<TextControl
						label={ __( 'Días (separados por |)', 'pura' ) }
						value={ days }
						onChange={ ( value ) =>
							setAttributes( { days: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Preguntar talla de playera', 'pura' ) }
						checked={ askShirtSize }
						onChange={ ( value ) =>
							setAttributes( { askShirtSize: value } )
						}
					/>
					<TextareaControl
						label={ __( 'Nota al pie', 'pura' ) }
						value={ footnote }
						onChange={ ( value ) =>
							setAttributes( { footnote: value } )
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
