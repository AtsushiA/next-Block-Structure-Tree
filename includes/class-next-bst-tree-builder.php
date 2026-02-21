<?php
/**
 * Tree builder: converts Gutenberg block data into a tree-formatted string.
 *
 * Used by both the WP-CLI export command and any future server-side features.
 *
 * @package NextBST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Next_BST_Tree_Builder
 */
class Next_BST_Tree_Builder {

	/**
	 * Get the display label for a single parsed block.
	 *
	 * Mirrors the JS getBlockLabel() in src/index.js:
	 *   - Uses WP_Block_Type_Registry to get the human-readable title.
	 *   - Appends "[name]" when block.attributes.metadata.name is set.
	 *
	 * @param array $block Parsed block array from parse_blocks().
	 * @return string Display label.
	 */
	public function get_block_label( array $block ): string {
		$block_name = $block['blockName'] ?? '';

		if ( ! $block_name ) {
			return '(unknown)';
		}

		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );
		$title      = $block_type instanceof WP_Block_Type && $block_type->title
			? $block_type->title
			: $block_name;

		$meta_name = $block['attrs']['metadata']['name'] ?? null;

		return $meta_name ? "{$title} [{$meta_name}]" : $title;
	}

	/**
	 * Recursively build tree lines for a block and its inner blocks.
	 *
	 * Mirrors the JS buildTree() in src/index.js.
	 *
	 * @param array  $block   Parsed block array.
	 * @param string $prefix  Current line prefix (tree drawing characters).
	 * @param bool   $is_last Whether this block is the last sibling.
	 * @return string[] Array of tree lines.
	 */
	public function build_tree( array $block, string $prefix, bool $is_last ): array {
		$title     = $this->get_block_label( $block );
		$connector = $is_last ? '└── ' : '├── ';
		$lines     = [ $prefix . $connector . $title ];

		$children = $block['innerBlocks'] ?? [];
		$count    = count( $children );

		foreach ( $children as $i => $child ) {
			$child_prefix = $prefix . ( $is_last ? '    ' : '│   ' );
			array_push( $lines, ...$this->build_tree( $child, $child_prefix, $i === $count - 1 ) );
		}

		return $lines;
	}

	/**
	 * Generate a tree-formatted string for an entire post/page.
	 *
	 * The post title is the root node.
	 * Each top-level block becomes a direct child of the root.
	 *
	 * Output example:
	 *   プライバシーポリシー
	 *   ├── グループ [MAIN]
	 *   │   ├── 見出し
	 *   │   └── 段落
	 *   └── セクション
	 *       └── 段落
	 *
	 * @param WP_Post $post WordPress post object.
	 * @return string Tree-formatted string (trailing newline included).
	 */
	public function generate_post_tree( WP_Post $post ): string {
		$blocks = parse_blocks( $post->post_content );

		// parse_blocks() inserts null/whitespace-only blocks between real blocks.
		$blocks = array_values(
			array_filter( $blocks, static fn( $b ) => ! empty( $b['blockName'] ) )
		);

		// Root: post title.
		$lines = [ $post->post_title ];
		$count = count( $blocks );

		if ( 0 === $count ) {
			$lines[] = '└── (ブロックなし)';
		} else {
			foreach ( $blocks as $i => $block ) {
				array_push( $lines, ...$this->build_tree( $block, '', $i === $count - 1 ) );
			}
		}

		return implode( "\n", $lines ) . "\n";
	}
}
