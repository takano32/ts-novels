#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""本文 HTML → Markdown 変換 (T7.6 / 設計 S37)

出力:
  bodies/<episode_id>.md            証明に通った話の本文 (Markdown)
  catalog/convert_report.jsonl      全話の判定 (status / mode / 証明結果 / 除去したクローム)
  catalog/reports/body_convert.json サマリ

変換の方針と無損失証明は scripts/wp/tslib/bodies.py (specs/S243.md)。このファイルは読み書きだけ。

使い方:
  python3 scripts/wp/body_convert.py                 # 全話を変換
  python3 scripts/wp/body_convert.py --limit 200     # 先頭 200 話だけ
  python3 scripts/wp/body_convert.py --id <episode_id>  # 1 話だけ (デバッグ用)
  python3 scripts/wp/body_convert.py --check         # 出力を再検証 (書き込まない)
"""
import argparse
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, bodies  # noqa: E402
from tslib.fileio import read_jsonl, write_jsonl, write_json, write_text, ensure_dir  # noqa: E402

ROOT = paths.REPO_ROOT


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--limit', type=int)
    ap.add_argument('--id')
    ap.add_argument('--check', action='store_true', help='書き込まずに判定だけ行う')
    args = ap.parse_args()

    eps = read_jsonl(paths.episodes_path(ROOT))
    if args.id:
        eps = [e for e in eps if e['episode_id'] == args.id]
    if args.limit:
        eps = eps[:args.limit]

    bodies_dir = paths.bodies_dir(ROOT)
    summary_path = paths.report_path(ROOT, 'body_convert.json')
    if not args.check:
        ensure_dir(bodies_dir)
        ensure_dir(os.path.dirname(summary_path))

    rows = []
    for ep in eps:
        r = bodies.convert_safely(ep, ROOT)
        md = r.pop('md', None)
        if md is not None and not args.check:
            write_text(os.path.join(bodies_dir, r['episode_id'] + '.md'), md)
        rows.append(r)

    if not args.check:
        write_jsonl(paths.convert_report_path(ROOT), rows)

    summary = bodies.summarize(rows, len(eps))
    if not args.check:
        write_json(summary_path, summary, indent=1, newline=False)
    print(json.dumps(summary, ensure_ascii=False, indent=1))
    # 不合格の代表例
    bad = [r for r in rows if r['status'] == 'raw-fallback'][:8]
    for r in bad:
        print('  FALLBACK', r.get('mode'), r.get('source_path'), '|', str(r.get('reason'))[:90])


if __name__ == '__main__':
    main()
