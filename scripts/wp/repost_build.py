#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""repost_build.py — 作者本人の再掲から回収した本文を catalog に入れる (T7.8、specs/S179.md)。

検証済みの pixiv 2 件 (雨女 = 既存エントリに本文と来歴を追加 / きらいなもの→ＧＷ = 文庫未掲載として
`corpus=extern-repost` で新規収録) と、bc-cafe.net の 3 点 (T34.2、specs/S204.md) を扱う。
対象の一覧と各レコードの形は scripts/wp/tslib/reposts.py (specs/S243.md)。

`catalog_build.py` / `uncatalogued_build.py` の後に実行すること
(episodes.jsonl を読んで自分の corpus 分を差し替えて書き戻す = 何度でも同じ結果)。
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, reposts  # noqa: E402
from tslib.checks import mark  # noqa: E402
from tslib.fileio import read_jsonl, write_jsonl, write_json, write_text, ensure_dir  # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT
DEFAULT_HOLDING = os.path.expanduser('~/ts-novels-holding/stage_repost')


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--holding', default=DEFAULT_HOLDING)
    ap.add_argument('--acquired-at', default='2026-08-30')
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)
    records = read_jsonl(paths.episodes_path(root))
    records, bodies, report = reposts.build(root, records, args.holding, args.acquired_at)

    print('既存エントリに本文と来歴を追加: %s' % report['patched_existing'])
    print('文庫未掲載として新規収録: %s' % report['added_extern_repost'])
    if report['missing_inputs']:
        print('  ! 入力が見つからない: %s' % report['missing_inputs'], file=sys.stderr)
    ok = not report['missing_inputs']
    # 表示だけ。判断の値はレポートから読む (Checks に乗せない理由は tslib/checks.py)
    print('  [%s] 宣言した %d 件がすべて収録できた' % (mark(ok), report['items_declared']))

    if not args.check:
        body_dir = os.path.join(root, reposts.BODY_DIR)
        ensure_dir(body_dir)
        for eid, body in bodies.items():
            write_text(os.path.join(body_dir, eid + '.txt'), body)
        write_jsonl(paths.episodes_path(root), records)
        ensure_dir(paths.reports_dir(root))
        write_json(paths.report_path(root, 'repost_build.json'), report)
        print('wrote %s/*.txt (%d), catalog/episodes.jsonl, catalog/reports/repost_build.json'
              % (reposts.BODY_DIR, len(bodies)))
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
