# S.56 本番ホスト側の制約が効くファイル

- 種類: 実測
- 状態: 有効
- 出典: docs/data-inventory.md の「本番ホスト(novels.xwp.jp)側の制約が効くファイル」
- 関連: T.16.4, S.63, S.110, T.40.5

## 本番ホスト(novels.xwp.jp)側の制約が効くファイル

**`.cgi` の件数は数え方で 1 件ずれる。文書で言及するときはどちらか明示すること。**

| 条件 | 件数 | 影響 |
|---|---:|---|
| **basename** が `.cgi` を含む(数え方: `git ls-files -z \| tr '\0' '\n' \| awk -F/ '{print $NF}' \| grep -c '\.cgi'`) | **9,021** | 本番では配信できない → 原本アーカイブ(GitHub Pages)恒久併存の理由。**環境の制約はファイル名基準なのでこちらが本則**。**点検の結果 (2026-10-26、S.110)**: 中身は CGI が出力した画面の保存 (HTML 8,966・画像 46・不明バイナリ 7・サーバのエラー出力 2) で、CGI のソース 0・秘密 0。GitHub Pages が `application/octet-stream` で返すのでブラウザではダウンロードになる (解消は T.16.5)。S.110 に当初書いた 9,019 は転記の誤りで、この 9,021 と同じもの |
| **フルパス**に `.cgi` セグメントを含む(数え方: 同上で `awk` を挟まない) | 9,022 | `~yaji/blog/mt-atom.cgi/weblog/blog_id=1` の 1 件だけディレクトリ名側。S.35・S.7・S.69〜S.71 の「9,022」はこの数え方 |
| `.pl` | 1 | `ts-novels.jp/kantan-cgi/counter@id_sd03205Y.pl` |
| `.shtml` | 2 | `entrance.shtml`・`~yays/library/entrance.shtml` |

詳細と実測は S.63。
