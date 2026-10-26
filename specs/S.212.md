# S.212 episodes.jsonl の欄

- 種類: 仕様
- 状態: 有効
- 出典: catalog/README.md の「episodes.jsonl のフィールド」から「人手で上書きした話の追加欄」まで (2026-10-26 にここへ移した)
- 関連: T.7.1, T.7.2, T.7.8, T.39.4, S.8, S.171, S.172, S.178, S.179, S.211, S.214

## episodes.jsonl のフィールド

結合キーは **`episode_id`**。`source_path` の `/` を `__` に置換し、アンカーがあれば
`@<anchor>`、**同一ファイルを指す別エントリがある場合は `+<掲載日 YYYYMMDD>`** を付ける。

| フィールド | 内容 |
|---|---|
| `episode_id` | 結合キー (episodes ↔ bodies/ ↔ convert_report ↔ import) |
| `corpus` | `honkan` (lib1–73) / `legacy` (旧目録 lib01–09) / `uncatalogued` (目録に無い収蔵物) / `extern-repost` (文庫未掲載の作者再掲) |
| `source_path` / `source_anchor` / `source_kind` | 原パス空間での位置。`source_kind` = local / external |
| `source_exists` | その原パスがこのリポジトリ (=原本アーカイブの配信元) に実在するか |
| `source_shared_by` | 同じ原パスを指す目録エントリの数 (1 なら固有) |
| `entry_type` | html / image (CG 作品) / external |
| `title` `author` `homepage` | 目録 1 行目。`homepage` は URL のみ (mailto は捨てる) |
| `illustrator[]` `illustrator_url` `illustrator_raw` | 画師 (複数可)。原表記も保持 |
| `date` `date_raw` `weekday` | 掲載日。**post_date は必ずこれを使う** (ディレクトリ名の日付は投稿バッチであり話の日付ではない) |
| `size_kb` `files_n` | 目録のサイズ欄 (`154KB / 4FILES`) |
| `kansou_slug` `kansou_annex_url` | 作者感想板の id と、原本アーカイブ上の板ログ URL |
| `arasuji` `comment` `suisen` | あらすじ / 作者コメント / 推薦文 (編集部)。話ナビリンクは除いた表示用テキスト |
| `osusume` | `{recommender, refs[{href,title,author,author_inherited}], text}` |
| `nav_links[]` | 【第N話はこちら】形の話ナビ (Work クラスタリングの材料) |
| `inline_links[]` | 本文欄の 【】 でないリンク |
| `genre[]` `type[]` `keywords[]` | NFKC 正規化後のトークン |
| `genre_raw[]` `type_raw[]` `keywords_raw[]` | 原表記 (史料性の担保 + T.7.3 の頻度集計用) |
| `zokusei[]` | 旧目録の【属性】欄。正規目録 (`honkan`) のエントリでは常に空 |
| `catalog_ref` | 出典 (`lib17.html#12`)。情報メタであり結合キーではない |
| `orig_url` | 当時の URL (`http://ts.novels.jp/…`) |
| `annex_url` | GitHub Pages (原本アーカイブ) の原本 URL (実在する場合のみ) |
| `annex_yays_url` | `~yays/library/` に同一相対パスがある場合の初出版 URL |
| `provenance` | 回収経路・取得日・コミット・(アーカイブ系は) 照会 URL |

## 旧目録 (corpus=legacy) の追加欄

旧目録は書式が違うため、**legacy レコードにだけ**次の欄が付く (honkan レコードの形は T.7.1 のまま不変)。

| フィールド | 内容 |
|---|---|
| `title_raw` | ［…］ 括弧を外す前の原表記 |
| `legacy_credit_raw` | `作・A ／ 画・B` のクレジット原文 (lib01–06 は作者欄が独立していない) |
| `date_precision` | `exact` = 目録に年がある (lib07–09 の `YY/M/D`) / `range-derived` = 日付欄が `M/D` のみで、ページ見出しの収録期間から年を一意に決めた / `range-clamped` = 期間外の日付を最寄りの年に寄せた |
| `noteky_url` `noteky_id` | lib07–09 の感想リンク。正規目録の作者別板 (`bbs@log_<id>.cgi`) と違い**作品単位のノート** (`~ezpe/cgi-bin/noteky/…`) |
| `zokusei` `zokusei_raw` | 【属性】欄 (lib07–09 のみ) |
| `legacy_format` | `flat` (lib01–06 の非テーブル) / `table` (lib07–09 の移行期テーブル) |
| `entry_role` | `work` / `notice` (作者欄が `***` の編集部告知ブロック。現状 1 件) / `series-index` (シリーズ目次ページ。作者欄に人名でない値が入っていたもの。現状 1 件 = 姫神奇譚タイトルページ) |
| `commented_out` | **原本の HTML コメント内に隠されていたエントリ** (12 件)。運営が意図的に伏せた可能性があるので、WP へ投入するかは人間の判断待ち |
| `notice` | 【お知らせ】欄 |

`kansou_slug` / `kansou_annex_url` / `osusume` / `weekday` は legacy では常に null、
`size_kb` も CG エントリでは null になる。

**dedup はパス単位** — 正規目録 (lib1–73) が同じ `source_path` を持つ旧目録エントリは
(正規目録側がアンカー付き集約リンクや改訂再掲であっても) 重複として落とす。
実測: 旧目録ブロック 336 → 正規目録と重複 239 を除外 → **追加 97 件** (flat 75 / table 22)。

## 目録外収蔵 (corpus=uncatalogued) の追加欄

設計 (specs/S.41.md)「最大掲載原則」で拾った、目録のどのエントリにも対応しない `novel/` 配下の本文。

| フィールド | 内容 |
|---|---|
| `metadata_source[]` | 題名・作者をどこから取ったか (`lib-index` / `body-credit` / `body-title` / `dir-author` / `dir-author-majority` / `filename`) |
| `text_chars` | 本文テキストの文字数 (薄いページを人が見つけるため) |
| `date_precision` | `directory-batch` = 投稿ディレクトリ `novel/YYYYMM/` から起こした概算 / `unknown` |
| `entry_role` | `work` / `unattributed` (作者が特定できなかったもの。**36 件**。数え方: `episodes.jsonl` の `entry_role == "unattributed"` の行数。旧目録の編集部告知 `notice` 1 件を足した 37 が「作者不詳」の総数) |

**date は概算**。投稿ディレクトリ名は「作者の初回投稿バッチ」なので、
`date_precision != 'exact'` の話の post_date は概算として扱うこと。

## 作者本人の再掲 (corpus=extern-repost / body_source_path) の追加欄

| フィールド | 内容 |
|---|---|
| `body_source_path` | 本文の在処 (`reposts/<episode_id>.txt`)。プレーンテキスト |
| `body_format` / `body_convert_exempt` | `plain-text` / `true` = **T.7.6 の HTML→MD 変換の対象外**。T.9 で空行区切りの段落分割 |
| `published_in_bunko` | `false` なら**文庫には一度も載っていない**。書誌カードに「文庫未掲載・pixiv 由来」と明示する |
| `repost_url` | 再掲元 (pixiv 作品ページ) |

## 人手で上書きした話の追加欄 (episode_overrides.yml 由来)

| フィールド | 内容 |
|---|---|
| `overrides_applied[]` | `{field, from, to}`。**旧サイトの原本 `lib*.html` の値をどう直したか**。現状 3 話 |
| `override_note` | なぜ直したのか (`catalog/episode_overrides.yml` の `note:` の転記) |

原本アーカイブ (GitHub Pages) は原本レイヤなので誤植もそのまま保つ。直すのは catalog 側だけで、
その差分がこの 2 欄に残る。上書き可能な欄は author / kansou_slug / entry_role / title /
homepage / date に限っており、**結合キー `episode_id` と `provenance` には触れない**。
