#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""secret_scan.py — 配信される原本に CGI のソースや秘密値が混じっていないかを走査する
(T16.3、規則は specs/S111.md、背景は specs/S110.md)。

対象
  `git ls-files` の全ファイルから scripts/ mu-plugins/ themes/ specs/ catalog/ takedown/
  content/ .github/ を除いたもの (= 配信される原本)。--root がリポジトリでなければ、その下の
  全ファイル (.git を除く) を同じ除外で走査する (試験用)。

検査 (rule の番号は specs/S111.md の検査の番号。**値は出力しない**)
  1. 先頭 2 バイトが `#!`
  2. 行頭の Perl/シェル構文 (`use strict` / `use CGI` / `require "…"` / `sub 名 {` / `$ENV{` /
     `print "Content-type` / `open(IN|OUT|FH|DAT|LOG`)
  3. `<input type=hidden …>` で name が pass|pw|passwd|password|admin|adminpass|delkey|secret、
     value が非空。value が数字のみ、または英小文字 1 語 (6 字以下) で他ファイルに通常語として
     50 回以上現れるものは除外
  4. `password=` `passwd=` `pass=` の直後に 4 字以上の値 (HTML 属性 `type=password` は除外)
  5. ファイル名が .htaccess / .htpasswd / wp-config* / *.env / *.pem / id_rsa*

出力
  catalog/reports/secret_scan.json (--root 指定時は <root>/catalog/reports/) =
  {"scanned": N, "hits": [{"rule": 3, "path": "…", "line": 46}], "generated": "<ISO 8601>"}
  と標準出力の要約。path と行番号だけで、行の内容は書かない。
  検証済みの許容は scripts/wp/secret_scan_allow.yml に `path:行` と理由を書いて除外する。

終了コード
  hits が 0 なら 0、1 件以上なら 1。

使い方
  python3 scripts/wp/secret_scan.py            … 走査してレポートを書く
  python3 scripts/wp/secret_scan.py --check    … 走査だけ (レポートを書かない。make check 用)
  python3 scripts/wp/secret_scan.py --root DIR … 別のツリーを走査する (試験用)
"""
import argparse
import datetime
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import fileio, paths, secret_rules as rules  # noqa: E402
from tslib.gitinfo import ls_files_z  # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT
DEFAULT_ALLOW = os.path.join(DEFAULT_ROOT, 'scripts', 'wp', 'secret_scan_allow.yml')


def list_files(root):
    """走査対象の相対パス (posix) の一覧。"""
    found = None
    if os.path.isdir(os.path.join(root, '.git')) or os.path.isfile(os.path.join(root, '.git')):
        found = ls_files_z(root)
    if found is None:
        found = []
        for d, dirs, files in os.walk(root):
            dirs[:] = [x for x in dirs if x != '.git']
            for f in files:
                found.append(os.path.relpath(os.path.join(d, f), root).replace(os.sep, '/'))
    return rules.select_files(found, lambda p: os.path.isfile(os.path.join(root, p)))


def scan(root, files):
    contents = {}
    for p in files:
        with open(os.path.join(root, p), 'rb') as f:
            contents[p] = f.read()
    return rules.scan(contents)


def load_allow(path):
    """scripts/wp/secret_scan_allow.yml: `allow:` の下に {at: "path:行", reason: "…"} を並べる。"""
    if not path or not os.path.exists(path):
        return []
    if fileio.yaml is None:
        raise SystemExit('secret_scan: 許容リストを読むには PyYAML が要る (make venv)')
    doc = fileio.read_yaml(path)
    try:
        return rules.parse_allow(doc, path)
    except ValueError as e:
        raise SystemExit(str(e))


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--allow', default=DEFAULT_ALLOW, help='許容リスト (既定: scripts/wp/secret_scan_allow.yml)')
    ap.add_argument('--check', action='store_true', help='レポートを書かない')
    args = ap.parse_args()
    root = os.path.abspath(args.root)

    files = list_files(root)
    raw = scan(root, files)
    allow = load_allow(args.allow)
    hits = rules.apply_allow(raw, allow)

    generated = datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0).isoformat()
    report = rules.report_of(files, raw, hits, generated)
    if not args.check:
        out = os.path.join(root, 'catalog', 'reports', 'secret_scan.json')
        fileio.ensure_parent(out)
        fileio.write_json(out, report)

    for line in rules.summary_lines(report, allow):
        print(line)
    return 0 if not hits else 1


if __name__ == '__main__':
    sys.exit(main())
