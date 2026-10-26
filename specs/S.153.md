# S.153 作品クラスタの手動修正の検証

- 種類: 手順
- 状態: 有効
- 出典: docs/cluster-overrides-spec.md の「8. 検証」
- 関連: T.24.8

## 8. 検証

| 確認 | 期待 |
|---|---|
| `make check` | OK (overrides の参照が全部実在、orphan 0) |
| `catalog/reports/work_builder.json` | `works` が裁定どおり増減、`needs_review` が 331 から減る |
| 末尾が助数詞の作品題名 | 0 件 |
| 旧 URL 3 本 | 301 → 新 URL (200) |
| `wp ts verify` | OK |
