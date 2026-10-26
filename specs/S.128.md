# S.128 運営委員の操作

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「運営委員の操作」
- 関連: T.26.3.5

## 運営委員の操作 (申請の編集画面のメタボックス)

- **承認して即時適用** / **却下** (理由を内部メモに) / **復帰** / **期限を変える** の 4 つ。
- 復帰: 対象投稿の `_ts_visibility` `_ts_takedown_id` `_ts_public_note` を消し、`superseded` が無ければ `publish` に戻す。`ts_takedown_urls` から除く。`reinstated` と記録。
- すべての操作は `_tr_resolved_by` (ユーザ ID)・時刻・メモを残す。
