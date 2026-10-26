# S.152 再投入の方式 (全消し再構築か import --prune か)

- 種類: 設計
- 状態: 有効
- 出典: docs/cluster-overrides-spec.md の「7. 再投入の方式」
- 関連: T.24.7, S.73

## 7. 再投入の方式 (T.24.7 で決める)

| 方式 | 内容 | 利点 / 欠点 |
|---|---|---|
| (a) 全消し再構築 | (高野さんの判断) `wp ts reset --yes` → `sync-terms` → `import` → `apply-takedown` → `sync-pages/docs` (S.73 の C) | 旧 slug の投稿が確実に消える / 本番 DB を全消しする (所要と復旧点が要る)。取り下げ申請 CPT は reset の対象外なので残る |
| (b) `wp ts import --prune` | catalog に無い `_ts_work_slug` / `_ts_episode_id` の投稿を一覧し、`trash` へ (子孫も) | DB を消さない / 実装が要る。upsert だけだと旧 slug の投稿が残る (S.73 の A の注意) |

どちらでも、再投入後に `wp ts verify` OK と S.151 の 301 検証を行う。
