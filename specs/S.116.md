# S.116 運営委員ロール

- 種類: 仕様
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の冒頭と「ロール」
- 関連: T.23.3.1, T.27.5, S.43, S.102

## 運営委員ロールと可視性の仕様 (2026-10-26)

**位置づけ**: 取り下げ・旧版を private と運営委員ロールで扱う決定 (S.43。取り下げ・旧版は `private` + 運営委員ロール、410/301 は WordPress が返す) の実装仕様。
根拠と実測は S.102、実装タスクは `TASKS.md` の T.23
(T.25 版違い・T.26 取り下げフォームがこの仕様を使う)。**ロールの権限と可視性の振る舞いはこの S 群 (S.116〜S.123) を優先する。**
用語 (WP サイト/原本アーカイブ/旧サイト。S.15) は内部用語で、読者に見せる文言には使わない。

### ロール

| ロール (slug) | 表示名 | 権限 (capability) | 用途 |
|---|---|---|---|
| `ts_committee` | 運営委員 | `read` / `read_private_posts` / `ts_view_hidden` / `ts_manage_takedown` | 取り下げ済み・旧版の閲覧、取り下げ申請の確認・承認・却下・復帰 |
| `ts_committee_editor` | 運営委員 (編集可) | 上の全部 + `edit_pages` `edit_published_pages` `edit_others_pages` `publish_pages` + `edit_ts_docs` `edit_published_ts_docs` `edit_others_ts_docs` `publish_ts_docs` | 固定ページ (`/about/` 等) と解題 (`ts_doc`) の編集。所有者向け |
| `administrator` | 管理者 | WP 既定 | 運営者 (takano32)。配備・import・ユーザ発行 |

- 運営委員に **作品 (`ts_work`) の編集権限は付けない**。catalog を優先し、WP 上の編集は次の import で skip され verify が NG になり、reset で消えるため (S.100)。
- `ts_doc` は `register_post_type` の `capability_type` を `ts_doc`、`map_meta_cap` を true にする (現状は `post` 相当で、Editor 系に編集を許すと `ts_work` と区別できない)。`wp ts sync-docs` は WP-CLI なので権限の影響を受けない。
- `ts_view_hidden` と `ts_manage_takedown` は本プラグイン独自の cap。administrator にも付ける。
- **`wp ts role-sync`**: 上表のとおりに `add_role` / `add_cap` / `remove_cap` を冪等に適用する。出力は `role-sync: created=N updated=N unchanged=N`。2 回目は created=0 / updated=0 が受け入れ条件。
- アカウント運用: 発行は administrator が WP-CLI で (`wp user create <login> <mail> --role=ts_committee`)。ログイン URL は CloudSecure で変更済みの値を**文書・チャット・コミットに転記しない** (S.10 のログイン URL の変更値を転記しない決まり) — 口頭か別経路で伝える。パスワードは生成した強いもの。検証用の試験ユーザは作業後に `wp user delete <id> --yes` (投稿を持たないので再割当は不要)。
