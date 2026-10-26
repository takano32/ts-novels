# S.121 wp ts apply-takedown の変更

- 種類: 仕様
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「6. wp ts apply-takedown の変更」
- 関連: T.23.3.3, S.76

## 6. `wp ts apply-takedown` の変更

- 対象 (+子孫) を `private` + `_ts_visibility=withdrawn` + `_ts_takedown_id=<TD>` + `_ts_public_note` (denylist の `public_note`。`/removed/` の動的生成が読む) にする。既に `private`/`draft` の投稿もメタを揃える (自己修復)。
- `status: reinstated` のエントリ: `_ts_visibility` と `_ts_takedown_id` を消し、`superseded` が無ければ `publish` に戻す。
- option `ts_takedown_urls` は監査用に引き続き記録するが、`.htaccess` の生成には使わない。
- dry-run の出力: 「private 化 N / 復帰 M / 変更なし K」。
