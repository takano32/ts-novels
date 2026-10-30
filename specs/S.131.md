# S.131 /removed/ の動的生成

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「/removed/ の動的生成」
- 関連: T.26.3.6, S.80, S.121

## `/removed/` の動的生成

- ショートコード `[ts_removed_list]`: `ts_work` の親投稿 (post_parent=0) で `private` かつ `_ts_visibility=withdrawn` のものを `_ts_takedown_id` ごとに 1 行、新しい順に列挙。
- 文言: `_ts_public_note` があればそれ (denylist 由来は `public_note`、申請由来は適用時に生成)。申請由来の生成規則: `allow_name` が真なら「『題名』(ペンネームさん) — 作者の申し出により掲載していません (YYYY 年 MM 月)」、偽なら「作者の申し出により 1 作品を掲載していません (YYYY 年 MM 月)」。理由は公表しない。
- `content/pages/removed.html` は見出し・前置き・ショートコードだけにし、手書きの一覧は消す (現状の手書き 9 行は denylist の `public_note` に移っている)。

## 実装の記録 (2026-10-30、T.26.3。未配備)

- 「新しい順」はお申し出の日の新しい順 (同じ日は記録の番号の順)。投稿にはその日が無いので、メタ `_ts_takedown_date` を足した。apply-takedown は denylist の `requested_at` を、申請の適用は確認時刻の日付を書く。配備の後に apply-takedown を流すと、いまの取り下げにも入る (メタだけ直す)。
- 親投稿に限らず、取り下げ中の投稿を記録 (`_ts_takedown_id`) ごとにまとめて 1 行にする (話だけを指した申請も出るように)。
- `_ts_public_note` の無い記録 (S.231・S.232。掲示を保留しているもの) は出さない。いまの denylist では既存 9 件 + Marie 氏の 10 行になる (単体の試験で確かめた)。
- 申請由来の文言の題名の括弧は、既存の denylist の `public_note` に合わせて「」にした。名前を出さないときの作品数は、その申請で非公開にした作品の数。
- ショートコードを置いたページには `nocache_headers()` を付ける (自動適用がすぐに一覧へ出るように)。
- 一覧が空のときは「いま掲載していない作品はありません。」。
