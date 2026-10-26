# S.198 企画・アンソロジー (T.10.5)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 4.8
- 関連: T.10.5

- [x] T.10.5 (企画・アンソロジー) **企画・アンソロジー** (S.41): `special/` 61 ファイル(03summer 等)を
      アンソロジー区画 (`ts_corpus=anthology`) または `ts_doc` として収録
      - **企画・アンソロジーの実測 (2026-08-30)**: `wp ts sync-docs` の **created=34** に
        03summer の解題 1 本が含まれる(内訳は S.200)。
        `ts_corpus=anthology` は使わず **`ts_doc` に寄せる**実装になっている
        (T.7.3 の実測どおり `anthology` は実データに無い語彙なので妥当)
