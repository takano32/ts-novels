# S.147 作品クラスタの手動修正の適用順序

- 種類: 仕様
- 状態: 有効
- 出典: docs/cluster-overrides-spec.md の「適用の順序」
- 関連: T.24.1

## 適用の順序

`cluster()` (union-find) → `groups` → **overrides の適用** (`merge_into` → `split` → `detach` の順) → 題名・slug・alias の決定 (従来どおり)。
適用したクラスタの `evidence` に `override` を足す (S.23 `_ts_work_seed` の `override` に対応)。雛形の自動出力 (`confirmed: false` の行) は従来どおり needs_review のものだけ。
