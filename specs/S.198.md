# S.198 企画・アンソロジー (T.10.5、元の 4.8)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 4.8
- 関連: T.10.5

- [x] 4.8 **企画・アンソロジー** (S.41): `special/` 61 ファイル(03summer 等)を
      アンソロジー区画 (`ts_corpus=anthology`) または `ts_doc` として収録
      - **4.8 実測 (2026-08-30)**: `wp ts sync-docs` の **created=34** に
        03summer の解題 1 本が含まれる(内訳は 4.6 の項)。
        `ts_corpus=anthology` は使わず **`ts_doc` に寄せる**実装になっている
        (1.3 の実測どおり `anthology` は実データに無い語彙なので妥当)
