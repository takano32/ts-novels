# S.126 取り下げ申請の状態機械

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「状態機械」
- 関連: T.26.3.3, T.26.3.4

## 状態機械

```
[フォーム送信] → unconfirmed ──(確認リンク)──→ received (確認時刻) ──(確認時刻から 10 日, cron)──→ applied
                     │ 7 日で未確認                     │                              │
                     └→ expired          運営委員: approved (即時適用) ────────────┘   運営委員: reinstated
                                          運営委員: rejected (自動適用を止める)
```

| 状態 | 意味 |
|---|---|
| `unconfirmed` | 受付済み。確認メール待ち |
| `received` | 本人確認 (メール) 済み。自動適用の待機中 |
| `applied` | 自動適用済み (非公開) |
| `approved` | 運営委員が承認し即時適用 (非公開) |
| `rejected` | 運営委員が却下。自動適用しない。記録は残す |
| `reinstated` | 非公開後に復帰 |
| `expired` | 確認されずに失効 |

| 契機 | 遷移 | 副作用 |
|---|---|---|
| フォーム送信 | → `unconfirmed` | 受付のメール (依頼者) |
| 確認リンク (`?ts_confirm=<token>`) | `unconfirmed` → `received` | 確認時刻・`auto_apply_at` を記録。確認完了のメール (運営委員) |
| cron: `now ≥ received_at + CONFIRM_TTL_DAYS` | `unconfirmed` → `expired` | なし |
| cron: `now ≥ auto_apply_at - REMIND_BEFORE_DAYS` (1 回) | — | リマインドのメール (運営委員) |
| cron: `now ≥ auto_apply_at` | `received` → `applied` | S.127 の処理。適用のメール (依頼者・運営委員) |
| 運営委員「承認して即時適用」 | `received` → `approved` | S.127 の処理。適用のメール |
| 運営委員「却下」 | `unconfirmed`/`received` → `rejected` | 却下のメール (依頼者。S.135 の裁定による) |
| 運営委員「復帰」 | `applied`/`approved` → `reinstated` | S.128 の復帰処理 |
| 運営委員「期限を変える」 | — | `auto_apply_at` を書き換える (早める・延ばす) |

定数 (既定): `CONFIRM_TTL_DAYS = 7`、`AUTO_APPLY_DAYS = 10`、`REMIND_BEFORE_DAYS = 3`。
