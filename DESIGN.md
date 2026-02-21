# next-Block-Structure-Tree — 設計ドキュメント

## 概要

WordPress Gutenberg エディター上で、選択中のブロックのブロック構造を
Unix `tree` コマンド風のテキストとしてクリップボードにコピーする機能を提供するプラグイン。

ブロックの3点メニュー（コンテキストメニュー）に **「Copy Block-Structure-Tree」** を追加し、
実行するとネストされたブロック階層のツリー文字列が生成される。

---

## 機能要件

| # | 要件 |
|---|------|
| 1 | Gutenberg エディターのブロックコンテキストメニューに「Copy Block-Structure-Tree」メニュー項目を追加する |
| 2 | 選択ブロック（および子ブロック）を再帰的に走査し、tree 形式の文字列を生成する |
| 3 | 各ブロックの表示名はブロック API（`getBlockType`）から取得した日本語/英語タイトルを使用する |
| 4 | 生成したテキストをクリップボードにコピーする |
| 5 | コピー成功時にトースト通知（スナックバー）でフィードバックを表示する |

---

## ツリー出力フォーマット

選択ブロックを root として、`tree` コマンドと同等の記号で階層を表現する。

### 記号ルール

| 記号 | 意味 |
|------|------|
| `├── ` | 兄弟ノードが後に続く子ノード |
| `└── ` | 最後の子ノード |
| `│   ` | 非末尾の親の子孫に対するインデント継続線 |
| `    ` | 末尾の親の子孫に対するインデント（空白4文字） |

### 出力例

```
グループ
├── 見出し
├── 段落
└── グループ
    ├── 画像
    └── 段落
```

複数ブロックを同時選択した場合の出力例：

```
[選択ブロック 1] グループ
├── 見出し
└── 段落

[選択ブロック 2] カラム
├── カラム
│   └── 画像
└── カラム
    └── 段落
```

---

## プラグイン構成（ファイルツリー）

```
next-Block-Structure-Tree/
├── next-block-structure-tree.php   # プラグインエントリポイント（PHP）
├── package.json                    # npm 依存関係・ビルドスクリプト
├── DESIGN.md                       # 本設計書
├── README.md                       # ユーザー向け説明書
├── src/
│   └── index.js                    # JavaScript エントリポイント（プラグイン登録）
└── build/
    └── index.js                    # wp-scripts でコンパイルされた成果物（gitignore）
```

---

## 技術設計

### 使用 WordPress API

| API | 用途 |
|-----|------|
| `@wordpress/plugins` の `registerPlugin` | エディタープラグインの登録 |
| `@wordpress/block-editor` の `BlockSettingsMenuControls` | コンテキストメニューへの項目追加 |
| `@wordpress/data` の `select('core/block-editor')` | `getBlock`, `getSelectedBlockClientIds` でブロックデータ取得 |
| `@wordpress/blocks` の `getBlockType` | ブロック名（slug）→ 表示タイトル変換 |
| `@wordpress/components` の `MenuItem` | メニュー項目 UI コンポーネント |
| `@wordpress/notices` の `createNotice` | コピー完了トースト通知 |
| `navigator.clipboard.writeText()` | クリップボードへの書き込み |

### ブロックツリー生成アルゴリズム

```
function buildTree(block, prefix, isLast):
    blockType = getBlockType(block.name)
    title     = blockType.title ?? block.name

    connector = isLast ? "└── " : "├── "
    line      = prefix + connector + title
    lines     = [line]

    children = block.innerBlocks
    for i, child in children:
        isLastChild = (i === children.length - 1)
        childPrefix = prefix + (isLast ? "    " : "│   ")
        lines += buildTree(child, childPrefix, isLastChild)

    return lines

// root ブロック（選択ブロック自身）
function generateTree(rootBlock):
    blockType = getBlockType(rootBlock.name)
    title     = blockType.title ?? rootBlock.name

    lines = [title]   // root はコネクタなし
    children = rootBlock.innerBlocks
    for i, child in children:
        isLast = (i === children.length - 1)
        lines += buildTree(child, "", isLast)

    return lines.join("\n")
```

### コンポーネント構成

```
registerPlugin('next-bst', {
  render: () => (
    <BlockSettingsMenuControls>
      {({ selectedClientIds }) => (
        <MenuItem
          icon={copyIcon}
          onClick={() => handleCopy(selectedClientIds)}
        >
          Copy Block-Structure-Tree
        </MenuItem>
      )}
    </BlockSettingsMenuControls>
  )
})
```

---

## PHP プラグインファイル仕様

```
Plugin Name:  NExT Block Structure Tree
Plugin URI:   -
Description:  Adds "Copy Block-Structure-Tree" to the Gutenberg block context menu.
Version:      1.0.0
Author:       -
License:      GPL-2.0-or-later
Text Domain:  next-bst
```

### プレフィックス規則

| 種別 | プレフィックス | 例 |
|------|--------------|-----|
| PHP 関数 | `next_bst_` | `next_bst_enqueue_editor_assets` |
| PHP 定数 | `NEXT_BST_` | `NEXT_BST_VERSION` |
| スクリプトハンドル | `next-bst-` | `next-bst-editor` |
| JS プラグイン名 | `next-bst` | `registerPlugin('next-bst', ...)` |
| Text Domain | `next-bst` | `__('...', 'next-bst')` |

### スクリプト登録

```php
function next_bst_enqueue_editor_assets() {
    $asset = include plugin_dir_path(__FILE__) . 'build/index.asset.php';

    wp_enqueue_script(
        'next-bst-editor',
        plugin_dir_url(__FILE__) . 'build/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );
}
add_action('enqueue_block_editor_assets', 'next_bst_enqueue_editor_assets');
```

`build/index.asset.php` は `@wordpress/scripts` が自動生成し、
依存する `@wordpress/*` パッケージを自動的に `wp_enqueue_script` の依存リストに含める。

---

## ビルド環境

```json
{
  "scripts": {
    "build":  "wp-scripts build",
    "start":  "wp-scripts start",
    "lint:js": "wp-scripts lint-js"
  },
  "devDependencies": {
    "@wordpress/scripts": "^30.0.0"
  }
}
```

`@wordpress/scripts` の標準 webpack 設定を使用するため、追加の `webpack.config.js` は不要。
エントリポイントは `src/index.js` がデフォルト。

---

## 処理フロー

```
ユーザー操作
│
├─ ブロックを選択（リストビューまたはエディター上）
│
├─ 3点メニュー（⋮）をクリック
│
├─ コンテキストメニューが開く
│   └─ 「Copy Block-Structure-Tree」が表示される   ← BlockSettingsMenuControls
│
└─ メニュー項目をクリック
    │
    ├─ getSelectedBlockClientIds() で選択中の clientId 取得
    ├─ getBlock(clientId) でブロックデータ（innerBlocks 含む）取得
    ├─ generateTree() でツリー文字列を生成
    ├─ navigator.clipboard.writeText() でクリップボードにコピー
    └─ createNotice() でトースト「ブロック構造をコピーしました」表示
```

---

## 実装ステップ

| Step | 内容 | ファイル |
|------|------|----------|
| 1 | PHP プラグインファイル作成・スクリプト登録 | `next-block-structure-tree.php` |
| 2 | `package.json` 作成・npm install | `package.json` |
| 3 | `src/index.js` にプラグイン登録・ツリー生成関数を実装 | `src/index.js` |
| 4 | `npm run build` でビルド | `build/` |
| 5 | WordPress 管理画面でプラグインを有効化 | - |
| 6 | 動作確認・デバッグ | - |

---

## 出力例（クリップボードにコピーされるテキスト）

### シンプルなケース

```
グループ
├── 見出し
├── 段落
└── 段落
```

### 深くネストしたケース

```
グループ
├── カラム
│   ├── カラム
│   │   ├── 画像
│   │   └── 段落
│   └── カラム
│       ├── 見出し
│       └── リスト
│           ├── リストアイテム
│           └── リストアイテム
└── ボタン
```

---

## 考慮事項・制約

| 項目 | 内容 |
|------|------|
| 内部ブロックのみ | 選択したブロック配下のみ対象。兄弟ブロックは含まない |
| 最大深さ制限 | 現バージョンでは制限なし（将来的に設定可能にすることも可） |
| クリップボード API | `navigator.clipboard` は HTTPS または localhost 環境が必要 |
| ブロック名フォールバック | `getBlockType` で取得できない場合は `block.name`（slug 形式）を使用 |
| 複数選択 | 複数ブロック選択時は各ブロックのツリーを空行区切りで結合 |
| 動的ブロック | 内部ブロック構造は同様に取得可能 |
