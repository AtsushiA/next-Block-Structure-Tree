import { registerPlugin } from '@wordpress/plugins';
import { BlockSettingsMenuControls } from '@wordpress/block-editor';
import { MenuItem } from '@wordpress/components';
import { select, dispatch, resolveSelect } from '@wordpress/data';
import { getBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Recursively collect all core/block ref IDs from a block tree.
 *
 * @param {Object}  block Block object.
 * @param {Set}     refs  Accumulator set.
 * @returns {Set<number>} Set of ref IDs.
 */
function collectPatternRefs( block, refs = new Set() ) {
	if ( block.name === 'core/block' && block.attributes?.ref ) {
		refs.add( block.attributes.ref );
	}
	( block.innerBlocks ?? [] ).forEach( ( child ) =>
		collectPatternRefs( child, refs )
	);
	return refs;
}

/**
 * Get the display label for a block.
 *
 * - core/block (synced pattern): uses resolved pattern title from patternMap,
 *   falls back to block type title if not yet loaded.
 * - Other blocks: uses block type title, appending "[name]" if metadata.name is set.
 *
 * @param {Object}          block      Block object.
 * @param {Map<number, string>} patternMap Resolved pattern titles keyed by ref ID.
 * @returns {string} Display label.
 */
function getBlockLabel( block, patternMap ) {
	const blockType = getBlockType( block.name );
	const title = blockType?.title ?? block.name;

	// core/block = synced pattern: show "パターン [パターン名]".
	if ( block.name === 'core/block' ) {
		const ref = block.attributes?.ref;
		if ( ref ) {
			const patternTitle = patternMap?.get( ref );
			if ( patternTitle ) {
				return `${ title } [${ patternTitle }]`;
			}
		}
	}

	const metaName = block.attributes?.metadata?.name;
	return metaName ? `${ title } [${ metaName }]` : title;
}

/**
 * Recursively build tree lines for a block's inner blocks.
 *
 * @param {Object}              block      Block object.
 * @param {string}              prefix     Current line prefix.
 * @param {boolean}             isLast     Whether this block is the last sibling.
 * @param {Map<number, string>} patternMap Resolved pattern titles.
 * @returns {string[]} Array of tree lines.
 */
function buildTree( block, prefix, isLast, patternMap ) {
	const title = getBlockLabel( block, patternMap );
	const connector = isLast ? '└── ' : '├── ';
	const lines = [ prefix + connector + title ];

	const children = block.innerBlocks ?? [];
	children.forEach( ( child, i, arr ) => {
		const childPrefix = prefix + ( isLast ? '    ' : '│   ' );
		lines.push( ...buildTree( child, childPrefix, i === arr.length - 1, patternMap ) );
	} );

	return lines;
}

/**
 * Generate a tree-formatted string from a root block.
 *
 * @param {Object}              rootBlock  The root block object.
 * @param {Map<number, string>} patternMap Resolved pattern titles.
 * @returns {string} Tree-formatted string.
 */
function generateTree( rootBlock, patternMap ) {
	const lines = [ getBlockLabel( rootBlock, patternMap ) ];

	const children = rootBlock.innerBlocks ?? [];
	children.forEach( ( child, i, arr ) => {
		lines.push( ...buildTree( child, '', i === arr.length - 1, patternMap ) );
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
 * Resolves synced pattern (core/block) titles via the REST API before
 * generating the tree, so pattern names appear instead of generic "パターン".
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

	// Collect all synced pattern ref IDs from the selected block trees.
	const refs = new Set();
	rootBlocks.forEach( ( block ) => collectPatternRefs( block, refs ) );

	// Resolve pattern titles via core data store (REST API).
	const patternMap = new Map();
	if ( refs.size > 0 ) {
		await Promise.all(
			[ ...refs ].map( async ( ref ) => {
				const entity = await resolveSelect( 'core' ).getEntityRecord(
					'postType',
					'wp_block',
					ref
				);
				if ( entity?.title?.raw ) {
					patternMap.set( ref, entity.title.raw );
				}
			} )
		);
	}

	const text = rootBlocks
		.map( ( block, i, arr ) => {
			const label =
				arr.length > 1 ? `[選択ブロック ${ i + 1 }] ` : '';
			return label + generateTree( block, patternMap );
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
