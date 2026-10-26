# S.173 分類語彙の正規化 (T.7.3、元の 1.3)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 1.3
- 関連: T.7.3, S.22, T.42.1

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
      - **タスクリストの記述の訂正・補足 3 点**:
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
        `slug_pending_review`)。**T.7.5.1 (S.176) の確認対象に含めるかは人間 (サイト所有者) の判断**
        (d) **ts_corpus は 4 語ではなく 5 語** — 実データの corpus 値は
        `honkan` 2,887 / `legacy` 97 / `uncatalogued` 859 / `extern-repost` 1 の 4 種で、
        旧実装の語彙 (`honkan`/`legacy`/`dojo`/`anthology`) では **860 話が term を持たない**
        状態だった。S.14〜S.19 の corpus 表に合わせて実データの 4 語 + T.36 用の
        `dojo` の計 5 語に直し、`anthology` は実データに無いので落とした
        (2026-08-30 修正)。**全 episode の corpus に term があること**を
        `terms_build.py` の自己検査に追加してある (被覆 3,844/3,844)
