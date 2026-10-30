# S.132 takedown/denylist.yml との整合

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「takedown/denylist.yml との整合」
- 関連: T.26.3.7

## `takedown/denylist.yml` との整合

- **denylist.yml = git に残す確定記録、申請 CPT = 運用の原本。** 手動エントリ (S.222〜) は従来どおり denylist → `apply-takedown`。
- `wp ts export-takedowns [--since=<date>]`: `applied` / `approved` / `reinstated` の申請を denylist スキーマ (version 1) のエントリとして標準出力に出す。`id` は次の S 番号 (`S.n`。同時にその取り下げの記録の S を `specs/S.n.md` に作り、SPECS.md の一覧に足す)、`target` は `kind: work` + `work_slug` (対象が複数なら複数エントリ)、`reason: 取り下げフォーム経由の申し出 (申請 <投稿 ID>)`、`reason_class: author_request`、`requested_at` = 確認時刻の日付、`received_via: takedown_form`、`status: applied` (復帰は `reinstated`)、`applied.wp_draft` = 適用日、`public_note` = S.131 の文言。**連絡先・本文・証拠は出さない。**
- S.69〜S.71 に 1 手順: publish の前に `export-takedowns` → denylist に追記 → `make check` → コミット (原本アーカイブの除外 = T.35.2 に伝播する)。
- `wp ts verify` に「`applied`/`approved` の申請で denylist に対応エントリが無いもの」の件数を足す (0 でなければ警告)。

## 実装の記録 (2026-10-30、T.26.3。未配備)

- `wp ts export-takedowns [--since=<date>] [--start=<n>] [--repo=<path>]`。id は `--start` か、サーバの repo の SPECS.md の「次は S.n」から振る。標準出力は denylist の `entries:` にそのまま足せる YAML だけで、振った id などの案内は標準エラーに出す。
- 1 申請の対象ごとに 1 エントリ。作品は `kind: work` + `work_slug`、話だけを指した申請は `kind: episode` + `source_path` (アンカー分割話は `path#anchor`)。`public_note` はエントリごとに作る (名前を出さないときは「1 作品」)。
- `requested_at` は確認時刻の日付、`received_via` は `takedown_form` か (運営委員が起票した申請は) `email`。`applied` は `wp_draft` と `htaccess_410` に適用日 (読者に 410 が返り始めた日。denylist の冒頭の説明どおり) を入れ、ほかは null。
- denylist に書き写し済みの申請 (`reason` の「(申請 <ID>)」で結ぶ) は出さない。書き写した後に復帰した申請は、既存のエントリの `status` を `reinstated` に直すよう警告で知らせる (新しいエントリは作らない)。書き写す前に復帰した申請は出さない。
- 引用符の要る値は単一引用符で囲む。あわせて apply-takedown の denylist の読み取りを、`value` の `path#anchor` (引用符なしの「 #」だけをコメントとみなす) と単一引用符の中の `''` に対応させた。
- `wp ts verify` は「`applied`/`approved` の申請で denylist に対応エントリが無いもの」を数え、0 でなければ警告する (verify を NG にはしない。自動適用から書き写すまでの間は当然に残るため)。
