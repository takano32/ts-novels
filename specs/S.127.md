# S.127 非公開化の処理 (applied / approved)

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「4. 非公開化の処理」
- 関連: T.26.3.4, S.117

## 4. 非公開化の処理 (applied / approved)

1. `_tr_targets` の解決できた投稿ごとに、その投稿と子孫を `private` + `_ts_visibility=withdrawn` + `_ts_takedown_id=req:<ID>` + `_ts_public_note` (S.131 の文言) にする (S.117)。
2. option `ts_takedown_urls` に公開時の URL を追加 (監査用)。
3. 解決できなかった行があれば運営委員にその旨を通知 (できた分だけ適用する)。
4. 既に `withdrawn` (denylist 由来) の投稿は触らない。`superseded` の投稿は `withdrawn` を上書きで立てる (優先順位どおり)。
