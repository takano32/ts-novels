# S.150 クラスタの裁定の書き方 → YAML

- 種類: 仕様
- 状態: 有効
- 出典: docs/cluster-overrides-spec.md の「5. 裁定の書き方 → YAML」
- 関連: T.24.4, T.24.5

## 5. 裁定の書き方 → YAML

| 裁定 | YAML |
|---|---|
| ○ (正しい) | `confirmed: true` だけ |
| 分割 | `split:` に新しい作品ごとの `work_title` と `episodes` |
| 切り離し (別作が 1 話混ざっている) | `detach: [episode_id]` |
| 併合 (別クラスタと同じ作品) | 併合される側に `merge_into: "<鍵>"` |
| 題名だけ違う | `work_title:` (現行) |

転記後に `make catalog` → `catalog/reports/work_builder.json` の `works` / `needs_review` / `orphans` を記録 → `make check` `make verify`。
