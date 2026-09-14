import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import metadata from './block.json';

const TEMPLATE = [ [ 'pura/timeline-item', { year: '2005', text: '' } ] ];

function Edit() {
	const blockProps = useBlockProps( { className: 'timeline' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: [ 'pura/timeline-item' ],
		template: TEMPLATE,
		renderAppender: InnerBlocks.ButtonBlockAppender,
	} );

	return <div { ...innerBlocksProps } />;
}

function Save() {
	const blockProps = useBlockProps.save( { className: 'timeline' } );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );

	return <div { ...innerBlocksProps } />;
}

registerBlockType( metadata.name, { edit: Edit, save: Save } );
