# S.23 post meta (設計 v1.0 の 1.3)

- 種類: 設計
- 状態: 一部失効 (S.39・S.8 で上書き。provenance の出所と冪等キー)
- 出典: docs/wordpress-library-design.md の「1.3 post meta」
- 関連: T.8.2, S.8, S.39

## 1.3 post meta (すべて `_ts_` prefix、register_post_meta で登録)

冪等キー: **`_ts_source_path`** (正規原パス、例 `novel/200209/19204751/d_upboy06.htm`) + `_ts_source_anchor` (`#JUNKO` 等)。ops-simple の「lib 番号+テーブル序数」鍵はパーサ改修で全件重複を生む脆い鍵のため不採用 (審査員1)。序数は `_ts_catalog_ref` (`lib17.html#12`) として情報メタに残す。

| キー | 内容 |
|---|---|
| `_ts_kind` | work / episode |
| `_ts_alias_paths` | JSON。md5 一致・別名重複ファイル群 (城弾シアターミラー 200907/201009 由来等)。正本 1 つを選び残りは alias |
| `_ts_orig_url` / `_ts_annex_url` / `_ts_annex_yays_url` | 当時の ts.novels.jp URL / 原本アーカイブの原本 URL / ~yays 初出版 URL (相対パス一致 1,399 件のみ) |
| `_ts_pub_date_raw` | 目録日付の原表記 (`2000/10/8（日）` 等)。post_date はここから設定。**DDHHMMSS ディレクトリ名は「初回投稿バッチ」であり話の日付ではない (200209 配下に 2014 年の話が実在) — インポータ仕様書に明記** |
| `_ts_size_kb` / `_ts_files_count` | 目録のサイズ欄 (`154KB / 4FILES` 対応) |
| `_ts_illustrator` / `_ts_illustrator_url` | 画師 477 件 (単引用符 href 8 件、複数画師の 、/＆ 区切り対応) |
| `_ts_kansou_slug` / `_ts_kansou_annex_url` | 感想板 slug と板ログへの深リンク |
| `_ts_arasuji` / `_ts_author_comment` / `_ts_suisen` | あらすじ / 作者コメント / 推薦文 (編集部) |
| `_ts_osusume` / `_ts_osusume_in` | 読者オススメ出リンク (765 エントリ 988 対) / sync 時構築の被推薦逆引き |
| `_ts_nav_links` | 推薦文内ナビ 1,726 リンク (【第N話はこちら】等、話順検証用 JSON) |
| `_ts_raw_genre` / `_ts_raw_type` / `_ts_raw_keywords` / `_ts_zokusei` | 正規化前の原表記全保持 (史料性担保) |
| `_ts_provenance` | JSON: 回収経路 (wayback/megalodon/cc/live-site/pixiv)・スナップショット URL・取得日 — collinfo.json から転記 |

> **【失効】** v1.2 補足 5 により `_ts_provenance` の出所は **git 履歴**(回収コミットの件名と日付)に変更(`collinfo.json` は CommonCrawl のコレクション一覧 127 件で、ファイル単位の来歴を一切持たないため)。個別キャプチャの timestamp は記録が無いのでスナップショット URL は Wayback の照会 URL 形式。
| `_ts_body_status` / `_ts_reflow_mode` | raw-html / converted / manual-needed、および診断分類 br-para / br-hardwrap / p / mso / custom (将来のブロック昇格の作業用の一覧 — reader-ux 案の発明を一覧としてだけ採用) |
| `_ts_body_sha256` / `_ts_import_hash` | 原本ハッシュ / 正規化レコード全体ハッシュ (差分スキップ用) |
| `_ts_episode_no` / `_ts_work_seed` / `_ts_needs_review` | 話数 / Work 抽出根拠 (series.html / share_world / titlepage / navlink / cluster / override) / 人手確認フラグ |

mailto 由来のメールアドレスは **catalog 生成段階で除外し WP の DB に入れない** (data-model の核)。

> **【訂正 2026-08-30】** 「構造的に入らない/表示層の消し漏れが原理的に起きない」というのは
> **事実ではなかった**。catalog の**目録メタ**は確かに `catalog_build.scrub_mailto` を通るが、
> **本文 (bodies → payloads → post_content) はその経路を通らない**ため、実アドレス 26 種が
> 本文 47 本に載ったまま公開されていた (公開前レビューで発見)。
> 現在は `payload_build.scrub_addresses` が変換・再掲テキスト・raw フォールバックの全経路で
> 除去し、**出荷前の自己検査 (残存 1 本でも生成失敗)** で担保している。
> 教訓として、「構造的に不可能」と書くときは**その構造を機械が検査していること**を必ず添える。
