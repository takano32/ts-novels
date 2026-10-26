# S.136 取り下げの検証手順 (配備後)

- 種類: 手順
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「13. 検証手順」
- 関連: T.26.6

## 13. 検証手順 (配備後)

1. 試験用の公開作品 1 本を対象にフォーム送信 → 申請が `unconfirmed`、メール①が届く。
2. 確認リンク → `received`、`auto_apply_at` = T0 + 10 日、メール②が届く。
3. `auto_apply_at` を WP-CLI で過去に書き換え → `wp cron event run ts_takedown_tick` → `applied`、対象が `private`/410、`/removed/` に出る、メール④。
4. 「復帰」→ `publish`、`/removed/` から消える。
5. スパム系: honeypot 入り → 400、送信 3 秒未満 → 400、同一 IP 1 時間 4 件目 → 429、他サイトの URL → 保存されるが警告フラグ。
6. `wp ts reset --yes` を**使わずに** (本番では流さない) ローカルの同等環境がないため、`TYPES` に含まれないことはコードレビューと `wp ts reset --help` の説明文で確認する。
7. `wp ts export-takedowns` の出力を denylist に追記して `make check` が通る。
