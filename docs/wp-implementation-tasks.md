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
8. **サイトは最初から公開(インデックス可)で構築する**(設計 v1.5)。ただし `/boards/`
   `/dojo/` 等の掲示板層は**恒久 noindex**(第三者のプライバシー配慮 — 公開ゲートとは別物)
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

**実装の所管(最終)**: **コードは親セッション(Fable)が書く**。実行セッションはコードを
書かず、Fable 製スクリプトとランブックの**実行・検収報告**に専念する(例外: verify_*_html5 系の
独立検算のみ、別の書き手であることが価値なので実行セッションが書いてよい)。
Opus の作業は目視でなく検査コード(make check / wp ts verify / 不変量)で検証する。
以下は特に変更禁止の根幹ファイル: 
実行セッションは変更せず、不具合を見つけたら報告すること:
`scripts/wp/body_convert.py` / `mu-plugins/ts-library/includes/commands.php` /
catalog 系パーサの変更(catalog_build・uncatalogued_build・authors_build・work_builder・
episode_overrides) / 今後の `boards_build.py`(6.1)・`annex_inject.py`(5.2)・
MD→Gutenberg ペイロード生成(3.1)・**`deploy.sh`(2.3。本番を触るコードは全部 Fable)**。
実行セッションは Fable 製スクリプトを**ランブックどおり実行する**のは可。
逆に**独立検算(verify_*_html5 系)は本体と別の書き手が担当**する(相互検証のため)。

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
      - ~~**👤 判断待ち (同名別鍵 2 件)**~~ → **1.5b で決着 (2026-08-30)**。
        `神川綾乃` は同一人物で `kamikawa_ayano` が正(`kamikawa_ayano_` は実在しない板)、
        `コーディー` と `ジャージレッド` は**別人**で コーディー の正は `kohdhi`。
        いずれも目録の感想リンクの誤植 1 箇所が原因で、`catalog/episode_overrides.yml` で
        上書きした。「作者の併合」も `slug_overrides.yml` の**同じ slug を複数の表示名に
        書く**形で表現できるようにした(実装は 1.5b の項)
      - **1.4 の数値は 1.5b で更新された**: 作者は **342 → 322 名**
        (板あり 294 → **300** / 板なし 48 → **22**)、slug 確認待ち 40 → **0**。
        上の「1.4 実測」は 1.5b 実施**前**の値なので、現在値は 1.5b の項を見ること
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
- [x] 👤 1.5b work_overrides.yml と slug_overrides.yml の確認・確定。
      **これが済むまで Phase 2.5(全量投入)に進まない**(needs_review 分の恒久 URL が変わるため。
      2.4 のパイロット 100 件は確定済み分のみで先行可)
      - **確定 (2026-08-30)**: 作者 slug **42 件を `status: confirmed`** にした
        (資料 `catalog/review/authors-slugs.md`、裁定は `catalog/slug_overrides.yml` の
        `authors:` セクションと同ファイル冒頭のコメント)。作品 slug 1,451 件と
        分類語彙 slug 1,407 件は**サイト所有者の決定により自動生成のまま採用**(確認しない)。
        `work_overrides.yml` の needs_review 331 件も確認せず採用
      - **裁定を build 側に追随させた (実装 3 本)**:
        (a) **作者の併合** — 複数の表示名が同じ確定 slug を指すとき 1 人に統合し、
        表示名は `display_variants` に集約する処理を `authors_build.py` に追加。
        **16 組・作者 17 名が統合で消え、`kamikawa_ayano_` の廃止と合わせて
        作者は 342 → 322 名**(数え方: `catalog/authors.json` の `authors` 配列長 /
        `catalog/reports/authors_build.json` の `author_merges` と
        `authors_removed_by_merge`)。統合の一覧は
        `catalog/reports/authors_build.json` の `author_merges` に全件残る
        (slents ← 根室　眞琴 / 根室　眞琴改め…、marie ← 麗香、popo ← HIKU / ぽぽ、
        aizu_rika ← 海津里花、you_ ← you' / you’、qqqqq ← ？？？？ ほか)
        (b) **`role: not-an-author`** — `slug_overrides.yml` の authors 行に書ける新しい欄。
        その表示名では作者を作らない。「シェアワールド」に付けた。該当話
        `novel/200103/24194542/himetitle.html` は**作者不詳ではなく
        `entry_role: series-index`(シリーズ目次)**として扱い、感想板 `himekami` 経由で
        ts_world「妖魔夜行 姫神奇譚シリーズ」(41 話)に紐づく。works 側では
        擬似作者 `series-index`(表示「シリーズ目次」)の下に置き、`unattributed`
        (作者不詳 22 件)と混ぜない
        (c) **`catalog/episode_overrides.yml` を新設**(適用は
        `scripts/wp/episode_overrides.py`。`make catalog` の repost_build 直後)。
        **旧館の原本 `lib*.html` の誤植を catalog 側で上書きする唯一の場所**で、
        別館(Pages)は原本レイヤなので誤植をそのまま保つ、という分担。
        `expect:`(上書き前にあるはずの値)を必ず書かせ、合わなければ自己検査が落ちる。
        **episode_id は source_path から導くのでこの上書きでは変わらない**
      - **同名 2 組の裁定の実施結果**:
        - `神川綾乃`: `lib45.html#12` だけが指していた実在しない板 `kamikawa_ayano_` を
          `kamikawa_ayano` に上書き。**作者 `kamikawa_ayano_` は消え、
          `kamikawa_ayano` が 34 → 35 話**
        - `コーディー`: `lib61.html#6`(「蒼い時（第二章）」)だけが指していた
          `jersey_red` を `kohdhi` に上書き。**`kohdhi` が 2 → 3 話(蒼い時 ao1–3 が
          1 作品にまとまった)、`jersey_red` は 27 → 26 話で
          `display_variants` から「コーディー」が消えた**
      - **感想板一覧を第 2 の情報源にした** (review §8-1 の恒久対策):
        `~ts/kansou/bbs@log_.cgi`(**290 板**。うち共有世界の板 9 を除く作者板 281、
        `<title>` から作者名が取れたもの 278)と、**板一覧に載らない板ファイル 16 本**の
        `<title>` を `authors_build.py` が読む。目録から一度もリンクされていない板を
        持つ**作者 8 名**(この市場 / Ｍａｃｆｉｓｔ / 由衣 / 西さとる / ふう / 佐藤由衣 /
        you' / ぽぽ)がこれで板 id に載り、**板を持つ作者は 294 → 300 名**に増えた。
        旧名義・別名義の板は `authors.json` の新欄 **`kansou_slug_alt`** に残る
        (薪喬 → `shinkyou`、根室　眞琴 → `memuro_makoto`、HIKU → `hiku`、
        神川綾乃 → `kamikawa_ayano_` など 8 名)
      - **回帰防止の自己検査 3 本を追加**(`authors_build.py`):
        「**confirmed slug と板一覧の不一致 0**」(表示名がいずれかの板を指すのに
        作者 slug がどの板 id とも一致しなければ落ちる = 今回の見落とし 7 名が再発したら
        検知する)・「👤 1.5b の裁定が全て authors.json に出ている」・
        「裁定による併合の記録」。既存の「**slug の重複 0**」は緩めていない
        (別人の slug 衝突を検出する網なので)
      - **再生成の検収 (2026-08-30 実測)**: `make catalog` 所要 46 秒。
        **作者 322 名**(板あり 300 / 板なし 22、slug 確認待ち **0**)・
        **works 1,451 件**(単発 932 / 連載 519、**orphan 0**、needs_review 331)・
        episodes 3,844(**episode_id の集合は再生成前後で完全一致**)・
        `make catalog` を 2 回連続で流して `catalog/` に**差分ゼロ**(冪等)・
        `make check` は全段 OK
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

**状況 (2026-08-30 時点): 2.6 を除いて完了。** 2.1〜2.5・2.7 は実測で受け入れ条件を満たし、
`wp ts verify` は **OK**(投稿 4,363・orphan 0・語彙外 term 0・taxonomy 割当 6 本すべて期待値と一致)、
`wp ts import` は**実行・dry-run とも created=0 / updated=0 で冪等**。
本番の `ts_catalog_commit` = `322522a8f0248aea3976cbe23e1b9753e3b4292b`。
**残るのは 2.6 (.htaccess、親所管) のみ**。既知の軽微バグが 1 件だけある
(投稿 ID 8038 の slug。2.2 の末尾を参照)。

- [x] 2.1 `mu-plugins/ts-library/` v0.1(リポジトリ管理→rsync 配備): CPT `ts_work`(hierarchical,
      rewrite=works)・`ts_doc`・**`ts_dojo`(登録のみ。使用は Phase 6)**・`ts_board_post`(同)・
      タクソノミー 6 本・`register_post_meta`(`_ts_*`)・meta robots noindex 出力・
      comment_status=closed 強制。PHP 8.0 互換。定義は設計書 §1 の表のとおり。
      あわせて **WP Multibyte Patch を有効化**(`wp plugin install wp-multibyte-patch --activate`)
      - **2.1 実測 (2026-08-30 実行セッション)**: 配備後 `wp post-type list` に
        **ts_work(hierarchical・public)/ ts_doc / ts_dojo / ts_board_post** が、
        `wp taxonomy list` に **ts_author / ts_genre / ts_type / ts_keyword / ts_world /
        ts_corpus の 6 本**が出ることを確認。`wp plugin list --status=must-use` に
        **ts-library-loader** が出る(本体は `ts-library/ts-library.php`。WP は
        mu-plugins 直下の php しか読まないのでローダ経由)。
        **WP Multibyte Patch 2.9.3 を install + activate 済み**。
        `wp rewrite flush --hard` 実行(`.htaccess` 再生成の Warning は wpX 構成では正常)、
        rewrite ルール **185 本**・`works/(.+?)/…` 系が生成されていることを確認
- [x] 2.2 `wp ts` WP-CLI コマンド群(mu-plugin 内): `sync-terms` / `import`(--dry-run/--limit/
      --author、created/updated/skipped 集計出力) / `apply-takedown` / `verify`(件数照合・orphan・
      taxonomy 被覆率・**特殊エントリ内訳**・冪等性=2 回目 created/updated 0) / `export-pathmap` /
      **`reset --yes`(ts_* の全投稿・term・meta を削除 — やり直し用。docs/rebuild-runbook.md §2-C)**。
      **import は投入時の catalog の git コミットハッシュを `wp option ts_catalog_commit` に記録する**
      (何が本番に載っているかの追跡。rebuild-runbook §2-D)
      - **2.2 修正後の検収 (2026-08-30、コミット `70873826` + `2bd22a7d`)**: 下の初回実行で
        出た (A)〜(E) は**全て解消**。`wp ts reset --yes`(👤 ユーザ手動実行。実行セッションからは
        権限分類器に掛かる)→ `sync-terms` → `import` の再投入で確認した実測:
        - **(A) 冪等性 = 達成**。`wp ts import --dry-run` で **created=0 / updated=0 /
          skipped=5295**、さらに**実 import(dry-run でない)でも created=0 / updated=0 /
          skipped=5295**(1分48秒)。`_ts_import_hash` の奪い合いは解消
        - **(B) 語彙外 term = 全 taxonomy で 0**。term 数は catalog と完全一致
          (ts_author 324 / ts_genre 196 / ts_type 165 / ts_keyword 1032 / ts_world 14 /
          ts_corpus 5)、**パーセントエンコード slug の term は 6 taxonomy とも 0 本**
          (旧: 場当たり term 184 本)
        - **(C) 共有世界板の作者誤認 = 解消**(語彙外 ts_author term 0)
        - **(D) 単発作品の作者欠落 = 解消**。**ts_author を持たない親 work 投稿 0**(旧 266)。
          割当 ts_author も **4363/4363** で全投稿被覆
        - **(E) verify の検査項目 = 拡充済み**。投稿数・orphan・taxonomy 別の
          「WP / 語彙 / 語彙外 / 未作成」・taxonomy 別の割当数照合・未解決値の一覧を出す。
          期待値を catalog から**毎回再計算**して importer と独立に数え直す作りになっている
        - **新たに判明して修正された 6 件目**: 旧 importer は **ts_world を一度も付けていなかった**
          (`割当 ts_world: WP=0 期待=420`)。修正後は **420/420**
      - **2.2 最終検収 (2026-08-30、コミット `322522a8` + `2fe08197`)**:
        下の「残っていた未達 1 件」も**解消し、`wp ts verify` が初めて OK で終わった**。
        `terms.json` の各 term に**照合用の異表記 `lookup_variants`(genre 51 / type 26 /
        keyword 88)**が入り、半角 `?` 形が正規の term に吸われるようになった。実測:
        - `wp ts sync-terms` = **created=0 updated=1 skipped=1735 / 1.1 秒**
          (updated 1 = 擬似作者「(シリーズ目録)」の全角括弧化のみ)
        - `wp ts import`(全量)= **created=0 updated=5295 skipped=0 / 2 分 18 秒**
          (HASH_VER v3 で全件が差分扱い。reset は不要だった)
        - **`wp ts verify` = OK**。割当は **ts_author 4363 / ts_genre 5131 / ts_type 4381 /
          ts_keyword 5424 / ts_world 420 / ts_corpus 3844** が**全て期待値と一致**、
          語彙外 term 0・未作成 0・orphan 0、**「term 未解決の値」の警告は消滅**
        - `wp ts import --dry-run` = **created=0 / updated=0 / skipped=5295**(冪等)
        - 合流の確認: `ts_genre` の `gakuen` が **745 件**(`学園?` 表記 20 話が合流)、
          `ts_type` の `henshin` が **1,892 件**(旧 `変身(?)` の場当たり term 1,886 が本来の
          term に統合)。`ts_catalog_commit` = **`322522a8f0248aea3976cbe23e1b9753e3b4292b`**
      - **2.2 に残っていた未達 1 件 (→ `322522a8` で解消済み。経緯として残す)**:
        **分類語彙の異表記 166 種が term に解決できず、割当 448 件が捨てられていた。**
        import が `Warning: term に解決できず割当を落とした値: 164 種`、verify も
        `term 未解決の値 (164 種)` を出して **verify NG** で終わる。内訳(catalog 側で再集計):
        **ts_genre 52 種 / 延べ 169・ts_type 26 種 / 延べ 105・ts_keyword 88 種 / 延べ 174**。
        値は `学園?` `コメディ?` `魔法?` `変身?` `理解ある女友達(?)` `メカ?` のような
        **末尾に半角 `?` や `(?)` が付いた形**。
        **原因**: 1.3 の正規化は**全角「？」の除去**しかしておらず、原本に多い
        **半角 `?`** の異表記が `terms.json` の `name` にも `raw_variants` にも入っていない。
        importer は name / raw_variants / slug の対応表でしか引かないので解決できず捨てる。
        **直す場所は `terms_build.py`(半角 `?`・`(?)` も正規化して raw_variants に載せる)**で、
        importer 側ではない。直したら `make catalog` → deploy → import のやり直しが要る
      - **同じ原因の副作用 (verify の割当数不一致 1 件)**: `割当 ts_genre: WP=4974 期待=4963` の
        **+11 は全て `sf` term**。`SF?` / `SF(?)` を持つ 11 話が該当する。
        importer の `term_ids()` は対応表に無い値を `?? $v` で**slug とみなして**
        `get_term_by('slug', 'SF?')` を引き、WP の `get_term_by` が内部で `sanitize_title()` を
        掛けて `?` を落とすため **偶然 `sf` に当たって割り当たる**。
        一方 verify の期待値計算は `?? null` で数えない。**import と verify で
        未マップ値の扱いが非対称**なのが直接の原因(上の raw_variants を直せば
        両方とも正規に解決するので、この不一致も自然に消える)
        → **`322522a8` の `lookup_variants` 導入で予告どおり両方とも解消**
        (`割当 ts_genre` は 4974/4963 の不一致 → **5131/5131 の一致**へ)
      - **⚠ 2.2 に残る唯一の既知バグ (Fable 所管。実行セッションは未修正)**:
        **`child_slugs()` はアンカー付きの話で全角→半角の変換が効かない。**
        投稿 ID 8038(`novel__story3.html@ＢＢＳ`)の slug が
        `story3-%ef%bd%82%ef%bd%82%ef%bd%93` のままで、期待の `story3-bbs` にならない
        (**パーセントエンコードの post_name は 4,363 投稿中この 1 件のみ**)。
        原因は**処理順**で、`mb_convert_kana($s,'as')` を掛けた**後**に
        `$s .= '-' . strtolower($ep['source_anchor'])` でアンカーを連結しているため、
        **アンカー側の全角文字が変換を通らない**(`strtolower` はバイト単位なので
        全角大文字も落ちない)。サーバの PHP に mbstring はあり、
        `mb_convert_kana("story3-ｂｂｓ","as")` は `story3-bbs` を返すことを実測で確認済み。
        直し方はアンカー連結の**後**に変換を掛けること
      - **2.2 初回実行時の記録 (2026-08-30。修正前。上記のとおり全て解消済み)**:
        6 サブコマンドは全て登録され動作する(`wp help ts` で確認)。ただし以下が未達:
        - **(A) 冪等性が成立しない** — 受け入れ条件「2 回目 created=0 / updated=0」に対し
          実測は **created=0 / updated=1864**(全量)・**updated=76**(パイロット 100 話)。
          `updated` は 3 回・4 回目も同値で発散はしないが 0 にならない。
          原因: `upsert_work()` と `fill_single_work()` が**同一投稿の同一メタキー
          `_ts_import_hash` を奪い合っている**。単発作品は 1 投稿を両者が触るため、
          works ループが work JSON の md5 を書く → episodes ループが `single:` 付き md5 で
          上書き、を毎回繰り返す。**単発 work 1 件につき 1 回の実行で 2 updated**。
          実測が `2 × 932(単発 work 数) = 1864` と完全一致することで裏付け済み
        - **(B) 分類語彙が sync-terms の語彙に載らず、場当たり term が 184 本増殖**。
          `apply_episode_data()` は `wp_set_object_terms($id, $names, $tax)` を **name 解決**で
          呼ぶが、WP の `term_exists()` は **slug 照合が先**で、`sanitize_title('学園?')` と
          `sanitize_title('学園')` はどちらも `%e5%ad%a6%e5%9c%92` に潰れる。
          episodes.jsonl の値(`学園?`)は terms.json の正規化名(`学園`)と一致しないので
          まず `学園?` という term が新規作成され、以後 `学園` もその term に吸われる。
          実測: **ts_genre 割当 5,135 のうち 3,682 (72%) / ts_type 4,381 のうち 3,438 (78%) /
          ts_keyword 5,424 のうち 2,200 (41%)** が、catalog 由来 term ではなく
          **パーセントエンコード slug の場当たり term**に付いた(1.3 の正規化が事実上無効化)。
          恒久 URL にもなるので要修正。**term は slug で解決すべき**
        - **(C) 共有世界の板 slug が `ts_author` になる** — `apply_episode_data()` が
          `ts_author` を `$ep['kansou_slug']`(= 感想板 id)から付けるため、authors.json に
          存在しない **11 板 slug**(kayo_chan 218 / himekami 41 / foster 21 / 2daime 12 /
          utanotsuki 11 / d_angel 11 / delayed 10 / relay_novel 8 / setubou 2 /
          rental_body 1 / sugar_sweets 1 = **延べ 336 話**)が作者 term として作られた。
          これは台帳 1.4 訂正 (b) が名指しで警告していた誤認そのもの
        - **(D) 単発作品 266 件が作者を失う** — `apply_episode_data()` は
          `wp_set_object_terms($id, $ep['kansou_slug'] ?: null, 'ts_author')` なので
          **kansou_slug が空(957 話)だと ts_author を空にする**。単発作品では
          `upsert_work()` が works.jsonl の `author_slug` で正しく付けた作者を
          直後の `fill_single_work()` が消してしまう。
          実測: **ts_author 無しの投稿 957 / うち単発 work 266**(連載の親 work は 0)。
          Phase 4 の作者ページ・五十音索引が欠落するので公開前に要修正
        - **(E) `verify` の実装が docblock より狭い** — 実装は「投稿数照合」と
          「手動編集警告」だけ。台帳が要求する **orphan / taxonomy 被覆率 /
          特殊エントリ内訳 / 冪等性(2 回目 created・updated 0)** は未実装。
          上記 (A)〜(D) が verify OK をすり抜けたのはこのため
        - 参考(不具合ではない): authors.json の `karaage_New` は WP が slug を小文字化して
          `karaage_new` になる。`unattributed` / `series-index` は authors.json に無い
          擬似作者なので import 側で term 名 = slug のまま作られる
        - **修正記録 (2026-08-30, Fable)**: (A)〜(E) を commands.php で修正済み —
          (A) 単発 fill の hash を `_ts_fill_hash` に分離 /
          (B) terms.json の name・raw_variants→slug 対応表を積み全 taxonomy を **term ID で割当**。
          未解決値は場当たり term を作らず option `ts_term_warnings` に記録。あわせて未実装だった
          **ts_world 割当 (episode_worlds 408 件)** を追加 /
          (C)(D) ts_author は作品の author_slug のみから。kansou_slug はメタ限定、単発 fill は作者に
          触らない。擬似作者 `unattributed`(作者不詳)/`series-index` は sync-terms が term を作る
          (sync-terms の期待 created は 1,734→**1,736**) /
          (E) verify に orphan・語彙整合(語彙外/未作成 term)・taxonomy 割当数の catalog 独立再計算
          照合・term 未解決値チェックを実装(冪等は `import --dry-run` 2 回目 created=0/updated=0 で確認) /
          子投稿の URL slug は原ファイル名の語幹から決定的に生成(`child_slugs()`: 作品内重複は
          親ディレクトリ前置→日付後置→jsonl 順連番。全て ASCII になる)。
          `HASH_VER=v2` のため既存投稿は全て更新対象。**再投入は reset --yes → sync-terms →
          import → verify → import --dry-run(冪等確認)の順**
- [x] 2.3 デプロイスクリプト `scripts/wp/deploy.sh`: rsync(`-n` 先行・`--delete` 禁止)で
      catalog/ bodies/ mu-plugins/ を配備(mu-plugins → `public_html/wp-content/mu-plugins/`)。
      **robots.txt(0.4 草案)を `public_html/` へ配備し curl で内容確認**もここに含める
      - **2.3 実測 (2026-08-30)**: 引数なし = dry-run、`--apply` = 実配備。dry-run で
        宛先 4 つ(repo / bodies / mu-plugins / robots.txt)以外に転送が無いこと・
        `--delete` が無いことを確認してから `--apply`。**所要 7.5 秒**
        (bodies 120MB は転送済みだったため差分のみ)。サーバ側 `php -l` は
        commands.php・ts-library.php とも **No syntax errors**、ローダも別途 OK。
        配備後: `~/novels.xwp.jp/repo` が HEAD `cf20f746` に一致、`repo/bodies` **3,642 本**、
        `repo/catalog/episodes.jsonl` **3,844 行** / `works.jsonl` **1,451 行**。
        robots.txt は curl 200 でローカル `scripts/wp/assets/robots.txt` と**バイト一致**
      - **補足**: publish-runbook は deploy.sh が `themes/` と `assets/annex-img/` も運ぶと
        書いているが、**現行 deploy.sh は運ばない**(2.7 は手動 rsync で実施した)。
        どちらかに合わせること
        → **解消 (2026-08-30, Fable)**: deploy.sh に [5] 画像(`git ls-files novel` の
        jpg/gif/png/bmp → `assets/annex-img/`)と [6] `themes/ts-bunko`(存在すれば)を追加。
        runbook の記述どおりになった
- [x] 2.4 サーバでパイロット投入: `wp ts import --limit=100`(1.5b 確定済み分のみ)→ 検収は
      **WP-CLI(`wp post list --post_type=ts_work`、meta 確認)+curl(キャッシュバスター付)**で:
      パーマリンク・タクソノミー・メタ・noindex メタ/ヘッダ
      - **2.4 実測 (2026-08-30)**: 直前に復旧点 `~/dumps/pre-pilot-20260830-1734.sql`。
        `wp ts sync-terms` = **created=1734 updated=0 skipped=0 / 5.6 秒**
        (= 作者 322 + genre 196 + type 165 + keyword 1,032 + world 14 + corpus 5。catalog と完全一致)。
        `wp ts import --limit=100` = **created=1513 updated=38 skipped=0 / 15.5 秒**。
        **`--limit` は episodes ループにしか効かない**(works 1,451 は毎回全部投入される)ので
        created 1,513 = works 1,451 + 連載話の子投稿 62、updated 38 = 単発 work への本文流し込み。
        62 + 38 = 100 で辻褄が合う(仕様どおり。台帳の「100 件」という語感とはずれる)。
        再実行で **created=0**(パイロットの受け入れ条件は達成)。ただし updated は 76 で
        0 にならない → **2.2 (A)** 参照。
        curl 検収(全て `?_cb=$RANDOM` 付き): 単発 work `/works/yaji-trans11/` **200
        `<title>trans11 – 少年少女文庫</title>`**、`/works/unattributed-hanayochanshiriizu-hobosan/`
        **200**、`/works/mashiro_yuu-ahujouri03-1/` **200**、連載子投稿
        `/works/johdan-tsukinuke/<話>/` **200 `<title>突き抜け …</title>`**、
        `/works/sakiemon-hanayochanshiriizu/<話>/` **200**
      - ~~**要判断**: 連載の子投稿の slug が題名由来のパーセントエンコードになる~~
        → **決着 (2026-08-30、コミット `70873826`)**。`upsert_episode()` が
        **原ファイル名の語幹から決定的に ASCII slug を作る**ようになった
        (例 `/novel/unattributed-triangle-equation/triangle_equation2/`、
        `henkaku25` `hunter25` `revival_girl25`)。
        **パーセントエンコードの post_name は 4,363 投稿中 1 件だけ**残る:
        ID 8038「ＢＢＳ☆ハプニング！(原題：BBS OOOOOOPS!)」の
        `story3-%ef%bd%82%ef%bd%82%ef%bd%93`(= `story3-ｂｂｓ`。原ファイル名の語幹が
        **全角英字**なので `sanitize_title` がエンコードする)。実害は小さいが
        気になるなら slug 生成で全角→半角の正規化を足す
      - **URL 基底が `/works/` → `/novel/` に変更された (2026-08-30、コミット `2bd22a7d`。
        ユーザ裁定)**。上の実測ログの `/works/…` は当時の値。現行 URL は `/novel/…`。
        なお旧 `/works/<slug>/` は WP の正規化リダイレクトで **301 → `/novel/<slug>/`**
        になり(404 ではない)、旧 URL も生きたまま
- [x] 2.5 全量メタ投入(本文なし)。直前に `wp db export` で復旧点。
      **冪等性テスト: 2 回目 import が created=0 / updated=0**
      - **2.5 再投入の検収 (2026-08-30、修正コミット `70873826` / `2bd22a7d`)**:
        `wp ts reset --yes`(👤 手動。投稿 4,363・term 1,931 を削除)→
        **`wp ts sync-terms` = created=1736 updated=0 skipped=0 / 5.6 秒**
        (1,734 + 擬似作者 `unattributed` `series-index` の 2)→
        **`wp ts import`(全量)= created=4363 updated=932 skipped=0 / 2 分 08 秒**
        (4,363 = 全投稿の新規作成、932 = 単発 work への本文・メタ流し込み)。
        **`wp ts import --dry-run` = created=0 / updated=0 / skipped=5295**、
        **実 import でも created=0 / updated=0 / skipped=5295 (1 分 48 秒)**
        → **冪等性の受け入れ条件を実測で達成**。
        `ts_catalog_commit` = **`2bd22a7d312ffb09d5009bc20ee9a55a3f91bb45`**。
        投稿は **ts_work 4,363(親 work 1,451 / 子投稿 2,912)**、orphan 子投稿 0。
      - **`wp ts verify` の全出力 (再投入後)**: 投稿数 WP=4363 期待=4363 /
        orphan 子投稿 0 / 語彙外 term は 6 taxonomy とも 0・未作成 0 /
        割当 **ts_author 4363=4363・ts_type 4276=4276・ts_keyword 5250=5250・
        ts_world 420=420・ts_corpus 3844=3844** はすべて一致、
        **ts_genre のみ WP=4974 期待=4963(+11)** で不一致。
        これと `term 未解決の値 (164 種)` の 2 件が残っているため
        **verify の終了は依然 NG**。原因と直す場所は 2.2 の「残る未達 1 件」を参照。
        **投入そのものは完走しており、残件は分類語彙の異表記マップだけ**
      - **2.5 最終状態 (2026-08-30、コミット `322522a8` + `2fe08197`。reset なし)**:
        `sync-terms` **created=0 updated=1 skipped=1735** → `import` **created=0 updated=5295
        skipped=0 (2分18秒)** → **`wp ts verify` OK(全項目一致)** →
        `import --dry-run` **created=0 / updated=0 / skipped=5295**。
        **Phase 2 の投入は受け入れ条件をすべて満たした**。
        `ts_catalog_commit` = `322522a8f0248aea3976cbe23e1b9753e3b4292b`
      - **最終の受入 curl (すべて `?_cb=$RANDOM` 付き)**: トップ `/` **200**、
        見出しが **`<h1 class="wp-block-heading has-text-align-left">作品一覧</h1>`**
        (「ブログ」の出現 **0 箇所**、placeholder ナビ `href="#"` **0 個**)、
        作品リンク 12 件はすべて `/novel/` /
        連載子投稿 `/novel/johdan-tsukinuke/19204751-tsukinuke/` **200
        `<title>突き抜け – 少年少女文庫</title>`** /
        ジャンルアーカイブ **`/genre/gakuen/` 200 `<title>学園 – 少年少女文庫</title>`**
        で作品 10 件(『学園?』表記の合流を目視確認)/
        旧 `/works/yaji-trans11/` は **404**(`2fe08197` の裁定変更で 301 推測リダイレクトを停止)
      - **初回の受入 curl (2026-08-30。`/works/` 時代の記録)**: トップ `/` **200**
        `<title>少年少女文庫</title>`・作品リンク **12 件が全て `/novel/`**(旧 `/works/` は 0)/
        単発作品 `/novel/yaji-trans11/` **200 `<title>trans11 – 少年少女文庫</title>`** /
        連載の子投稿 `/novel/unattributed-triangle-equation/triangle_equation2/` **200**
        (ASCII slug) / 作者アーカイブ `/authors/johdan/` **200 `<title>城弾 – 少年少女文庫</title>`**
        で作品が 10 件並ぶ(旧バグ (D) の修正確認)/ 旧 `/novel/` 以前の
        `/works/yaji-trans11/` は **301 → `/novel/yaji-trans11/`(最終 200)**
      - **2.5 初回実行の記録 (2026-08-30。修正前。上の再投入で置き換わっている)**:
        復旧点 `~/novels.xwp.jp/backup-phase2-20260830.sql` (1.8MB)。
        `wp ts import` (limit なし) = **created=2850 updated=970 skipped=1475 /
        2 分 00 秒**(created+updated+skipped = 5,295 = works 1,451 + episodes 3,844)。
        `wp ts verify` = **投稿数 WP=4363 期待=4363 (works 1451 + episodes 3844 − 単発 932)
        → verify OK**。`wp option get ts_catalog_commit` =
        **`cf20f74652792360cbce69743bcab5ec1c987b02`**、`ts_last_import_at` = 2026-08-30T08:42:24Z。
        `ts_corpus` の内訳も catalog と完全一致(honkan 2,887 / uncatalogued 859 /
        legacy 97 / extern-repost 1 / dojo 0)。
        **2 回目の `wp ts import` = created=0 / updated=1864 / skipped=3431 (2 分 00 秒)**
        → **受け入れ条件「created=0 かつ updated=0」を満たさない**。原因と実測は 2.2 (A)。
        あわせて 2.2 (B)(C)(D) の語彙・作者の取り違えも要修正なので、
        **修正後に `wp ts reset --yes` → 再 import して 2.5 をやり直すこと**
        (→ 実施済み。結果は上の「2.5 再投入の検収」)
      - **運用上の注意**: `wp ts reset --yes` は Claude Code の権限分類器に掛かるため
        **実行セッションからは実行できない**。やり直しが要るときは 👤 ユーザに手動実行を
        依頼する(迂回して `wp db query` 等で消さないこと)
- [ ] 2.6 .htaccess の noindex/410 ブロック管理(**v1.5: 全域 noindex は行わない**。
      恒久 noindex 層 /boards/ /dojo/ のヘッダと denylist 410 のみ):
      (a) サーバ上で `cp .htaccess .htaccess.bak-YYYYMMDD` を必ず先行
      (b) `scripts/wp/gen_htaccess.py` は**マーカーコメント間の追記ブロックのみ**をローカル生成
      (c) 挿入は ssh 経由のサーバ上編集で行い、**既存 .htaccess 全文をローカルに保存・コミットしない**
      (d) 直後に無関係 URL 1 本の 200 と `X-Robots-Tag` ヘッダを curl(バスター付)で確認、
      失敗時は .bak を戻す
      - **未着手。Phase 2 で唯一残っているタスクで、親セッション (Fable) 所管**
        (本番 .htaccess を触るため。実行セッションは着手しない)
- [x] 2.7 novel/ 画像 **1,196 点**(約 59MB。数え方: `git ls-files novel` の `.jpg` 562 + `.gif` 614 + `.png` 19 + `.bmp` 1)を `public_html/assets/annex-img/` に相対構造ごと rsync
      - **2.7 実測 (2026-08-30)**: 点数は台帳どおり **1,196**(jpg 562 / gif 614 / png 19 / bmp 1)、
        実サイズ **59,719,763 バイト**。`git ls-files novel` を拡張子で絞った一覧を
        `rsync --files-from` に渡し、`-n` で 1,196 件・宛先・`--delete` 無しを確認してから実配備
        (**12.9 秒**)。宛先ディレクトリは事前に `mkdir -p` が要る(rsync が自力で作れず一度失敗した)。
        結果: `assets/annex-img/` に **1,196 ファイル / 60MB**。
      - **配置は `assets/annex-img/novel/<元の相対パス>`**(`novel/` を残した)。
        理由: `bodies/*.md` の画像参照は `<img src="maidgirls.gif">` のように
        **元 HTML と同じディレクトリ前提の裸のファイル名**なので、
        `source_path` のディレクトリ部分をそのまま連結すれば解決できる形にした。
        3.2 の書換もこの前提で書くこと
      - 検証(`?_cb=$RANDOM` 付き): `/assets/annex-img/novel/youma/youma1.jpg` → **200 image/jpeg 9,391B**、
        `/assets/annex-img/novel/youma/youma0.gif` → **200 image/gif 5,027B**

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
- [ ] 7.4 公開整備(v1.5 で「解禁」は消滅 — 最初から公開): sitemap.xml 生成
      (恒久 noindex 層を除く)・robots.txt の本番内容を curl で最終確認・
      **恒久 noindex 境界の確認(/boards/ /dojo/ が noindex、/novel/ 等が index 可)**。
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
