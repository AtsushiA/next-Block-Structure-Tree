# NExT Block Structure Tree

WordPress Gutenberg エディターのブロックコンテキストメニューに
**「Copy Block-Structure-Tree」** を追加するプラグインです。

選択したブロックの階層構造を Unix `tree` コマンド風のテキストとして
クリップボードにコピーできます。

WP-CLI コマンド（`wp next-bst export`）でサイト全体・個別ページのブロック構造を
テキストファイルに一括出力することもできます。

---

## 機能

- ブロックの3点メニュー（⋮）に **Copy Block-Structure-Tree** を追加
- 選択ブロック以下のすべての子ブロックを再帰的にツリー表示
- 各ブロックの名称はエディターと同じ表示名（日本語対応）
- ブロックに付けたカスタム名（`metadata.name`）も `[名前]` 形式で表示
- 複数ブロック同時選択にも対応
- コピー完了をスナックバーで通知
- **WP-CLI 対応**: サイト全体・ID・スラッグ指定でテキストファイルを一括生成

---

## 出力例

### エディター（クリップボードコピー）

```
グループ [MAIN]
├── 見出し
├── 段落
└── グループ [SIDEBAR]
    ├── カラム
    │   ├── 画像
    │   └── 段落
    └── カラム
        ├── 見出し
        └── リスト
            ├── リストアイテム
            └── リストアイテム
```

### WP-CLI エクスポート（テキストファイル）

```
Title:    プライバシーポリシー
Slug:     privacy-policy
ID:       12
URL:      https://example.com/privacy-policy/
Modified: 2026-02-21 10:00:00
----------------------------------------

プライバシーポリシー
├── グループ [MAIN]
│   ├── 見出し
│   └── 段落
└── セクション
    └── 段落
```

---

## 動作環境

- WordPress 6.0 以上
- PHP 7.4 以上
- Gutenberg ブロックエディター（クラシックエディター非対応）

---

## インストール

1. `/wp-content/plugins/next-Block-Structure-Tree/` にプラグインを配置
2. WordPress 管理画面 → プラグイン → **NExT Block Structure Tree** を有効化

---

## 使い方

### エディター（コンテキストメニュー）

1. Gutenberg エディターでブロックを選択
2. ブロック右上の **⋮（3点メニュー）** をクリック
3. **Copy Block-Structure-Tree** をクリック
4. クリップボードにツリー構造がコピーされます
5. 任意のテキストエディターに貼り付けて確認

### WP-CLI エクスポート

コマンドリファレンスは `wp next-bst export --help` で確認できます。

```bash
# すべての公開ページをエクスポート（デフォルト出力先: ./next-bst-export）
wp next-bst export --all

# 出力先ディレクトリを指定
wp next-bst export --all --output=/var/export/pages

# 投稿タイプを指定（デフォルト: page）
wp next-bst export --all --post-type=post

# 下書きも含める
wp next-bst export --all --status=any

# ページ ID を指定して1件だけエクスポート
wp next-bst export --id=42 --output=./export

# スラッグを指定して1件だけエクスポート
wp next-bst export --slug=about

# 子ページを階層スラッグで指定
wp next-bst export --slug=about/team
```

#### オプション一覧

| オプション | デフォルト | 説明 |
|---|---|---|
| `--all` | — | すべての投稿をエクスポート |
| `--id=<id>` | — | 指定した投稿 ID の1件をエクスポート |
| `--slug=<slug>` | — | 指定したスラッグの1件をエクスポート（階層スラッグ対応） |
| `--post-type=<post-type>` | `page` | 対象の投稿タイプ |
| `--status=<status>` | `publish` | 投稿ステータス（publish / draft / any など） |
| `--output=<path>` | `./next-bst-export` | 出力先ディレクトリ（絶対パスまたは相対パス） |

#### 出力フォルダ構成

親子関係のあるページは親スラッグのフォルダ以下に自動配置されます。

```
next-bst-export/
├── sample-page.txt          ← ルートページ
├── about.txt
├── about/                   ← 親スラッグ名のフォルダ
│   ├── team.txt
│   └── history.txt
└── services/
    ├── web.txt
    └── design.txt
```

---

## 開発

### 必要環境

- Node.js 18 以上
- npm

### セットアップ

```bash
npm install
```

### ビルド

```bash
# 本番ビルド
npm run build

# 開発ウォッチモード
npm run start

# リリース zip 生成
npm run plugin-zip
```

---

## ライセンス

GPL-2.0-or-later
