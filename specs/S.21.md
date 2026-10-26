# S.21 投稿タイプ (設計 v1.0 の 1.1)

- 種類: 設計
- 状態: 有効
- 出典: docs/wordpress-library-design.md の「1. コンテンツモデル」の「1.1 投稿タイプ」
- 関連: T.8.1, S.181

## 1. コンテンツモデル

### 1.1 投稿タイプ (すべて `mu-plugins/ts-library/` で登録 — テーマ・プラグイン無効化事故に耐える)

| CPT | 設定 | 用途 |
|---|---|---|
| `ts_work` | hierarchical=true, rewrite `novel` (with_front=false。当初 `works`、2026-08-30 ユーザ裁定で `/novel/` — 旧サイトの URL 語彙と揃える), supports: title/editor/excerpt/custom-fields/page-attributes/thumbnail, has_archive=false | 親投稿 (post_parent=0) = Work (作品)、子投稿 = Episode (話=目録エントリ)。menu_order=話数。単発作品は親のみ 1 投稿 (Work=Episode 1:1)。階層 CPT 1 本にすることで `/novel/{work}/{ep}/` の入れ子パーマリンクがコア機能で得られる (data-model の 2 CPT 案より実装が薄い。区別は post_parent と `_ts_kind` メタ) |
| `ts_doc` | flat, rewrite `docs` | 運営コンテンツ: 編集"好"記 (comittee/ 21)、巻頭言 (columns/ 2)、構築日記 (dialy/ 1)、03summer 解題、語彙解題等 |
| `ts_dojo` | flat, rewrite `dojo`, supports comments (T.36 で登録) | 2ndbbs「ストーリー道場」作品 267 本 |
| 固定ページ | — | `/about/` `/takedown/` `/removed/` `/annex/` `/search/` `/index/` ハブ |

Work の本文にはシリーズタイトルページ (novel/**/title*.htm 約 175 個) の内容を転用し、無い作品は目録あらすじ+話一覧を自動生成する。
