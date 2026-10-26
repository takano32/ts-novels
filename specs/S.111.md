# S.111 秘密走査 secret_scan.py の仕様

- 種類: 仕様
- 状態: 有効
- 出典: docs/owner-feedback-2026-10.md の「secret_scan.py の仕様」
- 関連: T.16.3, S.110

## `secret_scan.py` の仕様

- 対象: `git ls-files` の全ファイルから `scripts/` `mu-plugins/` `themes/` `specs/` (2026-10-26 までは `docs/`) `catalog/` `takedown/` `content/` `.github/` を除いたもの (= 配信される原本)。`--root <dir>` で別ツリーを指せる (テスト用)。
- 検査 (いずれも**値は出力しない**):
  1. 先頭 2 バイトが `#!`
  2. Perl/シェル構文: 行頭の `use strict` / `use CGI` / `require "…"` / `sub 名 {` / `$ENV{` / `print "Content-type` / `open(IN|OUT|FH|DAT|LOG`
  3. `<input type=hidden …>` で name が `pass|pw|passwd|password|admin|adminpass|delkey|secret` かつ value が非空。ただし value が数字のみ (投稿番号・タイムスタンプ)、または英小文字 1 語 (6 字以下) でリポジトリ内の他ファイルに通常語として 50 回以上現れるものは除外
  4. `password=` `passwd=` `pass=` の直後に 4 字以上の値 (HTML 属性 `type=password` は除外)
  5. ファイル名が `.htaccess` `.htpasswd` `wp-config*` `*.env` `*.pem` `id_rsa*`
- 出力: `catalog/reports/secret_scan.json` = `{"scanned": N, "hits": [{"rule": 3, "path": "…", "line": 46}], "generated": "<ISO 8601>"}` と標準出力の要約 (`rule` は上の検査の番号)。path と行番号だけで、行の内容は書かない。
- 終了コード: hits が 0 なら 0、1 件以上なら 1 (`make check` が止まる)。検証済みの許容 (例: モード語の hidden 値) は `scripts/wp/secret_scan_allow.yml` に `path:行` と理由を書いて除外する。
- 2026-10-26 の全数走査 (S.110 の表) がこの規則の初回結果で hits 0。

**未決**: EXE 配信の可否。
**規模**: 走査スクリプト 半日 / 削除 2 ファイル即時 / T.35.2 は T.35。
