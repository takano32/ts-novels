# S.127 非公開化の処理 (applied / approved)

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「非公開化の処理」
- 関連: T.26.3.4, S.117

## 非公開化の処理 (applied / approved)

1. `_tr_targets` の解決できた投稿ごとに、その投稿と子孫を `private` + `_ts_visibility=withdrawn` + `_ts_takedown_id=req:<ID>` + `_ts_public_note` (S.131 の文言) にする (S.117)。
2. option `ts_takedown_urls` に公開時の URL を追加 (監査用)。
3. 解決できなかった行があれば運営委員にその旨を通知 (できた分だけ適用する)。
4. 既に `withdrawn` (denylist 由来) の投稿は触らない。`superseded` の投稿は `withdrawn` を上書きで立てる (優先順位どおり)。

## 実装の記録 (2026-10-30、T.26.3。未配備)

- `_ts_takedown_id=req:<ID>` で、`_ts_public_note` は S.131 の規則で作る。あわせて `_ts_takedown_date` (確認時刻の日付) を書く (S.131 の実装の記録)。
- 既に取り下げ中の投稿は、denylist 由来でも別の申請由来でも触らない (同じ申請の分だけ揃え直す)。その別の記録が後で復帰すると、その投稿は公開に戻る。
- 解決できた対象が 1 つも無い申請も `applied` にし、運営委員宛の適用のメールに結びつかなかった URL を並べる。依頼者宛の一覧は「(なし)」になる。
- 状態を変えた投稿の `_ts_last_import_gmt` は、apply-takedown と同じく合わせる (手動編集と誤検知させない)。`ts_takedown_urls` に公開時の URL を足す。
