# NExT Block Structure Tree

WordPress Gutenberg エディターのブロックコンテキストメニューに
**「Copy Block-Structure-Tree」** を追加するプラグインです。

選択したブロックの階層構造を Unix `tree` コマンド風のテキストとして
クリップボードにコピーできます。

---

## 機能

- ブロックの3点メニュー（⋮）に **Copy Block-Structure-Tree** を追加
- 選択ブロック以下のすべての子ブロックを再帰的にツリー表示
- 各ブロックの名称はエディターと同じ表示名（日本語対応）
- 複数ブロック同時選択にも対応
- コピー完了をスナックバーで通知

---

## 出力例

```
グループ
├── 見出し
├── 段落
└── グループ
    ├── カラム
    │   ├── 画像
    │   └── 段落
    └── カラム
        ├── 見出し
        └── リスト
            ├── リストアイテム
            └── リストアイテム
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

1. Gutenberg エディターでブロックを選択
2. ブロック右上の **⋮（3点メニュー）** をクリック
3. **Copy Block-Structure-Tree** をクリック
4. クリップボードにツリー構造がコピーされます
5. 任意のテキストエディターに貼り付けて確認

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
```

---

## ライセンス

GPL-2.0-or-later
