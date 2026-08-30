# 少年少女文庫 → WordPress ライブラリ 実装タスクリスト v1.1

**このファイルが実装の単一の進行台帳。** 実行セッション(Opus 想定)は毎回これを最初に読み、
完了したタスクの `[ ]` を `[x]` に変えてコミットしながら進めること。
(v1.1: 文脈ゼロ実行者視点の 3 体審査で出たブロッキング 14 件を反映した改稿)

## 0. 実行セッションの心得(毎回読む)

**用語**: 本館 (= 移築先の WordPress) / 別館 (= 原本を保つ GitHub Pages ミラー) / 旧館 (= 消滅した原サイト)
ほかの定義は [`docs/glossary.md`](glossary.md) が正典。**この 3 語は内部用語**で、
サイト上の表示文言・about ページ・バナー・書誌カードのラベルには使わない。

**読み順**: ① このファイル → ② `docs/wordpress-library-design.md`(設計書。v1.0 本文+v1.1〜v1.4 改訂章。
**改訂章が本文に優先し、番号の大きい章が優先**。「v1.2 改訂」章は 2 本あるが**後方の A/B 構成の章が正典**。
**v1.4「最大掲載原則」章が範囲の最終決定**) →
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
- **地雷: ファイル名に `.cgi` を含む URL は前段 nginx が一律 403**(回避不能)。だから別館
  (= 現行 GitHub Pages ミラーの**全 18,006 ファイルの URL 空間**。`.cgi` スナップショットは
  **フルパス基準 9,022 / basename 基準 9,021** を含む。数え方は `docs/data-inventory.md` §0 と §7)
  は GitHub Pages 恒久併存(設計 v1.1)
- **nginx キャッシュの罠**: 変更が反映されないように見えたら、まずキャッシュを疑う。
  検証 curl には毎回キャッシュバスター(`?_cb=<乱数>`)を付ける。パネルからのキャッシュ全消しは
  ブラウザ操作のため 👤 タスク。**「反映されない」と誤認して .htaccess や mu-plugin を
  修正し続けるループに入らないこと**
- WP 管理画面もセッションからは開けない(ログイン URL は変更済みで、その値の転記は禁止=規則 7)。
  検収は **WP-CLI(`wp post list` / `wp post meta get` 等)+curl** で行う
- 開発機: このリポジトリ(python3.11、pip 使用可)。catalog 生成は開発機、投入はサーバ上 WP-CLI

**結合キーの定義(全フェーズ共通。1.1 実測により確定)**:
- **冪等キー(WP upsert キー)= `episode_id`**。path+anchor では足りない — 同一ファイルを指す
  目録エントリが 39 件(16 グループ)実在する(1.1 の訂正 (c) 参照)
- **episode_id** = `_ts_source_path` の `/` を `__` に置換し、anchor があれば `@<anchor>`、
  同一ファイル衝突グループのみさらに `+<掲載日YYYYMMDD>` を付加
  (例: `novel__200209__19204751__d_upboy06.htm`、`novel__toukou01.html@JUNKO`)。
  **正典は catalog/episodes.jsonl の episode_id 欄** — 再導出せずこれを使う。
  episodes.jsonl ↔ `bodies/` ↔ convert_report ↔ import の結合はすべてこの id
- 本文 MD の置き場 = リポジトリ直下 **`bodies/`**(設計書の `catalog/bodies/` 表記は旧版)

**最大掲載原則 (設計 v1.4。2026-08-30 サイト所有者決定)**:
**サルベージしたもの・できるものはすべて掲載する。** v1.0 の「WP 化は第 2 世代の作品層のみ・
残り 70% は別館に凍結」という範囲限定は**撤回済み**。別館(GitHub Pages)は原本
レイヤとして維持したまま、WP 側にも可能な限り載せる。**掲載しない例外は 3 つだけ**:

1. **denylist 掲載分**(削除依頼を受けたもの)
2. **スパム**(ラウンジ BBS の 94%、道場の汚染 13 ページ) — 除外ログを必ず残す
3. **メールアドレス**(mailto 由来の PII) — 本文・投稿は載せるがアドレスは catalog 段階で除去

作者が転載条件を掲げている場合の**事前保留は行わない(例外なし)**。連絡後に中止の意思が
示されたら 72h SLA で denylist に載せて全層から消す、という事後対応で担保する。

**後から素材が届いたら catalog に足して再インポートすれば反映される。** 八重洲さん・
移管先管理者・作者から原本が出てくる可能性はあるが、入るか不確実なので**今あるものだけで
公開まで完走する**設計にしてある。単一真実源+冪等インポートがこれを保証する。
提供素材は `provenance` に出所(提供者・提供日)を記録すること。

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
- series.html の**有効行(リンクを持つ TR)は実測 112**。設計 v1.0 と旧版台帳の「123 行」は誤り
  (数え方: `catalog/reports/work_builder.json` の `series_html_rows`)

**人間(サイト所有者)しかできないタスクは 👤 印**。実行セッションは 👤 タスクに当たったら
スキップして次へ進み、最後に「👤 待ち」一覧を報告する。

---

## Phase 0 — 方針とガバナンス(目安 1 週)

- [x] 0.1 `takedown/denylist.yml` のスキーマを決めて空ファイル+README を作る
      (エントリ: 対象 = work slug / episode の source_path / 別館のパス、理由、日付、状態)
- [x] 0.2 `docs/removal-runbook.md` v0: 削除依頼受領 → 72h 以内に
      ①WP draft 化 ②.htaccess 410 追記 ③別館(Pages)側除外 ④**git 履歴除去
      (git filter-repo。raw-original タグにも残る事実と対応方針を明記)** ⑤`/removed/` 更新、
      の **四層+掲示** を文書化
- [x] 0.3 `docs/publish-runbook.md` v0: catalog 再生成 → rsync → `wp db export`(復旧点)→
      `wp ts import` → verify → 👤 キャッシュクリア依頼、の定常手順(コマンド列)を文書化
- [x] 0.4 robots.txt 草案(AI 学習クローラ Disallow・ia_archiver 許可)を `scripts/wp/assets/` に用意
      ※ リポジトリ直下の現 robots.txt は Wayback 誤回収物なので参照しない。配備は 2.3
- [x] 👤 0.5 要決定事項の判断(2026-08-30 回答済 — 設計書 **v1.3 改訂章**参照)。
      **Q3=public 維持(README で透明化)/ Q4=全量公開+削除即応 / Q5=旧目録は全差分投入 /
      Q7=呼称「少年少女文庫」原題のまま**。
      **重要な前提変更: 本プロジェクトは八重洲メディアリサーチ(文庫設立者)の持ち主の依頼による
      リブートである。**「黙認ベース」ではなく原運営者公認。about ページにその旨と運営者ハンドルを
      明記(4.6)。ただし個別作品の著作権は各原著者にあり、削除 SLA・PII マスク・BBS 層 noindex は維持。
      作者連絡台帳の初期化と発送は 3.5/7.1 で継続

## Phase 1 — catalog 確立(目安 2〜3 週。ここが土台)

- [x] 1.1 `scripts/wp/catalog_build.py`: lib1〜73 の 2,887 エントリを JSONL 化
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
      - **1.1 実測メモ (2026-08-30 実装時。以降のフェーズはこちらが実測値)**:
        2,887 エントリ / パース失敗 0 / mailto 264 アドレスを除去し残存 0 /
        provenance 被覆率 93.35%(2,695/2,887。残り 192 は原本ファイルが未回収の話 186 +
        外部 URL 6 で、回収されていない以上は経路も無い)。
        レポートは `catalog/reports/catalog_build.json`、欄の説明は `catalog/README.md`。
      - **台帳の記述の訂正 3 点**(実物を見て判明):
        (a) provenance の出所は `collinfo.json` **ではない** — 中身は CommonCrawl の
        コレクション一覧 127 件で来歴情報を持たない。実際の出所は **git 履歴**(回収コミットの
        件名と日付)。スナップショット URL は個別 timestamp の記録が無いため Wayback の照会 URL 形式
        (b) `annex_yays_url` の「約 1,399 件」は **~yays 側ファイル 1,466 のうち同一相対パスが
        直下にもある数**であって目録エントリ数ではない。目録エントリ基準の実測は **724 件**
        (c) 冪等キーは **path+anchor では足りない** — 同じファイルを指す目録エントリが 39 件
        (16 グループ) 実在する(シリーズタイトルページに各話がリンクする「生命戦隊トランスギャルズ」型、
        改訂版の再掲型)。episode_id はこの衝突グループにだけ `+<掲載日 YYYYMMDD>` を付けて
        一意化してある。**Phase 2 の import は `_ts_source_path`+anchor ではなく episode_id を
        upsert キーにすること**
- [x] 1.2 同スクリプトで旧目録 lib01〜09 を差分パース(`corpus=legacy`)。パス prefix 重複排除後の
      追加分のみ。受け入れ: 追加件数を QA レポートに記録
      - **1.2 実測 (2026-08-30)**: 旧目録ブロック 336(lib01–06 の 252 + lib07–09 の 84)を
        パース失敗 0 で読み、**正規目録側(`corpus=honkan`)と同一 source_path の 239 件を落として 97 件を追加**
        (flat 75 / table 22、実パス 76、1997-11-06〜2000-02-25)。レポートは
        `catalog/reports/catalog_build.json` の `legacy` 節、欄の説明は `catalog/README.md`。
      - **台帳の記述に対する補足 3 点**:
        (a) 「パス prefix 重複排除」は**パス完全一致**で実装した。ディレクトリ prefix で
        落とすと `novel/short/` のような共用ディレクトリで無関係な話まで消える。
        正規目録側がアンカー付き集約リンクの場合もパスは一致するので目的は達する
        (b) lib01–06 の日付欄は `M/D` だけで**年が無い**。ページ見出しの収録期間
        (`旧作品(1997.11.4 - 1998.4.5)`、必ず 12 ヶ月未満)から年を一意に決めている
        (`date_precision` に記録)。「ページ内は新しい順」の仮定は末尾のパロディ群で破れる
        (c) lib01–06 には **HTML コメントで隠されたエントリが 12 件**あり、うち全数が
        正規目録に無いため差分に入っている。運営が意図的に伏せた可能性があるため
        `commented_out: true` で印を付けてあり、**WP へ投入するかは 👤 判断待ち**
- [x] 1.3 `scripts/wp/terms_build.py`: `catalog/terms.json` 生成
      - genre 244→約 30 語・type 187→約 25 語。正規化マップは `catalog/genre_map.yml` /
        `type_map.yml`(NFKC+「？」除去+類義統合)。**頻度表は catalog_build 出力から全語彙を
        自前集計**(survey は top-50 のみ)
      - keywords 約 1,000 語(表記揺れ統合のみ)。旧【属性】17 語は keywords へ
      - **ts_world 13 語**(share_world.html+共有感想板 7 つが元資料)と **ts_corpus 4 語**もここで生成
      - 原表記は必ず `raw_variants` に保持
      - **1.3 実測 (2026-08-30 再計測)**: genre 244→**196 語(中核 30 語で延べの 94.1%)**・
        type 187→**165 語(中核 30 語で 93.6%)**・keyword 1,087→**1,032 語(中核 74 語)**・
        **ts_world 14 本**(該当 408 話)・**ts_corpus 5 本**。
        (数え方: `catalog/reports/terms_build.json` の各 taxonomy の `terms` /
        `worlds_total` / `episodes_with_world` / `corpus_terms` の長さ)
        正規化は NFKC+「」外し+末尾？除去+
        `catalog/{genre,type,keyword}_map.yml` の同義語マップ。語彙定義ページ
        (genre.html 27 語 / type_of_change.html 18 語 / keyword.html 38 語) を description に転用。
      - **台帳の記述の訂正・補足 3 点**:
        (a) 「約 30 語 / 約 25 語」は**語彙を 30 語に削る**意味では達成できない
        (正規化だけでは 196/165 語が残り、長い尾は一回限りの自由記述)。
        中核語彙 (出現 10 回以上) に `core: true` を立て、残りも term としては保持した。
        テーマのファセット UI は core だけを出す前提。type の中核は 30 語で「約 25」より多い
        (b) **ts_world は 13 ではなく 14 本**。設計 v1.0 の一覧にある `corrector` は
        独立した世界ではなく `novel/corrector/` = フォスターシリーズの置き場
        (中身は foster01〜20.html)。一方 share_world.html が挙げる
        **ハンターシリーズ・FMS シリーズ**が一覧から漏れていた。判定規則は
        `catalog/world_map.yml` (共有感想板 slug / 作品ディレクトリ / 題名正規表現) に外出し
        (c) 分類語彙の slug も恒久 URL なので、genre/type/keyword/world の全語を
        `catalog/slug_overrides.yml` の `terms_*` セクションに候補出力した
        (**確認待ち 1,407 件**。うち大半は keyword。数え方: `terms_build.json` の
        `slug_pending_review`)。**1.5b の確認対象に含めるかは 👤 判断**
        (d) **ts_corpus は 4 語ではなく 5 語** — 実データの corpus 値は
        `honkan` 2,887 / `legacy` 97 / `uncatalogued` 859 / `extern-repost` 1 の 4 種で、
        旧実装の語彙 (`honkan`/`legacy`/`dojo`/`anthology`) では **860 話が term を持たない**
        状態だった。`docs/glossary.md` の corpus 表に合わせて実データの 4 語 + Phase 6 用の
        `dojo` の計 5 語に直し、`anthology` は実データに無いので落とした
        (2026-08-30 修正)。**全 episode の corpus に term があること**を
        `terms_build.py` の自己検査に追加してある (被覆 3,844/3,844)
- [x] 1.4 `scripts/wp/authors_build.py`: `catalog/authors.json` 生成
      - slug = 感想板 author_id(目録の `bbs@log_<id>.cgi` リンク。305 slug)。表記揺れ統合 48 組
      - **板なし作者 33 名のローマ字 slug は pykakasi(開発機 pip 導入可)で候補生成し、
        `catalog/slug_overrides.yml` に全数出力 → 👤 確認後に確定**(恒久 URL のため幻覚読み厳禁)
      - meta: display_variants / yomi(lib-index-*.html の所属行から) / homepage /
        homepage_wayback(`https://web.archive.org/web/2010/<URL>` 形式で機械生成) /
        kansou_annex_url / contact_status(全員 `uncontacted`)
      - 受け入れ: 作者数 ≈ 399・全 episode の author が解決
      - **1.4 実測 (2026-08-30 再計測)**: **作者 342 名**(板あり 294 / 板なし 48)。
        表示名の異なりは 428 種で、**57 組の表記揺れ (延べ 430 表示名 − 統合 88) を
        感想板 id で統合**した結果。yomi 解決 312 / homepage 116 /
        **全 episode の author 解決** (作者不詳 37 = 編集部告知 1 + 目録外収蔵の
        `unattributed` 36 を除く 3,807 話に割り当て)。slug は板 id 294・pykakasi 39・
        ASCII 8・fallback 1 (`？？？？` という表示名)。**確認待ち 40 件**。
        (数え方: `catalog/authors.json` の `authors` 配列長、内訳は
        `catalog/reports/authors_build.json` の `authors_with_board` /
        `display_name_strings_distinct` / `slug_sources` / `slug_pending_review`)
      - **台帳の記述の訂正 2 点**:
        (a) 受け入れ条件「作者数 ≈ 399」は達成不能。**399 は survey の
        `author_count` = 表示名の異なり数であって人数ではない**。表記揺れを
        板 id で統合するのが 1.4 の仕事なので、統合すれば必ず 399 より減る。
        内訳は survey の他の数字とも整合する (自前板 297 + 共有板のみ 31 ≒ 328)。
        実装の自己検査は「延べ表示名 − 統合分 == 作者数」に置き換えた
        (b) 共有シリーズ板は 7 つではなく **11 板**。設計の 7 つ (kayo_chan / himekami /
        foster / relay_novel / delayed / 2daime / utanotsuki) に加えて
        **d_angel (ダーティーエンジェル、3 作者)・setubou (切望)・rental_body・
        sugar_sweets** が実在する。これを作者板と誤認すると別人が 1 作者に潰れる
      - **👤 判断待ち (同名別鍵 2 件)**: `神川綾乃` が板 `kamikawa_ayano` と
        `kamikawa_ayano_` の 2 つに、`コーディー` が `jersey_red` と `kohdhi` の 2 つに
        跨がる。同一人物の板が 2 つある可能性が高いが、機械では決められない
        (slug_overrides.yml は slug の確定用で「作者の併合」は表現できない)
- [x] 1.5 `scripts/wp/work_builder.py`: Episode → Work クラスタリング → `catalog/works.jsonl`
      - シード: `series.html` 有効 **112** 行(コメント除去後にリンクを持つ TR。
        旧記述の「123 行」は誤り)+`share_world.html`+
        **シリーズタイトルページ(novel/ 配下の `*title*.htm*` および投稿ディレクトリ内 index.html 名、
        計約 124+旧世代ツリー内 51)**+推薦文内ナビ 696 リンクの誘導先 URL 集合
      - 補完: 作者×ファイル名 prefix×題名共通接頭辞クラスタ
      - あいまい分は `needs_review: true` で `catalog/work_overrides.yml` 雛形に出力(想定 100〜200 件)
      - work_slug = `{author_slug}-{作品ローマ字}`。**作品ローマ字も pykakasi 候補+
        `slug_overrides.yml` 経由の 👤 確認ゲートを通す**。重複ファイルは正本 1 つ+`alias_paths`
      - 受け入れ: 全 episode がいずれかの work に属す。orphan 0
      - **1.5 実測 (2026-08-30 再計測。1.8/1.9 で episode が 2,984→3,844 に増えた分を反映)**:
        **Work 1,463 件**(単発 946 / 連載 517)、3,844 episode 全部がどれかに属し **orphan 0**。
        `needs_review` は **328 件**(内訳: 弱い根拠のみ 269 / 15 話以上 22 /
        複数ディレクトリ 37)。md5 一致の重複ファイル 55 本を `alias_paths` に。
        タイトルページを持つ work 234 件、series.html の有効行 112。
        (数え方: `catalog/works.jsonl` の行数、内訳は
        `catalog/reports/work_builder.json` の `works_single_episode` /
        `works_multi_episode` / `needs_review` / `needs_review_reasons` /
        `alias_paths_total` / `works_with_title_page` / `series_html_rows`)
      - **台帳の記述の補足 3 点**:
        (a) 「series.html 有効 123 行」は実測 **112 行**(リンクを持つ行。
        コメント除去後の `<TR>` は 135 だがヘッダ行と番外編のみの行を含む)
        (b) 「推薦文内ナビ 696 リンク」は実測 **1,236 リンク・誘導先 114 ページ**
        (【シリーズタイトルはこちら】1,023 +【華代ちゃんシリーズタイトルはこちら】139 ほか)
        (c) **Work は 1 作者に閉じる規則を追加した**。共有世界のタイトルページ
        (novel/kayo_chan/index.html に 139 リンク) は 69 名の話を 1 つに束ねてしまい、
        「作品」ではなく「世界」になる。シリーズとしての同一性は ts_world が担う
- [ ] 👤 1.5b work_overrides.yml と slug_overrides.yml の確認・確定。
      **これが済むまで Phase 2.5(全量投入)に進まない**(needs_review 分の恒久 URL が変わるため。
      2.4 のパイロット 100 件は確定済み分のみで先行可)
- [x] 1.6 `scripts/wp/body_convert.py`: 本文 Markdown 変換(設計 v1.2 改訂章 A「本文 Markdown 正準化」)
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
- [x] 1.8 `scripts/wp/uncatalogued_build.py`: **目録外収蔵の収録** (設計 v1.4 の最大掲載原則)
      - `novel/` 配下の本文ファイル 3,818 のうち、**871 本が lib1〜73 / lib01〜09 のどの
        エントリにも対応しない**(例: `novel/200009/17163907/the_heroine01.html`「ザ・ヒロイン」、
        `reborns_day.html`、`nanairo.html` — いずれもフラット期の実作品)
      - 第二メタデータ源に **`lib-index-*.html`(作家別五十音索引。題名+作者の 2 列・約 2,796 行)**
        を使う。それでも足りない分は本文の `<title>` や「作：」行から最小エントリを起こす
      - `corpus=uncatalogued` で区別し、メタの出所を `metadata_source` に記録する
      - **受け入れ: `novel/` 配下の本文ファイルで catalog に載らないものが 0**
        (意図的除外 = 目録・索引・ナビ等の非作品ファイルを除く。除外理由を機械可読に残すこと)
      - **1.8 実測 (2026-08-30)**: `novel/` の本文 **3,818 = 既収蔵 2,724 + 追加 859 +
        意図的除外 235 (残 0)**。除外の内訳は既収蔵と md5 一致の別名コピー 58 /
        シリーズタイトル・目次ページ 117 / CGI 並べ替えビュー 56 / サイト定型ページ 3 /
        空 1 で、全部 `catalog/uncatalogued_excluded.jsonl` に理由つきで残る。
        題名は 859 件すべて解決、作者は **823 件解決・36 件不詳** (`entry_role=unattributed`)。
        メタの出所は lib-index 91 / 本文の「作：」行 216 / `<title>` 728 /
        同ディレクトリの作者 466(+多数決 50) / ファイル名 40。
      - **台帳の記述の訂正 2 点**:
        (a) 「871 本」の実測は **859 本**。差は「既収蔵ファイルと md5 が一致する別名コピー」
        58 件と「タイトル/目次ページ」117 件を作品として数えるかどうかの定義差
        (b) **lib-index-1〜4.html は他の索引ページと列の並びが逆**で、作者セルが
        `rowspan` で複数行にまたがる。同じパーサで読むと題名と作者がずれる
        (実際 1 度ずれた)。旧世代版は専用パーサにした。索引の行数は
        aa〜etc の 2,796 に旧世代 983 を足して **3,779 行**
      - **date は概算**: 目録外の話には掲載日が無い。投稿ディレクトリ `novel/YYYYMM/` から
        月初として起こし `date_precision: directory-batch` を立てた (フラット期の話は `unknown`)
- [x] 1.9 保管中の pixiv 再掲 2 作を catalog に収録 (`~/ts-novels-holding/stage_repost/`)
      - **雨女**(おもちばこ) = 文庫の欠落 `novel/200802/15232641/rainGirl.html` に対応。
        本文は**作者本人の pixiv 再掲 (id 351985)** から取得。`provenance` に出所 `pixiv`・
        作品 id・取得日を必ず記録する
      - **きらいなもの→ＧＷ**(同) = **文庫には一度も掲載されていない pixiv 限定の番外編**。
        `corpus=extern-repost` で区別し、書誌カードに「文庫未掲載・pixiv 由来」と出せるメタを持たせる
      - 本文はプレーンテキストなので **1.6 body_convert の対象外**。Phase 3 で空行区切りの
        段落分割 → paragraph ブロック化する
      - 同ディレクトリの他の回収物(きりか進ノ介さんの wiki 発掘 6 点、ライターマン「天女の末裔」、
        ヴァルキュリア外伝、城弾シアター版ディレイド 4/5 など)は**検証未完なので今回は収録しない**
      - **1.9 実測 (2026-08-30)**: 雨女は既存の目録エントリ
        `novel__200802__15232641__rainGirl.html` に本文 (`reposts/…txt`)・provenance
        (route=pixiv・作品 id 351985・取得日)・`published_in_bunko: true` を**足した**
        (新しい話は作っていない)。きらいなもの→ＧＷ は `repost__pixiv__272830` として
        `corpus=extern-repost`・`published_in_bunko: false` で新規収録。
        両方 `body_convert_exempt: true` (1.6 の対象外)。
      - **補足**: pixiv のタグ 5 語 (オリジナル/TSF/SS/女性人格化/チェス) が
        keywords に入るので、文庫の語彙に外部由来の語が 1 件分だけ混ざる
      - **1.6 実測 (2026-08-30、変換器のバグ 4 種を潰したあとの再計測)**: 変換対象 3,644 のうち
        **3,642 話が証明つきで合格 (99.95%)**、証明違反 0、raw フォールバック 2 話。
        出力は `bodies/<episode_id>.md` (**3,642 ファイル**)・判定は
        `catalog/convert_report.jsonl`・サマリは `catalog/reports/body_convert.json`。
        (数え方: `ls bodies/*.md | wc -l` と `body_convert.json` の `status` / `md_rate_pct`)
        **`bodies/` は `.gitignore` 済みで git 管理外** — `make catalog` で再生成する派生物
      - **設計を二段構えにした**: ①本文領域は無損失証明で完全保護 ②**クローム除去は証明の外側**
        で行い、検証を通したあとに定型ナビ行だけを落とす。落とした 9,670 行 (3,273 話) は
        `nav_trimmed` に全件記録してあり、本文を巻き込んでいないか監査できる。
        これで先頭が「戻る」の話は 2,576→0、感想フッタ残りは 1,354→45 になった
      - **証明側の誤りを 3 件潰した**(いずれも原本側が文字を失っていた。放置すると正しい変換を
        不合格と誤判定する): (a) タグ除去より先に実体参照を解くと地の文の裸の `<` (`ち<ゃんと`) を
        タグと誤認して次の `>` まで食う → 順序を タグ除去→復元 に (b) 変換側に汎用タグ除去を
        当てると `<右図>` `<短編小説のページへ>` のような山括弧付きの地の文を食う → 既知の
        HTML タグ名だけを対象にする検証用パターンへ (c) 温存断片 (ルビ・画像・本物の表) を戻す前に
        unescape すると断片内の実体参照だけ解かれず原本とずれる → 復元してから 1 回だけ解く
      - 表はセル数と平均文字数で「本物の表」と「レイアウト table」を判別し、前者はインライン HTML で
        温存。非 UTF-8 の `.txt` に cp932/euc_jp フォールバックを追加
- [x] 1.7 `make catalog` で 1.1〜1.6 を一発実行できる Makefile。QA レポートを `catalog/QA.md` に
      (件数・合格率・needs_review 数・provenance 被覆率・特殊エントリ内訳)
      - **1.7 実測 (2026-08-30 再計測)**: `make catalog` は 1.1/1.2 → **1.8 → 1.9** → 1.3 → 1.4
        → 1.5 → 1.6 → 1.7 の順で走る(**1.6 込みで 44 秒**。数え方: `time make catalog`)。
        `make check` は書き込みなしの自己検査(全段 OK・終了コード 0)、
        `make venv` は pykakasi / PyYAML / beautifulsoup4 / html5lib 入りの `.venv` を作る。
        **2 回連続実行で全出力(episodes/terms/authors/works/QA.md/slug_overrides)が
        バイト単位で同一**であることを確認済み。
      - QA レポートは `scripts/wp/qa_report.py` が `catalog/reports/*.json` から転記して
        `catalog/QA.md` を書く(二重帳簿にしないため、ここでは再計算しない)。
        **QA.md は生成物なので手で書き換えない — 直すのは `qa_report.py` のほう**。
        (2026-08-30 の修正: 読むレポート名が `convert_report.json` になっていて実ファイル
        `body_convert.json` と食い違い、1.6 完了後も §8 が「未実装・`bodies/` はまだ空」と
        出続けていた。あわせて corpus `honkan` の表示を「本館」から「正規目録 (lib1–73)」に
        直し、corpus 語彙の自己検査結果を §4 に出すようにした)

## Phase 2 — WP 骨格+メタ全量投入(目安 2 週)

- [ ] 2.1 `mu-plugins/ts-library/` v0.1(リポジトリ管理→rsync 配備): CPT `ts_work`(hierarchical,
      rewrite=works)・`ts_doc`・**`ts_dojo`(登録のみ。使用は Phase 6)**・`ts_board_post`(同)・
      タクソノミー 6 本・`register_post_meta`(`_ts_*`)・meta robots noindex 出力・
      comment_status=closed 強制。PHP 8.0 互換。定義は設計書 §1 の表のとおり。
      あわせて **WP Multibyte Patch を有効化**(`wp plugin install wp-multibyte-patch --activate`)
- [ ] 2.2 `wp ts` WP-CLI コマンド群(mu-plugin 内): `sync-terms` / `import`(--dry-run/--limit/
      --author、created/updated/skipped 集計出力) / `apply-takedown` / `verify`(件数照合・orphan・
      taxonomy 被覆率・**特殊エントリ内訳**・冪等性=2 回目 created/updated 0) / `export-pathmap` /
      **`reset --yes`(ts_* の全投稿・term・meta を削除 — やり直し用。docs/rebuild-runbook.md §2-C)**。
      **import は投入時の catalog の git コミットハッシュを `wp option ts_catalog_commit` に記録する**
      (何が本番に載っているかの追跡。rebuild-runbook §2-D)
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
- [ ] 2.7 novel/ 画像 **1,196 点**(約 59MB。数え方: `git ls-files novel` の `.jpg` 562 + `.gif` 614 + `.png` 19 + `.bmp` 1)を `public_html/assets/annex-img/` に相対構造ごと rsync

## Phase 3 — 本文投入(目安 2 週)

- [ ] 3.1 本文投入: **MD→Gutenberg ブロック HTML への変換は開発機の python で事前実施**し、
      投入用 payload を生成して rsync → `wp ts import --bodies` は payload を post_content に
      流すだけにする(サーバ PHP に Markdown パーサを導入しない)。ブロック対応:
      paragraph/separator/quote/heading/image。ruby と本文内 table はインライン HTML。
      raw フォールバック分は `wp:html` 1 ブロック+`_ts_body_status=raw-fallback`
- [ ] 3.2 img src を `assets/annex-img/` 参照に書換。**eyecatch 画像(実体 4 ファイル・参照 15 話)**は
      dedup して該当話の featured image に設定(survey の「109 件」は参照回数であり実体数ではない)
- [ ] 3.3 無作為 100 話の原本併読 QA(別館の原本と並べて本文欠落・順序破壊がないか)。
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
      - **書誌カードのラベルは読者に見える表示文言**。「原本を見る」「初出時の姿(~yays 版)」
        「当時の感想を読む」のように具体的に書き、**「別館」「本館」等の内部語は出さない**
        (→ `docs/glossary.md`)
- [ ] 4.2 リーダー UI(素 JS+localStorage): 文字サイズ/行間/明朝ゴシック/ダーク/しおり/←→話ナビ。
      raw フォールバック話のみ「原本配色」トグル
- [ ] 4.3 索引ページ群: `/index/kana/{a..wa}/`・`/index/timeline/`・`/index/bunrui/`・
      `/index/vocabulary/`・`/index/osusume/`(`_ts_osusume(_in)` から)
- [ ] 4.4 `/year/YYYY/` リライト(mu-plugin)とトップページ(「今日の一作」= 日付シード選出、
      「新着」は置かない)
- [ ] 4.5 検索: WP 標準検索+タクソノミーのファセット絞込テンプレート
      (設計書の Pagefind は静的書き出し前提が v1.1 で消えたため置換 — 設計書側にも追補済み)
- [ ] 4.7 **ギャラリー区画** (設計 v1.4): `~yays/gallery/` の CG 313 点を WP に収録。
      作品ページの挿絵とは別区画。作者クレジットが取れるものは作者ページからも辿れるように
- [ ] 4.8 **企画・アンソロジー** (設計 v1.4): `special/` 61 ファイル(03summer 等)を
      アンソロジー区画 (`ts_corpus=anthology`) または `ts_doc` として収録
- [ ] 4.9 **文庫前史** (設計 v1.4): `~ezpe/yasai/` の静的 8 ページ(1999 年の原型サイト)を
      `ts_doc` に収録し、`/index/timeline/` から辿れるようにする
- [ ] 4.6 運営コンテンツ投入: `ts_doc` に comittee/ 21・columns/・dialy/・03summer 解題。
      固定ページ `/about/`(**八重洲メディアリサーチの持ち主の依頼によるリブートである旨・
      運営者ハンドル・各作品の著作権は原著者に帰属する旨・原本アーカイブとの関係・
      無損失証明の説明**)・`/archive/`(別館 4 区画の案内。**URL にも表示にも内部語を出さない**)・`/removed/`(空)・
      **`/takedown/`(72h SLA を明文で宣言)。全ページフッタに /takedown/ へのリンク**
      - **表示文言の規則**: これらのページは読者が読む面なので、本文・見出し・リンクラベルに
        **「本館」「別館」「旧館」を書かない**。「原本アーカイブ」「当時のページ」
        「原本を見る」のように、具体的に何であるかを書く(→ `docs/glossary.md`)

## ⏸ レビュー チェックポイント(Phase 4 完了時。ここで止まる)

- [ ] 👤 CP-4 **サイト所有者のレビュー**。Phase 4 の全タスク完了後、実行セッションは
      **Phase 5 以降に進まず**、次を揃えて報告して止まること:
      1. レビュー用 URL 一覧(トップ / 作品 3 例 / 話 3 例〔MD・raw フォールバック・画像作品を各 1〕/
         作者 2 例 / 各索引 / 検索。全て noindex のまま)
      2. その時点の `wp option get ts_catalog_commit` の値(NG 報告の基準点)
      3. 既知の未了・妥協点の一覧
      NG の出し方と再走手順は `docs/rebuild-runbook.md`(§3 と §2)。
      レビュー通過の連絡があってから Phase 5 へ。

## Phase 5 — 別館連携(目安 1 週)

- [ ] 5.1 `wp ts export-pathmap > catalog/path_map.json`(原パス+alias → WP URL)
- [ ] 5.2 GitHub Pages 側デプロイをビルド注入方式へ: `scripts/wp/annex_inject.py` —
      デプロイ artifact 生成時に (a) mailto 除去(リンクとテキスト両方。対象ファイル数は
      ビルド時に再計測 — 参考実測 6,515) (b) 各 HTML 冒頭に 1 行バナー
      (**読者に見える表示文言。「本館」とは書かない**)
      「原本アーカイブ | 少年少女文庫で読む → path_map の該当 URL」 (c) meta noindex 注入
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
- [ ] 7.2 削除リハーサル: テスト作品 1 件で「draft 化+410+別館からの除外+**git 履歴除去の手順確認**+
      /removed/ 掲載」を実際に流し、removal-runbook.md を実測で更新
- [ ] 👤 7.3 公開判断(Q4: 連絡不達作者の扱い)
- [ ] 7.4 解禁: .htaccess を差し替え —
      **解禁(index 許可)**: `/works/ /authors/ /genre/ /type/ /keyword/ /world/ /year/ /index/
      /docs/ /about/ /takedown/ /removed/` とトップ。
      **恒久 noindex 維持**: `/boards/ /dojo/ /assets/annex-img/` ほか BBS/PII 層
      (別館=Pages 側は 5.2 の meta noindex のまま)。
      sitemap.xml は解禁区画のみ生成。robots.txt の本番内容を curl で最終確認。
      Search Console 登録は 👤 任意
- [ ] 7.5 公開後 verify: 主要 20 URL の 200/内容確認・**noindex 境界の確認(/boards/ が noindex の
      まま、/works/ が解禁されていること)**・👤 キャッシュクリア依頼

## 定常運用(公開後)

- [ ] 8.1 publish のたび: `wp db export` を dumps/(私有ストレージ。公開リポジトリに入れない)へ
- [ ] 8.2 年 2 回: lychee でリンクフルクロール(WP+Pages 両方)
- [ ] 8.3 再構築演習: **初回は Phase 3 完了直後**、以後年 1 回 — 素の環境から
      `make catalog` → deploy → import で同一サイトが再現できることを確認し、手順書の腐敗を検出
- [x] 8.4 **holding 分の検証と収録** (第 4 次サルベージの続き) — **2026-08-30 完了**。
      `~/ts-novels-holding/stage_repost` の 13 点を全部同定した。結果:
      - **収録 3 点** → `reposts/repost__bcwiki__{ama,saidai_no_higaisha,bangai3ts}.*`。
        出所は**きりか進ノ介さんの現行サイト `bc-cafe.net/bcwiki.old/`**
        (ページ「トレイル/虹色のかけら」の孤立添付。md5・登録日時つきで
        `reposts/repost__bcwiki__provenance.json` に記録)。3 点とも**文庫未掲載**なので
        きらいなもの→ＧＷ と同じ `corpus=extern-repost` 相当の扱いになる。
        `saidai_no_higaisha` は作者自身が性描写を注記しているので年齢表示の要否を要判断
      - **重複 8 点は不収録** — ゴールデンロード 0A/0B と wiki 3 頁は
        `kirika.novels.name/wiki` に、ヴァルキュリア外伝 1/2 は
        `novel/200210/28213920/valkyrie_ex{1,2}.htm` に、ディレイド 4/5 は
        `novel/201009/16215412/delayed0{4,5}.htm` に既収蔵で本文一致
      - **同定の訂正 2 件**: ①`あま.txt` は欠落作品「二代目は海女」
        (`novel/201004/02215640/ama.htm`) **ではない**(本文に海女もセバスチャンも無く、
        添付の登録が 2008-09 で掲載日 2010-05 に先行)。②「天女の末裔」は欠落
        `novel/200011/15112734/nouryo_title.html`(BAF「納涼ＴｏＳｉ伝説」)と**無関係**で、
        文庫版は `novel/200707/14133526/tennyo.htm` に既収蔵。holding のものは
        作者サイトの改訂版(本文が別テキスト)なので不収録
      - 派生: 上記の出所調査から `bc-cafe.net/bcwiki.old/` の**全 297 ファイルを新規収蔵**
        (未収蔵作品「ホーリーメイデンズ外伝『復活の依代』」ほか。§data-inventory §2 参照)。
        **catalog への反映は未了** — `repost_build.py` の `ITEMS` に 3 点を足し、
        `bc-cafe.net/` の作品ページを uncatalogued として拾うかを決める必要がある
- [ ] 削除対応は removal-runbook.md に従い 72h SLA

## 完了の定義(公開時点)

- **全 3,844 話**(正規目録 2,887 + 旧目録 97 + 目録外収蔵 859 + 文庫未掲載 1。特殊エントリは
  正規目録分 28 件・全 corpus では 100 件)が `/works/` で読める(MD ≥85%・raw は明示)
- 五十音/年代/ジャンル/種別/キーワード/共有世界/推薦の 7 索引と検索が機能
- 全ページに書誌カード(初出・回収経路・原本リンク)。別館との往還が両方向で機能
- mailto が WP・Pages 両方でゼロ。denylist が四層+掲示を 1 ファイルで駆動
- /boards/ /dojo/ は恒久 noindex のまま、本館のみ index 解禁
- `make catalog && deploy && wp ts import` を素の状態から流して同一サイトが再構築できる
