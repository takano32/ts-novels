# S.174 作者の同定 (T.7.4、元の 1.4)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 1.4
- 関連: T.7.4

- [x] 1.4 `scripts/wp/authors_build.py`: `catalog/authors.json` 生成
      - slug = 感想板 author_id(目録の `bbs@log_<id>.cgi` リンク。305 slug)。表記揺れ統合 48 組
      - **板なし作者 33 名のローマ字 slug は pykakasi(開発機 pip 導入可)で候補生成し、
        `catalog/slug_overrides.yml` に全数出力 → 人間 (サイト所有者) の確認後に確定**(恒久 URL のため幻覚読み厳禁)
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
      - **タスクリストの記述の訂正 2 点**:
        (a) 受け入れ条件「作者数 ≈ 399」は達成不能。**399 は survey の
        `author_count` = 表示名の異なり数であって人数ではない**。表記揺れを
        板 id で統合するのが 1.4 の仕事なので、統合すれば必ず 399 より減る。
        内訳は survey の他の数字とも整合する (自前板 297 + 共有板のみ 31 ≒ 328)。
        実装の自己検査は「延べ表示名 − 統合分 == 作者数」に置き換えた
        (b) 共有シリーズ板は 7 つではなく **11 板**。設計の 7 つ (kayo_chan / himekami /
        foster / relay_novel / delayed / 2daime / utanotsuki) に加えて
        **d_angel (ダーティーエンジェル、3 作者)・setubou (切望)・rental_body・
        sugar_sweets** が実在する。これを作者板と誤認すると別人が 1 作者に潰れる
      - ~~**人間の判断待ち (同名別鍵 2 件)**~~ → **T.7.5.1 (S.176) で決着 (2026-08-30)**。
        `神川綾乃` は同一人物で `kamikawa_ayano` が正(`kamikawa_ayano_` は実在しない板)、
        `コーディー` と `ジャージレッド` は**別人**で コーディー の正は `kohdhi`。
        いずれも目録の感想リンクの誤植 1 箇所が原因で、`catalog/episode_overrides.yml` で
        上書きした。「作者の併合」も `slug_overrides.yml` の**同じ slug を複数の表示名に
        書く**形で表現できるようにした(実装は S.176)
      - **1.4 の数値は T.7.5.1 (S.176) で更新された**: 作者は **342 → 322 名**
        (板あり 294 → **300** / 板なし 48 → **22**)、slug 確認待ち 40 → **0**。
        上の「1.4 実測」は T.7.5.1 実施**前**の値なので、現在値は S.176 を見ること
