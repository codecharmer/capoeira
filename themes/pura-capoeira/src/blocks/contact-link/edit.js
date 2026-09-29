import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

const CHANNELS = [
	{ value: 'whatsapp', label: 'WhatsApp' },
	{ value: 'instagram', label: 'Instagram' },
	{ value: 'facebook', label: 'Facebook' },
	{ value: 'calendly_adults', label: 'Calendly (adultos)' },
	{ value: 'calendly_kids', label: 'Calendly (niños)' },
];

const VARIANTS = [
	{ value: 'link', label: __( 'Enlace', 'pura' ) },
	{ value: 'btn-primary', label: __( 'Botón amarillo', 'pura' ) },
	{ value: 'btn-secondary', label: __( 'Botón secundario', 'pura' ) },
	{ value: 'btn-whatsapp', label: __( 'Botón WhatsApp', 'pura' ) },
	{ value: 'btn-blue', label: __( 'Botón azul', 'pura' ) },
	{ value: 'btn-ghost', label: __( 'Botón enlace', 'pura' ) },
];

export default function Edit( { attributes, setAttributes } ) {
	const { channel, label, showValue, variant, message } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Enlace', 'pura' ) }>
					<SelectControl
						label={ __( 'Canal', 'pura' ) }
						value={ channel }
						options={ CHANNELS }
						onChange={ ( value ) =>
							setAttributes( { channel: value } )
						}
					/>
					<TextControl
						label={ __( 'Texto', 'pura' ) }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Mostrar el valor (número, usuario…)',
							'pura'
						) }
						checked={ showValue }
						onChange={ ( value ) =>
							setAttributes( { showValue: value } )
						}
					/>
					<SelectControl
						label={ __( 'Estilo', 'pura' ) }
						value={ variant }
						options={ VARIANTS }
						onChange={ ( value ) =>
							setAttributes( { variant: value } )
						}
					/>
					{ channel === 'whatsapp' && (
						<TextControl
							label={ __(
								'Mensaje prellenado (opcional)',
								'pura'
							) }
							value={ message }
							onChange={ ( value ) =>
								setAttributes( { message: value } )
							}
						/>
					) }
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
