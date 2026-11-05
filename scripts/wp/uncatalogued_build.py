#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""uncatalogued_build.py — 目録に載っていない収蔵物を拾う (T7.8、specs/S178.md)。

`novel/` 配下の本文ファイルのうち、正規目録 (lib1–73) にも旧目録 (lib01–09) にも対応する
エントリが無いものを `corpus=uncatalogued` として `catalog/episodes.jsonl` に足す。
落とした分は理由つきで `catalog/uncatalogued_excluded.jsonl` に全部残す。

`catalog_build.py` の後・`terms_build.py` の前に実行すること (episodes.jsonl を
読んで自分の corpus 分を差し替えて書き戻す = 何度実行しても同じ結果になる)。
メタデータの出所と除外の判定は scripts/wp/tslib/uncatalogued.py (specs/S243.md)。
"""
import argparse
import collections
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, uncatalogued  # noqa: E402
from tslib.checks import mark  # noqa: E402
from tslib.fileio import read_jsonl, write_jsonl, write_json, ensure_dir  # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--annex-base', default=paths.DEFAULT_ANNEX_BASE)
    ap.add_argument('--no-provenance', action='store_true')
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)
    records = read_jsonl(paths.episodes_path(root))
    keep, added, excluded, report = uncatalogued.build(root, records, args.annex_base,
                                                       not args.no_provenance)

    total = report['novel_body_files']
    uncovered = total - report['already_catalogued'] - report['added'] - report['excluded']
    report['uncovered_after'] = uncovered
    print('novel/ 本文ファイル %d = 既収蔵 %d + 追加 %d + 意図的除外 %d (残 %d)' %
          (total, report['already_catalogued'], report['added'], report['excluded'], uncovered))
    print('除外の内訳: %s' % report['excluded_by_reason'])
    print('題名 %d / 作者 %d (未解決 %d)  出所: %s' %
          (report['title_resolved'], report['author_resolved'],
           report['author_unresolved_count'],
           dict(collections.Counter(s for r in added for s in r['metadata_source']))))
    ok = uncovered == 0
    print('  [%s] novel/ の本文で catalog に載らないものが 0 (意図的除外を除く)' % mark(ok))

    if not args.check:
        cat = paths.catalog_dir(root)
        write_jsonl(paths.episodes_path(root), keep + added)
        write_jsonl(os.path.join(cat, 'uncatalogued_excluded.jsonl'), excluded)
        ensure_dir(paths.reports_dir(root))
        write_json(paths.report_path(root, 'uncatalogued_build.json'), report)
        print('wrote catalog/episodes.jsonl (+%d), catalog/uncatalogued_excluded.jsonl, '
              'catalog/reports/uncatalogued_build.json' % len(added))
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
