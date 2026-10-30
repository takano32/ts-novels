# S.111 秘密走査 secret_scan.py の仕様

- 種類: 仕様
- 状態: 有効
- 出典: docs/owner-feedback-2026-10.md の「secret_scan.py の仕様」
- 関連: T.16.3, S.110, S.210

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

## 実装 (2026-10-30、T.16.3)

- `scripts/wp/secret_scan.py`。引数なしで走査して `catalog/reports/secret_scan.json` を書く。`--check` は書かずに走査だけ (`make check` が使う)。`--root <dir>` は git の作業ツリーでなければ `.git` を除く全ファイルを同じ除外で走査する。
- 対象の除外は上の一覧どおり。`docs/` は 2026-10-26 から説明文書の置き場で、上の一覧に無いので走査する (2026-10-30 の時点で docs/ の該当も 0)。
- 検査 2 の「行頭」は字下げを許す。検査 3 の「他ファイルに通常語として」は、走査対象のうちその話のファイル以外での語としての出現回数 (前後が英数字でない) の合計。検査 4 の値は引用符・空白・`&` `<` `>` `;` `,` `)` までの 4 字以上で、直前が英数字の語 (`bypass=` など) は見ない。検査 5 の行番号は 0。
- 許容リスト `scripts/wp/secret_scan_allow.yml` は `allow:` の下に `{at: "path:行", reason: "…"}` を並べる。2026-10-30 の時点で 0 件。レポートには該当のほかに許容で除外した件数 `allowed` も書く。
- 初回の結果 (2026-10-30、HEAD 7d4778f4 の配信物 18,036 ファイル): 該当 0・終了コード 0。除外の規則を外すと検査 3 に S.110 の表の `name=admin` 5 ファイルだけが出る (除外が想定どおり効いている)。試験: `#!/usr/bin/perl` 1 行のファイルを置いた一時ツリーを `--root` で指すと検査 1 で終了コード 1。検査 2〜5 の疑似ファイルもそれぞれ検出した。

**未決**: EXE 配信の可否。
**規模**: 走査スクリプト 半日 / 削除 2 ファイル即時 / T.35.2 は T.35。
