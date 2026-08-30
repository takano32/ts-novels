# 少年少女文庫 → WordPress ライブラリ 実装タスクリスト v1.1

**このファイルが実装の単一の進行台帳。** 実行セッション(Opus 想定)は毎回これを最初に読み、
完了したタスクの `[ ]` を `[x]` に変えてコミットしながら進めること。
(v1.1: 文脈ゼロ実行者視点の 3 体審査で出たブロッキング 14 件を反映した改稿)

## 0. 実行セッションの心得(毎回読む)

**読み順**: ① このファイル → ② `docs/wordpress-library-design.md`(設計書。v1.0 本文+v1.1/v1.2 改訂章。
**改訂章が本文に優先**。「v1.2 改訂」章は 2 本あるが**後方の A/B 構成の章が正典**) →
③ `scripts/README.md` → ④ `scripts/wp/` の実装済みプローブ 2 本
(`md_convert_probe.py` = 本文変換+無損失証明の手本 / `kansou_parse_probe.py` = 感想板パースの手本) →
必要に応じ `scripts/workflows/wp-survey-2026-08-30.json`(8視点実測調査。
**目録パース仕様の正規表現・出現率・例外列挙は catalog 節 fields にある** — Phase 1 の仕様書)。

**消滅済み中間生成物に注意**: 設計書が言及する `parse_lib.py` / `extract_taxonomy.py` /
`author_inventory.py` / `inventory.json` / `board_index.json` は一時領域の消滅で**現存しない**。
探さないこと。survey JSON の仕様から再実装する: 板一覧は `~ts/kansou/bbs@log_*.cgi` の再走査、
語彙頻度表は catalog_build の出力から再集計(survey には top-50 しか残っていない)。

**環境の確定事実**(調査済み・再確認不要):
- 本番: `ssh novels` = Xserver for WordPress。`https://novels.xwp.jp`、WP 設置済み、
  wp-config.php は `~/novels.xwp.jp/` 直下、公開ルートは `~/novels.xwp.jp/public_html/`
- サーバ内ツール: WP-CLI 2.8.1 / mysql / rsync / git / **PHP 8.0.30**(コードは 8.0 互換)/ python3.6
- 構成: nginx キャッシュ → Apache(.htaccess 有効)→ PHP。`Header set X-Robots-Tag` ✓・
  `Redirect gone`(410)✓・rewrite ✓ は本番検証済み
- **地雷: ファイル名に `.cgi` を含む URL は前段 nginx が一律 403**(回避不能)。だからアネックス
  (= 現行 GitHub Pages ミラーの**全 17,218 ファイルの URL 空間**。`.cgi` スナップショット 9,022 を含む)
  は GitHub Pages 恒久併存(設計 v1.1)
- **nginx キャッシュの罠**: 変更が反映されないように見えたら、まずキャッシュを疑う。
  検証 curl には毎回キャッシュバスター(`?_cb=<乱数>`)を付ける。パネルからのキャッシュ全消しは
  ブラウザ操作のため 👤 タスク。**「反映されない」と誤認して .htaccess や mu-plugin を
  修正し続けるループに入らないこと**
- WP 管理画面もセッションからは開けない(ログイン URL は変更済みで、その値の転記は禁止=規則 7)。
  検収は **WP-CLI(`wp post list` / `wp post meta get` 等)+curl** で行う
- 開発機: このリポジトリ(python3.11、pip 使用可)。catalog 生成は開発機、投入はサーバ上 WP-CLI

**結合キーの定義(全フェーズ共通)**:
- 冪等キー = **`_ts_source_path` + `_ts_source_anchor`**(toukou01–03.html のアンカー分割・
  アンカー付き href 5 件があるため、パス単独では衝突する)
- **episode_id** = `_ts_source_path` の `/` を `__` に置換し、anchor があれば `@<anchor>` を付加
  (例: `novel__200209__19204751__d_upboy06.htm`、`novel__toukou01.html@JUNKO`)。
  episodes.jsonl ↔ `bodies/` ↔ convert_report ↔ import の結合はすべてこの id
- 本文 MD の置き場 = リポジトリ直下 **`bodies/`**(設計書の `catalog/bodies/` 表記は旧版)

**普遍ルール**:
1. **単一真実源は `catalog/`**。WP DB は使い捨ての派生ビュー。WP 管理画面での手動編集は禁止、
   修正は必ず catalog 側 overrides ファイルで
2. すべてのインポートは冪等(上記キーで upsert)。**受け入れ条件は「2 回連続実行で 2 回目が
   created=0 / updated=0」**。import コマンドは created/updated/skipped を集計出力すること
3. 新スクリプトは `scripts/wp/` に置き、`scripts/README.md` に 1 行追記してコミット
4. メールアドレス(mailto 由来の値)は catalog 生成段階で除去し、**WP の DB に入れない**
5. 長時間タスクは着手前に概算 ETA を進行メモに書く。**破壊的・本番操作の前に復旧点を作る**
   (DB は `wp db export`、.htaccess は `.bak-日付` コピー)
6. 本番 `public_html` での実験は `_probe/` ディレクトリで行い、終わったら必ず消す
7. CloudSecure のログイン URL 変更値(.htaccess 内に記載)を文書・コミット・ログ.htaccess 写しに
   転記しない。**本番 .htaccess の全文をリポジトリに入れない**
8. サイトは公開解禁タスク **7.4** まで全域 noindex を維持
9. rsync は **`--delete` 禁止・必ず `-n`(dry-run)を先行**。宛先 `~/novels.xwp.jp/` 直下には
   wp-config.php が居る

**目録パースの地雷**(詳細は survey JSON の catalog 節):
- 正規目録は `lib1.html`〜`lib73.html`(**ゼロ埋めなし**)。`lib01`〜`lib09.html` は**別物**の旧目録
  (lib01–06 = 非テーブル 252 エントリ、lib07–09 = 移行期テーブル 84 ブロック中実作品 81。
  【属性】欄・2桁年・`***` 欠損値あり)
- `library.html` の 10 件は lib1 先頭 10 件の完全重複 — スキップ。`lib-index*.html` は索引でエントリなし
- エントリは `<TABLE BORDER=1>` 単位・必ず 4 行構成・1 行目 6 セル。長大 1 行 HTML なので行単位パース禁止
- タグ大文字小文字混在・単引用符 href あり・オススメ欄に壊れた入れ子 `<A>` → フラット正規表現で
- NFKC 正規化必須(ＳＦ/SF)。多値は ASCII+全角空白で分割。マーカーあり空値は NULL(5〜15%)
- **投稿ディレクトリ名 novel/YYYYMM/DDHHMMSS は「作者の初回投稿バッチ」であり話の日付ではない**
  (200209 配下に 2014 年の話が実在)。post_date は必ず目録の日付欄から
- 本文 HTML: bogus comment `<!　…!!`(没稿隠し)と裸の `<` はブラウザ等価処理(preclean の手本は
  `md_convert_probe.py`)
- series.html の「123 行」はコメントアウト除去後の有効行(生 TR は 138)

**人間(サイト所有者)しかできないタスクは 👤 印**。実行セッションは 👤 タスクに当たったら
スキップして次へ進み、最後に「👤 待ち」一覧を報告する。

---

## Phase 0 — 方針とガバナンス(目安 1 週)

- [x] 0.1 `takedown/denylist.yml` のスキーマを決めて空ファイル+README を作る
      (エントリ: 対象 = work slug / episode の source_path / アネックスパス、理由、日付、状態)
- [x] 0.2 `docs/removal-runbook.md` v0: 削除依頼受領 → 72h 以内に
      ①WP draft 化 ②.htaccess 410 追記 ③アネックス(Pages)側除外 ④**git 履歴除去
      (git filter-repo。raw-original タグにも残る事実と対応方針を明記)** ⑤`/removed/` 更新、
      の **四層+掲示** を文書化
- [ ] 0.3 `docs/publish-runbook.md` v0: catalog 再生成 → rsync → `wp db export`(復旧点)→
      `wp ts import` → verify → 👤 キャッシュクリア依頼、の定常手順(コマンド列)を文書化
- [ ] 0.4 robots.txt 草案(AI 学習クローラ Disallow・ia_archiver 許可)を `scripts/wp/assets/` に用意
      ※ リポジトリ直下の現 robots.txt は Wayback 誤回収物なので参照しない。配備は 2.3
- [ ] 👤 0.5 作者連絡台帳の初期化と残り要決定の判断(設計書「要決定事項」の **Q3/Q4/Q5/Q7**。
      Q5〔旧目録の投入範囲〕が未決の間、1.2 は推奨案 (a)=全差分投入 を仮採用して進めてよい)

## Phase 1 — catalog 確立(目安 2〜3 週。ここが土台)

- [ ] 1.1 `scripts/wp/catalog_build.py`: lib1〜73 の 2,887 エントリを JSONL 化
      - survey JSON の fields 節の正規表現をそのまま実装(パース率 100% 検証済みの仕様)
      - 出力 `catalog/episodes.jsonl` の必須フィールド: **episode_id / source_path / source_anchor** /
        title / author / homepage(URL のみ。mailto は捨てる) / illustrator(+URL) / date_raw /
        size_kb / files_n / kansou_slug / arasuji / comment / osusume{recommender,refs[]} / suisen /
        nav_links[] / genre[] / type[] / keywords[] / zokusei / catalog_ref /
        **orig_url**(`http://ts.novels.jp/<source_path>`) / **annex_url** /
        **annex_yays_url**(`~yays/library/` に同一相対パスが実在する場合のみ。機械判定・約 1,399 件) /
        **provenance**(リポジトリ直下 `collinfo.json` から回収経路・スナップショット URL・取得日を転記)
      - **受け入れ: エントリ数 2,887・パース失敗 0・mailto 由来の値の残存 0
        (検査は「mailto: リンクから抽出した値が出力に無いこと」をロジックで確認する自動テストを同梱。
        シェル検査を使うなら `! grep -q` 形式にし、`index@08201937.html` 型ファイル名や homepage URL を
        誤検知しないこと)・provenance 被覆率をレポート**
- [ ] 1.2 同スクリプトで旧目録 lib01〜09 を差分パース(`corpus=legacy`)。パス prefix 重複排除後の
      追加分のみ。受け入れ: 追加件数を QA レポートに記録
- [ ] 1.3 `scripts/wp/terms_build.py`: `catalog/terms.json` 生成
      - genre 244→約 30 語・type 187→約 25 語。正規化マップは `catalog/genre_map.yml` /
        `type_map.yml`(NFKC+「？」除去+類義統合)。**頻度表は catalog_build 出力から全語彙を
        自前集計**(survey は top-50 のみ)
      - keywords 約 1,000 語(表記揺れ統合のみ)。旧【属性】17 語は keywords へ
      - **ts_world 13 語**(share_world.html+共有感想板 7 つが元資料)と **ts_corpus 4 語**もここで生成
      - 原表記は必ず `raw_variants` に保持
- [ ] 1.4 `scripts/wp/authors_build.py`: `catalog/authors.json` 生成
      - slug = 感想板 author_id(目録の `bbs@log_<id>.cgi` リンク。305 slug)。表記揺れ統合 48 組
      - **板なし作者 33 名のローマ字 slug は pykakasi(開発機 pip 導入可)で候補生成し、
        `catalog/slug_overrides.yml` に全数出力 → 👤 確認後に確定**(恒久 URL のため幻覚読み厳禁)
      - meta: display_variants / yomi(lib-index-*.html の所属行から) / homepage /
        homepage_wayback(`https://web.archive.org/web/2010/<URL>` 形式で機械生成) /
        kansou_annex_url / contact_status(全員 `uncontacted`)
      - 受け入れ: 作者数 ≈ 399・全 episode の author が解決
- [ ] 1.5 `scripts/wp/work_builder.py`: Episode → Work クラスタリング → `catalog/works.jsonl`
      - シード: `series.html` 有効 123 行(コメント除去後)+`share_world.html`+
        **シリーズタイトルページ(novel/ 配下の `*title*.htm*` および投稿ディレクトリ内 index.html 名、
        計約 124+旧世代ツリー内 51)**+推薦文内ナビ 696 リンクの誘導先 URL 集合
      - 補完: 作者×ファイル名 prefix×題名共通接頭辞クラスタ
      - あいまい分は `needs_review: true` で `catalog/work_overrides.yml` 雛形に出力(想定 100〜200 件)
      - work_slug = `{author_slug}-{作品ローマ字}`。**作品ローマ字も pykakasi 候補+
        `slug_overrides.yml` 経由の 👤 確認ゲートを通す**。重複ファイルは正本 1 つ+`alias_paths`
      - 受け入れ: 全 episode がいずれかの work に属す。orphan 0
- [ ] 👤 1.5b work_overrides.yml と slug_overrides.yml の確認・確定。
      **これが済むまで Phase 2.5(全量投入)に進まない**(needs_review 分の恒久 URL が変わるため。
      2.4 のパイロット 100 件は確定済み分のみで先行可)
- [ ] 1.6 `scripts/wp/body_convert.py`: 本文 Markdown 変換(設計 v1.2 改訂章 A「本文 Markdown 正準化」)
      - `md_convert_probe.py` を本実装に昇格。追加対応: 装飾/レイアウト table の分類(title 箱=
        クローム除去、本文内 table=インライン HTML 温存)、Word `o:p`/`MsoNormal` 剥離、
        typo タグ(storng/senter 等)吸収、クローム剥がし(冒頭「戻る」〜2 個目 hr、末尾感想フッタ)
      - **特殊エントリ 4 分類**: ① toukou01–03.html は `<a name>` アンカーで各話に分割
        (episode_id に @anchor) ② 画像作品 21 件(.jpg/.gif 直リンク)は figure 1 枚の本文
        ③ 外部絶対 URL 6 件+special/rb 1 件はリンクスタブ本文(死にリンクは Wayback URL に書換)
        ④ 通常 HTML
      - **無損失証明**: タグ・空白・山括弧除去+NFC の文字列が原本と完全一致した話だけ
        `bodies/{episode_id}.md` に出力。不合格は raw フォールバック台帳へ。
        全話の判定を `catalog/convert_report.jsonl` に記録
      - **受け入れ: 証明合格率 ≥85%(目標 90〜95%)・合格分の不変量違反 0・
        乱数抽出 20 話の目視比較で本文欠落なし**
- [ ] 1.7 `make catalog` で 1.1〜1.6 を一発実行できる Makefile。QA レポートを `catalog/QA.md` に
      (件数・合格率・needs_review 数・provenance 被覆率・特殊エントリ内訳)

## Phase 2 — WP 骨格+メタ全量投入(目安 2 週)

- [ ] 2.1 `mu-plugins/ts-library/` v0.1(リポジトリ管理→rsync 配備): CPT `ts_work`(hierarchical,
      rewrite=works)・`ts_doc`・**`ts_dojo`(登録のみ。使用は Phase 6)**・`ts_board_post`(同)・
      タクソノミー 6 本・`register_post_meta`(`_ts_*`)・meta robots noindex 出力・
      comment_status=closed 強制。PHP 8.0 互換。定義は設計書 §1 の表のとおり。
      あわせて **WP Multibyte Patch を有効化**(`wp plugin install wp-multibyte-patch --activate`)
- [ ] 2.2 `wp ts` WP-CLI コマンド群(mu-plugin 内): `sync-terms` / `import`(--dry-run/--limit/
      --author、created/updated/skipped 集計出力) / `apply-takedown` / `verify`(件数照合・orphan・
      taxonomy 被覆率・**特殊エントリ内訳**・冪等性=2 回目 created/updated 0) / `export-pathmap`
- [ ] 2.3 デプロイスクリプト `scripts/wp/deploy.sh`: rsync(`-n` 先行・`--delete` 禁止)で
      catalog/ bodies/ mu-plugins/ を配備(mu-plugins → `public_html/wp-content/mu-plugins/`)。
      **robots.txt(0.4 草案)を `public_html/` へ配備し curl で内容確認**もここに含める
- [ ] 2.4 サーバでパイロット投入: `wp ts import --limit=100`(1.5b 確定済み分のみ)→ 検収は
      **WP-CLI(`wp post list --post_type=ts_work`、meta 確認)+curl(キャッシュバスター付)**で:
      パーマリンク・タクソノミー・メタ・noindex メタ/ヘッダ
- [ ] 2.5 全量メタ投入(本文なし)。直前に `wp db export` で復旧点。
      **冪等性テスト: 2 回目 import が created=0 / updated=0**
- [ ] 2.6 .htaccess の noindex/410 ブロック管理:
      (a) サーバ上で `cp .htaccess .htaccess.bak-YYYYMMDD` を必ず先行
      (b) `scripts/wp/gen_htaccess.py` は**マーカーコメント間の追記ブロックのみ**をローカル生成
      (c) 挿入は ssh 経由のサーバ上編集で行い、**既存 .htaccess 全文をローカルに保存・コミットしない**
      (d) 直後に無関係 URL 1 本の 200 と `X-Robots-Tag` ヘッダを curl(バスター付)で確認、
      失敗時は .bak を戻す
- [ ] 2.7 novel/ 画像 1,195 点(約 59MB)を `public_html/assets/annex-img/` に相対構造ごと rsync

## Phase 3 — 本文投入(目安 2 週)

- [ ] 3.1 本文投入: **MD→Gutenberg ブロック HTML への変換は開発機の python で事前実施**し、
      投入用 payload を生成して rsync → `wp ts import --bodies` は payload を post_content に
      流すだけにする(サーバ PHP に Markdown パーサを導入しない)。ブロック対応:
      paragraph/separator/quote/heading/image。ruby と本文内 table はインライン HTML。
      raw フォールバック分は `wp:html` 1 ブロック+`_ts_body_status=raw-fallback`
- [ ] 3.2 img src を `assets/annex-img/` 参照に書換。**eyecatch 画像(実体 4 ファイル・参照 15 話)**は
      dedup して該当話の featured image に設定(survey の「109 件」は参照回数であり実体数ではない)
- [ ] 3.3 無作為 100 話の原本併読 QA(アネックス原本と並べて本文欠落・順序破壊がないか)。
      結果を `catalog/QA.md` に追記。**問題率 >2% なら 1.6 に戻る**
- [ ] 3.4 verify 拡張: 全話に書誌カード用メタ(初出日・orig_url・annex_url・provenance)が
      揃っているか被覆率で確認
- [ ] 👤 3.5 (前倒し推奨) 作者連絡の発送開始 — 設計は「Phase 3 以降並行」。返信待ちを
      公開のクリティカルパスにしないため、ここで発送しておく(7.1 はその継続)

## Phase 4 — テーマ・索引・読書 UX(目安 2〜3 週)

- [ ] 4.1 ブロックテーマ `ts-bunko`(リポジトリ管理→rsync 配備): 設計書 §4 のとおり。
      システムフォントスタック・本文 38em・書誌カード(初出/回収経路/原本リンク/~yays 初出版/
      当時の感想)・話ナビ(menu_order)。**タクソノミーアーカイブ(genre/type/keyword/world)は
      テーマの標準テンプレートで提供**(=「7 索引」の内数)。
      **作者ページの Homepage リンクは既定で homepage_wayback、現存確認済みの移転先のみ生 URL**
      (ドメインスクワット対策)。外部 CDN/フォント読み込みなし
- [ ] 4.2 リーダー UI(素 JS+localStorage): 文字サイズ/行間/明朝ゴシック/ダーク/しおり/←→話ナビ。
      raw フォールバック話のみ「原本配色」トグル
- [ ] 4.3 索引ページ群: `/index/kana/{a..wa}/`・`/index/timeline/`・`/index/bunrui/`・
      `/index/vocabulary/`・`/index/osusume/`(`_ts_osusume(_in)` から)
- [ ] 4.4 `/year/YYYY/` リライト(mu-plugin)とトップページ(「今日の一作」= 日付シード選出、
      「新着」は置かない)
- [ ] 4.5 検索: WP 標準検索+タクソノミーのファセット絞込テンプレート
      (設計書の Pagefind は静的書き出し前提が v1.1 で消えたため置換 — 設計書側にも追補済み)
- [ ] 4.6 運営コンテンツ投入: `ts_doc` に comittee/ 21・columns/・dialy/・03summer 解題。
      固定ページ `/about/`・`/annex/`(4 区画案内)・`/removed/`(空)・
      **`/takedown/`(72h SLA を明文で宣言)。全ページフッタに /takedown/ へのリンク**

## Phase 5 — アネックス連携(目安 1 週)

- [ ] 5.1 `wp ts export-pathmap > catalog/path_map.json`(原パス+alias → WP URL)
- [ ] 5.2 GitHub Pages 側デプロイをビルド注入方式へ: `scripts/wp/annex_inject.py` —
      デプロイ artifact 生成時に (a) mailto 除去(リンクとテキスト両方。対象ファイル数は
      ビルド時に再計測 — 参考実測 6,515) (b) 各 HTML 冒頭に 1 行バナー
      「原本アーカイブ | 整理版で読む → path_map の該当 URL」 (c) meta noindex 注入
      (d) denylist 除外。**git 原本は無改変**(deploy-pages.yml を artifact ビルドに改修)。
      受け入れ: Pages 上で mailto 0・バナー表示・原 URL 全部生存(lychee サンプル)
- [ ] 5.3 WP 書誌カードの「原本を見る」リンク先を Pages 実 URL で検証(サンプル 200)

## Phase 6 — 感想板の近代ビュー(設計 v1.2 改訂章 B。目安 1〜2 週)

- [ ] 6.1 `scripts/wp/boards_build.py`: `~ts/kansou` の log 294+res 2,866 をパース
      (`kansou_parse_probe.py` が手本: log 97%/res 100% 実証済み)。log∪res をタイムスタンプ ID で
      重複排除 → `catalog/board_posts.jsonl`(約 3,978 投稿)。投稿者メール除去。
      Re: 親子は res ファイル名の親 ID から
- [ ] 6.2 `/boards/{author-slug}/` テンプレート(スレッドツリー・投稿フォームなし・**恒久 noindex**)。
      書誌カードの「当時の感想」を板ページへ差替(原本リンクは板側に残す)
- [ ] 6.3 道場: 2ndbbs 作品 267 を `ts_dojo` に、埋込感想 1,509 を読み取り専用アーカイブコメントに
      (スパム 13 ページは除外し `catalog/dojo_excluded.jsonl` に記録)。`/dojo/` も恒久 noindex
- [ ] 6.4 ~yays noteky f_0 の 63 ノートを題名完全一致分のみ該当 Work の史料ブロックに

## Phase 7 — 公開ゲート(👤 中心)

- [ ] 👤 7.1 作者連絡の完了確認(3.5 の継続。台帳の contact_status 更新)。現役サイト直接回収
      18 ページ+pixiv 許諾待ち 2 作(`~/ts-novels-holding/` 保管中)は許諾が取れるまで denylist
- [ ] 7.2 削除リハーサル: テスト作品 1 件で「draft 化+410+アネックス除外+**git 履歴除去の手順確認**+
      /removed/ 掲載」を実際に流し、removal-runbook.md を実測で更新
- [ ] 👤 7.3 公開判断(Q4: 連絡不達作者の扱い)
- [ ] 7.4 解禁: .htaccess を差し替え —
      **解禁(index 許可)**: `/works/ /authors/ /genre/ /type/ /keyword/ /world/ /year/ /index/
      /docs/ /about/ /takedown/ /removed/` とトップ。
      **恒久 noindex 維持**: `/boards/ /dojo/ /assets/annex-img/` ほか BBS/PII 層
      (アネックス=Pages 側は 5.2 の meta noindex のまま)。
      sitemap.xml は解禁区画のみ生成。robots.txt の本番内容を curl で最終確認。
      Search Console 登録は 👤 任意
- [ ] 7.5 公開後 verify: 主要 20 URL の 200/内容確認・**noindex 境界の確認(/boards/ が noindex の
      まま、/works/ が解禁されていること)**・👤 キャッシュクリア依頼

## 定常運用(公開後)

- [ ] 8.1 publish のたび: `wp db export` を dumps/(私有ストレージ。公開リポジトリに入れない)へ
- [ ] 8.2 年 2 回: lychee でリンクフルクロール(WP+Pages 両方)
- [ ] 8.3 再構築演習: **初回は Phase 3 完了直後**、以後年 1 回 — 素の環境から
      `make catalog` → deploy → import で同一サイトが再現できることを確認し、手順書の腐敗を検出
- [ ] 削除対応は removal-runbook.md に従い 72h SLA

## 完了の定義(公開時点)

- 2,887+legacy 全話(特殊エントリ 28 件含む)が `/works/` で読める(MD ≥85%・raw は明示)
- 五十音/年代/ジャンル/種別/キーワード/共有世界/推薦の 7 索引と検索が機能
- 全ページに書誌カード(初出・回収経路・原本リンク)。アネックスとの往還が両方向で機能
- mailto が WP・Pages 両方でゼロ。denylist が四層+掲示を 1 ファイルで駆動
- /boards/ /dojo/ は恒久 noindex のまま、整理版のみ index 解禁
- `make catalog && deploy && wp ts import` を素の状態から流して同一サイトが再構築できる
