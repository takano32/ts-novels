#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""authors_build.py — 作者を `catalog/authors.json` にまとめる (T7.4、specs/S174.md)。

入力
  catalog/episodes.jsonl   … T7.1/T7.2 の出力 (episode_overrides.py 適用後)
  catalog/terms.json       … ts_world (= 共有感想板の一覧。作者板と区別するために要る)
  catalog/slug_overrides.yml … T7.5.1 の人手確認による確定 (confirmed / role: not-an-author)
  ~ts/kansou/bbs@log_.cgi  … **サイト自身の感想板一覧 (290 板)**。「表示名 → 板 id」の
                             権威ある対応表。目録から板がリンクされていない作者を拾う
  ~ts/kansou/bbs@log_*.cgi … 板一覧に載らない板ファイル (<title> から作者名が取れる)
  lib-index-*.html         … 作家別五十音目録。yomi (所属行) と題名→作者の第二情報源

出力
  catalog/authors.json             … 作者 1 人 1 レコード
  catalog/slug_overrides.yml       … 板を持たない作者の slug 候補 (T7.5.1 の人手確認の対象)
  catalog/reports/authors_build.json

同定の考え方と段取りは scripts/wp/tslib/authors.py (specs/S243.md)。このファイルは読み書きと表示だけ。
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, slugs as slugmod, authors  # noqa: E402
from tslib.checks import format_line  # noqa: E402
from tslib.fileio import read_jsonl, read_json, write_json, ensure_dir  # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT
DEFAULT_ANNEX_BASE = paths.DEFAULT_ANNEX_BASE


def shared_boards_of(root):
    path = paths.terms_path(root)
    if not os.path.exists(path):
        return set()
    return authors.shared_boards_of_terms(read_json(path))


def build(root, annex_base=DEFAULT_ANNEX_BASE):
    """入力を読んで authors.json とレポートを組む。
    戻り値 (payload, report, overrides, overrides_path, index_rows)。"""
    records = read_jsonl(paths.episodes_path(root))
    shared = shared_boards_of(root)
    index_rows, yomi_of = authors.parse_index(root)
    board_of_name, board_stats = authors.parse_boards(root, shared)
    overrides_path = paths.slug_overrides_path(root)
    overrides = slugmod.load_overrides(overrides_path)
    payload, report, overrides = authors.assemble(
        records, shared, index_rows, yomi_of, board_of_name, board_stats, overrides, annex_base,
        board_file_exists=lambda slug: os.path.exists(paths.kansou_board_path(root, slug)))
    return payload, report, overrides, overrides_path, index_rows


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--annex-base', default=DEFAULT_ANNEX_BASE)
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)
    payload, report, overrides, overrides_path, _ = build(root, args.annex_base)
    ok, results = authors.selftest(payload, report)
    report['selftest'] = [{'name': n, 'ok': o, 'detail': d} for n, o, d in results]
    report['selftest_ok'] = ok

    for line in authors.summary_lines(report):
        print(line)
    for n, o, d in results:
        print(format_line(n, o, d))

    if not args.check:
        ensure_dir(paths.reports_dir(root))
        write_json(paths.authors_path(root), payload)
        write_json(paths.report_path(root, 'authors_build.json'), report)
        slugmod.save_overrides(overrides_path, overrides, notes=authors.OVERRIDES_NOTES)
        print('wrote catalog/authors.json, catalog/reports/authors_build.json, '
              'catalog/slug_overrides.yml')
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
