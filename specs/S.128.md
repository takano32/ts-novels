# S.128 運営委員の操作

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「運営委員の操作」
- 関連: T.26.3.5

## 運営委員の操作 (申請の編集画面のメタボックス)

- **承認して即時適用** / **却下** (理由を内部メモに) / **復帰** / **期限を変える** の 4 つ。
- 復帰: 対象投稿の `_ts_visibility` `_ts_takedown_id` `_ts_public_note` を消し、`superseded` が無ければ `publish` に戻す。`ts_takedown_urls` から除く。`reinstated` と記録。
- すべての操作は `_tr_resolved_by` (ユーザ ID)・時刻・メモを残す。

## 実装の記録 (2026-10-30、T.26.3。未配備)

- 操作は申請の編集画面のメタボックスのボタンで、保存の処理の中で `ts_manage_takedown` と nonce を確かめる。S.126 の遷移に無い組み合わせは何もせずに知らせる。本体の関数 `ts_takedown_operate` は WP-CLI の `wp eval` からも呼べる。
- 内部メモは 1 つの欄で、却下の理由などに使う。依頼者には送らない。
- 期限を変える: 新しい時刻をサイトのタイムゾーンで入れる。過去の時刻なら次の cron で適用される。リマインドの記録を消すので、延ばしたときはもう一度リマインドが届く。
- 復帰: この申請で取り下げた投稿 (`_ts_takedown_id=req:<ID>`) だけを戻す。`export-takedowns` で denylist に書き写した後は投稿の印が記録の S に変わっているので、この画面では戻さずに件数を知らせる (denylist のエントリを `reinstated` にして apply-takedown で戻す。S.132)。
- `wp ts apply-takedown` は `req:` の印の投稿を denylist の復帰では戻さない (申請の側で戻す)。
