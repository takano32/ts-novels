# S.237 所有者レビュー対応の受け入れ条件

- 種類: 仕様
- 状態: 有効
- 出典: tasks/T.23.md〜tasks/T.27.md の完了条件と S.116〜S.154 の検証の S。2026-10-30 に T.22.2 で起こした
- 関連: T.22.2, T.23, T.24, T.25, T.26, T.27, T.29.2, S.116〜S.154, S.203, S.236

## T.23〜T.27 の受け入れ条件

各大項目は、下の条件をすべて満たしたら完了とする。細部 (期待値・手順) は右の S を優先する。本番に書く作業は毎回 S.67。

| T | 受け入れ条件 | S |
|---|---|---|
| T.23 運営委員ロールと可視性の基盤 | nginx キャッシュとログインの実測が S.62 にある (T.23.1)。410 の返し方の結論が S.119 と S.43 にある (T.23.2)。`wp ts role-sync` の 2 回目が created=0 / updated=0。`apply-takedown` の dry-run が S.121 の形式。`htaccess_block.py` の dry-run に `/novel/` の gone 行が無い。配備後に private = 51・draft = 0・`wp ts verify` OK。ロールと可視性の検証の表をすべて満たす。5 文書を更新 | S.116〜S.121, S.122, S.123 |
| T.24 作品クラスタの手動修正 | 既存の work_overrides.yml で `make catalog` の出力が不変。末尾が助数詞の作品題名 0 件。レビュー資料に 71 作品 (弱根拠 39 が先頭)。裁定 (八重洲さんの判断) を転記して `make catalog && make check && make verify` OK。旧 URL 3 本が 301 で新 URL が 200 | S.146〜S.151, S.152, S.153, S.154 |
| T.25 版違いの整理 | `versions_build.json` の件数が基準値と整合 (組 303・自動 209・要確認 89、閾値は既定)。works.jsonl に `superseded_by` / `all_superseded`、`make check` OK。alias 71 話の正本が「最新」の規則どおり。import の dry-run 2 回目が created=0 / updated=0。版違いの検証の表を満たす。4 箇所の文書を更新 | S.137〜S.141, S.142, S.143, S.144, S.145 |
| T.26 取り下げフォームと 10 日後の自動適用 | 裁定待ちのパラメータが決まり文面が確定。確認時刻 + 10 日で `applied` になり対象が private/410。運営委員の操作 4 つが記録つきで動く。`/removed/` に既存 9 件 + Marie 氏の分が同じ文言で出る。`export-takedowns` の出力を denylist に足して `make check` OK。通知が本番で届く。`ts_takedown_tick` の次回時刻が進む。配備後の検証手順 (`wp ts reset` の確認を除く) をすべて満たす | S.124〜S.135, S.136 |
| T.27 about の所有者リライトと所有者アカウント | `wp ts export-pages` → `sync-pages` の往復で skipped=4。about.html に原稿と `owner-authored` 注記があり、「72 時間」と使わない語が 0 件 (T.27.3 の grep)。S.14 と S.90 を所有者の説明に訂正。所有者が取り下げ済み作品 1 本を閲覧できる | S.109, S.100, S.116 |

所有者レビューの通過に必須なのは T.16〜T.21 と T.23 (TASKS.md の実行計画)。T.24〜T.27 は所有者の裁定を得ながら T.35 以降と並行してよい。
