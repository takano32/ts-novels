# S.131 /removed/ の動的生成

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「/removed/ の動的生成」
- 関連: T.26.3.6, S.80

## `/removed/` の動的生成

- ショートコード `[ts_removed_list]`: `ts_work` の親投稿 (post_parent=0) で `private` かつ `_ts_visibility=withdrawn` のものを `_ts_takedown_id` ごとに 1 行、新しい順に列挙。
- 文言: `_ts_public_note` があればそれ (denylist 由来は `public_note`、申請由来は適用時に生成)。申請由来の生成規則: `allow_name` が真なら「『題名』(ペンネームさん) — 作者の申し出により掲載していません (YYYY 年 MM 月)」、偽なら「作者の申し出により 1 作品を掲載していません (YYYY 年 MM 月)」。理由は公表しない。
- `content/pages/removed.html` は見出し・前置き・ショートコードだけにし、手書きの一覧は消す (現状の手書き 9 行は denylist の `public_note` に移っている)。
