# S.117 可視性モデル (private と可視性メタ)

- 種類: 仕様
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「可視性モデル」
- 関連: T.23.3.2, S.102

## 可視性モデル

- 読者に見えるかどうかは **`post_status`** で表す: `publish` = 見える / `private` = 運営委員のみ。**`draft` は使わない** (現行の取り下げ 18 件 + Marie 氏分は移行で `private` にする)。
- 理由は post meta **`_ts_visibility`** で表す:

| `_ts_visibility` | 意味 | 付随メタ | 設定する主体 |
|---|---|---|---|
| `withdrawn` | 取り下げ (作者の申し出・運営判断) | `_ts_takedown_id` = `S.n` (denylist の id = その取り下げの記録の S 番号) または `req:<申請の post ID>` (フォーム) | `wp ts apply-takedown` / 取り下げ申請の自動適用・承認 |
| `superseded` | 旧版 (同じ作品の新しい版がある) | `_ts_superseded_by` = 新版の `episode_id` (親 work なら新版の `work_slug`) | `wp ts import` (catalog の `superseded_by`。S.137〜S.145) |
| (無し) | 公開 | — | `wp ts import` |

- **優先順位: withdrawn > superseded > publish。** import は `withdrawn` が立っている投稿の `post_status` に触れない。`superseded` は catalog が決めるので import が付け外しする (無くなれば publish に戻す)。
- 波及: `withdrawn` は親を止めたら子孫 (話) 全部 (`commands.php` `descendants()` の現行ロジック)。`superseded` は話単位。全話が superseded の作品は親も `private` + `superseded`。
- 件数・索引・検索結果・RSS・将来の sitemap は **publish だけ**を数える (現行実装 `indexes.php` `ts_bunko_work_counts` / `render.php` `ts_bunko_source_map` の `post_status = 'publish'` を維持)。運営委員がログイン中は WP の既定で `private` が一覧・検索に混ざる — 帯 (S.118) で識別できるので許容する。
