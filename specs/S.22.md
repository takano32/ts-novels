# S.22 タクソノミー

- 種類: 設計
- 状態: 一部失効 (S.41 で上書き。ts_corpus の語彙)
- 出典: docs/wordpress-library-design.md の「タクソノミー」
- 関連: T.7.3, S.41, S.173

## タクソノミー (対象: `ts_work`。`ts_author` は `ts_dojo` にも)

| taxonomy | rewrite | 語彙 | 要点 |
|---|---|---|---|
| `ts_author` | `authors` (コアの author_base=/author/ と衝突しない複数形) | **実測 342 名**(設計の本文の「399 名」は表示名の異なり数であって人数ではない。実測は表示名 428 種を感想板 id で統合して 342 名。数え方: `catalog/authors.json` の `authors` 配列長) | **slug = 感想板 author_id** (目録参照 305 slug、board_index.json 289 板が権威)。表記揺れ 48 組 (バレット/石積ナラ=baretto、KEBO 3表記=kebo、大原野/池田=ohharano 等) を slug で統合。板なし 33 slug + 共有板のみ作者 31 名はローマ字新規採番。term meta: `ts_display_variants`(JSON), `ts_yomi`(五十音、lib-index 所属ページから初期値), `ts_kansou_slug`, `ts_kansou_annex_url`, `ts_homepage_wayback`, `ts_homepage_live`(現存移転先のみ), `ts_active_links`(なろう/pixiv 等 JSON), `ts_contact_status`(下記状態機械) |
| `ts_genre` | `genre` | 正規化後約 30 語 | NFKC (ＳＦ→SF)+「？」除去 (学園？→学園)+類義統合 (ミステリ→ミステリー, SS→ショートショート, 学園物→学園, ２次創作→二次創作, コメディー/ギャグ→コメディ)。genre.html の定義文を term description に転用。term meta `ts_raw_variants` |
| `ts_type` | `type` | 正規化後約 25 語 | TS 変化手段 (変身/魔法/人格転移/入れ替わり/憑依/転生/テクノロジー/薬品/医術/アイテム…)。可逆/不可逆/強制も同 taxonomy 内で正規化のみ |
| `ts_keyword` | `keyword` | 約 1,000 語 | 自由タグ。正規化は表記揺れのみ (オレっ娘 3 表記統合、「華代ちゃん」→華代ちゃん)。旧【属性】17 語 (メイド/ナース等) はここへ統合。post_tag ではなく専用 taxonomy にして ts_* 系で統一 (コア結合を避ける) |
| `ts_world` | `world` | **実測 14 本** | 共有世界: kayo_chan, himekami, foster, hunter, fms, relay_novel, delayed, dirty, mirror_ring, rental, setsubou, sugar, 2daime, utanotsuki。**設計の本文の一覧の `corrector` は独立した世界ではなく** `novel/corrector/` = フォスターシリーズの置き場で、逆に share_world.html が挙げる**ハンターシリーズ・FMS シリーズ**が漏れていた。共有感想板は 7 つではなく **11 板**(判定規則は `catalog/world_map.yml`。数え方: `catalog/reports/terms_build.json` の `worlds_total`) |
| `ts_corpus` | (非公開 URL、絞込用) | **honkan / legacy / uncatalogued / extern-repost / dojo の 5 語** | 収蔵区分。世代の物語は taxonomy にせず `/index/timeline/` で解説。年代索引は post_date (=目録の掲載日) による `/year/YYYY/` で賄い taxonomy 化しない |

> **【失効】** S.41「最大掲載原則」により `ts_corpus` の語彙を変更 — 目録外収蔵を拾う `uncatalogued`(859 話)と文庫未掲載の再掲 `extern-repost`(1 話)が加わり、実データに 1 件も無い `anthology` は落とした(`dojo` は T.36 用の枠として残す)。語・表示名で優先するのは S.14〜S.19 の corpus 表で、`honkan` は日本語で書くとき必ず「正規目録 (lib1–73)」と表記する(S.15 の WP サイト (以前は「本館」と呼んでいた) と別概念のため)。

**contact 状態機械** (`ts_contact_status`、正本は catalog/authors.json): `uncontacted → notified → permitted / declined → takedown → removed`。段階 noindex 解禁の判断根拠を機械可読にする。
