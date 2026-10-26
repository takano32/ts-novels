# S.63 .cgi などの拡張子がブロックされる制約

- 種類: 実測
- 状態: 有効
- 出典: docs/environment.md の「.cgi などの拡張子がブロックされる制約」
- 関連: S.56, S.110

## `.cgi` などの拡張子がブロックされる制約 ★最重要

### 実測結果 (外部からの curl。すべてキャッシュバスター付き)

| リクエストした URL | 応答 |
|---|---|
| `/` | **200** |
| `/nonexistent-probe.html` (存在しない) | **404** (WordPress の 404) |
| `/probe.cgi` (存在しない) | **403** |
| `/probe.pl` `/probe.py` `/probe.shtml` | **403** |
| `/probe.cgi.html` (`.cgi` の後ろに別拡張子) | **403** (**改名では回避できない**) |
| `/probe@x.cgi` `/x@y.cgi` (`@` マングル名) | **403** |
| `/probe.cgi?a=1` | **403** |
| `/probe.CGI` (大文字) | **404** (ルールは**大文字小文字を区別する**) |
| `/a/probe.cgi` (サブディレクトリ・存在しない) | **404** |
| `/~ts/kansou/bbs@log_johdan.cgi` (存在しない) | **404** |
| `/~x/y.cgi` | **404** |

403 のボディは **XSERVER 製の静的 403 ページ**(EUC-JP、2,843 バイト、`etag` 付き)。

### 仕組み — `.htaccess` に明示のブロックがある

`public_html/.htaccess` の末尾に、Xserver の WordPress パネルが自動生成したブロックが入っている:

```apache
# BEGIN XS_WPPANEL_WP_ROOT_CGI_SSI
<FilesMatch "\.(cgi|pl|py|sh|phar|shtml|shtm)(\.|/|$)">
  Require all denied
</FilesMatch>
# END XS_WPPANEL_WP_ROOT_CGI_SSI
```

- 正規表現の `(\.|/|$)` が「`.cgi` の直後にドットかスラッシュが来ても一致」を意味する。
  **`foo.cgi.html` への改名が効かない理由はこれ**。
- 対象拡張子は `.cgi .pl .py .sh .phar .shtml .shtm` の 7 種。

### これまでの記述との差分(要修正)

S.7 と S.35 は「**前段 nginx が一律 403**。RemoveHandler・改名・RewriteRule のいずれでも
回避不能(**Apache に届かない**)」と書いているが、実測はこれと部分的に食い違う:

| 記述 | 実測 |
|---|---|
| 「前段 nginx が」 | **Apache 層にも明示のブロックがある**(上の `XS_WPPANEL_WP_ROOT_CGI_SSI`)。403 ボディが `etag`/`last-modified` 付きの静的ファイルであることも、ファイルを持つサーバ(Apache)が返した形に見える |
| 「一律 403」 | **観測できる 403 はドキュメントルート直下(パス 1 セグメント)のみ**。`/a/probe.cgi` のようにサブディレクトリにあるものは 404 が返り、WordPress まで到達している |
| 「改名でも回避不能」 | **正しい**。`(\.|/|$)` により `.cgi.html` も拒否される(実測で確認) |

**結論は変わらない** — basename に `.cgi` を含む 9,021 ファイル(フルパス基準では 9,022)を
このホストで配信する前提には立てない。
原本アーカイブを GitHub Pages に恒久併存させる S.35 の決定はそのまま維持する。
ただし**理由の記述は上のとおり訂正が要る**。

### 未検証(この調査でできなかったこと)

- **実在する `.cgi` ファイルがサブディレクトリに置かれたときに 403 になるか 200 になるか**。
  `.htaccess` の `FilesMatch` はサブディレクトリにも継承されるので **403 になる見込み**だが、
  現在サーバ上に `.cgi`/`.pl`/`.sh`/`.shtml`/`.py` のファイルは **1 つも存在せず**、
  読み取り専用の制約下ではテストファイルを置けなかったため未確定。
- 確かめるなら S.10 の「本番の実験は `_probe/` で行う決まり」に従い `public_html/_probe/` に 1 ファイルだけ置いて
  curl し、**終わったら必ず消す**こと。
- `XS_WPPANEL_*` はパネル管理のブロックなので、**パネル側にこの機能の ON/OFF がある可能性**がある
  (高野さんの確認事項)。仮に外せてもルート直下は別ルールで塞がれている可能性が残るため、
  外す判断の前に必ず `_probe/` で実測すること。

### リポジトリ側で影響を受けるファイル

| 条件 | 件数 |
|---|---:|
| basename が `.cgi` を含む | 9,021 (フルパスに `.cgi` セグメントを含む数え方なら 9,022 → S.56) |
| `.pl` | 1 (`ts-novels.jp/kantan-cgi/counter@id_sd03205Y.pl`) |
| `.shtml` | 2 (`entrance.shtml`・`~yays/library/entrance.shtml`) |
