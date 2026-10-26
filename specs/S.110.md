# S.110 CGI の配信実態 (パスワード等の埋め込み懸念への回答)

- 種類: 実測
- 状態: 有効
- 出典: docs/owner-feedback-2026-10.md の「CGI スクリプトがそのまま落ちてくる」
- 関連: T.16, S.111, S.56, T.40.5

## CGI スクリプトがそのまま落ちてくる — 当時のパスワード等の埋め込み懸念 ★最重要

**現状 (実測 2026-10-26。対象 = リポジトリ = GitHub Pages で配信中の全 18,013 ファイル)**:

| 検査 | 結果 |
|---|---|
| 先頭 2 バイトが `#!` のファイル | **0** |
| Perl 構文 (`use strict` / `sub x {` / `$ENV{` / `print "Content-type` / `open(IN` 等) を含むファイル | **0** |
| 名前に `.cgi` を含むファイル | **9,019**。中身: HTML (CGI が出力した画面の保存) **8,966** / 画像 46 (captcha PNG 37・GIF 9) / 不明バイナリ 7 (`~ezpe/cgi-bin/noteky/` の 7 本) / **サーバのエラー出力 2** |
| エラー出力 2 本 | `www2.sts.co.jp/~yaji/cgi-bin/minibbs2.cgi` (72B「script produced no output」+ Windows のフルパス) と `~yaji/blog/mt-atom.cgi/weblog/blog_id=1` (1,002B Perl の @INC エラー + `/home/<ユーザ>/public_html/…`)。**サーバ側のパスを晒す**が秘密値は無い |
| `password` / `pass` / `パスワード` を含む行 | 掲示板の削除キー**入力欄** (空の `<input type=password>`) と本文の語句のみ |
| hidden 入力で名前が pass/key/admin、値が非空 | `name=KEY` 10 桁 (投稿番号、`special/03summer/` 2 + 写し 2) / `name=key` 14 桁 (タイムスタンプ、`ts.raa0121.info/2ndbbs/` 5) / **`name=admin` 6 文字** (矢治さんの掲示板 `~bbs/cgi-bin/bbs.*`・`yajibbs.*`・`www2.sts.co.jp/~yaji/cgi-bin/hihyou.cgi` の 5 ファイル)。後者は英単語 1 語のモード指定で、同じ語がリポジトリ内 465 ファイルに通常の単語として出現、直前の説明も「削除キー欄にマスターキーを入力」= 鍵は入力式 → **秘密ではない** (値はこの S に書かない) |
| `.htaccess` / `.htpasswd` / 設定ファイル | **0** |
| 実行ファイル・アーカイブ | `special/03summer/zetsubou.1〜3` + `~yays/library/special/03summer/zetsubou.1〜3` + `.bat` = NScripter 製ゲーム「絶望未満」の **Windows EXE を 3 分割**したもの (2.1MB × 3、`COPY /B` で結合)。LZH 5 本 (fKiss ビューア Mac 版 + C ソース・KiSS ローダ・KiSS データ・Excel 2 本 (bc-cafe のカードリスト)) |
| git 履歴 | 削除済みの `.pl` / `.pm` / `.cgi` は 0 (履歴にも配信中と同じものしか無い)。public リポジトリ・`raw-original` タグあり |

「そのまま落ちてくる」の正体: GitHub Pages は `.cgi` (と拡張子なし) を **`application/octet-stream`** で返す (実測: `~ts/kansou/bbs@log_marie.cgi` → 200 octet-stream) → ブラウザがダウンロードする。本番 WP には `.cgi` を 1 つも置いていない (S.63 の「未検証」の節)。WP サイトからの導線は、固定ページ `/archive/` の区画リンク → 原本内のリンク、書誌カード「無改変の原本を読む」(これ自体は `.html`) → 原本内のリンク、そして **`.cgi` への直リンクを含む投稿 5 件** (`ts_doc` のにせ乙女パラダイス 3 頁 → `~ezpe/cgi-bin/resbbs4y/resbbs4.cgi` 等、`ts_work` の `shining_heaven-main` と `koromogae1` (原本そのまま表示の 2 話) → `kansou.cgi`)。いずれも中身は HTML の保存画面。

**結論**: **CGI のソースコードの流出・パスワードの露出は確認できなかった。** 所有者が心配した状況ではない。ただし「落ちてくる」体験そのものは本物で、資料館として直す価値がある。

**仕様**:

1. **恒久化**: `scripts/wp/secret_scan.py` を `make check` に組み込み、新規収蔵のたびに 0 件を確認する (規則は S.111)。
2. **小さな対処 (即時)**: エラー出力 2 ファイルをリポジトリから削除 (史料価値なし、サーバパス露出)。EXE 分割 (2 系統 3 本 + bat) の配信を続けるかは所有者判断 (ゲーム本体は企画の作品だが、実行ファイルの配信はリスク。原本は git 履歴に残る)。
3. **ダウンロード問題の解消**: T.35.2 `annex_inject.py` (ビルド注入) で `.cgi` 保存画面を HTML として配信できる形にする。案: 各 `x.cgi` を `x.cgi/index.html` に置き換え + `<base href>` 注入 (GitHub Pages は `/x.cgi` を `/x.cgi/` に 301 して `text/html` で返す。**要検証**)。T.36 の `/boards/` ができればWP サイト側の感想板リンクは `.cgi` に頼らなくなる。
4. 文書: S.63 の「これまでの記述との差分」の「前段 nginx」訂正は既記載。S.110 の結果を S.56 に 1 行で反映。
