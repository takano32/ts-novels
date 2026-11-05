#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""episode_overrides.py — 話 1 件単位の人手上書きを episodes.jsonl に適用する。

入力  catalog/episode_overrides.yml … 鍵 = source_path (`#anchor` 可)
      catalog/episodes.jsonl        … T7.1/T7.2/T7.8 の出力
出力  catalog/episodes.jsonl        … 上書きを適用して書き戻す
      catalog/reports/episode_overrides.json

`make catalog` では repost_build の直後・terms_build の直前に走らせる
(terms / authors / works が上書き後の episodes.jsonl を読むように)。
ロジックと上書きの決まりは scripts/wp/tslib/overrides.py (specs/S243.md)。
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths  # noqa: E402
from tslib.fileio import read_jsonl, write_jsonl, write_json, ensure_dir, exit_without_yaml  # noqa: E402
from tslib.overrides import load_overrides, apply_overrides, selftest, report_of  # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT
DEFAULT_ANNEX_BASE = paths.DEFAULT_ANNEX_BASE


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--annex-base', default=DEFAULT_ANNEX_BASE)
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    exit_without_yaml()
    root = os.path.abspath(args.root)
    cat = paths.catalog_dir(root)
    path = paths.episodes_path(root)
    records = read_jsonl(path)
    before_ids = [r['episode_id'] for r in records]
    overrides = load_overrides(os.path.join(cat, 'episode_overrides.yml'))
    applied, problems = apply_overrides(records, overrides, args.annex_base)
    checks = selftest(applied, problems, overrides)
    ids_unchanged = before_ids == [r['episode_id'] for r in records]
    ok = checks.ok and ids_unchanged

    print('episode_overrides: %d 行 / 変更 %d 話 / 適用済み %d 話' %
          (len(overrides), sum(1 for a in applied if a['changes']),
           sum(1 for a in applied if a['already_applied'])))
    for a in applied:
        for c in a['changes']:
            print('  %s  %s: %r -> %r' % (a['episode_id'], c['field'], c['from'], c['to']))
    checks.print()

    report = report_of(overrides, applied, problems, ids_unchanged, checks, ok)
    if not args.check:
        ensure_dir(paths.reports_dir(root))
        write_jsonl(path, records)
        write_json(paths.report_path(root, 'episode_overrides.json'), report)
        print('wrote catalog/episodes.jsonl, catalog/reports/episode_overrides.json')
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
