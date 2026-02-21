<?php
/**
 * Plugin Name:  NExT Block Structure Tree
 * Plugin URI:   -
 * Description:  Adds "Copy Block-Structure-Tree" to the Gutenberg block context menu.
 * Version:      1.1.0
 * Author:       NExT-Season
 * Author URI: https://next-season.net
 * License:      GPL-2.0-or-later
 * Text Domain:  next-bst
 *
 * @package NextBST
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXT_BST_VERSION', '1.1.0' );
define( 'NEXT_BST_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEXT_BST_URL', plugin_dir_url( __FILE__ ) );

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
