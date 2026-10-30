# S.125 取り下げ申請のデータ

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「データ」
- 関連: T.26.3.1, S.215

## データ

- CPT **`ts_takedown_request`**: `public=false` / `show_ui=true` / `exclude_from_search=true` / `show_in_rest=false`。管理画面に出す条件は cap `ts_manage_takedown`。`post_title` = 「申請 <ID> <pen_name>」(<ID> は申請の投稿 ID)、本文は使わない。
- **`wp ts reset` の対象に入れない** (`TS_Command::TYPES` に加えない)。運用の原本であり、catalog から再生成できないため。
- post meta:

| meta | 内容 |
|---|---|
| `_tr_status` | S.126 の状態 |
| `_tr_pen_name` `_tr_contact` `_tr_relation` `_tr_evidence` `_tr_message` `_tr_allow_name` | 入力値 |
| `_tr_targets` | JSON: `[{url, post_id|null, work_slug|null}]` |
| `_tr_token` | 確認トークン (32 byte random、`wp_hash` で保存) |
| `_tr_received_at` | フォーム送信時刻 |
| `_tr_confirmed_at` | 確認リンクを踏んだ時刻 (以下「確認時刻」) |
| `_tr_auto_apply_at` | 確認時刻 + `AUTO_APPLY_DAYS` |
| `_tr_resolved_at` `_tr_resolution_note` `_tr_resolved_by` | 承認・却下・復帰の記録 (理由は内部メモ) |
| `_tr_ip_hash` `_tr_ua` | 受付時の送信元 (ハッシュ) と UA |

- 連絡先は WP の DB にだけ置く。**リポジトリ (`takedown/denylist.yml`) には書かない** (S.215 (元の `takedown/README.md`) の約束)。DB dump は私有ストレージへ (既存運用 T.38.1)。
- 保持: 解決 (`applied`/`rejected`/`reinstated`/`expired`) から **`RETENTION_DAYS` (既定 365 日)** 経過で `_tr_contact` `_tr_evidence` `_tr_message` `_tr_ip_hash` `_tr_ua` を消す (申請の骨格と結果は残す)。

## 実装の記録 (2026-10-30、T.26.3。未配備)

- 権限: 投稿タイプの権限 (一覧・閲覧・起票・操作) をすべて `ts_manage_takedown` 1 つに結ぶ (`map_meta_cap=false`)。申請の投稿はいつも `private` (管理画面の「公開」でも公開状態にしない)。
- 時刻のメタは Unix 秒 (UTC)。画面とメールの表示はサイトのタイムゾーン。
- 表に無いメタ: `_tr_via` (受け付けた経路。`takedown_form` か、運営委員が起票した `email`。`export-takedowns` の `received_via` になる)、`_tr_reminded_at` (リマインドを送った時刻。1 回だけ送るため)、`_tr_log` (操作 1 回 1 行の履歴。`_tr_resolved_*` は最後の操作しか持てないので、承認の後の復帰などを追えるように)。
- 保持期限の消去は cron (S.130) の最後に行い、`_tr_contact` `_tr_evidence` `_tr_message` `_tr_ip_hash` `_tr_ua` を消す。
- `wp ts reset` の対象外であることは `scripts/wp/ts_library_test.php` が `TS_Command::TYPES` と `wp ts reset --help` の説明文で確かめる。
