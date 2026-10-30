# S.80 削除: /removed/ に掲載

- 種類: 手順
- 状態: 一部失効 (S.131 で上書き。動的生成)
- 出典: docs/removal-runbook.md の「/removed/ に掲載」
- 関連: T.32.2, T.23.6, S.112, S.121, S.131

## `/removed/` に掲載 (1 週間以内)

- 掲載するのは **作者名 + 作品名 + 理由の要約 (1 行) + 削除日**のみ。
  依頼者の身元・依頼文は載せない。作者本人が名前の掲載も望まない場合は
  「作者の申し出により 1 作品を削除 (YYYY-MM)」の形に匿名化する。
- 目的は**再収蔵の防止**。将来の回収作業者 (人間・実行セッション問わず) が
  「なぜここに穴があるのか」を確認できる場所を 1 つにする。

いま (2026-10-30) の `/removed/` は手書きの `content/pages/removed.html` で、`wp ts sync-pages` で WP に入れる (S.112)。
denylist の `public_note` から作る仕組みはまだ無い。`wp ts apply-takedown` は `public_note` を投稿のメタ `_ts_public_note` に写していて (S.121)、
これを読んで `/removed/` を作るのが S.131 (T.26)。それまでは `content/pages/removed.html` に 1 行足してコミット・push・配備し、ホームから流す。

```sh
cd ~/GitHub/ts-novels && git pull --ff-only
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts sync-pages'
curl -s "https://novels.xwp.jp/removed/?_cb=$RANDOM" | grep -c '<li>'
```
