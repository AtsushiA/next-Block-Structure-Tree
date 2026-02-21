<?php
/**
 * Plugin Name:  NExT Block Structure Tree
 * Plugin URI:   -
 * Description:  Adds "Copy Block-Structure-Tree" to the Gutenberg block context menu.
 * Version:      1.3.0
 * Author:       NExT-Season
 * Author URI: https://next-season.net
 * License:      GPL-2.0-or-later
 * Text Domain:  next-bst
 *
 * @package NextBST
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXT_BST_VERSION', '1.3.0' );
define( 'NEXT_BST_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEXT_BST_URL', plugin_dir_url( __FILE__ ) );

// Tree builder (shared by CLI and future server-side features).
require_once NEXT_BST_DIR . 'includes/class-next-bst-tree-builder.php';

// WP-CLI command (loaded only inside WP-CLI to avoid overhead on web requests).
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once NEXT_BST_DIR . 'includes/class-next-bst-cli.php';
}

/**
 * Enqueue editor assets for the block editor.
 * Only runs for users who can edit posts (defense-in-depth).
 */
function next_bst_enqueue_editor_assets() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$asset_file = NEXT_BST_DIR . 'build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = include $asset_file;

	wp_enqueue_script(
		'next-bst-editor',
		NEXT_BST_URL . 'build/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);

	wp_set_script_translations( 'next-bst-editor', 'next-bst' );
}
add_action( 'enqueue_block_editor_assets', 'next_bst_enqueue_editor_assets' );
