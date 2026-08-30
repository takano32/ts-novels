# 少年少女文庫 WordPress ライブラリ 最終統合設計 v1.0

用語 (本館 = 移築先の WordPress / 別館 = 原本を保つ GitHub Pages ミラー / 旧館 = 消滅した原サイト、
ほか) は [`glossary.md`](glossary.md) が正典。**この 3 語は内部用語**であり、
サイト上の表示文言・about ページ・バナー・書誌カードのラベルには使わない
(読者には「原本を見る」「当時のページ」のように具体的に書く)。

土台は審査で 2/3 の審査員が勝者指名した **data-model 案 (正準データモデルファースト)**。そこへ preservation 案の核 (原パス空間の温存・リポジトリ純潔原則・kansou 非インポート)、ops-simple 案の核 (常時稼働サーバゼロ・mu-plugin・make publish 一発化)、reader-ux 案の核 (メタ先行投入マイルストーン・軽量リーダー UX)、および審査員の全指摘 (BR 再フロー封印、guestbook 矛盾の削除、GitHub Issue 窓口の不採用、ヘッダ制御可能ホスト、410、lychee、再構築演習) を取り込んだ。

## 0. 設計原理と全体像

**3原則**

1. **単一真実源は `catalog/`** — 正規目録 lib1–73.html の 2,887 エントリ (+旧目録 lib01–09 の重複排除後差分) を正規化した JSONL 群を git 管理し、WordPress DB は `wp ts import` でいつでも全再構築できる「使い捨て可能な派生ビュー」とする。WP 内での手動編集は原則禁止し、修正はすべて catalog 側の overrides ファイルで行う (審査員指摘の「export-manual 還流の腐敗」リスクを、還流機構ではなく編集経路の一本化で構造的に消す)。
2. **公開面は完全静的** — WP は非公開オリジン (手元 Docker Compose、必要時のみ起動)。Simply Static で書き出した「本館」と、原パス空間をそのまま保持した「別館の配信物」を 1 つの静的サイトにマージして配信。常時稼働サーバゼロで 10 年放置耐性を確保する。
3. **原本無改変・加工は配信物のみ** — git 作業ツリーの原ファイルには一切触れない。mailto 除去・往還バナー注入・noindex 付与はすべてビルド時に deploy artifact へ施工する (ops-simple の「別コミット直接施工」は不採用。施工スクリプトが git 管理され、成果物はデプロイブランチに載るため来歴も残る)。

**層構造**

```
git リポジトリ (原本17,215ファイル + catalog/ + scripts/wp/ + takedown/)
 ├─ scripts/wp/catalog_build.py ─→ catalog/*.jsonl (単一真実源)
 │        └─ wp ts import ─→ 非公開WP(Docker) ─→ Simply Static ─→ dist/wp/   (予約名前空間)
 ├─ scripts/wp/annex_build.py ──────────────────────────────────→ dist/annex/ (原パス空間・マスク済)
 └─ merge + pagefind + verify ─→ dist/site/ ─→ Cloudflare Pages (1ドメイン)
```

旧 URL (`/novel/200209/19204751/d_upboy06.htm`、`/lib12.html`、`/~ts/kansou/bbs@log_johdan.cgi`、`/~yays/…`) は**マージ後もドキュメントルート直下の原パスのままファイルとして存在**するため、リダイレクト表なしで 1999 年以来のリンクが生きる (preservation 案の nginx 素通しを静的マージに移植。ops-simple の `/annex/` 接頭辞方式は原 URL 空間を壊すため不採用)。

---

## 1. コンテンツモデル

### 1.1 投稿タイプ (すべて `mu-plugins/ts-library/` で登録 — テーマ・プラグイン無効化事故に耐える)

| CPT | 設定 | 用途 |
|---|---|---|
| `ts_work` | hierarchical=true, rewrite `works` (with_front=false), supports: title/editor/excerpt/custom-fields/page-attributes/thumbnail, has_archive=false | 親投稿 (post_parent=0) = Work (作品)、子投稿 = Episode (話=目録エントリ)。menu_order=話数。単発作品は親のみ 1 投稿 (Work=Episode 1:1)。階層 CPT 1 本にすることで `/works/{work}/{ep}/` の入れ子パーマリンクがコア機能で得られる (data-model の 2 CPT 案より実装が薄い。区別は post_parent と `_ts_kind` メタ) |
| `ts_doc` | flat, rewrite `docs` | 運営コンテンツ: 編集"好"記 (comittee/ 21)、巻頭言 (columns/ 2)、構築日記 (dialy/ 1)、03summer 解題、語彙解題等 |
| `ts_dojo` | flat, rewrite `dojo`, supports comments (Phase 6 で登録) | 2ndbbs「ストーリー道場」作品 267 本 |
| 固定ページ | — | `/about/` `/takedown/` `/removed/` `/annex/` `/search/` `/index/` ハブ |

Work の本文にはシリーズタイトルページ (novel/**/title*.htm 約 175 個) の内容を転用し、無い作品は目録あらすじ+話一覧を自動生成する。

### 1.2 タクソノミー (対象: `ts_work`。`ts_author` は `ts_dojo` にも)

| taxonomy | rewrite | 語彙 | 要点 |
|---|---|---|---|
| `ts_author` | `authors` (コアの author_base=/author/ と衝突しない複数形) | 399 名 | **slug = 感想板 author_id** (目録参照 305 slug、board_index.json 289 板が権威)。表記揺れ 48 組 (バレット/石積ナラ=baretto、KEBO 3表記=kebo、大原野/池田=ohharano 等) を slug で統合。板なし 33 slug + 共有板のみ作者 31 名はローマ字新規採番。term meta: `ts_display_variants`(JSON), `ts_yomi`(五十音、lib-index 所属ページから初期値), `ts_kansou_slug`, `ts_kansou_annex_url`, `ts_homepage_wayback`, `ts_homepage_live`(現存移転先のみ), `ts_active_links`(なろう/pixiv 等 JSON), `ts_contact_status`(下記状態機械) |
| `ts_genre` | `genre` | 正規化後約 30 語 | NFKC (ＳＦ→SF)+「？」除去 (学園？→学園)+類義統合 (ミステリ→ミステリー, SS→ショートショート, 学園物→学園, ２次創作→二次創作, コメディー/ギャグ→コメディ)。genre.html の定義文を term description に転用。term meta `ts_raw_variants` |
| `ts_type` | `type` | 正規化後約 25 語 | TS 変化手段 (変身/魔法/人格転移/入れ替わり/憑依/転生/テクノロジー/薬品/医術/アイテム…)。可逆/不可逆/強制も同 taxonomy 内で正規化のみ |
| `ts_keyword` | `keyword` | 約 1,000 語 | 自由タグ。正規化は表記揺れのみ (オレっ娘 3 表記統合、「華代ちゃん」→華代ちゃん)。旧【属性】17 語 (メイド/ナース等) はここへ統合。post_tag ではなく専用 taxonomy にして ts_* 系で統一 (コア結合を避ける) |
| `ts_world` | `world` | 約 13 語 | 共有世界: kayo_chan, himekami, foster, relay_novel, delayed, corrector, dirty, mirror_ring, rental, setsubou, sugar, 2daime, utanotsuki (share_world.html + 共有感想板 7 つが元資料、Phase 1 で確定) |
| `ts_corpus` | (非公開 URL、絞込用) | honkan / legacy / dojo / anthology | 収蔵区分。世代の物語は taxonomy にせず `/index/timeline/` で解説。年代索引は post_date (=目録の掲載日) による `/year/YYYY/` で賄い taxonomy 化しない |

**contact 状態機械** (`ts_contact_status`、正本は catalog/authors.json): `uncontacted → notified → permitted / declined → takedown → removed`。段階 noindex 解禁の判断根拠を機械可読にする。

### 1.3 post meta (すべて `_ts_` prefix、register_post_meta で登録)

冪等キー: **`_ts_source_path`** (正規原パス、例 `novel/200209/19204751/d_upboy06.htm`) + `_ts_source_anchor` (`#JUNKO` 等)。ops-simple の「lib 番号+テーブル序数」鍵はパーサ改修で全件重複を生む脆い鍵のため不採用 (審査員1)。序数は `_ts_catalog_ref` (`lib17.html#12`) として情報メタに残す。

| キー | 内容 |
|---|---|
| `_ts_kind` | work / episode |
| `_ts_alias_paths` | JSON。md5 一致・別名重複ファイル群 (城弾シアターミラー 200907/201009 由来等)。正本 1 つを選び残りは alias |
| `_ts_orig_url` / `_ts_annex_url` / `_ts_annex_yays_url` | 当時の ts.novels.jp URL / 別館の原本 URL / ~yays 初出版 URL (相対パス一致 1,399 件のみ) |
| `_ts_pub_date_raw` | 目録日付の原表記 (`2000/10/8（日）` 等)。post_date はここから設定。**DDHHMMSS ディレクトリ名は「初回投稿バッチ」であり話の日付ではない (200209 配下に 2014 年の話が実在) — インポータ仕様書に明記** |
| `_ts_size_kb` / `_ts_files_count` | 目録のサイズ欄 (`154KB / 4FILES` 対応) |
| `_ts_illustrator` / `_ts_illustrator_url` | 画師 477 件 (単引用符 href 8 件、複数画師の 、/＆ 区切り対応) |
| `_ts_kansou_slug` / `_ts_kansou_annex_url` | 感想板 slug と板ログへの深リンク |
| `_ts_arasuji` / `_ts_author_comment` / `_ts_suisen` | あらすじ / 作者コメント / 推薦文 (編集部) |
| `_ts_osusume` / `_ts_osusume_in` | 読者オススメ出リンク (765 エントリ 988 対) / sync 時構築の被推薦逆引き |
| `_ts_nav_links` | 推薦文内ナビ 1,726 リンク (【第N話はこちら】等、話順検証用 JSON) |
| `_ts_raw_genre` / `_ts_raw_type` / `_ts_raw_keywords` / `_ts_zokusei` | 正規化前の原表記全保持 (史料性担保) |
| `_ts_provenance` | JSON: 回収経路 (wayback/megalodon/cc/live-site/pixiv)・スナップショット URL・取得日 — collinfo.json から転記 |
| `_ts_body_status` / `_ts_reflow_mode` | raw-html / converted / manual-needed、および診断分類 br-para / br-hardwrap / p / mso / custom (将来のブロック昇格の作業台帳 — reader-ux 案の発明を台帳としてだけ採用) |
| `_ts_body_sha256` / `_ts_import_hash` | 原本ハッシュ / 正規化レコード全体ハッシュ (差分スキップ用) |
| `_ts_episode_no` / `_ts_work_seed` / `_ts_needs_review` | 話数 / Work 抽出根拠 (series.html / share_world / titlepage / navlink / cluster / override) / 人手確認フラグ |

mailto 由来のメールアドレスは **catalog 生成段階で除外し WP の DB に構造的に入れない** (data-model の核。表示層の消し漏れが原理的に起きない)。

---

## 2. インポートパイプライン

### 2.1 スクリプト構成 (`scripts/wp/`、全段冪等)

```
scripts/wp/
  catalog_build.py      # lib1–73 (2,887) + 旧目録差分 → catalog/episodes.jsonl, authors.json,
                        #   terms.json, recommendations.jsonl。QAレポート付き
  work_builder.py       # Work クラスタリング → catalog/works.jsonl (+needs_review)
  body_extract.py       # 本文切り出し+最小施工 → bodies/{ep_id}.html (wp:html ペイロード)
  annex_build.py        # 原本ツリー → dist/annex/ (マスク+バナー+noindex+除外)
  gen_headers.py        # _headers / _redirects / robots.txt / sitemap.xml 生成
  verify_site.py        # マージ衝突検査・件数照合・lychee 起動
catalog/
  episodes.jsonl  works.jsonl  authors.json  terms.json  recommendations.jsonl
  work_overrides.yml  genre_map.yml  type_map.yml  keyword_map.yml  path_map.json
takedown/
  denylist.yml          # 削除の単一情報源
mu-plugins/ts-library/  # CPT/タクソノミー/メタ登録 + WP-CLI コマンド群 + テンプレタグ
```

### 2.2 各ステージ仕様

**(1) catalog_build.py** — 調査で 100% パース確定済みの戦略をそのまま昇格: `<TABLE BORDER=1>` 単位の findall (1 行に長大 HTML のため行単位パース禁止)、4 行×row1=6TD 固定、【】マーカー切り。フィールド regex は調査 spec を採用 (日付 `^(\d{4})/(\d{1,2})/(\d{1,2})(?:[(（]([月火水木金土日])[)）])?$`、サイズ `^(\d+)\s?KB(?:\s*/\s*(\d+)FILES)?$`、感想 `~ts/kansou/bbs@log_([A-Za-z0-9_\-]+)\.cgi`)。堅牢化: タグ大文字小文字無視、単引用符 href 許容、オススメ欄の壊れた入れ子 `<A>` はフラット findall、NFKC、ASCII+U+3000 空白 split、マーカーあり空値は NULL。**mailto はこの段階でリンク・表示テキストとも除去**。library.html (lib1 先頭 10 件の完全重複) と lib-index*.html はスキップ。旧目録 lib01–06 (252 エントリ、非テーブル形式) と lib07–09 (81 作品、【属性】/【作者様コメント】/`***`/2桁年/noteky 感想リンク対応) はパス prefix dedup 後の差分のみ `ts_corpus=legacy` で追加 (差分規模は Phase 1 で確定)。

**(2) work_builder.py** — シード = series.html 123 行 + share_world.html + タイトルページ約 175 個 + 推薦文の【シリーズタイトルはこちら】696 リンク。補完 = 作者×ファイル名 prefix (d_upboy, yama…)×目録タイトル共通接頭辞クラスタ。命名不統一 (dup_boy07 vs d_upboy09) と複数ディレクトリ分散 (城弾の 200209/200907/201009/201212) は `work_overrides.yml` で人手上書き。md5 一致・同文別名は正本 1 つ+ `_ts_alias_paths`。work_slug = `{author_slug}-{作品ローマ字}` (例 `johdan-d-upboy`) で衝突回避。needs_review リスト (推定 100–200 件) が本パイプライン最大の人手工数。

**(3) body_extract.py** — **決定: 本文は当面 raw 一律**。全審査員が BR 再フロー・mso 剥離の「気づかれない本文改変」リスクを指摘したため、施工は除去系 4 種のみ:
- (a) script 122 件全削除 (全て Geocities ビーコン/カウンタと確認済み)
- (b) mailto をリンク・表示テキストごと除去
- (c) サイト共通クローム剥がし (冒頭「戻る」〜2 番目の `<hr width=70%>`、末尾 `<hr>`〜「□感想はこちらに□」フッタ。87%/73% で定型確認済み) — 剥がした内容はメタに退避し、テーマのナビで置換
- (d) 相対 img src → 別館の絶対 URL へ書換

BR 再フロー・MsoNormal 剥離・旧 IE 式 ruby 書換は**行わない** (ruby は互換 CSS `rb{display:inline}` で吸収 — preservation 案)。出力は単一の `<!-- wp:html -->` ブロック。`_ts_reflow_mode` の診断分類だけ全件に付与し、将来「許諾作者分または高信頼定型のみ」の段落ブロック昇格 (Phase 6 任意) の作業台帳とする。特殊エントリ: toukou01–03.html はアンカー分割で各 Episode に配布、画像作品 21 件 (.jpg/.gif 直リンク) は figure 1 枚の Episode、外部絶対 URL 6 件と special/rb 1 件はリンクスタブ Episode (死にリンクは Wayback 書換)。

**(4) 画像** — **メディアライブラリに複製しない**。挿絵・絵文字 GIF 実体 1,195 個は別館の正本を絶対 URL 参照 (DB 肥大と再インポート増殖を防ぐ。同一ドメイン配信なので実害なし)。例外は eyecatch.jpg 109 件のみ featured image として sideload (`_ts_media_source` で dedup)。

**(5) 投入: `wp ts` コマンド群** (mu-plugin 内、WXR は冪等再実行に不向きのため不採用):

```
wp ts sync-terms --authors=catalog/authors.json --terms=catalog/terms.json
wp ts import --works=catalog/works.jsonl --episodes=catalog/episodes.jsonl \
             [--bodies=bodies/] [--dry-run] [--limit=N] [--author=slug]
wp ts apply-takedown --list=takedown/denylist.yml     # 該当投稿を draft 化
wp ts verify        # 件数照合 (2,887+legacy Δ / 作者399)・孤児・taxonomy被覆率
wp ts export-pathmap > catalog/path_map.json          # 原パス(alias含む)→WP URL
```

upsert は `_ts_source_path`(+anchor) の meta_query 検索 → 無ければ insert、あれば `_ts_import_hash` 一致で skip / 不一致で update。**2 回連続実行で差分ゼロ**が受け入れ条件。comment_status=closed を全投稿に強制。

**(6) `make publish` 一発化** (ops-simple):

```
make catalog   # catalog_build + work_builder + body_extract
make import    # compose up → sync-terms → import → apply-takedown → verify
make export    # Simply Static 書き出し → dist/wp/
make annex     # annex_build.py → dist/annex/
make site      # merge (annex 先・WP 予約名前空間のみ上書き) → pagefind --site dist/site
               #   → gen_headers.py → verify_site.py (衝突検査+lychee サンプル)
make deploy    # wrangler pages deploy dist/site
```

---

## 3. 情報設計と URL

### 3.1 名前空間の原則

ドキュメントルート = 別館の原パス空間。WP 本館は**予約名前空間**にのみ書き出し、ビルド時の衝突検査で予約外への上書きを機械的に禁止する。予約: `/works/ /authors/ /genre/ /type/ /keyword/ /world/ /year/ /index/ /search/ /docs/ /dojo/ /about/ /takedown/ /removed/ /annex/ /assets/` + ルートの `index.html robots.txt sitemap.xml _headers _redirects`。`/special/` は別館の実在パス (03summer 61 ファイル) のため WP は使わない。唯一の意図的衝突はルート index.html (WP トップが取る): 原本ルートトップ (2018 再建骨格、固有コンテンツ実質なしと調査済み) は `/annex/index-2018.html` に複製再掲し `/annex/` 案内に明記する。

### 3.2 パーマリンク

| ページ | URL 例 |
|---|---|
| トップ | `/` — サイト趣旨 (閉鎖サイトの資料保存)・収蔵統計 (2,887 作品/399 作者/1997–2014)・入口 4 つ (五十音/ジャンル/年代/検索)・**「新着」は置かず「今日の一作」** (works-index.json から日付シードでクライアント選出)・原本アーカイブの案内 (表示文言。「別館」とは書かない)・削除窓口 |
| 作品 | `/works/johdan-d-upboy/` (あらすじ・作者コメント・推薦文・話一覧・オススメ双方向・原本リンク) |
| 話 | `/works/johdan-d-upboy/06/` (単発は `/works/{slug}/` で完結) |
| 作者 | `/authors/writerman/` (全作品年代順・異表記・感想板 (別館) へのリンク・Homepage=Wayback・現役活動先) |
| 分類 | `/genre/gakuen/` `/type/henshin/` `/keyword/orekko/` `/world/kayo-chan/` |
| 年代 | `/year/2003/` (mu-plugin のカスタムリライトで post_date 年別一覧) |
| 索引 | `/index/` ハブ → `/index/kana/a/`〜`/index/kana/wa/` (五十音、lib-index の現代版)、`/index/timeline/` (1997–2014 年表+世代解説)、`/index/bunrui/` (化石索引 bunrui.html の SF/FT/リアル×器/核×自/他 マトリクスを解題付きで再構成)、`/index/vocabulary/` (genre.html 語彙解題)、`/index/osusume/` (推薦グラフ: 被推薦ランキング+推薦者別一覧) |
| 検索 | `/search/` — Pagefind (ビルド時索引・クライアントサイド・自己ホスト JS)。`data-pagefind-filter` で 作者/ジャンル/種別/年 のファセット絞込 |
| 運営 | `/docs/comittee-no1/` 等、`/about/`、`/takedown/`、`/removed/` (削除済みリスト)、`/annex/` (別館の 4 区画の案内+gallery/paintbbs 導線。**ページ上の見出し・本文は「原本アーカイブ」等の具体語で書き、「別館」とは書かない**) |
| 道場 | `/dojo/20031015213021/` (Phase 6) |
| 旧 URL | `/novel/200209/19204751/d_upboy06.htm`・`/lib12.html`・`/library.html`・`/series.html`・`/~ts/kansou/bbs@log_johdan.cgi`・`/~yays/…` — **原本ファイルがそのまま返る (リダイレクト不要)**。alias パスも実ファイルとして残り、バナーで同一 WP URL へ誘導 |

---

## 4. 読書体験

**テーマ**: 自作の軽量ブロックテーマ `ts-bunko` (theme.json + テンプレート数枚。機能は全て mu-plugin 側に置き、テーマは見た目のみ)。外部依存ゼロ: システムフォントスタック (游明朝/Hiragino Mincho/serif、ゴシック切替)、Google Fonts 不使用。本文カラム max-width 38em・行間 1.9。

**本文表示**: `.ts-reader` コンテナに原本 HTML (wp:html) を格納。デフォルト「整形表示」= コンテナ内の bgcolor/text/font color 属性を CSS で override (色トークンのみ、all:revert はしない) + 上記タイポグラフィ。**「原本配色」トグル**で当時の #EEEEEE 標準・黒背景変種もそのまま見られる。旧 IE 式 ruby は互換 CSS 同梱で無変換表示。

**リーダー UI** (素 JS + localStorage、プラグイン不使用、静的配信と完全両立):
- 文字サイズ 4 段階 / 行間 2 段階 / 明朝⇄ゴシック / ダークモード (prefers-color-scheme 追随+手動)
- 読了位置しおり (URL 単位、端末内完結) / キーボード ←/→ 話ナビ / サイズ KB からの読了時間目安
- **縦書きは実装しない** — 原本の縦書き指定は 3,817 ファイル中 1 件 (data-model の実測論拠を採用、preservation の opt-in 案は却下)

**Episode ページ構成**: パンくず (作者>作品>話) → メタヘッダ (初出日・サイズ・画師・分類チップ) → 本文 → 話ナビ [前話|作品目次|次話] (menu_order、`_ts_nav_links` で検証) → **書誌カード** (「」内は**読者に見える表示文言**。「別館」等の内部語は出さず、リンク先が別館であることは書かない): 「初出: ts.novels.jp {原パス} ({日付}) / 回収経路: {Wayback 等の人間可読表示} / 原本を見る / 初出時の姿 (~yays 2002 年版、存在する 1,399 件のみ) / 当時の感想を読む (作者感想板ログ)」→ 推薦文 (編集委員クレジット付き blockquote、`/docs/` の委員プロフィールへリンク) → オススメ双方向カード。

---

## 5. コミュニティ層

原則: **「当時の声は史料として見せる。新規の声は受けない。正本は常に別館」**。

- **kansou (作者単位板 289、投稿 2,866 件)**: **インポートも構造化表示もしない。深リンクのみ** (preservation の論拠「板は作者単位で作品単位でない — リンクが最も誠実」を全審査員が支持)。作者ページと各 Episode 書誌カードから該当板ログへ。将来の構造化タブは要決定事項 (実施時も恒久 noindex)。
- **2ndbbs ストーリー道場 (作品 267 + 感想 1,509 件)**: 感想が同一ページ埋込で紐付けが自明な唯一の層。Phase 6 で CPT `ts_dojo` + 読み取り専用アーカイブコメント (`comment_type='ts_archive'`、原日時・ハンドル保持・メール除去) として取込。カジノ/薬物スパム (13 ページ濃厚汚染) は NG ワード+言語判定でフィルタし、**除外ログ `catalog/dojo_excluded.jsonl` を残す** (何を史料から落としたかの記録 = 説明責任)。
- **~yays noteky f_0 (作品単位感想 63 ノート)**: Phase 6 で題名の完全一致・非曖昧マッチ分のみ該当 Work ページに「当時の感想 (2001–02 感想ボードより)」の読み取り専用史料ブロックとしてメタ表示 (コメント欄には入れない — 現役機能との誤認防止)。曖昧分は別館へのリンクのみ。
- **オススメ作品グラフ (765 エントリ・988 リンク)**: catalog 由来データなので Phase 4 から。作品ページに出リンク「{推薦者}さんのオススメ」+入リンク「この作品を挙げた読者」、`/index/osusume/` に被推薦ランキング。
- **除外/凍結**: rounge (94% スパム — 実スレ約 20 本の所在は `/annex/` 案内に記載)、~ts/bbs 3 板・resbbs4a・ezpe noteky・newinfo・paintbbs (163+59 枚)・~yays gallery (CG 313 点) は別館に凍結。gallery/paintbbs は `/annex/` からギャラリー導線のみ。
- **新規コメント: 全面閉鎖** (comment_status=closed 強制。rounge の 94% スパム化が実績根拠)。guestbook は静的方針と矛盾するため置かない (審査員1)。連絡は `/takedown/` の運営窓口 (メール+フォーム転送) に一本化し、「感想は作者の現役活動先 (なろう/pixiv、判明分は作者ページに記載) へ」と案内。**GitHub Issue 窓口は不採用** — 削除依頼者の身元と依頼内容を公開の場に晒す (審査員1・3)。

---

## 6. 静的ミラー (別館) との併存

**境界** (調査の boundary_proposal を採用): WP 化 = 第 2 世代リポジトリ直下の作品層のみ (novel/ 5,017 のうち本文 3,818 + 目録 + comittee/columns/dialy ≒ 全体の 30%)。別館に凍結 = 残り 70%: (1) 世代の姿 — ~ezpe (1999)、~yays (2000–02、gallery/paintbbs 含む。~yays/library/ は 95.4% 重複と知りつつ「2002 年春のタイムカプセル」として丸ごと)、ts-novels.jp 2018 再建骨格 (2) 交流ログ — ~ts (kansou/bbs 4,008)、~ezpe/~yays cgi-bin 約 4,800 (3) 閉鎖後姉妹 — ts.novels.name / kirika.novels.name / ts.raa0121.info / www.novels.name (4) 周辺 — ~yaji、www2.sts.co.jp。

**別館の配信物への施工** (annex_build.py、原ファイルは無改変):
- mailto 除去 (リンク属性+表示テキスト。目録 3,569 件・全体 6,445 ファイル)、BBS 投稿者メール除去
- 各 HTML 冒頭に 1 行バナー (**読者に見える表示文言**。「本館」とは書かない):
  「これは原本アーカイブです | 少年少女文庫で読む → {path_map.json から解決した WP URL} | 削除依頼」— ビルド時注入なので nginx sub_filter の gzip/charset 問題 (審査員1) は構造的に消える
- noindex (meta + `_headers` の X-Robots-Tag 両方)
- takedown/denylist.yml 該当ファイルの除外

**正規性**: canonical は常に WP 側 (本館は self-canonical、別館は全域恒久 noindex なので重複問題は発生しない)。**削除同期**: `takedown/denylist.yml` を単一情報源とし、(a) `wp ts apply-takedown` (draft 化) (b) annex_build の除外 (c) `functions/_middleware.js` (Cloudflare Pages Functions ~20 行) が該当パスに **410 Gone** を返す (d) git 履歴除去は docs/removal-runbook.md (git filter-repo、raw-original タグにも残る事実を明記) — 四層が同じファイルを読む。**リンク健全性**: 各 publish の verify で lychee サンプル検査、年 2 回フルクロール (バナー・path_map の黙った腐敗を検出)。

---

## 7. 権利・PII・SEO

**前提認識**: 権利者の明示許諾がない黙認+削除即応ベースの運用であり、WP 化は「編集・再公開」の色を強める。緩和策を設計に固定する:
1. 本文無改変原則 (raw wp:html。同一性保持権への配慮を形にする) + 全ページに作者名・初出日・出所 (回収経路) 明記 (氏名表示・出所明示) + 原本への常設リンク
2. 広告・収益化・寄付・外部送信プラグイン一切なし (非営利が黙認の生命線)
3. **公開前の作者連絡**: README 特定済みの連絡可能作者 (きりか進ノ介、toshi9、大原野山城守武里、天爛、なろう/pixiv 活動中の作者群 — MEMORY の連絡経路リスト起点) へ許諾依頼または事前告知。**現役サイトから直接回収した 18 ページ+挿絵は許諾必須**とし、得られるまで denylist で非公開。進捗は `ts_contact_status` 状態機械で管理
4. **削除窓口 `/takedown/`**: メール+フォーム転送。全ページフッタからリンク。本人確認は緩く (当時のサイト/なろう/pixiv アカウントからの連絡で足りる)。**受領後 72 時間以内の非公開化 SLA** を宣言。四層同期削除+`/removed/` に削除済みリスト (作者名+理由のみ) を公開し再収蔵を防ぐ

**PII**: mailto は WP=catalog 段階除外 (DB に入らない)、別館=配信物マスク。BBS ハンドル名は残置、実名らしきもの・生年月日入りアドレス等の高 PII は個別マスク。作者個人サイト URL 175 本は Wayback スナップショットリンクへ書換 (ドメインスクワット対策)、現存移転先のみ生リンク。git raw 層に未マスク原本が残り clone で取得可能な事実は README に明記 (「マスクは秘匿でなく礼儀」)。

**SEO / 段階解禁**: robots.txt を差し替え (現物は archive.org の誤回収物) — GPTBot/CCBot/Google-Extended 等 AI 学習クローラ Disallow、ia_archiver 許可。公開初期は `_headers` で**全域 X-Robots-Tag: noindex** (URL を知る人向け)。窓口整備+作者連絡完了後、`/works/ /authors/ /index/` 等の本館のみ解禁 (=グローバル noindex を外し別館の名前空間への恒久 noindex ルールに差し替え。名前空間マニフェストから gen_headers.py が生成)。sitemap.xml は解禁区画のみビルド時生成。BBS/別館/PII 層は恒久 noindex。

---

## 8. 運用

**構成**: 公開面 = **Cloudflare Pages** (ヘッダ制御 `_headers`・Pages Functions で 410 — GitHub Pages 単独では X-Robots-Tag も 410 も返せず takedown SLA が弱る、という審査員3の指摘に基づく決定。Netlify は 410 を `_redirects` だけで書ける代替)。WP オリジン = リポジトリ管理の `docker/compose.yml` (wordpress + mariadb、PHP/WP バージョン固定)、インターネット非公開、必要時のみ起動。常時稼働サーバゼロ = 保守・攻撃面・費用が実質ゼロ (ドメイン代のみ)。

**プラグイン最小構成 (3 点)**: (1) 自作 `ts-library` — **mu-plugins/ 配置** (管理画面から無効化不能 = コンテンツモデルが消えない。全審査員が推奨)。CPT/タクソノミー/メタ登録・WP-CLI コマンド群・meta robots/canonical 出力・テンプレタグを集約 (2) Simply Static (静的書き出し) (3) WP Multibyte Patch。SEO/キャッシュ/リダイレクト/Akismet/統計系は入れない。検索は Pagefind CLI (WP 非依存)。

**バックアップ/再現性**: 一次ソースは常に git (原本 + catalog/ + takedown/ + mu-plugin + テーマ + docker/)。WP DB は `wp ts import` で全再構築できる家畜。加えて publish のたびに `wp db export` を dumps/ (私有ストレージ) へ。uploads/ は eyecatch 109 件のみで再 sideload 可能。**年 1 回の「素の VM から `make publish` まで」再構築演習** (初年度は Phase 3 完了直後に 1 回実施) でドキュメント腐敗を防ぐ。ランブック 2 本: `docs/publish-runbook.md` / `docs/removal-runbook.md`。

**監視**: 静的につき最小 — UptimeRobot 無料枠 (任意) + publish 時 verify (件数照合・衝突検査・lychee サンプル) + 年 2 回リンクフルクロール。

---

## 9. 実装ロードマップ

| Phase | 期間目安 | 内容 | 成果物 |
|---|---|---|---|
| **0. 方針とガバナンス** | 1–2 週 | takedown フロー文書化、denylist.yml スキーマ、robots.txt 草案、ランブック骨子、作者連絡台帳初期化 (MEMORY 連絡経路)、ドメイン/ホスト決定 | 公開ポリシー文書一式、takedown/denylist.yml (空)、docs/removal-runbook.md v0 |
| **1. catalog 確立** | 2–3 週 | parse_lib.py / extract_taxonomy.py / author_inventory.py / inventory.json / board_index.json を scripts/wp/ へ移植・統合。catalog_build + work_builder。**Work クラスタの人手確認 (needs_review 100–200 件) が最重量タスク**。正規化マップ人手レビュー。旧目録差分確定 | catalog/*.jsonl 一式、QA レポート (2,887 件・パース率 100% 検証)、work_overrides.yml |
| **2. WP 骨格+メタ全量先行投入** | 2 週 | docker 環境、mu-plugin v0.1 (CPT/タクソノミー/メタ/wp ts コマンド)、パイロット 100 件→全量メタ投入、**冪等性テスト (2 連続実行で差分ゼロ)**、annex_build v0.1、make publish 開通 → **全域 noindex で限定公開**。本文なしでも目録・索引・作者ページが成立する **A 案相当の中間形** (reader-ux 案のマイルストーンを正式採用 — 作者連絡の反応が悪ければここで凍結できる退路) | 動く限定公開サイト (メタポータル+別館)、path_map.json |
| **3. 本文投入 (raw)** | 2 週 | body_extract、全話 wp:html 投入、eyecatch 109 件 sideload、無作為 100 件の原本併読 QA、`_ts_reflow_mode` 全件分類 | 全話が読める限定公開サイト、reflow 台帳レポート |
| **4. テーマ・索引・読書 UX** | 2–3 週 | ts-bunko テーマ、リーダー UI (しおり/←→/ダーク)、五十音・年表・bunrui・語彙・osusume 索引、Pagefind ファセット検索、書誌カード | 通読可能な β サイト (noindex のまま) |
| **5. 権利ゲートと段階解禁** | 作者返信次第 (Phase 3 以降並行) | 作者連絡発送、現役回収 18 ページの許諾確認 (未許諾は denylist)、/takedown/ 稼働、削除リハーサル (テスト作品で四層削除演習)、完了後に本館のみ index 解禁+sitemap | 公開サイト v1、連絡記録台帳、演習記録 |
| **6. コミュニティ史料層 (順次)** | 順次 | ts_dojo 取込 (スパム除外ログ付き)、noteky f_0 非曖昧マッチ表示、/annex/ ギャラリー案内。任意: 許諾作者分の段落ブロック昇格 (_ts_reflow_mode 台帳から)、kansou 構造化タブ (要決定) | community 取込一式 |
| **定常運用** | — | 削除対応 (72h SLA)、随時 publish、年 1 回再構築演習、年 2 回リンク検査 | 運用ログ |

**既存資産の再利用対応表**: parse_lib.py→catalog_build の核 / extract_taxonomy.py+taxonomy_report.txt→正規化マップ / author_inventory.py+inventory.json+board_index.json→authors.json と ts_author term / fix_links.py→annex_build の一括書換枠組み / collinfo.json→`_ts_provenance` / audit_full.py→verify の照合枠組み / 既存 Wayback 回収系→今後の欠落回収時の追補インポート / deploy-pages.yml→デプロイワークフローの雛形。

---

## 審査で割れた論点の決定一覧

| 論点 | 決定 | 根拠 |
|---|---|---|
| 本文をブロック変換するか | **raw (wp:html) 一律で開始。除去 4 種のみ。変換は Phase 6 任意で許諾作者/高信頼定型に限定** | 3 審査員全員が BR 再フローの「気づかれない本文改変」を最悪の故障モードと指摘。同一性保持権リスクも最小化。reflow_mode 台帳で将来の昇格経路は確保 |
| 本文を WP に入れるか (A 案 vs B 案) | **B 案 (入れる) だが、Phase 2 のメタのみ中間形 = A 案相当を正式マイルストーンにし、作品単位で A 案へ縮退可能な可逆構造** | 課題要件 (作品ライブラリ) と権利リスクの両立。全審査員が退路設計を最良の発明と評価 |
| 動的 WP 常時公開 vs 静的書き出し | **非公開オリジン+静的書き出し** | 10 年放置耐性・攻撃面消滅。コメント閉鎖・GET 検索のみなので失うものがない |
| 旧 URL 互換の方式 | **原パス空間をドキュメントルートに保持し WP を予約名前空間に重ねる静的マージ。リダイレクト表なし** | preservation の nginx 素通しの利点を、VPS なしで達成。3,900 行のリダイレクト DB も _redirects スタブも不要 |
| ホスティング | **Cloudflare Pages (ヘッダ制御+Functions で 410)。GitHub Pages 単独は不可** | X-Robots-Tag・410 が takedown SLA と段階 noindex の実装要件 (審査員3) |
| 画像の置き場 | **別館の正本を絶対 URL 参照。eyecatch 109 件のみ featured image** | DB 肥大・再インポート増殖の回避 (審査員1・2 が採用指示) |
| kansou 2,866 件 | **当面リンクのみ。コメント化も構造化表示もしない** | 作者単位板の作品コメント偽装は史料の改竄 (全審査員一致) |
| 新規コメント/guestbook | **全面閉鎖。guestbook も置かない** | rounge 94% スパム化の実績。静的方針との矛盾 (審査員1・2) |
| takedown 窓口の形態 | **メール+フォーム転送のみ。GitHub Issue 不採用** | 依頼者の身元・依頼内容を公開の場に晒す設計ミス (審査員1・3) |
| 縦書き | **不実装** | 原本の縦書き指定は 3,817 中 1 件 (実測)。装飾テーブル混在で崩れる |
| 冪等キー | **_ts_source_path (+anchor)。目録序数は情報メタのみ** | 序数キーはパーサ改修で全件重複を生む (審査員1) |
| マスク施工の場所 | **git 原本無改変・deploy artifact のみ施工** | ops-simple の別コミット直接施工より一段誠実 (審査員1)。施工スクリプト+デプロイブランチで来歴も担保 (審査員3) |
| バナー注入の方式 | **ビルド時注入 (serve-time sub_filter は不採用)** | 静的化で nginx 層が消え、gzip/charset の古典的罠 (審査員1・3) が構造的に消滅 |
| CPT 構成 | **単一階層 CPT ts_work (親=作品/子=話)** | 入れ子パーマリンクをコア機能で獲得し実装を最薄化。話順は menu_order |
| コミュニティ取込の優先順位 | **道場 (紐付け自明) → noteky f_0 (題名マッチ) → kansou (やらない)** | 紐付けの自明性順 (審査員2) |
| 登録コードの置き場 | **mu-plugins/ts-library/** | 無効化事故でコンテンツモデルが消えない (全審査員) |

---

## 要決定事項 (サイト所有者の選択待ち)

1. **公開ドメイン**: (a) 新規カスタムドメイン取得+Cloudflare Pages【推奨 — 中立で移転自由、原サイトの ts.novels.jp/novels.name は第三者管理下のため使用不可前提】 (b) *.pages.dev のまま (c) 現行 GitHub Pages ドメイン継続 (410/ヘッダ不可の制約を許容する場合のみ)。
2. **現行 GitHub Pages ミラーの処遇**: (a) 新静的サイトに一本化して廃止【推奨 — canonical と削除同期が単純化】 (b) meta noindex+誘導バナーを入れて並行継続 (冗長ミラーとしての価値はあるが削除四層が五層になる)。
3. **公開 git リポジトリの可視範囲**: (a) public のまま README 透明化【現状維持。ただし削除依頼が発生し始めたら再検討】 (b) private 化し deploy artifact (マスク済) のみ公開【権利照会が増えた場合の推奨】。clone で未マスク PII/原本が取得できる構造的問題への態度決め。
4. **作者連絡が到達不能多数だった場合の本文公開範囲**: (a) 全量公開継続+削除即応【推奨 — 黙認ベースの現行運用と整合】 (b) 連絡不能作者はメタのみ (作品単位 A 案縮退)。縮退トリガー (苦情初動/一定期間の異議) の基準値もあわせて決める。
5. **旧目録 (lib01–09) 差分の投入範囲**: (a) 重複排除後の全差分を ts_corpus=legacy で投入【推奨 — 1997–98 年の初期史料】 (b) 新形式 2,887 のみで開始し legacy は Phase 6 送り。
6. **kansou 構造化タブ (Phase 6 任意)**: (a) 実施しない (深リンク恒久)【推奨 — 誤紐付けゼロ・投稿者ハンドルを検索面に近づけない】 (b) 作者ページ限定・恒久 noindex で実施。
7. **サイト呼称と about 文面**: 「少年少女文庫 資料館」等の名乗り方、および運営者名義 (ハンドル/実名) の表示範囲。
---

# v1.1 改訂: XServer for WordPress 実測に基づく再設計 (2026-08-30)

移行先が **Xserver for WordPress**(`novels.xwp.jp` / sv3.xwp.ne.jp)に決定し、SSH で実地調査・実地テストを行った結果に基づく改訂。

## 実測結果 (ssh novels)

| 項目 | 実測 |
|---|---|
| 基盤 | wpX 系: **nginx キャッシュ → Apache (.htaccess 有効) → PHP 8.0.30**。Rocky Linux 8、一般ユーザ権限 (Docker 不可) |
| 設置済み | WordPress 7.1 @ https://novels.xwp.jp (素の状態 + CloudSecure WP Security 有効・ログイン URL 変更済み) |
| ツール | WP-CLI 2.8.1・mysql・rsync・git・curl・python3.6 が最初から利用可 |
| .htaccess | `Header set X-Robots-Tag` ✓ / `Redirect gone`(真の 410)✓ / mod_rewrite ✓ / `~`・`@` を含むパス ✓ — いずれも本番 URL で検証済み |
| **致命制約** | **ファイル名に `.cgi` を含む URL は前段 nginx が一律 403**。RemoveHandler・改名 (`.cgi.html`)・RewriteRule のいずれでも回避不能 (Apache に届かない)。ミラーの **9,022 ファイル**が該当 |
| 規模感 | ミラー全体 427MB / novel/ 画像 1,195 点 56MB / ホスト側ディスクは十分 |

## 再設計の決定

1. **二拠点分業が制約により確定** — 旧・要決定事項 Q2 の推奨「Pages 廃止・一本化」を撤回する。
   - **novels.xwp.jp** = 本館 = WP ライブラリ (公開面・canonical・全 2,887 話)
   - **GitHub Pages 現行ミラー** = 別館 = 原本 (`.cgi` を含む全 17,218 ファイルの URL 空間をそのまま維持 — 静的配信なので `.cgi` も無害に返せる。稼働実績あり)
   - 相互リンク: WP 書誌カード → Pages 原本 / Pages 側はビルド時バナー注入で `path_map.json` から WP へ誘導
2. **Docker は構成から除外** — catalog 生成は開発機、投入はサーバ上の WP-CLI。`wp ts import` が冪等で DB が使い捨てなので、**全域 noindex の限定公開期間中は本番サイト自体がステージング**。隔離実験が必要になった場合のみ docker context で他マシンを使う (任意・非必須)。
3. **権利ゲートは novels 側 .htaccess で実装** — 全域 `X-Robots-Tag: noindex` → 段階解禁、denylist からの `Redirect gone` 自動生成 (410)。**別館側 (Pages) はヘッダ制御不可**のため、ビルド時 meta noindex 注入+削除は 404 で妥協 (denylist 四層同期は従来どおり)。
4. **novel/ 画像 1,195 点 (56MB) は novels 側にも複製配備** — 読書体験を Pages 非依存にする (`/assets/annex-img/` に相対構造ごと rsync、本文の img src はそこへ)。eyecatch 109 件の featured image 化は従来どおり。
5. **mu-plugin は PHP 8.0 互換で書く** (サーバ CLI/web とも 8.0.30。パネルで上げられるなら 8.2+ 推奨)。
6. **インポート後の nginx キャッシュクリア**を publish 手順に追加 (wpX のキャッシュ削除操作)。CloudSecure のログイン URL 変更は現状維持。

## 改訂後のデプロイフロー

```
[開発機]  make catalog        # catalog_build + work_builder + body_extract (python3.11)
          rsync catalog/ bodies/ mu-plugins/ themes/ assets-img/  → novels:~/novels.xwp.jp/
[novels]  wp ts sync-terms && wp ts import && wp ts apply-takedown && wp ts verify
          (.htaccess を denylist から再生成 → 410 反映)
          キャッシュクリア
[Pages]   別館は既存の deploy-pages.yml のまま (バナー/noindex/マスクは今後ビルド注入に移行)
```

ロードマップへの影響: Phase 0 の「ホスト決定」は完了。Phase 2 の「docker 環境」は「novels SSH/WP-CLI 疎通 (確認済み)+rsync デプロイスクリプト」に置換。他フェーズは不変。

## 要決定事項の更新

- ~~Q1 公開ドメイン~~ → **決定: novels.xwp.jp** (将来カスタムドメインを被せる余地あり)
- ~~Q2 現行 Pages ミラーの処遇~~ → **決定: 別館 (原本レイヤ) として恒久併存** (プラットフォーム制約による必然)
- Q3〜Q7 は引き続き選択待ち

---

# v1.2 改訂: 本文の Markdown 正準化 (2026-08-30)

v1.1 の二拠点確定により「原文の手触りは別館 (GitHub Pages) が原 URL ごと恒久担保する」構図が
成立したため、v1.0 の「本文 raw 一律」決定を差し替え、**WP 側の本文は Markdown を正準とする**。
raw を選んだ根拠だった「気づかれない本文改変」リスクは、実測プローブで**機械証明できる**ことを確認した。

## 1. 正準構造

- `catalog/episodes.jsonl` = メタデータの正準 (従来どおり)
- **`bodies/{episode_id}.md` = 本文の正準** (git 管理 — 変換品質を diff でレビューできる。
  「単一真実源」原則がむしろ強まる)
- MD 方言: CommonMark + 許容インライン HTML は `<ruby>`(HTML5 形式へ正規化) と `<img>` のみ。
  シーン区切り (`<hr>`・＜＞連打) → `----`、見出し → `#`、引用 → `>`、段落 = 空行区切り、
  全角スペース字下げは保持
- インポート時に MD → Gutenberg (core/paragraph・separator・quote・heading・image)。
  `wp:html` は raw フォールバック専用となる

## 2. 変換パイプライン (`scripts/wp/body_convert.py`)

1. **ブラウザ等価の前処理** — script/style/コメント除去に加え、**bogus comment
   (`<!　…` 形式、次の `>` まで不可視) をブラウザと同一規則で除去**。実際に bem シリーズで
   作者が `<!　…!!` 記法で没稿を隠しているのをプローブが検出した。「当時の読者に見えていた
   テキスト」を正とする。この前処理は**原本側・変換側の両辺に同一実装を適用**する
2. クローム剥がし (戻るリンク・感想フォーム・フッタ) → メタへ退避
3. 構文写像 (既知構文表: BR 段落・P・hr・blockquote・h1-6・font/center 系除去・ruby・img・
   テーブル分類 (装飾タイトル枠=クローム / 本文表=そのまま HTML 保持))
4. hardwrap 再フロー — 検出時のみ (実測 0.4% と稀)
5. **無損失証明**: 「ブラウザ可視テキストの非空白文字列が原本と完全一致」を全話で機械検証。
   **合格した話だけ MD 正準に昇格、不合格は raw フォールバック** (`_ts_body_status=raw-html`)。
   全か無かにしない。証明結果は `catalog/convert_report.jsonl` に全話分記録 (説明責任)

## 3. 実測根拠 (scripts/wp/md_convert_probe.py、novel/ 無作為 800 件)

| 指標 | 値 |
|---|---|
| 素朴実装 (数時間分) で証明付き合格 | **75.5%** |
| テーブル含みでフラグ (装飾枠分類が必要) | 20% |
| Word HTML (`o:p`) | 2% |
| hardwrap 再フロー発動 | 0.4% |
| 不変量チェックが検出した変換バグ | 14 件 (v1) — 安全網の実証 |

不一致 8% の大半は比較経路の非対称 (本文中のエスケープ文字の処理順) によるもので、本実装では
両辺を単一の正規化器に通す仕様で解消する。**本実装の想定: 90–95% を MD 化、5–10% を raw 残置**。

## 4. 読書体験・権利への波及

- 変換済みの話はテーマの素の組版で表示 (v1.0 の「原本配色トグル」は raw フォールバック話のみに縮小)
- 全話の書誌カードに「**無改変の原本を読む**」(**表示文言**。リンク先は別館) を常設し、本文表示が**整形版である旨を明示**
  — 同一性保持権への配慮は「原本ワンクリック+機械証明ログ」で raw 方式と同等以上に立つ
- 検索・抜粋・関連表示・将来のテーマ変更が構造化ブロックの恩恵をフルに受ける

## 5. ロードマップ・決定の更新

- Phase 3 = 「**MD 変換投入 (証明付き) + raw フォールバック**」に差し替え (+1〜2 週:
  構文表の拡充・テーブル分類規則・両辺共通正規化器)
- Phase 6 の「ブロック昇格 (任意)」は本フェーズに吸収され消滅
- 決定一覧「本文をブロック変換するか」→ **「Markdown 正準+無損失証明、証明不合格のみ raw」に改訂**
  (v1.0 決定の根拠だった懸念は、別館による恒久担保と機械証明の実証により解消)

---

# v1.2 改訂: 本文 Markdown 正準化と感想板の近代ビュー (2026-08-30)

v1.1 の二拠点分業 (別館 = GitHub Pages が原文の手触りを恒久担保) を前提に、
本文表現と感想板の扱いを実測に基づいて差し替える。

## A. 本文は Markdown 正準 (v1.0「raw 一律」を差し替え)

**決定**: 本文の正準を `catalog/bodies/{episode_id}.md` (git 管理・diff レビュー可能) とし、
インポート時に MD → Gutenberg core ブロックへ変換する。**無損失証明に合格した話だけを MD 化**し、
不合格・未対応構文の話は raw (`wp:html`) フォールバック — 全か無かにしない。

**根拠** (v1.0 で raw を選んだ 2 つの理由が消えた):
1. 同一性保持権・「気づかれない本文改変」への懸念 → 別館に無改変原本が原 URL のまま常設され、
   全話の書誌カードからワンクリックで到達できる。WP 側は「整形版」と明示する。
2. 保証コスト → 無損失不変量 (下記) で機械的に証明できることを試作で実証。

**実測** (`scripts/wp/md_convert_probe.py`、800 件×3 シード):
- 証明付き合格 **75〜76%** (素朴実装のまま)
- 既知構文フラグ 23〜24% — 内訳はほぼ装飾/レイアウト `table` (151〜162 件)・Word の `o:p`・
  typo タグ (`storng`/`senter` 等、ブラウザは無視する) — いずれも変換規則の追加対象
- hardwrap (固定桁物理改行) 検出・再フロー 0.4〜0.8% — v1.0 で最大の敵とされたが実際は稀
- 証明不合格 0.1〜0.8% → raw フォールバック行き
- 本実装 (table 分類・typo 吸収・クローム剥がし統合) で **90〜95% の MD 化**を見込む

**変換仕様の要点**:
1. ブラウザ等価 preclean — script/style/コメント除去、bogus comment `<!…>` (没稿隠しに使われている)、
   **裸の `<` はテキスト扱い** (実データに存在)、感想フォーム除去。原本とプレビューの比較にも同じ preclean を使う
2. 構造化 — `<p>`/`<br>` → 段落 (空行区切り)、`<hr>` → separator、`blockquote` → 引用、
   `h1–h6` → 見出し、`ruby`/`img` はインライン HTML のまま温存 (MD は inline HTML 許容)
3. hardwrap 検出時のみ行末結合の再フロー (句読点・カギ括弧ヒューリスティック)
4. **無損失不変量** — タグ・空白・山括弧を除去し NFC 正規化した文字列が原本と完全一致すること。
   変換側が挿入する装飾 (separator 等) はセンチネルで区別し、挿入分だけを比較から除外する
5. メタデータは episodes.jsonl のまま (front matter にしない — 真実源の分離を維持)。
   `_ts_body_status` = md / raw-fallback / manual

**効果**: 全文検索・抜粋・関連度・テーマ差し替え・将来の EPUB 化が全てまともに機能する。
v1.0 の Phase 6「ブロック昇格 (任意)」はこの決定に吸収され消滅。

## B. 感想板 = 読み取り専用の「近代ビュー」(v1.0「リンクのみ」を差し替え)

**背景**: xwp の `.cgi` 403 は URL への制約であり、**中身を WP 投稿として取り込むことは妨げない**。
また v1.0 が感想板取り込みを退けた理由は「作品コメントへの偽装 = 史料改竄」であって、
**板を板として見せる**なら反対理由は消える。

**決定** (所有者委任: 「動く掲示板」vs「近代ビュー」→ 美しい方): **読み取り専用の近代ビュー**。
生きた掲示板は community 亡きあと必ず荒れる (実績: ラウンジ BBS はスレの 94% がスパム化)。
投稿フォームは置かず、「感想は作者の現役活動先へ」(作者ページに なろう/pixiv 記載) を案内する。

**実測** (`scripts/wp/kansou_parse_probe.py`): log 294 板中 219 板に投稿あり、log パース 97%
(6 板のみ要個別対応)、res 2,866 ファイル 100% パース、**log∪res の重複排除で約 3,978 投稿**
(調査時推定 2,866 を上回る回収)。件名・投稿者・日時・本文・Re: 親子が全て構造化抽出できる。

**モデル**: CPT `ts_board_post` (flat) + meta `_ts_board` (=作者 slug、`ts_author` と対応)・
`_ts_post_no`・`_ts_posted_at`・`_ts_poster`・`_ts_parent_id` (Re: ツリー)・`_ts_source_path`。
URL は予約名前空間に **`/boards/{author-slug}/`** を追加 (板ページ = スレッドツリーの
アーカイブテンプレート)。書誌カードの「当時の感想」リンクは別館への深リンクから
`/boards/{slug}/` へ差し替え (原本リンクは板ページ側に残す)。

**範囲と優先順**: ① kansou 294 板 (~3,978 投稿) ② 道場 2ndbbs (作品 267+感想 1,509、従来計画どおり
`ts_dojo`) ③ ~yays noteky f_0 (作品単位感想 63 ノート)。resbbs4a・ezpe noteky (雑談・推薦) は
任意の Phase 7、rounge は除外 (スパム 94%)。**PII**: 投稿者メールは catalog 段階で除去、
ハンドル名は残置、板ページは恒久 noindex (v1.0 方針を維持)。

## 決定一覧の差し替え

| 論点 | v1.0 | v1.2 |
|---|---|---|
| 本文の表現 | raw (wp:html) 一律 | **無損失証明付き Markdown 正準 (90〜95%) + raw フォールバック** |
| kansou 感想板 | 別館への深リンクのみ | **読み取り専用の近代ビュー (板として再現、/boards/)** |
| Phase 6 | ブロック昇格 (任意) | 感想板・道場・noteky の近代ビュー実装 |

ロードマップ影響: Phase 1 に MD 変換器の本実装 (+1 週)、Phase 3 は「MD 投入 (証明付き) + raw
フォールバック」、Phase 6 は感想板ビュー (+1〜2 週)。

---

# v1.2 補足 (2026-08-30、タスクリスト審査での正誤確定)

1. **「v1.2 改訂」章は本文書に 2 本あるが、後方の A/B 構成の章(本文 Markdown 正準化/感想板の近代ビュー)を正典とする。** 本文 MD の置き場は リポジトリ直下 `bodies/`(`catalog/bodies/` 表記は旧版)。
2. **検索方式の改訂**: Pagefind は「静的書き出し」前提が v1.1(公開面=ライブ WP)で消滅したため撤回し、**WP 標準検索+タクソノミーのファセット絞込**を採用する(2,887 投稿規模では十分)。
3. v1.0 の原則 2「公開面は完全静的・常時稼働サーバゼロ」は v1.1 のホスティング決定(novels.xwp.jp のライブ WP)により失効している — v1.1 改訂章が優先。
4. 実装の進行台帳は `docs/wp-implementation-tasks.md`(結合キー episode_id の定義・特殊エントリ 28 件の扱い・.htaccess 手順のガード等はそちらが正)。
5. (1.1 実装時の訂正) `_ts_provenance` の出所は collinfo.json ではなく **git 履歴**(回収コミット)。
   collinfo.json は CommonCrawl コレクション一覧であり来歴を持たない。個別キャプチャ timestamp は
   未記録のためスナップショット URL は Wayback 照会 URL 形式。~yays 初出版リンクは目録エントリ基準で
   724 件。WP upsert キーは episode_id(詳細は docs/wp-implementation-tasks.md)。

---

# v1.3 改訂: 原運営者公認のリブートとして (2026-08-30、要決定事項の回答を受けて)

## 前提の変更 (最重要)

**本プロジェクトは「八重洲メディアリサーチの持ち主の依頼による少年少女文庫のリブート」である。**
v1.0〜v1.2 が前提としていた「権利者の明示許諾がない黙認+削除即応ベースの運用」は、**原サイト運営者
(文庫の設立母体) の依頼という正統性の裏づけを得た**。設計への影響:

- **サイト呼称**: 「少年少女文庫」を原題のまま名乗る。about ページに「八重洲メディアリサーチの
  依頼により復元・再建したもの」である旨と、運営者名義 (ハンドル) を明記する
- **作者への向き合い方**: 個々の作品の著作権は依然として各原著者に帰属する (運営者の依頼は
  作者の許諾を代替しない)。ただし**原運営者という正統な紹介元がある**ため、作者連絡は
  「素性不明の第三者からの照会」ではなく「文庫からの再開のお知らせ」として行える。
  設立者 (八重洲一成氏) は多くの作者と当時の関係があり、**仲介・連名での告知が可能なら
  それが最良の経路**
- **削除依頼フロー・72h SLA・denylist 四層は維持** (公認であっても個別作者の意思が優先)
- **PII マスク (mailto 除去)・BBS 層の恒久 noindex も維持** (当時の投稿者は今回の依頼の当事者ではない)

## 要決定事項の回答 (2026-08-30、サイト所有者)

| # | 論点 | 決定 |
|---|---|---|
| Q1 | 公開ドメイン | **novels.xwp.jp** (Xserver for WordPress) — v1.1 で決定済 |
| Q2 | 現行 GitHub Pages ミラー | **別館 (原本レイヤ) として恒久併存** — v1.1 で決定済 (.cgi 403 制約) |
| Q3 | 公開 git リポジトリ | **public のまま**。README に「clone すれば未マスクの原本・当時のメールアドレスも取得できる」旨を明記して透明化する。権利照会が増えたら private 化を再検討 |
| Q4 | 作者連絡が不達の場合 | **全量公開+削除即応**。現行 Pages ミラーと同じ姿勢を WP でも継続する |
| Q5 | 旧目録 (lib01〜09) | **全差分を投入** (`corpus=legacy`)。1997.11〜の初期史料を目録に載せる |
| Q7 | サイト呼称・名義 | **「少年少女文庫」** を原題のまま。about に「八重洲メディアリサーチの持ち主の依頼によるリブート」と運営者ハンドルを明記 |

残る未決: なし (Q6 は v1.2 で決定済)。

---

# v1.4 改訂: 最大掲載原則 (2026-08-30、サイト所有者の方針)

## 基本方針

**サルベージしたもの、サルベージできるものは、すべて掲載する。**
v1.0 の「WP 化は第 2 世代の作品層のみ(全体の約 30%)、残り 70% は別館に凍結」という
範囲限定は撤回する。別館(GitHub Pages・原 URL 空間)は**原本レイヤとして維持**しつつ、
WordPress 側にも収蔵物を可能な限り載せる。掲載しないものは、下記の明示的な例外に限る。

## この方針で新たに掲載対象になるもの(実測)

| 対象 | 規模 | 扱い |
|---|---:|---|
| **目録に載っていない本文ファイル** | **871 本**(novel/ 配下の本文 3,818 のうち、lib1–73/lib01–09 のエントリに対応しないもの。実体は「ザ・ヒロイン」01–04・reborns_day・七色 等の**フラット期の実作品**) | 第二メタデータ源 **`lib-index-*.html`(五十音索引 2,796 行: 題名+作者)** で作者・題名を補い、それでも足りない分は本文の `<title>`・「作：」行から最小エントリを生成して収録。`corpus=uncatalogued` で区別 |
| ~yays 世代のギャラリー CG | 313 | 作品ページの挿絵とは別の「ギャラリー」区画として WP に収録(作者クレジットが取れるものは作者ページからも辿れるように) |
| 企画・アンソロジー `special/` | 61 | `ts_doc` またはアンソロジー区画として収録 |
| ~ezpe 世代の静的ページ | 8 | 文庫前史として `ts_doc` に収録 |
| 感想板・道場・noteky | 約 5,500 投稿 | v1.2 の決定どおり `/boards/` `/dojo/` に収録(恒久 noindex は維持) |
| 運営コンテンツ(編集"好"記・巻頭言・構築日記) | 24 | v1.0 どおり `ts_doc` に収録 |

`~yays/library/novel` の 1,466 は本体期 novel/ とほぼ同文の祖先スナップショット(固有作品はゼロと確認済み)
なので、WP には重複収録せず、各話の書誌カードから「初出時の姿」として別館へリンクする
(既存方針を維持)。

## 掲載しない例外(これだけ)

1. **denylist 掲載分**(削除依頼を受けたもの)
2. **スパム**(ラウンジ BBS のスレッド 94%、道場の汚染 13 ページ)— 除外ログを残す
3. **PII そのもの**(mailto アドレス)— 本文・投稿は載せるが、アドレスは catalog 段階で除去

### 事前保留は行わない(2026-08-30 サイト所有者決定「例外なし」)

作者が転載条件を掲げている場合でも、**事前保留はせず掲載する**。根拠は要決定 Q4 の決定
(全量公開+削除即応)と本章の最大掲載原則。該当していた案件を掲載に切り替える:

- **雨女**(おもちばこ) — 2011 年に文庫に掲載された作品。文庫版は失われており、**本文は
  作者本人の pixiv 再掲(id 351985)から取得**したもの。`provenance` に出所 `pixiv` と
  取得日を必ず記録する
- **きらいなもの→ＧＷ**(同) — pixiv のみで公開された番外編で、**文庫には一度も載っていない**。
  文庫の復元という趣旨からは対象外だが、所有者判断により収録する。
  `corpus=extern-repost` で区別し、書誌カードに「文庫未掲載・pixiv 由来」と明示する

**運用上の担保**: 作者(pixiv userid 1888568 / X @omochibako)は連絡可能な Tier A であり、
`docs/author-outreach.md` の連絡順序で最優先群に入る。連絡時に本件を明示し、
中止の意思が示された場合は 72h SLA で denylist に載せて全層から削除する。
(この 2 作は pixiv プロフィールに「転載前に問い合わせを」と明記されている作品であり、
掲載は所有者の判断であること、削除要請が来る可能性が相対的に高いことを記録として残す。)

## 追加素材が入ったときの扱い

八重洲さん・移管先管理者・作者から原本が提供される可能性がある(~yays 時代の控え、
ts.novels.jp 時代のサーバデータ、作者の手元原稿)。**入るか入らないか不確実なので、
現在手元にあるものだけで公開まで完走する設計とし、後から入手したものは catalog に足して
再インポートすれば反映される**(単一真実源+冪等インポートの構造がそれを保証する)。
提供素材は `provenance` に出所(提供者・提供日)を記録する。
