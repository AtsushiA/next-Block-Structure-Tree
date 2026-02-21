import { registerPlugin } from '@wordpress/plugins';
import { BlockSettingsMenuControls } from '@wordpress/block-editor';
import { MenuItem } from '@wordpress/components';
import { select, dispatch } from '@wordpress/data';
import { getBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Recursively build tree lines for a block's inner blocks.
 *
 * @param {Object}  block   Block object containing innerBlocks.
 * @param {string}  prefix  Current line prefix (tree drawing characters).
 * @param {boolean} isLast  Whether this block is the last sibling.
 * @returns {string[]} Array of tree lines.
 */
function buildTree( block, prefix, isLast ) {
	const blockType = getBlockType( block.name );
	const title = blockType?.title ?? block.name;
	const connector = isLast ? '└── ' : '├── ';
	const lines = [ prefix + connector + title ];

	const children = block.innerBlocks ?? [];
	children.forEach( ( child, i, arr ) => {
		const childPrefix = prefix + ( isLast ? '    ' : '│   ' );
		lines.push( ...buildTree( child, childPrefix, i === arr.length - 1 ) );
	} );

	return lines;
}

/**
 * Generate a tree-formatted string from a root block.
 *
 * @param {Object} rootBlock The root block object.
 * @returns {string} Tree-formatted string.
 */
function generateTree( rootBlock ) {
	const blockType = getBlockType( rootBlock.name );
	const title = blockType?.title ?? rootBlock.name;

	const lines = [ title ];

	const children = rootBlock.innerBlocks ?? [];
	children.forEach( ( child, i, arr ) => {
		lines.push( ...buildTree( child, '', i === arr.length - 1 ) );
	} );

	return lines.join( '\n' );
}

/**
 * Write text to the clipboard.
 * Falls back to document.execCommand for non-secure contexts (http://*.local etc.).
 *
 * @param {string} text Text to copy.
 * @returns {Promise<void>}
 */
async function writeToClipboard( text ) {
	if ( navigator.clipboard?.writeText ) {
		await navigator.clipboard.writeText( text );
		return;
	}

	// Fallback: execCommand('copy') — works in non-secure contexts.
	const textarea = document.createElement( 'textarea' );
	textarea.value = text;
	textarea.style.cssText =
		'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
	document.body.appendChild( textarea );
	textarea.focus();
	textarea.select();
	const ok = document.execCommand( 'copy' );
	document.body.removeChild( textarea );
	if ( ! ok ) {
		throw new Error( 'execCommand copy failed' );
	}
}

/**
 * Copy the block structure tree for the given client IDs to the clipboard.
 *
 * @param {string[]|undefined} selectedClientIds Array of selected block client IDs.
 */
async function handleCopy( selectedClientIds ) {
	const { getBlock, getSelectedBlockClientIds } = select( 'core/block-editor' );
	const { createNotice } = dispatch( 'core/notices' );

	// Fallback: use store when render-prop doesn't supply ids.
	const ids =
		Array.isArray( selectedClientIds ) && selectedClientIds.length
			? selectedClientIds
			: getSelectedBlockClientIds();

	const rootBlocks = ids
		.map( ( clientId ) => getBlock( clientId ) )
		.filter( Boolean );

	if ( ! rootBlocks.length ) {
		return;
	}

	const text = rootBlocks
		.map( ( block, i, arr ) => {
			const label =
				arr.length > 1 ? `[選択ブロック ${ i + 1 }] ` : '';
			return label + generateTree( block );
		} )
		.join( '\n\n' );

	try {
		await writeToClipboard( text );
		createNotice(
			'success',
			__( 'ブロック構造をコピーしました', 'next-bst' ),
			{ type: 'snackbar', isDismissible: true }
		);
	} catch {
		createNotice(
			'error',
			__( 'クリップボードへのコピーに失敗しました', 'next-bst' ),
			{ type: 'snackbar', isDismissible: true }
		);
	}
}

/**
 * Plugin component: renders the menu item inside the block settings menu.
 */
function NextBSTPlugin() {
	return (
		<BlockSettingsMenuControls>
			{ ( { selectedClientIds } ) => (
				<MenuItem
					onClick={ () => handleCopy( selectedClientIds ) }
				>
					{ __( 'Copy Block-Structure-Tree', 'next-bst' ) }
				</MenuItem>
			) }
		</BlockSettingsMenuControls>
	);
}

registerPlugin( 'next-bst', {
	render: NextBSTPlugin,
} );
