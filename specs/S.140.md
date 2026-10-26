# S.140 版違いの出力

- 種類: 仕様
- 状態: 有効
- 出典: docs/versions-spec.md の「出力」
- 関連: T.25.2.2, T.25.2.4, S.212

## 出力

- `catalog/works.jsonl` の `episodes[]` に **`superseded_by`** (新版の `episode_id`、新版自身と単独の話は `null`)。親 work に **`all_superseded`** (bool)。
- `catalog/reports/versions_build.json`: `groups_total` / `auto_same` / `review_pending` / `review_decided` / `distinct` / `superseded_episodes` / `by_corpus_pair` (組合せ別の件数) / `threshold`。
- `--check` (make check): `superseded_by` が循環しない・新版自身が superseded でない・`same`/`distinct` の episode_id が実在・`superseded_episodes` が works.jsonl から数え直した値と一致。`review` に `decision: null` が残っていれば件数を**警告** (エラーにしない)。
- S.212 (元の `catalog/README.md`) に `superseded_by` / `all_superseded` の欄を追記。
