# S.55 CGI スナップショットの命名規約

- 種類: 実測
- 状態: 有効
- 出典: docs/data-inventory.md の「6. CGI スナップショットの命名規約」
- 関連: なし

## 6. CGI スナップショットの命名規約(`@` マングル)

BBS・CGI ページは**クエリつき URL** なので、そのままではファイルにできない。
本リポジトリは以下の規約でファイル名化してある(実装: `scripts/place_convert.py` の `mangle_q`)。

```
<stem>@<query>.<ext>       query は  % を除去、= & / ? をすべて _ に置換
```

| 元 URL | リポジトリ上のパス |
|---|---|
| `~ts/kansou/bbs.cgi?log=johdan` | `~ts/kansou/bbs@log_johdan.cgi` |
| `~ts/kansou/bbs.cgi?res=123&log=johdan` | `~ts/kansou/bbs@res_123_log_johdan.cgi` |
| `cgi-bin/manage2.cgi?search` | `cgi-bin/manage2@search.cgi` |
| `novel/200312/index.html?D=A` (autoindex の並べ替え) | `novel/200312/index@D_A.html` |
| `~ezpe/cgi-bin/noteky/noteky.cgi?c=noteread&f=9&id=…&ff=on` | `~ezpe/cgi-bin/noteky/noteky@c_noteread_f_9_id_…_ff_on.cgi` |

**注意 1**: `~yays/cgi-bin/` の**クエリなしの基準ページ**(`resbbs4.cgi`・`noteky.cgi` 等)は、
実体ではなく **`www14.big.or.jp` の広告 soft-404 ページ**である
(`<title>1GB レンタルサーバー・プロバイダー Amusement BiG-NET | …</title>`)。
中身が入っているのは `@` つきのスナップショットのほう。

**注意 2**: 同じマングル規約が **3 つのスクリプトで微妙に違う実装**になっている(→ S.57 の (5))。

**注意 3**: シェルの `git ls-files` 等で `~` 始まりのパスを扱うときは必ずクォートすること
(`git ls-files '~ts'`)。またクエリ由来の壊れた文字を含むパスが 14 件あり、
`git ls-files` はそれを `"…\357\277\275…"` の形でクォート出力する。
数えるときは `git ls-files -z` を使うのが安全。
