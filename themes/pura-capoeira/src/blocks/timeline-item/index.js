import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'tl-item' } );

	return (
		<div { ...blockProps }>
			<RichText
				tagName="span"
				className="tl-year"
				value={ attributes.year }
				onChange={ ( year ) => setAttributes( { year } ) }
				placeholder={ __( 'Año', 'pura' ) }
				allowedFormats={ [] }
				withoutInteractiveFormatting
			/>
			<RichText
				tagName="p"
				className="tl-text"
				value={ attributes.text }
				onChange={ ( text ) => setAttributes( { text } ) }
				placeholder={ __( 'Qué pasó ese año…', 'pura' ) }
				allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
			/>
		</div>
	);
}

function Save( { attributes } ) {
	const blockProps = useBlockProps.save( { className: 'tl-item reveal' } );

	return (
		<div { ...blockProps }>
			<RichText.Content
				tagName="span"
				className="tl-year"
				value={ attributes.year }
			/>
			<RichText.Content
				tagName="p"
				className="tl-text"
				value={ attributes.text }
			/>
		</div>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: Save } );
