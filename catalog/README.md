# catalog/ — 単一真実源

WordPress は `wp ts import` でいつでも作り直せる**使い捨ての派生ビュー**であり、
正本はここにある JSONL / JSON。WP 管理画面での手動編集は禁止で、修正は必ずこちら側で行う
(設計 `docs/wordpress-library-design.md` の 3 原則 1)。
用語は [`../docs/glossary.md`](../docs/glossary.md) が正典
(**本館** = 移築先の WordPress / **別館** = 原本を保つ GitHub Pages ミラー / **旧館** = 消滅した原サイト。
いずれも内部用語で、読者に見せる文面では使わない)。
**注意**: 本書で `corpus` の値として出てくる「本館」は **corpus `honkan` = 正規目録 lib1–73 由来**という
収蔵区分の名前であって、移築先 WordPress を指す「本館」ではない。

## ファイル

| ファイル | 生成元 | 内容 | 状態 |
|---|---|---|---|
| `episodes.jsonl` | `catalog_build.py` → `uncatalogued_build.py` → `repost_build.py` | 全 3,844 話 = 本館 2,887 + 旧目録 97 + 目録外 859 + 文庫未掲載の再掲 1 | ✅ タスク 1.1 / 1.2 / 1.8 / 1.9 |
| `uncatalogued_excluded.jsonl` | `uncatalogued_build.py` | 目録外走査で「作品ではない」と判定して落としたファイルと理由 | ✅ |
| `reports/catalog_build.json` | 同上 | 件数・被覆率・自己検査結果の機械可読レポート | ✅ |
| `terms.json` | `terms_build.py` | 分類語彙 6 本 (genre 196 / type 165 / keyword 1,028 / world 14 / corpus 4) | ✅ タスク 1.3 |
| `genre_map.yml` / `type_map.yml` / `keyword_map.yml` / `world_map.yml` | 同上 (初回生成→以後は人手で編集) | 正規化マップと ts_world の判定規則 | ✅ |
| `slug_overrides.yml` | `terms_build.py` / `authors_build.py` / `work_builder.py` | 恒久 URL slug の確認台帳 (👤 1.5b) | ⏳ 確認待ち |
| `authors.json` | `authors_build.py` | 作者 333 名 (表示名 413 種を感想板 id で統合) | ✅ タスク 1.4 |
| `works.jsonl` / `work_overrides.yml` | `work_builder.py` | Episode → Work クラスタ 1,156 件 (orphan 0) | ✅ タスク 1.5 |
| `convert_report.jsonl` | `body_convert.py` | 本文 MD 変換の無損失証明ログ | ⏳ タスク 1.6 |
| `QA.md` | `qa_report.py` (`make catalog` の最後) | 全体の QA レポート | ✅ タスク 1.7 |

本文 Markdown の置き場はリポジトリ直下の `bodies/`(catalog の下ではない)。

## 再生成

```sh
make venv        # 初回のみ: pykakasi / PyYAML 入りの .venv を作る
make catalog     # 1.1〜1.9 を順に実行して catalog/ を作り直し、QA.md まで書く
make check       # ファイルを書かずに各段の自己検査だけ
```

段の順番には意味がある。`episodes.jsonl` は `catalog_build` が新規に書き、
`uncatalogued_build` と `repost_build` が**自分の corpus 分だけ差し替えて**追記し、
`terms_build` / `authors_build` / `work_builder` はその出来上がりを読む。
2 回連続実行で全出力が同一になることを確認済み。

自己検査は受け入れ条件をそのままコード化してある (エントリ数 2,887 / パース失敗 0 /
mailto 由来の値の残存 0 / survey 実測値との突き合わせ)。1 つでも落ちると終了コード 1。

## episodes.jsonl のフィールド

結合キーは **`episode_id`**。`source_path` の `/` を `__` に置換し、アンカーがあれば
`@<anchor>`、**同一ファイルを指す別エントリがある場合は `+<掲載日 YYYYMMDD>`** を付ける。

| フィールド | 内容 |
|---|---|
| `episode_id` | 結合キー (episodes ↔ bodies/ ↔ convert_report ↔ import) |
| `corpus` | `honkan` (lib1–73) / `legacy` (旧目録 lib01–09) / `uncatalogued` (目録に無い収蔵物) / `extern-repost` (文庫未掲載の作者再掲) |
| `source_path` / `source_anchor` / `source_kind` | 原パス空間での位置。`source_kind` = local / external |
| `source_exists` | その原パスがこのリポジトリ (=別館の配信元) に実在するか |
| `source_shared_by` | 同じ原パスを指す目録エントリの数 (1 なら固有) |
| `entry_type` | html / image (CG 作品) / external |
| `title` `author` `homepage` | 目録 1 行目。`homepage` は URL のみ (mailto は捨てる) |
| `illustrator[]` `illustrator_url` `illustrator_raw` | 画師 (複数可)。原表記も保持 |
| `date` `date_raw` `weekday` | 掲載日。**post_date は必ずこれを使う** (ディレクトリ名の日付は投稿バッチであり話の日付ではない) |
| `size_kb` `files_n` | 目録のサイズ欄 (`154KB / 4FILES`) |
| `kansou_slug` `kansou_annex_url` | 作者感想板の id と、別館上の板ログ URL |
| `arasuji` `comment` `suisen` | あらすじ / 作者コメント / 推薦文 (編集部)。話ナビリンクは除いた表示用テキスト |
| `osusume` | `{recommender, refs[{href,title,author,author_inherited}], text}` |
| `nav_links[]` | 【第N話はこちら】形の話ナビ (Work クラスタリングの材料) |
| `inline_links[]` | 本文欄の 【】 でないリンク |
| `genre[]` `type[]` `keywords[]` | NFKC 正規化後のトークン |
| `genre_raw[]` `type_raw[]` `keywords_raw[]` | 原表記 (史料性の担保 + 1.3 の頻度集計用) |
| `zokusei[]` | 旧目録の【属性】欄。本館エントリでは常に空 |
| `catalog_ref` | 出典 (`lib17.html#12`)。情報メタであり結合キーではない |
| `orig_url` | 当時の URL (`http://ts.novels.jp/…`) |
| `annex_url` | GitHub Pages (別館) の原本 URL (実在する場合のみ) |
| `annex_yays_url` | `~yays/library/` に同一相対パスがある場合の初出版 URL |
| `provenance` | 回収経路・取得日・コミット・(アーカイブ系は) 照会 URL |

## 旧目録 (corpus=legacy) の追加欄

旧目録は書式が違うため、**legacy レコードにだけ**次の欄が付く (honkan レコードの形は 1.1 のまま不変)。

| フィールド | 内容 |
|---|---|
| `title_raw` | ［…］ 括弧を外す前の原表記 |
| `legacy_credit_raw` | `作・A ／ 画・B` のクレジット原文 (lib01–06 は作者欄が独立していない) |
| `date_precision` | `exact` = 目録に年がある (lib07–09 の `YY/M/D`) / `range-derived` = 日付欄が `M/D` のみで、ページ見出しの収録期間から年を一意に決めた / `range-clamped` = 期間外の日付を最寄りの年に寄せた |
| `noteky_url` `noteky_id` | lib07–09 の感想リンク。本館の作者別板 (`bbs@log_<id>.cgi`) と違い**作品単位のノート** (`~ezpe/cgi-bin/noteky/…`) |
| `zokusei` `zokusei_raw` | 【属性】欄 (lib07–09 のみ) |
| `legacy_format` | `flat` (lib01–06 の非テーブル) / `table` (lib07–09 の移行期テーブル) |
| `entry_role` | `work` / `notice` (作者欄が `***` の編集部告知ブロック。現状 1 件) |
| `commented_out` | **原本の HTML コメント内に隠されていたエントリ** (12 件)。運営が意図的に伏せた可能性があるので、WP へ投入するかは人間の判断待ち |
| `notice` | 【お知らせ】欄 |

`kansou_slug` / `kansou_annex_url` / `osusume` / `weekday` は legacy では常に null、
`size_kb` も CG エントリでは null になる。

**dedup はパス単位** — 本館 (lib1–73) が同じ `source_path` を持つ旧目録エントリは
(本館側がアンカー付き集約リンクや改訂再掲であっても) 重複として落とす。
実測: 旧目録ブロック 336 → 本館重複 239 を除外 → **追加 97 件** (flat 75 / table 22)。

## 目録外収蔵 (corpus=uncatalogued) の追加欄

設計 v1.4「最大掲載原則」で拾った、目録のどのエントリにも対応しない `novel/` 配下の本文。

| フィールド | 内容 |
|---|---|
| `metadata_source[]` | 題名・作者をどこから取ったか (`lib-index` / `body-credit` / `body-title` / `dir-author` / `dir-author-majority` / `filename`) |
| `text_chars` | 本文テキストの文字数 (薄いページを人が見つけるため) |
| `date_precision` | `directory-batch` = 投稿ディレクトリ `novel/YYYYMM/` から起こした概算 / `unknown` |
| `entry_role` | `work` / `unattributed` (作者が特定できなかったもの。37 件) |

**date は概算**。投稿ディレクトリ名は「作者の初回投稿バッチ」なので、
`date_precision != 'exact'` の話の post_date は概算として扱うこと。

## 作者本人の再掲 (corpus=extern-repost / body_source_path) の追加欄

| フィールド | 内容 |
|---|---|
| `body_source_path` | 本文の在処 (`reposts/<episode_id>.txt`)。プレーンテキスト |
| `body_format` / `body_convert_exempt` | `plain-text` / `true` = **1.6 の HTML→MD 変換の対象外**。Phase 3 で空行区切りの段落分割 |
| `published_in_bunko` | `false` なら**文庫には一度も載っていない**。書誌カードに「文庫未掲載・pixiv 由来」と明示する |
| `repost_url` | 再掲元 (pixiv 作品ページ) |

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
