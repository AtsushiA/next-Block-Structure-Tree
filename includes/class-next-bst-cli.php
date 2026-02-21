<?php
/**
 * WP-CLI command: wp next-bst export
 *
 * Exports block structure trees for pages/posts to text files.
 *
 * @package NextBST
 */

defined( 'ABSPATH' ) || exit;

// このファイルは WP-CLI 環境でのみロードされる。
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Gutenberg ブロック構造を Unix tree 形式のテキストファイルにエクスポートします。
 *
 * ## EXAMPLES
 *
 *     # すべての公開ページをエクスポート（デフォルト出力先: ./next-bst-export）
 *     wp next-bst export --all
 *
 *     # 出力先ディレクトリを指定してエクスポート
 *     wp next-bst export --all --output=/var/export/pages
 *
 *     # 投稿タイプを指定してエクスポート
 *     wp next-bst export --all --post-type=post
 *
 *     # 下書きも含めてエクスポート
 *     wp next-bst export --all --status=any
 *
 *     # ID を指定して1件だけエクスポート
 *     wp next-bst export --id=42
 *
 *     # スラッグを指定して1件だけエクスポート
 *     wp next-bst export --slug=about
 *
 * @when after_wp_load
 */
class Next_BST_CLI extends WP_CLI_Command {

	/**
	 * Gutenberg ブロック構造をテキストファイルにエクスポートします。
	 *
	 * ## DESCRIPTION
	 *
	 * 各ページ・投稿のブロック構造を Unix `tree` コマンド風のテキストファイルに出力します。
	 * 出力先ディレクトリ（--output）に投稿スラッグ名のファイルが生成されます。
	 * 親子関係のあるページは親スラッグ名のサブフォルダに自動配置されます。
	 *
	 * ## OUTPUT FORMAT
	 *
	 * 各ファイルの形式:
	 *
	 *   Title:    ページタイトル
	 *   Slug:     page-slug
	 *   ID:       42
	 *   URL:      https://example.com/page-slug/
	 *   Modified: 2026-02-21 10:00:00
	 *   ----------------------------------------
	 *
	 *   ページタイトル
	 *   ├── グループ [MAIN]
	 *   │   ├── 見出し
	 *   │   └── 段落
	 *   └── セクション
	 *       └── 段落
	 *
	 * ブロックにカスタム名（metadata.name）が設定されている場合は
	 * 「ブロック名 [カスタム名]」の形式で表示されます。
	 *
	 * ## FOLDER STRUCTURE
	 *
	 * 親子関係のあるページは親スラッグ名のフォルダ以下に自動配置されます:
	 *
	 *   next-bst-export/
	 *   ├── sample-page.txt       ← ルートページ
	 *   ├── about.txt
	 *   ├── about/                ← 親スラッグ名のフォルダ
	 *   │   ├── team.txt
	 *   │   └── history.txt
	 *   └── services/
	 *       ├── web.txt
	 *       └── design.txt
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : すべての投稿をエクスポートします。--id / --slug と同時には使えません。
	 *
	 * [--id=<id>]
	 * : 指定した投稿 ID の1件をエクスポートします。
	 *
	 * [--slug=<slug>]
	 * : 指定したスラッグの1件をエクスポートします。
	 *   階層スラッグ（parent/child）も指定できます。
	 *
	 * [--post-type=<post-type>]
	 * : 対象の投稿タイプを指定します。
	 * ---
	 * default: page
	 * ---
	 *
	 * [--status=<status>]
	 * : エクスポートする投稿ステータスを指定します（publish / draft / any など）。
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--output=<path>]
	 * : 出力先ディレクトリ（絶対パスまたは wp コマンド実行ディレクトリからの相対パス）。
	 * ---
	 * default: ./next-bst-export
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # すべての公開ページをエクスポート（デフォルト出力先: ./next-bst-export）
	 *     wp next-bst export --all
	 *
	 *     # 出力先ディレクトリを指定してエクスポート
	 *     wp next-bst export --all --output=./export
	 *
	 *     # 投稿タイプ post をエクスポート（下書きも含む）
	 *     wp next-bst export --all --post-type=post --status=any
	 *
	 *     # ページ ID を指定して1件だけエクスポート
	 *     wp next-bst export --id=42 --output=/tmp/export
	 *
	 *     # スラッグを指定して1件だけエクスポート
	 *     wp next-bst export --slug=about
	 *
	 *     # 子ページを階層スラッグで指定してエクスポート
	 *     wp next-bst export --slug=about/team
	 *
	 * @subcommand export
	 */
	public function export( array $args, array $assoc_args ): void {
		$post_type  = $assoc_args['post-type'] ?? 'page';
		$status     = $assoc_args['status']    ?? 'publish';
		$output_dir = $this->resolve_output_dir( $assoc_args['output'] ?? './next-bst-export' );
		$builder    = new Next_BST_Tree_Builder();

		// ポスト投稿タイプが存在するか確認
		if ( ! post_type_exists( $post_type ) ) {
			WP_CLI::error( "投稿タイプ '{$post_type}' は存在しません。" );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$this->export_all( $builder, $post_type, $status, $output_dir );

		} elseif ( isset( $assoc_args['id'] ) ) {
			$post = get_post( (int) $assoc_args['id'] );
			if ( ! $post || $post->post_type !== $post_type ) {
				WP_CLI::error(
					"ID {$assoc_args['id']} の投稿が見つかりません（post-type: {$post_type}）。"
				);
			}
			$this->export_single( $builder, $post, $output_dir );
			WP_CLI::success( "エクスポート完了: {$output_dir}" );

		} elseif ( isset( $assoc_args['slug'] ) ) {
			$post = get_page_by_path( $assoc_args['slug'], OBJECT, $post_type );
			if ( ! $post ) {
				WP_CLI::error(
					"スラッグ '{$assoc_args['slug']}' の投稿が見つかりません（post-type: {$post_type}）。"
				);
			}
			$this->export_single( $builder, $post, $output_dir );
			WP_CLI::success( "エクスポート完了: {$output_dir}" );

		} else {
			WP_CLI::error( '--all、--id=<id>、--slug=<slug> のいずれかを指定してください。' );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Export all posts of a given post type.
	 */
	private function export_all(
		Next_BST_Tree_Builder $builder,
		string $post_type,
		string $status,
		string $output_dir
	): void {
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => $status,
				'posts_per_page' => -1,
				'orderby'        => [ 'menu_order' => 'ASC', 'ID' => 'ASC' ],
				'no_found_rows'  => true,
			]
		);

		if ( empty( $posts ) ) {
			WP_CLI::warning(
				"post-type '{$post_type}'、status '{$status}' の投稿が見つかりません。"
			);
			return;
		}

		$total    = count( $posts );
		$progress = WP_CLI\Utils\make_progress_bar( "エクスポート中 ({$total} 件)", $total );

		foreach ( $posts as $post ) {
			$this->export_single( $builder, $post, $output_dir );
			$progress->tick();
		}

		$progress->finish();
		WP_CLI::success(
			sprintf( '%d 件のファイルを %s に出力しました。', $total, $output_dir )
		);
	}

	/**
	 * Export a single post to a text file.
	 *
	 * File path is determined by the parent page hierarchy:
	 *   - Root page:       <output>/<slug>.txt
	 *   - Child page:      <output>/<parent-slug>/<slug>.txt
	 *   - Grandchild page: <output>/<grandparent>/<parent>/<slug>.txt
	 */
	private function export_single(
		Next_BST_Tree_Builder $builder,
		WP_Post $post,
		string $output_dir
	): void {
		$file_path = $this->get_file_path( $post, $output_dir );
		$dir       = dirname( $file_path );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$content = $this->build_file_content( $builder, $post );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file_path, $content );

		WP_CLI::log( "  -> {$file_path}" );
	}

	/**
	 * Build the output file path based on the post's ancestor hierarchy.
	 *
	 * Ancestors are resolved via get_ancestors() and ordered from top to bottom.
	 * Post slugs are sanitized with sanitize_file_name() for filesystem safety.
	 */
	private function get_file_path( WP_Post $post, string $output_dir ): string {
		$slug  = sanitize_file_name( $post->post_name ?: (string) $post->ID );
		$parts = [];

		if ( $post->post_parent ) {
			foreach ( $this->get_ancestor_slugs( $post ) as $ancestor_slug ) {
				$parts[] = sanitize_file_name( $ancestor_slug );
			}
		}

		$parts[] = $slug;

		return $output_dir . '/' . implode( '/', $parts ) . '.txt';
	}

	/**
	 * Return ancestor slugs from top (root) to bottom (direct parent).
	 *
	 * @return string[]
	 */
	private function get_ancestor_slugs( WP_Post $post ): array {
		// get_ancestors() returns from direct parent to root; reverse for top-down order.
		$ancestor_ids = array_reverse(
			get_ancestors( $post->ID, $post->post_type, 'post_type' )
		);

		$slugs = [];
		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor = get_post( $ancestor_id );
			if ( $ancestor instanceof WP_Post ) {
				$slugs[] = $ancestor->post_name ?: (string) $ancestor_id;
			}
		}

		return $slugs;
	}

	/**
	 * Build the text file content: header metadata + tree.
	 *
	 * File format:
	 *   Title:    <post title>
	 *   Slug:     <post slug>
	 *   ID:       <post ID>
	 *   URL:      <permalink>
	 *   Modified: <post_modified>
	 *   ----------------------------------------
	 *
	 *   <block structure tree>
	 */
	private function build_file_content(
		Next_BST_Tree_Builder $builder,
		WP_Post $post
	): string {
		$url     = get_permalink( $post );
		$divider = str_repeat( '-', 40 );

		$header = implode(
			"\n",
			[
				"Title:    {$post->post_title}",
				"Slug:     {$post->post_name}",
				"ID:       {$post->ID}",
				"URL:      {$url}",
				"Modified: {$post->post_modified}",
				$divider,
			]
		) . "\n\n";

		return $header . $builder->generate_post_tree( $post );
	}

	/**
	 * Resolve the output directory to an absolute path.
	 *
	 * - Absolute paths are used as-is.
	 * - Relative paths are resolved against the current working directory
	 *   (the directory from which `wp` was invoked).
	 */
	private function resolve_output_dir( string $path ): string {
		if ( path_is_absolute( $path ) ) {
			return rtrim( $path, '/' );
		}

		// Resolve relative to CWD; realpath() returns false for non-existent paths.
		$abs      = getcwd() . '/' . $path;
		$resolved = realpath( $abs );

		return rtrim( false !== $resolved ? $resolved : $abs, '/' );
	}
}

WP_CLI::add_command( 'next-bst', 'Next_BST_CLI' );
