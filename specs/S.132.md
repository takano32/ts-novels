# S.132 takedown/denylist.yml との整合

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「9. takedown/denylist.yml との整合」
- 関連: T.26.3.7

## 9. `takedown/denylist.yml` との整合

- **denylist.yml = git に残す確定記録、申請 CPT = 運用の原本。** 手動エントリ (TD-0001〜) は従来どおり denylist → `apply-takedown`。
- `wp ts export-takedowns [--since=<date>]`: `applied` / `approved` / `reinstated` の申請を denylist スキーマ (version 1) のエントリとして標準出力に出す。`id` は TODO.md の次の TD 番号 (`TD.n`)、`target` は `kind: work` + `work_slug` (対象が複数なら複数エントリ)、`reason: 取り下げフォーム経由の申し出 (申請 #n)`、`reason_class: author_request`、`requested_at` = T0 の日付、`received_via: takedown_form`、`status: applied` (復帰は `reinstated`)、`applied.wp_draft` = 適用日、`public_note` = S.131 の文言。**連絡先・本文・証拠は出さない。**
- S.69〜S.71 に 1 手順: publish の前に `export-takedowns` → denylist に追記 → `make check` → コミット (原本アーカイブの除外 = T.35.2 に伝播する)。
- `wp ts verify` に「`applied`/`approved` の申請で denylist に対応エントリが無いもの」の件数を足す (0 でなければ警告)。
