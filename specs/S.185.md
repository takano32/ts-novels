# S.185 全量メタ投入 (T.8.4)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 2.5
- 関連: T.8.4

- [x] T.8.4 (全量投入) 全量メタ投入(本文なし)。直前に `wp db export` で復旧点。
      **冪等性テスト: 2 回目 import が created=0 / updated=0**
      - **全量投入の再投入の検収 (2026-08-30、修正コミット `70873826` / `2bd22a7d`)**:
        `wp ts reset --yes`(高野さんが手動で。投稿 4,363・term 1,931 を削除)→
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
        **verify の終了は依然 NG**。原因と直す場所は S.182 の「T.8.2 に残っていた未達 1 件」を参照。
        **投入そのものは完走しており、残件は分類語彙の異表記マップだけ**
      - **全量投入の最終状態 (2026-08-30、コミット `322522a8` + `2fe08197`。reset なし)**:
        `sync-terms` **created=0 updated=1 skipped=1735** → `import` **created=0 updated=5295
        skipped=0 (2分18秒)** → **`wp ts verify` OK(全項目一致)** →
        `import --dry-run` **created=0 / updated=0 / skipped=5295**。
        **T.8 の投入は受け入れ条件をすべて満たした**。
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
        で作品が 10 件並ぶ(単発作品の作者欠落 (S.182) の修正確認)/ 旧 `/novel/` 以前の
        `/works/yaji-trans11/` は **301 → `/novel/yaji-trans11/`(最終 200)**
      - **全量投入の初回実行の記録 (2026-08-30。修正前。上の再投入で置き換わっている)**:
        復旧点 `~/novels.xwp.jp/backup-phase2-20260830.sql` (1.8MB)。
        `wp ts import` (limit なし) = **created=2850 updated=970 skipped=1475 /
        2 分 00 秒**(created+updated+skipped = 5,295 = works 1,451 + episodes 3,844)。
        `wp ts verify` = **投稿数 WP=4363 期待=4363 (works 1451 + episodes 3844 − 単発 932)
        → verify OK**。`wp option get ts_catalog_commit` =
        **`cf20f74652792360cbce69743bcab5ec1c987b02`**、`ts_last_import_at` = 2026-08-30T08:42:24Z。
        `ts_corpus` の内訳も catalog と完全一致(honkan 2,887 / uncatalogued 859 /
        legacy 97 / extern-repost 1 / dojo 0)。
        **2 回目の `wp ts import` = created=0 / updated=1864 / skipped=3431 (2 分 00 秒)**
        → **受け入れ条件「created=0 かつ updated=0」を満たさない**。原因と実測は S.182 の冪等性の不具合。
        あわせて S.182 の語彙外 term の増殖・共有世界の板を作者と誤認・単発作品の作者欠落 (語彙・作者の取り違え) も要修正なので、
        **修正後に `wp ts reset --yes` → 再 import して全量投入をやり直すこと**
        (→ 実施済み。結果は上の「全量投入の再投入の検収」)
      - **運用上の注意**: `wp ts reset --yes` は Claude Code の権限分類器に掛かるため
        **実行セッションからは実行できない**。やり直しが要るときは人間 (高野さん) に手動実行を
        依頼する(迂回して `wp db query` 等で消さないこと)
