# S.141 版違いと wp ts import・テーマ

- 種類: 仕様
- 状態: 有効
- 出典: docs/versions-spec.md の「5. wp ts import とテーマ」
- 関連: T.25.4

## 5. `wp ts import` とテーマ

- import (`upsert_episode` / `fill_single_work`): `superseded_by` があれば `post_status=private` + `_ts_visibility=superseded` + `_ts_superseded_by`。無ければ `withdrawn` でない限り `publish` (= catalog が可視性を決める。優先順位は S.117)。`all_superseded` の親も `private` + `superseded` (+ `_ts_superseded_by` = 新版の `work_slug`)。`HASH_VER` を上げる (v6)。
- テーマ/mu-plugin: 非ログインの旧版 URL は新版へ 301、運営委員には帯「旧版 (YYYY 年の掲載) — 読者には新版を表示しています」。新版の書誌カードに運営委員だけ「旧版あり」リンク。
- `wp ts verify`: 投稿数の式は不変 (private も数える)。「本文 status」の行に `_ts_visibility` 別の件数 (withdrawn / superseded) を足す。
- 話の URL は slug から決定的に作られる (`child_slugs`) ので、新版・旧版の入れ替えで URL は変わらない。
