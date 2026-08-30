# catalog/ — 単一真実源

WordPress は `wp ts import` でいつでも作り直せる**使い捨ての派生ビュー**であり、
正本はここにある JSONL / JSON。WP 管理画面での手動編集は禁止で、修正は必ずこちら側で行う
(設計 `docs/wordpress-library-design.md` の 3 原則 1)。

## ファイル

| ファイル | 生成元 | 内容 | 状態 |
|---|---|---|---|
| `episodes.jsonl` | `scripts/wp/catalog_build.py` | 正規目録 lib1〜73 の 2,887 エントリ (1 行 1 話) | ✅ タスク 1.1 |
| `reports/catalog_build.json` | 同上 | 件数・被覆率・自己検査結果の機械可読レポート | ✅ |
| `terms.json` / `genre_map.yml` / `type_map.yml` | `terms_build.py` | 分類語彙の正規化 | ⏳ タスク 1.3 |
| `authors.json` / `slug_overrides.yml` | `authors_build.py` | 作者 399 名 | ⏳ タスク 1.4 |
| `works.jsonl` / `work_overrides.yml` | `work_builder.py` | Episode → Work クラスタ | ⏳ タスク 1.5 |
| `convert_report.jsonl` | `body_convert.py` | 本文 MD 変換の無損失証明ログ | ⏳ タスク 1.6 |
| `QA.md` | Makefile | 全体の QA レポート | ⏳ タスク 1.7 |

本文 Markdown の置き場はリポジトリ直下の `bodies/`(catalog の下ではない)。

## 再生成

```sh
python3 scripts/wp/catalog_build.py          # 生成 + 自己検査 + レポート
python3 scripts/wp/catalog_build.py --check  # 書かずに検査だけ (CI 向け)
```

自己検査は受け入れ条件をそのままコード化してある (エントリ数 2,887 / パース失敗 0 /
mailto 由来の値の残存 0 / survey 実測値との突き合わせ)。1 つでも落ちると終了コード 1。

## episodes.jsonl のフィールド

結合キーは **`episode_id`**。`source_path` の `/` を `__` に置換し、アンカーがあれば
`@<anchor>`、**同一ファイルを指す別エントリがある場合は `+<掲載日 YYYYMMDD>`** を付ける。

| フィールド | 内容 |
|---|---|
| `episode_id` | 結合キー (episodes ↔ bodies/ ↔ convert_report ↔ import) |
| `corpus` | `honkan` (lib1–73)。旧目録は `legacy` (タスク 1.2) |
| `source_path` / `source_anchor` / `source_kind` | 原パス空間での位置。`source_kind` = local / external |
| `source_exists` | その原パスがこのリポジトリ (=アネックス) に実在するか |
| `source_shared_by` | 同じ原パスを指す目録エントリの数 (1 なら固有) |
| `entry_type` | html / image (CG 作品) / external |
| `title` `author` `homepage` | 目録 1 行目。`homepage` は URL のみ (mailto は捨てる) |
| `illustrator[]` `illustrator_url` `illustrator_raw` | 画師 (複数可)。原表記も保持 |
| `date` `date_raw` `weekday` | 掲載日。**post_date は必ずこれを使う** (ディレクトリ名の日付は投稿バッチであり話の日付ではない) |
| `size_kb` `files_n` | 目録のサイズ欄 (`154KB / 4FILES`) |
| `kansou_slug` `kansou_annex_url` | 作者感想板の id と、アネックス上の板ログ URL |
| `arasuji` `comment` `suisen` | あらすじ / 作者コメント / 推薦文 (編集部)。話ナビリンクは除いた表示用テキスト |
| `osusume` | `{recommender, refs[{href,title,author,author_inherited}], text}` |
| `nav_links[]` | 【第N話はこちら】形の話ナビ (Work クラスタリングの材料) |
| `inline_links[]` | 本文欄の 【】 でないリンク |
| `genre[]` `type[]` `keywords[]` | NFKC 正規化後のトークン |
| `genre_raw[]` `type_raw[]` `keywords_raw[]` | 原表記 (史料性の担保 + 1.3 の頻度集計用) |
| `zokusei[]` | 旧目録の【属性】欄。本館エントリでは常に空 |
| `catalog_ref` | 出典 (`lib17.html#12`)。情報メタであり結合キーではない |
| `orig_url` | 当時の URL (`http://ts.novels.jp/…`) |
| `annex_url` | GitHub Pages のアネックス原本 URL (実在する場合のみ) |
| `annex_yays_url` | `~yays/library/` に同一相対パスがある場合の初出版 URL |
| `provenance` | 回収経路・取得日・コミット・(アーカイブ系は) 照会 URL |

## provenance の出所について (台帳との差異)

進行台帳のタスク 1.1 は provenance を「リポジトリ直下 `collinfo.json` から転記」と書いているが、
**`collinfo.json` は CommonCrawl のコレクション一覧 (127 件) であり、ファイル単位の来歴情報を
一切持たない** (`scripts/cc_zipnum_sweep.py` が参照する外部データ)。

ファイルがどの経路で入ったかを実際に記録しているのは **git 履歴**(回収コミットの件名と日付)
なので、`catalog_build.py` はそちらを出所にしている (`provenance.method = "git-history"`)。

- `route` … wayback / commoncrawl / megalodon / live-site / narou / pixiv / yays-gapfill / alias / other
- `acquired_at` … そのファイルがリポジトリに入った日 (回収日そのものではなく、回収後のコミット日)
- `snapshot_query_url` … 個別キャプチャの timestamp は記録が無いため、Wayback の**照会 URL**の形
  (`https://web.archive.org/web/2*/<原URL>`)。書誌カードでは「回収経路: Wayback (照会)」の
  リンク先として使える
- `route_mixed: true` … 1 つのコミットが複数経路をまとめている (件名から一意に決まらない) 印
